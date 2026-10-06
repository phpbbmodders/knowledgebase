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

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/search/backend/kb_base.php';
require_once dirname(__DIR__) . '/search/backend/kb_search_backend_interface.php';
require_once dirname(__DIR__) . '/search/backend/kb_fulltext_mysql.php';
require_once dirname(__DIR__) . '/search/backend/kb_fulltext_postgres.php';
if (!defined('ANONYMOUS')) define('ANONYMOUS', 1);
if (!defined('POSTS_TABLE')) define('POSTS_TABLE', 'phpbb_posts');

class kb_search_sql_test extends \PHPUnit\Framework\TestCase
{
	private function inject($object, $name, $value)
	{
		$property = new \ReflectionProperty($object, $name);
		$property->setAccessible(true);
		$property->setValue($object, $value);
	}

	private function driver()
	{
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('sql_escape')->willReturnCallback(fn($value) => str_replace("'", "''", $value));
		$db->method('sql_in_set')->willReturnCallback(fn($field, $ids) => $field . ' IN (' . implode(',', $ids) . ')');
		return $db;
	}

	/** @dataProvider keyword_backends */
	public function test_keywords_and_author_names_are_quoted($class, $query)
	{
		$backend = $this->getMockBuilder($class)->disableOriginalConstructor()->onlyMethods(['obtain_ids'])->getMock();
		$backend->method('obtain_ids')->willReturn(0);
		$db = $this->driver();
		$sql = '';
		$db->method('sql_query_limit')->willReturnCallback(function ($value) use (&$sql) {
			$sql = $value;
			throw new \RuntimeException('SQL captured');
		});
		foreach (['db' => $db, 'config' => new \phpbb\config\config(['search_block_size' => 100, 'fulltext_postgres_ts_name' => 'english']), 'search_query' => $query, 'tsearch_query' => 'migration', 'articles_table' => 'kb_articles', 'article_categories_table' => 'kb_article_categories'] as $name => $value)
		{
			if ($name !== 'tsearch_query' || $class === kb_fulltext_postgres::class) $this->inject($backend, $name, $value);
		}
		$ids = [];
		$start = 0;
		try { $backend->keyword_search('posts', 'all', 'all', ['t' => 'p.article_date'], 't', 'd', '0', [], 0, [2], "O'Brien", $ids, $start, 10); }
		catch (\RuntimeException $error) { $this->assertSame('SQL captured', $error->getMessage()); }
		$this->assertStringContainsString('SELECT p.article_id', $sql);
		$this->assertStringContainsString("p.author = 'O''Brien'", $sql);
		$this->assertStringContainsString('p.approved = 1', $sql);
		$this->assertStringContainsString('ORDER BY p.article_date DESC', $sql);
		$this->assertStringNotContainsString('$sql_', $sql);
		if ($class === kb_fulltext_mysql::class) $this->assertStringContainsString("AGAINST ('migration''s' IN BOOLEAN MODE)", $sql);
	}

	public function keyword_backends()
	{
		return [[kb_fulltext_mysql::class, "migration's"], [kb_fulltext_postgres::class, '+migration']];
	}

	public function test_postgres_indexes_target_article_columns_and_match_discovery_names()
	{
		$backend = $this->getMockBuilder(kb_fulltext_postgres::class)->disableOriginalConstructor()->onlyMethods(['init'])->getMock();
		$backend->method('init')->willReturn(false);
		$db = $this->driver();
		$queries = [];
		$db->method('sql_query')->willReturnCallback(function ($sql) use (&$queries) { $queries[] = $sql; return false; });
		foreach (['db' => $db, 'config' => new \phpbb\config\config(['fulltext_postgres_ts_name' => 'english']), 'articles_table' => 'kb_articles', 'search_results_table' => 'kb_results', 'stats' => ['total_posts' => 0]] as $name => $value) $this->inject($backend, $name, $value);
		$backend->create_index();
		$this->assertCount(5, $queries);
		foreach (['article_title', 'article_description', 'article_body', 'article_content'] as $index => $field)
		{
			$this->assertStringContainsString('CREATE INDEX kb_articles_english_' . $field . ' ON kb_articles', $queries[$index]);
			$this->assertStringNotContainsString('phpbb_posts', $queries[$index]);
		}
		$this->assertStringContainsString("article_title || ' ' || article_body || ' ' || article_description", $queries[3]);
		$this->assertStringNotContainsString(', article_content', $queries[3]);
	}
}
