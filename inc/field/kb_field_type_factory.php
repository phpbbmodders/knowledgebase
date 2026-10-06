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

use phpbb\di\service_collection;

class kb_field_type_factory
{
	/** @var service_collection */
	protected service_collection $field_types;

	/**
	 * @param service_collection $field_types
	 */
	public function __construct(service_collection $field_types)
	{
		$this->field_types = $field_types;
	}

	/**
	 * @param string $class
	 * @return kb_field_type_interface
	 */
	public function get(string $class): kb_field_type_interface
	{
		return $this->field_types->get_by_class($class);
	}

	/**
	 * Every registered field type - class => display name, for the ACP
	 * "pick a type" dropdown when creating a new field.
	 *
	 * @return array<string, string>
	 */
	public function get_all(): array
	{
		$types = [];
		foreach ($this->field_types as $field_type)
		{
			/** @var kb_field_type_interface $field_type */
			$types[$field_type->get_type()] = $field_type->get_type_name();
		}
		return $types;
	}
}
