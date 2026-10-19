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

class version_2_0_3 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\phpbbmodders\knowledgebase\migrations\version_2_0_2',
		];
	}

	public function effectively_installed()
	{
		// kb_version stays in the config on purpose: it is this extension's
		// version marker, and this check reads it to skip a step the board
		// already has (see version_2_0_20 and docs/upgrading-from-original.md).
		return
			(isset($this->config['kb_version']) &&
				version_compare($this->config['kb_version'], '2.0.3', '>='));
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'kb_articles' => [
					'status'           => ['TINT:2', 0],
					'author_type'      => ['BOOL', 0],
					'assigned_team_id' => ['UINT', 0],
				],
			],
			'add_tables'  => [
				$this->table_prefix . 'kb_article_revisions' => [
					'COLUMNS'     => [
						'article_id'          => ['UINT', 0],
						'article_title'       => ['VCHAR:255', ''],
						'article_description' => ['VCHAR:255', ''],
						'article_body'        => ['MTEXT_UNI', ''],
						'bbcode_uid'          => ['VCHAR:10', ''],
						'bbcode_bitfield'     => ['VCHAR:32', ''],
						'submitted_by'        => ['UINT', 0],
						'submitted_date'      => ['UINT:11', 0],
					],
					'PRIMARY_KEY' => 'article_id',
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'kb_articles' => [
					'status',
					'author_type',
					'assigned_team_id',
				],
			],
			'drop_tables'  => [
				$this->table_prefix . 'kb_article_revisions',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['kb_version', '2.0.3']],
			['custom', [[$this, 'migrate_approved_to_status']]],
		];
	}

	public function migrate_approved_to_status()
	{
		// Status: 0 = New, 1 = Reviewed, 2 = Active, 3 = Inactive
		$sql = 'UPDATE ' . $this->table_prefix . 'kb_articles SET status = 2 WHERE approved = 1';
		$this->db->sql_query($sql);
	}
}
