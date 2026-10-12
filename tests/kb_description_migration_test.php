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
require_once dirname(__DIR__) . '/migrations/version_2_0_18.php';

class kb_description_migration_test extends \PHPUnit\Framework\TestCase
{
	private function migration(array $config): version_2_0_18
	{
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$tools = $this->createMock(\phpbb\db\tools\tools_interface::class);

		return new version_2_0_18(new \phpbb\config\config($config), $db, $tools, '', 'php', 'phpbb_', []);
	}

	public function test_it_adds_the_limit_at_the_column_width_and_records_the_version()
	{
		$this->assertSame(
			[['config.add', ['kb_description_max_length', 255]], ['config.update', ['kb_version', '2.0.18']]],
			$this->migration([])->update_data()
		);
		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_17'], version_2_0_18::depends_on());
	}

	public function test_it_is_skipped_when_the_setting_already_exists()
	{
		$this->assertFalse($this->migration([])->effectively_installed());
		$this->assertTrue($this->migration(['kb_description_max_length' => 120])->effectively_installed());
	}
}
