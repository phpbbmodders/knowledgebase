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

class version_2_0_23 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_22'];
	}

	public function update_data()
	{
		// Defaults now belong to the PM method; preserve all saved UCP choices.
		return [
			['config.remove', ['kb_require_author_pm']],
		];
	}

	public function revert_data()
	{
		return [
			['config.add', ['kb_require_author_pm', 0]],
		];
	}
}
