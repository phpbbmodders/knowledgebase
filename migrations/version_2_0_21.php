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

class version_2_0_21 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_20'];
	}

	public function effectively_installed()
	{
		return isset($this->config['kb_classic_look']);
	}

	/**
	 * Whether to add the extension's original look (button-style tabs and
	 * striped article tables) on top of the board style's own. Off by
	 * default: the Knowledge Base now follows the board style.
	 */
	public function update_data()
	{
		return [
			['config.add', ['kb_classic_look', 0]],
			['config.update', ['kb_version', '2.0.21']],
		];
	}
}
