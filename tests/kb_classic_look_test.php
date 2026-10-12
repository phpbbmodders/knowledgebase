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
require_once dirname(__DIR__) . '/migrations/version_2_0_21.php';

/** The 2.0.21 migration that added the classic look setting; 2.0.24 later replaced it (see kb_alternative_style_test). */
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
}
