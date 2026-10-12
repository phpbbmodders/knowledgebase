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
 * The CSS classes of a Font Awesome icon, for the phpBB version in use.
 *
 * phpBB 3.3.x draws an icon with <i class="icon fa-pencil fa-fw">. phpBB 4.0
 * moved to Font Awesome 6 and its own icon markup: the element needs
 * "o-icon o-icon-font" and a style class (fas, far), and an icon with no style
 * class is drawn blank. Outline icons also changed name (file-o became
 * far fa-file). This class returns the class list that works on the running
 * version, so a template or a piece of PHP says "pencil" once.
 *
 * 4.0 is recognised by the Icon() Twig function it adds, not by a version
 * number, so a 4.0 development build is handled the same as the release.
 */
class icon
{
	/**
	 * Outline icons that changed name in Font Awesome 6: old name => [style, new name].
	 * Only the icons the extension itself uses are listed; a name an administrator
	 * types into the settings is passed through unchanged.
	 */
	private const OUTLINE = [
		'file-o'      => ['far', 'file'],
		'file-text-o' => ['far', 'file-lines'],
		'folder-o'    => ['far', 'folder'],
		'thumbs-o-up' => ['far', 'thumbs-up'],
	];

	/** Whether the running phpBB draws icons the Font Awesome 6 way (phpBB 4.0). */
	public static function is_font_awesome_6(): bool
	{
		return class_exists(\phpbb\template\twig\extension\icon::class);
	}

	/**
	 * The class attribute value for an icon.
	 *
	 * @param string $name  Icon name without the "fa-" prefix, as on phpBB 3.3.x (e.g. "pencil", "file-o")
	 * @param string $extra Further classes, such as a colour ("icon-blue") or a size ("fa-2x")
	 * @return string
	 */
	public static function classes(string $name, string $extra = ''): string
	{
		$extra = $extra === '' ? '' : ' ' . $extra;

		if (!static::is_font_awesome_6())
		{
			return 'icon fa-' . $name . ' fa-fw' . $extra;
		}

		[$style, $name] = self::OUTLINE[$name] ?? ['fas', $name];

		return 'o-icon o-icon-font icon fa-fw fa-' . $name . ' ' . $style . $extra;
	}
}
