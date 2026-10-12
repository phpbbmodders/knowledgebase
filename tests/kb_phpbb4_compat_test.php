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
require_once dirname(__DIR__) . '/notification/type/avatar_compat.php';
require_once dirname(__DIR__) . '/notification/type/approve.php';
require_once dirname(__DIR__) . '/notification/type/disapprove.php';
require_once dirname(__DIR__) . '/notification/type/need_approval.php';

use phpbbmodders\knowledgebase\inc\functions_kb;
use phpbbmodders\knowledgebase\notification\type\approve;
use phpbbmodders\knowledgebase\notification\type\disapprove;
use phpbbmodders\knowledgebase\notification\type\need_approval;

/** Behaviour that differs between phpBB 3.3.x and 4.0, checked for both. */
class kb_phpbb4_compat_test extends \PHPUnit\Framework\TestCase
{
	/** The real types, each with only the version check overridden so both cores can be simulated. */
	private const TEST_SUBCLASS = [
		approve::class       => kb_test_approve::class,
		disapprove::class    => kb_test_disapprove::class,
		need_approval::class => kb_test_need_approval::class,
	];

	/** @var mixed What the fake user loader hands back for an avatar */
	private $loader_returns;

	/** Build a notification type whose core is pretended to be 3.3.x ($arrays false) or 4.0 ($arrays true). */
	private function type(string $class, bool $arrays, array $data)
	{
		$loader = $this->createMock(\phpbb\user_loader::class);
		$loader->method('get_avatar')->willReturnCallback(fn() => $this->loader_returns);

		$type = (new \ReflectionClass(self::TEST_SUBCLASS[$class]))->newInstanceWithoutConstructor();
		$type::$arrays = $arrays;
		$property = new \ReflectionProperty($class, 'user_loader');
		$property->setAccessible(true);
		$property->setValue($type, $loader);
		$type->set_initial_data(['notification_data' => serialize([
			'moderator'   => $data['moderator'] ?? 0,
			'author_id'   => $data['author_id'] ?? 0,
			'author_type' => $data['author_type'] ?? 0,
		])]);

		return $type;
	}

	/** @dataProvider avatar_types */
	public function test_the_avatar_is_returned_unchanged_whatever_shape_the_core_uses(string $class)
	{
		$this->loader_returns = '<img src="a.png">';
		$this->assertSame('<img src="a.png">', $this->type($class, false, ['moderator' => 2, 'author_id' => 2])->get_avatar());

		$this->loader_returns = ['avatar' => 'a.png', 'id' => 2];
		$this->assertSame(['avatar' => 'a.png', 'id' => 2], $this->type($class, true, ['moderator' => 2, 'author_id' => 2])->get_avatar());
	}

	public function avatar_types(): array
	{
		return [
			'approve'       => [approve::class],
			'disapprove'    => [disapprove::class],
			'need_approval' => [need_approval::class],
		];
	}

	public function test_a_group_author_has_no_avatar_in_the_shape_the_core_expects()
	{
		$this->loader_returns = 'must not be used';

		$this->assertSame('', $this->type(need_approval::class, false, ['author_id' => 7, 'author_type' => 1])->get_avatar());
		$this->assertSame([], $this->type(need_approval::class, true, ['author_id' => 7, 'author_type' => 1])->get_avatar());
	}

	public function test_the_four_oh_base_class_is_recognised_by_the_avatar_helper_it_gains()
	{
		$method = new \ReflectionMethod(approve::class, 'core_wants_avatar_arrays');
		$method->setAccessible(true);
		$type = (new \ReflectionClass(approve::class))->newInstanceWithoutConstructor();

		$this->assertSame(property_exists(\phpbb\notification\type\base::class, 'avatar_helper'), $method->invoke($type));
	}

	// The upload directory.

	private function upload_path(array $config): string
	{
		$kb = (new \ReflectionClass(functions_kb::class))->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty(functions_kb::class, 'config');
		$property->setAccessible(true);
		$property->setValue($kb, new \phpbb\config\config($config));

		return $kb->get_upload_path();
	}

	/** @dataProvider upload_settings */
	public function test_the_upload_directory_comes_from_whichever_setting_the_board_has($config, string $expected)
	{
		$this->assertSame($expected, $this->upload_path($config));
	}

	public function upload_settings(): array
	{
		return [
			'3.3.x upload_path'              => [['upload_path' => 'files'], 'files'],
			'3.3.x custom folder'            => [['upload_path' => 'uploads/kb'], 'uploads/kb'],
			'trailing slash dropped'         => [['upload_path' => 'files/'], 'files'],
			'4.0 storage path'               => [['storage\\attachment\\config\\path' => 'attachments'], 'attachments'],
			'4.0 wins nothing over 3.3.x'    => [['upload_path' => 'files', 'storage\\attachment\\config\\path' => 'attachments'], 'files'],
			'neither set'                    => [[], 'files'],
			'empty values'                   => [['upload_path' => '', 'storage\\attachment\\config\\path' => ''], 'files'],
		];
	}

	public function test_the_image_size_limits_may_be_missing_on_phpbb_four()
	{
		$source = file_get_contents(dirname(__DIR__) . '/textformatter/article_text.php');

		$this->assertStringContainsString("(int) (\$this->config['max_post_img_height'] ?? 0)", $source);
		$this->assertStringContainsString("(int) (\$this->config['max_post_img_width'] ?? 0)", $source);
	}
}

class kb_test_approve extends approve
{
	public static $arrays = false;

	protected function core_wants_avatar_arrays(): bool
	{
		return static::$arrays;
	}
}

class kb_test_disapprove extends disapprove
{
	public static $arrays = false;

	protected function core_wants_avatar_arrays(): bool
	{
		return static::$arrays;
	}
}

class kb_test_need_approval extends need_approval
{
	public static $arrays = false;

	protected function core_wants_avatar_arrays(): bool
	{
		return static::$arrays;
	}
}
