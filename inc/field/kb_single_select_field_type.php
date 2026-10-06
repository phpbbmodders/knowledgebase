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

/**
 * Pick one value from an admin-managed list, shown as a dropdown. The values,
 * their ACP editor, how they are stored and how they show on the article page
 * are the same as for the checkbox list; only the posting widget and the
 * "at most one" rule differ.
 *
 * A yes/no field needs no type of its own: make one of these with the two
 * values "Yes" and "No".
 */
class kb_single_select_field_type extends kb_multi_select_field_type
{
	/**
	 * @return string
	 */
	public function get_type_name(): string
	{
		return $this->language->lang('KB_FIELD_TYPE_SINGLE_DROPDOWN');
	}

	/**
	 * At most one value: the first real one submitted. The placeholder entry
	 * submits 0, which is skipped, so it can never hide a real choice that
	 * arrived after it.
	 *
	 * @param int[] $value_ids
	 * @return int[]
	 */
	public function normalize_submitted_ids(array $value_ids): array
	{
		return array_slice(array_values(array_filter(array_map('intval', $value_ids))), 0, 1);
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
		$options = '<option value="0">' . $this->language->lang('KB_FIELD_SELECT_PLACEHOLDER') . '</option>';
		foreach ($values as $value)
		{
			$is_selected = in_array((int) $value['value_id'], $selected, true) ? ' selected="selected"' : '';
			$options .= '<option value="' . (int) $value['value_id'] . '"' . $is_selected . '>' . htmlspecialchars($value['value_label']) . '</option>';
		}

		return $this->wrap_posting_field($field, '<select name="field_values[' . (int) $field['field_id'] . '][]">' . $options . '</select>');
	}

	/**
	 * The label (with the "please select" hint when the field is required)
	 * around the widget.
	 *
	 * @param array  $field
	 * @param string $widget
	 * @return string
	 */
	protected function wrap_posting_field(array $field, string $widget): string
	{
		$hint = !empty($field['field_required']) ? '<br><span>' . $this->language->lang('KB_FIELD_REQUIRED_HINT') . '</span>' : '';

		return '<dl style="clear: left;">
			<dt><label>' . htmlspecialchars($field['field_name']) . $this->language->lang('COLON') . '</label>' . $hint . '</dt>
			<dd>' . $widget . '</dd>
		</dl>';
	}
}
