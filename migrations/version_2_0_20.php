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

class version_2_0_20 extends \phpbb\db\migration\migration
{
	protected const OLD_NAMESPACE = 'sheer\\knowledgebase\\';
	protected const NEW_NAMESPACE = 'phpbbmodders\\knowledgebase\\';
	protected const OLD_EXTENSION = 'sheer/knowledgebase';
	protected const NEW_EXTENSION = 'phpbbmodders/knowledgebase';
	protected const OLD_NOTIFICATION_PREFIX = 'sheer.knowledgebase.notification.type.';
	protected const NEW_NOTIFICATION_PREFIX = 'phpbbmodders.knowledgebase.notification.type.';

	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_19'];
	}

	/**
	 * Take over a board that ran the original sheer/knowledgebase package.
	 *
	 * The original and this extension share their tables and settings (the
	 * earlier migrations are skipped on such a board), but the original
	 * registered everything it owns under its own name. This renames those
	 * registrations so they point at this extension, and copies the uploaded
	 * files that lived inside the original's folder. On a board that never ran
	 * the original there is nothing to rename and it changes nothing.
	 */
	public function update_data()
	{
		return [
			['custom', [[$this, 'adopt_original_package']]],
			['config.update', ['kb_version', '2.0.20']],
		];
	}

	public function adopt_original_package()
	{
		// Files first: if one cannot be copied the migration stops before
		// anything in the database has been renamed.
		$this->copy_original_files();
		$this->rename_acp_modules();
		$this->rename_search_backend();
		$this->rename_notification_types();
	}

	/**
	 * Copy the attachments the original stored in its own extension folder
	 * into this extension's attachments folder (store/phpbbmodders_knowledgebase/files/),
	 * when the original's folder is still there. Files already present are left alone.
	 *
	 * @throws \phpbb\db\migration\exception When a file cannot be copied
	 */
	protected function copy_original_files()
	{
		$source = $this->phpbb_root_path . 'ext/' . self::OLD_EXTENSION . '/files/';
		$target = upload_directory::path($this->phpbb_root_path);

		if (!is_dir($source) || realpath($source) === realpath($target))
		{
			return;
		}

		if (!is_dir($target))
		{
			upload_directory::ensure($this->phpbb_root_path);
		}
		if (!is_dir($target))
		{
			throw new \phpbb\db\migration\exception('The Knowledge Base could not create ' . $target . ' to copy the original extension\'s attachments into. Create it or fix its permissions, then enable the extension again.');
		}

		$failed = [];
		foreach (scandir($source) as $file)
		{
			if ($file === '.' || $file === '..' || !is_file($source . $file) || file_exists($target . $file))
			{
				continue;
			}

			if (!@copy($source . $file, $target . $file))
			{
				$failed[] = $file;
			}
		}

		if ($failed)
		{
			throw new \phpbb\db\migration\exception('The Knowledge Base could not copy these attachments from ' . $source . ' to ' . $target . ': ' . implode(', ', array_slice($failed, 0, 10)) . (count($failed) > 10 ? ', ...' : '') . '. Fix the permissions, then enable the extension again.');
		}
	}

	/**
	 * Point the ACP modules the original registered at this extension's
	 * module classes, and its permission string at this extension.
	 */
	protected function rename_acp_modules()
	{
		$sql = 'SELECT module_id, module_basename, module_auth
			FROM ' . $this->table_prefix . 'modules
			WHERE ' . $this->db->sql_in_set('module_class', ['acp']);
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		foreach ($rows as $row)
		{
			$basename = $row['module_basename'];
			$auth = $row['module_auth'];

			$new_basename = str_starts_with(ltrim($basename, '\\'), self::OLD_NAMESPACE)
				? str_replace(self::OLD_NAMESPACE, self::NEW_NAMESPACE, $basename)
				: $basename;
			$new_auth = str_replace('ext_' . self::OLD_EXTENSION, 'ext_' . self::NEW_EXTENSION, $auth);

			if ($new_basename === $basename && $new_auth === $auth)
			{
				continue;
			}

			$sql = 'UPDATE ' . $this->table_prefix . 'modules
				SET ' . $this->db->sql_build_array('UPDATE', ['module_basename' => $new_basename, 'module_auth' => $new_auth]) . '
				WHERE module_id = ' . (int) $row['module_id'];
			$this->db->sql_query($sql);
		}
	}

	/**
	 * The search backend is stored as a full class name.
	 */
	protected function rename_search_backend()
	{
		$backend = (string) ($this->config['kb_search_type'] ?? '');

		if (str_starts_with(ltrim($backend, '\\'), self::OLD_NAMESPACE))
		{
			$this->config->set('kb_search_type', str_replace(self::OLD_NAMESPACE, self::NEW_NAMESPACE, $backend));
		}
	}

	/**
	 * The original's notification types, and the notification settings users
	 * saved for them, move to this extension's type names. phpBB only creates
	 * a type's row the first time it sends one, so a board that never sent
	 * any has nothing to rename. Notifications already sent refer to their
	 * type by id and follow the rename.
	 */
	protected function rename_notification_types()
	{
		foreach (['need_approval', 'approve', 'disapprove'] as $type)
		{
			$old = self::OLD_NOTIFICATION_PREFIX . $type;
			$new = self::NEW_NOTIFICATION_PREFIX . $type;

			$sql = 'SELECT notification_type_id
				FROM ' . $this->table_prefix . "notification_types
				WHERE notification_type_name = '" . $this->db->sql_escape($new) . "'";
			$result = $this->db->sql_query($sql);
			$already_renamed = (bool) $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if ($already_renamed)
			{
				continue;
			}

			// Enabling this extension turns its notification types on, but that
			// runs before this migration, when the type still has the old name,
			// and the original switched its types off when it was disabled.
			$sql = 'UPDATE ' . $this->table_prefix . "notification_types
				SET notification_type_name = '" . $this->db->sql_escape($new) . "',
					notification_type_enabled = 1
				WHERE notification_type_name = '" . $this->db->sql_escape($old) . "'";
			$this->db->sql_query($sql);

			$sql = 'UPDATE ' . $this->table_prefix . "user_notifications
				SET item_type = '" . $this->db->sql_escape($new) . "'
				WHERE item_type = '" . $this->db->sql_escape($old) . "'";
			$this->db->sql_query($sql);
		}
	}
}
