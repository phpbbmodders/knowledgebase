<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\notification;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/notification/method/pm.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_23.php';

class kb_pm_defaults_test extends \PHPUnit\Framework\TestCase
{
	public function test_pm_is_enabled_by_default_only_for_supported_types()
	{
		$method = (new \ReflectionClass(method\pm::class))->newInstanceWithoutConstructor();
		$this->assertTrue($method->is_enabled_by_default());
		foreach (['approve', 'disapprove', 'need_approval'] as $name)
		{
			$type = $this->createMock(\phpbb\notification\type\type_interface::class);
			$type->method('get_type')->willReturn('phpbbmodders.knowledgebase.notification.type.' . $name);
			$this->assertSame($name !== 'need_approval', $method->is_available($type));
		}
	}

	public function test_upgrade_only_removes_the_obsolete_override()
	{
		$migration = (new \ReflectionClass(\phpbbmodders\knowledgebase\migrations\version_2_0_23::class))->newInstanceWithoutConstructor();
		$this->assertSame([
			['config.remove', ['kb_require_author_pm']],
		], $migration->update_data());
	}
}
