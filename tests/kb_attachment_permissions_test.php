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
require_once dirname(__DIR__) . '/controller/kb_file.php';

function send_status_line($code, $message) { $GLOBALS['kb_attachment_status'] = $code; }
function utf8_basename($filename) { throw new \RuntimeException('attachment access granted'); }

class kb_attachment_permissions_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider article_visibility */
	public function test_download_matches_article_visibility($status, $approved, $own, $moderator, $reviewer, $allowed)
	{
		$GLOBALS['kb_attachment_status'] = null;
		$controller = (new \ReflectionClass(kb_file::class))->newInstanceWithoutConstructor();
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('sql_fetchrow')->willReturnOnConsecutiveCalls(
			['article_id' => 2, 'physical_filename' => 'test'],
			['article_category_id' => 1, 'approved' => $approved, 'status' => $status, 'author_id' => 2, 'author_type' => 0, 'assigned_team_id' => 3]
		);
		$auth = $this->getMockBuilder(\phpbb\auth\auth::class)->disableOriginalConstructor()->getMock();
		$auth->method('acl_get')->willReturnCallback(fn($permission) => $permission === 'u_kb_view' || ($permission === 'a_manage_kb' && $moderator));
		$request = $this->createMock(\phpbb\request\request_interface::class);
		$request->method('variable')->willReturnCallback(fn($key, $default) => $key === 'id' ? 1 : $default);
		$kb = $this->getMockBuilder(\phpbbmodders\knowledgebase\inc\functions_kb::class)->disableOriginalConstructor()->getMock();
		$kb->method('is_article_author')->willReturn($own);
		$kb->method('get_article_categories')->willReturn([1]);
		$kb->method('get_effective_team_id')->willReturn(3);
		$kb->method('is_team_member')->willReturn($reviewer);
		$language = $this->getMockBuilder(\phpbb\language\language::class)->disableOriginalConstructor()->getMock();
		foreach (['db' => $db, 'auth' => $auth, 'request' => $request, 'kb' => $kb, 'language' => $language, 'articles_table' => 'articles', 'attachments_table' => 'attachments'] as $name => $value)
		{
			$property = new \ReflectionProperty(kb_file::class, $name);
			$property->setAccessible(true);
			$property->setValue($controller, $value);
		}
		try
		{
			$controller->main();
			$this->fail('Expected rejection or the stop before reading the file');
		}
		catch (\RuntimeException $error)
		{
			$this->assertSame($allowed ? 'attachment access granted' : 'NOT_AUTHORISED', $error->getMessage());
		}
		$this->assertSame($allowed ? null : 403, $GLOBALS['kb_attachment_status']);
	}

	public function article_visibility()
	{
		$draft = \phpbbmodders\knowledgebase\inc\functions_kb::STATUS_DRAFT;
		$new = \phpbbmodders\knowledgebase\inc\functions_kb::STATUS_NEW;
		$active = \phpbbmodders\knowledgebase\inc\functions_kb::STATUS_ACTIVE;
		return [
			[$draft, 0, false, true, false, false],
			[$draft, 0, false, false, true, false],
			[$draft, 0, true, false, false, true],
			[$draft, 1, false, true, false, false],
			[$new, 0, true, false, false, true],
			[$new, 0, false, false, true, true],
			[$new, 0, false, true, false, true],
			[$new, 0, false, false, false, false],
			[$active, 1, false, false, false, true],
			[$active, 0, false, false, true, false],
		];
	}
}
