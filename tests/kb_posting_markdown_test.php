<?php
namespace phpbbmodders\knowledgebase\controller;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';
require_once dirname(__DIR__) . '/search/backend/kb_search_backend_interface.php';
require_once dirname(__DIR__) . '/search/kb_search_backend_factory.php';
require_once dirname(__DIR__) . '/textformatter/article_text.php';
require_once dirname(__DIR__) . '/controller/posting.php';

// The legacy BBCode decoder asks core for its HTML-to-BBCode patterns; none are needed here.
if (!function_exists(__NAMESPACE__ . '\get_preg_expression'))
{
	function get_preg_expression($mode)
	{
		return [];
	}
}

class kb_posting_markdown_test extends \PHPUnit\Framework\TestCase
{
	private $controller;
	private $article_text;

	protected function setUp(): void
	{
		$this->article_text = $this->getMockBuilder(\phpbbmodders\knowledgebase\textformatter\article_text::class)->disableOriginalConstructor()->getMock();
		$this->controller = (new \ReflectionClass(posting::class))->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty(posting::class, 'article_text');
		$property->setAccessible(true);
		$property->setValue($this->controller, $this->article_text);
		$language = $this->getMockBuilder(\phpbb\language\language::class)->disableOriginalConstructor()->getMock();
		$language->method('lang')->willReturnArgument(0);
		$property = new \ReflectionProperty(posting::class, 'language');
		$property->setAccessible(true);
		$property->setValue($this->controller, $language);
	}

	private function call(string $method, ...$args)
	{
		$reflection = new \ReflectionMethod(posting::class, $method);
		$reflection->setAccessible(true);

		return $reflection->invoke($this->controller, ...$args);
	}

	public function test_a_markdown_article_is_decoded_for_editing_by_the_markdown_formatter()
	{
		$this->article_text->expects($this->once())->method('for_edit')->with('<r>stored</r>')->willReturn('# source');

		$this->assertSame('# source', $this->call('decode_article_text', '<r>stored</r>', 'abc12345', true));
	}

	public function test_a_changelog_diff_decodes_each_side_with_its_own_format()
	{
		// Switching an article from BBCode to Markdown: only the new side is a Markdown article.
		$this->article_text->expects($this->once())->method('for_edit')->with('<r>new</r>')->willReturn('# new source');

		$message = $this->call('append_changelog_body_diff', 'edited', '', '', false, '<r>new</r>', 'uid', true);

		$this->assertStringContainsString('[quote="KB_CHANGELOG_NEW_TEXT"]# new source[/quote]', $message);
	}

	public function test_an_unchanged_markdown_body_adds_no_diff()
	{
		$this->article_text->method('for_edit')->willReturn('# same');

		$this->assertSame('edited', $this->call('append_changelog_body_diff', 'edited', '<r>a</r>', '', true, '<r>b</r>', '', true));
	}
}
