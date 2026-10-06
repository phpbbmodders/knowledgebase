<?php
namespace phpbbmodders\knowledgebase\controller;

require_once __DIR__ . '/kb_acp_search_backend_confirm_test.php';
if (!defined('CONFIG_TABLE')) define('CONFIG_TABLE', 'phpbb_config');

class kb_acp_delete_field_test extends \PHPUnit\Framework\TestCase
{
	private $db;
	private $log_calls = [];

	protected function setUp(): void
	{
		$GLOBALS['kb_test_confirmed'] = false;
		$GLOBALS['kb_test_confirm_hidden'] = null;
		$this->db = new \phpbb\db\driver\sqlite3();
		$this->assertTrue($this->db->sql_connect(':memory:', '', '', ''));
		$this->db->sql_query('CREATE TABLE phpbb_kb_fields (field_id INTEGER PRIMARY KEY, field_key VARCHAR(100), field_name VARCHAR(255))');
		$this->db->sql_query('CREATE TABLE phpbb_kb_field_values (value_id INTEGER PRIMARY KEY, field_id INTEGER, value_label VARCHAR(255))');
		$this->db->sql_query('CREATE TABLE phpbb_kb_article_field_values (article_id INTEGER, value_id INTEGER, PRIMARY KEY (article_id, value_id))');
		$this->db->sql_query("INSERT INTO phpbb_kb_fields VALUES (1, 'version', 'phpBB Version'), (2, 'audience', 'Audience')");
		$this->db->sql_query("INSERT INTO phpbb_kb_field_values VALUES (10, 1, '3.3.x'), (20, 2, 'Users'), (21, 2, 'Admins'), (22, 2, 'Devs')");
		// Articles 100 and 101 carry Audience values (101 two of them), 100 also the version.
		$this->db->sql_query('INSERT INTO phpbb_kb_article_field_values VALUES (100, 10), (100, 20), (101, 21), (101, 22)');
	}

	protected function tearDown(): void
	{
		$this->db->sql_close();
		unset($GLOBALS['kb_test_confirmed']);
	}

	private function controller(): acp_controller
	{
		$kb = $this->createMock(\phpbbmodders\knowledgebase\inc\functions_kb::class);
		$kb->method('get_fields_table')->willReturn('phpbb_kb_fields');
		$kb->method('get_field_values_table')->willReturn('phpbb_kb_field_values');
		$kb->method('get_article_field_values_table')->willReturn('phpbb_kb_article_field_values');
		$kb->method('get_field')->willReturnCallback(function ($id) {
			$result = $this->db->sql_query('SELECT * FROM phpbb_kb_fields WHERE field_id = ' . (int) $id);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			return $row ?: null;
		});
		$language = $this->getMockBuilder(\phpbb\language\language::class)->disableOriginalConstructor()->getMock();
		$language->method('lang')->willReturnCallback(fn() => implode('|', func_get_args()));
		$log = $this->createMock(\phpbb\log\log::class);
		$log->method('add')->willReturnCallback(function (...$args) {
			$this->log_calls[] = $args;
			return 1;
		});
		$user = $this->getMockBuilder(\phpbb\user::class)->disableOriginalConstructor()->getMock();
		$user->data = ['user_id' => 2, 'user_ip' => '127.0.0.1'];

		$controller = (new \ReflectionClass(acp_controller::class))->newInstanceWithoutConstructor();
		foreach (['db' => $this->db, 'kb' => $kb, 'language' => $language, 'log' => $log, 'user' => $user, 'u_action' => 'acp&action=x'] as $name => $value)
		{
			$property = new \ReflectionProperty(acp_controller::class, $name);
			$property->setAccessible(true);
			$property->setValue($controller, $value);
		}

		return $controller;
	}

	private function total(string $sql): int
	{
		$result = $this->db->sql_query($sql);
		$count = (int) $this->db->sql_fetchfield('c');
		$this->db->sql_freeresult($result);

		return $count;
	}

	public function test_the_confirmation_page_says_how_much_will_go_and_changes_nothing()
	{
		try
		{
			$this->controller()->delete_field(2);
			$this->fail('The confirmation page should have been shown');
		}
		catch (\RuntimeException $e)
		{
			$this->assertSame('CONFIRM_BOX', $e->getMessage());
		}

		$this->assertSame(['action' => 'delete_field', 'field_id' => 2], $GLOBALS['kb_test_confirm_hidden']);
		$this->assertSame(2, $this->total('SELECT COUNT(*) AS c FROM phpbb_kb_fields'));
		$this->assertSame(4, $this->total('SELECT COUNT(*) AS c FROM phpbb_kb_field_values'));
		$this->assertSame(4, $this->total('SELECT COUNT(*) AS c FROM phpbb_kb_article_field_values'));
		$this->assertSame([], $this->log_calls);
	}

	public function test_the_message_names_the_field_with_its_value_and_article_counts()
	{
		try
		{
			$this->controller()->delete_field(2);
		}
		catch (\RuntimeException $e)
		{
		}

		// "Audience": 3 values, used on 2 articles (101 carries two of them but counts once).
		$this->assertSame('KB_FIELD_DELETE_CONFIRM|Audience|3|2', $GLOBALS['kb_test_confirm_title']);
	}

	public function test_confirming_removes_the_field_its_values_and_their_use_but_nothing_else()
	{
		$GLOBALS['kb_test_confirmed'] = true;

		try
		{
			$this->controller()->delete_field(2);
			$this->fail('The success message should have ended the request');
		}
		catch (\RuntimeException $e)
		{
			$this->assertStringContainsString('KB_FIELD_DELETED', $e->getMessage());
		}

		$this->assertSame(0, $this->total('SELECT COUNT(*) AS c FROM phpbb_kb_fields WHERE field_id = 2'));
		$this->assertSame(0, $this->total('SELECT COUNT(*) AS c FROM phpbb_kb_field_values WHERE field_id = 2'));
		$this->assertSame(1, $this->total('SELECT COUNT(*) AS c FROM phpbb_kb_fields WHERE field_id = 1'));
		$this->assertSame(1, $this->total('SELECT COUNT(*) AS c FROM phpbb_kb_field_values WHERE field_id = 1'));
		// Only the version tag on article 100 is left.
		$this->assertSame(1, $this->total('SELECT COUNT(*) AS c FROM phpbb_kb_article_field_values'));
		$this->assertSame(1, $this->total('SELECT COUNT(*) AS c FROM phpbb_kb_article_field_values WHERE article_id = 100 AND value_id = 10'));
	}

	public function test_deleting_is_logged_with_the_fields_name()
	{
		$GLOBALS['kb_test_confirmed'] = true;
		try { $this->controller()->delete_field(1); } catch (\RuntimeException $e) {}

		$this->assertCount(1, $this->log_calls);
		$this->assertSame(['admin', 2, '127.0.0.1', 'LOG_KB_FIELD_DELETE'], array_slice($this->log_calls[0], 0, 4));
		$this->assertSame(['phpBB Version'], $this->log_calls[0][5]);
	}

	public function test_a_field_that_does_not_exist_is_refused_even_when_confirmed()
	{
		$GLOBALS['kb_test_confirmed'] = true;

		try
		{
			$this->controller()->delete_field(99);
			$this->fail('A missing field should be refused');
		}
		catch (\RuntimeException $e)
		{
			$this->assertStringContainsString('KB_FIELD_NOT_FOUND', $e->getMessage());
		}

		$this->assertSame(2, $this->total('SELECT COUNT(*) AS c FROM phpbb_kb_fields'));
		$this->assertSame([], $this->log_calls);
	}
}
