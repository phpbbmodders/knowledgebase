<?php
namespace phpbbmodders\knowledgebase\inc;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';

/** Where "Add article" starts from a page that has no category of its own. */
class kb_postable_category_test extends \PHPUnit\Framework\TestCase
{
	private $db;

	protected function tearDown(): void
	{
		if ($this->db) $this->db->sql_close();
	}

	/** @param array $permitted permission => category ids the visitor holds it in */
	private function kb(array $permitted, ?int &$checks = 0)
	{
		$this->db = new \phpbb\db\driver\sqlite3();
		$this->assertTrue($this->db->sql_connect(':memory:', '', '', ''));
		$this->db->sql_query('CREATE TABLE phpbb_kb_categories (category_id INTEGER PRIMARY KEY, left_id INTEGER)');
		// Tree order is not id order: category 7 comes first, then 3, then 5.
		$this->db->sql_query('INSERT INTO phpbb_kb_categories VALUES (3, 3), (5, 5), (7, 1)');

		$kb = $this->getMockBuilder(functions_kb::class)->disableOriginalConstructor()->onlyMethods(['acl_kb_get'])->getMock();
		$kb->method('acl_kb_get')->willReturnCallback(function ($category_id, $permission) use ($permitted, &$checks) {
			$checks++;
			return in_array($category_id, $permitted[$permission] ?? [], true);
		});
		foreach (['db' => $this->db, 'categories_table' => 'phpbb_kb_categories'] as $name => $value)
		{
			$property = new \ReflectionProperty(functions_kb::class, $name);
			$property->setAccessible(true);
			$property->setValue($kb, $value);
		}

		return $kb;
	}

	public function test_it_returns_the_first_category_in_tree_order_that_the_visitor_may_post_in()
	{
		$this->assertSame(3, $this->kb(['kb_u_add' => [3, 5]])->get_postable_category_id('kb_u_add'));
	}

	public function test_tree_order_not_id_order_decides()
	{
		$this->assertSame(7, $this->kb(['kb_u_add' => [3, 5, 7]])->get_postable_category_id('kb_u_add'));
	}

	public function test_it_returns_zero_when_the_visitor_may_post_nowhere()
	{
		$this->assertSame(0, $this->kb(['kb_u_add' => []])->get_postable_category_id('kb_u_add'));
	}

	public function test_each_permission_is_looked_up_on_its_own()
	{
		$kb = $this->kb(['kb_u_add' => [7], 'kb_m_approve' => [5]]);

		$this->assertSame(7, $kb->get_postable_category_id('kb_u_add'));
		$this->assertSame(5, $kb->get_postable_category_id('kb_m_approve'));
	}

	public function test_the_answer_is_remembered_for_the_rest_of_the_request()
	{
		$checks = 0;
		$kb = $this->kb(['kb_u_add' => [5]], $checks);

		$first = $kb->get_postable_category_id('kb_u_add');
		$after_first = $checks;
		$second = $kb->get_postable_category_id('kb_u_add');

		$this->assertSame([5, 5], [$first, $second]);
		$this->assertSame(3, $after_first);
		$this->assertSame($after_first, $checks);
	}

	public function test_it_stops_at_the_first_permitted_category()
	{
		$checks = 0;
		$this->kb(['kb_u_add' => [7]], $checks)->get_postable_category_id('kb_u_add');

		$this->assertSame(1, $checks);
	}
}
