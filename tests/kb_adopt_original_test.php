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
require_once dirname(__DIR__) . '/migrations/version_2_0_20.php';

/** Taking over a board that ran the original sheer/knowledgebase package. */
class kb_adopt_original_test extends \PHPUnit\Framework\TestCase
{
	private $db;
	private $root;

	protected function setUp(): void
	{
		$this->root = sys_get_temp_dir() . '/kb_adopt_' . bin2hex(random_bytes(4)) . '/';
		mkdir($this->root, 0755, true);
	}

	protected function tearDown(): void
	{
		if ($this->db) $this->db->sql_close();
		$this->remove($this->root);
	}

	private function remove(string $path): void
	{
		if (is_dir($path) && !is_link($path))
		{
			@chmod($path, 0755);
			foreach (scandir($path) as $entry)
			{
				if ($entry !== '.' && $entry !== '..') $this->remove(rtrim($path, '/') . '/' . $entry);
			}
			@rmdir($path);
		}
		elseif (file_exists($path) || is_link($path))
		{
			@unlink($path);
		}
	}

	private function connect(string $engine): void
	{
		if ($engine === 'sqlite')
		{
			$this->db = new \phpbb\db\driver\sqlite3();
			$this->assertTrue($this->db->sql_connect(':memory:', '', '', ''));
		}
		else
		{
			$port = getenv('KB_TEST_' . strtoupper($engine) . '_PORT');
			if (!$port) $this->markTestSkipped('Set the fixture database port to run integration tests');
			$this->assertTrue(ctype_digit($port));
			$this->db = $engine === 'mysql' ? new \phpbb\db\driver\mysqli() : new \phpbb\db\driver\postgres();
			$this->db->sql_return_on_error(true);
			$this->assertIsObject($this->db->sql_connect('127.0.0.1', $engine === 'mysql' ? 'root' : 'postgres', '', 'kb_fixture', (int) $port));
		}

		foreach (['phpbb_modules', 'phpbb_notification_types', 'phpbb_user_notifications'] as $table)
		{
			$this->query('DROP TABLE IF EXISTS ' . $table);
		}
		$this->query('CREATE TABLE phpbb_modules (module_id INTEGER, module_class VARCHAR(10), module_basename VARCHAR(255), module_mode VARCHAR(255), module_auth VARCHAR(255))');
		$this->query('CREATE TABLE phpbb_notification_types (notification_type_id INTEGER, notification_type_name VARCHAR(255), notification_type_enabled INTEGER)');
		$this->query('CREATE TABLE phpbb_user_notifications (item_type VARCHAR(255), item_id INTEGER, user_id INTEGER, method VARCHAR(255), notify INTEGER)');
	}

	private function query(string $sql)
	{
		$result = $this->db->sql_query($sql);
		if ($result === false) throw new \RuntimeException(json_encode($this->db->sql_error()));
		return $result;
	}

	private function rows(string $sql): array
	{
		$result = $this->query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows;
	}

	private function module(int $id, string $class, string $basename, string $auth): void
	{
		$this->query('INSERT INTO phpbb_modules ' . $this->db->sql_build_array('INSERT', ['module_id' => $id, 'module_class' => $class, 'module_basename' => $basename, 'module_mode' => 'm', 'module_auth' => $auth]));
	}

	private function migration(array $config = []): version_2_0_20
	{
		return new version_2_0_20(new \phpbb\config\config($config), $this->db, $this->createMock(\phpbb\db\tools\tools_interface::class), $this->root, 'php', 'phpbb_');
	}

	private function adopt(array $config = []): \phpbb\config\config
	{
		$migration = $this->migration($config);
		$migration->adopt_original_package();

		$property = new \ReflectionProperty(\phpbb\db\migration\migration::class, 'config');
		$property->setAccessible(true);

		return $property->getValue($migration);
	}

	/** @dataProvider engines */
	public function test_acp_modules_of_the_original_point_at_this_extension($engine)
	{
		$this->connect($engine);
		$this->module(1, 'acp', '\\sheer\\knowledgebase\\acp\\config_module', 'ext_sheer/knowledgebase && acl_a_board && acl_a_manage_kb');
		$this->module(2, 'acp', 'acp_board', 'acl_a_board');
		$this->module(3, 'acp', '', '');
		$this->module(4, 'mcp', '\\sheer\\knowledgebase\\acp\\mcp_module', 'ext_sheer/knowledgebase');
		$this->module(5, 'acp', '\\phpbbmodders\\knowledgebase\\acp\\fields_module', 'ext_phpbbmodders/knowledgebase && acl_a_board');

		$this->adopt();

		$this->assertSame(
			[
				['1', '\\phpbbmodders\\knowledgebase\\acp\\config_module', 'ext_phpbbmodders/knowledgebase && acl_a_board && acl_a_manage_kb'],
				['2', 'acp_board', 'acl_a_board'],
				['3', '', ''],
				['4', '\\sheer\\knowledgebase\\acp\\mcp_module', 'ext_sheer/knowledgebase'],
				['5', '\\phpbbmodders\\knowledgebase\\acp\\fields_module', 'ext_phpbbmodders/knowledgebase && acl_a_board'],
			],
			array_map(fn($row) => [(string) $row['module_id'], $row['module_basename'], $row['module_auth']], $this->rows('SELECT module_id, module_basename, module_auth FROM phpbb_modules ORDER BY module_id'))
		);
	}

	/** @dataProvider engines */
	public function test_running_it_again_changes_nothing($engine)
	{
		$this->connect($engine);
		$this->module(1, 'acp', '\\sheer\\knowledgebase\\acp\\config_module', 'ext_sheer/knowledgebase');
		$this->adopt(['kb_search_type' => 'sheer\\knowledgebase\\search\\backend\\kb_fulltext_native']);
		$first = $this->rows('SELECT * FROM phpbb_modules');

		$config = $this->adopt(['kb_search_type' => 'phpbbmodders\\knowledgebase\\search\\backend\\kb_fulltext_native']);

		$this->assertSame($first, $this->rows('SELECT * FROM phpbb_modules'));
		$this->assertSame('phpbbmodders\\knowledgebase\\search\\backend\\kb_fulltext_native', $config['kb_search_type']);
	}

	/** @dataProvider engines */
	public function test_a_board_that_never_ran_the_original_is_left_alone($engine)
	{
		$this->connect($engine);
		$this->module(1, 'acp', 'acp_board', 'acl_a_board');
		$this->query("INSERT INTO phpbb_notification_types VALUES (1, 'notification.type.post', 1)");

		$config = $this->adopt();

		$this->assertSame('acp_board', $this->rows('SELECT module_basename FROM phpbb_modules')[0]['module_basename']);
		$this->assertSame('notification.type.post', $this->rows('SELECT notification_type_name FROM phpbb_notification_types')[0]['notification_type_name']);
		$this->assertFalse(isset($config['kb_search_type']));
	}

	/** @dataProvider search_types */
	public function test_the_search_backend_class_name_is_converted_only_when_it_is_the_originals($before, $after)
	{
		$this->connect('sqlite');

		$this->assertSame($after, $this->adopt(['kb_search_type' => $before])['kb_search_type']);
	}

	public function search_types(): array
	{
		return [
			'original'      => ['sheer\\knowledgebase\\search\\backend\\kb_fulltext_native', 'phpbbmodders\\knowledgebase\\search\\backend\\kb_fulltext_native'],
			'original, mysql' => ['sheer\\knowledgebase\\search\\backend\\kb_fulltext_mysql', 'phpbbmodders\\knowledgebase\\search\\backend\\kb_fulltext_mysql'],
			'leading slash' => ['\\sheer\\knowledgebase\\search\\backend\\kb_fulltext_native', '\\phpbbmodders\\knowledgebase\\search\\backend\\kb_fulltext_native'],
			'already ours'  => ['phpbbmodders\\knowledgebase\\search\\backend\\kb_fulltext_native', 'phpbbmodders\\knowledgebase\\search\\backend\\kb_fulltext_native'],
			'something else' => ['custom\\backend', 'custom\\backend'],
		];
	}

	/** @dataProvider engines */
	public function test_notification_types_and_user_settings_move_and_are_switched_back_on($engine)
	{
		$this->connect($engine);
		$old = 'sheer.knowledgebase.notification.type.';
		$new = 'phpbbmodders.knowledgebase.notification.type.';
		$this->query("INSERT INTO phpbb_notification_types VALUES (1, '{$old}need_approval', 0), (2, '{$old}approve', 0), (3, 'notification.type.post', 1)");
		$this->query("INSERT INTO phpbb_user_notifications VALUES ('{$old}need_approval', 0, 2, 'board', 1), ('notification.type.post', 0, 2, 'board', 1)");

		$this->adopt();

		$types = [];
		foreach ($this->rows('SELECT notification_type_id, notification_type_name, notification_type_enabled FROM phpbb_notification_types ORDER BY notification_type_id') as $row)
		{
			$types[(int) $row['notification_type_id']] = [$row['notification_type_name'], (int) $row['notification_type_enabled']];
		}
		$this->assertSame([1 => [$new . 'need_approval', 1], 2 => [$new . 'approve', 1], 3 => ['notification.type.post', 1]], $types);
		$this->assertSame(
			[$new . 'need_approval', 'notification.type.post'],
			array_column($this->rows('SELECT item_type FROM phpbb_user_notifications ORDER BY item_type DESC'), 'item_type')
		);
	}

	public function test_a_type_that_already_has_the_new_name_is_not_renamed_over()
	{
		$this->connect('sqlite');
		$old = 'sheer.knowledgebase.notification.type.approve';
		$new = 'phpbbmodders.knowledgebase.notification.type.approve';
		$this->query("INSERT INTO phpbb_notification_types VALUES (1, '{$old}', 0), (2, '{$new}', 1)");
		$this->query("INSERT INTO phpbb_user_notifications VALUES ('{$old}', 0, 2, 'board', 1), ('{$new}', 0, 2, 'board', 1)");

		$this->adopt();

		$this->assertSame([$old, $new], array_column($this->rows('SELECT notification_type_name FROM phpbb_notification_types ORDER BY notification_type_id'), 'notification_type_name'));
		$this->assertCount(2, $this->rows('SELECT * FROM phpbb_user_notifications'));
	}

	private function original_files(array $files): string
	{
		$source = $this->root . 'ext/sheer/knowledgebase/files/';
		mkdir($source, 0755, true);
		foreach ($files as $name => $content)
		{
			file_put_contents($source . $name, $content);
		}

		return $source;
	}

	public function test_uploaded_files_are_copied_without_overwriting_and_the_originals_stay()
	{
		$this->connect('sqlite');
		$source = $this->original_files(['abc_report.pdf' => 'original', 'thumb_abc_report.pdf' => 'thumb', 'kept.txt' => 'from original']);
		$target = $this->root . 'ext/phpbbmodders/knowledgebase/files/';
		mkdir($target, 0755, true);
		file_put_contents($target . 'kept.txt', 'already here');
		mkdir($source . 'subdir');

		$this->adopt();

		$this->assertSame('original', file_get_contents($target . 'abc_report.pdf'));
		$this->assertSame('thumb', file_get_contents($target . 'thumb_abc_report.pdf'));
		$this->assertSame('already here', file_get_contents($target . 'kept.txt'));
		$this->assertFalse(file_exists($target . 'subdir'));
		$this->assertSame('original', file_get_contents($source . 'abc_report.pdf'));
	}

	public function test_the_files_folder_is_created_when_this_extension_has_none_yet()
	{
		$this->connect('sqlite');
		$this->original_files(['a.dat' => 'x']);

		$this->adopt();

		$this->assertSame('x', file_get_contents($this->root . 'ext/phpbbmodders/knowledgebase/files/a.dat'));
	}

	public function test_nothing_happens_when_the_original_folder_is_already_gone()
	{
		$this->connect('sqlite');

		$this->adopt();

		$this->assertFalse(is_dir($this->root . 'ext'));
	}

	public function test_both_extensions_sharing_one_folder_is_not_copied_onto_itself()
	{
		$this->connect('sqlite');
		$source = $this->original_files(['a.dat' => 'x']);
		mkdir($this->root . 'ext/phpbbmodders', 0755, true);
		symlink($this->root . 'ext/sheer/knowledgebase', $this->root . 'ext/phpbbmodders/knowledgebase');

		$this->adopt();

		$this->assertSame('x', file_get_contents($source . 'a.dat'));
	}

	public function test_a_file_that_cannot_be_copied_stops_the_migration_before_the_database_is_touched()
	{
		if (function_exists('posix_geteuid') && posix_geteuid() === 0) $this->markTestSkipped('Permissions are not enforced for root');
		$this->connect('sqlite');
		$this->module(1, 'acp', '\\sheer\\knowledgebase\\acp\\config_module', 'ext_sheer/knowledgebase');
		$this->original_files(['a.dat' => 'x']);
		$target = $this->root . 'ext/phpbbmodders/knowledgebase/files/';
		mkdir($target, 0755, true);
		chmod($target, 0555);

		try
		{
			$this->adopt();
			$this->fail('The migration should have stopped');
		}
		catch (\phpbb\db\migration\exception $e)
		{
			$this->assertStringContainsString('a.dat', $e->getMessage());
		}

		$this->assertSame('\\sheer\\knowledgebase\\acp\\config_module', $this->rows('SELECT module_basename FROM phpbb_modules')[0]['module_basename']);
	}

	public function test_it_depends_on_the_last_migration_and_records_the_version()
	{
		$this->connect('sqlite');
		$migration = $this->migration();

		$this->assertSame(['\\phpbbmodders\\knowledgebase\\migrations\\version_2_0_19'], version_2_0_20::depends_on());
		$this->assertSame([['custom', [[$migration, 'adopt_original_package']]], ['config.update', ['kb_version', '2.0.20']]], $migration->update_data());
	}

	/** @dataProvider first_migration_cases */
	public function test_the_first_migration_is_skipped_on_a_board_that_ran_the_original($config, $skipped)
	{
		$this->connect('sqlite');
		$migration = new version_1_0_0(new \phpbb\config\config($config), $this->db, $this->createMock(\phpbb\db\tools\tools_interface::class), '', 'php', 'phpbb_');

		$this->assertSame($skipped, (bool) $migration->effectively_installed());
	}

	public function first_migration_cases(): array
	{
		return [
			'fresh board'                    => [[], false],
			'original 2.0.1'                 => [['kb_version' => '2.0.1'], true],
			'original 2.0.0'                 => [['kb_version' => '2.0.0'], true],
			'original 1.0.x line'            => [['knowledge_base_version' => '1.0.7'], true],
			'version before the original'    => [['kb_version' => '1.9.0'], false],
			'unrelated settings only'        => [['version' => '3.3.19'], false],
		];
	}

	public function engines(): array
	{
		return [['sqlite'], ['mysql'], ['postgres']];
	}
}
