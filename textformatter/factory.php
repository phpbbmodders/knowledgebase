<?php

/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\textformatter;

/**
 * Text formatter used only for Markdown articles.
 *
 * It is the board's own s9e formatter with the Litedown (Markdown) and
 * PipeTables plugins added, kept as a separate instance with its own cache
 * keys so forum posts are never parsed as Markdown. Headings get a prefixed
 * id (see get_configurator()) so a table of contents can link to them.
 */
class factory extends \phpbb\textformatter\s9e\factory
{
	/** Prepended to every heading id, and to links that point at one. */
	public const HEADING_ID_PREFIX = 'kb-';

	/**
	 * Point a link that targets a fragment of the same page (a heading slug
	 * such as #basic-usage) at the prefixed heading id. Every other link is
	 * returned untouched.
	 *
	 * @param string $url The link's address, after the board's own checks
	 * @return string
	 */
	public static function prefix_fragment($url)
	{
		$prefix = '#' . self::HEADING_ID_PREFIX;

		if (is_string($url) && preg_match('/^#[a-z0-9][a-z0-9-]*$/D', $url) && strncmp($url, $prefix, strlen($prefix)) !== 0)
		{
			return $prefix . substr($url, 1);
		}

		return $url;
	}

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

		// phpBB turns every newline in a post into a line break. Markdown
		// paragraphs are usually written as wrapped lines, where a newline is
		// only a space; an intended break is still made with two trailing
		// spaces or a backslash, which Litedown handles itself.
		$configurator->rootRules->disableAutoLineBreaks();
		foreach ($configurator->tags as $tag)
		{
			$tag->rules->disableAutoLineBreaks();
		}

		// Headings get an id so an author's own table of contents, written as
		// [Basic usage](#basic-usage), has somewhere to jump to. The id is
		// prefixed so a heading called "Search" cannot take the id of an element
		// of the page around the article, and a link that points at a fragment
		// is given the same prefix.
		$configurator->Litedown->addHeadersId(self::HEADING_ID_PREFIX);
		$configurator->tags['URL']->attributes['url']->filterChain->append(__CLASS__ . '::prefix_fragment');

		return $configurator;
	}
}
