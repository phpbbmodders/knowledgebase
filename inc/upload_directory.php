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
 * Where the Knowledge Base keeps attachments and the uploads that are still in progress:
 * store/phpbbmodders_knowledgebase/files/ in the board's own folder, not inside the
 * extension. phpBB already needs its store folder to be writable by the web server on every
 * board, and the files survive replacing the extension's folder when it is upgraded.
 */
class upload_directory
{
	/** Folder of the attachments, below the board's root folder. */
	public const FOLDER = 'store/phpbbmodders_knowledgebase/files/';

	/** Where the attachments were kept before, below the board's root folder. */
	public const LEGACY_FOLDER = 'ext/phpbbmodders/knowledgebase/files/';

	/** Files in a folder that are not attachments and stay where they are. */
	private const PROTECTION_FILES = ['.htaccess', 'index.htm'];

	/**
	 * @param string $phpbb_root_path The board's root folder, with a trailing slash
	 * @return string The attachments folder, with a trailing slash
	 */
	public static function path(string $phpbb_root_path): string
	{
		return $phpbb_root_path . self::FOLDER;
	}

	/**
	 * @param string $phpbb_root_path The board's root folder, with a trailing slash
	 * @return string The folder the attachments were kept in before, with a trailing slash
	 */
	public static function legacy_path(string $phpbb_root_path): string
	{
		return $phpbb_root_path . self::LEGACY_FOLDER;
	}

	/**
	 * Make sure the attachments folder and its plupload folder exist and can be written, with
	 * the files that keep a web server from listing or serving them. Opens a folder up only when
	 * it is not writable already, and says nothing when it cannot.
	 *
	 * @param string $phpbb_root_path The board's root folder, with a trailing slash
	 * @return bool Whether both folders exist and can be written to
	 */
	public static function ensure(string $phpbb_root_path): bool
	{
		$writable = true;
		foreach ([self::path($phpbb_root_path), self::path($phpbb_root_path) . 'plupload/'] as $directory)
		{
			if (!is_dir($directory))
			{
				@mkdir($directory, 0777, true);
			}
			if (is_dir($directory) && !is_writable($directory))
			{
				@chmod($directory, 0777);
				clearstatcache(true, $directory);
			}
			if (!is_dir($directory) || !is_writable($directory))
			{
				$writable = false;
				continue;
			}
			foreach (self::PROTECTION_FILES as $name)
			{
				if (!file_exists($directory . $name))
				{
					@file_put_contents($directory . $name, self::protection_file($name));
				}
			}
		}

		return $writable;
	}

	/**
	 * Move the attachments kept in the extension's own folder into the attachments folder.
	 * A file is never written over one that is already there, and one that cannot be moved
	 * stays where it is.
	 *
	 * @param string $phpbb_root_path The board's root folder, with a trailing slash
	 * @return int[] How many files were moved and how many were left: [moved, left]
	 */
	public static function move_legacy_files(string $phpbb_root_path): array
	{
		$moved = $left = 0;
		$source = self::legacy_path($phpbb_root_path);
		if (!is_dir($source) || !self::ensure($phpbb_root_path))
		{
			return [0, is_dir($source) ? self::count_attachments($source) : 0];
		}

		$target = self::path($phpbb_root_path);
		foreach (scandir($source) as $name)
		{
			if ($name === '.' || $name === '..' || $name === 'plupload' || in_array($name, self::PROTECTION_FILES, true) || !is_file($source . $name))
			{
				continue;
			}
			if (file_exists($target . $name))
			{
				$left++;
			}
			else if (@rename($source . $name, $target . $name) || (@copy($source . $name, $target . $name) && @unlink($source . $name)))
			{
				$moved++;
			}
			else
			{
				$left++;
			}
		}

		return [$moved, $left];
	}

	/**
	 * @param string $directory
	 * @return int How many attachment files are in the folder
	 */
	private static function count_attachments(string $directory): int
	{
		$count = 0;
		foreach (scandir($directory) as $name)
		{
			if ($name !== '.' && $name !== '..' && $name !== 'plupload' && !in_array($name, self::PROTECTION_FILES, true) && is_file($directory . $name))
			{
				$count++;
			}
		}

		return $count;
	}

	/**
	 * @param string $name One of PROTECTION_FILES
	 * @return string What the file holds
	 */
	private static function protection_file(string $name): string
	{
		if ($name === '.htaccess')
		{
			return "# Apache 2.2\n<IfModule !mod_authz_core.c>\n\tOrder Allow,Deny\n\tDeny from All\n</IfModule>\n\n# Apache 2.4+\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n";
		}

		return "<html>\n<head>\n\t<title></title>\n\t<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">\n</head>\n\n<body bgcolor=\"#FFFFFF\" text=\"#000000\">\n\n</body>\n</html>\n";
	}
}
