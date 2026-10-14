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
 * Makes the flat article index the default and turns its setting round.
 *
 * kb_hierarchical_index replaces kb_flat_index: it is off by default, which gives the
 * flat index (one dropdown of every category and one list of every article), and
 * switching it on brings back the category tree. The old kb_flat_index setting is removed
 * and deliberately not mapped, so a board that had it off (the tree) shows the flat index
 * until an administrator switches hierarchical browsing on.
 *
 * kb_version stays: it is the version marker that earlier migrations read to
 * skip steps a board already has.
 */
class version_2_0_26 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_25'];
	}

	public function effectively_installed()
	{
		return isset($this->config['kb_hierarchical_index']);
	}

	public function update_data()
	{
		return [
			['config.add', ['kb_hierarchical_index', 0]],
			['config.remove', ['kb_flat_index']],
			['config.update', ['kb_version', '2.0.26']],
		];
	}

	public function revert_data()
	{
		return [
			['config.add', ['kb_flat_index', 0]],
			['config.remove', ['kb_hierarchical_index']],
			['config.update', ['kb_version', '2.0.25']],
		];
	}
}
