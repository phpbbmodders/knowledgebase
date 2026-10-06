<?php
namespace phpbbmodders\knowledgebase\controller;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';
require_once dirname(__DIR__) . '/search/backend/kb_search_backend_interface.php';
require_once dirname(__DIR__) . '/search/kb_search_backend_factory.php';

if (!defined('GROUPS_TABLE')) define('GROUPS_TABLE', 'phpbb_groups');

class kb_acp_contact_group_test extends \PHPUnit\Framework\TestCase
{
	private function valid(int $submitted, bool $group_exists, ?array &$queries = [])
	{
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('sql_query')->willReturnCallback(function ($sql) use (&$queries) {
			$queries[] = $sql;
			return true;
		});
		$db->method('sql_fetchrow')->willReturn($group_exists ? ['group_id' => $submitted] : false);

		$controller = (new \ReflectionClass(acp_controller::class))->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty(acp_controller::class, 'db');
		$property->setAccessible(true);
		$property->setValue($controller, $db);
		$method = new \ReflectionMethod(acp_controller::class, 'valid_contact_group');
		$method->setAccessible(true);

		return $method->invoke($controller, $submitted);
	}

	/** @dataProvider none_values */
	public function test_none_and_negative_values_mean_no_group_without_a_query($submitted)
	{
		$queries = [];
		$this->assertSame(0, $this->valid($submitted, true, $queries));
		$this->assertSame([], $queries);
	}

	public function none_values()
	{
		return [[0], [-1]];
	}

	public function test_an_existing_group_is_kept()
	{
		$this->assertSame(11, $this->valid(11, true));
	}

	public function test_a_made_up_group_id_becomes_none()
	{
		$this->assertSame(0, $this->valid(9999, false));
	}
}
