<?php

/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\migrations;

class version_2_0_6 extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return [
			'\phpbbmodders\knowledgebase\migrations\version_2_0_5',
		];
	}

	public function effectively_installed()
	{
		// kb_version stays in the config on purpose: it is this extension's
		// version marker, and this check reads it to skip a step the board
		// already has (see version_2_0_20 and docs/upgrading-from-original.md).
		return
			(isset($this->config['kb_version']) &&
				version_compare($this->config['kb_version'], '2.0.6', '>='));
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'kb_articles'          => [
					'is_redirect'  => ['BOOL', 0],
					'redirect_url' => ['VCHAR:255', ''],
				],
				$this->table_prefix . 'kb_article_revisions' => [
					'redirect_url' => ['VCHAR:255', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'kb_articles'          => [
					'is_redirect',
					'redirect_url',
				],
				$this->table_prefix . 'kb_article_revisions' => [
					'redirect_url',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['kb_version', '2.0.6']],
		];
	}
}
