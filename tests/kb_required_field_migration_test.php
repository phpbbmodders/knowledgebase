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
require_once dirname(__DIR__) . '/migrations/version_2_0_19.php';

class kb_required_field_migration_test extends \PHPUnit\Framework\TestCase
{
	private function migration(bool $column_exists): version_2_0_19
	{
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$tools = $this->createMock(\phpbb\db\tools\tools_interface::class);
		$tools->method('sql_column_exists')->with('phpbb_kb_fields', 'field_required')->willReturn($column_exists);

		return new version_2_0_19(new \phpbb\config\config([]), $db, $tools, '', 'php', 'phpbb_', []);
	}

	public function test_it_adds_an_off_by_default_flag_to_the_fields_table_and_can_remove_it()
	{
		$migration = $this->migration(false);

		$this->assertSame(['add_columns' => ['phpbb_kb_fields' => ['field_required' => ['BOOL', 0]]]], $migration->update_schema());
		$this->assertSame(['drop_columns' => ['phpbb_kb_fields' => ['field_required']]], $migration->revert_schema());
		$this->assertSame([['config.update', ['kb_version', '2.0.19']]], $migration->update_data());
		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_18'], version_2_0_19::depends_on());
	}

	public function test_it_is_skipped_when_the_column_already_exists()
	{
		$this->assertFalse($this->migration(false)->effectively_installed());
		$this->assertTrue($this->migration(true)->effectively_installed());
	}
}
