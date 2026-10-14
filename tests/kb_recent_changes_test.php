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
require_once dirname(__DIR__) . '/inc/feed.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_29.php';
require_once dirname(__DIR__) . '/controller/recent_changes.php';

use phpbbmodders\knowledgebase\controller\recent_changes;

/**
 * The Recent changes page and its feeds: the migration, the controller's answers
 * (permission, format offered, the entries) and the pieces that wire it in.
 */
class kb_recent_changes_test extends \PHPUnit\Framework\TestCase
{
	private const ROOT = __DIR__ . '/..';

	private function migration(array $config): migrations\version_2_0_29
	{
		return new migrations\version_2_0_29(new \phpbb\config\config($config), $this->createMock(\phpbb\db\driver\driver_interface::class), $this->createMock(\phpbb\db\tools\tools_interface::class), '', 'php', 'phpbb_', []);
	}

	public function test_the_migration_adds_the_feed_setting_as_both_formats()
	{
		$this->assertSame([
			['config.add', ['kb_feed_format', 'both']],
			['config.update', ['kb_version', '2.0.29']],
		], $this->migration([])->update_data());
		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_28'], migrations\version_2_0_29::depends_on());
		$this->assertFalse($this->migration([])->effectively_installed());
		$this->assertTrue($this->migration(['kb_feed_format' => 'rss'])->effectively_installed());
		$this->assertSame('config.remove', $this->migration([])->revert_data()[0][0]);
	}

	/**
	 * @param array $rows        Rows the article query returns
	 * @param bool  $can_view    Whether the visitor may read the Knowledge Base
	 * @param array $config      Board config
	 * @param array $queries     Receives the SQL that was run
	 */
	private function controller(array $rows, bool $can_view, array $config = [], array &$queries = []): recent_changes
	{
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('sql_query_limit')->willReturnCallback(function ($sql) use (&$queries) {
			$queries[] = $sql;
			return true;
		});
		$db->method('sql_fetchrowset')->willReturn($rows);

		$auth = $this->createMock(\phpbb\auth\auth::class);
		$auth->method('acl_get')->willReturnCallback(fn($option) => $can_view && $option === 'u_kb_view');

		$helper = $this->createMock(\phpbb\controller\helper::class);
		$helper->method('route')->willReturnCallback(function ($name, $params = []) {
			return 'https://example.org/' . $name . '?' . http_build_query($params);
		});

		$language = $this->createMock(\phpbb\language\language::class);
		$language->method('lang')->willReturnCallback(fn(...$args) => implode('|', $args));

		return new recent_changes(
			$db, new \phpbb\config\config($config + ['sitename' => 'My Board']), $helper, $language, $auth,
			$this->createMock(\phpbb\request\request_interface::class), $this->createMock(\phpbb\template\template::class),
			$this->createMock(\phpbb\user::class), $this->createMock(\phpbb\pagination::class),
			$this->createMock(\phpbbmodders\knowledgebase\inc\functions_kb::class), 'phpbb_kb_articles', 'phpbb_kb_categories'
		);
	}

	private function row(int $id, string $title, int $written, int $edited): array
	{
		return [
			'article_id' => $id, 'article_title' => $title, 'article_description' => 'About &amp; around', 'article_date' => $written,
			'edit_date' => $edited, 'changed_time' => max($written, $edited), 'author' => 'admin', 'category_name' => 'Basics',
		];
	}

	public function test_a_guest_who_may_not_read_gets_403_and_no_query_is_run()
	{
		$queries = [];
		$response = $this->controller([], false, [], $queries)->feed('rss');

		$this->assertSame(403, $response->getStatusCode());
		$this->assertSame([], $queries);
	}

	public function test_a_format_the_setting_does_not_offer_is_404()
	{
		$this->assertSame(404, $this->controller([], true, ['kb_feed_format' => 'atom'])->feed('rss')->getStatusCode());
		$this->assertSame(404, $this->controller([], true, ['kb_feed_format' => 'rss'])->feed('atom')->getStatusCode());
		$this->assertSame(200, $this->controller([], true, ['kb_feed_format' => 'atom'])->feed('atom')->getStatusCode());
		$this->assertSame(200, $this->controller([], true)->feed('rss')->getStatusCode());
	}

	public function test_the_feed_lists_the_rows_it_is_given_with_the_right_headers_and_marks_new_and_updated()
	{
		$rows = [$this->row(7, 'Edited &amp; Better', 100, 300), $this->row(5, 'Never edited', 200, 0)];
		$response = $this->controller($rows, true)->feed('atom');

		$this->assertSame('application/atom+xml; charset=UTF-8', $response->headers->get('Content-Type'));
		$this->assertStringContainsString('max-age=300', $response->headers->get('Cache-Control'));

		$dom = new \DOMDocument();
		$this->assertTrue($dom->loadXML($response->getContent()));
		$ns = 'http://www.w3.org/2005/Atom';
		$entries = $dom->getElementsByTagNameNS($ns, 'entry');
		$this->assertSame(2, $entries->length);
		$this->assertSame('Edited & Better', $entries->item(0)->getElementsByTagNameNS($ns, 'title')->item(0)->textContent);
		$this->assertStringStartsWith('KB_FEED_UPDATED_IN|Basics', $entries->item(0)->getElementsByTagNameNS($ns, 'summary')->item(0)->textContent);
		$this->assertStringStartsWith('KB_FEED_ADDED_TO|Basics', $entries->item(1)->getElementsByTagNameNS($ns, 'summary')->item(0)->textContent);
		$this->assertStringContainsString('About & around', $entries->item(0)->getElementsByTagNameNS($ns, 'summary')->item(0)->textContent);
		$this->assertSame('https://example.org/phpbbmodders_knowledgebase_article?k=7', $entries->item(0)->getElementsByTagNameNS($ns, 'id')->item(0)->textContent);
	}

	public function test_the_query_leaves_out_everything_that_is_not_live_and_is_limited_to_the_feed_size()
	{
		$queries = [];
		$this->controller([], true, [], $queries)->feed('rss');

		$this->assertCount(1, $queries);
		$this->assertStringContainsString('a.approved = 1', $queries[0]);
		$this->assertStringContainsString('a.status = ' . inc\functions_kb::STATUS_ACTIVE, $queries[0]);
		$this->assertStringContainsString('a.is_redirect = 0', $queries[0]);
		$this->assertMatchesRegularExpression('/ORDER BY changed_time DESC, a\.article_id DESC/', $queries[0]);
	}

	public function test_the_pieces_that_wire_the_page_in_are_all_there()
	{
		$routes = file_get_contents(self::ROOT . '/config/routing.yml');
		$this->assertStringContainsString('path: /knowledgebase/recent_changes', $routes);
		$this->assertStringContainsString('path: /knowledgebase/recent_changes.{_format}', $routes);
		$this->assertStringContainsString('_format: rss|atom', $routes);
		$this->assertStringContainsString('controller.recent_changes', file_get_contents(self::ROOT . '/config/services.yml'));

		$tabs = file_get_contents(self::ROOT . '/styles/all/template/kb_nav_tabs.html');
		$this->assertStringContainsString('U_KB_TAB_RECENT_CHANGES', $tabs);
		$this->assertStringContainsString("'S_KB_TAB_RECENT_CHANGES' => \$active === 'recent_changes'", preg_replace('/\s+=>/', ' =>', file_get_contents(self::ROOT . '/inc/functions_kb.php')));

		$head = file_get_contents(self::ROOT . '/styles/all/template/event/overall_header_head_append.html');
		$this->assertStringContainsString('application/rss+xml', $head);
		$this->assertStringContainsString('application/atom+xml', $head);

		$acp = file_get_contents(self::ROOT . '/controller/acp_controller.php');
		$this->assertStringContainsString("'kb_feed_format', \$feed_format", $acp);
		$this->assertStringContainsString('KB_FEED_FORMAT_INVALID', $acp);
		$this->assertStringContainsString('S_FEED_FORMAT_OPTIONS', file_get_contents(self::ROOT . '/adm/style/acp_knowledgebase_config.html'));
	}

	public function test_every_new_language_string_exists()
	{
		$lang = [];
		if (!defined('IN_PHPBB'))
		{
			define('IN_PHPBB', true);
		}
		include self::ROOT . '/language/en/knowledgebase_lng.php';
		$front = $lang;
		$lang = [];
		include self::ROOT . '/language/en/acp_knowledgebase.php';
		$acp = $lang;

		foreach (['KB_TAB_RECENT_CHANGES', 'KB_RECENT_CHANGES_EXPLAIN', 'KB_RECENT_CHANGES_WHEN', 'KB_RECENT_NEW', 'KB_RECENT_UPDATED', 'KB_FEED_SUBSCRIBE', 'KB_FEED_RSS_LINK', 'KB_FEED_ATOM_LINK', 'KB_FEED_TITLE', 'KB_FEED_TITLE_SHORT', 'KB_FEED_UPDATED_IN', 'KB_FEED_ADDED_TO'] as $key)
		{
			$this->assertArrayHasKey($key, $front, $key);
		}
		foreach (['KB_FEED_FORMAT', 'KB_FEED_FORMAT_EXPLAIN', 'KB_FEED_FORMAT_BOTH', 'KB_FEED_FORMAT_RSS', 'KB_FEED_FORMAT_ATOM', 'KB_FEED_FORMAT_INVALID'] as $key)
		{
			$this->assertArrayHasKey($key, $acp, $key);
		}
	}
}
