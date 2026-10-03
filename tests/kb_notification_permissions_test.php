<?php
namespace phpbbmodders\knowledgebase\notification\type;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/notification/type/need_approval.php';
require_once dirname(__DIR__) . '/notification/type/approve.php';
require_once dirname(__DIR__) . '/notification/type/disapprove.php';
if (!defined('USER_GROUP_TABLE')) define('USER_GROUP_TABLE', 'user_group');
if (!defined('USER_FOUNDER')) define('USER_FOUNDER', 3);

class kb_notification_permissions_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider notification_types */
	public function test_never_only_excludes_the_requested_permission($class)
	{
		$pdo = new \PDO('sqlite::memory:');
		$pdo->exec('CREATE TABLE options (auth_option_id INTEGER, auth_option TEXT, is_local INTEGER)');
		$pdo->exec("INSERT INTO options VALUES (1, 'kb_m_approve', 1), (2, 'kb_u_add', 1)");
		$pdo->exec('CREATE TABLE kb_users (user_id INTEGER, auth_option_id INTEGER, category_id INTEGER, auth_setting INTEGER)');
		$pdo->exec('INSERT INTO kb_users VALUES (2, 2, 1, 0)');
		$pdo->exec('CREATE TABLE kb_groups (group_id INTEGER, auth_option_id INTEGER, category_id INTEGER, auth_setting INTEGER)');
		$pdo->exec('INSERT INTO kb_groups VALUES (1, 1, 1, 1)');
		$pdo->exec('CREATE TABLE ' . USER_GROUP_TABLE . ' (user_id INTEGER, group_id INTEGER, user_pending INTEGER)');
		$pdo->exec('INSERT INTO ' . USER_GROUP_TABLE . ' VALUES (2, 1, 0)');
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db->method('sql_query')->willReturnCallback(fn($sql) => $pdo->query($sql));
		$db->method('sql_fetchrow')->willReturnCallback(fn($result) => $result->fetch(\PDO::FETCH_ASSOC));
		$db->method('sql_in_set')->willReturnCallback(fn($column, $values) => $column . ' IN (' . implode(',', array_map('intval', $values)) . ')');
		$notification = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty($class, 'db');
		$property->setAccessible(true);
		$property->setValue($notification, $db);
		$user = $this->getMockBuilder(\phpbb\user::class)->disableOriginalConstructor()->getMock();
		$user->data = ['user_id' => 2, 'user_type' => 0];
		$property = new \ReflectionProperty($class, 'user');
		$property->setAccessible(true);
		$property->setValue($notification, $user);
		$notification->set_tables('options', 'kb_users', 'kb_groups');
		$this->assertSame([2], array_map('intval', $notification->check_permission('kb_m_approve', 1)));
		$pdo->exec('INSERT INTO kb_users VALUES (2, 1, 2, 0)');
		$this->assertSame([2], array_map('intval', $notification->check_permission('kb_m_approve', 1)));
		$pdo->exec('INSERT INTO kb_users VALUES (2, 1, 1, 0)');
		$this->assertSame([], $notification->check_permission('kb_m_approve', 1));
	}

	public function notification_types()
	{
		return [[need_approval::class], [approve::class], [disapprove::class]];
	}
}
