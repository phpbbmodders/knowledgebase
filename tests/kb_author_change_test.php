<?php
namespace phpbbmodders\knowledgebase\controller;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';
require_once dirname(__DIR__) . '/inc/author_change.php';

use phpbbmodders\knowledgebase\inc\author_change;
use phpbbmodders\knowledgebase\inc\functions_kb;

/** Ownership transfers must not mutate article content or private history. */
class kb_author_change_test extends \PHPUnit\Framework\TestCase
{
	private \PDO $pdo;
	private $db;
	private $cache;
	private $log;
	private author_change $service;

	protected function setUp(): void
	{
		$this->pdo = new \PDO('sqlite::memory:');
		$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->pdo->exec('CREATE TABLE articles (article_id INTEGER PRIMARY KEY, author_id INTEGER, author_type INTEGER, author TEXT, article_title TEXT, status INTEGER, body TEXT, edited INTEGER)');
		$this->pdo->exec("INSERT INTO articles VALUES (1, 2, 0, 'Original', 'Guide', 2, 'Keep content', 123)");
		$this->pdo->exec('CREATE TABLE co_authors (article_id INTEGER, author_type INTEGER, author_ref_id INTEGER)');
		$this->pdo->exec('INSERT INTO co_authors VALUES (1, 0, 3), (1, 0, 4), (1, 1, 5)');
		$this->pdo->exec('CREATE TABLE drafts (article_id INTEGER, body TEXT)');
		$this->pdo->exec('CREATE TABLE revisions (article_id INTEGER, submitted_by INTEGER, body TEXT)');
		$this->pdo->exec("INSERT INTO revisions VALUES (1, 2, 'Pending revision')");
		$this->pdo->exec('CREATE TABLE results (search_key TEXT)');
		$this->pdo->exec("INSERT INTO results VALUES ('author-filter')");
		$this->db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$affected = 0;
		$this->db->method('sql_query')->willReturnCallback(function ($sql) use (&$affected) {
			$result = $this->pdo->query($sql);
			$affected = $result->rowCount();
			return $result;
		});
		$this->db->method('sql_affectedrows')->willReturnCallback(function () use (&$affected) { return $affected; });
		$this->db->method('sql_fetchrow')->willReturnCallback(fn($result) => $result->fetch(\PDO::FETCH_ASSOC));
		$this->db->method('sql_build_array')->willReturnCallback(function ($mode, $data) {
			return implode(', ', array_map(fn($key) => $key . ' = ' . $this->pdo->quote((string) $data[$key]), array_keys($data)));
		});
		$this->db->method('sql_transaction')->willReturnCallback(function ($mode) {
			return match ($mode) {
				'begin' => $this->pdo->beginTransaction(),
				'commit' => $this->pdo->commit(),
				'rollback' => $this->pdo->rollBack(),
			};
		});
		$this->cache = $this->createMock(\phpbb\cache\driver\driver_interface::class);
		$this->log = $this->createMock(\phpbb\log\log_interface::class);
		$this->log->method('add')->willReturn(1);
		$user = $this->createMock(\phpbb\user::class);
		$user->data = ['user_id' => 9];
		$user->ip = '127.0.0.1';
		$this->service = new author_change($this->db, $this->cache, $this->log, $user, 'articles', 'co_authors', 'drafts', 'results');
	}

	private function article(): array
	{
		return $this->pdo->query('SELECT * FROM articles WHERE article_id = 1')->fetch(\PDO::FETCH_ASSOC);
	}

	public function test_transfer_preserves_content_revisions_and_unrelated_coauthors(): void
	{
		$before = $this->article();
		$this->log->expects($this->once())->method('add')->with('admin', 9, '127.0.0.1', 'LOG_LIBRARY_CHANGED_AUTHOR', $this->anything(), ['Guide', 'Original (0:2)', 'New author (0:3)']);
		$this->cache->expects($this->once())->method('destroy')->with('_kb_search_results_author-filter');
		$this->assertTrue($this->service->transfer($before, 3, 0, 'New author'));
		$after = $this->article();
		$this->assertSame('New author', $after['author']);
		$this->assertSame(3, $after['author_id']);
		foreach (['body', 'edited', 'status'] as $field)
		{
			$this->assertSame($before[$field], $after[$field]);
		}
		$this->assertSame([4, 5], $this->pdo->query('SELECT author_ref_id FROM co_authors ORDER BY author_ref_id')->fetchAll(\PDO::FETCH_COLUMN));
		$this->assertSame(['article_id' => 1, 'submitted_by' => 2, 'body' => 'Pending revision'], $this->pdo->query('SELECT * FROM revisions')->fetch(\PDO::FETCH_ASSOC));
	}

	public function test_team_transfer_does_not_remove_a_user_with_the_same_numeric_id(): void
	{
		$this->assertTrue($this->service->transfer($this->article(), 3, 1, 'Team'));
		$this->assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM co_authors')->fetchColumn());
		$this->assertSame(1, $this->article()['author_type']);
	}

	public function test_same_author_does_not_log_or_write(): void
	{
		$this->log->expects($this->never())->method('add');
		$this->db->expects($this->never())->method('sql_query');
		$this->assertFalse($this->service->transfer($this->article(), 2, 0, 'Original'));
	}

	public function test_private_draft_edit_blocks_transfer(): void
	{
		$this->pdo->exec("INSERT INTO drafts VALUES (1, 'Private content')");
		$this->log->expects($this->never())->method('add');
		try
		{
			$this->service->transfer($this->article(), 3, 0, 'New');
			$this->fail('A private draft edit must not transfer');
		}
		catch (\RuntimeException $error)
		{
			$this->assertSame('KB_CHANGE_AUTHOR_CONFLICT', $error->getMessage());
		}
		$this->assertSame(2, $this->article()['author_id']);
		$this->assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM co_authors')->fetchColumn());
	}

	public function test_stale_owner_does_not_overwrite_another_transfer(): void
	{
		$before = $this->article();
		$this->pdo->exec('UPDATE articles SET author_id = 7');
		$this->expectExceptionMessage('KB_CHANGE_AUTHOR_CONFLICT');
		$this->service->transfer($before, 3, 0, 'New');
	}

	public function test_log_failure_rolls_back_the_author_and_coauthors(): void
	{
		$this->log->method('add')->willThrowException(new \RuntimeException('Log unavailable'));
		try
		{
			$this->service->transfer($this->article(), 3, 0, 'New');
			$this->fail('Log failure must be visible');
		}
		catch (\RuntimeException $error)
		{
			$this->assertSame('Log unavailable', $error->getMessage());
		}
		$this->assertSame(2, $this->article()['author_id']);
		$this->assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM co_authors')->fetchColumn());
	}

	public function test_private_draft_article_is_rejected(): void
	{
		$this->pdo->exec('UPDATE articles SET status = ' . functions_kb::STATUS_DRAFT);
		$this->expectExceptionMessage('KB_CHANGE_AUTHOR_PRIVATE_DRAFT');
		$this->service->transfer($this->article(), 3, 0, 'New');
	}
}
