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

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\db\driver\driver_interface;
use phpbb\language\language;
use phpbb\pagination;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;
use phpbbmodders\knowledgebase\inc\functions_kb;

class index
{
	/** @var driver_interface */
	protected driver_interface $db;

	/** @var config */
	protected config $config;

	/** @var helper */
	protected helper $helper;

	/** @var language */
	protected language $language;

	/** @var auth */
	protected auth $auth;

	/** @var request_interface */
	protected request_interface $request;

	/** @var template */
	protected template $template;

	/** @var user */
	protected user $user;

	/** @var pagination */
	protected pagination $pagination;

	/** @var functions_kb */
	protected functions_kb $kb;

	/** @var string */
	protected string $articles_table;

	/** @var string */
	protected string $categories_table;

	/**
	 * Constructor
	 *
	 * @param driver_interface   $db
	 * @param config             $config
	 * @param helper             $helper
	 * @param language           $language
	 * @param auth               $auth
	 * @param request_interface  $request
	 * @param template           $template
	 * @param user               $user
	 * @param pagination         $pagination
	 * @param functions_kb       $kb
	 * @param string             $articles_table
	 * @param string             $categories_table
	 */
	public function __construct(
		driver_interface $db, config $config, helper $helper, language $language, auth $auth,
		request_interface $request, template $template, user $user, pagination $pagination, functions_kb $kb,
		string $articles_table, string $categories_table
	)
	{
		$this->db = $db;
		$this->config = $config;
		$this->helper = $helper;
		$this->language = $language;
		$this->auth = $auth;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->pagination = $pagination;
		$this->kb = $kb;
		$this->articles_table = $articles_table;
		$this->categories_table = $categories_table;
	}

	/**
	 * @return \Symfony\Component\HttpFoundation\Response
	 * @throws \Exception
	 */
	public function main(): \Symfony\Component\HttpFoundation\Response
	{
		if (!$this->auth->acl_get('u_kb_view') && !$this->auth->acl_get('a_manage_kb'))
		{
			trigger_error($this->language->lang('NOT_AUTHORISED'));
		}

		if ($this->config['kb_flat_index'])
		{
			redirect($this->helper->route('phpbbmodders_knowledgebase_category', ['id' => 0]));
		}

		$sql = 'SELECT category_id, category_name, category_details, parent_id, number_articles
			FROM  ' . $this->categories_table . '
			WHERE parent_id = 0
			ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql);
		while ($cat_row = $this->db->sql_fetchrow($result))
		{
			$exclude_cats = [];
			foreach ($this->kb->get_category_branch($cat_row['category_id'], 'children') as $row)
			{
				$exclude_cats[] = $row['category_id'];
			}
			array_shift($exclude_cats);

			$sql_where = ($this->auth->acl_get('a_manage_kb') || $this->kb->acl_kb_get($cat_row['category_id'], 'kb_m_approve')) ? '' : 'AND approved = 1';
			$sql_where .= ' AND status <> ' . functions_kb::STATUS_DRAFT;
			$art_count = (int) $cat_row['number_articles'];

			$sql = 'SELECT a.article_id, a.article_title, a.article_date, a.author_id, a.author, a.author_type, a.approved, u.user_id, u.user_colour
					FROM ' . $this->articles_table . ' a
					LEFT JOIN ' . USERS_TABLE . ' u ON (a.author_id = u.user_id AND a.author_type = 0)
					WHERE a.article_category_id = ' . (int) $cat_row['category_id'] . '
					AND a.article_date =
						(SELECT MAX(article_date) AS max FROM ' . $this->articles_table . '
						WHERE article_category_id = ' . (int) $cat_row['category_id'] . ' ' . $sql_where . ')';
			$res = $this->db->sql_query($sql);
			$art_row = $this->db->sql_fetchrow($res);
			$this->db->sql_freeresult($res);

			$this->template->assign_block_vars('cat_row', [
				'U_CAT'           => $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $cat_row['category_id']]),
				'CAT_NAME'        => $cat_row['category_name'],
				'ARTICLES'        => $art_count,
				'CAT_DESCRIPTION' => $cat_row['category_details'],
				'SUBCATS'         => $this->kb->get_cat_list($exclude_cats),
				'ARTICLE_TITLE'   => $art_row['article_title'] ?? '',
				'U_ARTICLE'       => (isset($art_row['article_id'])) ? $this->helper->route('phpbbmodders_knowledgebase_article', ['k' => $art_row['article_id']]) : '',
				'ARTICLE_TIME'    => ($art_count) ? $this->user->format_date($art_row['article_date']) : '',
				'ARTICLE_AUTHOR'  => (isset($art_row['author_id'])) ? get_username_string('full', $art_row['author_id'], $art_row['author'], $art_row['user_colour']) : '',
				'NEED_APPROVE'    => !($art_row['approved'] ?? false),
			]);
		}
		$this->db->sql_freeresult($result);

		// Output the page
		$this->template->assign_vars([
			'LIBRARY_TITLE' => $this->language->lang('LIBRARY'),
		]);

		$this->template->assign_vars([
			'U_KB_SEARCH'        => $this->helper->route('phpbbmodders_knowledgebase_search'),
			'S_IS_SEARCH'        => (bool) $this->config['kb_search'],
			'S_KB_SEARCH_ACTION' => $this->helper->route('phpbbmodders_knowledgebase_search'),
			'CATS_DROPBOX'       => $this->kb->make_category_dropbox(),
			'S_KNOWLEDGEBASE'    => true,
		]);

		$this->template->assign_block_vars('navlinks', [
			'FORUM_NAME'   => $this->language->lang('LIBRARY'),
			'U_VIEW_FORUM' => $this->helper->route('phpbbmodders_knowledgebase_index'),
		]);

		$this->kb->assign_nav_tabs_vars('index');

		return $this->helper->render('@phpbbmodders_knowledgebase/kb_index_body.html', $this->language->lang('LIBRARY'));
	}

	/**
	 * "Manage Your Articles": a table of the current user's own
	 * submissions (including articles authored under a KB team group
	 * they belong to), across every category, with their status and
	 * pending-revision indicator.
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function my_articles(): \Symfony\Component\HttpFoundation\Response
	{
		if (!$this->auth->acl_get('u_kb_view') && !$this->auth->acl_get('a_manage_kb'))
		{
			trigger_error($this->language->lang('NOT_AUTHORISED'));
		}

		$start = $this->request->variable('start', 0);
		$per_page = $this->config['kb_articles_per_page'] ?: 10;
		$drafts_only = $this->request->variable('drafts_only', false);

		$user_id = (int) $this->user->data['user_id'];
		$team_group_ids = $this->kb->get_user_team_groups($user_id);

		$sql_where = '(a.author_type = 0 AND a.author_id = ' . $user_id . ')';
		if (!empty($team_group_ids))
		{
			$sql_where .= ' OR (a.author_type = 1 AND ' . $this->db->sql_in_set('a.author_id', $team_group_ids) . ')';
		}

		// Also count articles this user is a co-author on - directly, or
		// via one of their own team groups being credited as a co-author.
		$co_author_where = '(author_type = 0 AND author_ref_id = ' . $user_id . ')';
		if (!empty($team_group_ids))
		{
			$co_author_where .= ' OR (author_type = 1 AND ' . $this->db->sql_in_set('author_ref_id', $team_group_ids) . ')';
		}
		$sql_where .= ' OR a.article_id IN (SELECT article_id FROM ' . $this->kb->get_article_co_authors_table() . ' WHERE ' . $co_author_where . ')';

		// Wrapped so the optional drafts_only filter ANDs onto the whole
		// ownership condition above, not just its last OR clause.
		$sql_where = '(' . $sql_where . ')';
		if ($drafts_only)
		{
			$sql_where .= ' AND a.status = ' . functions_kb::STATUS_DRAFT;
		}

		$sql = 'SELECT COUNT(a.article_id) AS article_count
			FROM ' . $this->articles_table . ' a
			WHERE ' . $sql_where;
		$result = $this->db->sql_query($sql);
		$article_count = (int) $this->db->sql_fetchfield('article_count');
		$this->db->sql_freeresult($result);

		$pagination_url = $this->helper->route('phpbbmodders_knowledgebase_my_articles', $drafts_only ? ['drafts_only' => 1] : []);
		if ($article_count)
		{
			$this->pagination->generate_template_pagination($pagination_url, 'pagination', 'start', $article_count, $per_page, $start);
		}

		$sql = 'SELECT a.*
			FROM ' . $this->articles_table . ' a
			WHERE ' . $sql_where . '
			ORDER BY a.article_date DESC';
		$result = $this->db->sql_query_limit($sql, $per_page, $start);
		while ($art_row = $this->db->sql_fetchrow($result))
		{
			$art_id = (int) $art_row['article_id'];
			$status = (int) $art_row['status'];

			$this->template->assign_block_vars('art_row', [
				'ID'              => $art_id,
				'ARTICLE_TITLE'   => $art_row['article_title'],
				'U_ARTICLE'       => $this->helper->route('phpbbmodders_knowledgebase_article', ['k' => $art_id]),
				'ARTICLE_DATE'    => $this->user->format_date($art_row['article_date']),
				'EDIT_DATE'       => $art_row['edit_date'] ? $this->user->format_date($art_row['edit_date']) : '',
				'S_STATUS_NEW'      => $status === functions_kb::STATUS_NEW,
				'S_STATUS_REVIEWED' => $status === functions_kb::STATUS_REVIEWED,
				'S_STATUS_ACTIVE'   => $status === functions_kb::STATUS_ACTIVE,
				'S_STATUS_INACTIVE' => $status === functions_kb::STATUS_INACTIVE,
				'S_STATUS_DRAFT'    => $status === functions_kb::STATUS_DRAFT,
				'S_HAS_REVISION'    => in_array($status, [functions_kb::STATUS_ACTIVE, functions_kb::STATUS_INACTIVE], true) && $this->kb->article_has_pending_revision($art_id),
			]);
		}
		$this->db->sql_freeresult($result);

		$this->template->assign_vars([
			'TOTAL_ITEMS'         => $this->language->lang('TOTAL_ITEMS', $article_count),
			'CURRENT_PAGE_NUMBER' => $this->pagination->get_on_page($per_page, $start),
			'S_KNOWLEDGEBASE'     => true,
			'S_DRAFTS_ONLY'       => $drafts_only,
			'S_ACTION'            => $this->helper->route('phpbbmodders_knowledgebase_my_articles'),
		]);

		$this->template->assign_block_vars('navlinks', [
			'FORUM_NAME'   => $this->language->lang('LIBRARY'),
			'U_VIEW_FORUM' => $this->helper->route('phpbbmodders_knowledgebase_index'),
		]);

		$this->kb->assign_nav_tabs_vars('my_articles');

		return $this->helper->render('@phpbbmodders_knowledgebase/kb_my_articles_body.html', $this->language->lang('LIBRARY') . ' &raquo; ' . $this->language->lang('KB_TAB_MY_ARTICLES'));
	}

	/**
	 * Short alias so the knowledge base is reachable at /kb, not just
	 * /knowledgebase.
	 *
	 * @return void
	 */
	public function kb_alias(): void
	{
		redirect($this->helper->route('phpbbmodders_knowledgebase_index'));
	}
}
