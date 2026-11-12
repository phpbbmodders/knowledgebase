<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\inc;

require_once __DIR__ . '/kb_extensions_test.php';
if (!defined('IN_PHPBB')) define('IN_PHPBB', true);
if (!defined('CONFIG_TABLE')) define('CONFIG_TABLE', 'phpbb_config');
require_once (getenv('PHPBB_ROOT_PATH') ?: dirname(__DIR__, 4)) . '/includes/functions.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';
require_once dirname(__DIR__) . '/inc/kinerity_import.php';
foreach (glob(dirname(__DIR__) . '/migrations/*.php') as $migration_file)
{
	require_once $migration_file;
}

/** kinerity_import with tiny batches, to exercise resuming between calls. */
class kinerity_import_small_batches extends kinerity_import
{
	public const CATEGORY_BATCH = 2;
	public const ARTICLE_BATCH = 3;
}

/**
 * kinerity_import that, when it claims its first row, lets a whole second
 * import run first: what happens when an admin enables the extension again
 * while the first request is still converting the same batch.
 */
class kinerity_import_racing extends kinerity_import
{
	private bool $raced = false;

	protected function claim(string $table, string $column, int $id): bool
	{
		if (!$this->raced)
		{
			$this->raced = true;
			$other = new kinerity_import($this->db, $this->db_tools, $this->table_prefix);
			while ($other->import_categories(0) !== true);
			while ($other->import_articles(0) !== true);
		}

		return parent::claim($table, $column, $id);
	}
}

/**
 * Importing a board's kinerity/knowledgebase data: moving its tables aside,
 * converting categories, articles and permissions into this extension's real
 * schema (built from its own migrations), and cleaning up after kinerity.
 */
class kb_kinerity_import_test extends \PHPUnit\Framework\TestCase
{
	private $db;
	private $tools;
	private $sqlite_fixture;

	/** kinerity acl option ids used by the fixture */
	private const OPTIONS = [
		'u_kb_read' => 101, 'u_kb_post' => 102, 'u_kb_edit' => 103, 'u_kb_delete' => 104, 'u_kb_noapprove' => 105,
		'm_kb_edit' => 106, 'm_kb_delete' => 107, 'm_kb_approve' => 108, 'm_kb_chgposter' => 109, 'a_kb_manage' => 110,
		// This extension's board-wide permissions
		'u_kb_view' => 201, 'a_manage_kb' => 202,
	];

	protected function tearDown(): void
	{
		if ($this->tools instanceof \phpbb\db\tools\doctrine) $this->tools->get_connection()->close();
		if ($this->db) $this->db->sql_close();
		if ($this->sqlite_fixture) @unlink($this->sqlite_fixture);
	}

	public function engines()
	{
		return [['sqlite'], ['mysql'], ['postgres']];
	}

	private function connect(string $engine): void
	{
		if ($engine === 'sqlite')
		{
			$this->db = new \phpbb\db\driver\sqlite3();
			if (class_exists(\phpbb\db\tools\doctrine::class))
			{
				$this->sqlite_fixture = tempnam(sys_get_temp_dir(), 'kb-kinerity-');
				$this->assertTrue($this->db->sql_connect($this->sqlite_fixture, '', '', ''));
				$this->tools = new \phpbb\db\tools\doctrine(\phpbb\db\doctrine\connection_factory::get_connection_from_params('sqlite3', $this->sqlite_fixture));
				$this->tools->set_table_prefix('phpbb_');
			}
			else
			{
				$this->assertTrue($this->db->sql_connect(':memory:', '', '', ''));
				$this->tools = new \phpbb\db\tools\sqlite3($this->db);
			}
		}
		else
		{
			$port = getenv('KB_TEST_' . strtoupper($engine) . '_PORT');
			if (!$port) $this->markTestSkipped('Set the fixture database port to run integration tests');
			$this->assertTrue(ctype_digit($port));
			$this->db = $engine === 'mysql' ? new \phpbb\db\driver\mysqli() : new \phpbb\db\driver\postgres();
			$this->assertIsObject($this->db->sql_connect('127.0.0.1', $engine === 'mysql' ? 'root' : 'postgres', '', 'kb_fixture', (int) $port));
			if (class_exists(\phpbb\db\tools\doctrine::class))
			{
				$this->tools = new \phpbb\db\tools\doctrine(\phpbb\db\doctrine\connection_factory::get_connection_from_params(
					$engine === 'mysql' ? 'mysqli' : 'postgres', '127.0.0.1', $engine === 'mysql' ? 'root' : 'postgres', '', 'kb_fixture', $port
				));
				$this->tools->set_table_prefix('phpbb_');
			}
			else
			{
				$this->tools = $engine === 'mysql' ? new \phpbb\db\tools\tools($this->db) : new \phpbb\db\tools\postgres($this->db);
			}
		}

		$this->drop_all_tables();
		$this->create_core_tables();
	}

	/** Every table the fixture or the import touches, from earlier runs too. */
	private function drop_all_tables(): void
	{
		$tables = [
			'acl_options', 'acl_groups', 'acl_users', 'acl_roles_data', 'users', 'ext',
			'notification_types', 'notifications', 'user_notifications',
			'kb_kinerity_articles', 'kb_kinerity_categories', 'kb_kinerity_article_category', 'kb_article_category',
		];
		foreach ($this->our_schema() as $table => $unused)
		{
			$tables[] = substr($table, strlen('phpbb_'));
		}
		foreach (array_unique($tables) as $table)
		{
			if ($this->tools->sql_table_exists('phpbb_' . $table))
			{
				$this->tools->sql_table_drop('phpbb_' . $table);
			}
			// phpBB 4.0's tools leave a PostgreSQL table's sequence behind
			if ($this->db->get_sql_layer() === 'postgres')
			{
				$this->db->sql_query('DROP SEQUENCE IF EXISTS phpbb_' . $table . '_seq');
			}
		}
	}

	/** The phpBB core tables the import reads or writes, cut down to the columns it uses. */
	private function create_core_tables(): void
	{
		$tables = [
			'phpbb_acl_options'        => ['auth_option_id' => ['UINT', 0], 'auth_option' => ['VCHAR:50', ''], 'is_global' => ['BOOL', 0], 'is_local' => ['BOOL', 0]],
			'phpbb_acl_groups'         => ['group_id' => ['UINT', 0], 'forum_id' => ['UINT', 0], 'auth_option_id' => ['UINT', 0], 'auth_role_id' => ['UINT', 0], 'auth_setting' => ['TINT:2', 0]],
			'phpbb_acl_users'          => ['user_id' => ['UINT', 0], 'forum_id' => ['UINT', 0], 'auth_option_id' => ['UINT', 0], 'auth_role_id' => ['UINT', 0], 'auth_setting' => ['TINT:2', 0]],
			'phpbb_acl_roles_data'     => ['role_id' => ['UINT', 0], 'auth_option_id' => ['UINT', 0], 'auth_setting' => ['TINT:2', 0]],
			'phpbb_users'              => ['user_id' => ['UINT', 0], 'user_permissions' => ['MTEXT', ''], 'user_perm_from' => ['UINT', 0]],
			'phpbb_ext'                => ['ext_name' => ['VCHAR', ''], 'ext_active' => ['BOOL', 0]],
			'phpbb_notification_types' => ['notification_type_id' => ['USINT', 0], 'notification_type_name' => ['VCHAR', ''], 'notification_type_enabled' => ['BOOL', 1]],
			'phpbb_notifications'      => ['notification_id' => ['UINT', 0], 'notification_type_id' => ['USINT', 0]],
			'phpbb_user_notifications' => ['item_type' => ['VCHAR', ''], 'user_id' => ['UINT', 0]],
		];
		foreach ($tables as $table => $columns)
		{
			$this->tools->sql_create_table($table, ['COLUMNS' => $columns]);
		}
	}

	/**
	 * This extension's tables as its migrations leave them: every migration's
	 * update_schema(), in dependency order, merged into final table layouts.
	 *
	 * @return array<string, array> table => sql_create_table() layout
	 */
	private function our_schema(): array
	{
		static $schema = null;
		if ($schema !== null)
		{
			return $schema;
		}

		$classes = [];
		foreach (glob(dirname(__DIR__) . '/migrations/*.php') as $file)
		{
			$classes[] = '\\phpbbmodders\\knowledgebase\\migrations\\' . basename($file, '.php');
		}
		$ordered = [];
		$visit = function ($class) use (&$visit, &$ordered, $classes) {
			$class = '\\' . ltrim($class, '\\');
			if (isset($ordered[$class]) || !in_array($class, $classes, true)) return;
			foreach ($class::depends_on() as $dependency) $visit($dependency);
			$ordered[$class] = true;
		};
		foreach ($classes as $class) $visit($class);

		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$tools = $this->createMock(\phpbb\db\tools\tools_interface::class);
		$schema = [];
		foreach (array_keys($ordered) as $class)
		{
			$changes = (new $class(new \phpbb\config\config([]), $db, $tools, '', 'php', 'phpbb_', []))->update_schema();
			foreach ($changes['add_tables'] ?? [] as $table => $layout)
			{
				$schema[$table] = $layout;
			}
			foreach ($changes['add_columns'] ?? [] as $table => $columns)
			{
				$schema[$table]['COLUMNS'] = array_merge($schema[$table]['COLUMNS'], $columns);
			}
			foreach ($changes['drop_columns'] ?? [] as $table => $columns)
			{
				$schema[$table]['COLUMNS'] = array_diff_key($schema[$table]['COLUMNS'], array_flip($columns));
			}
			foreach ($changes['change_columns'] ?? [] as $table => $columns)
			{
				$schema[$table]['COLUMNS'] = array_merge($schema[$table]['COLUMNS'], $columns);
			}
			foreach ($changes['drop_tables'] ?? [] as $table)
			{
				unset($schema[$table]);
			}
		}

		return $schema;
	}

	/** Create this extension's tables (what enabling it does after the set-aside) with its options. */
	private function install_our_tables(): void
	{
		foreach ($this->our_schema() as $table => $layout)
		{
			$this->tools->sql_create_table($table, $layout);
		}

		$options = ['kb_u_add', 'kb_u_edit', 'kb_u_delete', 'kb_u_add_noapprove', 'kb_m_edit', 'kb_m_delete', 'kb_m_approve', 'kb_m_change_author'];
		foreach ($options as $option)
		{
			$this->insert('phpbb_kb_options', ['auth_option' => $option, 'is_global' => 0, 'is_local' => 1]);
		}
	}

	/** kinerity 1.2.1's tables, from its migrations release_0_0_1 and release_1_1_0. */
	private function install_kinerity_tables(bool $with_edit_name = true): void
	{
		$articles = [
			'article_id' => ['UINT', null, 'auto_increment'], 'article_poster_id' => ['UINT', 0], 'article_poster_name' => ['VCHAR:255', ''],
			'article_poster_colour' => ['VCHAR:6', ''], 'article_time' => ['TIMESTAMP', 0], 'article_edit_time' => ['TIMESTAMP', 0],
			'enable_bbcode' => ['BOOL', 1], 'enable_smilies' => ['BOOL', 1], 'enable_magic_url' => ['BOOL', 1],
			'article_title' => ['VCHAR:255', ''], 'article_description' => ['MTEXT_UNI', ''], 'article_text' => ['MTEXT_UNI', ''],
			'bbcode_bitfield' => ['VCHAR:255', ''], 'bbcode_uid' => ['VCHAR:8', ''], 'article_visibility' => ['TINT:3', 0], 'article_views' => ['UINT', 0],
		];
		if ($with_edit_name)
		{
			$articles['article_edit_name'] = ['VCHAR:255', ''];
		}
		$this->tools->sql_create_table('phpbb_kb_articles', ['COLUMNS' => $articles, 'PRIMARY_KEY' => 'article_id']);
		$this->tools->sql_create_table('phpbb_kb_article_category', ['COLUMNS' => ['category_id' => ['UINT', 0], 'article_id' => ['UINT', 0]]]);
		$this->tools->sql_create_table('phpbb_kb_categories', ['COLUMNS' => [
			'category_id' => ['UINT', null, 'auto_increment'], 'left_id' => ['UINT', 0], 'right_id' => ['UINT', 0],
			'category_name' => ['VCHAR:255', ''], 'category_description' => ['TEXT_UNI', ''], 'bbcode_bitfield' => ['VCHAR:255', ''],
			'bbcode_options' => ['TIMESTAMP', 7], 'bbcode_uid' => ['VCHAR:8', ''],
		], 'PRIMARY_KEY' => 'category_id']);

		// phpBB 3.3 made kinerity's sequences as separate objects that dropping
		// the table does not remove; a board upgraded to 4.0 still has them
		if ($this->db->get_sql_layer() === 'postgres')
		{
			foreach (['phpbb_kb_articles_seq', 'phpbb_kb_categories_seq'] as $sequence)
			{
				$this->db->sql_query('CREATE SEQUENCE IF NOT EXISTS ' . $sequence);
				$this->db->sql_query('ALTER SEQUENCE ' . $sequence . ' OWNED BY NONE');
			}
		}
	}

	/**
	 * A small kinerity board:
	 *
	 *   Guides (1)            Reference (4)
	 *     Install (2)
	 *       Linux (3)
	 *
	 * Article 1 in Install and Reference (cross-listed), 2 in Linux, 3 pending in
	 * Guides, 4 deleted, 5 denied, 6 in no category, 7 linked only to a category
	 * that no longer exists, 8 with an over-long description.
	 */
	private function seed_kinerity_board(bool $with_edit_name = true): void
	{
		$this->install_kinerity_tables($with_edit_name);

		$categories = [
			[1, 1, 6, 'Guides', '<r><B><s>[b]</s>Step-by-step<e>[/b]</e></B> guides &amp; how-tos</r>'],
			[2, 2, 5, 'Install', '<t>Installing</t>'],
			[3, 3, 4, 'Linux &amp; BSD', '<t>Rocket &#128640; launch</t>'],
			[4, 7, 8, 'Reference', '<t>Line one
line two</t>'],
		];
		foreach ($categories as [$id, $left, $right, $name, $description])
		{
			$this->insert('phpbb_kb_categories', ['category_id' => $id, 'left_id' => $left, 'right_id' => $right, 'category_name' => $name, 'category_description' => $description, 'bbcode_uid' => 'abcd1234']);
		}

		$articles = [
			[1, 1, 'Welcome', 'First &quot;steps&quot;'],
			[2, 1, 'Linux install', ''],
			[3, 0, 'Pending', ''],
			[4, 2, 'Deleted', ''],
			[5, 3, 'Denied', ''],
			[6, 1, 'Homeless', ''],
			[7, 1, 'Dangling', ''],
			[8, 1, 'Long', str_repeat('a &amp; b ', 40)],
		];
		foreach ($articles as [$id, $visibility, $title, $description])
		{
			$row = [
				'article_id' => $id, 'article_poster_id' => 2, 'article_poster_name' => 'Kailey', 'article_poster_colour' => 'AA0000',
				'article_time' => 1600000000 + $id, 'article_edit_time' => $id === 1 ? 1700000000 : 0,
				'article_title' => $title, 'article_description' => $description,
				'article_text' => '<r><B><s>[b]</s>Body ' . $id . '<e>[/b]</e></B></r>', 'bbcode_bitfield' => 'QQ==', 'bbcode_uid' => 'uid' . $id,
				'article_visibility' => $visibility, 'article_views' => $id * 10,
			];
			if ($with_edit_name)
			{
				$row['article_edit_name'] = '';
			}
			$this->insert('phpbb_kb_articles', $row);
		}

		foreach ([[4, 1], [2, 1], [3, 2], [1, 3], [2, 4], [2, 5], [99, 7], [1, 8]] as [$category, $article])
		{
			$this->insert('phpbb_kb_article_category', ['category_id' => $category, 'article_id' => $article]);
		}

		foreach (self::OPTIONS as $option => $id)
		{
			$this->insert('phpbb_acl_options', ['auth_option_id' => $id, 'auth_option' => $option, 'is_global' => 1, 'is_local' => 0]);
		}

		// Registered users (group 2) by role 5: read, post, edit; Never delete directly
		foreach (['u_kb_read' => 1, 'u_kb_post' => 1, 'u_kb_edit' => 1] as $option => $setting)
		{
			$this->insert('phpbb_acl_roles_data', ['role_id' => 5, 'auth_option_id' => self::OPTIONS[$option], 'auth_setting' => $setting]);
		}
		$this->insert('phpbb_acl_groups', ['group_id' => 2, 'forum_id' => 0, 'auth_option_id' => 0, 'auth_role_id' => 5, 'auth_setting' => 0]);
		$this->insert('phpbb_acl_groups', ['group_id' => 2, 'forum_id' => 0, 'auth_option_id' => self::OPTIONS['u_kb_delete'], 'auth_role_id' => 0, 'auth_setting' => 0]);
		// Global moderators (group 4): approve directly; a forum-only setting is not board-wide
		$this->insert('phpbb_acl_groups', ['group_id' => 4, 'forum_id' => 0, 'auth_option_id' => self::OPTIONS['m_kb_approve'], 'auth_role_id' => 0, 'auth_setting' => 1]);
		$this->insert('phpbb_acl_groups', ['group_id' => 4, 'forum_id' => 7, 'auth_option_id' => self::OPTIONS['m_kb_delete'], 'auth_role_id' => 0, 'auth_setting' => 1]);
		// Administrators (group 5): kinerity admin
		$this->insert('phpbb_acl_groups', ['group_id' => 5, 'forum_id' => 0, 'auth_option_id' => self::OPTIONS['a_kb_manage'], 'auth_role_id' => 0, 'auth_setting' => 1]);
		// User 3: may change authors, never post
		$this->insert('phpbb_acl_users', ['user_id' => 3, 'forum_id' => 0, 'auth_option_id' => self::OPTIONS['m_kb_chgposter'], 'auth_role_id' => 0, 'auth_setting' => 1]);
		$this->insert('phpbb_acl_users', ['user_id' => 3, 'forum_id' => 0, 'auth_option_id' => self::OPTIONS['u_kb_post'], 'auth_role_id' => 0, 'auth_setting' => 0]);

		$this->insert('phpbb_users', ['user_id' => 2, 'user_permissions' => 'cached', 'user_perm_from' => 0]);
		$this->insert('phpbb_ext', ['ext_name' => 'kinerity/knowledgebase', 'ext_active' => 0]);
		$this->insert('phpbb_ext', ['ext_name' => 'phpbb/viglink', 'ext_active' => 1]);
		$this->insert('phpbb_notification_types', ['notification_type_id' => 1, 'notification_type_name' => 'kinerity.knowledgebase.notification.type.approve_article', 'notification_type_enabled' => 0]);
		$this->insert('phpbb_notification_types', ['notification_type_id' => 2, 'notification_type_name' => 'notification.type.post', 'notification_type_enabled' => 1]);
		$this->insert('phpbb_notifications', ['notification_id' => 1, 'notification_type_id' => 1]);
		$this->insert('phpbb_notifications', ['notification_id' => 2, 'notification_type_id' => 2]);
		$this->insert('phpbb_user_notifications', ['item_type' => 'kinerity.knowledgebase.notification.type.approve_article', 'user_id' => 2]);
		$this->insert('phpbb_user_notifications', ['item_type' => 'notification.type.post', 'user_id' => 2]);
	}

	private function insert(string $table, array $row): void
	{
		$this->assertNotFalse($this->db->sql_query('INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', $row)));
	}

	private function rows(string $sql): array
	{
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows;
	}

	private function importer(string $class = kinerity_import::class): kinerity_import
	{
		return new $class($this->db, $this->tools, 'phpbb_');
	}

	/** Run a batched import step until it reports it is done; returns how many calls it took. */
	private function run_until_done(kinerity_import $importer, string $method): int
	{
		$state = 0;
		$calls = 0;
		do
		{
			$state = $importer->$method($state);
			$this->assertLessThan(1000, ++$calls, 'the import step never finished');
		}
		while ($state !== true);

		return $calls;
	}

	/** The whole enable: set aside, this extension's tables, import, finish. */
	private function full_import(string $class = kinerity_import::class, ?\phpbb\config\config $config = null): \phpbb\config\config
	{
		$config = $config ?? new \phpbb\config\config(['knowledgebase_enable' => '1', 'knowledgebase_header_link' => '1', 'knowledgebase_font_icon' => 'clone', 'kb_version' => '2.0.31']);
		$this->importer($class)->set_aside();
		$this->install_our_tables();
		$this->run_until_done($this->importer($class), 'import_categories');
		$this->run_until_done($this->importer($class), 'import_articles');
		$this->importer($class)->finish($config);

		return $config;
	}

	/** Our category id for a kinerity category id. */
	private function new_category_id(int $old_id): int
	{
		return (int) $this->rows('SELECT kb_new_id FROM phpbb_kb_kinerity_categories WHERE category_id = ' . $old_id)[0]['kb_new_id'];
	}

	private function new_article(int $old_id): array
	{
		$new_id = (int) $this->rows('SELECT kb_new_id FROM phpbb_kb_kinerity_articles WHERE article_id = ' . $old_id)[0]['kb_new_id'];
		$this->assertGreaterThan(0, $new_id);

		return $this->rows('SELECT * FROM phpbb_kb_articles WHERE article_id = ' . $new_id)[0];
	}

	/** @dataProvider engines */
	public function test_set_aside_moves_every_kinerity_table_to_a_backup_and_drops_it($engine)
	{
		$this->connect($engine);
		$this->seed_kinerity_board();

		$this->importer()->set_aside();

		foreach (['kb_articles', 'kb_categories', 'kb_article_category'] as $table)
		{
			$this->assertFalse($this->tools->sql_table_exists('phpbb_' . $table), $table . ' should be gone');
		}
		$this->assertCount(8, $this->rows('SELECT article_id FROM phpbb_kb_kinerity_articles'));
		$this->assertCount(4, $this->rows('SELECT category_id FROM phpbb_kb_kinerity_categories'));
		$this->assertCount(8, $this->rows('SELECT article_id FROM phpbb_kb_kinerity_article_category'));
		$this->assertSame('First &quot;steps&quot;', $this->rows('SELECT article_description FROM phpbb_kb_kinerity_articles WHERE article_id = 1')[0]['article_description']);

		// Running it again (an enable retried) changes nothing
		$this->importer()->set_aside();
		$this->assertCount(8, $this->rows('SELECT article_id FROM phpbb_kb_kinerity_articles'));

		// This extension's tables can now be created under the shared names
		$this->install_our_tables();
		$this->assertTrue($this->tools->sql_column_exists('phpbb_kb_articles', 'article_body'));
	}

	/** @dataProvider engines */
	public function test_set_aside_handles_kinerity_1_0_without_the_edit_name_column($engine)
	{
		$this->connect($engine);
		$this->seed_kinerity_board(false);

		$this->full_import();

		$this->assertSame('Welcome', $this->new_article(1)['article_title']);
		$this->assertCount(8, $this->rows('SELECT article_id FROM phpbb_kb_articles'));
	}

	/** @dataProvider engines */
	public function test_categories_keep_their_tree_names_and_plain_text_details($engine)
	{
		$this->connect($engine);
		$this->seed_kinerity_board();
		$this->full_import();

		$categories = [];
		foreach ($this->rows('SELECT * FROM phpbb_kb_categories') as $row)
		{
			$categories[(int) $row['category_id']] = $row;
		}
		// 4 kinerity categories and one for the articles kinerity filed nowhere
		$this->assertCount(5, $categories);

		$guides = $categories[$this->new_category_id(1)];
		$install = $categories[$this->new_category_id(2)];
		$linux = $categories[$this->new_category_id(3)];
		$reference = $categories[$this->new_category_id(4)];

		$this->assertSame(0, (int) $guides['parent_id']);
		$this->assertSame((int) $guides['category_id'], (int) $install['parent_id']);
		$this->assertSame((int) $install['category_id'], (int) $linux['parent_id']);
		$this->assertSame(0, (int) $reference['parent_id']);
		$this->assertSame([1, 6, 2, 5, 3, 4, 7, 8], [(int) $guides['left_id'], (int) $guides['right_id'], (int) $install['left_id'], (int) $install['right_id'], (int) $linux['left_id'], (int) $linux['right_id'], (int) $reference['left_id'], (int) $reference['right_id']]);

		$this->assertSame('Linux &amp; BSD', $linux['category_name']);
		$this->assertSame('Step-by-step guides &amp; how-tos', $guides['category_details']);
		$this->assertSame('Line one line two', $reference['category_details']);
		// An emoji phpBB stored as an entity stays one, for MySQL's utf8 tables
		$this->assertSame('Rocket &#128640; launch', $linux['category_details']);

		$fallback = array_values(array_filter($categories, fn($row) => $row['category_name'] === kinerity_import::FALLBACK_CATEGORY_NAME));
		$this->assertCount(1, $fallback);
		$this->assertSame([0, 9, 10], [(int) $fallback[0]['parent_id'], (int) $fallback[0]['left_id'], (int) $fallback[0]['right_id']]);
	}

	/** @dataProvider engines */
	public function test_articles_keep_their_content_and_go_to_the_lowest_category_cross_listed_in_the_rest($engine)
	{
		$this->connect($engine);
		$this->seed_kinerity_board();
		$this->full_import();

		$welcome = $this->new_article(1);
		$this->assertSame($this->new_category_id(2), (int) $welcome['article_category_id']);
		$this->assertSame(
			[$this->new_category_id(4)],
			array_map('intval', array_column($this->rows('SELECT category_id FROM phpbb_kb_article_categories WHERE article_id = ' . (int) $welcome['article_id']), 'category_id'))
		);
		$this->assertSame('Welcome', $welcome['article_title']);
		$this->assertSame('First &quot;steps&quot;', $welcome['article_description']);
		$this->assertSame('<r><B><s>[b]</s>Body 1<e>[/b]</e></B></r>', $welcome['article_body']);
		$this->assertSame('uid1', $welcome['bbcode_uid']);
		$this->assertSame([1600000001, 1700000000, 2, 10, 0], [(int) $welcome['article_date'], (int) $welcome['edit_date'], (int) $welcome['author_id'], (int) $welcome['views'], (int) $welcome['author_type']]);
		$this->assertSame('Kailey', $welcome['author']);
		$this->assertSame([1, functions_kb::STATUS_ACTIVE], [(int) $welcome['approved'], (int) $welcome['status']]);

		$this->assertSame($this->new_category_id(3), (int) $this->new_article(2)['article_category_id']);

		// Waiting for approval: in the queue. Deleted and denied: kept, deactivated.
		$this->assertSame([0, functions_kb::STATUS_NEW], [(int) $this->new_article(3)['approved'], (int) $this->new_article(3)['status']]);
		$this->assertSame([0, functions_kb::STATUS_INACTIVE], [(int) $this->new_article(4)['approved'], (int) $this->new_article(4)['status']]);
		$this->assertSame([0, functions_kb::STATUS_INACTIVE], [(int) $this->new_article(5)['approved'], (int) $this->new_article(5)['status']]);

		// No category, or only a category kinerity no longer has: the fallback category
		$fallback_id = (int) $this->rows("SELECT category_id FROM phpbb_kb_categories WHERE category_name = '" . kinerity_import::FALLBACK_CATEGORY_NAME . "'")[0]['category_id'];
		$this->assertSame($fallback_id, (int) $this->new_article(6)['article_category_id']);
		$this->assertSame($fallback_id, (int) $this->new_article(7)['article_category_id']);

		// Shortened to the column, without cutting an entity
		$long = $this->new_article(8)['article_description'];
		$this->assertLessThanOrEqual(255, mb_strlen($long));
		$this->assertStringEndsWith("\u{2026}", $long);
		$this->assertDoesNotMatchRegularExpression('/&[a-z]*\x{2026}$/u', $long);

		$this->assertCount(1, $this->rows('SELECT article_id FROM phpbb_kb_article_categories'));
	}

	/** @dataProvider engines */
	public function test_article_counts_are_set_per_primary_category($engine)
	{
		$this->connect($engine);
		$this->seed_kinerity_board();
		$this->full_import();

		$counts = [];
		foreach ($this->rows('SELECT category_id, number_articles FROM phpbb_kb_categories') as $row)
		{
			$counts[(int) $row['category_id']] = (int) $row['number_articles'];
		}

		$this->assertSame(2, $counts[$this->new_category_id(1)]); // articles 3, 8
		$this->assertSame(3, $counts[$this->new_category_id(2)]); // 1, 4, 5
		$this->assertSame(1, $counts[$this->new_category_id(3)]); // 2
		$this->assertSame(0, $counts[$this->new_category_id(4)]); // 1 is only cross-listed here
		$this->assertSame(8, array_sum($counts));                 // 6 and 7 in the fallback category
	}

	/** @dataProvider engines */
	public function test_board_wide_permissions_become_permissions_on_every_category($engine)
	{
		$this->connect($engine);
		$this->seed_kinerity_board();
		$this->full_import();

		$option_names = [];
		foreach ($this->rows('SELECT auth_option_id, auth_option FROM phpbb_kb_options') as $row)
		{
			$option_names[(int) $row['auth_option_id']] = $row['auth_option'];
		}

		$groups = [];
		foreach ($this->rows('SELECT group_id, category_id, auth_option_id, auth_setting FROM phpbb_kb_groups') as $row)
		{
			$groups[(int) $row['category_id']][(int) $row['group_id']][$option_names[(int) $row['auth_option_id']]] = (int) $row['auth_setting'];
		}
		$users = [];
		foreach ($this->rows('SELECT user_id, category_id, auth_option_id, auth_setting FROM phpbb_kb_users') as $row)
		{
			$users[(int) $row['category_id']][(int) $row['user_id']][$option_names[(int) $row['auth_option_id']]] = (int) $row['auth_setting'];
		}

		$all_categories = array_map('intval', array_column($this->rows('SELECT category_id FROM phpbb_kb_categories'), 'category_id'));
		$this->assertCount(5, $all_categories);
		foreach ($all_categories as $category_id)
		{
			$expected_groups = [
				2 => ['kb_u_add' => 1, 'kb_u_edit' => 1, 'kb_u_delete' => 0],
				4 => ['kb_m_approve' => 1],
			];
			ksort($groups[$category_id]);
			foreach ($groups[$category_id] as &$options) ksort($options);
			unset($options);
			foreach ($expected_groups as &$options) ksort($options);
			unset($options);
			$this->assertSame($expected_groups, $groups[$category_id], 'groups in category ' . $category_id);

			$expected_users = [3 => ['kb_m_change_author' => 1, 'kb_u_add' => 0]];
			ksort($users[$category_id][3]);
			ksort($expected_users[3]);
			$this->assertSame($expected_users, $users[$category_id], 'users in category ' . $category_id);
		}
	}

	/** @dataProvider engines */
	public function test_read_and_admin_permissions_carry_over_and_kinerity_is_unregistered($engine)
	{
		$this->connect($engine);
		$this->seed_kinerity_board();
		// An existing u_kb_view setting is not overwritten
		$this->insert('phpbb_acl_groups', ['group_id' => 9, 'forum_id' => 0, 'auth_option_id' => self::OPTIONS['u_kb_view'], 'auth_role_id' => 0, 'auth_setting' => 0]);
		$this->insert('phpbb_acl_groups', ['group_id' => 9, 'forum_id' => 0, 'auth_option_id' => self::OPTIONS['u_kb_read'], 'auth_role_id' => 0, 'auth_setting' => 1]);

		$config = $this->full_import();

		$this->assertSame(
			[['role_id' => 5, 'auth_setting' => 1]],
			array_map(fn($row) => ['role_id' => (int) $row['role_id'], 'auth_setting' => (int) $row['auth_setting']], $this->rows('SELECT role_id, auth_setting FROM phpbb_acl_roles_data WHERE auth_option_id = ' . self::OPTIONS['u_kb_view']))
		);
		$this->assertSame(
			[[5, 1]],
			array_map(fn($row) => [(int) $row['group_id'], (int) $row['auth_setting']], $this->rows('SELECT group_id, auth_setting FROM phpbb_acl_groups WHERE auth_option_id = ' . self::OPTIONS['a_manage_kb']))
		);
		$this->assertSame(
			[[9, 0]],
			array_map(fn($row) => [(int) $row['group_id'], (int) $row['auth_setting']], $this->rows('SELECT group_id, auth_setting FROM phpbb_acl_groups WHERE auth_option_id = ' . self::OPTIONS['u_kb_view']))
		);
		$this->assertSame('', $this->rows('SELECT user_permissions FROM phpbb_users WHERE user_id = 2')[0]['user_permissions']);

		foreach (kinerity_import::KINERITY_CONFIG as $name)
		{
			$this->assertFalse(isset($config[$name]), $name);
		}
		$this->assertSame('2.0.31', $config['kb_version']);
		$this->assertSame(['phpbb/viglink'], array_column($this->rows('SELECT ext_name FROM phpbb_ext'), 'ext_name'));
		$this->assertSame(['notification.type.post'], array_column($this->rows('SELECT notification_type_name FROM phpbb_notification_types'), 'notification_type_name'));
		$this->assertSame([2], array_map('intval', array_column($this->rows('SELECT notification_id FROM phpbb_notifications'), 'notification_id')));
		$this->assertSame(['notification.type.post'], array_column($this->rows('SELECT item_type FROM phpbb_user_notifications'), 'item_type'));

		// The backup stays as a backup
		$this->assertCount(8, $this->rows('SELECT article_id FROM phpbb_kb_kinerity_articles'));
	}

	/** @dataProvider engines */
	public function test_an_import_that_stops_part_way_carries_on_without_duplicates($engine)
	{
		$this->connect($engine);
		$this->seed_kinerity_board();
		$this->importer()->set_aside();
		$this->install_our_tables();

		$small = kinerity_import_small_batches::class;
		$state = $this->importer($small)->import_categories(0);
		$this->assertIsArray($state);
		$this->assertCount(2, $this->rows('SELECT category_id FROM phpbb_kb_categories'));

		// A new request (fresh object, no state) picks up the rest
		$this->assertSame(2, $this->run_until_done($this->importer($small), 'import_categories'));
		$this->assertCount(4, $this->rows('SELECT category_id FROM phpbb_kb_categories'));

		$this->importer($small)->import_articles(0);
		$this->assertCount(3, $this->rows('SELECT article_id FROM phpbb_kb_articles'));
		$this->assertSame(3, $this->run_until_done($this->importer($small), 'import_articles'));
		$this->assertCount(8, $this->rows('SELECT article_id FROM phpbb_kb_articles'));
		$this->assertSame(true, $this->importer($small)->import_articles(0));

		$this->importer()->finish(new \phpbb\config\config([]));
		$this->assertCount(8, $this->rows('SELECT article_id FROM phpbb_kb_articles'));
		$this->assertSame(1, (int) $this->rows("SELECT COUNT(*) AS total FROM phpbb_kb_categories WHERE category_name = '" . kinerity_import::FALLBACK_CATEGORY_NAME . "'")[0]['total']);
	}

	/** @dataProvider engines */
	public function test_two_requests_converting_the_same_batch_import_each_row_once($engine)
	{
		$this->connect($engine);
		$this->seed_kinerity_board();
		$this->importer()->set_aside();
		$this->install_our_tables();

		// Categories: the second import converts all of them (and all articles,
		// adding the fallback category) inside the first one's batch
		$this->run_until_done($this->importer(kinerity_import_racing::class), 'import_categories');
		$this->assertCount(5, $this->rows('SELECT category_id FROM phpbb_kb_categories'));

		// Articles likewise, with the fallback category made only once
		$this->run_until_done($this->importer(kinerity_import_racing::class), 'import_articles');
		$this->assertCount(8, $this->rows('SELECT article_id FROM phpbb_kb_articles'));
		$this->assertCount(1, $this->rows('SELECT article_id FROM phpbb_kb_article_categories'));
		$this->assertSame(1, (int) $this->rows("SELECT COUNT(*) AS total FROM phpbb_kb_categories WHERE category_name = '" . kinerity_import::FALLBACK_CATEGORY_NAME . "'")[0]['total']);

		// Every backup row points at an article that exists, and none twice
		$new_ids = array_map('intval', array_column($this->rows('SELECT kb_new_id FROM phpbb_kb_kinerity_articles'), 'kb_new_id'));
		$this->assertCount(8, array_unique($new_ids));
		$this->assertSame([], array_diff($new_ids, array_map('intval', array_column($this->rows('SELECT article_id FROM phpbb_kb_articles'), 'article_id'))));
	}

	/** @dataProvider engines */
	public function test_after_a_purge_the_backup_is_imported_again($engine)
	{
		$this->connect($engine);
		$this->seed_kinerity_board();
		$this->full_import();

		// Purging this extension: version_2_0_32 reverts, then its tables go
		$this->importer()->reset_import_markers();
		foreach (array_keys($this->our_schema()) as $table)
		{
			$this->tools->sql_table_drop($table);
			if ($this->db->get_sql_layer() === 'postgres')
			{
				$this->db->sql_query('DROP SEQUENCE IF EXISTS ' . $table . '_seq');
			}
		}

		$this->install_our_tables();
		$this->run_until_done($this->importer(), 'import_categories');
		$this->run_until_done($this->importer(), 'import_articles');

		$this->assertCount(8, $this->rows('SELECT article_id FROM phpbb_kb_articles'));
		$this->assertSame('Welcome', $this->new_article(1)['article_title']);
	}

	/** @dataProvider engines */
	public function test_a_board_that_never_ran_kinerity_is_left_alone($engine)
	{
		$this->connect($engine);
		$this->install_our_tables();
		$this->insert('phpbb_kb_categories', ['category_name' => 'Ours', 'category_parents' => '', 'category_details' => '', 'left_id' => 1, 'right_id' => 2]);
		$this->insert('phpbb_ext', ['ext_name' => 'phpbbmodders/knowledgebase', 'ext_active' => 1]);
		$config = new \phpbb\config\config(['kb_version' => '2.0.31']);

		$this->importer()->set_aside();
		$this->assertFalse($this->importer()->has_backup());
		$this->assertSame(true, $this->importer()->import_categories(0));
		$this->assertSame(true, $this->importer()->import_articles(0));
		$this->importer()->finish($config);
		$this->importer()->reset_import_markers();

		$this->assertTrue($this->tools->sql_table_exists('phpbb_kb_articles'));
		$this->assertFalse($this->tools->sql_table_exists('phpbb_kb_kinerity_articles'));
		$this->assertSame(['Ours'], array_column($this->rows('SELECT category_name FROM phpbb_kb_categories'), 'category_name'));
		$this->assertSame(['phpbbmodders/knowledgebase'], array_column($this->rows('SELECT ext_name FROM phpbb_ext'), 'ext_name'));
	}

	/** @dataProvider engines */
	public function test_categories_already_on_the_board_are_kept_ahead_of_the_imported_tree($engine)
	{
		$this->connect($engine);
		$this->seed_kinerity_board();
		$this->importer()->set_aside();
		$this->install_our_tables();
		$this->insert('phpbb_kb_categories', ['category_name' => 'Ours', 'category_parents' => '', 'category_details' => '', 'left_id' => 1, 'right_id' => 2]);

		$this->run_until_done($this->importer(), 'import_categories');

		$guides = $this->rows('SELECT left_id, right_id FROM phpbb_kb_categories WHERE category_id = ' . $this->new_category_id(1))[0];
		$this->assertSame([3, 8], [(int) $guides['left_id'], (int) $guides['right_id']]);
	}

	public function test_the_migrations_run_in_the_right_order()
	{
		$this->assertContains('\phpbbmodders\knowledgebase\migrations\kinerity_tables_set_aside', \phpbbmodders\knowledgebase\migrations\version_1_0_0::depends_on());
		$this->assertSame(['\phpbb\db\migration\data\v310\dev'], \phpbbmodders\knowledgebase\migrations\kinerity_tables_set_aside::depends_on());
		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_31'], \phpbbmodders\knowledgebase\migrations\version_2_0_32::depends_on());
	}

	public function test_the_import_migration_removes_kinerity_permissions_and_modules_only_on_a_board_that_ran_it()
	{
		$migration = new \phpbbmodders\knowledgebase\migrations\version_2_0_32(new \phpbb\config\config([]), $this->createMock(\phpbb\db\driver\driver_interface::class), $this->createMock(\phpbb\db\tools\tools_interface::class), '', 'php', 'phpbb_', []);
		$steps = $migration->update_data();

		$guarded = array_filter($steps, fn($step) => $step[0] === 'if');
		$this->assertCount(count(kinerity_import::KINERITY_PERMISSIONS) + 3, $guarded);
		foreach ($guarded as $step)
		{
			$this->assertSame(['custom', [[$migration, 'has_kinerity_data']]], $step[1][0]);
		}
		$removed = array_map(fn($step) => $step[1][1][1][0], array_filter($guarded, fn($step) => $step[1][1][0] === 'permission.remove'));
		$this->assertSame(kinerity_import::KINERITY_PERMISSIONS, array_values($removed));
		// This extension's own permissions are never among them
		$this->assertNotContains('u_kb_view', $removed);
		$this->assertNotContains('a_manage_kb', $removed);
		$this->assertSame(['config.update', ['kb_version', '2.0.32']], end($steps));
	}

	/** @dataProvider visibilities */
	public function test_map_visibility($visibility, $expected)
	{
		$this->assertSame($expected, kinerity_import::map_visibility($visibility));
	}

	public function visibilities()
	{
		return [
			'approved'    => [1, [1, functions_kb::STATUS_ACTIVE]],
			'waiting'     => [0, [0, functions_kb::STATUS_NEW]],
			'deleted'     => [2, [0, functions_kb::STATUS_INACTIVE]],
			'denied'      => [3, [0, functions_kb::STATUS_INACTIVE]],
			'unknown'     => [7, [0, functions_kb::STATUS_INACTIVE]],
		];
	}

	public function test_plain_text_from_parsed_and_older_bbcode()
	{
		$this->assertSame('Bold & "quoted" link', kinerity_import::plain_text('<r><B><s>[b]</s>Bold<e>[/b]</e></B> &amp; "quoted" <URL url="http://example.com">link</URL></r>'));
		$this->assertSame('Plain text', kinerity_import::plain_text('<t>Plain   text</t>'));
		$this->assertSame('Bold & more :)', kinerity_import::plain_text('[b:1a2b3c4d]Bold[/b:1a2b3c4d] &amp; more <!-- s:) --><img src="{SMILIES_PATH}/icon_e_smile.gif" alt=":)" title="Smile" /><!-- s:) -->'));
		$this->assertSame('', kinerity_import::plain_text(''));
	}

	public function test_category_details_keep_supplementary_characters_as_entities()
	{
		$this->assertSame('A &amp; B &#128640;', kinerity_import::category_details('<t>A &amp; B &#128640;</t>'));
		$this->assertSame('&#128640; &#127881;', kinerity_import::encode_supplementary("\u{1F680} \u{1F389}"));
		$this->assertSame('Ünïcödé 日本語', kinerity_import::encode_supplementary('Ünïcödé 日本語'));
	}

	public function test_fit_escaped_shortens_without_cutting_an_entity()
	{
		$this->assertSame('short', kinerity_import::fit_escaped('short', 10));
		$this->assertSame(str_repeat('x', 10), kinerity_import::fit_escaped(str_repeat('x', 10), 10));

		$fitted = kinerity_import::fit_escaped(str_repeat('&amp;', 10), 12);
		$this->assertSame('&amp;&amp;' . "\u{2026}", $fitted);

		// A character phpBB stored as an entity is not cut in half
		$this->assertSame('ab' . "\u{2026}", kinerity_import::fit_escaped('ab&#128640;cdef', 6));

		$fitted = kinerity_import::fit_escaped(str_repeat('é', 300), 255);
		$this->assertSame(255, mb_strlen($fitted));
		$this->assertStringEndsWith("\u{2026}", $fitted);
	}
}
