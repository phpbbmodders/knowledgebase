<?php

/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\migrations;

class version_2_0_10 extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return [
			'\phpbbmodders\knowledgebase\migrations\version_2_0_9',
		];
	}

	public function effectively_installed()
	{
		return
			(isset($this->config['kb_version']) &&
				version_compare($this->config['kb_version'], '2.0.10', '>='));
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				// Everyone credited on an article besides its primary
				// author_id/author_type (kb_articles) - author_type is part
				// of the key since a user and a group can share the same
				// numeric id. Mirrors kb_article_categories' shape exactly
				// (primary category on the article row, a join table for
				// the rest).
				$this->table_prefix . 'kb_article_co_authors' => [
					'COLUMNS'     => [
						'article_id'    => ['UINT', 0],
						'author_type'   => ['BOOL', 0],
						'author_ref_id' => ['UINT', 0],
					],
					'PRIMARY_KEY' => ['article_id', 'author_type', 'author_ref_id'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'kb_article_co_authors',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['kb_version', '2.0.10']],
		];
	}
}
