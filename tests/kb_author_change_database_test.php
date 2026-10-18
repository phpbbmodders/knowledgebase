<?php
namespace phpbbmodders\knowledgebase\controller;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';
require_once dirname(__DIR__) . '/inc/author_change.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_30.php';

/** Opt-in author transfer and permission-upgrade checks on disposable databases. */
class kb_author_change_database_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider engines */
	public function test_transfer_and_permission_upgrade(string $engine): void
	{
		$port = getenv('KB_TEST_' . strtoupper($engine) . '_PORT');
		if (!$port)
		{
			$this->markTestSkipped('Set the disposable kb_fixture database port');
		}
		$db = $engine === 'mysql' ? new \phpbb\db\driver\mysqli() : new \phpbb\db\driver\postgres();
		$db->sql_connect('127.0.0.1', $engine === 'mysql' ? 'root' : 'postgres', '', 'kb_fixture', (int) $port);
		$prefix = 'kb_author_test_';
		$tables = ['articles', 'coauthors', 'drafts', 'results', 'kb_options', 'kb_users', 'kb_groups'];
		try
		{
			foreach ($tables as $name) $db->sql_query('DROP TABLE IF EXISTS ' . $prefix . $name);
			$db->sql_query('CREATE TABLE ' . $prefix . 'articles (article_id INTEGER PRIMARY KEY, author_id INTEGER, author_type INTEGER, author VARCHAR(255), article_title VARCHAR(255), status INTEGER)');
			$db->sql_query("INSERT INTO " . $prefix . "articles VALUES (1, 2, 0, 'Original', 'Guide', 2)");
			$db->sql_query('CREATE TABLE ' . $prefix . 'coauthors (article_id INTEGER, author_type INTEGER, author_ref_id INTEGER)');
			$db->sql_query('INSERT INTO ' . $prefix . 'coauthors VALUES (1, 0, 3), (1, 0, 4)');
			$db->sql_query('CREATE TABLE ' . $prefix . 'drafts (article_id INTEGER)');
			$db->sql_query('CREATE TABLE ' . $prefix . 'results (search_key VARCHAR(32))');
			$db->sql_query("INSERT INTO " . $prefix . "results VALUES ('cached-author')");
			$cache = $this->createMock(\phpbb\cache\driver\driver_interface::class);
			$cache->expects($this->once())->method('destroy')->with('_kb_search_results_cached-author');
			$log = $this->createMock(\phpbb\log\log_interface::class);
			$log->expects($this->once())->method('add')->willReturn(1);
			$user = $this->createMock(\phpbb\user::class);
			$user->data = ['user_id' => 9];
			$user->ip = '127.0.0.1';
			$service = new \phpbbmodders\knowledgebase\inc\author_change($db, $cache, $log, $user, $prefix . 'articles', $prefix . 'coauthors', $prefix . 'drafts', $prefix . 'results');
			$result = $db->sql_query('SELECT * FROM ' . $prefix . 'articles');
			$article = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			$this->assertTrue($service->transfer($article, 3, 0, "O'Brien"));
			$result = $db->sql_query('SELECT * FROM ' . $prefix . 'articles');
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			$this->assertSame(3, (int) $row['author_id']);
			$this->assertSame("O'Brien", $row['author']);
			$result = $db->sql_query('SELECT author_ref_id FROM ' . $prefix . 'coauthors');
			$this->assertSame(4, (int) $db->sql_fetchfield('author_ref_id'));
			$db->sql_freeresult($result);

			// Run the permission migration on an existing options table, twice.
			$identity = $engine === 'mysql' ? 'INTEGER AUTO_INCREMENT' : 'SERIAL';
			$db->sql_query('CREATE TABLE ' . $prefix . 'kb_options (auth_option_id ' . $identity . ' PRIMARY KEY, auth_option VARCHAR(50) UNIQUE, is_global INTEGER, is_local INTEGER)');
			foreach (['kb_users', 'kb_groups'] as $table)
			{
				$db->sql_query('CREATE TABLE ' . $prefix . $table . ' (auth_option_id INTEGER)');
			}
			$db->sql_query("INSERT INTO " . $prefix . "kb_options (auth_option, is_global, is_local) VALUES ('kb_m_edit', 0, 1)");
			$tools = $this->createMock(\phpbb\db\tools\tools_interface::class);
			$migration = new \phpbbmodders\knowledgebase\migrations\version_2_0_30(new \phpbb\config\config([]), $db, $tools, '', 'php', $prefix, []);
			$migration->add_permission();
			$migration->add_permission();
			$result = $db->sql_query("SELECT auth_option_id FROM " . $prefix . "kb_options WHERE auth_option='kb_m_change_author'");
			$id = (int) $db->sql_fetchfield('auth_option_id');
			$db->sql_freeresult($result);
			$this->assertGreaterThan(0, $id);
			foreach (['kb_users', 'kb_groups'] as $table) $db->sql_query('INSERT INTO ' . $prefix . $table . ' VALUES (' . $id . ')');
			$migration->remove_permission();
			$migration->remove_permission();
			$result = $db->sql_query('SELECT auth_option FROM ' . $prefix . 'kb_options');
			$this->assertSame('kb_m_edit', $db->sql_fetchfield('auth_option'));
			$db->sql_freeresult($result);
		}
		finally
		{
			foreach ($tables as $name) $db->sql_query('DROP TABLE IF EXISTS ' . $prefix . $name);
			$db->sql_close();
		}
	}

	public function engines(): array
	{
		return [['mysql'], ['postgres']];
	}
}
