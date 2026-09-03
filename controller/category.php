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

class category
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
	 * @param driver_interface  $db
	 * @param config            $config
	 * @param helper            $helper
	 * @param language          $language
	 * @param auth              $auth
	 * @param request_interface $request
	 * @param template          $template
	 * @param user              $user
	 * @param pagination        $pagination
	 * @param functions_kb      $kb
	 * @param string            $articles_table
	 * @param string            $categories_table
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
	 */
	public function cat(): \Symfony\Component\HttpFoundation\Response
	{
		if (!$this->auth->acl_get('u_kb_view') && !$this->auth->acl_get('a_manage_kb'))
		{
			trigger_error($this->language->lang('NOT_AUTHORISED'));
		}

		$cat_id = $this->request->variable('id', 0);
		$start = $this->request->variable('start', 0);

		// Flat index mode: id=0 renders every article across every
		// category instead of redirecting to the hierarchical index.
		$flat_all = false;
		if (!$cat_id)
		{
			if (!$this->config['kb_flat_index'])
			{
				redirect($this->helper->route('phpbbmodders_knowledgebase_index'));
			}
			$flat_all = true;
		}

		if (!$flat_all)
		{
			$sql = 'SELECT category_id, category_name
				FROM ' . $this->categories_table . '
				WHERE category_id = ' . (int) $cat_id;
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if (empty($row))
			{
				trigger_error('CAT_NO_EXISTS');
			}
		}

		$per_page = $this->config['kb_articles_per_page'];
		$sort_type = $this->config['kb_sort_type'];

		$order_by = ' ORDER BY a.display_order ASC';
		$s_sort_key = $s_sort_dir = '';
		$alphabet = [];
		$s_can_move = !$flat_all && !$sort_type && $this->kb->acl_kb_get($cat_id, 'kb_m_edit');
		if ($s_can_move)
		{
			add_form_key('kb_reorder');
		}

		if ($flat_all)
		{
			// Global visibility would let a moderator of one category see
			// unapproved articles from every other category too - only the
			// categories they actually moderate are exempted from the
			// approval filter, everything else still requires it.
			$moderatable = $this->kb->get_moderatable_categories();
			$sql_where = empty($moderatable) ? 'AND a.approved = 1' : 'AND (a.approved = 1 OR ' . $this->db->sql_in_set('a.article_category_id', $moderatable) . ')';
		}
		else
		{
			$sql_where = $this->kb->acl_kb_get($cat_id, 'kb_m_approve') ? '' : 'AND a.approved = 1';
		}
		$pagination_ary = ['id' => $cat_id];

		// Custom field filter: narrow the listing to articles carrying one
		// specific field value (e.g. a phpBB version tag).
		$filter_value_id = $this->request->variable('field_value_id', 0);
		if ($filter_value_id)
		{
			$sql_where .= ' AND a.article_id IN (SELECT article_id FROM ' . $this->kb->get_article_field_values_table() . ' WHERE value_id = ' . $filter_value_id . ')';
			$pagination_ary['field_value_id'] = $filter_value_id;
		}

		if ($sort_type == 1)
		{
			$sort_dir = $this->request->variable('sd', 'a');
			$sort_key = $this->request->variable('sk', 't');

			$sort_key_text = ['a' => $this->language->lang('AUTHOR'),
							  't' => $this->language->lang('POST_TIME'),
							  's' => $this->language->lang('SUBJECT'),
							  'v' => $this->language->lang('VIEWS')];
			$sort_by_sql = ['a' => 'a.author', 't' => 'a.article_date', 's' => 'LOWER(a.article_title)', 'v' => 'a.views'];
			$sort_dir_text = ['a' => $this->language->lang('ASCENDING'), 'd' => $this->language->lang('DESCENDING')];

			foreach ($sort_key_text as $key => $value)
			{
				$selected = ($sort_key == $key) ? ' selected="selected"' : '';
				$s_sort_key .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
			}

			foreach ($sort_dir_text as $key => $value)
			{
				$selected = ($sort_dir == $key) ? ' selected="selected"' : '';
				$s_sort_dir .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
			}

			$direction = (($sort_dir == 'd') ? 'DESC' : 'ASC');
			$order_by = ' ORDER BY ' . $sort_by_sql[$sort_key] . ' ';
			$order_by .= $direction;
			$pagination_ary = array_merge($pagination_ary, ['sd' => $sort_dir, 'sk' => $sort_key]);
		}
		else if ($sort_type == -1)
		{
			$alphabet = explode('-', $this->language->lang('ALPHABET'));
			$order_by = ' ORDER BY LOWER(a.article_title) ASC';
			$first_letter = $this->request->variable('l', '', true);
			$url = [];
			foreach ($alphabet as $letter)
			{
				$b_letter = ($first_letter === $letter) ? '<span class="alphabet">' . $letter . '</span>' : $letter;
				$url[] = '<a href="' . $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $cat_id, 'l' => $letter]) . '">' . $b_letter . '</a>';
			}
			$sql_where .= ' AND a.article_title LIKE "' . $this->db->sql_escape($first_letter) . '%"';

			$this->template->assign_vars([
					'ALPHA_URLS'     => $this->language->lang('ALPHABET_NAV') . '&nbsp;&nbsp;' . implode(' - ', $url) . '&nbsp;&nbsp;&nbsp;&nbsp;',
					'U_RESET_FILTER' => $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $cat_id]),
				]
			);
		}

		$category_name = $flat_all ? $this->language->lang('KB_ALL_CATEGORIES') : $row['category_name'];

		// Shared by the count and the listing below, so a cross-listed
		// article (Phase 2) is counted the same way it's actually listed -
		// otherwise pagination undercounts whenever cross-listings exist.
		$category_filter_sql = $flat_all ? '' : ('AND (a.article_category_id = ' . (int) $cat_id . '
			OR a.article_id IN (SELECT article_id FROM ' . $this->kb->get_article_categories_table() . ' WHERE category_id = ' . (int) $cat_id . '))');

		$sql = 'SELECT COUNT(a.article_id) as article_count
			FROM ' . $this->articles_table . ' a
			WHERE 1=1
			' . $category_filter_sql . '
			' . $sql_where;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$article_count = $row['article_count'];
		$this->db->sql_freeresult($result);

		$pagination_url = $this->helper->route('phpbbmodders_knowledgebase_category', $pagination_ary);
		if ($article_count)
		{
			$this->pagination->generate_template_pagination($pagination_url, 'pagination', 'start', $article_count, $per_page, $start);
		}
		$current_page_number = $this->pagination->get_on_page($per_page, $start);

		$this->template->assign_block_vars('navlinks', [
			'FORUM_NAME'   => $this->language->lang('LIBRARY'),
			'U_VIEW_FORUM' => $this->helper->route('phpbbmodders_knowledgebase_index'),
		]);

		if (!$flat_all)
		{
			foreach ($this->kb->get_category_branch($cat_id, 'parents') as $row)
			{
				$this->template->assign_block_vars('navlinks', [
					'FORUM_NAME'   => $row['category_name'],
					'U_VIEW_FORUM' => $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $row['category_id']]),
				]);
			}

			$sql = 'SELECT *
				FROM ' . $this->categories_table . '
				WHERE parent_id = ' . (int) $cat_id . '
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

				$where = ($this->kb->acl_kb_get($cat_row['category_id'], 'kb_m_approve')) ? '' : 'AND approved = 1';
				$sql = 'SELECT COUNT(article_id) AS articles
					FROM ' . $this->articles_table . '
					WHERE article_category_id = ' . (int) $cat_row['category_id'] . '
						' . $where;
				$res = $this->db->sql_query($sql);
				$art_count = (int) $this->db->sql_fetchfield('articles');
				$this->db->sql_freeresult($res);

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
					'CAT_ID'          => $cat_row['category_id'],
					'CAT_NAME'        => $cat_row['category_name'],
					'CAT_DESCRIPTION' => $cat_row['category_details'],
					'U_CAT'           => $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $cat_row['category_id']]),
					'ARTICLES'        => $art_count,
					'SUBCATS'         => $this->kb->get_cat_list($exclude_cats),
					'ARTICLE_TITLE'   => $art_row['article_title'] ?? '',
					'U_ARTICLE'       => (isset($art_row['article_id'])) ? $this->helper->route('phpbbmodders_knowledgebase_article', ['k' => $art_row['article_id']]) : '',
					'ARTICLE_TIME'    => (isset($art_row['article_date'])) ? $this->user->format_date($art_row['article_date']) : '',
					'ARTICLE_AUTHOR'  => (isset($art_row['author_id'])) ? get_username_string('full', $art_row['author_id'], $art_row['author'], $art_row['user_colour']) : '',
					'NEED_APPROVE'    => !($art_row['approved'] ?? false),
				]);
			}
		}

		if (!isset($per_page))
		{
			$per_page = 10;
		}

		$sql = 'SELECT a.*, u.user_colour, u.username
			FROM ' . $this->articles_table . ' a
			LEFT JOIN ' . USERS_TABLE . ' u ON (u.user_id = a.author_id AND a.author_type = 0)
			WHERE 1=1
			' . $category_filter_sql . '
			' . $sql_where
			. $order_by;

		$result = $this->db->sql_query_limit($sql, $per_page, $start);
		while ($art_row = $this->db->sql_fetchrow($result))
		{
			$art_id = $art_row['article_id'];
			$author_id = $art_row['author_id'] ?? ANONYMOUS;
			$article_categories = $this->kb->get_article_categories($art_id);
			$is_own_article = ((int) $art_row['author_type'] === 1)
				? $this->kb->is_team_member([(int) $author_id])
				: $this->user->data['user_id'] == $author_id;

			$this->template->assign_block_vars('art_row', [
				'ID'                  => $art_id,
				'ORDER_ID'            => $art_row['display_order'],
				'U_ARTICLE'           => $this->helper->route('phpbbmodders_knowledgebase_article', ['k' => $art_row['article_id']]),
				'ARTICLE_TITLE'       => $art_row['article_title'],
				'ARTICLE_AUTHOR'      => ((int) $art_row['author_type'] === 1) ? $art_row['author'] : get_username_string('full', $art_row['author_id'], $art_row['username'], $art_row['user_colour']),
				'ARTICLE_DESCRIPTION' => $art_row['article_description'],
				'ARTICLE_DATE'        => $this->user->format_date($art_row['article_date']),
				'ART_VIEWS'           => $art_row['views'],
				'U_DELETE'            => $this->helper->route('phpbbmodders_knowledgebase_posting', ['id' => $cat_id, 'mode' => 'delete', 'k' => $art_id]),
				'U_EDIT_ART'          => $this->helper->route('phpbbmodders_knowledgebase_posting', ['id' => $cat_id, 'mode' => 'edit', 'k' => $art_id]),
				'S_CAN_DELETE'        => $this->kb->acl_kb_get_any($article_categories, 'kb_m_delete') || ($is_own_article && $this->kb->acl_kb_get_any($article_categories, 'kb_u_delete')),
				'S_CAN_EDIT'          => $this->kb->acl_kb_get_any($article_categories, 'kb_m_edit') || ($is_own_article && $this->kb->acl_kb_get_any($article_categories, 'kb_u_edit')),
				'S_APPROVED'          => (bool) $art_row['approved'],
				'STATUS'              => (int) $art_row['status'],
				'S_CROSS_LISTED'      => !$flat_all && (int) $art_row['article_category_id'] !== (int) $cat_id,
				'S_IS_REDIRECT'       => (bool) $art_row['is_redirect'],
			]);
		}
		$this->db->sql_freeresult($result);

		$field_filter_options = '';
		foreach ($this->kb->get_enabled_fields() as $field)
		{
			$values = $this->kb->get_field_values((int) $field['field_id'], true);
			if (empty($values))
			{
				continue;
			}

			$field_filter_options .= '<optgroup label="' . htmlspecialchars($field['field_name']) . '">';
			foreach ($values as $value)
			{
				$selected = ((int) $value['value_id'] === $filter_value_id) ? ' selected="selected"' : '';
				$field_filter_options .= '<option value="' . (int) $value['value_id'] . '"' . $selected . '>' . htmlspecialchars($value['value_label']) . '</option>';
			}
			$field_filter_options .= '</optgroup>';
		}

		$this->template->assign_vars([
				'CATS_DROPBOX'        => $this->kb->make_category_dropbox(),
				'CATS_OPTIONS'        => $flat_all ? $this->kb->make_category_select(0, [], true) : '',
				'S_FIELD_FILTER'         => $field_filter_options !== '',
				'FIELD_FILTER_OPTIONS'   => $field_filter_options,
				'S_FIELD_FILTER_ACTIVE'  => (bool) $filter_value_id,
				'U_RESET_FIELD_FILTER'   => $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $cat_id]),
				'CATEGORY'            => $category_name,
				'CATEGORY_ID'         => $cat_id,
				'TOTAL_ITEMS'         => $this->language->lang('TOTAL_ITEMS', (int) $article_count),
				'PAGE_NUMBER'         => $this->pagination->on_page($article_count, $per_page, $start),
				'U_ADD_ARTICLE'       => $this->helper->route('phpbbmodders_knowledgebase_posting', ['id' => $cat_id]),
				'U_ADD_REDIRECT'      => $this->helper->route('phpbbmodders_knowledgebase_posting', ['id' => $cat_id, 'mode' => 'redirect']),
				'U_KB'                => $this->helper->route('phpbbmodders_knowledgebase_index'),
				'U_KB_SEARCH'         => $this->helper->route('phpbbmodders_knowledgebase_search'),
				'S_CAN_ADD'           => !$flat_all && $this->kb->acl_kb_get($cat_id, 'kb_u_add'),
				'S_CAN_ADD_REDIRECT'  => !$flat_all && ($this->auth->acl_get('a_manage_kb') || $this->kb->acl_kb_get($cat_id, 'kb_m_approve')),
				'S_FLAT_ALL'          => $flat_all,
				'S_ACTION'            => $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $cat_id]),
				'S_IS_SEARCH'         => (bool) $this->config['kb_search'],
				'S_KB_SEARCH_ACTION'  => $this->helper->route('phpbbmodders_knowledgebase_search'),
				'S_KNOWLEDGEBASE'     => true,
				'S_CAN_MOVE'          => $s_can_move,
				'CURRENT_PAGE_NUMBER' => $current_page_number,
				'S_SORT_OPTIONS'      => ($s_sort_key) ?: '',
				'S_ORDER_SELECT'      => ($s_sort_dir) ?: '',
				'S_ALPHABET'          => !empty($alphabet),
				'L_SORT'              => $this->language->lang('SUBMIT'),
			]
		);

		if (!$flat_all)
		{
			$this->kb->gen_kb_auth_level($cat_id);
		}

		$this->kb->assign_nav_tabs_vars('index', $cat_id);

		return $this->helper->render('@phpbbmodders_knowledgebase/kb_cat_body.html', ($this->language->lang('LIBRARY') . ' &raquo; ' . $this->language->lang('CATEGORY')));
	}
}
