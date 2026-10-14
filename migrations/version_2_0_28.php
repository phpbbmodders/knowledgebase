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

use phpbbmodders\knowledgebase\inc\upload_directory;

/**
 * Moves the attachments out of the extension's own folder into store/phpbbmodders_knowledgebase/files/.
 *
 * phpBB needs its store folder to be writable by the web server on every board, so the extension
 * can make its folders there when it is enabled, which it often cannot inside its own folder (the
 * web server's user usually does not own that), and replacing the extension's folder when upgrading no
 * longer removes the attachments. A file is never written over one that is already in the new folder, and
 * one that cannot be moved stays where it is; the Configuration page says when the new folder cannot be written.
 *
 * kb_version stays: it is the version marker that earlier migrations read to
 * skip steps a board already has.
 */
class version_2_0_28 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_27'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'move_attachments']]],
			['config.update', ['kb_version', '2.0.28']],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['kb_version', '2.0.27']],
		];
	}

	/**
	 * @return void
	 */
	public function move_attachments()
	{
		upload_directory::ensure($this->phpbb_root_path);
		upload_directory::move_legacy_files($this->phpbb_root_path);
	}
}
