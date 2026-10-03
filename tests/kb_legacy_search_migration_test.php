<?php
namespace phpbbmodders\knowledgebase\migrations;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/migrations/version_1_0_0.php';
require_once dirname(__DIR__) . '/migrations/version_1_0_3.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_2.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_13.php';
if (!defined('CONFIG_TABLE')) define('CONFIG_TABLE', 'phpbb_config');

class kb_legacy_search_migration_test extends \PHPUnit\Framework\TestCase
{
	private $db;
	private $tools;
	private $config;

	protected function setUp(): void
	{
		$this->db = new \phpbb\db\driver\sqlite3();
		$this->assertTrue($this->db->sql_connect(':memory:', '', '', ''));
		$this->tools = new \phpbb\db\tools\sqlite3($this->db);
		$this->config = new \phpbb\config\config(['kb_version' => '2.0.12']);
	}

	protected function tearDown(): void
	{
		$this->db->sql_close();
	}

	private function migration($class, $prefix = 'phpbb_')
	{
		return new $class($this->config, $this->db, $this->tools, '', 'php', $prefix);
	}

	private function columns($index)
	{
		$result = $this->db->sql_query("PRAGMA index_info('" . $index . "')");
		$columns = array_column($this->db->sql_fetchrowset($result), 'name');
		$this->db->sql_freeresult($result);
		return $columns;
	}

	public function test_shipped_legacy_schema_upgrades_without_losing_matches()
	{
		$initial = $this->migration(version_1_0_0::class)->update_schema();
		$table = 'phpbb_kb_src_wrdmtch';
		$this->tools->sql_create_table($table, $initial['add_tables'][$table]);
		$this->db->sql_query('CREATE TABLE phpbb_kb_articles (article_id INTEGER, bbcode_bitfield TEXT, article_title TEXT, article_body TEXT)');
		$this->db->sql_query("INSERT INTO phpbb_kb_articles VALUES (1, '', 'Kept title', 'Kept body')");
		$this->db->sql_query('INSERT INTO ' . $table . ' (article_id, reply_id, word_id, title_match) VALUES (1, 0, 1, 0)');
		$this->assertSame(['article_id', 'word_id', 'title_match'], $this->columns($table . '_un_mtch'));
		$this->db->sql_return_on_error(true);
		$this->assertFalse($this->db->sql_query('INSERT INTO ' . $table . ' (article_id, reply_id, word_id, title_match) VALUES (1, 2, 1, 0)'));
		$this->db->sql_return_on_error(false);

		$old = $this->migration(version_1_0_3::class);
		$this->tools->perform_schema_changes($old->update_schema());
		$old->add_key();
		$this->tools->perform_schema_changes($this->migration(version_2_0_2::class)->update_schema());
		// The old migration's unprefixed DROP leaves the narrow index behind.
		$this->assertSame(['article_id', 'word_id', 'title_match'], $this->columns($table . '_un_mtch'));
		$this->assertFalse($this->tools->sql_column_exists($table, 'reply_id'));

		$repair = $this->migration(version_2_0_13::class);
		$this->tools->perform_schema_changes($repair->update_schema());
		$this->assertSame(['article_id', 'word_id', 'title_match', 'descr_match'], $this->columns($table . '_un_mtch'));
		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_12'], version_2_0_13::depends_on());
		$this->assertSame([['config.update', ['kb_version', '2.0.13']]], $repair->update_data());
		$this->db->sql_query('INSERT INTO ' . $table . ' (article_id, word_id, title_match, descr_match) VALUES (1, 1, 0, 1)');
		$this->tools->perform_schema_changes($repair->update_schema());
		$result = $this->db->sql_query('SELECT article_id, word_id, title_match, descr_match FROM ' . $table . ' ORDER BY descr_match');
		$this->assertSame([
			['article_id' => 1, 'word_id' => 1, 'title_match' => 0, 'descr_match' => 0],
			['article_id' => 1, 'word_id' => 1, 'title_match' => 0, 'descr_match' => 1],
		], $this->db->sql_fetchrowset($result));
		$this->db->sql_freeresult($result);
		$result = $this->db->sql_query('SELECT article_title, article_body FROM phpbb_kb_articles');
		$this->assertSame(['article_title' => 'Kept title', 'article_body' => 'Kept body'], $this->db->sql_fetchrow($result));
		$this->db->sql_freeresult($result);
	}

	public function test_repair_does_not_drop_an_unprefixed_index_owned_by_another_table()
	{
		$table = 'phpbb_kb_src_wrdmtch';
		$initial = $this->migration(version_1_0_0::class)->update_schema();
		$this->tools->sql_create_table($table, $initial['add_tables'][$table]);
		$this->db->sql_query('ALTER TABLE ' . $table . ' ADD COLUMN descr_match INTEGER NOT NULL DEFAULT 0');
		$this->db->sql_query('CREATE TABLE unrelated (id INTEGER)');
		$this->db->sql_query('CREATE UNIQUE INDEX un_mtch ON unrelated (id)');
		$this->tools->perform_schema_changes($this->migration(version_2_0_13::class)->update_schema());
		$this->assertSame(['id'], $this->columns('un_mtch'));
		$this->assertSame(['article_id', 'word_id', 'title_match', 'descr_match'], $this->columns($table . '_un_mtch'));
	}
}
