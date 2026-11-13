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

/**
 * The ACP permission trace and masks filter Knowledge Base permission options
 * with build_auth_option_statement(). A single option name comes from the
 * address of the trace page, so it must be a quoted SQL string.
 */
class kb_acp_auth_option_statement_test extends \PHPUnit\Framework\TestCase
{
	/** @var \PDO */
	protected $pdo;

	/** @var acp_controller */
	protected $controller;

	protected function setUp(): void
	{
		$this->pdo = new \PDO('sqlite::memory:');
		$this->pdo->exec('CREATE TABLE options (auth_option TEXT)');
		$this->pdo->exec("INSERT INTO options VALUES ('kb_u_add'), ('kb_u_edit'), ('kb_m_approve')");

		// The escaping and LIKE building of phpBB's SQLite driver
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('sql_escape')->willReturnCallback(fn($text) => str_replace("'", "''", $text));
		$db->method('get_any_char')->willReturn(chr(0) . '%');
		$db->method('sql_like_expression')->willReturnCallback(function ($expression) {
			$expression = str_replace(['_', '%'], ["\\_", "\\%"], $expression);
			$expression = str_replace([chr(0) . "\\_", chr(0) . "\\%"], ['_', '%'], $expression);
			return "LIKE '" . str_replace("'", "''", $expression) . "' ESCAPE '\\'";
		});
		$db->method('sql_in_set')->willReturnCallback(fn($field, $values) => $field . " IN ('" . implode("', '", array_map(fn($value) => str_replace("'", "''", $value), $values)) . "')");

		$this->controller = (new \ReflectionClass(acp_controller::class))->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty(acp_controller::class, 'db');
		$property->setValue($this->controller, $db);
	}

	/**
	 * The option names the filter matches in the test table
	 *
	 * @param string|array $options One option, a LIKE pattern with %, or a list
	 * @return array The matching option names, sorted
	 */
	protected function matching($options): array
	{
		$sql_opts = '';
		$method = new \ReflectionMethod(acp_controller::class, 'build_auth_option_statement');
		$method->invokeArgs($this->controller, ['auth_option', $options, &$sql_opts]);
		$rows = $this->pdo->query('SELECT auth_option FROM options WHERE 1 = 1 ' . $sql_opts . ' ORDER BY auth_option')->fetchAll(\PDO::FETCH_COLUMN);
		return $rows;
	}

	public function test_a_single_option_is_a_quoted_string()
	{
		$this->assertSame(['kb_u_add'], $this->matching('kb_u_add'));
	}

	public function test_sql_in_a_single_option_matches_nothing()
	{
		$this->assertSame([], $this->matching('kb_u_add OR 1 = 1'));
		$this->assertSame([], $this->matching("kb_u_add' OR '1' = '1"));
	}

	public function test_a_like_pattern_matches_its_options()
	{
		$this->assertSame(['kb_u_add', 'kb_u_edit'], $this->matching('kb_u_%'));
	}

	public function test_a_list_of_options_still_works()
	{
		$this->assertSame(['kb_m_approve', 'kb_u_add'], $this->matching(['kb_u_add', 'kb_m_approve']));
	}
}
