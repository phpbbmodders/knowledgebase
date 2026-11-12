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

/**
 * MSSQL's driver returns NULL columns as empty strings, so MAX(right_id) of an
 * empty category table is '' there, and adding the first category stopped with
 * "Unsupported operand types: string + int".
 */
class kb_acp_first_category_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider empty_max_values */
	public function test_the_first_root_category_gets_left_id_1($empty_max)
	{
		$inserted = null;
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('sql_query')->willReturn(true);
		$db->method('sql_fetchrow')->willReturn(['right_id' => $empty_max]);
		$db->method('sql_build_array')->willReturnCallback(function ($type, $data) use (&$inserted) {
			$inserted = $data;
			// Stop before the log and permission steps that follow the INSERT
			throw new \RuntimeException('insert built');
		});

		$controller = (new \ReflectionClass(acp_controller::class))->newInstanceWithoutConstructor();
		foreach (['db' => $db, 'categories_table' => 'categories'] as $name => $value)
		{
			$property = new \ReflectionProperty(acp_controller::class, $name);
			$property->setValue($controller, $value);
		}

		$category = ['category_name' => 'Getting Started', 'parent_id' => 0, 'category_parents' => ''];
		try
		{
			$controller->update_category_data($category, 0);
			$this->fail('Expected the stop at the INSERT');
		}
		catch (\RuntimeException $error)
		{
			$this->assertSame('insert built', $error->getMessage());
		}

		$this->assertSame(1, $inserted['left_id']);
		$this->assertSame(2, $inserted['right_id']);
	}

	public function empty_max_values()
	{
		return ['NULL (MySQL, PostgreSQL, SQLite)' => [null], 'empty string (MSSQL)' => ['']];
	}
}
