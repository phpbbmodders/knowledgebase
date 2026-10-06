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

class version_2_0_15 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_14'];
	}

	public function update_schema()
	{
		$table = $this->table_prefix . 'kb_src_wrdmtch';

		// Replace the prefixed legacy index without deleting any search matches.
		return [
			'drop_keys' => [$table => ['un_mtch']],
			'add_unique_index' => [
				$table => ['un_mtch' => ['article_id', 'word_id', 'title_match', 'descr_match']],
			],
		];
	}

	public function update_data()
	{
		return [['config.update', ['kb_version', '2.0.15']]];
	}
}
