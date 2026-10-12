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

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Gives the templates KbIconClass('pencil', 'icon-blue'): the class attribute of
 * an icon that works on the running phpBB version (see the icon class).
 *
 * Used as <i class="{{ KbIconClass('pencil', 'icon-blue') }}" aria-hidden="true"></i>.
 */
class twig_icon extends AbstractExtension
{
	public function getName(): string
	{
		return 'phpbbmodders_knowledgebase_icon';
	}

	/** @return TwigFunction[] */
	public function getFunctions(): array
	{
		return [
			new TwigFunction('KbIconClass', [icon::class, 'classes']),
		];
	}
}
