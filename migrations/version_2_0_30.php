<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
/** Adds the category moderator permission for primary-author transfers. */
namespace phpbbmodders\knowledgebase\migrations;

class version_2_0_30 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\\phpbbmodders\\knowledgebase\\migrations\\version_2_0_29'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'add_permission']]],
			['config.update', ['kb_version', '2.0.30']],
		];
	}

	public function add_permission(): void
	{
		$result = $this->db->sql_query("SELECT auth_option_id FROM " . $this->table_prefix . "kb_options WHERE auth_option = 'kb_m_change_author'");
		$exists = $this->db->sql_fetchfield('auth_option_id');
		$this->db->sql_freeresult($result);
		if (!$exists)
		{
			// An explicit id, like the options version_1_0_0 inserted: those
			// left PostgreSQL's sequence at 1, so letting it pick one fails
			// with a duplicate key on a fresh PostgreSQL board
			$result = $this->db->sql_query('SELECT MAX(auth_option_id) AS max_id FROM ' . $this->table_prefix . 'kb_options');
			$next_id = (int) $this->db->sql_fetchfield('max_id') + 1;
			$this->db->sql_freeresult($result);

			// MSSQL only takes an explicit id with IDENTITY_INSERT on
			$identity_insert = strpos($this->db->get_sql_layer(), 'mssql') === 0;
			if ($identity_insert)
			{
				$this->db->sql_query('SET IDENTITY_INSERT ' . $this->table_prefix . 'kb_options ON');
			}
			$this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'kb_options ' . $this->db->sql_build_array('INSERT', [
				'auth_option_id' => $next_id, 'auth_option' => 'kb_m_change_author', 'is_global' => 0, 'is_local' => 1,
			]));
			if ($identity_insert)
			{
				$this->db->sql_query('SET IDENTITY_INSERT ' . $this->table_prefix . 'kb_options OFF');
			}
		}
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'remove_permission']]],
			['config.update', ['kb_version', '2.0.29']],
		];
	}

	public function remove_permission(): void
	{
		$result = $this->db->sql_query("SELECT auth_option_id FROM " . $this->table_prefix . "kb_options WHERE auth_option = 'kb_m_change_author'");
		$id = (int) $this->db->sql_fetchfield('auth_option_id');
		$this->db->sql_freeresult($result);
		if (!$id)
		{
			return;
		}
		foreach (['kb_users', 'kb_groups', 'kb_options'] as $table)
		{
			$this->db->sql_query('DELETE FROM ' . $this->table_prefix . $table . ' WHERE auth_option_id = ' . $id);
		}
	}
}
