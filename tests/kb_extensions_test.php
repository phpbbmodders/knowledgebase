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

function add_form_key($key) {}
function check_form_key($key) { return !empty($GLOBALS['kb_test_valid_forms'][$key]); }
function generate_link_hash($key) { return $key . '_valid'; }
function check_link_hash($hash, $key) { return $hash === generate_link_hash($key); }
class kb_test_refresh_stop extends \Error {}
function meta_refresh($time, $url)
{
	$GLOBALS['kb_test_refresh_url'] = $url;
	if (!empty($GLOBALS['kb_test_stop_on_refresh'])) throw new kb_test_refresh_stop();
}
function append_sid($url, $params) { return $url . '&amp;' . $params; }
function adm_back_link($url) { return ''; }
function trigger_error($message, $level = E_USER_NOTICE) { throw new \RuntimeException($message); }

if (!class_exists(\phpbb\controller\helper::class))
{
	$root = getenv('PHPBB_ROOT_PATH') ?: dirname(__DIR__, 4);
	require $root . '/vendor/autoload.php';
	spl_autoload_register(function ($class) use ($root) {
		if (strpos($class, 'phpbb\\') === 0)
		{
			require $root . '/' . str_replace('\\', '/', $class) . '.php';
		}
	});
}
require_once dirname(__DIR__) . '/controller/acp_controller.php';
if (!defined('EXTENSIONS_TABLE')) define('EXTENSIONS_TABLE', 'extensions');
if (!defined('EXTENSION_GROUPS_TABLE')) define('EXTENSION_GROUPS_TABLE', 'extension_groups');

class kb_extensions_test extends \PHPUnit\Framework\TestCase
{
	private $pdo;
	private $controller;
	private $request;
	private $config_text;
	private $template;
	private $cache;
	private $auth;

	private function inject($name, $value)
	{
		$property = new \ReflectionProperty(acp_controller::class, $name);
		$property->setAccessible(true);
		$property->setValue($this->controller, $value);
	}

	protected function setUp(): void
	{
		$GLOBALS['kb_test_valid_forms'] = [];
		$this->pdo = new \PDO('sqlite::memory:');
		$this->pdo->exec('CREATE TABLE extensions (extension_id INTEGER PRIMARY KEY, extension TEXT, group_id INTEGER)');
		$this->pdo->exec('CREATE TABLE extension_groups (group_id INTEGER PRIMARY KEY, group_name TEXT)');
		$this->pdo->exec("INSERT INTO extension_groups VALUES (1, 'IMAGES')");
		$this->pdo->exec("INSERT INTO extensions VALUES (1, 'png', 1), (2, 'unassigned_probe', 0)");
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('sql_query')->willReturnCallback(fn($sql) => $this->pdo->query($sql));
		$db->method('sql_fetchrow')->willReturnCallback(fn($result) => $result->fetch(\PDO::FETCH_ASSOC));
		$db->method('sql_escape')->willReturnCallback(fn($text) => str_replace("'", "''", $text));
		$db->method('sql_build_array')->willReturnCallback(function ($type, $values) {
			return '(' . implode(', ', array_keys($values)) . ') VALUES (' . implode(', ', array_map(fn($value) => $this->pdo->quote((string) $value), $values)) . ')';
		});
		$this->controller = (new \ReflectionClass(acp_controller::class))->newInstanceWithoutConstructor();
		$this->request = $this->createMock(\phpbb\request\request_interface::class);
		$this->config_text = $this->getMockBuilder(\phpbb\config\db_text::class)->disableOriginalConstructor()->getMock();
		$this->config_text->method('get')->willReturn('{"IMAGES":["png"],"NOT_ASSIGNED":["unassigned_probe"]}');
		$this->template = $this->createMock(\phpbb\template\template::class);
		$language = $this->getMockBuilder(\phpbb\language\language::class)->disableOriginalConstructor()->getMock();
		$language->method('lang')->willReturnCallback(fn($key) => $key === 'NOT_ASSIGNED' ? 'Not assigned' : $key);
		$user = $this->getMockBuilder(\phpbb\user::class)->disableOriginalConstructor()->getMock();
		$user->data = ['user_id' => 2, 'user_ip' => '127.0.0.1'];
		$log = $this->getMockBuilder(\phpbb\log\log::class)->disableOriginalConstructor()->getMock();
		$this->cache = $this->createMock(\phpbb\cache\driver\driver_interface::class);
		$this->auth = $this->getMockBuilder(\phpbb\auth\auth::class)->disableOriginalConstructor()->getMock();
		foreach (['db' => $db, 'request' => $this->request, 'config_text' => $this->config_text, 'template' => $this->template, 'language' => $language, 'user' => $user, 'log' => $log, 'cache' => $this->cache, 'auth' => $this->auth, 'u_action' => '/adm/index.php'] as $name => $value)
		{
			$this->inject($name, $value);
		}
	}

	private function invoke($name)
	{
		$method = new \ReflectionMethod(acp_controller::class, $name);
		$method->setAccessible(true);
		return $method->invoke($this->controller);
	}

	private function request_add($group)
	{
		$this->request->method('variable')->willReturnMap([
			['add_extension', '', false, \phpbb\request\request_interface::REQUEST, 'new_image_probe'],
			['add_group_select', 0, false, \phpbb\request\request_interface::REQUEST, $group],
		]);
	}

	public function test_display_includes_enabled_unassigned_extensions()
	{
		$rows = [];
		$this->template->method('assign_block_vars')->willReturnCallback(function ($block, $row) use (&$rows) { $rows[$row['EXTENSION']] = $row; });
		$vars = [];
		$this->template->method('assign_vars')->willReturnCallback(function ($values) use (&$vars) { $vars = $values; });
		$this->controller->extensions();
		$this->assertCount(2, $rows);
		$this->assertSame('Not assigned', $rows['unassigned_probe']['GROUP_NAME']);
		$this->assertTrue($rows['unassigned_probe']['S_ENABLED']);
		$this->assertFalse($vars['S_CAN_ADD_EXTENSION']);
	}

	public function test_save_retains_selected_unassigned_extensions()
	{
		$this->request->method('variable')->with('enabled_extensions', [''])->willReturn(['png', 'unassigned_probe', 'unknown']);
		$saved = null;
		$this->config_text->method('set')->willReturnCallback(function ($key, $value) use (&$saved) { $saved = json_decode($value, true); });
		$this->invoke('set_extensions');
		$this->assertSame(['png'], $saved['IMAGES']);
		$this->assertSame(['unassigned_probe'], $saved['NOT_ASSIGNED']);
	}

	/** @dataProvider valid_groups */
	public function test_add_invalidates_caches($group)
	{
		$this->auth->method('acl_get')->with('a_attach')->willReturn(true);
		$this->request_add($group);
		$invalidated = [];
		$this->cache->expects($this->exactly(2))->method('destroy')->willReturnCallback(function ($key) use (&$invalidated) { $invalidated[] = $key; });
		$this->invoke('add_extension');
		$this->assertSame(['_extensions', '_kb_extension'], $invalidated);
		$this->assertSame($group, (int) $this->pdo->query("SELECT group_id FROM extensions WHERE extension = 'new_image_probe'")->fetchColumn());
	}

	public function valid_groups()
	{
		return [[0], [1]];
	}

	public function test_add_requires_core_attachment_permission()
	{
		$this->auth->method('acl_get')->with('a_attach')->willReturn(false);
		$this->request_add(1);
		try
		{
			$this->invoke('add_extension');
			$this->fail('An administrator without a_attach must not insert extensions');
		}
		catch (\RuntimeException $error)
		{
			$this->assertSame('NOT_AUTHORISED', $error->getMessage());
		}
		$this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM extensions')->fetchColumn());
	}

	public function test_add_rejects_unknown_groups()
	{
		$this->auth->method('acl_get')->with('a_attach')->willReturn(true);
		$this->request_add(999);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('FORM_INVALID');
		$this->invoke('add_extension');
	}

	public function test_add_rejects_invalid_form_token()
	{
		$this->request->method('is_set_post')->willReturnCallback(fn($name) => $name === 'add_extension_submit');
		$this->auth->expects($this->never())->method('acl_get');
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('FORM_INVALID');
		$this->controller->extensions();
	}
}
