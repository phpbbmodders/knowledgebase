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

/** The extension's one set of templates lives in styles/all; the flat index and the alternative stylesheets are optional sheets on top. */
class kb_style_layout_test extends \PHPUnit\Framework\TestCase
{
	private const ROOT = __DIR__ . '/..';

	private function read(string $path): string
	{
		$this->assertFileExists(self::ROOT . '/' . $path);

		return file_get_contents(self::ROOT . '/' . $path);
	}

	public function test_everything_ships_in_styles_all_and_nothing_is_tied_to_a_board_style()
	{
		$folders = array_values(array_diff(scandir(self::ROOT . '/styles'), ['.', '..']));

		$this->assertSame(['all'], $folders);
	}

	/** @dataProvider shipped_files */
	public function test_each_expected_file_is_in_styles_all($path)
	{
		$this->assertFileExists(self::ROOT . '/styles/all/' . $path);
	}

	public function shipped_files(): array
	{
		$files = [];
		foreach (['kb_approve_body', 'kb_article_body', 'kb_article_body_print', 'kb_cat_body', 'kb_cat_list', 'kb_index_body', 'kb_my_articles_body', 'kb_nav_tabs', 'kb_notice_body', 'kb_post_body', 'kb_queue_body', 'kb_search_body', 'kb_search_results'] as $template)
		{
			$files[$template] = ['template/' . $template . '.html'];
		}
		foreach (['overall_footer_body_after', 'overall_header_navigation_prepend', 'overall_header_stylesheets_after'] as $event)
		{
			$files['event ' . $event] = ['template/event/' . $event . '.html'];
		}
		foreach (['template/js/copy.js', 'template/js/markdown_toggle.js', 'theme/phpbbmodders_knowledgebase.css', 'theme/phpbbmodders_knowledgebase_flat.css', 'theme/alternatives/slate.css', 'theme/phpbbmodders_print.css'] as $asset)
		{
			$files[$asset] = [$asset];
		}

		return $files;
	}

	public function test_no_template_or_script_still_points_at_the_old_prosilver_folder()
	{
		foreach (glob(self::ROOT . '/styles/all/template/*.html') as $template)
		{
			$this->assertStringNotContainsString('styles/prosilver', file_get_contents($template), basename($template));
		}
		$this->assertStringContainsString('styles/all/theme/phpbbmodders_print.css', $this->read('styles/all/template/kb_article_body_print.html'));
	}

	public function test_the_stylesheets_load_in_order_and_each_only_when_its_setting_asks()
	{
		$event = $this->read('styles/all/template/event/overall_header_stylesheets_after.html');

		// Base first, then the flat layout, then a bundled alternative, then a custom one.
		$this->assertMatchesRegularExpression("/INCLUDECSS '@phpbbmodders_knowledgebase\/phpbbmodders_knowledgebase\.css'.*\{% if S_KB_FLAT_INDEX %\}.*phpbbmodders_knowledgebase_flat\.css.*\{% endif %\}.*\{% if KB_ALT_STYLE_BUNDLED_FILE %\}.*INCLUDECSS '@phpbbmodders_knowledgebase\/' ~ KB_ALT_STYLE_BUNDLED_FILE.*\{% endif %\}.*\{% if U_KB_ALT_STYLE_CUSTOM %\}.*<link href=\"\{\{ U_KB_ALT_STYLE_CUSTOM \}\}\" rel=\"stylesheet\">.*\{% endif %\}/s", $event);
		$this->assertSame(1, substr_count($event, 'phpbbmodders_knowledgebase_flat.css'));
		$this->assertStringNotContainsString('classic', $event);
	}

	public function test_the_base_sheet_carries_neither_the_flat_layout_nor_any_alternative_colours()
	{
		$base = $this->read('styles/all/theme/phpbbmodders_knowledgebase.css');

		foreach (['.kb-tab', '#536482', '#8f9dab', 'nth-child(even)', 'thead th', 'kb-listing'] as $other_sheet)
		{
			$this->assertStringNotContainsString($other_sheet, $base, $other_sheet);
		}
		// The status badges and the article page layout fixes belong to every look.
		$this->assertStringContainsString('.kb-status-badge', $base);
		$this->assertStringContainsString('.kb-meta', $base);
	}

	public function test_the_flat_sheet_is_layout_only()
	{
		$flat = $this->css_without_comments('styles/all/theme/phpbbmodders_knowledgebase_flat.css');

		$this->assertStringContainsString('.kb-tab', $flat);
		$this->assertStringContainsString('table.table1.kb-index-table', $flat);
		$this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', $flat, 'a fixed colour');
		$this->assertDoesNotMatchRegularExpression('/(^|[;{\s])(background[\w-]*|color|border-[\w-]*color)\s*:/', $flat, 'a colour declaration');
		$this->assertStringNotContainsString('.kb-status-badge', $flat);
	}

	public function test_a_bundled_alternative_is_colours_only_and_documents_itself()
	{
		$raw = $this->read('styles/all/theme/alternatives/slate.css');
		$slate = $this->css_without_comments('styles/all/theme/alternatives/slate.css');

		$this->assertDoesNotMatchRegularExpression('/(^|[;{\s])(padding|margin|float|display|flex|width|height|font[\w-]*|text-align|border-radius|border-width|border-style)[\w-]*\s*:/', $slate, 'a layout declaration');
		$this->assertStringNotContainsString('!important', $slate);
		foreach (['.kb-tab', '.forabg.kb-listing', '.forumbg.kb-listing', 'tr.bg1', 'tr.bg2'] as $hook)
		{
			$this->assertStringContainsString($hook, $slate, $hook);
		}
		// The example explains what it is, where custom files go, and that layout is elsewhere.
		foreach (['store/phpbbmodders_knowledgebase/stylesheets/', 'colours only', 'SECTION 1', 'SECTION 4', 'flat'] as $explanation)
		{
			$this->assertStringContainsString($explanation, $raw, $explanation);
		}
		$this->assertGreaterThan(40, substr_count($raw, "\n *"), 'the example is richly commented');
	}

	public function test_the_tab_strip_uses_prosilvers_own_tab_classes_and_keeps_the_kb_hooks()
	{
		$tabs = $this->read('styles/all/template/kb_nav_tabs.html');

		$this->assertStringContainsString('<div class="tabs kb-tabs">', $tabs);
		$this->assertSame(6, substr_count($tabs, '<li class="tab'));
		$this->assertSame(6, preg_match_all('/<li class="tab [^"]*kb-tab/', $tabs));
		$this->assertStringNotContainsString('<a href="{{ U_KB_TAB_INDEX }}" class="kb-tab', $tabs);
		$this->assertSame(substr_count($tabs, 'activetab'), substr_count($tabs, 'kb-tab-active'));
	}

	public function test_the_forum_style_boxes_carry_a_hook_the_alternatives_recolour()
	{
		$this->assertStringContainsString('<div class="forabg kb-listing">', $this->read('styles/all/template/kb_cat_list.html'));
		$this->assertStringContainsString('<div class="forumbg kb-listing">', $this->read('styles/all/template/kb_search_results.html'));

		$slate = $this->read('styles/all/theme/alternatives/slate.css');
		$this->assertStringContainsString('.forabg.kb-listing', $slate);
		$this->assertStringContainsString('.forumbg.kb-listing', $slate);
		$this->assertStringNotContainsString('kb-listing', $this->read('styles/all/theme/phpbbmodders_knowledgebase.css'));
	}

	public function test_markdown_headings_in_article_text_are_visible_and_not_styled_as_a_forum_title()
	{
		$base = $this->read('styles/all/theme/phpbbmodders_knowledgebase.css');

		// prosilver paints an h1 white and gives it the board-name margins.
		$this->assertMatchesRegularExpression('/\.content h1\s*\{[^}]*color:\s*inherit;[^}]*margin-right:\s*0;/s', $base);
		foreach (['h1', 'h3', 'h4', 'h5', 'h6'] as $heading)
		{
			$this->assertStringContainsString('.content ' . $heading, $base, $heading);
		}
		$this->assertMatchesRegularExpression('/\.content h1,\s*\.content h3,[^{]*\{[^}]*float:\s*none;[^}]*text-transform:\s*none;/s', $base);
	}

	/** @dataProvider article_tables */
	public function test_the_article_tables_use_prosilvers_header_box_and_stripes_and_keep_the_kb_hooks($template)
	{
		$html = $this->read('styles/all/template/' . $template . '.html');

		// The header text is white in prosilver and needs the board style's forum-header box behind it.
		$this->assertStringContainsString("<div class=\"forumbg forumbg-table kb-listing\">\n<div class=\"inner\">\n<table class=\"table1 kb-index-table\">", $html);
		$this->assertSame(1, substr_count($html, '</table>'));
		$this->assertStringContainsString("</table>\n</div>\n</div>", $html);
		// Rows take the board style's own stripe classes instead of a fixed colour.
		$this->assertStringContainsString("{{ loop.index is odd ? 'bg1' : 'bg2' }}", $html);
		$this->assertStringContainsString('<tr class="bg1">', $html);
	}

	public function article_tables(): array
	{
		return [['kb_cat_body'], ['kb_queue_body'], ['kb_my_articles_body']];
	}

	/** A stylesheet's rules with every /* comment *\/ removed. */
	private function css_without_comments(string $path): string
	{
		return preg_replace('~/\*.*?\*/~s', '', $this->read($path));
	}
}
