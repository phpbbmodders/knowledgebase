<?php

/**
 *
 * @package       phpBB Extension - Knowledge Base
 * @copyright (c) 2022 phpBB Limited <https://www.phpbb.com>
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */
/**
 * DO NOT CHANGE
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'KB_NOT_ENABLEABLE' => '<b>The Knowledge Base extension does not meet the minimum requirements</b><br><br>phpBB minimum version %1$s - you are using %2$s <br>PHP minimum version %3$s - you are using %4$s',
	'INSTALL_FAILED_ORIGINAL_ENABLED' => '<b>The original %1$s extension is still enabled</b><br><br>It uses the same database tables and pages as this Knowledge Base, so both cannot be enabled together. Disable %1$s first (Customise &raquo; Manage extensions), then enable this extension: it takes over the articles, categories, permissions and settings the original created.',
	'INSTALL_FAILED_KINERITY_ENABLED' => '<b>The %1$s extension is still enabled</b><br><br>It uses the same database table names and pages as this Knowledge Base, so both cannot be enabled together. Disable %1$s first (Customise &raquo; Manage extensions), without deleting its data, then enable this extension: it imports the categories, articles and permissions %1$s created.',
]);
