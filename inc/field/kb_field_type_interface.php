<?php

/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\inc\field;

interface kb_field_type_interface
{
	/**
	 * Display name shown in the ACP "type" dropdown when creating a field.
	 *
	 * @return string
	 */
	public function get_type_name(): string;

	/**
	 * ACP HTML for managing this field's list of possible values
	 * (add/remove/toggle) - the ACP form posts back to the same field's
	 * value-editor action regardless of type.
	 *
	 * @param array $field  the kb_fields row
	 * @param array $values every value row for this field (kb_field_values)
	 * @return string
	 */
	public function render_value_editor(array $field, array $values): string;

	/**
	 * Posting-form HTML for assigning this field's value(s) to an article.
	 *
	 * @param array $field             the kb_fields row
	 * @param array $values            every *enabled* value row for this field
	 * @param int[] $selected_value_ids value_ids currently assigned to the article being posted/edited
	 * @return string
	 */
	public function render_posting_field(array $field, array $values, array $selected_value_ids): string;

	/**
	 * The value ids to keep from what the posting form submitted. A type that
	 * allows one value returns at most one; a type that allows several returns
	 * them all. Called both when checking a required field and when saving, so
	 * a tampered request cannot store more than the type allows.
	 *
	 * @param int[] $value_ids value_ids submitted for this field
	 * @return int[]
	 */
	public function normalize_submitted_ids(array $value_ids): array;

	/**
	 * Article-page HTML showing this field's assigned values.
	 *
	 * @param array $field            the kb_fields row
	 * @param array $selected_values  the value rows assigned to the article
	 * @return string
	 */
	public function render_display(array $field, array $selected_values): string;

	/**
	 * The FQCN identifying this type, stored in kb_fields.field_type and
	 * used to resolve it back via the service_collection - same role as
	 * kb_search_backend_interface::get_type().
	 *
	 * @return string
	 */
	public function get_type(): string;
}
