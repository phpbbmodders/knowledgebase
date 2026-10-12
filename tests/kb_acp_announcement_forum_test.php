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
require_once dirname(__DIR__) . '/search/backend/kb_search_backend_interface.php';
require_once dirname(__DIR__) . '/search/kb_search_backend_factory.php';

/** The ACP announcement setting: one forum choice, where "None" is the off switch. */
class kb_acp_announcement_forum_test extends \PHPUnit\Framework\TestCase
{
	private const ROOT = __DIR__ . '/..';

	private function saved(array $post): array
	{
		$saved = [];
		$request = $this->createMock(\phpbb\request\request_interface::class);
		$request->method('variable')->willReturnCallback(fn($name, $default) => $post[$name] ?? $default);
		$config = $this->getMockBuilder(\phpbb\config\config::class)->setConstructorArgs([[]])->onlyMethods(['set'])->getMock();
		$config->method('set')->willReturnCallback(function ($key, $value) use (&$saved) {
			$saved[$key] = $value;
			return true;
		});

		$controller = (new \ReflectionClass(acp_controller::class))->newInstanceWithoutConstructor();
		foreach (['request' => $request, 'config' => $config] as $name => $value)
		{
			$property = new \ReflectionProperty(acp_controller::class, $name);
			$property->setAccessible(true);
			$property->setValue($controller, $value);
		}
		$method = new \ReflectionMethod(acp_controller::class, 'save_announcement_forum');
		$method->setAccessible(true);
		$method->invoke($controller);

		return $saved;
	}

	public function test_choosing_a_forum_saves_it_and_switches_announcements_on()
	{
		$this->assertSame(['kb_forum_id' => 10, 'kb_anounce' => 1], $this->saved(['forum_id' => '10']));
	}

	/** @dataProvider none */
	public function test_none_switches_announcements_off($submitted)
	{
		$this->assertSame(['kb_forum_id' => 0, 'kb_anounce' => 0], $this->saved($submitted));
	}

	public function none(): array
	{
		return [
			'none chosen'    => [['forum_id' => 0]],
			'nothing sent'   => [[]],
			'negative id'    => [['forum_id' => -4]],
			'not a number'   => [['forum_id' => 'abc']],
		];
	}

	public function test_the_page_has_no_checkbox_and_one_labelled_forum_choice_that_shows_the_prefix_options()
	{
		$html = file_get_contents(self::ROOT . '/adm/style/acp_knowledgebase_config.html');

		$this->assertStringNotContainsString('name="anounce"', $html);
		$this->assertStringNotContainsString('ADVANCED_FORM_ON', $html);
		$this->assertStringContainsString('<label for="forum_id">{{ lang(\'ANOUNCE\') }}', $html);
		$this->assertStringContainsString('<select name="forum_id" id="forum_id" aria-controls="form_advanced"', $html);
		$this->assertStringContainsString("this.value === '0' ? 'none' : ''", $html);
		$this->assertSame(1, substr_count($html, 'name="forum_id"'), 'the forum is chosen in one place only');
		// The changelog choice is labelled the same way (it had no for/id before).
		$this->assertStringContainsString('<label for="changelog_forum_id">', $html);
		$this->assertStringContainsString('<select name="changelog_forum_id" id="changelog_forum_id">', $html);
		// The prefix options sit in the block the choice reveals.
		$this->assertLessThan(strpos($html, 'id="forum_prefix"'), strpos($html, 'id="form_advanced"'));
	}

	public function test_the_wording_is_the_old_checkbox_text_adjusted_for_a_single_choice()
	{
		if (!defined('IN_PHPBB'))
		{
			define('IN_PHPBB', true);
		}
		$lang = [];
		require self::ROOT . '/language/en/acp_knowledgebase.php';

		$this->assertSame('Announce new articles in a forum', $lang['ANOUNCE']);
		$this->assertStringNotContainsString('If enabled', $lang['ANOUNCE_EXPLAIN']);
		$this->assertStringContainsString('None (disabled)', $lang['ANOUNCE_EXPLAIN']);
		$this->assertArrayNotHasKey('KB_FORUM_EXPLAIN', $lang);
	}
}
