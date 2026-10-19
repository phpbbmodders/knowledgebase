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

class version_2_0_7 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\phpbbmodders\knowledgebase\migrations\version_2_0_6',
		];
	}

	public function effectively_installed()
	{
		// kb_version stays in the config on purpose: it is this extension's
		// version marker, and this check reads it to skip a step the board
		// already has (see version_2_0_20 and docs/upgrading-from-original.md).
		return
			(isset($this->config['kb_version']) &&
				version_compare($this->config['kb_version'], '2.0.7', '>='));
	}

	public function update_data()
	{
		return [
			['config.add', ['kb_flat_index', 0]],
			['config.update', ['kb_version', '2.0.7']],
		];
	}
}
