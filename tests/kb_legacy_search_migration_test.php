<?php
namespace phpbbmodders\knowledgebase\migrations;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/migrations/version_1_0_0.php';
require_once dirname(__DIR__) . '/migrations/version_1_0_3.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_2.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_13.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_15.php';
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
		$this->config = new \phpbb\config\config(['kb_version' => '2.0.14']);
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

	public function test_legacy_module_cleanup_uses_sql_string_literals()
	{
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$queries = [];
		$db->expects($this->exactly(3))->method('sql_query')->willReturnCallback(function ($sql) use (&$queries) {
			$queries[] = $sql;
			return true;
		});
		$migration = new version_1_0_0($this->config, $db, $this->tools, '', 'php', 'fixture_');
		$migration->remove_knowlege_base_ext();
		$this->assertStringContainsString('DELETE FROM fixture_modules', $queries[2]);
		foreach (['KNOWLEGE_BASE', 'ACP_KNOWLEGE_BASE_CONFIGURE', 'ACP_LIBRARY_MANAGE', 'ACP_LIBRARY_ARTICLES', 'ACP_LIBRARY_PERMISSIONS', 'ACP_LIBRARY_SEARCH', 'ACP_LIBRARY_LOGS'] as $name)
		{
			$this->assertStringContainsString("'" . $name . "'", $queries[2]);
			$this->assertStringNotContainsString('"' . $name . '"', $queries[2]);
		}
	}

	public function test_legacy_cleanup_preserves_unrelated_data_and_is_repeatable()
	{
		$this->db->sql_query('CREATE TABLE phpbb_ext (ext_name TEXT)');
		$this->db->sql_query('CREATE TABLE phpbb_config (config_name TEXT)');
		$this->db->sql_query('CREATE TABLE phpbb_modules (module_langname TEXT)');
		$this->db->sql_query("INSERT INTO phpbb_ext VALUES ('Sheer/knowlegebase'), ('other/extension')");
		$this->db->sql_query("INSERT INTO phpbb_config VALUES ('knowlege_base_version'), ('other_setting')");
		$this->db->sql_query("INSERT INTO phpbb_modules VALUES ('KNOWLEGE_BASE'), ('ACP_LIBRARY_LOGS'), ('OTHER_MODULE')");
		$migration = $this->migration(version_1_0_0::class);
		$migration->remove_knowlege_base_ext();
		$migration->remove_knowlege_base_ext();
		foreach (['ext' => ['ext_name', 'other/extension'], 'config' => ['config_name', 'other_setting'], 'modules' => ['module_langname', 'OTHER_MODULE']] as $table => [$column, $expected])
		{
			$result = $this->db->sql_query('SELECT ' . $column . ' FROM phpbb_' . $table);
			$this->assertSame([[$column => $expected]], $this->db->sql_fetchrowset($result));
			$this->db->sql_freeresult($result);
		}
	}

	public function test_version_1_0_3_declares_every_column_drop_under_one_key()
	{
		$drops = $this->migration(version_1_0_3::class)->update_schema()['drop_columns'];
		$this->assertSame(['reply_id'], $drops['phpbb_kb_src_wrdmtch']);
		$this->assertSame(['bbcode_bitfield'], $drops['phpbb_kb_articles']);
	}

	public function test_version_1_0_3_alone_removes_reply_id_and_keeps_matches()
	{
		$table = 'phpbb_kb_src_wrdmtch';
		$initial = $this->migration(version_1_0_0::class)->update_schema();
		$this->tools->sql_create_table($table, $initial['add_tables'][$table]);
		$this->db->sql_query('CREATE TABLE phpbb_kb_articles (article_id INTEGER, bbcode_bitfield TEXT, article_title TEXT)');
		$this->db->sql_query('INSERT INTO ' . $table . ' (article_id, reply_id, word_id, title_match) VALUES (1, 5, 1, 0)');

		$this->tools->perform_schema_changes($this->migration(version_1_0_3::class)->update_schema());

		$this->assertFalse($this->tools->sql_column_exists($table, 'reply_id'));
		$this->assertFalse($this->tools->sql_column_exists('phpbb_kb_articles', 'bbcode_bitfield'));
		$result = $this->db->sql_query('SELECT article_id, word_id, title_match FROM ' . $table);
		$this->assertSame([['article_id' => 1, 'word_id' => 1, 'title_match' => 0]], $this->db->sql_fetchrowset($result));
		$this->db->sql_freeresult($result);
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

		$repair = $this->migration(version_2_0_15::class);
		$this->tools->perform_schema_changes($repair->update_schema());
		$this->assertSame(['article_id', 'word_id', 'title_match', 'descr_match'], $this->columns($table . '_un_mtch'));
		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_14'], version_2_0_15::depends_on());
		$this->assertSame([['config.update', ['kb_version', '2.0.15']]], $repair->update_data());
		$this->assertArrayHasKey('phpbb_kb_article_draft_edits', $this->migration(version_2_0_13::class)->update_schema()['add_tables']);
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
		$this->tools->perform_schema_changes($this->migration(version_2_0_15::class)->update_schema());
		$this->assertSame(['id'], $this->columns('un_mtch'));
		$this->assertSame(['article_id', 'word_id', 'title_match', 'descr_match'], $this->columns($table . '_un_mtch'));
	}
}
