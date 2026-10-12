<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\controller;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';
require_once dirname(__DIR__) . '/search/backend/kb_search_backend_interface.php';
require_once dirname(__DIR__) . '/search/kb_search_backend_factory.php';

if (!function_exists(__NAMESPACE__ . '\build_hidden_fields'))
{
	function build_hidden_fields($fields) { return $fields; }
}
if (!function_exists(__NAMESPACE__ . '\confirm_box'))
{
	function confirm_box($check, $title = '', $hidden = [])
	{
		if ($check)
		{
			// Tests that exercise the confirmed branch set this flag.
			return !empty($GLOBALS['kb_test_confirmed']);
		}
		$GLOBALS['kb_test_confirm_title'] = $title;
		$GLOBALS['kb_test_confirm_hidden'] = $hidden;
		throw new \RuntimeException('CONFIRM_BOX');
	}
}

class kb_acp_search_backend_confirm_test extends \PHPUnit\Framework\TestCase
{
	public function test_confirm_page_carries_the_form_token_so_the_confirmed_request_passes_the_form_check()
	{
		$GLOBALS['kb_test_valid_forms']['kb_acp_search'] = true;
		$GLOBALS['kb_test_confirm_hidden'] = null;

		$request = $this->createMock(\phpbb\request\request_interface::class);
		$request->method('is_set_post')->willReturnCallback(fn($name) => $name === 'submit');
		$request->method('is_set')->willReturnCallback(fn($name) => $name === 'config');
		$request->method('variable')->willReturnCallback(function ($name, $default) {
			$values = [
				'config'        => ['kb_search_type' => 'new\\backend'],
				'creation_time' => 1791229734,
				'form_token'    => 'token-from-the-first-submit',
			];
			return $values[$name] ?? $default;
		});
		$language = $this->getMockBuilder(\phpbb\language\language::class)->disableOriginalConstructor()->getMock();
		$language->method('lang')->willReturnArgument(0);
		$backend = $this->createMock(\phpbbmodders\knowledgebase\search\kb_search_backend_factory::class);

		$controller = (new \ReflectionClass(acp_controller::class))->newInstanceWithoutConstructor();
		$values = [
			'request'           => $request,
			'language'          => $language,
			'search_factory'    => $backend,
			'search_collection' => new \phpbb\di\service_collection($this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class)),
			'config'            => new \phpbb\config\config(['kb_num_articles' => 5, 'kb_search_type' => 'old\\backend']),
			'u_action'          => '/adm/index.php?mode=test',
		];
		foreach ($values as $name => $value)
		{
			$property = new \ReflectionProperty(acp_controller::class, $name);
			$property->setValue($controller, $value);
		}

		try
		{
			$controller->search_settings('id', 'index');
			$this->fail('Switching the search backend must ask for confirmation');
		}
		catch (\RuntimeException $error)
		{
			$this->assertSame('CONFIRM_BOX', $error->getMessage());
		}

		// The confirmed request is checked with check_form_key() again, which
		// needs both values back in the POST data.
		$this->assertSame(1791229734, $GLOBALS['kb_test_confirm_hidden']['creation_time']);
		$this->assertSame('token-from-the-first-submit', $GLOBALS['kb_test_confirm_hidden']['form_token']);
		$this->assertSame(['kb_search_type' => 'new\\backend'], $GLOBALS['kb_test_confirm_hidden']['config']);
	}
}
