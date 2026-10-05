<?php

/**
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace phpbbmodders\knowledgebase\textformatter;

/**
 * Text formatter used only for Markdown articles.
 *
 * It is the board's own s9e formatter with the Litedown (Markdown) and
 * PipeTables plugins added, kept as a separate instance with its own cache
 * keys so forum posts are never parsed as Markdown. Header ids are left off:
 * an article heading such as "Search" would otherwise get id="search" and
 * collide with elements of the page around it.
 */
class factory extends \phpbb\textformatter\s9e\factory
{
	/**
	 * {@inheritdoc}
	 */
	public function get_configurator()
	{
		$configurator = parent::get_configurator();

		if (!isset($configurator->Litedown))
		{
			$configurator->Litedown;
		}

		if (!isset($configurator->PipeTables))
		{
			$configurator->PipeTables;
		}

		return $configurator;
	}
}
