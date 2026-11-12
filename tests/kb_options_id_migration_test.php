<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\migrations;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/migrations/version_1_0_0.php';
require_once dirname(__DIR__) . '/migrations/version_1_0_3.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_2.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_15.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_30.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_31.php';
if (!defined('CONFIG_TABLE')) define('CONFIG_TABLE', 'phpbb_config');

/**
 * Migrations that stopped a fresh PostgreSQL board from enabling the extension.
 *
 * version_2_0_30 adds kb_m_change_author after the seven options
 * version_1_0_0 inserted with explicit ids. On PostgreSQL those inserts leave
 * the table's sequence at 1, so the new option must not take its id from it.
 *
 * version_2_0_31 rebuilds the search-match index that version_1_0_3 created
 * as plain un_mtch on PostgreSQL, where phpBB 3.3's tools drop it as
 * {table}_un_mtch.
 */
class kb_options_id_migration_test extends \PHPUnit\Framework\TestCase
{
	private $db;
	private $tools;
	private $sqlite_fixture;

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
				$this->sqlite_fixture = tempnam(sys_get_temp_dir(), 'kb-options-');
				$this->assertTrue($this->db->sql_connect($this->sqlite_fixture, '', '', ''));
				$this->tools = new \phpbb\db\tools\doctrine(\phpbb\db\doctrine\connection_factory::get_connection_from_params('sqlite3', $this->sqlite_fixture));
				$this->tools->set_table_prefix('phpbb_');
			}
			else
			{
				$this->assertTrue($this->db->sql_connect(':memory:', '', '', ''));
				$this->tools = new \phpbb\db\tools\sqlite3($this->db);
			}
			return;
		}

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

	/** @dataProvider engines */
	public function test_the_author_permission_is_added_after_the_first_options_on_a_fresh_board($engine)
	{
		$this->connect($engine);
		foreach (['phpbb_kb_options', 'phpbb_kb_users', 'phpbb_kb_groups'] as $table)
		{
			if ($this->tools->sql_table_exists($table)) $this->tools->sql_table_drop($table);
			if ($this->db->get_sql_layer() === 'postgres') $this->db->sql_query('DROP SEQUENCE IF EXISTS ' . $table . '_seq');
		}

		// The options table and its first seven options, exactly as version_1_0_0 makes them
		$config = new \phpbb\config\config([]);
		$first = new version_1_0_0($config, $this->db, $this->tools, '', 'php', 'phpbb_', []);
		$this->tools->sql_create_table('phpbb_kb_options', $first->update_schema()['add_tables']['phpbb_kb_options']);
		$first->update_kb_options_table();

		(new version_2_0_30($config, $this->db, $this->tools, '', 'php', 'phpbb_', []))->add_permission();

		$result = $this->db->sql_query('SELECT auth_option_id, auth_option FROM phpbb_kb_options ORDER BY auth_option_id');
		$options = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$options[(int) $row['auth_option_id']] = $row['auth_option'];
		}
		$this->db->sql_freeresult($result);

		$this->assertSame('kb_m_change_author', $options[8] ?? null);
		$this->assertCount(8, $options);

		// Running it again adds nothing
		(new version_2_0_30($config, $this->db, $this->tools, '', 'php', 'phpbb_', []))->add_permission();
		$result = $this->db->sql_query('SELECT COUNT(*) AS total FROM phpbb_kb_options');
		$this->assertSame(8, (int) $this->db->sql_fetchfield('total'));
		$this->db->sql_freeresult($result);

		$this->tools->sql_table_drop('phpbb_kb_options');
		if ($this->db->get_sql_layer() === 'postgres') $this->db->sql_query('DROP SEQUENCE IF EXISTS phpbb_kb_options_seq');
	}

	/** @dataProvider engines */
	public function test_the_search_match_index_is_rebuilt_after_version_1_0_3_made_it($engine)
	{
		$this->connect($engine);
		$table = 'phpbb_kb_src_wrdmtch';
		foreach ([$table, 'phpbb_kb_articles'] as $name)
		{
			if ($this->tools->sql_table_exists($name)) $this->tools->sql_table_drop($name);
		}
		// version_1_0_3 also drops a kb_articles column
		$this->tools->sql_create_table('phpbb_kb_articles', ['COLUMNS' => ['article_id' => ['UINT', 0], 'bbcode_bitfield' => ['VCHAR:32', '']]]);

		// The table as version_1_0_0, 1_0_3 (schema and data), 2_0_2 and 2_0_15 leave it
		$config = new \phpbb\config\config([]);
		$migration = fn($class) => new $class($config, $this->db, $this->tools, '', 'php', 'phpbb_', []);
		$this->tools->sql_create_table($table, $migration(version_1_0_0::class)->update_schema()['add_tables'][$table]);
		$this->tools->perform_schema_changes($migration(version_1_0_3::class)->update_schema());
		$migration(version_1_0_3::class)->add_key();
		$this->tools->perform_schema_changes($migration(version_2_0_2::class)->update_schema());
		$this->tools->perform_schema_changes($migration(version_2_0_15::class)->update_schema());
		$this->db->sql_query('INSERT INTO ' . $table . ' (article_id, word_id, title_match, descr_match) VALUES (1, 1, 0, 1)');

		$migration(version_2_0_31::class)->rebuild_match_index();
		// Run again (an enable retried): still fine
		$migration(version_2_0_31::class)->rebuild_match_index();

		// The match survives, and the four-column unique key holds
		$result = $this->db->sql_query('SELECT COUNT(*) AS total FROM ' . $table);
		$this->assertSame(1, (int) $this->db->sql_fetchfield('total'));
		$this->db->sql_freeresult($result);
		$this->db->sql_return_on_error(true);
		$duplicate = $this->db->sql_query('INSERT INTO ' . $table . ' (article_id, word_id, title_match, descr_match) VALUES (1, 1, 0, 1)');
		$this->db->sql_return_on_error(false);
		$this->assertFalse($duplicate, 'a duplicate match should be refused by the unique index');
		$this->assertNotFalse($this->db->sql_query('INSERT INTO ' . $table . ' (article_id, word_id, title_match, descr_match) VALUES (1, 1, 0, 0)'));

		$this->tools->sql_table_drop($table);
		$this->tools->sql_table_drop('phpbb_kb_articles');
	}
}
