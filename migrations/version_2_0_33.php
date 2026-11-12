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

use phpbbmodders\knowledgebase\inc\default_content;
use phpbbmodders\knowledgebase\inc\intro;
use phpbbmodders\knowledgebase\inc\kinerity_import;

/**
 * Adds the Knowledge Base introduction text (see inc/intro.php), switched off
 * and holding the text kinerity/knowledgebase showed on its index, in the
 * board's default language (KB_INTRO_DEFAULT_TEXT in default_content.php). A board
 * that came from kinerity (version_2_0_32 imported it) has it switched on, so
 * visitors keep seeing that text.
 */
class version_2_0_33 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_32'];
	}

	public function effectively_installed()
	{
		return isset($this->config['kb_intro_enabled']);
	}

	public function update_data()
	{
		return [
			['config.add', ['kb_intro_enabled', 0]],
			['config.add', ['kb_intro_markdown', 0]],
			['config.add', ['kb_intro_uid', '']],
			['config_text.add', ['kb_intro_text', intro::stored_plain_text(default_content::lang((string) ($this->config['default_lang'] ?? 'en'), 'KB_INTRO_DEFAULT_TEXT'))]],
			['if', [
				['custom', [[$this, 'came_from_kinerity']]],
				['config.update', ['kb_intro_enabled', 1]],
			]],
			['config.update', ['kb_version', '2.0.33']],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['kb_version', '2.0.32']],
		];
	}

	/**
	 * @return bool Whether this board's data was imported from kinerity/knowledgebase
	 */
	public function came_from_kinerity()
	{
		return (new kinerity_import($this->db, $this->db_tools, $this->table_prefix))->has_backup();
	}
}
