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
 * The recent changes feed, as RSS 2.0 or Atom 1.0.
 *
 * The ACP chooses which formats are offered. The documents are built with
 * DOMDocument, so every value is escaped by the XML library, not by hand.
 */
class feed
{
	public const RSS = 'rss';
	public const ATOM = 'atom';
	public const BOTH = 'both';

	/** Entries in a feed */
	public const LIMIT = 50;

	/**
	 * Whether a stored setting is one of the three choices.
	 *
	 * @param mixed $setting
	 * @return bool
	 */
	public static function is_valid_setting($setting): bool
	{
		return in_array($setting, [self::RSS, self::ATOM, self::BOTH], true);
	}

	/**
	 * Whether a feed format is offered under a setting.
	 *
	 * @param mixed  $setting RSS, ATOM or BOTH; anything else offers both
	 * @param string $format  RSS or ATOM
	 * @return bool
	 */
	public static function offers($setting, string $format): bool
	{
		if (!self::is_valid_setting($setting))
		{
			$setting = self::BOTH;
		}

		return $setting === self::BOTH || $setting === $format;
	}

	/**
	 * The feed document.
	 *
	 * @param string $format  RSS or ATOM
	 * @param array  $meta    title, description, link (the page), self (this feed's URL), updated (timestamp)
	 * @param array  $entries Each: id (permalink URL), title, link, summary, author, time (timestamp)
	 * @return string XML
	 */
	public static function build(string $format, array $meta, array $entries): string
	{
		return ($format === self::ATOM) ? self::atom($meta, $entries) : self::rss($meta, $entries);
	}

	/**
	 * @param array $meta
	 * @param array $entries
	 * @return string
	 */
	protected static function rss(array $meta, array $entries): string
	{
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->formatOutput = true;

		$rss = $dom->createElement('rss');
		$rss->setAttribute('version', '2.0');
		$rss->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
		$rss->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dc', 'http://purl.org/dc/elements/1.1/');
		$dom->appendChild($rss);

		$channel = $dom->createElement('channel');
		$rss->appendChild($channel);
		$channel->appendChild(self::node($dom, null, 'title', $meta['title']));
		$channel->appendChild(self::node($dom, null, 'link', $meta['link']));
		$channel->appendChild(self::node($dom, null, 'description', $meta['description']));
		$channel->appendChild($dom->createElement('lastBuildDate', gmdate('D, d M Y H:i:s', (int) $meta['updated']) . ' +0000'));

		$self = $dom->createElementNS('http://www.w3.org/2005/Atom', 'atom:link');
		$self->setAttribute('href', $meta['self']);
		$self->setAttribute('rel', 'self');
		$self->setAttribute('type', 'application/rss+xml');
		$channel->appendChild($self);

		foreach ($entries as $entry)
		{
			$item = $dom->createElement('item');
			$item->appendChild(self::node($dom, null, 'title', $entry['title']));
			$item->appendChild(self::node($dom, null, 'link', $entry['link']));
			$guid = self::node($dom, null, 'guid', $entry['id']);
			$guid->setAttribute('isPermaLink', 'true');
			$item->appendChild($guid);
			$item->appendChild($dom->createElement('pubDate', gmdate('D, d M Y H:i:s', (int) $entry['time']) . ' +0000'));
			$item->appendChild(self::node($dom, 'http://purl.org/dc/elements/1.1/', 'dc:creator', $entry['author']));
			$item->appendChild(self::node($dom, null, 'description', $entry['summary']));
			$channel->appendChild($item);
		}

		return $dom->saveXML();
	}

	/**
	 * @param array $meta
	 * @param array $entries
	 * @return string
	 */
	protected static function atom(array $meta, array $entries): string
	{
		$ns = 'http://www.w3.org/2005/Atom';
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->formatOutput = true;

		$feed = $dom->createElementNS($ns, 'feed');
		$dom->appendChild($feed);
		$feed->appendChild(self::node($dom, $ns, 'title', $meta['title']));
		$feed->appendChild(self::node($dom, $ns, 'subtitle', $meta['description']));
		$feed->appendChild(self::node($dom, $ns, 'id', $meta['self']));
		$feed->appendChild($dom->createElementNS($ns, 'updated', gmdate('Y-m-d\TH:i:s\Z', (int) $meta['updated'])));

		foreach (['alternate' => $meta['link'], 'self' => $meta['self']] as $rel => $href)
		{
			$link = $dom->createElementNS($ns, 'link');
			$link->setAttribute('rel', $rel);
			$link->setAttribute('href', $href);
			$feed->appendChild($link);
		}

		foreach ($entries as $entry)
		{
			$item = $dom->createElementNS($ns, 'entry');
			$item->appendChild(self::node($dom, $ns, 'title', $entry['title']));
			$link = $dom->createElementNS($ns, 'link');
			$link->setAttribute('href', $entry['link']);
			$item->appendChild($link);
			$item->appendChild(self::node($dom, $ns, 'id', $entry['id']));
			$item->appendChild($dom->createElementNS($ns, 'updated', gmdate('Y-m-d\TH:i:s\Z', (int) $entry['time'])));
			$author = $dom->createElementNS($ns, 'author');
			$author->appendChild(self::node($dom, $ns, 'name', $entry['author']));
			$item->appendChild($author);
			$summary = self::node($dom, $ns, 'summary', $entry['summary']);
			$summary->setAttribute('type', 'text');
			$item->appendChild($summary);
			$feed->appendChild($item);
		}

		return $dom->saveXML();
	}

	/**
	 * A value as XML text: the board stores titles and descriptions HTML-escaped, so they
	 * are decoded first and the XML library escapes them once.
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected static function text($value): string
	{
		return htmlspecialchars_decode((string) $value, ENT_QUOTES);
	}

	/**
	 * An element holding a text node, so the XML library escapes the value.
	 *
	 * @param \DOMDocument $dom
	 * @param string|null  $namespace Namespace URI, or null for none
	 * @param string       $name      Element name
	 * @param mixed        $value     Stored (HTML-escaped) text
	 * @return \DOMElement
	 */
	protected static function node(\DOMDocument $dom, string|null $namespace, string $name, $value): \DOMElement
	{
		$element = ($namespace === null) ? $dom->createElement($name) : $dom->createElementNS($namespace, $name);
		$element->appendChild($dom->createTextNode(self::text($value)));

		return $element;
	}
}
