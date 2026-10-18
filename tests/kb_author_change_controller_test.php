<?php
namespace phpbbmodders\knowledgebase\controller;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';
require_once dirname(__DIR__) . '/inc/author_change.php';
require_once dirname(__DIR__) . '/controller/change_author.php';
if (!defined('ANONYMOUS')) define('ANONYMOUS', 1);

/** The HTTP controller must enforce access and CSRF before transferring. */
class kb_author_change_controller_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider access_cases */
	public function test_access_gates(int $user_id, bool $view, bool $permission, int $status, bool $draft_edit, int $expected): void
	{
		$controller = $this->controller($user_id, $view, $permission, $status, $draft_edit);
		$this->assertSame($expected, $controller->main()->getStatusCode());
	}

	public function access_cases(): array
	{
		return [
			'guest' => [1, true, true, 2, false, 403],
			'no KB access' => [2, false, true, 2, false, 403],
			'no transfer permission' => [2, true, false, 2, false, 403],
			'private draft' => [2, true, true, 4, false, 404],
			'private draft edit' => [2, true, true, 2, true, 409],
		];
	}

	public function test_invalid_csrf_cannot_call_transfer(): void
	{
		$controller = $this->controller(2, true, true, 2, false, true);
		$response = $controller->main();
		$this->assertSame(200, $response->getStatusCode());
		$this->assertStringContainsString('FORM_INVALID', $response->getContent());
	}

	private function controller(int $user_id, bool $view, bool $permission, int $status, bool $draft_edit, bool $submit = false): change_author
	{
		$GLOBALS['kb_test_valid_forms'] = [];
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('sql_fetchrow')->willReturn(['article_id' => 1, 'article_category_id' => 1, 'author_id' => 3, 'author_type' => 0, 'author' => 'Author', 'article_title' => 'Guide', 'status' => $status]);
		$auth = $this->createMock(\phpbb\auth\auth::class);
		$auth->method('acl_get')->willReturnCallback(fn($permission) => $view && $permission === 'u_kb_view');
		$request = $this->createMock(\phpbb\request\request_interface::class);
		$request->method('variable')->willReturnCallback(fn($name, $default) => ['k' => 1, 'old_author_id' => 3, 'old_author_type' => 0][$name] ?? $default);
		$request->method('is_set_post')->willReturn($submit);
		$kb = $this->createMock(\phpbbmodders\knowledgebase\inc\functions_kb::class);
		$kb->method('get_article_categories')->willReturn([1]);
		$kb->method('acl_kb_get_any')->willReturn($permission);
		$kb->method('article_has_draft_edit')->willReturn($draft_edit);
		$kb->method('get_kb_team_group_names')->willReturn([]);
		$kb->method('get_author_display')->willReturn('Author');
		$transfer = $this->createMock(\phpbbmodders\knowledgebase\inc\author_change::class);
		$transfer->expects($this->never())->method('transfer');
		$user = $this->createMock(\phpbb\user::class);
		$user->data = ['user_id' => $user_id];
		$lang = $this->createMock(\phpbb\language\language::class);
		$lang->method('lang')->willReturnArgument(0);
		$template = $this->createMock(\phpbb\template\template::class);
		$errors = '';
		$template->method('assign_vars')->willReturnCallback(function ($values) use (&$errors) { $errors = $values['ERROR'] ?? $values['NOTICE_MESSAGE'] ?? ''; });
		$helper = $this->createMock(\phpbb\controller\helper::class);
		$helper->method('route')->willReturn('/knowledgebase/article?k=1');
		$helper->method('render')->willReturnCallback(function ($file, $title, $status = 200) use (&$errors) { return new \Symfony\Component\HttpFoundation\Response($errors, $status); });
		return new change_author($db, $helper, $lang, $auth, $request, $template, $user, $kb, $transfer, 'articles');
	}
}
