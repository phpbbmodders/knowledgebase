<?php

/**
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace phpbbmodders\knowledgebase\migrations;

class version_2_0_17 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_16'];
	}

	public function effectively_installed()
	{
		return isset($this->config['kb_contact_group']);
	}

	/**
	 * The usergroup authors are told to contact, on "Manage Your Articles",
	 * with questions about their articles. 0 means none, so nothing changes
	 * for an existing board until an administrator picks a group.
	 */
	public function update_data()
	{
		return [
			['config.add', ['kb_contact_group', 0]],
			['config.update', ['kb_version', '2.0.17']],
		];
	}
}
