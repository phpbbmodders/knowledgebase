<?php
namespace phpbbmodders\knowledgebase\migrations;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_21.php';
require_once dirname(__DIR__) . '/event/listener.php';

class kb_classic_look_test extends \PHPUnit\Framework\TestCase
{
	private function migration(array $config): version_2_0_21
	{
		return new version_2_0_21(new \phpbb\config\config($config), $this->createMock(\phpbb\db\driver\driver_interface::class), $this->createMock(\phpbb\db\tools\tools_interface::class), '', 'php', 'phpbb_');
	}

	public function test_the_setting_is_added_switched_off_and_the_version_recorded()
	{
		$migration = $this->migration([]);

		$this->assertSame([['config.add', ['kb_classic_look', 0]], ['config.update', ['kb_version', '2.0.21']]], $migration->update_data());
		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_20'], version_2_0_21::depends_on());
	}

	public function test_it_is_skipped_when_the_setting_already_exists()
	{
		$this->assertFalse($this->migration([])->effectively_installed());
		$this->assertTrue($this->migration(['kb_classic_look' => 1])->effectively_installed());
	}

	/** @dataProvider settings */
	public function test_the_page_header_tells_the_templates_whether_the_classic_look_is_on($config, bool $expected)
	{
		$assigned = [];
		$template = $this->createMock(\phpbb\template\template::class);
		$template->method('assign_vars')->willReturnCallback(function ($vars) use (&$assigned) {
			$assigned = $vars;
		});
		$auth = $this->createMock(\phpbb\auth\auth::class);
		$auth->method('acl_get')->willReturn(false);

		$listener = new \phpbbmodders\knowledgebase\event\listener(
			new \phpbb\config\config($config + ['kb_font_icon' => 'book']),
			$this->createMock(\phpbb\config\db_text::class),
			$this->createMock(\phpbb\controller\helper::class),
			$this->createMock(\phpbb\language\language::class),
			$auth,
			$template,
			'php'
		);
		$listener->add_page_header_link(new \stdClass());

		$this->assertSame($expected, $assigned['S_KB_CLASSIC_LOOK']);
	}

	public function settings(): array
	{
		return [
			'on'          => [['kb_classic_look' => '1'], true],
			'off'         => [['kb_classic_look' => '0'], false],
			'not set yet' => [[], false],
		];
	}
}
