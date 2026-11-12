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

use phpbbmodders\knowledgebase\inc\kinerity_import;

/**
 * Imports a board's kinerity/knowledgebase data, which migration
 * kinerity_tables_set_aside moved into kb_kinerity_* backup tables:
 * categories with their permissions, then articles, a batch per step. phpBB
 * runs the steps one after another (in the ACP, all in one page load), and
 * each finished batch is kept, so an import cut off by a time limit carries
 * on where it stopped when the extension is enabled again. Then
 * article counts, board-wide permissions and removal of what kinerity
 * registered. On a board that never ran kinerity it changes nothing.
 * See docs/upgrading-from-kinerity.md.
 */
class version_2_0_32 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_31'];
	}

	public function update_data()
	{
		$steps = [
			['custom', [[$this, 'import_categories']]],
			['custom', [[$this, 'import_articles']]],
			['custom', [[$this, 'finish_import']]],
		];

		// phpBB's own tools remove kinerity's permissions (with their
		// settings and roles) and its ACP modules; only on a board that ran it
		foreach (kinerity_import::KINERITY_PERMISSIONS as $permission)
		{
			$steps[] = ['if', [
				['custom', [[$this, 'has_kinerity_data']]],
				['permission.remove', [$permission]],
			]];
		}
		foreach (['ACP_KNOWLEDGEBASE_SETTINGS', 'ACP_KNOWLEDGEBASE_MANAGE'] as $module)
		{
			$steps[] = ['if', [
				['custom', [[$this, 'has_kinerity_data']]],
				['module.remove', ['acp', 'ACP_KNOWLEDGEBASE', $module]],
			]];
		}
		$steps[] = ['if', [
			['custom', [[$this, 'has_kinerity_data']]],
			['module.remove', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_KNOWLEDGEBASE']],
		]];

		$steps[] = ['config.update', ['kb_version', '2.0.32']];

		return $steps;
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'reset_import_markers']]],
			['config.update', ['kb_version', '2.0.31']],
		];
	}

	/**
	 * @param mixed $state
	 * @return array|true
	 */
	public function import_categories($state)
	{
		return $this->importer()->import_categories($state);
	}

	/**
	 * @param mixed $state
	 * @return array|true
	 */
	public function import_articles($state)
	{
		return $this->importer()->import_articles($state);
	}

	public function finish_import()
	{
		$this->importer()->finish($this->config);
	}

	/**
	 * @return bool Whether this board ran kinerity/knowledgebase
	 */
	public function has_kinerity_data()
	{
		return $this->importer()->has_backup();
	}

	public function reset_import_markers()
	{
		$this->importer()->reset_import_markers();
	}

	/**
	 * @return kinerity_import
	 */
	protected function importer()
	{
		return new kinerity_import($this->db, $this->db_tools, $this->table_prefix, (string) ($this->config['default_lang'] ?? 'en'));
	}
}
