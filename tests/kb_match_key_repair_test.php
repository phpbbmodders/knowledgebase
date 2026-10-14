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
require_once dirname(__DIR__) . '/migrations/version_2_0_25.php';

/**
 * Migration 2.0.25 leaves the native search's word-match table with one unique key over the
 * four match columns. On phpBB 4.0 with PostgreSQL or SQLite the earlier migrations left the
 * three-column key, so a word in both an article's title and description failed to insert.
 */
class kb_match_key_repair_test extends \PHPUnit\Framework\TestCase
{
	private const TABLE = 'phpbb_kb_src_wrdmtch';

	private function migration($db): version_2_0_25
	{
		return new version_2_0_25(new \phpbb\config\config(['kb_version' => '2.0.24']), $db, $this->createMock(\phpbb\db\tools\tools_interface::class), '', 'php', 'phpbb_');
	}

	/**
	 * A fake connection that reports some keys and records what runs.
	 *
	 * @param string $layer   Database layer name
	 * @param array  $rows    What the introspection query returns
	 * @param array  $queries Filled with the statements run
	 */
	private function fake_db(string $layer, array $rows, array &$queries)
	{
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('get_sql_layer')->willReturn($layer);
		$db->method('sql_escape')->willReturnArgument(0);
		$pending = [];
		$db->method('sql_query')->willReturnCallback(function ($sql) use (&$queries, &$pending, $rows) {
			$queries[] = $sql;
			// Introspection statements get the rows; the rest get nothing
			$pending = (strpos($sql, 'SHOW INDEX') === 0 || strpos($sql, 'SELECT indexname') === 0 || strpos($sql, 'PRAGMA') === 0) ? ($rows[$sql] ?? $rows['*'] ?? []) : [];
			return true;
		});
		$db->method('sql_fetchrow')->willReturnCallback(function () use (&$pending) {
			return array_shift($pending) ?? false;
		});

		return $db;
	}

	private function mysql_rows(array $keys): array
	{
		$rows = [];
		foreach ($keys as $name => $columns)
		{
			foreach ($columns as $i => $column)
			{
				$rows[] = ['Key_name' => $name, 'Non_unique' => $name === 'word_id' ? 1 : 0, 'Seq_in_index' => $i + 1, 'Column_name' => $column];
			}
		}

		return $rows;
	}

	public function test_the_three_column_key_on_mysql_is_replaced_by_the_four_column_one()
	{
		$queries = [];
		$rows = $this->mysql_rows(['kbsrcwrdmt_un_mtch' => ['article_id', 'word_id', 'title_match'], 'word_id' => ['word_id']]);
		$this->migration($this->fake_db('mysqli', ['*' => $rows], $queries))->ensure_match_key();

		$this->assertSame([
			'SHOW INDEX FROM phpbb_kb_src_wrdmtch',
			'ALTER TABLE phpbb_kb_src_wrdmtch DROP INDEX kbsrcwrdmt_un_mtch',
			'ALTER TABLE phpbb_kb_src_wrdmtch ADD UNIQUE un_mtch (article_id, word_id, title_match, descr_match) USING BTREE',
		], $queries);
	}

	public function test_the_three_column_key_on_postgresql_is_replaced_by_the_four_column_one()
	{
		$queries = [];
		$rows = [
			['indexname' => 'kbsrcwrdmt_article_id', 'indexdef' => 'CREATE INDEX kbsrcwrdmt_article_id ON public.phpbb_kb_src_wrdmtch USING btree (article_id)'],
			['indexname' => 'kbsrcwrdmt_un_mtch', 'indexdef' => 'CREATE UNIQUE INDEX kbsrcwrdmt_un_mtch ON public.phpbb_kb_src_wrdmtch USING btree (article_id, word_id, title_match)'],
		];
		$this->migration($this->fake_db('postgres', ['*' => $rows], $queries))->ensure_match_key();

		$this->assertSame([
			"SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'phpbb_kb_src_wrdmtch'",
			'DROP INDEX kbsrcwrdmt_un_mtch',
			'CREATE UNIQUE INDEX un_mtch ON phpbb_kb_src_wrdmtch (article_id, word_id, title_match, descr_match)',
		], $queries);
	}

	public function test_a_board_that_already_has_the_four_column_key_is_left_alone()
	{
		foreach ([['mysqli', $this->mysql_rows(['un_mtch' => ['article_id', 'word_id', 'title_match', 'descr_match']])], ['postgres', [['indexname' => 'un_mtch', 'indexdef' => 'CREATE UNIQUE INDEX un_mtch ON public.phpbb_kb_src_wrdmtch USING btree (article_id, word_id, title_match, descr_match)']]]] as [$layer, $rows])
		{
			$queries = [];
			$this->migration($this->fake_db($layer, ['*' => $rows], $queries))->ensure_match_key();

			$this->assertCount(1, $queries, $layer . ': only the question is asked');
		}
	}

	public function test_an_unrelated_unique_key_is_left_and_a_missing_key_is_just_added()
	{
		$queries = [];
		$rows = $this->mysql_rows(['un_other' => ['author_id']]);
		$this->migration($this->fake_db('mysqli', ['*' => $rows], $queries))->ensure_match_key();

		$this->assertSame(['SHOW INDEX FROM phpbb_kb_src_wrdmtch', 'ALTER TABLE phpbb_kb_src_wrdmtch ADD UNIQUE un_mtch (article_id, word_id, title_match, descr_match) USING BTREE'], $queries);
	}

	public function test_a_database_this_does_not_read_is_left_as_it_is()
	{
		$queries = [];
		$this->migration($this->fake_db('mssql_odbc', [], $queries))->ensure_match_key();

		$this->assertSame([], $queries);
	}

	public function test_it_repairs_a_real_sqlite_table_so_a_word_in_title_and_description_inserts()
	{
		if (!class_exists(\phpbb\db\driver\sqlite3::class))
		{
			$this->markTestSkipped('This phpBB has no SQLite driver.');
		}
		$db = new \phpbb\db\driver\sqlite3();
		$this->assertTrue($db->sql_connect(':memory:', '', '', ''));
		$db->sql_query('CREATE TABLE ' . self::TABLE . ' (article_id INTEGER, word_id INTEGER, title_match INTEGER, descr_match INTEGER)');
		$db->sql_query('CREATE UNIQUE INDEX kbsrcwrdmt_un_mtch ON ' . self::TABLE . ' (article_id, word_id, title_match)');
		$db->sql_query('CREATE INDEX kbsrcwrdmt_word_id ON ' . self::TABLE . ' (word_id)');
		$db->sql_query('INSERT INTO ' . self::TABLE . ' VALUES (1, 7, 0, 0)');

		// The symptom: the same word as a description match of the same article is refused
		$db->sql_return_on_error(true);
		$refused = $db->sql_query('INSERT INTO ' . self::TABLE . ' VALUES (1, 7, 0, 1)');
		$db->sql_return_on_error(false);
		$this->assertFalse($refused);

		$migration = $this->migration($db);
		$migration->ensure_match_key();

		$this->assertSame(['un_mtch' => version_2_0_25::MATCH_COLUMNS], $migration->unique_keys(self::TABLE, 'sqlite3'));
		$this->assertTrue((bool) $db->sql_query('INSERT INTO ' . self::TABLE . ' VALUES (1, 7, 0, 1)'));

		$migration->ensure_match_key();
		$this->assertSame(['un_mtch' => version_2_0_25::MATCH_COLUMNS], $migration->unique_keys(self::TABLE, 'sqlite3'));
		$db->sql_close();
	}

	public function test_the_migration_follows_2_0_24_and_sets_the_version()
	{
		$queries = [];
		$migration = $this->migration($this->fake_db('mssql_odbc', [], $queries));

		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_24'], version_2_0_25::depends_on());
		$this->assertSame([['custom', [[$migration, 'ensure_match_key']]], ['config.update', ['kb_version', '2.0.25']]], $migration->update_data());
		$this->assertSame([['config.update', ['kb_version', '2.0.24']]], $migration->revert_data());
	}
}
