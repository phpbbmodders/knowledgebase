<?php

/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase;

require_once dirname(__DIR__) . '/inc/feed.php';

use phpbbmodders\knowledgebase\inc\feed;

/**
 * The recent changes feed: which formats the setting offers, and the documents.
 */
class kb_feed_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider offers */
	public function test_the_setting_decides_which_formats_are_offered(string $setting, bool $rss, bool $atom)
	{
		$this->assertSame($rss, feed::offers($setting, feed::RSS), $setting . ' rss');
		$this->assertSame($atom, feed::offers($setting, feed::ATOM), $setting . ' atom');
	}

	public static function offers(): array
	{
		return [
			'both'      => ['both', true, true],
			'rss only'  => ['rss', true, false],
			'atom only' => ['atom', false, true],
			'unknown'   => ['json', true, true],
			'empty'     => ['', true, true],
		];
	}

	public function test_only_the_three_settings_are_valid()
	{
		foreach (['rss', 'atom', 'both'] as $setting)
		{
			$this->assertTrue(feed::is_valid_setting($setting), $setting);
		}

		foreach (['', 'RSS', 'json', 'rss,atom', null, 0] as $setting)
		{
			$this->assertFalse(feed::is_valid_setting($setting), var_export($setting, true));
		}
	}

	private function meta(): array
	{
		return [
			'title'       => 'My Board – Knowledge Base recent changes',
			'description' => 'The articles that changed most recently.',
			'link'        => 'https://example.org/knowledgebase/recent_changes',
			'self'        => 'https://example.org/knowledgebase/recent_changes.rss',
			'updated'     => 1760200000,
		];
	}

	/**
	 * Titles and descriptions are stored HTML-escaped; one of them has markup characters.
	 */
	private function entries(): array
	{
		return [
			['id' => 'https://example.org/knowledgebase/article?k=2', 'title' => 'Tom &amp; Jerry &lt;b&gt; “quoted” Übersicht', 'link' => 'https://example.org/knowledgebase/article?k=2', 'summary' => 'Updated in Basics. O&#039;Neil&#039;s &amp; more', 'author' => 'O&#039;Neil', 'time' => 1760200000],
			['id' => 'https://example.org/knowledgebase/article?k=1', 'title' => 'Plain', 'link' => 'https://example.org/knowledgebase/article?k=1', 'summary' => 'New in Basics.', 'author' => 'admin', 'time' => 1760100000],
		];
	}

	public function test_the_rss_document_is_well_formed_and_escapes_values_once()
	{
		$xml = feed::build(feed::RSS, $this->meta(), $this->entries());

		$dom = new \DOMDocument();
		$this->assertTrue($dom->loadXML($xml), 'RSS must parse');

		$rss = $dom->documentElement;
		$this->assertSame('rss', $rss->nodeName);
		$this->assertSame('2.0', $rss->getAttribute('version'));

		$items = $dom->getElementsByTagName('item');
		$this->assertSame(2, $items->length);
		$this->assertSame('Tom & Jerry <b> “quoted” Übersicht', $items->item(0)->getElementsByTagName('title')->item(0)->textContent);
		$this->assertSame("Updated in Basics. O'Neil's & more", $items->item(0)->getElementsByTagName('description')->item(0)->textContent);
		$this->assertSame('Sat, 11 Oct 2025 16:26:40 +0000', $items->item(0)->getElementsByTagName('pubDate')->item(0)->textContent);
		$this->assertSame('true', $items->item(0)->getElementsByTagName('guid')->item(0)->getAttribute('isPermaLink'));
		$this->assertSame('https://example.org/knowledgebase/recent_changes.rss', $dom->getElementsByTagNameNS('http://www.w3.org/2005/Atom', 'link')->item(0)->getAttribute('href'));
	}

	public function test_the_atom_document_is_well_formed_and_escapes_values_once()
	{
		$xml = feed::build(feed::ATOM, $this->meta(), $this->entries());

		$dom = new \DOMDocument();
		$this->assertTrue($dom->loadXML($xml), 'Atom must parse');

		$ns = 'http://www.w3.org/2005/Atom';
		$this->assertSame($ns, $dom->documentElement->namespaceURI);
		$this->assertSame('feed', $dom->documentElement->localName);
		$this->assertSame('2025-10-11T16:26:40Z', $dom->getElementsByTagNameNS($ns, 'updated')->item(0)->textContent);

		$entries = $dom->getElementsByTagNameNS($ns, 'entry');
		$this->assertSame(2, $entries->length);
		$this->assertSame('Tom & Jerry <b> “quoted” Übersicht', $entries->item(0)->getElementsByTagNameNS($ns, 'title')->item(0)->textContent);
		$this->assertSame("O'Neil", $entries->item(0)->getElementsByTagNameNS($ns, 'name')->item(0)->textContent);
		$this->assertSame('https://example.org/knowledgebase/article?k=2', $entries->item(0)->getElementsByTagNameNS($ns, 'id')->item(0)->textContent);
		$this->assertSame('text', $entries->item(0)->getElementsByTagNameNS($ns, 'summary')->item(0)->getAttribute('type'));
	}

	public function test_a_feed_with_no_entries_is_still_valid()
	{
		foreach ([feed::RSS, feed::ATOM] as $format)
		{
			$dom = new \DOMDocument();
			$this->assertTrue($dom->loadXML(feed::build($format, $this->meta(), [])), $format);
		}
	}
}
