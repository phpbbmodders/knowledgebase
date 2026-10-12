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
	'INSTALL_FAILED_VERSION' => '<b>The Knowledge Base extension does not meet the minimum requirements</b><br><br>phpBB minimum version %1s - you are using %2s <br>PHP minimum version %3s - you are using %4s',
	'INSTALL_FAILED_ORIGINAL_ENABLED' => '<b>The original %1$s extension is still enabled</b><br><br>It uses the same database tables and pages as this Knowledge Base, so both cannot be enabled together. Disable %1$s first (Customise &raquo; Manage extensions), then enable this extension: it takes over the articles, categories, permissions and settings the original created.',
]);
