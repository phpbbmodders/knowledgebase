<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\inc;

/**
 * Text from language/<lang>/default_content.php that migrations write into
 * the board as content, such as a category name. Migrations have no language
 * service, so the file is read directly, in the board's default language when
 * it has one, otherwise in English.
 */
class default_content
{
	/**
	 * @param string $language A language folder name, such as the board's default_lang
	 * @param string $key      A key in default_content.php
	 * @return string The text, or the key when no language file has it
	 */
	public static function lang(string $language, string $key): string
	{
		foreach (array_unique([preg_replace('/[^a-z0-9_\-]/i', '', $language), 'en']) as $folder)
		{
			$file = dirname(__DIR__) . '/language/' . $folder . '/default_content.php';
			if ($folder === '' || !is_file($file))
			{
				continue;
			}

			// The language file checks IN_PHPBB, which phpBB always defines
			$lang = [];
			include $file;
			if (isset($lang[$key]))
			{
				return $lang[$key];
			}
		}

		return $key;
	}
}
