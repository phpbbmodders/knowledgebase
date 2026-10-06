<?php

/**
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace phpbbmodders\knowledgebase\migrations;

class version_2_0_19 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_18'];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'kb_fields', 'field_required');
	}

	/**
	 * A custom field can be marked required, so authors must select at
	 * least one of its values when they submit. Off for every existing
	 * field, so nothing changes on a board until an administrator turns it on.
	 */
	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'kb_fields' => [
					'field_required' => ['BOOL', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'kb_fields' => ['field_required'],
			],
		];
	}

	public function update_data()
	{
		return [['config.update', ['kb_version', '2.0.19']]];
	}
}
