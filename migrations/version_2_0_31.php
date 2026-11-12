<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
/** Rebuilds the search-match unique index with all four columns on phpBB 4.0. */
namespace phpbbmodders\knowledgebase\migrations;

class version_2_0_31 extends \phpbb\db\migration\migration
{
	/** Columns of the unique search-match index, as version_2_0_15 intended them */
	const MATCH_INDEX_COLUMNS = ['article_id', 'word_id', 'title_match', 'descr_match'];

	public static function depends_on()
	{
		return ['\\phpbbmodders\\knowledgebase\\migrations\\version_2_0_30'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'rebuild_match_index']]],
			['config.update', ['kb_version', '2.0.31']],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['kb_version', '2.0.30']],
		];
	}

	/**
	 * Drop and re-create the un_mtch unique index on kb_src_wrdmtch.
	 *
	 * version_2_0_15 drops and re-adds this index in one schema change. phpBB
	 * 4.0's Doctrine-based database tools apply that as a single diff in which
	 * dropping and re-adding an index with the same name cancel out, so the
	 * old three-column index stays. Doing it as two separate changes works on
	 * phpBB 3.3 and 4.0; on 3.3, where version_2_0_15 already worked, this
	 * rebuilds the same index.
	 *
	 * @return void
	 */
	public function rebuild_match_index(): void
	{
		$table = $this->table_prefix . 'kb_src_wrdmtch';
		if (!$this->db_tools->sql_table_exists($table) || !$this->db_tools->sql_column_exists($table, 'descr_match'))
		{
			return;
		}

		// On PostgreSQL, version_1_0_3 created the index as plain un_mtch, but
		// phpBB 3.3's tools find it by that name and then drop
		// {table}_un_mtch, which does not exist. Drop whichever is there by
		// its real name instead.
		if ($this->db->get_sql_layer() === 'postgres' && !($this->db_tools instanceof \phpbb\db\tools\doctrine))
		{
			// Only this table's: another table may own an index called un_mtch
			$result = $this->db->sql_query("SELECT indexname
				FROM pg_indexes
				WHERE tablename = '" . $this->db->sql_escape($table) . "'
					AND " . $this->db->sql_in_set('indexname', ['un_mtch', $table . '_un_mtch']));
			$indexes = array_column($this->db->sql_fetchrowset($result), 'indexname');
			$this->db->sql_freeresult($result);

			foreach ($indexes as $index)
			{
				$this->db->sql_query('DROP INDEX ' . $index);
			}
			$this->db_tools->sql_create_unique_index($table, 'un_mtch', self::MATCH_INDEX_COLUMNS);
			return;
		}

		// phpBB 4.0 stores the index under the shortened table name, for example
		// kbsrcwrdmtch_un_mtch, and only finds it by that full name
		$stored_name = 'un_mtch';
		if ($this->db_tools instanceof \phpbb\db\tools\doctrine)
		{
			$stored_name = \phpbb\db\doctrine\table_helper::generate_shortname('kb_src_wrdmtch') . '_un_mtch';
		}

		if ($this->db_tools->sql_unique_index_exists($table, $stored_name))
		{
			$this->db_tools->sql_index_drop($table, 'un_mtch');
		}
		$this->db_tools->sql_create_unique_index($table, 'un_mtch', self::MATCH_INDEX_COLUMNS);
	}
}
