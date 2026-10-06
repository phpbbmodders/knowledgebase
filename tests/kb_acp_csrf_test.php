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

class kb_acp_csrf_test extends \PHPUnit\Framework\TestCase
{
	private $controller;
	private $request;
	private $db;
	private $config;

	private function inject($name, $value)
	{
		$property = new \ReflectionProperty(acp_controller::class, $name);
		$property->setAccessible(true);
		$property->setValue($this->controller, $value);
	}

	protected function setUp(): void
	{
		$GLOBALS['kb_test_valid_forms'] = [];
		$GLOBALS['kb_test_refresh_url'] = null;
		$GLOBALS['kb_test_stop_on_refresh'] = false;
		$this->controller = (new \ReflectionClass(acp_controller::class))->newInstanceWithoutConstructor();
		$this->request = $this->createMock(\phpbb\request\request_interface::class);
		$this->db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$this->config = new \phpbb\config\config(['search_indexing_state' => '']);
		$language = $this->getMockBuilder(\phpbb\language\language::class)->disableOriginalConstructor()->getMock();
		$language->method('lang')->willReturnArgument(0);
		foreach (['request' => $this->request, 'db' => $this->db, 'config' => $this->config, 'language' => $language, 'u_action' => '/adm/index.php?mode=test'] as $name => $value)
		{
			$this->inject($name, $value);
		}
	}

	protected function tearDown(): void
	{
		$GLOBALS['kb_test_valid_forms'] = [];
		$GLOBALS['kb_test_stop_on_refresh'] = false;
	}

	private function request_values(array $post, array $values): void
	{
		$this->request->method('is_set_post')->willReturnCallback(fn($name) => in_array($name, $post, true));
		$this->request->method('variable')->willReturnCallback(fn($name, $default) => $values[$name] ?? $default);
	}

	/** @dataProvider forged_requests */
	public function test_forged_requests_stop_before_database_access($method, $args, $post, $values, $state)
	{
		$this->request_values($post, $values);
		$this->config->set('search_indexing_state', $state);
		$this->db->expects($this->never())->method('sql_query');
		$this->db->expects($this->never())->method('sql_query_limit');
		try
		{
			$this->controller->$method(...$args);
		}
		catch (\RuntimeException $error)
		{
			$this->assertSame('FORM_INVALID', $error->getMessage());
			$this->assertSame($state, $this->config['search_indexing_state']);
			return;
		}
		$this->fail('A forged request must be rejected');
	}

	public function forged_requests()
	{
		return [
			['orphan', [], ['submit'], [], ''],
			['extra_files', [], ['submit'], [], ''],
			['lost_files', [], ['submit'], [], ''],
			['extra_files', [], [], ['submit' => true], ''],
			['lost_files', [], [], ['submit' => true, 'continue' => true, 'hash' => 'wrong'], ''],
			['extra_files', [], [], ['submit' => true, 'continue' => true, 'hash' => 'kb_acp_lost_files_valid'], ''],
			['add_field', [], ['submit'], [], ''],
			['edit_field_values', [1], ['submit'], [], ''],
			['toggle_field', [1], [], [], ''],
			['toggle_field', [1], [], ['hash' => 'kb_field_toggle_2_valid'], ''],
			['search_index', ['id', 'index'], ['action', 'kb_search_type'], ['action' => 'create'], ''],
			['search_index', ['id', 'index'], [], ['action' => 'delete'], 'probe,delete,1'],
			['search_index', ['id', 'index'], [], ['action' => 'delete', 'hash' => 'kb_acp_search_create_valid'], 'probe,delete,1'],
			['search_index', ['id', 'index'], [], ['action' => 'create', 'hash' => 'kb_acp_search_create_valid'], ''],
			['search_index', ['id', 'index'], ['continue'], ['action' => 'create'], 'probe,create,1'],
			['search_index', ['id', 'index'], ['cancel'], [], 'probe,create,1'],
		];
	}

	/** @dataProvider batch_keys */
	public function test_valid_batch_submissions_and_continuations($key, $post)
	{
		$GLOBALS['kb_test_valid_forms'][$key] = true;
		$this->request_values($post ? ['submit'] : [], ['submit' => true, 'continue' => true, 'hash' => $key . '_valid']);
		$method = new \ReflectionMethod(acp_controller::class, 'validate_batch_request');
		$method->setAccessible(true);
		$this->assertTrue($method->invoke($this->controller, $key));
	}

	public function batch_keys()
	{
		return [['kb_acp_extra_files', true], ['kb_acp_extra_files', false], ['kb_acp_lost_files', true], ['kb_acp_lost_files', false]];
	}

	public function test_valid_field_toggle_changes_only_the_requested_field()
	{
		$this->request_values([], ['hash' => 'kb_field_toggle_1_valid']);
		$kb = $this->getMockBuilder(\phpbbmodders\knowledgebase\inc\functions_kb::class)->disableOriginalConstructor()->getMock();
		$kb->method('get_field')->with(1)->willReturn(['enabled' => 1]);
		$kb->method('get_fields_table')->willReturn('fields');
		$this->inject('kb', $kb);
		$this->db->expects($this->once())->method('sql_query')->with('UPDATE fields SET enabled = 0 WHERE field_id = 1');
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('KB_FIELD_UPDATED');
		$this->controller->toggle_field(1);
	}

	/** @dataProvider search_requests */
	public function test_valid_search_requests_execute_backend_and_keep_continuation_hash($action, $post)
	{
		$GLOBALS['kb_test_stop_on_refresh'] = true;
		$GLOBALS['kb_test_valid_forms']['kb_acp_search'] = true;
		$this->request_values($post ? ['action', 'kb_search_type'] : [], ['action' => $action, 'kb_search_type' => 'probe', 'hash' => 'kb_acp_search_' . $action . '_valid']);
		if (!$post) $this->config->set('search_indexing_state', 'probe,' . $action . ',1');
		$backend = $this->createMock(\phpbbmodders\knowledgebase\search\backend\kb_search_backend_interface::class);
		$backend->expects($this->once())->method($action . '_index')->willReturn(['row_count' => 1, 'post_counter' => 1, 'rows_per_second' => 1]);
		$factory = $this->getMockBuilder(\phpbbmodders\knowledgebase\search\kb_search_backend_factory::class)->disableOriginalConstructor()->getMock();
		$factory->method('get')->with('probe')->willReturn($backend);
		$this->inject('search_factory', $factory);
		try { $this->controller->search_index('id', 'index'); }
		catch (kb_test_refresh_stop $error) {}
		$this->assertStringContainsString('action=' . $action, $GLOBALS['kb_test_refresh_url']);
		$this->assertStringContainsString('hash=kb_acp_search_' . $action . '_valid', $GLOBALS['kb_test_refresh_url']);
		$this->assertSame('probe,' . $action . ',' . ($post ? '0' : '1'), $this->config['search_indexing_state']);
	}

	public function search_requests()
	{
		return [['create', true], ['create', false], ['delete', true], ['delete', false]];
	}

	public function test_valid_search_cancellation_clears_state()
	{
		$GLOBALS['kb_test_valid_forms']['kb_acp_search'] = true;
		$this->request_values(['cancel'], []);
		$this->config->set('search_indexing_state', 'probe,create,1');
		$collection = $this->getMockBuilder(\phpbb\di\service_collection::class)->disableOriginalConstructor()->getMock();
		$collection->method('getIterator')->willReturn(new \ArrayIterator([]));
		$this->inject('search_collection', $collection);
		$this->inject('template', $this->createMock(\phpbb\template\template::class));
		$this->controller->search_index('id', 'index');
		$this->assertSame('', $this->config['search_indexing_state']);
	}

	public function test_continue_form_has_its_own_action()
	{
		$template = $this->createMock(\phpbb\template\template::class);
		$vars = [];
		$template->method('assign_vars')->willReturnCallback(function ($values) use (&$vars) { $vars = $values; });
		$this->inject('template', $template);
		$method = new \ReflectionMethod(acp_controller::class, 'index_inprogress');
		$method->setAccessible(true);
		$method->invoke($this->controller, 'delete');
		$this->assertStringContainsString('action=delete', $vars['U_CONTINUE_INDEXING']);
		$this->assertArrayNotHasKey('U_ACTION', $vars);
	}
}
