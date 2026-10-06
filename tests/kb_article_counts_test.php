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
require_once dirname(__DIR__) . '/inc/functions_kb.php';
if (!defined('IN_PHPBB')) define('IN_PHPBB', true);

function delete_topics($type, $ids, $auto_sync, $post_count_sync, $call_delete_posts) {}

class kb_article_counts_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider article_statuses */
	public function test_delete_and_move_do_not_count_drafts($status, $counted)
	{
		$pdo = new \PDO('sqlite::memory:');
		$pdo->exec('CREATE TABLE articles (article_id INTEGER, article_category_id INTEGER, display_order INTEGER)');
		$pdo->exec('INSERT INTO articles VALUES (1, 1, 1)');
		$pdo->exec('CREATE TABLE categories (category_id INTEGER, number_articles INTEGER)');
		$pdo->exec('INSERT INTO categories VALUES (1, 5), (2, 3)');
		$pdo->exec('CREATE TABLE attachments (attach_id INTEGER, article_id INTEGER, physical_filename TEXT, thumbnail INTEGER)');
		$pdo->exec('CREATE TABLE article_categories (article_id INTEGER)');
		$pdo->exec('CREATE TABLE article_field_values (article_id INTEGER)');
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('sql_query')->willReturnCallback(fn($sql) => $pdo->query($sql));
		$db->method('sql_fetchrow')->willReturnCallback(fn($result) => $result->fetch(\PDO::FETCH_ASSOC));
		$kb = $this->getMockBuilder(functions_kb::class)->disableOriginalConstructor()->onlyMethods(['get_kb_article_info', 'get_cat_info', 'delete_article_revision', 'delete_article_draft_edit'])->getMock();
		$kb->method('get_kb_article_info')->willReturn(['article_category_id' => 1, 'display_order' => 1, 'status' => $status, 'topic_id' => 0]);
		$kb->method('get_cat_info')->willReturnCallback(function ($id) use ($pdo) {
			return ['number_articles' => (int) $pdo->query('SELECT number_articles FROM categories WHERE category_id = ' . (int) $id)->fetchColumn(), 'category_name' => 'category'];
		});
		$config = new \phpbb\config\config(['kb_num_articles' => 8]);
		$cache = $this->createMock(\phpbb\cache\driver\driver_interface::class);
		$log = $this->getMockBuilder(\phpbb\log\log::class)->disableOriginalConstructor()->getMock();
		$user = $this->getMockBuilder(\phpbb\user::class)->disableOriginalConstructor()->getMock();
		$user->data = ['user_id' => 2, 'user_ip' => '127.0.0.1'];
		foreach (['db' => $db, 'config' => $config, 'cache' => $cache, 'log' => $log, 'user' => $user, 'phpbb_root_path' => getenv('PHPBB_ROOT_PATH') . '/', 'php_ext' => 'php', 'articles_table' => 'articles', 'categories_table' => 'categories', 'attachments_table' => 'attachments', 'article_categories_table' => 'article_categories', 'article_field_values_table' => 'article_field_values'] as $name => $value)
		{
			$property = new \ReflectionProperty(functions_kb::class, $name);
			$property->setAccessible(true);
			$property->setValue($kb, $value);
		}
		$kb->kb_move_article(1, 'title', 1, 2, 1);
		$this->assertSame(5 - $counted, (int) $pdo->query('SELECT number_articles FROM categories WHERE category_id = 1')->fetchColumn());
		$this->assertSame(3 + $counted, (int) $pdo->query('SELECT number_articles FROM categories WHERE category_id = 2')->fetchColumn());
		$this->assertSame(8, (int) $config['kb_num_articles']);
		// Restore the fixture's primary category before testing deletion independently.
		$pdo->exec('UPDATE categories SET number_articles = 5 WHERE category_id = 1');
		$kb->kb_delete_article(1, 'title');
		$this->assertSame(5 - $counted, (int) $pdo->query('SELECT number_articles FROM categories WHERE category_id = 1')->fetchColumn());
		$this->assertSame(8 - $counted, (int) $config['kb_num_articles']);
		$this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn());
	}

	public function article_statuses()
	{
		return [[functions_kb::STATUS_DRAFT, 0], [functions_kb::STATUS_NEW, 1], [functions_kb::STATUS_ACTIVE, 1]];
	}
}
