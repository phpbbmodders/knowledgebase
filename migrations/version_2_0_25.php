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

/**
 * Makes sure the native search's word-match table has one unique key over all four match
 * columns (article_id, word_id, title_match, descr_match) and no older, narrower one.
 *
 * Migrations 1.0.3 and 2.0.15 were meant to leave that key, but on phpBB 4.0 with
 * PostgreSQL or SQLite they leave the original three-column key (named with a table
 * prefix there, so the names they use do not find it) and no four-column one. A word in
 * both the title and the description of an article then fails to insert, silently, and the
 * article is left only partly indexed. The keys are found by their columns, so this works
 * on every phpBB version and database, and does nothing where the key is already right.
 *
 * kb_version stays: it is the version marker that earlier migrations read to
 * skip steps a board already has.
 */
class version_2_0_25 extends \phpbb\db\migration\migration
{
	/** The columns the unique key must cover, in order. */
	public const MATCH_COLUMNS = ['article_id', 'word_id', 'title_match', 'descr_match'];

	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_24'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'ensure_match_key']]],
			['config.update', ['kb_version', '2.0.25']],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['kb_version', '2.0.24']],
		];
	}

	/**
	 * Replace any narrower unique key on the word-match table by the four-column one.
	 *
	 * @return void
	 */
	public function ensure_match_key()
	{
		$table = $this->table_prefix . 'kb_src_wrdmtch';
		$layer = $this->db->get_sql_layer();
		$keys = $this->unique_keys($table, $layer);

		// Other databases (MSSQL, Oracle) are left as they are, as migration 1.0.3 does.
		if ($keys === null)
		{
			return;
		}

		foreach ($keys as $columns)
		{
			if ($columns === self::MATCH_COLUMNS)
			{
				return;
			}
		}

		foreach ($keys as $name => $columns)
		{
			// The older key: some of the match columns, always the word
			if (!array_diff($columns, self::MATCH_COLUMNS) && in_array('word_id', $columns, true))
			{
				$this->db->sql_query(strpos($layer, 'mysql') === 0 ? 'ALTER TABLE ' . $table . ' DROP INDEX ' . $name : 'DROP INDEX ' . $name);
			}
		}

		$columns = implode(', ', self::MATCH_COLUMNS);
		if (strpos($layer, 'mysql') === 0)
		{
			$this->db->sql_query('ALTER TABLE ' . $table . ' ADD UNIQUE un_mtch (' . $columns . ') USING BTREE');
		}
		else
		{
			$this->db->sql_query('CREATE UNIQUE INDEX un_mtch ON ' . $table . ' (' . $columns . ')');
		}
	}

	/**
	 * The table's unique keys, other than a primary key, by name with their columns in order.
	 *
	 * @param string $table
	 * @param string $layer The database layer name
	 * @return array|null Null for a database this does not read
	 */
	public function unique_keys($table, $layer)
	{
		$keys = [];

		if (strpos($layer, 'mysql') === 0)
		{
			$result = $this->db->sql_query('SHOW INDEX FROM ' . $table);
			while ($row = $this->db->sql_fetchrow($result))
			{
				if ((int) $row['Non_unique'] === 0 && $row['Key_name'] !== 'PRIMARY')
				{
					$keys[$row['Key_name']][(int) $row['Seq_in_index']] = $row['Column_name'];
				}
			}
			$this->db->sql_freeresult($result);
		}
		else if (strpos($layer, 'postgres') === 0)
		{
			$result = $this->db->sql_query("SELECT indexname, indexdef FROM pg_indexes WHERE tablename = '" . $this->db->sql_escape($table) . "'");
			while ($row = $this->db->sql_fetchrow($result))
			{
				if (stripos($row['indexdef'], 'CREATE UNIQUE INDEX') === 0 && preg_match('#\(([^()]+)\)\s*$#', $row['indexdef'], $match))
				{
					$keys[$row['indexname']] = array_map('trim', explode(',', $match[1]));
				}
			}
			$this->db->sql_freeresult($result);
		}
		else if (strpos($layer, 'sqlite') === 0)
		{
			$result = $this->db->sql_query('PRAGMA index_list(' . $table . ')');
			$indexes = [];
			while ($row = $this->db->sql_fetchrow($result))
			{
				if ((int) $row['unique'] === 1 && ($row['origin'] ?? 'c') !== 'pk')
				{
					$indexes[] = $row['name'];
				}
			}
			$this->db->sql_freeresult($result);

			foreach ($indexes as $name)
			{
				$result = $this->db->sql_query("PRAGMA index_info('" . $this->db->sql_escape($name) . "')");
				while ($row = $this->db->sql_fetchrow($result))
				{
					$keys[$name][(int) $row['seqno']] = $row['name'];
				}
				$this->db->sql_freeresult($result);
			}
		}
		else
		{
			return null;
		}

		foreach ($keys as $name => $columns)
		{
			ksort($columns);
			$keys[$name] = array_values($columns);
		}

		return $keys;
	}
}
