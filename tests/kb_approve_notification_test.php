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
require_once dirname(__DIR__) . '/controller/approve.php';

class kb_approve_notification_test extends \PHPUnit\Framework\TestCase
{
	public function test_deleting_the_need_approval_notification_passes_only_the_article_id()
	{
		$manager = $this->createMock(\phpbb\notification\manager::class);
		// The manager treats every value it is given as an item id, so an
		// article's title, author id or category id must never be passed.
		$manager->expects($this->once())
			->method('delete_notifications')
			->with('phpbbmodders.knowledgebase.notification.type.need_approval', 12);

		$controller = (new \ReflectionClass(approve::class))->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty(approve::class, 'notification_manager');
		$property->setAccessible(true);
		$property->setValue($controller, $manager);

		$method = new \ReflectionMethod(approve::class, 'delete_need_approval_notification');
		$method->setAccessible(true);
		$method->invoke($controller, 12);
	}
}
