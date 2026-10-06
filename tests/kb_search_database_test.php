<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\search\backend;

require_once __DIR__ . '/kb_search_sql_test.php';
require_once dirname(__DIR__) . '/migrations/version_1_0_0.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_15.php';
if (!defined('IN_PHPBB')) define('IN_PHPBB', true);
if (!defined('CONFIG_TABLE')) define('CONFIG_TABLE', 'phpbb_config');
if (!defined('SEARCH_RESULTS_TABLE')) define('SEARCH_RESULTS_TABLE', 'phpbb_search_results');
require_once (getenv('PHPBB_ROOT_PATH') ?: dirname(__DIR__, 4)) . '/includes/functions.php';

/** Opt-in tests against disposable localhost databases named kb_fixture. */
class kb_search_database_test extends \PHPUnit\Framework\TestCase
{
	private $db;
	private $tools;
	private $config;
	private $class;

	private function connect($engine)
	{
		$port = getenv('KB_TEST_' . strtoupper($engine) . '_PORT');
		if (!$port) $this->markTestSkipped('Set the fixture database port to run integration tests');
		$this->assertTrue(ctype_digit($port));
		$this->db = $engine === 'mysql' ? new \phpbb\db\driver\mysqli() : new \phpbb\db\driver\postgres();
		$this->db->sql_return_on_error(true);
		$this->assertIsObject($this->db->sql_connect('127.0.0.1', $engine === 'mysql' ? 'root' : 'postgres', '', 'kb_fixture', (int) $port));
		$this->tools = $engine === 'mysql' ? new \phpbb\db\tools\tools($this->db) : new \phpbb\db\tools\postgres($this->db);
		$this->class = $engine === 'mysql' ? kb_fulltext_mysql::class : kb_fulltext_postgres::class;
		$this->config = new \phpbb\config\config(['fulltext_postgres_ts_name' => 'english', 'search_block_size' => 100, 'num_posts' => 99, 'kb_num_articles' => 3]);
		foreach (['phpbb_kb_articles', 'phpbb_kb_search_results', 'phpbb_kb_src_wrdmtch', 'phpbb_kb_article_categories', SEARCH_RESULTS_TABLE] as $table)
		{
			$this->query('DROP TABLE IF EXISTS ' . $table);
		}
		$old = new \phpbbmodders\knowledgebase\migrations\version_1_0_0($this->config, $this->db, $this->tools, '', 'php', 'phpbb_');
		$schema = $old->update_schema()['add_tables'];
		foreach (['phpbb_kb_articles', 'phpbb_kb_search_results', 'phpbb_kb_src_wrdmtch'] as $table)
		{
			$this->tools->sql_create_table($table, $schema[$table]);
		}
		$this->query('CREATE TABLE phpbb_kb_article_categories (article_id INTEGER, category_id INTEGER)');
		$this->query('INSERT INTO phpbb_kb_article_categories VALUES (1, 2)');
		$this->query('CREATE TABLE ' . SEARCH_RESULTS_TABLE . ' (marker INTEGER)');
		$this->query('INSERT INTO ' . SEARCH_RESULTS_TABLE . ' VALUES (123)');
		foreach ([[1, 1, 2, "O'Brien"], [2, 0, 2, "O'Brien"], [3, 1, 3, 'Another author'], [4, 1, 1, "O'Brien"]] as $row)
		{
			$this->query('INSERT INTO phpbb_kb_articles ' . $this->db->sql_build_array('INSERT', ['article_id' => $row[0], 'article_category_id' => 1, 'approved' => $row[1], 'author_id' => $row[2], 'author' => $row[3], 'article_title' => 'Migration guide', 'article_description' => 'Extension migration instructions', 'article_body' => 'A migration guide for extension developers', 'article_date' => $row[0] * 100]));
		}
	}

	protected function tearDown(): void
	{
		if ($this->db) $this->db->sql_close();
	}

	private function query($sql)
	{
		$result = $this->db->sql_query($sql);
		if ($result === false) throw new \RuntimeException(json_encode($this->db->sql_error()));
		return $result;
	}

	private function backend()
	{
		$backend = $this->getMockBuilder($this->class)->disableOriginalConstructor()->onlyMethods(['obtain_ids', 'save_ids'])->getMock();
		$backend->method('obtain_ids')->willReturn(0);
		$language = $this->getMockBuilder(\phpbb\language\language::class)->disableOriginalConstructor()->getMock();
		$language->method('lang')->willReturnArgument(0);
		foreach (['db' => $this->db, 'config' => $this->config, 'language' => $language, 'articles_table' => 'phpbb_kb_articles', 'search_results_table' => 'phpbb_kb_search_results', 'article_categories_table' => 'phpbb_kb_article_categories', 'search_query' => $this->class === kb_fulltext_mysql::class ? 'migration' : '+migration', 'split_words' => ['migration']] as $name => $value)
		{
			$property = new \ReflectionProperty($backend, $name);
			$property->setAccessible(true);
			$property->setValue($backend, $value);
		}
		if ($this->class === kb_fulltext_postgres::class)
		{
			$property = new \ReflectionProperty($backend, 'tsearch_query');
			$property->setAccessible(true);
			$property->setValue($backend, 'migration');
		}
		return $backend;
	}

	/** @dataProvider engines */
	public function test_index_create_delete_recreate_and_core_cache_isolation($engine)
	{
		$this->connect($engine);
		$backend = $this->backend();
		$this->assertFalse($backend->index_created());
		$backend->create_index();
		$this->assertTrue($backend->index_created());
		$stats = $backend->index_stats();
		$this->assertSame(4, (int) reset($stats));
		$this->query('INSERT INTO phpbb_kb_search_results ' . $this->db->sql_build_array('INSERT', ['search_key' => 'fixture', 'search_time' => 0, 'search_keywords' => 'migration', 'search_authors' => '']));
		$backend->delete_index();
		$this->assertFalse($backend->index_created());
		$result = $this->query('SELECT COUNT(*) AS cached FROM phpbb_kb_search_results');
		$this->assertSame(0, (int) $this->db->sql_fetchfield('cached', false, $result));
		$this->db->sql_freeresult($result);
		$result = $this->query('SELECT marker FROM ' . SEARCH_RESULTS_TABLE);
		$this->assertSame(123, (int) $this->db->sql_fetchfield('marker', false, $result));
		$this->db->sql_freeresult($result);
		$backend->create_index();
		$this->assertTrue($backend->index_created());
	}

	/** @dataProvider engines */
	public function test_keywords_authors_approval_and_legacy_index_repair($engine)
	{
		$this->connect($engine);
		$this->backend()->create_index();
		$backend = $this->backend();
		foreach ([
			[[], '', 0, [], 'all', [4, 3, 1]],
			[[2], "O'Brien", 0, [], 'all', [4, 1]],
			[[], '', 2, [], 'all', [1]],
			[[], '', 0, [], 'titleonly', [4, 3, 1]],
			[[], '', 0, [], 'msgonly', [4, 3, 1]],
			[[9], '', 0, [], 'all', []],
			[[], '', 0, [1], 'all', []],
		] as [$authors, $name, $category, $excluded, $fields, $expected])
		{
			$ids = [];
			$start = 0;
			$count = $backend->keyword_search('posts', $fields, 'all', ['t' => 'p.article_date'], 't', 'd', '0', $excluded, $category, $authors, $name, $ids, $start, 10);
			$this->assertSame(count($expected), (int) $count);
			$this->assertSame($expected, array_map('intval', $ids));
			$this->assertGreaterThanOrEqual(0, $start);
			$this->assertFalse($this->db->get_transaction());
		}
		foreach ([[[2], [1]], [[9], []]] as [$authors, $expected])
		{
			$ids = [];
			$start = 0;
			$count = $backend->author_search('posts', ['t' => 'p.article_date'], 't', 'd', '0', [], 0, $authors, '', $ids, $start, 10);
			$this->assertSame(count($expected), (int) $count);
			$this->assertSame($expected, array_map('intval', $ids));
			$this->assertFalse($this->db->get_transaction());
		}
		$ids = [];
		$start = 100;
		$count = $backend->keyword_search('posts', 'all', 'all', ['t' => 'p.article_date'], 't', 'd', '0', [], 0, [], '', $ids, $start, 10);
		$this->assertSame(3, $count);
		$this->assertSame([4, 3, 1], array_map('intval', $ids));
		$this->assertSame(0, (int) $start);
		$this->assertFalse($this->db->get_transaction());
		$this->query('ALTER TABLE phpbb_kb_src_wrdmtch ADD COLUMN descr_match INTEGER NOT NULL DEFAULT 0');
		$this->query('INSERT INTO phpbb_kb_src_wrdmtch (article_id, word_id, title_match, descr_match) VALUES (1, 1, 0, 0)');
		$repair = new \phpbbmodders\knowledgebase\migrations\version_2_0_15($this->config, $this->db, $this->tools, '', 'php', 'phpbb_');
		$this->tools->perform_schema_changes($repair->update_schema());
		$this->query('INSERT INTO phpbb_kb_src_wrdmtch (article_id, word_id, title_match, descr_match) VALUES (1, 1, 0, 1)');
		$this->tools->perform_schema_changes($repair->update_schema());
		$result = $this->query('SELECT COUNT(*) AS matches FROM phpbb_kb_src_wrdmtch');
		$this->assertSame(2, (int) $this->db->sql_fetchfield('matches', false, $result));
		$this->db->sql_freeresult($result);
	}

	public function engines()
	{
		return [['mysql'], ['postgres']];
	}
}
