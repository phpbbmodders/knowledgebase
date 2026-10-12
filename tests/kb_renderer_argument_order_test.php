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
require_once dirname(__DIR__) . '/textformatter/renderer.php';

if (!defined('ANONYMOUS')) define('ANONYMOUS', 1);

/** The renderer's configure_user() accepts its three arguments in any order, because 3.3.x and 4.0 declare them in different orders. */
class kb_renderer_argument_order_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider orders */
	public function test_every_order_of_the_three_services_reaches_the_parent_in_the_order_it_declares(array $order)
	{
		$user = $this->getMockBuilder(\phpbb\user::class)->disableOriginalConstructor()->onlyMethods(['optionget', 'lang'])->getMock();
		$user->method('optionget')->willReturn(true);
		$user->method('lang')->willReturn('x');
		$user->data = ['user_id' => 2, 'is_bot' => false, 'is_registered' => true];
		$user->style = ['style_id' => 7];
		$config = new \phpbb\config\config(['allow_nocensors' => 0]);
		$auth = $this->createMock(\phpbb\auth\auth::class);
		$services = ['user' => $user, 'config' => $config, 'auth' => $auth];

		$captured = [];
		$stub = new class($captured) {
			public $captured;
			public function __construct(&$captured) { $this->captured = &$captured; }
			public function getParameters() { return []; }
			public function setParameter($name, $value) {}
			public function setParameters(array $parameters) { $this->captured = $parameters; }
		};
		$renderer = (new \ReflectionClass(\phpbbmodders\knowledgebase\textformatter\renderer::class))->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty(\phpbb\textformatter\s9e\renderer::class, 'renderer');
		$property->setValue($renderer, $stub);

		$renderer->configure_user(...array_map(fn($name) => $services[$name], $order));

		$this->assertSame(7, $captured['STYLE_ID']);
		$this->assertTrue($captured['S_REGISTERED_USER']);
		$this->assertTrue($captured['S_USER_LOGGED_IN']);
	}

	public function orders(): array
	{
		$orders = [];
		foreach ([['user', 'config', 'auth'], ['auth', 'config', 'user'], ['config', 'user', 'auth'], ['auth', 'user', 'config'], ['user', 'auth', 'config'], ['config', 'auth', 'user']] as $order)
		{
			$orders[implode(',', $order)] = [$order];
		}

		return $orders;
	}

	public function test_the_service_definition_uses_the_version_neutral_renderer_and_an_optional_mention_helper()
	{
		$services = file_get_contents(dirname(__DIR__) . '/config/services.yml');

		$this->assertStringContainsString("class: phpbbmodders\\knowledgebase\\textformatter\\renderer", $services);
		$this->assertStringContainsString("[configure_mention_helper, ['@?text_formatter.s9e.mention_helper']]", $services);
	}
}
