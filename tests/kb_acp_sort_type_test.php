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

/** The ACP "Sorting order" setting: clear names, a tooltip and screen-reader text for each choice. */
class kb_acp_sort_type_test extends \PHPUnit\Framework\TestCase
{
	private const ROOT = __DIR__ . '/..';

	private function acp_language(): array
	{
		if (!defined('IN_PHPBB'))
		{
			define('IN_PHPBB', true);
		}
		$lang = [];
		require self::ROOT . '/language/en/acp_knowledgebase.php';

		return $lang;
	}

	private function template(): string
	{
		return file_get_contents(self::ROOT . '/adm/style/acp_knowledgebase_config.html');
	}

	/** @dataProvider choices */
	public function test_each_choice_has_a_name_and_an_explanation($name, $explain, $label)
	{
		$lang = $this->acp_language();

		$this->assertSame($label, $lang[$name]);
		$this->assertGreaterThan(40, strlen($lang[$explain]), $explain);
	}

	public function choices(): array
	{
		return [
			'fixed'      => ['KB_SORT_FIXED', 'KB_SORT_FIXED_EXPLAIN', 'Fixed'],
			'selectable' => ['KB_SORT_SELECTABLE', 'KB_SORT_SELECTABLE_EXPLAIN', 'Selectable'],
			'alphabetic' => ['KB_SORT_ALPHABET', 'KB_SORT_ALPHABET_EXPLAIN', 'Alphabetical'],
		];
	}

	public function test_the_old_names_are_gone_so_nothing_collides_with_the_page_language_file()
	{
		$lang = $this->acp_language();

		// The page file keeps ALPHABET for the A-B-C... row; the ACP must not redefine it.
		foreach (['FORCIBLY', 'SELECTABLE', 'ALPHABET'] as $old)
		{
			$this->assertArrayNotHasKey($old, $lang, $old);
		}
		$this->assertStringNotContainsString("lang('FORCIBLY')", $this->template());
		$this->assertStringNotContainsString("lang('ALPHABET')", $this->template());
	}

	public function test_the_three_radios_form_a_labelled_described_group()
	{
		$html = $this->template();

		$this->assertStringContainsString('<label id="sort_type_label">', $html);
		$this->assertStringContainsString('role="radiogroup" aria-labelledby="sort_type_label" aria-describedby="sort_type_explain"', $html);
		$this->assertStringContainsString('<span id="sort_type_explain">', $html);
		$this->assertStringNotContainsString('for="sort_type"', $html, 'a label for a single radio must not name the whole group');
		foreach (['fixed' => '0', 'selectable' => '1', 'alphabet' => '-1'] as $key => $value)
		{
			$id = 'sort_type_' . $key;
			$this->assertStringContainsString('<label for="' . $id . '" title="{{ lang(\'KB_SORT_' . strtoupper($key) . '_EXPLAIN\')|e(\'html_attr\') }}">', $html, $key);
			$this->assertStringContainsString('id="' . $id . '" name="sort_type" value="' . $value . '"', $html, $key);
			$this->assertStringContainsString('aria-describedby="' . $id . '_help"', $html, $key);
			$this->assertStringContainsString('<span id="' . $id . '_help" class="kb-sr-only">{{ lang(\'KB_SORT_' . strtoupper($key) . '_EXPLAIN\') }}</span>', $html, $key);
		}
	}

	public function test_the_screen_reader_text_is_hidden_visually_but_not_removed_from_the_page()
	{
		$css = file_get_contents(self::ROOT . '/adm/style/css/acp_knowledgebase.css');

		$this->assertMatchesRegularExpression('/\.kb-sr-only\s*\{[^}]*position:\s*absolute;[^}]*clip:\s*rect\(0, 0, 0, 0\);/s', $css);
		$this->assertDoesNotMatchRegularExpression('/\.kb-sr-only\s*\{[^}]*display:\s*none/s', $css);
	}

	public function test_the_alphabetic_navigation_is_a_labelled_landmark_with_a_tooltip_on_every_letter()
	{
		$template = file_get_contents(self::ROOT . '/styles/all/template/kb_cat_body.html');
		$controller = file_get_contents(self::ROOT . '/controller/category.php');
		if (!defined('IN_PHPBB'))
		{
			define('IN_PHPBB', true);
		}
		$lang = [];
		require self::ROOT . '/language/en/knowledgebase_lng.php';

		$this->assertStringContainsString('id="alphabet" class="inner" role="navigation" aria-label="{{ lang(\'ALPHABET_NAV\') }}"', $template);
		$this->assertStringContainsString("title=\"' . \$this->language->lang('KB_ALPHABET_LETTER', \$letter)", $controller);
		$this->assertStringContainsString("' aria-current=\"true\"'", $controller);
		$this->assertSame('Articles starting with %s', $lang['KB_ALPHABET_LETTER']);
	}
}
