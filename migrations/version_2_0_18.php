<?php

/**
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace phpbbmodders\knowledgebase\migrations;

class version_2_0_18 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_17'];
	}

	public function effectively_installed()
	{
		return isset($this->config['kb_description_max_length']);
	}

	/**
	 * The longest article description, in characters, that the posting form
	 * accepts. 255 is the width of the article_description column, so a
	 * board is unchanged unless an administrator lowers it.
	 */
	public function update_data()
	{
		return [
			['config.add', ['kb_description_max_length', 255]],
			['config.update', ['kb_version', '2.0.18']],
		];
	}
}
