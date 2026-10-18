<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\migrations;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/migrations/version_1_0_0.php';
require_once dirname(__DIR__) . '/migrations/version_1_0_2.php';
if (!defined('CONFIG_TABLE')) define('CONFIG_TABLE', 'phpbb_config');

class kb_display_order_migration_test extends \PHPUnit\Framework\TestCase
{
	private $db;
	private $tools;
	private $sqlite_fixture;

	protected function tearDown(): void
	{
		if ($this->tools instanceof \phpbb\db\tools\doctrine) $this->tools->get_connection()->close();
		if ($this->db) $this->db->sql_close();
		if ($this->sqlite_fixture) unlink($this->sqlite_fixture);
	}

	private function connect(string $engine): void
	{
		if ($engine === 'sqlite')
		{
			$this->db = new \phpbb\db\driver\sqlite3();
			if (class_exists(\phpbb\db\tools\doctrine::class))
			{
				$this->sqlite_fixture = tempnam(sys_get_temp_dir(), 'kb-schema-');
				$this->assertTrue($this->db->sql_connect($this->sqlite_fixture, '', '', ''));
				$connection = \phpbb\db\doctrine\connection_factory::get_connection_from_params('sqlite3', $this->sqlite_fixture);
				$this->tools = new \phpbb\db\tools\doctrine($connection);
				$this->tools->set_table_prefix('phpbb_');
			}
			else
			{
				$this->assertTrue($this->db->sql_connect(':memory:', '', '', ''));
				$this->tools = new \phpbb\db\tools\sqlite3($this->db);
			}
			return;
		}

		$port = getenv('KB_TEST_' . strtoupper($engine) . '_PORT');
		if (!$port) $this->markTestSkipped('Set the fixture database port to run integration tests');
		$this->assertTrue(ctype_digit($port));
		$this->db = $engine === 'mysql' ? new \phpbb\db\driver\mysqli() : new \phpbb\db\driver\postgres();
		$this->db->sql_return_on_error(true);
		$this->assertIsObject($this->db->sql_connect('127.0.0.1', $engine === 'mysql' ? 'root' : 'postgres', '', 'kb_fixture', (int) $port));
		if (class_exists(\phpbb\db\tools\doctrine::class))
		{
			$connection = \phpbb\db\doctrine\connection_factory::get_connection_from_params(
				$engine === 'mysql' ? 'mysqli' : 'postgres', '127.0.0.1',
				$engine === 'mysql' ? 'root' : 'postgres', '', 'kb_fixture', $port
			);
			$this->tools = new \phpbb\db\tools\doctrine($connection);
			$this->tools->set_table_prefix('phpbb_');
		}
		else
		{
			$this->tools = $engine === 'mysql' ? new \phpbb\db\tools\tools($this->db) : new \phpbb\db\tools\postgres($this->db);
		}
		foreach (['phpbb_kb_articles', 'phpbb_kb_categories'] as $table)
		{
			$this->query('DROP TABLE IF EXISTS ' . $table);
			if ($engine === 'postgres') $this->query('DROP SEQUENCE IF EXISTS ' . $table . '_seq');
		}
	}

	private function query(string $sql)
	{
		$result = $this->db->sql_query($sql);
		if ($result === false) throw new \RuntimeException(json_encode($this->db->sql_error()));
		return $result;
	}

	/** The tables as the first migration creates them, plus the column the migration under test fills in. */
	private function create_tables(): void
	{
		$config = new \phpbb\config\config([]);
		$schema = (new version_1_0_0($config, $this->db, $this->tools, '', 'php', 'phpbb_', []))->update_schema()['add_tables'];
		foreach (['phpbb_kb_articles', 'phpbb_kb_categories'] as $table)
		{
			$this->tools->sql_create_table($table, $schema[$table]);
		}
		$this->tools->sql_column_add('phpbb_kb_articles', 'display_order', ['UINT', 0]);
	}

	private function add_article(int $id, int $category_id): void
	{
		// Strict MySQL wants a value for every NOT NULL column, so fill each
		// column the first migration defines with an empty value of its type.
		$config = new \phpbb\config\config([]);
		$columns = (new version_1_0_0($config, $this->db, $this->tools, '', 'php', 'phpbb_', []))->update_schema()['add_tables']['phpbb_kb_articles']['COLUMNS'];
		$row = [];
		foreach ($columns as $name => $definition)
		{
			$row[$name] = preg_match('/^(VCHAR|TEXT|MTEXT|STEXT|VARBINARY)/', $definition[0]) ? '' : 0;
		}
		$row['article_id'] = $id;
		$row['article_category_id'] = $category_id;
		$row['article_title'] = 'A' . $id;
		$row['display_order'] = 0;

		$this->query('INSERT INTO phpbb_kb_articles ' . $this->db->sql_build_array('INSERT', $row));
	}

	private function orders(): array
	{
		$result = $this->query('SELECT article_id, display_order FROM phpbb_kb_articles ORDER BY article_id');
		$orders = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$orders[(int) $row['article_id']] = (int) $row['display_order'];
		}
		$this->db->sql_freeresult($result);

		return $orders;
	}

	private function migrate(): void
	{
		(new version_1_0_2(new \phpbb\config\config([]), $this->db, $this->tools, '', 'php', 'phpbb_', []))->set_display_order();
	}

	/** @dataProvider engines */
	public function test_each_category_is_numbered_from_one_in_submission_order($engine)
	{
		$this->connect($engine);
		$this->create_tables();
		foreach ([1, 2, 3] as $category_id)
		{
			$this->query('INSERT INTO phpbb_kb_categories ' . $this->db->sql_build_array('INSERT', ['category_id' => $category_id, 'category_name' => 'C' . $category_id, 'category_parents' => '']));
		}
		// Interleaved across categories, ids deliberately out of order, one
		// category with no articles, and one article in a category that no
		// longer exists.
		foreach ([[7, 2], [3, 1], [9, 1], [4, 2], [1, 1], [8, 2], [5, 99], [6, 3]] as [$id, $category_id])
		{
			$this->add_article($id, $category_id);
		}

		$this->migrate();

		$this->assertSame([1 => 1, 3 => 2, 4 => 1, 5 => 0, 6 => 1, 7 => 2, 8 => 3, 9 => 3], $this->orders());
	}

	/** @dataProvider engines */
	public function test_an_empty_board_is_left_alone($engine)
	{
		$this->connect($engine);
		$this->create_tables();
		$this->query('INSERT INTO phpbb_kb_categories ' . $this->db->sql_build_array('INSERT', ['category_id' => 1, 'category_name' => 'C', 'category_parents' => '']));

		$this->migrate();

		$this->assertSame([], $this->orders());
	}

	public function test_the_number_of_queries_does_not_grow_with_the_number_of_categories_or_articles()
	{
		$this->connect('sqlite');
		$this->create_tables();
		for ($category_id = 1; $category_id <= 40; $category_id++)
		{
			$this->query('INSERT INTO phpbb_kb_categories ' . $this->db->sql_build_array('INSERT', ['category_id' => $category_id, 'category_name' => 'C' . $category_id, 'category_parents' => '']));
		}
		$this->query('BEGIN');
		for ($id = 1; $id <= 1201; $id++)
		{
			$this->add_article($id, ($id % 40) + 1);
		}
		$this->query('COMMIT');
		$before = $this->db->sql_num_queries();

		$this->migrate();

		// One SELECT for everything, then 500 articles per UPDATE: 1201 -> 3.
		$this->assertSame(4, $this->db->sql_num_queries() - $before);
		$orders = $this->orders();
		$this->assertSame(1, $orders[40]);
		$this->assertSame(30, $orders[1200]);
		$this->assertCount(1201, $orders);
		$this->assertNotContains(0, $orders);
	}

	public function engines(): array
	{
		return [['sqlite'], ['mysql'], ['postgres']];
	}
}
