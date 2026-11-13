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
require_once dirname(__DIR__) . '/migrations/version_2_0_1.php';
if (!defined('CONFIG_TEXT_TABLE')) define('CONFIG_TEXT_TABLE', 'phpbb_config_text');

/**
 * Two steps for boards that still hold an older Knowledge Base's settings.
 *
 * version_1_0_0 drops the old kb_config table's is_dynamic column through
 * phpBB's database tools, which work on every database (a plain DROP COLUMN
 * fails on MSSQL while the column has a default). version_2_0_1 converts the
 * old serialized kb_extensions setting to JSON without creating any object.
 */
class kb_legacy_migration_test extends \PHPUnit\Framework\TestCase
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
				$this->sqlite_fixture = tempnam(sys_get_temp_dir(), 'kb-legacy-');
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
	public function test_the_old_settings_table_loses_is_dynamic_and_gets_the_new_settings($engine)
	{
		$this->connect($engine);
		$tables = ['phpbb_kb_config', 'phpbb_extensions', 'phpbb_extension_groups', 'phpbb_config'];
		foreach ($tables as $table)
		{
			if ($this->tools->sql_table_exists($table)) $this->tools->sql_table_drop($table);
		}

		// The tables set_default_config() reads, as an older Knowledge Base left them
		$this->tools->sql_create_table('phpbb_extension_groups', ['COLUMNS' => ['group_id' => ['UINT', 0], 'group_name' => ['VCHAR', ''], 'allow_group' => ['BOOL', 0]]]);
		$this->tools->sql_create_table('phpbb_extensions', ['COLUMNS' => ['group_id' => ['UINT', 0], 'extension' => ['VCHAR:100', '']]]);
		$this->tools->sql_create_table('phpbb_config', ['COLUMNS' => ['config_name' => ['VCHAR', ''], 'config_value' => ['VCHAR_UNI', '']]]);
		$this->tools->sql_create_table('phpbb_kb_config', ['COLUMNS' => [
			'config_name'  => ['VCHAR', ''],
			'config_value' => ['TEXT_UNI', ''],
			'is_dynamic'   => ['BOOL', 0],
		]]);
		$this->db->sql_query("INSERT INTO phpbb_extension_groups (group_id, group_name, allow_group) VALUES (1, 'IMAGES', 1)");
		$this->db->sql_query("INSERT INTO phpbb_extensions (group_id, extension) VALUES (1, 'png')");
		$this->db->sql_query("INSERT INTO phpbb_config (config_name, config_value) VALUES ('max_filesize', '262144')");
		$this->db->sql_query("INSERT INTO phpbb_kb_config (config_name, config_value, is_dynamic) VALUES ('anounce', '1', 0)");

		$migration = new version_1_0_0(new \phpbb\config\config([]), $this->db, $this->tools, '', 'php', 'phpbb_', []);
		$migration->set_default_config();

		$this->assertFalse($this->tools->sql_column_exists('phpbb_kb_config', 'is_dynamic'));
		$result = $this->db->sql_query('SELECT config_name, config_value FROM phpbb_kb_config');
		$settings = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$settings[$row['config_name']] = $row['config_value'];
		}
		$this->db->sql_freeresult($result);
		$this->assertSame('1', $settings['anounce']);
		$this->assertSame('262144', $settings['max_filesize']);
		$this->assertSame(['IMAGES' => ['png']], unserialize($settings['extensions']));

		foreach ($tables as $table)
		{
			$this->tools->sql_table_drop($table);
		}
	}

	public function test_the_serialized_extensions_setting_creates_no_object()
	{
		kb_legacy_wakeup_probe::$woken = false;
		$stored = serialize(['IMAGES' => ['png', 'gif'], 'evil' => new kb_legacy_wakeup_probe()]);

		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('sql_query')->willReturn(true);
		$db->method('sql_fetchrow')->willReturn(['config_value' => $stored]);
		$migration = new version_2_0_1(new \phpbb\config\config([]), $db, $this->createMock(\phpbb\db\tools\tools_interface::class), '', 'php', 'phpbb_', []);

		$steps = $migration->update_data();

		$this->assertFalse(kb_legacy_wakeup_probe::$woken);
		$this->assertSame('config_text.update', $steps[0][0]);
		$converted = json_decode($steps[0][1][1], true);
		$this->assertSame(['png', 'gif'], $converted['IMAGES']);
		$this->assertArrayNotHasKey('woken', $converted['evil']);
	}
}

/**
 * A class whose __wakeup() records that unserialize() created it
 */
class kb_legacy_wakeup_probe
{
	public static $woken = false;

	public function __wakeup()
	{
		self::$woken = true;
	}
}
