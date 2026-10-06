<?php

/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\migrations;

class version_2_0_4 extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return [
			'\phpbbmodders\knowledgebase\migrations\version_2_0_3',
		];
	}

	public function effectively_installed()
	{
		// kb_version stays in the config on purpose: it is this extension's
		// version marker, and this check reads it to skip a step the board
		// already has (see version_2_0_20 and docs/upgrading-from-original.md).
		return
			(isset($this->config['kb_version']) &&
				version_compare($this->config['kb_version'], '2.0.4', '>='));
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'kb_article_categories' => [
					'COLUMNS'     => [
						'article_id'  => ['UINT', 0],
						'category_id' => ['UINT', 0],
					],
					'PRIMARY_KEY' => ['article_id', 'category_id'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'kb_article_categories',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['kb_version', '2.0.4']],
		];
	}
}
