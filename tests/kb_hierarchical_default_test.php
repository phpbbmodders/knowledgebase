<?php

/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_26.php';

/**
 * The flat article index is the default; the setting is now "Hierarchical category
 * browsing", off by default, and the 2.0.26 migration swaps the old setting for it.
 */
class kb_hierarchical_default_test extends \PHPUnit\Framework\TestCase
{
	private const ROOT = __DIR__ . '/..';

	private function migration(array $config): migrations\version_2_0_26
	{
		return new migrations\version_2_0_26(new \phpbb\config\config($config), $this->createMock(\phpbb\db\driver\driver_interface::class), $this->createMock(\phpbb\db\tools\tools_interface::class), '', 'php', 'phpbb_', []);
	}

	public function test_the_migration_adds_the_new_setting_off_and_removes_the_old_one_without_mapping_it()
	{
		$this->assertSame([
			['config.add', ['kb_hierarchical_index', 0]],
			['config.remove', ['kb_flat_index']],
			['config.update', ['kb_version', '2.0.26']],
		], $this->migration([])->update_data());
		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_25'], migrations\version_2_0_26::depends_on());
	}

	public function test_the_migration_is_skipped_once_the_setting_exists_and_reverts_cleanly()
	{
		$this->assertFalse($this->migration([])->effectively_installed());
		$this->assertTrue($this->migration(['kb_hierarchical_index' => 0])->effectively_installed());

		$reverted = array_map(fn($step) => $step[0] . ':' . $step[1][0], $this->migration([])->revert_data());
		$this->assertSame(['config.add:kb_flat_index', 'config.remove:kb_hierarchical_index', 'config.update:kb_version'], $reverted);
	}

	public function test_the_extension_version_matches_the_last_migration()
	{
		$composer = json_decode(file_get_contents(self::ROOT . '/composer.json'), true);

		$this->assertSame('2.0.26', $composer['version']);
	}

	/** @dataProvider code_that_reads_the_setting */
	public function test_nothing_outside_the_old_migrations_reads_the_removed_setting($path)
	{
		$this->assertStringNotContainsString('kb_flat_index', file_get_contents(self::ROOT . '/' . $path), $path);
	}

	public function code_that_reads_the_setting(): array
	{
		return [
			['controller/index.php'], ['controller/category.php'], ['controller/acp_controller.php'], ['event/listener.php'],
			['adm/style/acp_knowledgebase_config.html'], ['language/en/acp_knowledgebase.php'],
		];
	}

	public function test_the_index_and_the_category_page_choose_the_layout_from_the_hierarchical_setting()
	{
		$index = file_get_contents(self::ROOT . '/controller/index.php');
		$category = file_get_contents(self::ROOT . '/controller/category.php');

		// Home page: flat unless hierarchical browsing is on.
		$this->assertMatchesRegularExpression('/if \(!\$this->config\[\'kb_hierarchical_index\'\]\)\s*\{\s*redirect/', $index);
		// The all-categories page sends a hierarchical board to the tree, and every category page is flat unless it is on.
		$this->assertMatchesRegularExpression('/if \(\$this->config\[\'kb_hierarchical_index\'\]\)\s*\{\s*redirect\(\$this->helper->route\(\'phpbbmodders_knowledgebase_index\'\)\)/', $category);
		$this->assertStringContainsString("\$flat_layout = !\$this->config['kb_hierarchical_index'];", $category);
	}

	public function test_the_acp_offers_a_hierarchical_checkbox_that_is_unticked_by_default()
	{
		$acp = file_get_contents(self::ROOT . '/controller/acp_controller.php');
		$template = file_get_contents(self::ROOT . '/adm/style/acp_knowledgebase_config.html');
		$language = file_get_contents(self::ROOT . '/language/en/acp_knowledgebase.php');

		$this->assertStringContainsString('name="hierarchical_index" id="hierarchical_index" type="checkbox" value="1" {{ HIERARCHICAL_INDEX_ON }}', $template);
		$this->assertStringContainsString("'HIERARCHICAL_INDEX_ON'    => \$this->config['kb_hierarchical_index'] ? 'checked=\"checked\"' : ''", $acp);
		$this->assertStringContainsString("\$this->config->set('kb_hierarchical_index', \$this->request->variable('hierarchical_index', 0) ? 1 : 0);", $acp);
		foreach (['KB_HIERARCHICAL_INDEX', 'KB_HIERARCHICAL_INDEX_EXPLAIN'] as $key)
		{
			$this->assertStringContainsString("'" . $key . "'", $language, $key);
		}
	}
}
