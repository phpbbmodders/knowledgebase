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
 * The alternative stylesheets an administrator can switch on in the ACP.
 *
 * Two kinds exist. Bundled ones ship with the extension in
 * styles/all/theme/alternatives/ and load through phpBB's normal INCLUDECSS.
 * Custom ones are plain .css files an administrator copies into
 * store/phpbbmodders_knowledgebase/stylesheets/ on the board; that folder is
 * outside the extension, so they survive replacing the extension, and the
 * stylesheet controller serves them because store/ is not reachable by URL.
 *
 * A choice is stored as "bundled:<slug>" or "custom:<file name>". Everything
 * here treats a stored or submitted choice as untrusted: names must match a
 * strict pattern, resolve to a regular file directly inside the right folder,
 * and a symbolic link is never followed.
 */
class alternative_styles
{
	/** Prefix of a bundled choice, followed by the file name without ".css". */
	public const BUNDLED = 'bundled';

	/** Prefix of a custom choice, followed by the whole file name. */
	public const CUSTOM = 'custom';

	/** Where on the board custom stylesheets live, relative to the board root. */
	public const CUSTOM_DIRECTORY = 'store/phpbbmodders_knowledgebase/stylesheets/';

	/** Largest custom stylesheet that is listed or served, in bytes. */
	public const MAX_CUSTOM_BYTES = 262144;

	/** A custom file name: letters, digits, dot, dash and underscore, no "..", ending in .css. */
	private const CUSTOM_NAME_PATTERN = '/^(?!.*\.\.)[A-Za-z0-9][A-Za-z0-9._-]{0,59}\.css$/';

	/** A bundled slug: lower-case letters, digits and underscore. */
	private const BUNDLED_SLUG_PATTERN = '/^[a-z0-9_]{1,40}$/';

	/** @var \phpbb\config\config */
	protected $config;

	/** @var string Absolute path of the bundled stylesheets folder, with a trailing slash */
	protected $bundled_directory;

	/** @var string Absolute path of the custom stylesheets folder, with a trailing slash */
	protected $custom_directory;

	/**
	 * @param \phpbb\config\config $config
	 * @param string               $extension_path Absolute path of this extension, with a trailing slash
	 * @param string               $phpbb_root_path The board's root path, with a trailing slash
	 */
	public function __construct(\phpbb\config\config $config, string $extension_path, string $phpbb_root_path)
	{
		$this->config = $config;
		$this->bundled_directory = $extension_path . 'styles/all/theme/alternatives/';
		$this->custom_directory = $phpbb_root_path . self::CUSTOM_DIRECTORY;
	}

	/** The custom folder as an administrator would type it, relative to the board root. */
	public function custom_directory_label(): string
	{
		return self::CUSTOM_DIRECTORY;
	}

	/**
	 * The bundled alternatives found in the extension.
	 *
	 * @return string[] Slugs in alphabetical order
	 */
	public function bundled(): array
	{
		$slugs = [];
		foreach ($this->regular_css_files($this->bundled_directory) as $file)
		{
			$slug = substr($file, 0, -4);
			if (preg_match(self::BUNDLED_SLUG_PATTERN, $slug))
			{
				$slugs[] = $slug;
			}
		}
		sort($slugs);

		return $slugs;
	}

	/**
	 * The custom stylesheets found in the board's store folder.
	 *
	 * Symbolic links, sub-folders, oversized files and names outside the
	 * allowed pattern are left out.
	 *
	 * @return string[] File names in alphabetical order
	 */
	public function custom(): array
	{
		$names = [];
		foreach ($this->regular_css_files($this->custom_directory) as $file)
		{
			if ($this->custom_file_path($file) !== null)
			{
				$names[] = $file;
			}
		}
		sort($names);

		return $names;
	}

	/**
	 * Split a stored or submitted choice into its kind and name.
	 *
	 * @param string $choice "bundled:<slug>" or "custom:<file name>"
	 * @return array|null [kind, name], or null when the choice is not well formed
	 */
	public function parse_choice(string $choice): array|null
	{
		$parts = explode(':', $choice, 2);
		if (count($parts) !== 2)
		{
			return null;
		}

		[$kind, $name] = $parts;
		if ($kind === self::BUNDLED && preg_match(self::BUNDLED_SLUG_PATTERN, $name))
		{
			return [self::BUNDLED, $name];
		}
		if ($kind === self::CUSTOM && preg_match(self::CUSTOM_NAME_PATTERN, $name))
		{
			return [self::CUSTOM, $name];
		}

		return null;
	}

	/** Whether a choice is well formed and its file exists right now. */
	public function is_available(string $choice): bool
	{
		$parsed = $this->parse_choice($choice);
		if ($parsed === null)
		{
			return false;
		}

		return $parsed[0] === self::BUNDLED
			? in_array($parsed[1], $this->bundled(), true)
			: $this->custom_file_path($parsed[1]) !== null;
	}

	/**
	 * The alternative the board is using, if any.
	 *
	 * Null when alternative styling is off, nothing is chosen, or the chosen
	 * file has disappeared; the board then simply shows its own style.
	 *
	 * @return array|null [kind, name]
	 */
	public function active(): array|null
	{
		if (empty($this->config['kb_alt_style_enabled']))
		{
			return null;
		}

		$choice = (string) ($this->config['kb_alt_style'] ?? '');

		return $this->is_available($choice) ? $this->parse_choice($choice) : null;
	}

	/** The stored choice when it names a file that is no longer there, otherwise an empty string. */
	public function missing_choice(): string
	{
		$choice = (string) ($this->config['kb_alt_style'] ?? '');

		return ($choice !== '' && !$this->is_available($choice)) ? $choice : '';
	}

	/**
	 * Absolute path of a custom stylesheet, or null when it must not be served.
	 *
	 * The name must match the strict pattern and the entry must be a regular
	 * file (never a symbolic link) directly inside the custom folder, readable
	 * and no larger than the limit.
	 */
	public function custom_file_path(string $name): string|null
	{
		if (!preg_match(self::CUSTOM_NAME_PATTERN, $name))
		{
			return null;
		}

		$directory = realpath($this->custom_directory);
		if ($directory === false || is_link(rtrim($this->custom_directory, '/')))
		{
			return null;
		}

		$path = $this->custom_directory . $name;
		if (is_link($path) || !is_file($path) || !is_readable($path))
		{
			return null;
		}

		$real = realpath($path);
		if ($real === false || dirname($real) !== $directory)
		{
			return null;
		}

		$size = filesize($real);

		return ($size !== false && $size <= self::MAX_CUSTOM_BYTES) ? $real : null;
	}

	/**
	 * Names of the .css entries directly inside a folder, whatever they are.
	 *
	 * @return string[]
	 */
	protected function regular_css_files(string $directory): array
	{
		if (!is_dir($directory))
		{
			return [];
		}

		$files = [];
		foreach (new \DirectoryIterator($directory) as $entry)
		{
			if ($entry->isDot() || $entry->isLink() || !$entry->isFile() || $entry->getExtension() !== 'css')
			{
				continue;
			}
			$files[] = $entry->getFilename();
		}

		return $files;
	}
}
