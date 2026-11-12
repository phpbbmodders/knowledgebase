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
 * Moves the kinerity/knowledgebase extension's tables out of the way.
 *
 * kinerity uses the table names kb_articles and kb_categories with its own
 * columns, so version_1_0_0 could not create this extension's tables on a
 * board that ran it. version_1_0_0 depends on this migration: it copies
 * kinerity's tables to kb_kinerity_* backup tables and drops the originals.
 * version_2_0_32 later converts the backup into this extension's tables.
 * On any other board it changes nothing.
 */
class kinerity_tables_set_aside extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v310\dev'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'set_aside']]],
		];
	}

	/**
	 * The backup tables are kept when this extension is purged: they hold
	 * the only copy of the kinerity data.
	 */
	public function set_aside()
	{
		(new kinerity_import($this->db, $this->db_tools, $this->table_prefix))->set_aside();
	}
}
