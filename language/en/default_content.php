<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

/**
 * Text the extension writes into the board as content (category names and
 * the like), in the board's default language when it is set up. Read by
 * inc/default_content.php; editing it later does not change what was written.
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
	'KB_IMPORTED_NO_CATEGORY' => 'Imported articles without a category',
]);
