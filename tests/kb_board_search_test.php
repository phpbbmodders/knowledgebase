<?php

/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';
require_once dirname(__DIR__) . '/inc/board_search.php';
require_once dirname(__DIR__) . '/textformatter/article_text.php';
require_once dirname(__DIR__) . '/event/board_search_listener.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_27.php';

use phpbbmodders\knowledgebase\event\board_search_listener;
use phpbbmodders\knowledgebase\inc\board_search;

/**
 * Knowledge Base matches in the board's search: when the block and the option show,
 * what carries the choice through the result pages, and the 2.0.27 migration.
 */
class kb_board_search_test extends \PHPUnit\Framework\TestCase
{
	private function migration(array $config): migrations\version_2_0_27
	{
		return new migrations\version_2_0_27(new \phpbb\config\config($config), $this->createMock(\phpbb\db\driver\driver_interface::class), $this->createMock(\phpbb\db\tools\tools_interface::class), '', 'php', 'phpbb_', []);
	}

	public function test_the_migration_adds_the_two_settings()
	{
		$this->assertSame([
			['config.add', ['kb_search_board', 1]],
			['config.add', ['kb_search_board_max', 5]],
			['config.update', ['kb_version', '2.0.27']],
		], $this->migration([])->update_data());
		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_26'], migrations\version_2_0_27::depends_on());
		$this->assertFalse($this->migration([])->effectively_installed());
		$this->assertTrue($this->migration(['kb_search_board' => 1])->effectively_installed());

		$reverted = array_map(fn($step) => $step[0] . ':' . $step[1][0], $this->migration([])->revert_data());
		$this->assertSame(['config.remove:kb_search_board', 'config.remove:kb_search_board_max', 'config.update:kb_version'], $reverted);
	}

	/**
	 * Build the listener with what a board gives it.
	 *
	 * @param array      $request   Request values by name
	 * @param array      $config    Config values
	 * @param bool       $allowed   Whether the member may read the Knowledge Base
	 * @param array|bool $found     What the search finds (false for words it cannot search on)
	 * @param bool       $index     Whether the search has an index
	 * @param array      $assigned  Filled with the template variables and blocks assigned
	 * @param board_search|null $search A search to use instead of one that returns $found
	 * @return board_search_listener
	 */
	private function listener(array $request, array $config, bool $allowed, $found, bool $index, ?array &$assigned = [], ?board_search $search = null): board_search_listener
	{
		$assigned = ['vars' => [], 'blocks' => []];
		$auth = $this->createMock(\phpbb\auth\auth::class);
		$auth->method('acl_get')->willReturnCallback(fn($option) => $allowed && $option === 'u_kb_view');
		$language = $this->createMock(\phpbb\language\language::class);
		$language->method('lang')->willReturnCallback(fn($key, ...$args) => $key . ($args ? ' ' . implode(',', $args) : ''));
		$requests = $this->createMock(\phpbb\request\request_interface::class);
		$requests->method('variable')->willReturnCallback(fn($name, $default) => $request[$name] ?? $default);
		$helper = $this->createMock(\phpbb\controller\helper::class);
		$helper->method('route')->willReturnCallback(fn($name, $params = []) => '/' . $name . '?' . http_build_query($params));
		$template = $this->createMock(\phpbb\template\template::class);
		$template->method('assign_vars')->willReturnCallback(function ($vars) use (&$assigned) {
			$assigned['vars'] = array_merge($assigned['vars'], $vars);
		});
		$template->method('assign_var')->willReturnCallback(function ($name, $value) use (&$assigned) {
			$assigned['vars'][$name] = $value;
		});
		$template->method('assign_block_vars')->willReturnCallback(function ($block, $vars) use (&$assigned) {
			$assigned['blocks'][$block][] = $vars;
		});
		if ($search === null)
		{
			$search = $this->createMock(board_search::class);
			$search->method('index_created')->willReturn($index);
			$search->method('find')->willReturn($found);
		}

		return new board_search_listener($auth, new \phpbb\config\config($config + ['kb_search' => 1, 'kb_search_board' => 1, 'kb_search_board_max' => 5]), $helper, $language, $requests, $template, $search);
	}

	private function event(array $data): \phpbb\event\data
	{
		return new \phpbb\event\data($data);
	}

	private function some_matches(int $total = 7): array
	{
		return ['total' => $total, 'rows' => [['TITLE' => 'An article', 'SNIPPET' => 'text', 'CATEGORY' => 'Cat', 'DATE' => 'today', 'U_VIEW' => '/a', 'U_CAT' => '/c']]];
	}

	public function test_a_keyword_search_shows_the_block_with_its_count_and_a_link_to_all_matches()
	{
		$listener = $this->listener(['sf' => 'titleonly', 'terms' => 'any'], [], true, $this->some_matches(7), true, $assigned);
		$listener->add_block($this->event(['keywords' => 'backup &amp; restore', 'start' => 0]));

		$this->assertTrue($assigned['vars']['S_KB_BOARD_BLOCK']);
		$this->assertSame('KB_BOARD_BLOCK_TITLE 7', $assigned['vars']['KB_BOARD_TOTAL']);
		$this->assertSame('KB_BOARD_VIEW_ALL 7', $assigned['vars']['KB_BOARD_VIEW_ALL']);
		$this->assertSame('/phpbbmodders_knowledgebase_search?keywords=backup+%26+restore&terms=any&sf=titleonly', $assigned['vars']['U_KB_BOARD_VIEW_ALL']);
		$this->assertCount(1, $assigned['blocks']['kb_matches']);
	}

	/** @dataProvider searches_without_a_block */
	public function test_these_searches_show_no_block($request, array $config, bool $allowed, $found, bool $index, array $event)
	{
		$listener = $this->listener($request, $config, $allowed, $found, $index, $assigned);
		$listener->add_block($this->event($event + ['keywords' => 'backup', 'start' => 0]));

		$this->assertSame([], $assigned['vars']);
		$this->assertSame([], $assigned['blocks']);
	}

	public function searches_without_a_block(): array
	{
		$found = $this->some_matches();

		return [
			'no keywords'                 => [[], [], true, $found, true, ['keywords' => '']],
			'a later page of results'     => [[], [], true, $found, true, ['start' => 25]],
			'the block switched off'      => [[], ['kb_search_board' => 0], true, $found, true, []],
			'the KB search switched off'  => [[], ['kb_search' => 0], true, $found, true, []],
			'a member who may not read'   => [[], [], false, $found, true, []],
			'the box unticked'            => [['kb_form' => 1, 'kb_also' => 0], [], true, $found, true, []],
			'a pre-made search'           => [['search_id' => 'active_topics'], [], true, $found, true, []],
			'a search by author'          => [['author' => 'someone'], [], true, $found, true, []],
			'a search by author id'       => [['author_id' => 4], [], true, $found, true, []],
			'a search inside one topic'   => [['t' => 9], [], true, $found, true, []],
			'no index yet'                => [[], [], true, $found, false, []],
			'words it cannot search on'   => [[], [], true, false, true, []],
			'no matches'                  => [[], [], true, ['total' => 0, 'rows' => []], true, []],
			'a count with nothing to list' => [[], [], true, ['total' => 3, 'rows' => []], true, []],
		];
	}

	public function test_a_form_that_leaves_the_box_ticked_and_a_search_without_the_form_both_show_the_block()
	{
		foreach ([['kb_form' => 1, 'kb_also' => 1], []] as $request)
		{
			$listener = $this->listener($request, [], true, $this->some_matches(), true, $assigned);
			$listener->add_block($this->event(['keywords' => 'backup', 'start' => 0]));

			$this->assertTrue($assigned['vars']['S_KB_BOARD_BLOCK']);
		}
	}

	public function test_the_block_is_limited_to_the_size_set_in_the_acp()
	{
		$search = $this->createMock(board_search::class);
		$search->method('index_created')->willReturn(true);
		$search->expects($this->once())->method('find')->with('backup', 'all', 'all', 12)->willReturn($this->some_matches());

		$this->listener([], ['kb_search_board_max' => 12], true, false, true, $assigned, $search)->add_block($this->event(['keywords' => 'backup', 'start' => 0]));

		$this->assertTrue($assigned['vars']['S_KB_BOARD_BLOCK']);
	}

	public function test_only_a_member_who_may_read_gets_the_option_on_the_advanced_search()
	{
		$this->listener([], [], true, false, true, $assigned)->add_option();
		$this->assertTrue($assigned['vars']['S_KB_BOARD_SEARCH']);

		$this->listener([], [], false, false, true, $assigned)->add_option();
		$this->assertSame([], $assigned['vars']);

		$this->listener([], ['kb_search_board' => 0], true, false, true, $assigned)->add_option();
		$this->assertSame([], $assigned['vars']);
	}

	public function test_an_unticked_box_is_carried_through_the_result_links_and_a_ticked_one_is_not()
	{
		$event = $this->event(['u_search' => 'search.php?keywords=x']);
		$this->listener(['kb_form' => 1, 'kb_also' => 0], [], true, false, true)->keep_option($event);
		$this->assertSame('search.php?keywords=x&amp;kb_form=1', $event['u_search']);

		$event = $this->event(['u_search' => 'search.php?keywords=x']);
		$this->listener(['kb_form' => 1, 'kb_also' => 1], [], true, false, true)->keep_option($event);
		$this->assertSame('search.php?keywords=x', $event['u_search']);

		$event = $this->event(['u_search' => 'search.php?keywords=x']);
		$this->listener(['kb_form' => 1, 'kb_also' => 0], ['kb_search_board' => 0], true, false, true)->keep_option($event);
		$this->assertSame('search.php?keywords=x', $event['u_search']);
	}

	public function test_the_listener_hooks_the_three_core_search_events()
	{
		$this->assertSame([
			'core.search_modify_forum_select_list'    => 'add_option',
			'core.search_modify_url_parameters'       => 'keep_option',
			'core.search_results_modify_search_title' => 'add_block',
		], board_search_listener::getSubscribedEvents());
	}

	public function test_the_two_template_events_and_the_acp_fields_are_in_place()
	{
		$block = file_get_contents(__DIR__ . '/../styles/all/template/event/search_results_header_after.html');
		$option = file_get_contents(__DIR__ . '/../styles/all/template/event/search_body_search_options_append.html');
		$acp = file_get_contents(__DIR__ . '/../adm/style/acp_knowledgebase_search.html');

		$this->assertStringContainsString('role="region"', $block);
		$this->assertStringContainsString('{% for kb_match in kb_matches %}', $block);
		$this->assertStringContainsString('name="kb_form" value="1"', $option);
		$this->assertStringContainsString('name="kb_also" id="kb_also" value="1" checked="checked"', $option);
		$this->assertStringContainsString('name="config[kb_search_board]"', $acp);
		$this->assertStringContainsString('name="config[kb_search_board_max]"', $acp);
	}

	/** @dataProvider rendered_articles */
	public function test_the_snippet_text_is_the_rendered_article_without_its_tags_and_is_escaped($html, string $expected)
	{
		$article_text = $this->createMock(\phpbbmodders\knowledgebase\textformatter\article_text::class);
		$article_text->method('display')->willReturn($html);
		$search = (new \ReflectionClass(board_search::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(board_search::class, 'article_text'))->setValue($search, $article_text);

		$plain = (new \ReflectionMethod(board_search::class, 'plain_text'));

		$this->assertSame($expected, $plain->invoke($search, ['article_body' => '<r>stored</r>', 'bbcode_uid' => '', 'article_markdown' => 0]));
	}

	public function rendered_articles(): array
	{
		return [
			'headings, paragraphs and a list'  => ['<h2>Before you begin</h2><p>Keep a <strong>backup</strong>.</p><ul><li>One</li><li>Two</li></ul>', 'Before you begin Keep a backup. One Two'],
			'line breaks and runs of spaces'   => ["Line one<br />\n\n   Line two<br>Line three", 'Line one Line two Line three'],
			'entities come out escaped again'  => ['<p>Fish &amp; chips &lt;b&gt; "quoted"</p>', 'Fish &amp; chips &lt;b&gt; &quot;quoted&quot;'],
			'a script tag is only text'        => ['<p>Hi</p><script>alert(1)</script>', 'Hi alert(1)'],
			'a code box and a quote'           => ['<div class="codebox"><code>ls -l</code></div><blockquote>Quoted text</blockquote>', 'ls -l Quoted text'],
		];
	}
}
