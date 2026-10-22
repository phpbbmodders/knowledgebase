<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @copyright (c) 2023, sutrus ( update to phpBB 3.3 )
 * @copyright (c) 2017, Sheer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase;

use phpbbmodders\knowledgebase\inc\upload_directory;

class ext extends \phpbb\extension\base
{
	/** Minimum phpBB version - 3.3.19 carries the security fixes from 3.3.17-3.3.19 */
	public const MIN_PHPBB_VERSION = '3.3.19';

	/** Minimum PHP version - 8.3.36 includes security fixes. */
	public const MIN_PHP_VERSION = '8.3.36';

	/** The original package this extension can take over from; it must be disabled first. */
	public const ORIGINAL_EXTENSION = 'sheer/knowledgebase';

	/**
	 * Check whether the extension can be enabled.
	 * The current phpBB version should meet or exceed
	 * the minimum version required by this extension.
	 *
	 * @return bool|array True if enableable, an array of error messages if not.
	 */
	public function is_enableable()
	{
		$messages = [];
		$language = $this->container->get('language');

		if (!$this->check_phpbb_version() || !$this->check_php_version())
		{
			$language->add_lang('install_failed', 'phpbbmodders/knowledgebase');
			$messages[] = $language->lang('INSTALL_FAILED_VERSION', self::MIN_PHPBB_VERSION, PHPBB_VERSION, self::MIN_PHP_VERSION, PHP_VERSION);
		}

		// The original sheer/knowledgebase package shares this extension's
		// tables and routes, so the two can never be enabled together. It
		// has to be disabled first; enabling this one then takes over its
		// data (see migration version_2_0_20).
		if ($this->container->get('ext.manager')->is_enabled(self::ORIGINAL_EXTENSION))
		{
			$language->add_lang('install_failed', 'phpbbmodders/knowledgebase');
			$messages[] = $language->lang('INSTALL_FAILED_ORIGINAL_ENABLED', self::ORIGINAL_EXTENSION);
		}

		return $messages ?: true;
	}

	/**
	 * Require phpBB 3.3.19 due to several security fixes in the phpBB software.
	 *
	 * @return bool
	 */
	public function check_phpbb_version()
	{
		return phpbb_version_compare(PHPBB_VERSION, '3.3.19', '>=');
	}

	/**
	 * Requires PHP 8.3.36 due to security fixes.
	 *
	 * @return bool
	 */
	public function check_php_version()
	{
		return PHP_VERSION_ID >= 80336;
	}

	/**
	 * Whether a PHP version is new enough for this extension.
	 *
	 * @param string $php_version A version string such as PHP_VERSION
	 * @return bool
	 */
	public static function php_version_meets_minimum(string $php_version): bool
	{
		return version_compare($php_version, self::MIN_PHP_VERSION, '>=');
	}

	/**
	 * Enable notifications for the extension
	 *
	 * @param mixed $old_state        The return value of the previous call
	 *                                of this method, or false on the first call
	 * @return    bool|string         Returns false after last step, otherwise
	 *                                temporary state which is passed as an
	 *                                argument to the next step
	 */
	function enable_step($old_state)
	{
		if ($old_state === false)
		{
			// Enable notifications
			$phpbb_notifications = $this->container->get('notification_manager');
			$phpbb_notifications->enable_notifications('phpbbmodders.knowledgebase.notification.type.need_approval');
			$phpbb_notifications->enable_notifications('phpbbmodders.knowledgebase.notification.type.approve');
			$phpbb_notifications->enable_notifications('phpbbmodders.knowledgebase.notification.type.disapprove');

			// The attachments folder, in the board's store folder, and any attachments still in the extension's own folder;
			// the Configuration page says so if the folder cannot be made
			upload_directory::ensure($this->container->getParameter('core.root_path'));
			upload_directory::move_legacy_files($this->container->getParameter('core.root_path'));

			return 'notifications';
		}

		return parent::enable_step($old_state);
	}

	/**
	 * Disable notifications for the extension
	 *
	 * @param mixed $old_state        The return value of the previous call
	 *                                of this method, or false on the first call
	 * @return    false|string        Returns false after last step, otherwise
	 *                                temporary state which is passed as an
	 *                                argument to the next step
	 */
	function disable_step($old_state)
	{
		if ($old_state === false)
		{
			// Disable notifications
			$phpbb_notifications = $this->container->get('notification_manager');
			$phpbb_notifications->disable_notifications('phpbbmodders.knowledgebase.notification.type.need_approval');
			$phpbb_notifications->disable_notifications('phpbbmodders.knowledgebase.notification.type.approve');
			$phpbb_notifications->disable_notifications('phpbbmodders.knowledgebase.notification.type.disapprove');

			return 'notifications';
		}

		return parent::disable_step($old_state);
	}

	/**
	 * Purge notifications for the extension
	 *
	 * @param mixed $old_state        The return value of the previous call
	 *                                of this method, or false on the first call
	 * @return    bool|string         Returns false after last step, otherwise
	 *                                temporary state which is passed as an
	 *                                argument to the next step
	 */
	function purge_step($old_state)
	{
		if ($old_state === false)
		{
			// Purge notifications
			$phpbb_notifications = $this->container->get('notification_manager');
			$phpbb_notifications->purge_notifications('phpbbmodders.knowledgebase.notification.type.need_approval');
			$phpbb_notifications->purge_notifications('phpbbmodders.knowledgebase.notification.type.approve');
			$phpbb_notifications->purge_notifications('phpbbmodders.knowledgebase.notification.type.disapprove');

			return 'notifications';
		}

		return parent::purge_step($old_state);
	}
}
