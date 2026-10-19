<?php

/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @copyright (c) 2017, Sheer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\migrations;

class version_1_0_3 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_1_0_2'];
	}

	public function effectively_installed()
	{
		// kb_version stays in the config on purpose: this check reads it, so a
		// board that already ran the original sheer/knowledgebase package skips
		// the steps that package did (see version_2_0_20 and
		// docs/upgrading-from-original.md).
		return
			(isset($this->config['knowledge_base_version']) &&
				version_compare($this->config['knowledge_base_version'], '1.0.3', '>=')) ||
			(isset($this->config['kb_version']) &&
				version_compare($this->config['kb_version'], '2.0.0', '>='));
	}

	public function update_schema()
	{
		return [
			'add_columns'  => [
				$this->table_prefix . 'kb_src_wrdmtch' => [
					'descr_match' => ['BOOL', 0],
				],
			],
			'drop_columns' => [
				$this->table_prefix . 'kb_src_wrdmtch' => [
					'reply_id',
				],
				$this->table_prefix . 'kb_articles' => [
					'bbcode_bitfield',
				],
			],
		];
	}

	public function revert_schema()
	{
		return [];
	}

	public function update_data()
	{
		return [
			// Update configs
			['config.update', ['knowledge_base_version', '1.0.3']],
			['custom', [[$this, 'add_key']]],
		];
	}

	public function add_key()
	{
		$table = $this->table_prefix . 'kb_src_wrdmtch';
		$sql_layer = $this->db->get_sql_layer();

		if (strpos($sql_layer, 'mysql') === 0)
		{
			// The old unique key is named un_mtch on phpBB 3.3 but carries a table
			// prefix (kbsrcwrdmt_un_mtch) on phpBB 4.0, so it is found by its
			// columns, not by that name.
			$drops = '';
			foreach ($this->old_unique_keys($table) as $key)
			{
				$drops .= 'DROP INDEX ' . $key . ', ';
			}

			// MySQL-specific combined DROP/ADD INDEX syntax with a
			// storage-engine index-type hint.
			$sql = 'ALTER TABLE ' . $table . ' ' . $drops . 'ADD UNIQUE un_mtch (article_id, word_id, title_match, descr_match) USING BTREE';
			$this->db->sql_query($sql);
		}
		else if (strpos($sql_layer, 'postgres') === 0 || strpos($sql_layer, 'sqlite') === 0)
		{
			// Same end result (a unique index across all four columns),
			// expressed as separate DDL statements since neither DBMS
			// supports MySQL's combined ALTER TABLE ... DROP/ADD INDEX form.
			$this->db->sql_query('DROP INDEX IF EXISTS un_mtch');
			$this->db->sql_query('CREATE UNIQUE INDEX un_mtch ON ' . $table . ' (article_id, word_id, title_match, descr_match)');
		}
		// Other DBMS (MSSQL, Oracle): left as-is rather than guessing at
		// syntax that can't be verified here - the table keeps whatever
		// index it already had.
	}

	/**
	 * The names of the table's unique keys, other than the primary key, that cover
	 * only some of the four match columns: the one this migration replaces.
	 *
	 * @param string $table
	 * @return string[]
	 */
	public function old_unique_keys($table)
	{
		$columns = [];
		$result = $this->db->sql_query('SHOW INDEX FROM ' . $table);
		while ($row = $this->db->sql_fetchrow($result))
		{
			if ((int) $row['Non_unique'] === 0 && $row['Key_name'] !== 'PRIMARY')
			{
				$columns[$row['Key_name']][] = $row['Column_name'];
			}
		}
		$this->db->sql_freeresult($result);

		$match_columns = ['article_id', 'word_id', 'title_match', 'descr_match'];
		$keys = [];
		foreach ($columns as $key => $key_columns)
		{
			if (!array_diff($key_columns, $match_columns) && in_array('word_id', $key_columns, true))
			{
				$keys[] = $key;
			}
		}

		return $keys;
	}
}
