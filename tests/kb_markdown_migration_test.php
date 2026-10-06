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
require_once dirname(__DIR__) . '/migrations/version_2_0_16.php';

class kb_markdown_migration_test extends \PHPUnit\Framework\TestCase
{
	private const TABLES = ['kb_articles', 'kb_article_revisions', 'kb_article_draft_edits'];

	private $db;
	private $tools;

	protected function setUp(): void
	{
		$this->db = new \phpbb\db\driver\sqlite3();
		$this->assertTrue($this->db->sql_connect(':memory:', '', '', ''));
		$this->tools = new \phpbb\db\tools\sqlite3($this->db);
		foreach (self::TABLES as $table)
		{
			$this->db->sql_query('CREATE TABLE phpbb_' . $table . ' (article_id INTEGER, article_body TEXT)');
			$this->db->sql_query('INSERT INTO phpbb_' . $table . " VALUES (1, 'kept text')");
		}
	}

	protected function tearDown(): void
	{
		$this->db->sql_close();
	}

	private function migration(): version_2_0_16
	{
		return new version_2_0_16(new \phpbb\config\config(['kb_version' => '2.0.15']), $this->db, $this->tools, '', 'php', 'phpbb_');
	}

	public function test_it_adds_the_markdown_flag_to_the_article_and_both_copies_of_its_text()
	{
		$this->assertFalse($this->migration()->effectively_installed());

		$this->tools->perform_schema_changes($this->migration()->update_schema());

		foreach (self::TABLES as $table)
		{
			$this->assertTrue($this->tools->sql_column_exists('phpbb_' . $table, 'article_markdown'), $table);
			// Existing rows are BBCode articles: flag off, text untouched.
			$result = $this->db->sql_query('SELECT article_markdown, article_body FROM phpbb_' . $table);
			$this->assertSame(['article_markdown' => 0, 'article_body' => 'kept text'], $this->db->sql_fetchrow($result), $table);
			$this->db->sql_freeresult($result);
		}
		$this->assertTrue($this->migration()->effectively_installed());
	}

	public function test_it_can_be_reverted_without_touching_the_text()
	{
		$this->tools->perform_schema_changes($this->migration()->update_schema());
		$this->tools->perform_schema_changes($this->migration()->revert_schema());

		foreach (self::TABLES as $table)
		{
			$this->assertFalse($this->tools->sql_column_exists('phpbb_' . $table, 'article_markdown'), $table);
			$result = $this->db->sql_query('SELECT article_body FROM phpbb_' . $table);
			$this->assertSame('kept text', $this->db->sql_fetchfield('article_body'), $table);
			$this->db->sql_freeresult($result);
		}
	}

	public function test_it_records_the_new_version_and_depends_on_the_previous_one()
	{
		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_15'], version_2_0_16::depends_on());
		$this->assertSame([['config.update', ['kb_version', '2.0.16']]], $this->migration()->update_data());
	}
}
