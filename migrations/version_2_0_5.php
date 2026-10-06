<?php

/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\migrations;

class version_2_0_5 extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return [
			'\phpbbmodders\knowledgebase\migrations\version_2_0_4',
		];
	}

	public function effectively_installed()
	{
		// kb_version stays in the config on purpose: it is this extension's
		// version marker, and this check reads it to skip a step the board
		// already has (see version_2_0_20 and docs/upgrading-from-original.md).
		return
			(isset($this->config['kb_version']) &&
				version_compare($this->config['kb_version'], '2.0.5', '>='));
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'kb_fields' => [
					'COLUMNS'     => [
						'field_id'   => ['UINT', null, 'auto_increment'],
						'field_key'  => ['VCHAR:100', ''],
						'field_name' => ['VCHAR:255', ''],
						'field_type' => ['VCHAR:255', ''],
						'enabled'    => ['BOOL', 1],
					],
					'PRIMARY_KEY' => 'field_id',
					'KEYS'        => [
						'field_key' => ['UNIQUE', 'field_key'],
					],
				],
				$this->table_prefix . 'kb_field_values' => [
					'COLUMNS'     => [
						'value_id'      => ['UINT', null, 'auto_increment'],
						'field_id'      => ['UINT', 0],
						'value_label'   => ['VCHAR:255', ''],
						'enabled'       => ['BOOL', 1],
						'display_order' => ['UINT', 0],
					],
					'PRIMARY_KEY' => 'value_id',
					'KEYS'        => [
						'field_id' => ['INDEX', 'field_id'],
					],
				],
				$this->table_prefix . 'kb_article_field_values' => [
					'COLUMNS'     => [
						'article_id' => ['UINT', 0],
						'value_id'   => ['UINT', 0],
					],
					'PRIMARY_KEY' => ['article_id', 'value_id'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'kb_fields',
				$this->table_prefix . 'kb_field_values',
				$this->table_prefix . 'kb_article_field_values',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['kb_version', '2.0.5']],
			['custom', [[$this, 'seed_version_field']]],
			['module.add', ['acp', 'KNOWLEDGE_BASE', [
				'module_basename' => '\phpbbmodders\knowledgebase\acp\fields_module',
				'module_langname' => 'ACP_LIBRARY_FIELDS',
				'module_mode'     => 'fields',
				'module_auth'     => 'ext_phpbbmodders/knowledgebase && acl_a_board && acl_a_manage_kb',
			]]],
		];
	}

	public function seed_version_field()
	{
		$sql = 'INSERT INTO ' . $this->table_prefix . 'kb_fields ' . $this->db->sql_build_array('INSERT', [
			'field_key'  => 'phpbb_version',
			'field_name' => 'phpBB Version',
			'field_type' => 'phpbbmodders\\knowledgebase\\inc\\field\\kb_multi_select_field_type',
			'enabled'    => 1,
		]);
		$this->db->sql_query($sql);
		$field_id = $this->db->sql_nextid();

		$sql = 'INSERT INTO ' . $this->table_prefix . 'kb_field_values ' . $this->db->sql_build_array('INSERT', [
			'field_id'      => $field_id,
			'value_label'   => '3.3.x',
			'enabled'       => 1,
			'display_order' => 1,
		]);
		$this->db->sql_query($sql);
	}
}
