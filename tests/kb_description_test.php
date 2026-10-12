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

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';

class kb_description_test extends \PHPUnit\Framework\TestCase
{
	private function kb(array $config)
	{
		$language = $this->getMockBuilder(\phpbb\language\language::class)->disableOriginalConstructor()->getMock();
		$language->method('lang')->willReturnCallback(fn() => implode('|', func_get_args()));

		$kb = (new \ReflectionClass(functions_kb::class))->newInstanceWithoutConstructor();
		foreach (['config' => new \phpbb\config\config($config), 'language' => $language] as $name => $value)
		{
			$property = new \ReflectionProperty(functions_kb::class, $name);
			$property->setValue($kb, $value);
		}

		return $kb;
	}

	/** @dataProvider max_length_provider */
	public function test_the_length_limit_defaults_to_the_column_width_and_stays_within_it($config, int $expected)
	{
		$this->assertSame($expected, $this->kb($config)->get_description_max_length());
	}

	public function max_length_provider(): array
	{
		return [
			'not configured'   => [[], 255],
			'configured'       => [['kb_description_max_length' => 120], 120],
			'as a string'      => [['kb_description_max_length' => '60'], 60],
			'zero'             => [['kb_description_max_length' => 0], 255],
			'negative'         => [['kb_description_max_length' => -5], 1],
			'above the column' => [['kb_description_max_length' => 999], 255],
			'not a number'     => [['kb_description_max_length' => 'abc'], 255],
		];
	}

	public function test_a_description_within_the_limit_is_accepted()
	{
		$kb = $this->kb(['kb_description_max_length' => 10]);

		$this->assertSame('', $kb->get_description_error(''));
		$this->assertSame('', $kb->get_description_error('0123456789'));
	}

	public function test_a_description_over_the_limit_reports_its_length_and_the_limit()
	{
		$this->assertSame('KB_DESCRIPTION_TOO_LONG|11|10', $this->kb(['kb_description_max_length' => 10])->get_description_error('01234567890'));
	}

	public function test_length_counts_characters_not_bytes()
	{
		$kb = $this->kb(['kb_description_max_length' => 5]);

		$this->assertSame('', $kb->get_description_error('ščřžý'));
		$this->assertSame('KB_DESCRIPTION_TOO_LONG|6|5', $kb->get_description_error('ščřžýá'));
	}

	public function test_length_is_measured_on_the_text_the_author_typed_not_its_escaped_form()
	{
		// Typed as 5 characters ("&<>'\""), stored as "&amp;&lt;&gt;&#039;&quot;".
		$this->assertSame('', $this->kb(['kb_description_max_length' => 5])->get_description_error('&amp;&lt;&gt;&#039;&quot;'));
	}

	public function test_a_description_that_fits_the_limit_but_not_the_column_once_escaped_is_refused()
	{
		$typed = str_repeat('&', 100);

		$this->assertSame('KB_DESCRIPTION_TOO_MANY_SPECIAL', $this->kb([])->get_description_error(htmlspecialchars($typed)));
	}

	public function test_bbcode_smilies_and_links_are_put_into_a_post_as_literal_text()
	{
		$post = '<r><B><s>[b]</s>Description:<e>[/b]</e></B> {KB_DESCRIPTION}</r>';

		$this->assertSame(
			'<r><B><s>[b]</s>Description:<e>[/b]</e></B> [b]x[/b] :) [url=https://example.org]link[/url]</r>',
			functions_kb::insert_plain_text($post, ['{KB_DESCRIPTION}' => '[b]x[/b] :) [url=https://example.org]link[/url]'])
		);
	}

	public function test_stored_html_escapes_become_xml_escapes_in_the_post()
	{
		$escaped = htmlspecialchars('a < b & "c" <i>x</i>', ENT_COMPAT);

		$this->assertSame(
			'<r>a &lt; b &amp; "c" &lt;i&gt;x&lt;/i&gt;</r>',
			functions_kb::insert_plain_text('<r>{KB_DESCRIPTION}</r>', ['{KB_DESCRIPTION}' => $escaped])
		);
	}

	public function test_every_placeholder_is_replaced_and_inserted_text_is_never_searched_again()
	{
		$result = functions_kb::insert_plain_text('<r>{A} / {B}</r>', ['{A}' => '{B}', '{B}' => 'second']);

		$this->assertSame('<r>{B} / second</r>', $result);
		$this->assertSame('<r>kept</r>', functions_kb::insert_plain_text('<r>kept</r>', ['{A}' => 'x']));
	}

	public function test_the_result_is_well_formed_xml()
	{
		$escaped = htmlspecialchars('[b]x[/b] <script>alert(1)</script> & more', ENT_COMPAT);
		$xml = functions_kb::insert_plain_text('<r>{KB_DESCRIPTION}</r>', ['{KB_DESCRIPTION}' => $escaped]);

		$this->assertNotFalse(simplexml_load_string($xml));
		$this->assertSame('[b]x[/b] <script>alert(1)</script> & more', (string) simplexml_load_string($xml));
	}
}
