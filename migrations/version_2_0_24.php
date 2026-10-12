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
 * Splits the classic look into layout and colours.
 *
 * The layout now belongs to the flat article index setting (kb_flat_index,
 * unchanged). The colours become an optional alternative stylesheet picked in
 * the ACP: kb_alt_style_enabled switches it on and kb_alt_style remembers the
 * choice, also while it is off. The old kb_classic_look setting is removed.
 * It is deliberately not mapped to either new setting, so a board that had it
 * on shows its board style's own colours until an administrator picks Slate.
 *
 * kb_version stays: it is the version marker that earlier migrations read to
 * skip steps a board already has.
 */
class version_2_0_24 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_23'];
	}

	public function effectively_installed()
	{
		return isset($this->config['kb_alt_style_enabled']);
	}

	public function update_data()
	{
		return [
			['config.add', ['kb_alt_style_enabled', 0]],
			['config.add', ['kb_alt_style', 'bundled:slate']],
			['config.remove', ['kb_classic_look']],
			['config.update', ['kb_version', '2.0.24']],
		];
	}

	public function revert_data()
	{
		return [
			['config.add', ['kb_classic_look', 0]],
			['config.remove', ['kb_alt_style_enabled']],
			['config.remove', ['kb_alt_style']],
			['config.update', ['kb_version', '2.0.22']],
		];
	}
}
