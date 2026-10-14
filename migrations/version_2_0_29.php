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
 * Adds the setting that says which recent changes feed formats are offered:
 * kb_feed_format is "rss", "atom" or "both".
 *
 * kb_version stays: it is the version marker that earlier migrations read to
 * skip steps a board already has.
 */
class version_2_0_29 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_28'];
	}

	public function effectively_installed()
	{
		return isset($this->config['kb_feed_format']);
	}

	public function update_data()
	{
		return [
			['config.add', ['kb_feed_format', 'both']],
			['config.update', ['kb_version', '2.0.29']],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['kb_feed_format']],
			['config.update', ['kb_version', '2.0.28']],
		];
	}
}
