<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\controller;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';

function gen_sort_selects(...$args) {}

class kb_acp_counts_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider count_actions */
	public function test_scoped_lists_and_drafts_do_not_overwrite_global_counts($method, $initial_count, $expected_count)
	{
		$pdo = new \PDO('sqlite::memory:');
		$pdo->exec('CREATE TABLE articles (article_id INTEGER, article_category_id INTEGER, status INTEGER)');
		$pdo->exec('INSERT INTO articles VALUES (1, 1, 0), (2, 2, 0), (3, 1, ' . \phpbbmodders\knowledgebase\inc\functions_kb::STATUS_DRAFT . ')');
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('sql_query')->willReturnCallback(fn($sql) => $pdo->query($sql));
		$db->method('sql_fetchrow')->willReturnCallback(fn($result) => $result->fetch(\PDO::FETCH_ASSOC));
		$db->method('sql_freeresult')->willReturnCallback(function () { throw new \RuntimeException('count query completed'); });
		$config = new \phpbb\config\config(['kb_num_articles' => $initial_count, 'kb_articles_per_page' => 10]);
		$request = $this->createMock(\phpbb\request\request_interface::class);
		$request->method('variable')->willReturnCallback(fn($key, $default) => $key === 'cat_id' ? 1 : $default);
		$language = $this->getMockBuilder(\phpbb\language\language::class)->disableOriginalConstructor()->getMock();
		$language->method('lang')->willReturnArgument(0);
		$controller = (new \ReflectionClass(acp_controller::class))->newInstanceWithoutConstructor();
		foreach (['db' => $db, 'config' => $config, 'request' => $request, 'language' => $language, 'articles_table' => 'articles'] as $name => $value)
		{
			$property = new \ReflectionProperty(acp_controller::class, $name);
			$property->setAccessible(true);
			$property->setValue($controller, $value);
		}
		try
		{
			$controller->$method('', '');
			$this->fail('Expected the stop after the count query');
		}
		catch (\RuntimeException $error)
		{
			$this->assertSame('count query completed', $error->getMessage());
		}
		$this->assertSame($expected_count, (int) $config['kb_num_articles']);
	}

	public function count_actions()
	{
		return [['show_articles', 8, 8], ['search_settings', 0, 2]];
	}
}
