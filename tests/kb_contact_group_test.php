<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\inc;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';

if (!defined('GROUPS_TABLE')) define('GROUPS_TABLE', 'phpbb_groups');
if (!function_exists(__NAMESPACE__ . '\append_sid'))
{
	function append_sid($url, $params = false)
	{
		return $url . ($params ? '?' . $params : '');
	}
}

class kb_contact_group_test extends \PHPUnit\Framework\TestCase
{
	private function kb(array $config, ?array $group_row, &$queries = [])
	{
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('sql_query')->willReturnCallback(function ($sql) use (&$queries) {
			$queries[] = $sql;
			return true;
		});
		$db->method('sql_fetchrow')->willReturn($group_row ?? false);
		$group_helper = $this->getMockBuilder(\phpbb\group\helper::class)->disableOriginalConstructor()->getMock();
		$group_helper->method('get_name')->willReturnCallback(fn($name) => 'Translated ' . $name);

		$kb = (new \ReflectionClass(functions_kb::class))->newInstanceWithoutConstructor();
		foreach (['db' => $db, 'config' => new \phpbb\config\config($config), 'group_helper' => $group_helper, 'phpbb_root_path' => './../', 'php_ext' => 'php'] as $name => $value)
		{
			$property = new \ReflectionProperty(functions_kb::class, $name);
			$property->setValue($kb, $value);
		}

		return $kb;
	}

	/** @dataProvider unset_values */
	public function test_no_group_is_returned_and_nothing_is_queried_when_none_is_set($value)
	{
		$queries = [];
		$this->assertNull($this->kb($value === null ? [] : ['kb_contact_group' => $value], null, $queries)->get_contact_group());
		$this->assertSame([], $queries);
	}

	public function unset_values()
	{
		return [[null], [0], ['0'], [-3]];
	}

	public function test_a_group_that_no_longer_exists_is_ignored()
	{
		$this->assertNull($this->kb(['kb_contact_group' => 999], null)->get_contact_group());
	}

	public function test_the_group_comes_back_with_its_translated_name_colour_and_member_list_link()
	{
		$queries = [];
		$group = $this->kb(['kb_contact_group' => 11], ['group_id' => '11', 'group_name' => 'KB Team', 'group_colour' => 'E67300'], $queries)->get_contact_group();

		$this->assertSame([
			'id'     => 11,
			'name'   => 'Translated KB Team',
			'colour' => 'E67300',
			'url'    => './../memberlist.php?mode=group&amp;g=11',
		], $group);
		$this->assertStringContainsString('WHERE group_id = 11', $queries[0]);
	}

	/** @dataProvider colours */
	public function test_only_a_six_digit_hex_colour_is_passed_on($stored, $expected)
	{
		$group = $this->kb(['kb_contact_group' => 5], ['group_id' => 5, 'group_name' => 'X', 'group_colour' => $stored])->get_contact_group();
		$this->assertSame($expected, $group['colour']);
	}

	public function colours()
	{
		return [['AA0000', 'AA0000'], ['aa00ff', 'aa00ff'], ['', ''], ['red', ''], ['12345', ''], ['"><script>', '']];
	}
}
