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

/**
 * The settings for showing Knowledge Base matches next to the results of the board's own search:
 * kb_search_board switches the block on or off, kb_search_board_max is how many articles it lists.
 *
 * kb_version stays: it is the version marker that earlier migrations read to
 * skip steps a board already has.
 */
class version_2_0_27 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_26'];
	}

	public function effectively_installed()
	{
		return isset($this->config['kb_search_board']);
	}

	public function update_data()
	{
		return [
			['config.add', ['kb_search_board', 1]],
			['config.add', ['kb_search_board_max', 5]],
			['config.update', ['kb_version', '2.0.27']],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['kb_search_board']],
			['config.remove', ['kb_search_board_max']],
			['config.update', ['kb_version', '2.0.26']],
		];
	}
}
