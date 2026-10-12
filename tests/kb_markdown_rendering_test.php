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
require_once dirname(__DIR__) . '/textformatter/factory.php';
require_once dirname(__DIR__) . '/textformatter/article_text.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_22.php';

use phpbbmodders\knowledgebase\textformatter\factory;

class kb_markdown_rendering_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider fragments */
	public function test_a_link_to_a_heading_of_the_same_page_gets_the_headings_prefix($url, $expected)
	{
		$this->assertSame($expected, factory::prefix_fragment($url));
	}

	public function fragments(): array
	{
		return [
			'slug'                    => ['#basic-usage', '#kb-basic-usage'],
			'single word'             => ['#search', '#kb-search'],
			'with digits'             => ['#step-2', '#kb-step-2'],
			'already prefixed'        => ['#kb-basic-usage', '#kb-basic-usage'],
			'another page'            => ['https://example.org/#basic-usage', 'https://example.org/#basic-usage'],
			'relative page'           => ['/knowledgebase/article?k=2#basic-usage', '/knowledgebase/article?k=2#basic-usage'],
			'upper case is not a slug' => ['#Top', '#Top'],
			'a lone hash'             => ['#', '#'],
			'not a fragment'          => ['basic-usage', 'basic-usage'],
			'markup in a fragment'    => ['#a"onclick="x', '#a"onclick="x'],
		];
	}

	public function test_the_prefix_matches_the_one_the_headings_get()
	{
		$this->assertSame('kb-', factory::HEADING_ID_PREFIX);
	}

	private function migration($article_text): version_2_0_22
	{
		$db = new \phpbb\db\driver\sqlite3();
		$this->assertTrue($db->sql_connect(':memory:', '', '', ''));
		foreach (['phpbb_kb_articles', 'phpbb_kb_article_revisions', 'phpbb_kb_article_draft_edits'] as $table)
		{
			$db->sql_query('CREATE TABLE ' . $table . ' (article_id INTEGER PRIMARY KEY, article_body TEXT, article_markdown INTEGER)');
		}
		$this->db = $db;

		return new class(new \phpbb\config\config([]), $db, $this->createMock(\phpbb\db\tools\tools_interface::class), '', 'php', 'phpbb_', $article_text) extends version_2_0_22 {
			public $helper;

			public function __construct($config, $db, $tools, $root, $ext, $prefix, $helper)
			{
				parent::__construct($config, $db, $tools, $root, $ext, $prefix, []);
				$this->helper = $helper;
			}

			protected function get_article_text(): \phpbbmodders\knowledgebase\textformatter\article_text
			{
				if ($this->helper === null)
				{
					throw new \LogicException('The formatter must not be built when there is nothing to re-parse');
				}

				return $this->helper;
			}
		};
	}

	private $db;

	/** A stand-in for the real helper: stored text "<r>x</r>" becomes "<r>x!</r>" unless it already ends in "!". */
	private function helper()
	{
		$helper = $this->getMockBuilder(\phpbbmodders\knowledgebase\textformatter\article_text::class)->disableOriginalConstructor()->getMock();
		$helper->method('for_edit')->willReturnCallback(fn($stored) => $stored);
		$helper->method('store')->willReturnCallback(function (&$text) {
			$text = preg_replace('#!?</r>$#', '!</r>', $text);
		});

		return $helper;
	}

	private function body(string $table, int $id): string
	{
		$result = $this->db->sql_query('SELECT article_body FROM phpbb_' . $table . ' WHERE article_id = ' . $id);
		$body = $this->db->sql_fetchfield('article_body');
		$this->db->sql_freeresult($result);

		return (string) $body;
	}

	public function test_every_markdown_article_revision_and_draft_is_parsed_again_and_nothing_else_is()
	{
		$migration = $this->migration($this->helper());
		foreach (['kb_articles', 'kb_article_revisions', 'kb_article_draft_edits'] as $table)
		{
			$this->db->sql_query("INSERT INTO phpbb_$table VALUES (1, '<r>old</r>', 1), (2, '<r>bbcode</r>', 0), (3, '<r>done!</r>', 1)");
		}

		$migration->reparse_markdown_articles();

		foreach (['kb_articles', 'kb_article_revisions', 'kb_article_draft_edits'] as $table)
		{
			$this->assertSame('<r>old!</r>', $this->body($table, 1), $table);
			$this->assertSame('<r>bbcode</r>', $this->body($table, 2), $table);
			$this->assertSame('<r>done!</r>', $this->body($table, 3), $table);
		}
	}

	public function test_running_it_twice_changes_nothing_more()
	{
		$migration = $this->migration($this->helper());
		$this->db->sql_query("INSERT INTO phpbb_kb_articles VALUES (1, '<r>old</r>', 1)");

		$migration->reparse_markdown_articles();
		$migration->reparse_markdown_articles();

		$this->assertSame('<r>old!</r>', $this->body('kb_articles', 1));
	}

	public function test_more_articles_than_one_batch_are_all_handled()
	{
		$migration = $this->migration($this->helper());
		$this->db->sql_query('BEGIN');
		for ($id = 1; $id <= 120; $id++)
		{
			$this->db->sql_query("INSERT INTO phpbb_kb_articles VALUES ($id, '<r>old $id</r>', 1)");
		}
		$this->db->sql_query('COMMIT');

		$migration->reparse_markdown_articles();

		$this->assertSame('<r>old 120!</r>', $this->body('kb_articles', 120));
		$this->assertSame('<r>old 51!</r>', $this->body('kb_articles', 51));
		$this->assertSame('<r>old 1!</r>', $this->body('kb_articles', 1));
	}

	public function test_a_board_with_no_markdown_articles_never_builds_the_formatter()
	{
		$migration = $this->migration(null);
		$this->db->sql_query("INSERT INTO phpbb_kb_articles VALUES (1, '<r>bbcode</r>', 0)");

		$migration->reparse_markdown_articles();

		$this->assertSame('<r>bbcode</r>', $this->body('kb_articles', 1));
	}

	public function test_it_follows_the_classic_look_migration_and_records_its_version()
	{
		$migration = $this->migration(null);

		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_21'], version_2_0_22::depends_on());
		$this->assertSame([['custom', [[$migration, 'reparse_markdown_articles']]], ['config.update', ['kb_version', '2.0.22']]], $migration->update_data());
	}
}
