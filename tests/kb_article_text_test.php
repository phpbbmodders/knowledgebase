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

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/textformatter/article_text.php';

if (!defined('OPTION_FLAG_BBCODE')) define('OPTION_FLAG_BBCODE', 1);
if (!defined('OPTION_FLAG_LINKS')) define('OPTION_FLAG_LINKS', 2);
if (!defined('OPTION_FLAG_SMILIES')) define('OPTION_FLAG_SMILIES', 4);

class kb_article_text_test extends \PHPUnit\Framework\TestCase
{
	private $parser;
	private $renderer;
	private $utils;
	private $article_text;

	protected function setUp(): void
	{
		$this->parser = $this->getMockBuilder(\phpbb\textformatter\s9e\parser::class)->disableOriginalConstructor()->getMock();
		$this->renderer = $this->getMockBuilder(\phpbb\textformatter\s9e\renderer::class)->disableOriginalConstructor()->getMock();
		$this->utils = $this->getMockBuilder(\phpbb\textformatter\s9e\utils::class)->disableOriginalConstructor()->getMock();

		$container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
		$container->method('get')->willReturnMap([
			['phpbbmodders.knowledgebase.text_formatter.parser', 1, $this->parser],
			['phpbbmodders.knowledgebase.text_formatter.renderer', 1, $this->renderer],
		]);
		$config = new \phpbb\config\config([
			'max_post_font_size' => 200, 'max_post_img_height' => 300, 'max_post_img_width' => 400,
			'max_post_smilies' => 5, 'max_post_urls' => 10,
		]);

		$this->article_text = new article_text($container, $config, $this->utils);
	}

	public function test_markdown_is_parsed_by_the_knowledge_base_formatter_after_undoing_request_escaping()
	{
		// Everything BBCode-ish stays switched on inside Markdown, with the board's limits.
		foreach (['enable_bbcodes', 'enable_magic_url', 'enable_smilies'] as $method)
		{
			$this->parser->expects($this->once())->method($method);
		}
		$this->parser->expects($this->exactly(4))->method('enable_bbcode');
		$this->parser->expects($this->once())->method('set_vars')->with([
			'max_font_size' => 200, 'max_img_height' => 300, 'max_img_width' => 400, 'max_smilies' => 5, 'max_urls' => 10,
		]);
		// The request layer hands over htmlspecialchars()'d text.
		$this->parser->expects($this->once())->method('parse')->with("5 > 3 & \"quoted\" <b>")->willReturn('<r>parsed</r>');

		$text = '5 &gt; 3 &amp; &quot;quoted&quot; &lt;b&gt;';
		$uid = $bitfield = $options = 'unset';
		$this->article_text->store($text, true, $uid, $bitfield, $options);

		$this->assertSame('<r>parsed</r>', $text);
		$this->assertSame('', $uid);
		$this->assertSame('', $bitfield);
		$this->assertSame(OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS, $options);
	}

	public function test_a_markdown_article_is_rendered_by_the_knowledge_base_renderer()
	{
		$this->renderer->method('get_viewcensors')->willReturn(true);
		$this->renderer->expects($this->never())->method('set_viewcensors');
		$this->renderer->expects($this->once())->method('render')->with('<r><H1>Title</H1></r>')->willReturn('<h1>Title</h1>');

		$this->assertSame('<h1>Title</h1>', $this->article_text->display('<r><H1>Title</H1></r>', '', true));
	}

	public function test_word_censoring_can_be_switched_off_and_is_put_back_afterwards()
	{
		$this->renderer->method('get_viewcensors')->willReturn(true);
		$calls = [];
		$this->renderer->method('set_viewcensors')->willReturnCallback(function ($value) use (&$calls) {
			$calls[] = $value;
		});
		$this->renderer->method('render')->willReturn('<p>x</p>');

		$this->article_text->display('<t>x</t>', '', true, false);
		$this->assertSame([false, true], $calls);
	}

	public function test_the_renderer_setting_is_restored_even_if_rendering_fails()
	{
		$this->renderer->method('get_viewcensors')->willReturn(true);
		$calls = [];
		$this->renderer->method('set_viewcensors')->willReturnCallback(function ($value) use (&$calls) {
			$calls[] = $value;
		});
		$this->renderer->method('render')->willThrowException(new \RuntimeException('render failed'));

		try
		{
			$this->article_text->display('<r>x</r>', '', true, false);
			$this->fail('The failure must not be swallowed');
		}
		catch (\RuntimeException $error)
		{
			$this->assertSame([false, true], $calls);
		}
	}

	public function test_editing_a_markdown_article_gives_back_the_source_escaped_for_a_textarea()
	{
		$this->utils->expects($this->once())->method('unparse')->with('<r><STRONG>x</STRONG></r>')->willReturn("**x** < y & \"z\"");

		$this->assertSame('**x** &lt; y &amp; &quot;z&quot;', $this->article_text->for_edit('<r><STRONG>x</STRONG></r>'));
	}

	public function test_text_that_is_not_stored_xml_is_only_escaped_for_editing()
	{
		$this->utils->expects($this->never())->method('unparse');

		$this->assertSame('a &lt; b', $this->article_text->for_edit('a < b'));
	}
}
