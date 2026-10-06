<?php
namespace phpbbmodders\knowledgebase;

require_once __DIR__ . '/kb_extensions_test.php';

/** The extension's one set of templates lives in styles/all; the classic look is an optional stylesheet on top. */
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
		foreach (['template/js/copy.js', 'template/js/markdown_toggle.js', 'theme/phpbbmodders_knowledgebase.css', 'theme/phpbbmodders_knowledgebase_classic.css', 'theme/phpbbmodders_print.css'] as $asset)
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

	public function test_the_classic_sheet_is_loaded_only_when_the_setting_is_on()
	{
		$event = $this->read('styles/all/template/event/overall_header_stylesheets_after.html');

		$this->assertMatchesRegularExpression("/INCLUDECSS '@phpbbmodders_knowledgebase\/phpbbmodders_knowledgebase\.css'.*\{% if S_KB_CLASSIC_LOOK %\}.*phpbbmodders_knowledgebase_classic\.css.*\{% endif %\}/s", $event);
		$this->assertSame(1, substr_count($event, 'phpbbmodders_knowledgebase_classic.css'));
	}

	public function test_the_base_sheet_has_no_classic_look_and_the_classic_sheet_has_it()
	{
		$base = $this->read('styles/all/theme/phpbbmodders_knowledgebase.css');
		$classic = $this->read('styles/all/theme/phpbbmodders_knowledgebase_classic.css');

		foreach (['.kb-tab', '#536482', '#8f9dab', 'nth-child(even)', 'thead th'] as $classic_only)
		{
			$this->assertStringNotContainsString($classic_only, $base, $classic_only);
		}
		$this->assertStringContainsString('.kb-tab', $classic);
		$this->assertStringContainsString('table.table1.kb-index-table', $classic);
		// The status badges and the article page layout fixes belong to every look.
		$this->assertStringContainsString('.kb-status-badge', $base);
		$this->assertStringContainsString('.kb-meta', $base);
		$this->assertStringNotContainsString('.kb-status-badge', $classic);
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

	public function test_the_forum_style_boxes_carry_a_hook_the_classic_sheet_recolours()
	{
		$this->assertStringContainsString('<div class="forabg kb-listing">', $this->read('styles/all/template/kb_cat_list.html'));
		$this->assertStringContainsString('<div class="forumbg kb-listing">', $this->read('styles/all/template/kb_search_results.html'));

		$classic = $this->read('styles/all/theme/phpbbmodders_knowledgebase_classic.css');
		$this->assertStringContainsString('.forabg.kb-listing', $classic);
		$this->assertStringContainsString('.forumbg.kb-listing', $classic);
		$this->assertStringNotContainsString('kb-listing', $this->read('styles/all/theme/phpbbmodders_knowledgebase.css'));
	}

	/** @dataProvider article_tables */
	public function test_the_article_tables_use_prosilvers_table_class_and_keep_the_kb_hook($template)
	{
		$this->assertStringContainsString('<table class="table1 kb-index-table">', $this->read('styles/all/template/' . $template . '.html'));
	}

	public function article_tables(): array
	{
		return [['kb_cat_body'], ['kb_queue_body'], ['kb_my_articles_body']];
	}
}
