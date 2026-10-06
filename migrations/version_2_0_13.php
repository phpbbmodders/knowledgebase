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

class version_2_0_13 extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return [
			'\phpbbmodders\knowledgebase\migrations\version_2_0_12',
		];
	}

	public function effectively_installed()
	{
		// kb_version stays in the config on purpose: it is this extension's
		// version marker, and this check reads it to skip a step the board
		// already has (see version_2_0_20 and docs/upgrading-from-original.md).
		return
			(isset($this->config['kb_version']) &&
				version_compare($this->config['kb_version'], '2.0.13', '>='));
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				// A private draft of a further edit to an already-submitted
				// article - entirely separate from kb_article_revisions
				// (a real, moderator-facing pending revision), which may
				// already exist for the same article at the same time.
				// Same shape as kb_article_revisions, minus the
				// submitted_by/submitted_date naming (this was never
				// "submitted" anywhere) and at most one row per article,
				// same as a pending revision.
				$this->table_prefix . 'kb_article_draft_edits' => [
					'COLUMNS'     => [
						'article_id'          => ['UINT', 0],
						'article_title'       => ['VCHAR:255', ''],
						'article_description' => ['VCHAR:255', ''],
						'article_body'        => ['MTEXT_UNI', ''],
						'bbcode_uid'          => ['VCHAR:10', ''],
						'bbcode_bitfield'     => ['VCHAR:32', ''],
						'redirect_url'        => ['VCHAR:255', ''],
						'edited_by'           => ['UINT', 0],
						'edited_date'         => ['UINT:11', 0],
					],
					'PRIMARY_KEY' => 'article_id',
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'kb_article_draft_edits',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['kb_version', '2.0.13']],
		];
	}
}
