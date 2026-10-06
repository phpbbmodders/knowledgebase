<?php

/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\inc\field;

/**
 * The same one-value-from-a-list field as the dropdown, shown as radio
 * buttons. An optional field gets a "none" button so a choice can be cleared;
 * a required one does not.
 */
class kb_radio_select_field_type extends kb_single_select_field_type
{
	/**
	 * @return string
	 */
	public function get_type_name(): string
	{
		return $this->language->lang('KB_FIELD_TYPE_SINGLE_RADIO');
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

		$selected = $this->normalize_submitted_ids($selected_value_ids);
		$name = 'field_values[' . (int) $field['field_id'] . '][]';
		$buttons = '';

		if (empty($field['field_required']))
		{
			$buttons .= '<label><input type="radio" name="' . $name . '" value="0"' . ($selected ? '' : ' checked="checked"') . '/> ' . $this->language->lang('KB_FIELD_NONE_SELECTED') . '</label> ';
		}

		foreach ($values as $value)
		{
			$checked = in_array((int) $value['value_id'], $selected, true) ? ' checked="checked"' : '';
			$buttons .= '<label><input type="radio" name="' . $name . '" value="' . (int) $value['value_id'] . '"' . $checked . '/> ' . htmlspecialchars($value['value_label']) . '</label> ';
		}

		return $this->wrap_posting_field($field, $buttons);
	}
}
