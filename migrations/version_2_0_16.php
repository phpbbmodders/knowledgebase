<?php

/**
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace phpbbmodders\knowledgebase\migrations;

class version_2_0_16 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_15'];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'kb_articles', 'article_markdown');
	}

	/**
	 * Markdown is chosen per article. The flag lives on the article and on
	 * both copies of its text (a pending revision and a draft of an edit),
	 * so a revision or draft keeps the format it was written in.
	 */
	public function update_schema()
	{
		$column = ['article_markdown' => ['BOOL', 0]];

		return [
			'add_columns' => [
				$this->table_prefix . 'kb_articles'           => $column,
				$this->table_prefix . 'kb_article_revisions'  => $column,
				$this->table_prefix . 'kb_article_draft_edits' => $column,
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'kb_articles'           => ['article_markdown'],
				$this->table_prefix . 'kb_article_revisions'  => ['article_markdown'],
				$this->table_prefix . 'kb_article_draft_edits' => ['article_markdown'],
			],
		];
	}

	public function update_data()
	{
		return [['config.update', ['kb_version', '2.0.16']]];
	}
}
