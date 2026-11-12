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
require_once dirname(__DIR__) . '/migrations/version_1_0_5.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_0.php';

/**
 * The first migrations stopped the extension from being enabled on a fresh MSSQL
 * board: fixed kb_options ids need IDENTITY_INSERT, kb_config's text column takes
 * no numbers, and MSSQL will not start a transaction while a result is open.
 */
class kb_mssql_migration_test extends \PHPUnit\Framework\TestCase
{
	/** @var string[] Statements run through sql_query() */
	private $queries = [];

	/** @var array[] [table, rows] for each sql_multi_insert() call */
	private $multi_inserts = [];

	/** @var int Results opened by a SELECT minus results freed */
	private $open_results = 0;

	/**
	 * A fake connection that answers the SELECTs of version_1_0_0 and records the rest.
	 *
	 * @param string $layer     The SQL layer name
	 * @param array  $kb_config Rows the old kb_config table holds
	 * @return \phpbb\db\driver\driver_interface
	 */
	private function fake_db(string $layer, array $kb_config = [])
	{
		$rowsets = [
			'extension_groups' => [['extension' => 'png', 'group_name' => 'IMAGES'], ['extension' => 'zip', 'group_name' => 'ARCHIVES']],
			'max_filesize'     => [['config_value' => '262144']],
			'kb_config'        => $kb_config,
		];
		$rows = [];

		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('get_sql_layer')->willReturn($layer);
		$db->method('sql_query')->willReturnCallback(function ($sql) use (&$rows, $rowsets) {
			$this->queries[] = $sql;
			$rows = [];
			if (strpos($sql, 'SELECT') === 0)
			{
				$this->open_results++;
				foreach ($rowsets as $needle => $set)
				{
					if (strpos($sql, $needle) !== false)
					{
						$rows = $set;
						break;
					}
				}
			}
			return true;
		});
		$db->method('sql_fetchrow')->willReturnCallback(function () use (&$rows) {
			return array_shift($rows) ?? false;
		});
		$db->method('sql_fetchfield')->willReturnCallback(function ($field) use (&$rows) {
			$row = array_shift($rows);
			return $row[$field] ?? false;
		});
		$db->method('sql_freeresult')->willReturnCallback(function () {
			$this->open_results--;
			return true;
		});
		$db->method('sql_multi_insert')->willReturnCallback(function ($table, $rows) {
			$this->queries[] = 'MULTI INSERT ' . $table;
			$this->multi_inserts[] = [$table, $rows];
			return true;
		});
		$db->method('sql_build_array')->willReturnCallback(function ($type, $data) {
			return '(' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_map(fn($v) => is_string($v) ? "'$v'" : $v, $data)) . ')';
		});

		return $db;
	}

	private function migration(string $class, string $layer, array $kb_config = [])
	{
		return new $class(new \phpbb\config\config([]), $this->fake_db($layer, $kb_config), $this->createMock(\phpbb\db\tools\tools_interface::class), '', 'php', 'phpbb_', []);
	}

	public function test_mssql_inserts_the_fixed_option_ids_with_identity_insert_on()
	{
		$this->migration(version_1_0_0::class, 'mssqlnative')->update_kb_options_table();

		$this->assertSame([
			'SELECT * FROM phpbb_kb_options',
			'SET IDENTITY_INSERT phpbb_kb_options ON',
			'MULTI INSERT phpbb_kb_options',
			'SET IDENTITY_INSERT phpbb_kb_options OFF',
		], $this->queries);
		$this->assertSame(range(1, 7), array_column($this->multi_inserts[0][1], 'auth_option_id'));
	}

	public function test_other_databases_insert_the_option_ids_without_identity_insert()
	{
		foreach (['mysqli', 'postgres', 'sqlite3'] as $layer)
		{
			$this->queries = [];
			$this->migration(version_1_0_0::class, $layer)->update_kb_options_table();

			$this->assertSame(['SELECT * FROM phpbb_kb_options', 'MULTI INSERT phpbb_kb_options'], $this->queries, $layer);
		}
	}

	public function test_default_kb_config_values_are_strings_and_no_result_is_left_open()
	{
		// kb_config is empty on a fresh board
		$this->migration(version_1_0_0::class, 'mssqlnative')->set_default_config();

		$this->assertSame(0, $this->open_results, 'every SELECT result is freed');
		$this->assertCount(1, $this->multi_inserts);
		[$table, $rows] = $this->multi_inserts[0];
		$this->assertSame('phpbb_kb_config', $table);

		$values = array_column($rows, 'config_value', 'config_name');
		$this->assertEqualsCanonicalizing(
			['allow_attachments', 'anounce', 'articles_per_page', 'forum_id', 'max_filesize', 'extensions', 'thumbnail', 'max_attachments'],
			array_keys($values)
		);
		foreach ($values as $name => $value)
		{
			$this->assertIsString($value, $name);
		}
		$this->assertSame('262144', $values['max_filesize']);
		$this->assertSame('10', $values['articles_per_page']);
	}

	public function test_the_sort_type_config_value_is_a_string()
	{
		$this->migration(version_1_0_5::class, 'mssqlnative')->add_config();

		$this->assertSame(["INSERT INTO phpbb_kb_config (config_name, config_value) VALUES ('sort_type', '0')"], $this->queries);
	}

	public function test_reading_the_old_kb_config_frees_its_result()
	{
		$this->migration(version_2_0_0::class, 'mssqlnative', [['config_name' => 'sort_type', 'config_value' => '0']])->update_schema();

		$this->assertSame(0, $this->open_results);
	}
}
