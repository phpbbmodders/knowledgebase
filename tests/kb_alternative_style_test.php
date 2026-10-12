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
require_once dirname(__DIR__) . '/inc/alternative_styles.php';
require_once dirname(__DIR__) . '/controller/stylesheet.php';
require_once dirname(__DIR__) . '/event/listener.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_24.php';

use phpbbmodders\knowledgebase\inc\alternative_styles;

/**
 * The optional alternative stylesheet: discovery, the choice an administrator
 * saves, how the page header hands it to the templates, the controller that
 * serves a custom file from the store folder, and the 2.0.24 migration.
 */
class kb_alternative_style_test extends \PHPUnit\Framework\TestCase
{
	private const EXTENSION = __DIR__ . '/../';

	/** @var string A throwaway board root holding store/phpbbmodders_knowledgebase/stylesheets/ */
	private $root;

	/** @var string The custom folder inside it */
	private $custom;

	protected function setUp(): void
	{
		$this->root = sys_get_temp_dir() . '/kb-alt-style-' . bin2hex(random_bytes(4)) . '/';
		$this->custom = $this->root . 'store/phpbbmodders_knowledgebase/stylesheets/';
		mkdir($this->custom, 0777, true);
	}

	protected function tearDown(): void
	{
		$this->remove($this->root);
	}

	private function remove(string $path): void
	{
		if (is_link($path) || is_file($path))
		{
			unlink($path);
			return;
		}
		foreach (array_diff(scandir($path), ['.', '..']) as $entry)
		{
			$this->remove($path . '/' . $entry);
		}
		rmdir($path);
	}

	private function styles(array $config = []): alternative_styles
	{
		return new alternative_styles(new \phpbb\config\config($config), self::EXTENSION, $this->root);
	}

	private function custom_file(string $name, string $css = 'a { color: red; }'): void
	{
		file_put_contents($this->custom . $name, $css);
	}

	public function test_the_bundled_alternatives_are_found_in_the_extension()
	{
		$this->assertContains('slate', $this->styles()->bundled());
	}

	public function test_only_safe_custom_files_are_listed()
	{
		$this->custom_file('ocean.css');
		$this->custom_file('Theme_2.css');
		$this->custom_file('notes.txt');
		$this->custom_file('bad..name.css');
		$this->custom_file('.hidden.css');
		$this->custom_file('huge.css', str_repeat('a', alternative_styles::MAX_CUSTOM_BYTES + 1));
		mkdir($this->custom . 'folder.css');
		symlink($this->custom . 'ocean.css', $this->custom . 'link.css');

		$this->assertSame(['Theme_2.css', 'ocean.css'], $this->styles()->custom());
	}

	public function test_a_file_exactly_at_the_size_limit_is_listed()
	{
		$this->custom_file('limit.css', str_repeat('a', alternative_styles::MAX_CUSTOM_BYTES));

		$this->assertSame(['limit.css'], $this->styles()->custom());
	}

	public function test_a_missing_custom_folder_lists_nothing()
	{
		$this->remove($this->root . 'store');

		$this->assertSame([], $this->styles()->custom());
		$this->assertNull($this->styles()->custom_file_path('ocean.css'));
	}

	public function test_the_custom_folder_being_a_symlink_is_not_followed()
	{
		$elsewhere = $this->root . 'elsewhere/';
		mkdir($elsewhere);
		file_put_contents($elsewhere . 'ocean.css', 'a{}');
		$this->remove($this->custom);
		symlink($elsewhere, rtrim($this->custom, '/'));

		$this->assertNull($this->styles()->custom_file_path('ocean.css'));
		$this->assertSame([], $this->styles()->custom());
	}

	/** @dataProvider names_that_must_not_resolve */
	public function test_names_that_could_leave_the_folder_never_resolve(string $name)
	{
		$this->custom_file('ocean.css');
		file_put_contents($this->root . 'store/phpbbmodders_knowledgebase/outside.css', 'a{}');

		$this->assertNull($this->styles()->custom_file_path($name));
	}

	public function names_that_must_not_resolve(): array
	{
		return [
			'empty'              => [''],
			'parent folder'      => ['../outside.css'],
			'encoded parent'     => ['..%2Foutside.css'],
			'absolute'           => ['/etc/passwd'],
			'nested'             => ['sub/ocean.css'],
			'backslash'          => ['..\\outside.css'],
			'wrong extension'    => ['ocean.css.php'],
			'null byte'          => ["ocean.css\0.txt"],
			'double dot'         => ['a..b.css'],
			'leading dot'        => ['.ocean.css'],
			'too long'           => [str_repeat('a', 70) . '.css'],
			'space'              => ['my sheet.css'],
		];
	}

	public function test_a_symlinked_file_is_refused_even_when_it_points_inside_the_folder()
	{
		$this->custom_file('ocean.css');
		symlink($this->custom . 'ocean.css', $this->custom . 'alias.css');

		$this->assertNull($this->styles()->custom_file_path('alias.css'));
		$this->assertNotNull($this->styles()->custom_file_path('ocean.css'));
	}

	/** @dataProvider choices */
	public function test_a_stored_or_submitted_choice_is_parsed_strictly($choice, $expected)
	{
		$this->assertSame($expected, $this->styles()->parse_choice($choice));
	}

	public function choices(): array
	{
		return [
			'bundled'            => ['bundled:slate', ['bundled', 'slate']],
			'custom'             => ['custom:ocean.css', ['custom', 'ocean.css']],
			'nothing'            => ['', null],
			'no kind'            => ['slate', null],
			'unknown kind'       => ['remote:slate', null],
			'bundled with path'  => ['bundled:../slate', null],
			'bundled upper case' => ['bundled:Slate', null],
			'custom with path'   => ['custom:../x.css', null],
			'custom no css'      => ['custom:ocean', null],
		];
	}

	public function test_a_choice_is_available_only_when_its_file_exists()
	{
		$this->custom_file('ocean.css');
		$styles = $this->styles();

		$this->assertTrue($styles->is_available('bundled:slate'));
		$this->assertTrue($styles->is_available('custom:ocean.css'));
		$this->assertFalse($styles->is_available('bundled:nothere'));
		$this->assertFalse($styles->is_available('custom:nothere.css'));
		$this->assertFalse($styles->is_available('custom:../outside.css'));
	}

	public function test_nothing_is_active_while_alternative_styling_is_off_however_it_is_chosen()
	{
		$this->assertNull($this->styles(['kb_alt_style_enabled' => 0, 'kb_alt_style' => 'bundled:slate'])->active());
		$this->assertNull($this->styles(['kb_alt_style' => 'bundled:slate'])->active());
	}

	public function test_a_bundled_choice_is_active_when_switched_on()
	{
		$this->assertSame(['bundled', 'slate'], $this->styles(['kb_alt_style_enabled' => '1', 'kb_alt_style' => 'bundled:slate'])->active());
	}

	public function test_a_custom_choice_is_active_while_its_file_exists_and_not_after_it_goes()
	{
		$config = ['kb_alt_style_enabled' => 1, 'kb_alt_style' => 'custom:ocean.css'];
		$this->custom_file('ocean.css');
		$this->assertSame(['custom', 'ocean.css'], $this->styles($config)->active());

		unlink($this->custom . 'ocean.css');
		$this->assertNull($this->styles($config)->active(), 'falls back to the board style');
		$this->assertSame('custom:ocean.css', $this->styles($config)->missing_choice());
	}

	public function test_an_available_or_unset_choice_is_not_reported_missing()
	{
		$this->assertSame('', $this->styles(['kb_alt_style' => 'bundled:slate'])->missing_choice());
		$this->assertSame('', $this->styles([])->missing_choice());
	}

	// The page header.

	private function assigned_by_the_page_header(array $config, ?alternative_styles $styles = null): array
	{
		$assigned = [];
		$template = $this->createMock(\phpbb\template\template::class);
		$template->method('assign_vars')->willReturnCallback(function ($vars) use (&$assigned) {
			$assigned = $vars;
		});
		$helper = $this->createMock(\phpbb\controller\helper::class);
		$helper->method('route')->willReturnCallback(fn($name, $params = []) => '/' . $name . '?' . http_build_query($params));
		$auth = $this->createMock(\phpbb\auth\auth::class);
		$auth->method('acl_get')->willReturn(false);
		$config += ['kb_font_icon' => 'book', 'kb_flat_index' => 0];

		$listener = new \phpbbmodders\knowledgebase\event\listener(
			new \phpbb\config\config($config),
			$this->createMock(\phpbb\config\db_text::class),
			$helper,
			$this->createMock(\phpbb\language\language::class),
			$auth,
			$template,
			'php',
			$styles ?? $this->styles($config)
		);
		$listener->add_page_header_link(new \stdClass());

		return $assigned;
	}

	public function test_by_default_the_templates_get_no_flat_layout_and_no_alternative()
	{
		$vars = $this->assigned_by_the_page_header([]);

		$this->assertFalse($vars['S_KB_FLAT_INDEX']);
		$this->assertSame('', $vars['KB_ALT_STYLE_BUNDLED_FILE']);
		$this->assertSame('', $vars['U_KB_ALT_STYLE_CUSTOM']);
		$this->assertSame('book', $vars['KB_FONT_ICON']);
		$this->assertArrayNotHasKey('S_KB_CLASSIC_LOOK', $vars);
	}

	/** @dataProvider flat_settings */
	public function test_the_flat_layout_follows_only_the_flat_index_setting($config, bool $expected)
	{
		$this->assertSame($expected, $this->assigned_by_the_page_header($config)['S_KB_FLAT_INDEX']);
	}

	public function flat_settings(): array
	{
		return [
			'flat on'                     => [['kb_flat_index' => '1'], true],
			'flat off'                    => [['kb_flat_index' => '0'], false],
			'flat on, colours on'         => [['kb_flat_index' => 1, 'kb_alt_style_enabled' => 1, 'kb_alt_style' => 'bundled:slate'], true],
			'flat off, colours on'        => [['kb_flat_index' => 0, 'kb_alt_style_enabled' => 1, 'kb_alt_style' => 'bundled:slate'], false],
		];
	}

	public function test_a_bundled_alternative_is_handed_over_as_its_theme_file()
	{
		$vars = $this->assigned_by_the_page_header(['kb_alt_style_enabled' => 1, 'kb_alt_style' => 'bundled:slate']);

		$this->assertSame('alternatives/slate.css', $vars['KB_ALT_STYLE_BUNDLED_FILE']);
		$this->assertSame('', $vars['U_KB_ALT_STYLE_CUSTOM']);
	}

	public function test_a_custom_alternative_is_handed_over_as_a_versioned_controller_url()
	{
		$this->custom_file('ocean.css');
		touch($this->custom . 'ocean.css', 1700000000);

		$vars = $this->assigned_by_the_page_header(['kb_alt_style_enabled' => 1, 'kb_alt_style' => 'custom:ocean.css']);

		$this->assertSame('/phpbbmodders_knowledgebase_stylesheet?f=ocean.css&v=1700000000', $vars['U_KB_ALT_STYLE_CUSTOM']);
		$this->assertSame('', $vars['KB_ALT_STYLE_BUNDLED_FILE']);
	}

	public function test_a_vanished_custom_file_adds_nothing_to_the_page()
	{
		$vars = $this->assigned_by_the_page_header(['kb_alt_style_enabled' => 1, 'kb_alt_style' => 'custom:gone.css']);

		$this->assertSame('', $vars['U_KB_ALT_STYLE_CUSTOM']);
		$this->assertSame('', $vars['KB_ALT_STYLE_BUNDLED_FILE']);
	}

	// The controller that serves a custom stylesheet.

	private function served(string $name, string $if_none_match = ''): \Symfony\Component\HttpFoundation\Response
	{
		$request = $this->createMock(\phpbb\request\request::class);
		$request->method('variable')->willReturn($name);
		// request::server() hands values back HTML-escaped, quotes included.
		$request->method('server')->willReturn(htmlspecialchars($if_none_match, ENT_COMPAT));

		return (new stylesheet($this->styles(), $request))->serve();
	}

	public function test_a_custom_stylesheet_is_served_as_css_with_safe_headers()
	{
		$this->custom_file('ocean.css', '.tab { color: teal; }');

		$response = $this->served('ocean.css');

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('.tab { color: teal; }', $response->getContent());
		$this->assertSame('text/css; charset=UTF-8', $response->headers->get('Content-Type'));
		$this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
		$this->assertNotEmpty($response->headers->get('ETag'));
	}

	public function test_an_unchanged_file_gets_a_not_modified_reply_without_a_body()
	{
		$this->custom_file('ocean.css');
		$etag = $this->served('ocean.css')->headers->get('ETag');

		$response = $this->served('ocean.css', $etag);

		$this->assertSame(304, $response->getStatusCode());
		$this->assertSame('', $response->getContent());
		$this->assertSame(200, $this->served('ocean.css', '"another"')->getStatusCode());
	}

	/** @dataProvider names_that_must_not_resolve */
	public function test_nothing_unsafe_is_ever_served(string $name)
	{
		$this->custom_file('ocean.css');
		file_put_contents($this->root . 'store/phpbbmodders_knowledgebase/outside.css', 'secret');

		$response = $this->served($name);

		$this->assertSame(404, $response->getStatusCode());
		$this->assertStringNotContainsString('secret', $response->getContent());
		$this->assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
	}

	public function test_a_symlink_to_a_file_outside_the_board_is_not_served()
	{
		file_put_contents($this->root . 'private.txt', 'private');
		symlink($this->root . 'private.txt', $this->custom . 'leak.css');

		$this->assertSame(404, $this->served('leak.css')->getStatusCode());
	}

	// What an administrator saves.

	private function acp(array $submitted, array $config, ?array &$saved = []): acp_controller
	{
		$request = $this->createMock(\phpbb\request\request_interface::class);
		$request->method('variable')->willReturnCallback(fn($name, $default) => $submitted[$name] ?? $default);
		$stored = new \phpbb\config\config($config);
		$config_spy = $this->getMockBuilder(\phpbb\config\config::class)->setConstructorArgs([$config])->onlyMethods(['set'])->getMock();
		$config_spy->method('set')->willReturnCallback(function ($key, $value) use (&$saved) {
			$saved[$key] = $value;
			return true;
		});
		$language = $this->createMock(\phpbb\language\language::class);
		$language->method('lang')->willReturnCallback(fn($key, ...$args) => $key . ($args ? ' ' . implode(',', $args) : ''));
		$language->method('is_set')->willReturnCallback(fn($key) => $key === 'KB_ALT_STYLE_SLATE');

		$controller = (new \ReflectionClass(acp_controller::class))->newInstanceWithoutConstructor();
		foreach (['request' => $request, 'config' => $config_spy, 'language' => $language, 'alt_styles' => $this->styles($config), 'u_action' => 'x'] as $name => $value)
		{
			$property = new \ReflectionProperty(acp_controller::class, $name);
			$property->setAccessible(true);
			$property->setValue($controller, $value);
		}

		return $controller;
	}

	private function call(acp_controller $controller, string $method, ...$args)
	{
		$reflection = new \ReflectionMethod(acp_controller::class, $method);
		$reflection->setAccessible(true);

		return $reflection->invoke($controller, ...$args);
	}

	public function test_switching_on_with_a_bundled_stylesheet_saves_both_settings()
	{
		$saved = [];
		$controller = $this->acp(['alt_style_enabled' => 1, 'alt_style' => 'bundled:slate'], ['kb_alt_style' => ''], $saved);

		$this->call($controller, 'validate_alternative_style');
		$this->call($controller, 'save_alternative_style');

		$this->assertSame(['kb_alt_style_enabled' => 1, 'kb_alt_style' => 'bundled:slate'], $saved);
	}

	public function test_switching_off_still_remembers_the_chosen_stylesheet()
	{
		$this->custom_file('ocean.css');
		$saved = [];
		$controller = $this->acp(['alt_style' => 'custom:ocean.css'], ['kb_alt_style' => 'bundled:slate'], $saved);

		$this->call($controller, 'validate_alternative_style');
		$this->call($controller, 'save_alternative_style');

		$this->assertSame(['kb_alt_style_enabled' => 0, 'kb_alt_style' => 'custom:ocean.css'], $saved);
	}

	public function test_switching_off_keeps_the_remembered_choice_when_the_submitted_one_is_not_available()
	{
		$saved = [];
		$controller = $this->acp(['alt_style' => 'custom:../outside.css'], ['kb_alt_style' => 'bundled:slate'], $saved);

		$this->call($controller, 'validate_alternative_style');
		$this->call($controller, 'save_alternative_style');

		$this->assertSame(['kb_alt_style_enabled' => 0], $saved, 'no stylesheet was written');
	}

	/** @dataProvider unavailable_when_switching_on */
	public function test_switching_on_with_an_unavailable_stylesheet_is_refused($choice)
	{
		$controller = $this->acp(['alt_style_enabled' => 1, 'alt_style' => $choice], []);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('KB_ALT_STYLE_INVALID');
		$this->call($controller, 'validate_alternative_style');
	}

	public function unavailable_when_switching_on(): array
	{
		return [['custom:nothere.css'], ['custom:../outside.css'], ['bundled:nothere'], [''], ['slate']];
	}

	public function test_the_dropdown_groups_bundled_and_custom_choices_and_escapes_every_label()
	{
		$this->custom_file('ocean.css');
		$controller = $this->acp([], []);

		$html = $this->call($controller, 'alternative_style_options', 'custom:ocean.css');

		$this->assertSame(
			'<optgroup label="KB_ALT_STYLE_BUNDLED_GROUP"><option value="bundled:slate">KB_ALT_STYLE_SLATE</option></optgroup>'
			. '<optgroup label="KB_ALT_STYLE_CUSTOM_GROUP"><option value="custom:ocean.css" selected="selected">ocean.css</option></optgroup>',
			$html
		);
	}

	public function test_the_dropdown_has_no_custom_group_when_the_store_folder_is_empty()
	{
		$html = $this->call($this->acp([], []), 'alternative_style_options', 'bundled:slate');

		$this->assertStringContainsString('<option value="bundled:slate" selected="selected">', $html);
		$this->assertStringNotContainsString('KB_ALT_STYLE_CUSTOM_GROUP', $html);
	}

	// The migration.

	private function migration(array $config): \phpbbmodders\knowledgebase\migrations\version_2_0_24
	{
		return new \phpbbmodders\knowledgebase\migrations\version_2_0_24(new \phpbb\config\config($config), $this->createMock(\phpbb\db\driver\driver_interface::class), $this->createMock(\phpbb\db\tools\tools_interface::class), '', 'php', 'phpbb_');
	}

	public function test_the_migration_adds_the_two_settings_off_and_removes_the_classic_one_without_mapping_it()
	{
		$migration = $this->migration([]);

		$this->assertSame([
			['config.add', ['kb_alt_style_enabled', 0]],
			['config.add', ['kb_alt_style', 'bundled:slate']],
			['config.remove', ['kb_classic_look']],
			['config.update', ['kb_version', '2.0.24']],
		], $migration->update_data());
		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_23'], \phpbbmodders\knowledgebase\migrations\version_2_0_24::depends_on());
	}

	public function test_the_migration_leaves_the_flat_index_and_everything_else_alone()
	{
		$touched = array_map(fn($step) => $step[1][0], $this->migration([])->update_data());

		$this->assertSame(['kb_alt_style_enabled', 'kb_alt_style', 'kb_classic_look', 'kb_version'], $touched);
	}

	public function test_the_migration_is_skipped_once_the_setting_exists_and_reverts_cleanly()
	{
		$this->assertFalse($this->migration([])->effectively_installed());
		$this->assertTrue($this->migration(['kb_alt_style_enabled' => 0])->effectively_installed());

		$reverted = array_map(fn($step) => $step[0] . ':' . $step[1][0], $this->migration([])->revert_data());
		$this->assertSame(['config.add:kb_classic_look', 'config.remove:kb_alt_style_enabled', 'config.remove:kb_alt_style', 'config.update:kb_version'], $reverted);
	}
}
