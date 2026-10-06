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

use phpbb\language\language;

/**
 * A checkbox list of admin-managed values - the shape phpBB-version
 * tagging needs, and the only field type this phase ships.
 */
class kb_multi_select_field_type implements kb_field_type_interface
{
	/** @var language */
	protected language $language;

	/**
	 * @param language $language
	 */
	public function __construct(language $language)
	{
		$this->language = $language;
	}

	/**
	 * @return string
	 */
	public function get_type_name(): string
	{
		return $this->language->lang('KB_FIELD_TYPE_MULTI_SELECT');
	}

	/**
	 * @param array $field
	 * @param array $values
	 * @return string
	 */
	public function render_value_editor(array $field, array $values): string
	{
		$rows = '';
		foreach ($values as $value)
		{
			$checked = $value['enabled'] ? ' checked="checked"' : '';
			$rows .= '<dl>
				<dt>' . htmlspecialchars($value['value_label']) . '</dt>
				<dd>
					<label><input type="checkbox" name="value_enabled[' . (int) $value['value_id'] . ']" value="1"' . $checked . '/> ' . $this->language->lang('KB_FIELD_VALUE_ENABLED') . '</label>
					<label><input type="checkbox" name="value_delete[' . (int) $value['value_id'] . ']" value="1"/> ' . $this->language->lang('DELETE') . '</label>
				</dd>
			</dl>';
		}

		$rows .= '<dl>
			<dt><label for="new_value_label">' . $this->language->lang('KB_FIELD_NEW_VALUE') . '</label></dt>
			<dd><input type="text" name="new_value_label" id="new_value_label" maxlength="255" class="inputbox"/></dd>
		</dl>';

		return $rows;
	}

	/**
	 * @param array $field
	 * @param array $values
	 * @param int[] $selected_value_ids
	 * @return string
	 */
	public function render_posting_field(array $field, array $values, array $selected_value_ids): string
	{
		if (empty($values))
		{
			return '';
		}

		$options = '';
		foreach ($values as $value)
		{
			$checked = in_array((int) $value['value_id'], $selected_value_ids, true) ? ' checked="checked"' : '';
			$options .= '<label><input type="checkbox" name="field_values[' . (int) $field['field_id'] . '][]" value="' . (int) $value['value_id'] . '"' . $checked . '/> ' . htmlspecialchars($value['value_label']) . '</label> ';
		}

		$hint = !empty($field['field_required']) ? '<br><span>' . $this->language->lang('KB_FIELD_REQUIRED_HINT') . '</span>' : '';

		return '<dl style="clear: left;">
			<dt><label>' . htmlspecialchars($field['field_name']) . $this->language->lang('COLON') . '</label>' . $hint . '</dt>
			<dd>' . $options . '</dd>
		</dl>';
	}

	/**
	 * Any number of values may be chosen.
	 *
	 * @param int[] $value_ids
	 * @return int[]
	 */
	public function normalize_submitted_ids(array $value_ids): array
	{
		return $value_ids;
	}

	/**
	 * @param array $field
	 * @param array $selected_values
	 * @return string
	 */
	public function render_display(array $field, array $selected_values): string
	{
		if (empty($selected_values))
		{
			return '';
		}

		$labels = array_map(function ($value) {
			return htmlspecialchars($value['value_label']);
		}, $selected_values);

		return '<strong>' . htmlspecialchars($field['field_name']) . $this->language->lang('COLON') . '</strong> ' . implode(', ', $labels);
	}

	/**
	 * @return string
	 */
	public function get_type(): string
	{
		return static::class;
	}
}
