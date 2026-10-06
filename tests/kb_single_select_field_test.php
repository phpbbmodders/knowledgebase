<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\inc\field;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/field/kb_field_type_interface.php';
require_once dirname(__DIR__) . '/inc/field/kb_multi_select_field_type.php';
require_once dirname(__DIR__) . '/inc/field/kb_single_select_field_type.php';
require_once dirname(__DIR__) . '/inc/field/kb_radio_select_field_type.php';

class kb_single_select_field_test extends \PHPUnit\Framework\TestCase
{
	private const FIELD = ['field_id' => '7', 'field_name' => 'Audience <b>', 'field_required' => '0'];
	private const VALUES = [['value_id' => '4', 'value_label' => 'Users'], ['value_id' => '5', 'value_label' => 'Admins & <i>devs</i>']];

	private function type(string $class)
	{
		$language = $this->getMockBuilder(\phpbb\language\language::class)->disableOriginalConstructor()->getMock();
		$language->method('lang')->willReturnCallback(fn() => implode('|', func_get_args()));

		return new $class($language);
	}

	/** @dataProvider single_classes */
	public function test_a_single_choice_keeps_only_the_first_real_value($class)
	{
		$type = $this->type($class);

		$this->assertSame([], $type->normalize_submitted_ids([]));
		$this->assertSame([], $type->normalize_submitted_ids([0]));
		$this->assertSame([4], $type->normalize_submitted_ids([4]));
		$this->assertSame([4], $type->normalize_submitted_ids([4, 5]));
		$this->assertSame([5], $type->normalize_submitted_ids([0, 5, 4]));
		$this->assertSame([5], $type->normalize_submitted_ids(['0', '5']));
		$this->assertSame([], $type->normalize_submitted_ids(['abc', -0]));
	}

	public function test_the_checkbox_list_still_keeps_every_value()
	{
		$this->assertSame([4, 5, 0], $this->type(kb_multi_select_field_type::class)->normalize_submitted_ids([4, 5, 0]));
	}

	public function test_each_type_reports_its_own_class_and_name()
	{
		$this->assertSame(kb_multi_select_field_type::class, $this->type(kb_multi_select_field_type::class)->get_type());
		$this->assertSame(kb_single_select_field_type::class, $this->type(kb_single_select_field_type::class)->get_type());
		$this->assertSame(kb_radio_select_field_type::class, $this->type(kb_radio_select_field_type::class)->get_type());
		$this->assertSame('KB_FIELD_TYPE_SINGLE_DROPDOWN', $this->type(kb_single_select_field_type::class)->get_type_name());
		$this->assertSame('KB_FIELD_TYPE_SINGLE_RADIO', $this->type(kb_radio_select_field_type::class)->get_type_name());
	}

	public function test_the_dropdown_marks_the_chosen_value_and_escapes_labels()
	{
		$html = $this->type(kb_single_select_field_type::class)->render_posting_field(self::FIELD, self::VALUES, [5]);

		$this->assertStringContainsString('<select name="field_values[7][]">', $html);
		$this->assertStringContainsString('<option value="0">KB_FIELD_SELECT_PLACEHOLDER</option>', $html);
		$this->assertStringContainsString('<option value="4">Users</option>', $html);
		$this->assertStringContainsString('<option value="5" selected="selected">Admins &amp; &lt;i&gt;devs&lt;/i&gt;</option>', $html);
		$this->assertStringContainsString('Audience &lt;b&gt;', $html);
		$this->assertStringNotContainsString('KB_FIELD_REQUIRED_HINT', $html);
	}

	public function test_the_dropdown_selects_only_one_even_if_the_stored_selection_has_several()
	{
		$html = $this->type(kb_single_select_field_type::class)->render_posting_field(self::FIELD, self::VALUES, [4, 5]);

		$this->assertSame(1, substr_count($html, 'selected="selected"'));
	}

	public function test_a_required_field_shows_the_hint()
	{
		foreach ([kb_single_select_field_type::class, kb_radio_select_field_type::class] as $class)
		{
			$html = $this->type($class)->render_posting_field(['field_required' => '1'] + self::FIELD, self::VALUES, []);

			$this->assertStringContainsString('KB_FIELD_REQUIRED_HINT', $html);
		}
	}

	public function test_radio_buttons_offer_none_only_for_an_optional_field()
	{
		$type = $this->type(kb_radio_select_field_type::class);

		$optional = $type->render_posting_field(self::FIELD, self::VALUES, []);
		$this->assertStringContainsString('type="radio" name="field_values[7][]" value="0" checked="checked"/> KB_FIELD_NONE_SELECTED', $optional);
		$this->assertSame(1, substr_count($optional, 'checked="checked"'));

		$required = $type->render_posting_field(['field_required' => '1'] + self::FIELD, self::VALUES, []);
		$this->assertStringNotContainsString('value="0"', $required);
		$this->assertStringNotContainsString('checked="checked"', $required);
	}

	public function test_radio_buttons_check_the_chosen_value_and_not_none()
	{
		$html = $this->type(kb_radio_select_field_type::class)->render_posting_field(self::FIELD, self::VALUES, [5]);

		$this->assertStringContainsString('value="5" checked="checked"/> Admins &amp; &lt;i&gt;devs&lt;/i&gt;', $html);
		$this->assertStringNotContainsString('value="0" checked', $html);
		$this->assertSame(1, substr_count($html, 'checked="checked"'));
	}

	/** @dataProvider single_classes */
	public function test_a_field_with_no_enabled_values_renders_nothing($class)
	{
		$this->assertSame('', $this->type($class)->render_posting_field(self::FIELD, [], []));
	}

	public function test_all_three_types_are_registered_as_field_types()
	{
		$services = file_get_contents(dirname(__DIR__) . '/config/services.yml');

		foreach ([kb_multi_select_field_type::class, kb_single_select_field_type::class, kb_radio_select_field_type::class] as $class)
		{
			$this->assertStringContainsString('class: ' . str_replace('\\', '\\', $class), $services);
		}
		$this->assertSame(3, preg_match_all('/name: phpbbmodders\.knowledgebase\.field_type/', $services));
	}

	public function single_classes(): array
	{
		return [[kb_single_select_field_type::class], [kb_radio_select_field_type::class]];
	}
}
