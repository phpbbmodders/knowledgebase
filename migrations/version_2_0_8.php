<?php

/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\migrations;

class version_2_0_8 extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return [
			'\phpbbmodders\knowledgebase\migrations\version_2_0_7',
		];
	}

	public function effectively_installed()
	{
		return
			(isset($this->config['kb_version']) &&
				version_compare($this->config['kb_version'], '2.0.8', '>='));
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'kb_categories' => [
					'default_team_id' => ['UINT', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'kb_categories' => [
					'default_team_id',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['kb_version', '2.0.8']],
		];
	}
}
