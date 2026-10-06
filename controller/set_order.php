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

namespace phpbbmodders\knowledgebase\controller;

use phpbb\config\config;
use phpbb\controller\helper;
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

	/** @var helper */
	protected helper $helper;

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
	 * @param helper            $helper
	 * @param request_interface $request
	 * @param user              $user
	 * @param functions_kb      $kb
	 * @param string            $articles_table
	 */
	public function __construct(
		driver_interface $db, config $config, helper $helper,
		request_interface $request, user $user, functions_kb $kb, string $articles_table
	)
	{
		$this->db = $db;
		$this->config = $config;
		$this->helper = $helper;
		$this->request = $request;
		$this->user = $user;
		$this->kb = $kb;
		$this->articles_table = $articles_table;
	}

	/**
	 * Moves one article up or down among its category's other
	 * articles, then sends the moderator back to the category listing
	 * they came from. A plain GET link (matching phpBB's own
	 * forum/category admin move-up/move-down controls) rather than the
	 * drag-and-drop UI this replaced - that never actually worked,
	 * since it relied on jQuery UI's sortable widget, which nothing in
	 * this extension or phpBB core loads.
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function main(): \Symfony\Component\HttpFoundation\Response
	{
		$article_id = $this->request->variable('k', 0);
		$cat_id = $this->request->variable('cat_id', 0);
		$direction = $this->request->variable('direction', '');
		$start = $this->request->variable('start', 0);

		if ($this->user->data['user_id'] == ANONYMOUS
			|| !$article_id
			|| !$cat_id
			|| !in_array($direction, ['up', 'down'], true)
			|| !check_link_hash($this->request->variable('hash', ''), 'kb_move_article'))
		{
			return new \Symfony\Component\HttpFoundation\Response('', 403);
		}

		if (!$this->kb->acl_kb_get($cat_id, 'kb_m_edit'))
		{
			return new \Symfony\Component\HttpFoundation\Response('', 403);
		}

		$this->kb->move_article_by($article_id, $cat_id, $direction);

		redirect($this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $cat_id, 'start' => $start]));
	}
}
