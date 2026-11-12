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
if (!defined('IN_PHPBB')) define('IN_PHPBB', true);
require_once (getenv('PHPBB_ROOT_PATH') ?: dirname(__DIR__, 4)) . '/includes/utf/utf_tools.php';
require_once dirname(__DIR__) . '/textformatter/article_text.php';
require_once dirname(__DIR__) . '/inc/default_content.php';
require_once dirname(__DIR__) . '/inc/intro.php';
require_once dirname(__DIR__) . '/inc/kinerity_import.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_33.php';

/** The Knowledge Base index introduction: storage, display switch and migration. */
class kb_intro_test extends \PHPUnit\Framework\TestCase
{
	private array $assigned = [];

	/**
	 * An intro service over in-memory settings, with the text formatter
	 * replaced by one that wraps text in a marker.
	 */
	private function intro(\phpbb\config\config $config, array &$texts): intro
	{
		$config_text = $this->createMock(\phpbb\config\db_text::class);
		$config_text->method('get')->willReturnCallback(function ($key) use (&$texts) {
			return $texts[$key] ?? null;
		});
		$config_text->method('set')->willReturnCallback(function ($key, $value) use (&$texts) {
			$texts[$key] = $value;
		});

		$article_text = $this->createMock(\phpbbmodders\knowledgebase\textformatter\article_text::class);
		$article_text->method('store')->willReturnCallback(function (&$text, $markdown, &$uid, &$bitfield, &$options) {
			$text = ($markdown ? '<r md>' : '<r>') . $text . '</r>';
			$uid = $markdown ? '' : 'abc123';
		});
		$article_text->method('display')->willReturnCallback(fn($stored, $uid, $markdown) => 'HTML[' . ($markdown ? 'md' : 'bb') . ':' . $uid . ':' . $stored . ']');
		$article_text->method('for_edit')->willReturnCallback(fn($stored) => 'EDIT[' . $stored . ']');

		$template = $this->createMock(\phpbb\template\template::class);
		$template->method('assign_vars')->willReturnCallback(function ($vars) {
			$this->assigned = array_merge($this->assigned, $vars);
		});

		return new intro($config, $config_text, $article_text, $template);
	}

	public function test_the_default_is_kinerity_text_stored_as_plain_text()
	{
		$this->assertStringStartsWith('This section contains detailed articles', default_content::lang('en', 'KB_INTRO_DEFAULT_TEXT'));
		$this->assertSame('<t>A &amp; B &quot;C&quot; &lt;D&gt;</t>', intro::stored_plain_text('A & B "C" <D>'));
	}

	public function test_nothing_is_shown_while_it_is_switched_off()
	{
		$texts = ['kb_intro_text' => '<t>Hello</t>'];
		$this->intro(new \phpbb\config\config(['kb_intro_enabled' => 0]), $texts)->assign();

		$this->assertSame(['S_KB_INTRO' => false, 'KB_INTRO' => ''], $this->assigned);
	}

	public function test_it_is_shown_when_switched_on()
	{
		$texts = ['kb_intro_text' => '<t>Hello</t>'];
		$config = new \phpbb\config\config(['kb_intro_enabled' => 1, 'kb_intro_uid' => 'u1', 'kb_intro_markdown' => 0]);
		$this->intro($config, $texts)->assign();

		$this->assertSame(['S_KB_INTRO' => true, 'KB_INTRO' => 'HTML[bb:u1:<t>Hello</t>]'], $this->assigned);
	}

	public function test_an_empty_text_shows_nothing_even_when_switched_on()
	{
		foreach (['', '<t></t>', '<t>   </t>'] as $stored)
		{
			$this->assigned = [];
			$texts = ['kb_intro_text' => $stored];
			$this->intro(new \phpbb\config\config(['kb_intro_enabled' => 1]), $texts)->assign();
			$this->assertFalse($this->assigned['S_KB_INTRO'], var_export($stored, true));
		}
	}

	public function test_saving_stores_the_parsed_text_and_the_switches()
	{
		$texts = [];
		$config = new \phpbb\config\config([]);
		$intro = $this->intro($config, $texts);

		$intro->save(true, '[b]Hi[/b]', false);
		$this->assertSame('<r>[b]Hi[/b]</r>', $texts['kb_intro_text']);
		$this->assertSame(['abc123', 0, 1], [$config['kb_intro_uid'], (int) $config['kb_intro_markdown'], (int) $config['kb_intro_enabled']]);
		$this->assertSame('EDIT[<r>[b]Hi[/b]</r>]', $intro->for_edit());

		$intro->save(false, '# Hi', true);
		$this->assertSame('<r md># Hi</r>', $texts['kb_intro_text']);
		$this->assertSame(['', 1, 0], [$config['kb_intro_uid'], (int) $config['kb_intro_markdown'], (int) $config['kb_intro_enabled']]);
		$this->assertSame('HTML[md::<r md># Hi</r>]', $intro->html());

		// Emptying the box clears the text
		$intro->save(true, '   ', false);
		$this->assertSame('', $texts['kb_intro_text']);
		$this->assertSame('', $intro->html());
	}

	public function test_the_migration_adds_it_switched_off_and_switches_it_on_after_a_kinerity_import()
	{
		$migration = new \phpbbmodders\knowledgebase\migrations\version_2_0_33(new \phpbb\config\config([]), $this->createMock(\phpbb\db\driver\driver_interface::class), $this->createMock(\phpbb\db\tools\tools_interface::class), '', 'php', 'phpbb_', []);
		$steps = $migration->update_data();

		$this->assertContains(['config.add', ['kb_intro_enabled', 0]], $steps);
		$this->assertContains(['config_text.add', ['kb_intro_text', intro::stored_plain_text(default_content::lang('en', 'KB_INTRO_DEFAULT_TEXT'))]], $steps);
		$this->assertContains(['if', [['custom', [[$migration, 'came_from_kinerity']]], ['config.update', ['kb_intro_enabled', 1]]]], $steps);
		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_32'], \phpbbmodders\knowledgebase\migrations\version_2_0_33::depends_on());

		foreach ([true, false] as $has_backup)
		{
			$tools = $this->createMock(\phpbb\db\tools\tools_interface::class);
			$tools->method('sql_table_exists')->willReturn($has_backup);
			$check = new \phpbbmodders\knowledgebase\migrations\version_2_0_33(new \phpbb\config\config([]), $this->createMock(\phpbb\db\driver\driver_interface::class), $tools, '', 'php', 'phpbb_', []);
			$this->assertSame($has_backup, $check->came_from_kinerity());
		}
	}
}
