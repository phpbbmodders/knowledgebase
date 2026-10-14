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
require_once dirname(__DIR__) . '/migrations/version_1_0_3.php';

/**
 * Migration 1.0.3 replaces the word-match table's unique key on MySQL. The key is named
 * un_mtch on phpBB 3.3 and kbsrcwrdmt_un_mtch on phpBB 4.0, and dropping the wrong name
 * stopped the extension from being enabled on 4.0 with MySQL.
 */
class kb_search_key_migration_test extends \PHPUnit\Framework\TestCase
{
	/**
	 * Run add_key() on a fake MySQL connection that reports some indexes.
	 *
	 * @param array  $indexes Rows of SHOW INDEX: [Key_name, Non_unique, Column_name]
	 * @param string $layer   The SQL layer name
	 * @return string[] The statements that were run
	 */
	private function run_add_key(array $indexes, string $layer = 'mysqli'): array
	{
		$rows = array_map(fn($i) => ['Key_name' => $i[0], 'Non_unique' => $i[1], 'Column_name' => $i[2]], $indexes);
		$queries = [];
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('get_sql_layer')->willReturn($layer);
		$db->method('sql_query')->willReturnCallback(function ($sql) use (&$queries) {
			$queries[] = $sql;
			return true;
		});
		$db->method('sql_fetchrow')->willReturnCallback(function () use (&$rows) {
			return array_shift($rows) ?? false;
		});

		$migration = new version_1_0_3(new \phpbb\config\config([]), $db, $this->createMock(\phpbb\db\tools\tools_interface::class), '', 'php', 'phpbb_');
		$migration->add_key();

		return $queries;
	}

	private const NEW_KEY = 'ADD UNIQUE un_mtch (article_id, word_id, title_match, descr_match) USING BTREE';

	/** @dataProvider old_key_names */
	public function test_the_old_unique_key_is_dropped_under_whatever_name_the_core_gave_it($indexes, string $expected_sql)
	{
		$queries = $this->run_add_key($indexes);

		$this->assertSame('SHOW INDEX FROM phpbb_kb_src_wrdmtch', $queries[0]);
		$this->assertSame($expected_sql, $queries[1]);
		$this->assertCount(2, $queries);
	}

	public function old_key_names(): array
	{
		$other = [['word_id', 1, 'word_id'], ['article_id', 1, 'article_id']];
		$old_columns = ['article_id', 'word_id', 'title_match'];
		$named = fn(string $name) => array_map(fn($c) => [$name, 0, $c], $old_columns);

		return [
			'phpBB 3.3: un_mtch'                  => [array_merge([['PRIMARY', 0, 'article_id']], $named('un_mtch'), $other), 'ALTER TABLE phpbb_kb_src_wrdmtch DROP INDEX un_mtch, ' . self::NEW_KEY],
			'phpBB 4.0: kbsrcwrdmt_un_mtch'       => [array_merge($named('kbsrcwrdmt_un_mtch'), [['kbsrcwrdmt_word_id', 1, 'word_id']]), 'ALTER TABLE phpbb_kb_src_wrdmtch DROP INDEX kbsrcwrdmt_un_mtch, ' . self::NEW_KEY],
			'no old key at all'                   => [$other, 'ALTER TABLE phpbb_kb_src_wrdmtch ' . self::NEW_KEY],
			'an unrelated unique key is left'     => [array_merge($named('un_mtch'), [['un_other', 0, 'author_id']]), 'ALTER TABLE phpbb_kb_src_wrdmtch DROP INDEX un_mtch, ' . self::NEW_KEY],
		];
	}

	public function test_the_other_databases_still_use_their_own_statements()
	{
		foreach (['postgres', 'sqlite3'] as $layer)
		{
			$queries = $this->run_add_key([], $layer);

			$this->assertSame(['DROP INDEX IF EXISTS un_mtch', 'CREATE UNIQUE INDEX un_mtch ON phpbb_kb_src_wrdmtch (article_id, word_id, title_match, descr_match)'], $queries, $layer);
		}
	}
}
