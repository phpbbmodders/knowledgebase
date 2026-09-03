<?php

/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2017, Sheer
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\controller;

use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\request\request_interface;
use phpbb\user;
use phpbbmodders\knowledgebase\inc\functions_kb;

class set_order
{
	/** @var driver_interface */
	protected driver_interface $db;

	/** @var config */
	protected config $config;

	/** @var request_interface */
	protected request_interface $request;

	/** @var user */
	protected user $user;

	/** @var functions_kb */
	protected functions_kb $kb;

	/** @var string */
	protected string $articles_table;

	/**
	 * Constructor
	 *
	 * @param driver_interface  $db
	 * @param config            $config
	 * @param request_interface $request
	 * @param user              $user
	 * @param functions_kb      $kb
	 * @param string            $articles_table
	 */
	public function __construct(
		driver_interface $db, config $config,
		request_interface $request, user $user, functions_kb $kb, string $articles_table
	)
	{
		$this->db = $db;
		$this->config = $config;
		$this->request = $request;
		$this->user = $user;
		$this->kb = $kb;
		$this->articles_table = $articles_table;
	}

	/**
	 * Persists the drag-and-drop article order submitted by
	 * reorder.js. Every article id in the submitted list is checked
	 * against the category the request claims to reorder, so a
	 * tampered list can only ever affect articles the caller already
	 * has kb_m_edit in that category for - it can't be used to touch
	 * articles in other categories.
	 *
	 * @return void
	 */
	public function main(): void
	{
		if ($this->user->data['user_id'] == ANONYMOUS || !$this->request->is_set_post('list_order'))
		{
			return;
		}

		if (!check_form_key('kb_reorder'))
		{
			return;
		}

		$cat_id = $this->request->variable('cat_id', 0, false, request_interface::POST);
		if (!$cat_id || !$this->kb->acl_kb_get($cat_id, 'kb_m_edit'))
		{
			return;
		}

		$list_order = $this->request->variable('list_order', '', false, request_interface::POST);
		$page = $this->request->variable('page', 0, false, request_interface::POST);
		$per_page = $this->config['kb_articles_per_page'];
		$ids = array_filter(array_map('intval', explode(',', $list_order)));
		if (empty($ids))
		{
			return;
		}

		// Only ids that actually belong to the claimed category are
		// reordered - anything else in the submitted list is ignored.
		$owned_ids = [];
		$sql = 'SELECT article_id
			FROM ' . $this->articles_table . '
			WHERE article_category_id = ' . $cat_id . '
				AND ' . $this->db->sql_in_set('article_id', $ids);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$owned_ids[(int) $row['article_id']] = true;
		}
		$this->db->sql_freeresult($result);

		$i = 1 + (($page - 1) * $per_page);
		foreach ($ids as $id)
		{
			if (isset($owned_ids[$id]))
			{
				$sql = 'UPDATE ' . $this->articles_table . ' SET display_order = ' . $i . ' WHERE article_id = ' . $id;
				$this->db->sql_query($sql);
			}
			$i++;
		}
	}
}
