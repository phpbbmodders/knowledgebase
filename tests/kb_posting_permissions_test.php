<?php
namespace phpbbmodders\knowledgebase\controller;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';
require_once dirname(__DIR__) . '/search/backend/kb_search_backend_interface.php';
require_once dirname(__DIR__) . '/search/kb_search_backend_factory.php';
require_once dirname(__DIR__) . '/controller/posting.php';

class kb_posting_permissions_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider posting_modes */
	public function test_existing_article_modes_do_not_require_add_permission($mode, $expected)
	{
		$controller = (new \ReflectionClass(posting::class))->newInstanceWithoutConstructor();
		$auth = $this->getMockBuilder(\phpbb\auth\auth::class)->disableOriginalConstructor()->getMock();
		$auth->method('acl_get')->willReturnCallback(fn($permission) => $permission === 'u_kb_view');
		$request = $this->createMock(\phpbb\request\request_interface::class);
		$request->method('variable')->willReturnCallback(fn($key, $default) => ['id' => 1, 'k' => 2, 'mode' => $mode][$key] ?? $default);
		$kb = $this->getMockBuilder(\phpbbmodders\knowledgebase\inc\functions_kb::class)->disableOriginalConstructor()->getMock();
		$add_checked = false;
		$kb->method('acl_kb_get')->willReturnCallback(function ($category, $permission) use ($mode, &$add_checked) {
			if ($permission === 'kb_u_add')
			{
				$this->assertSame('', $mode, 'Edit/delete must not require the add permission');
				$add_checked = true;
			}
			return false;
		});
		$kb->method('get_article_categories')->willReturn([1]);
		$kb->method('is_article_author')->willReturn(true);
		$checked = [];
		$kb->method('acl_kb_get_any')->willReturnCallback(function ($categories, $permission) use (&$checked) {
			$checked[] = $permission;
			return strpos($permission, 'kb_u_') === 0;
		});
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('sql_fetchrow')->willReturn(['author_id' => 2, 'author_type' => 0, 'status' => \phpbbmodders\knowledgebase\inc\functions_kb::STATUS_ACTIVE]);
		$factory = $this->getMockBuilder(\phpbbmodders\knowledgebase\search\kb_search_backend_factory::class)->disableOriginalConstructor()->getMock();
		$factory->method('get_active')->willReturnCallback(function () { throw new \RuntimeException('permission gates passed'); });
		$language = $this->getMockBuilder(\phpbb\language\language::class)->disableOriginalConstructor()->getMock();
		$log = $this->getMockBuilder(\phpbb\log\log::class)->disableOriginalConstructor()->getMock();
		foreach (['auth' => $auth, 'request' => $request, 'kb' => $kb, 'db' => $db, 'search_factory' => $factory, 'language' => $language, 'log' => $log, 'logs_table' => 'logs', 'articles_table' => 'articles', 'categories_table' => 'categories', 'config' => new \phpbb\config\config(['kb_forum_id' => 1])] as $name => $value)
		{
			$property = new \ReflectionProperty(posting::class, $name);
			$property->setAccessible(true);
			$property->setValue($controller, $value);
		}
		try
		{
			$controller->post_article();
			$this->fail('Expected the permission gate or search-factory stop');
		}
		catch (\RuntimeException $error)
		{
			$this->assertSame($expected, $error->getMessage());
		}
		$this->assertSame($mode === '', $add_checked);
		if ($mode !== '')
		{
			$this->assertContains('kb_u_edit', $checked);
			$this->assertContains('kb_u_delete', $checked);
		}
	}

	public function posting_modes()
	{
		return [['edit', 'permission gates passed'], ['delete', 'permission gates passed'], ['', 'RULES_KB_ADD_CANNOT']];
	}
}
