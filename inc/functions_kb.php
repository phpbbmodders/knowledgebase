<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @copyright (c) 2023, sutrus ( update to phpBB 3.3 )
 * @copyright (c) 2017, Sheer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\inc;

use phpbb\auth\auth;
use phpbb\cache\driver\driver_interface as cache;
use phpbb\config\config;
use phpbb\config\db_text;
use phpbb\controller\helper;
use phpbb\db\driver\driver_interface;
use phpbb\extension\manager;
use phpbb\group\helper as group_helper;
use phpbb\language\language;
use phpbb\log\log;
use phpbb\template\template;
use phpbb\user;

class functions_kb
{
	public const STATUS_NEW = 0;
	public const STATUS_REVIEWED = 1;
	public const STATUS_ACTIVE = 2;
	public const STATUS_INACTIVE = 3;
	// Never submitted - visible only to its author/co-authors, never to
	// moderators or in any public listing or the moderation queue. Value
	// deliberately appended rather than inserted, so it never collides
	// with a status already stored for an existing article.
	public const STATUS_DRAFT = 4;

	/** Width of the article_description column. */
	public const DESCRIPTION_COLUMN_LENGTH = 255;

	public const AUTHOR_TYPE_USER = 0;
	public const AUTHOR_TYPE_GROUP = 1;

	/** @var driver_interface */
	protected driver_interface $db;

	/** @var config $config */
	protected config $config;

	/** @var db_text */
	protected db_text $config_text;

	/** @var helper */
	protected helper $helper;

	/** @var manager */
	protected manager $ext_manager;

	/** @var language */
	protected language $language;

	/** @var auth */
	protected auth $auth;

	/** @var template */
	protected template $template;

	/** @var user */
	protected user $user;

	/** @var cache $cache */
	protected cache $cache;

	/** @var log */
	protected log $log;

	/** @var string phpbb_root_path */
	protected string $phpbb_root_path;

	/** @var string php_ext */
	protected string $php_ext;

	/** @var string */
	protected string $articles_table;

	/** @var string */
	protected string $categories_table;

	/** @var string */
	protected string $kb_logs_table;

	/** @var string */
	protected string $attachments_table;

	/** @var string */
	protected string $options_table;

	/** @var string */
	protected string $kb_users_table;

	/** @var string */
	protected string $kb_groups_table;

	/** @var string */
	protected string $revisions_table;

	/** @var string */
	protected string $article_categories_table;

	/** @var string */
	protected string $fields_table;

	/** @var string */
	protected string $field_values_table;

	/** @var string */
	protected string $article_field_values_table;

	/** @var string */
	protected string $article_co_authors_table;

	/** @var string */
	protected string $draft_edits_table;

	/** @var group_helper */
	protected group_helper $group_helper;

	/** @var string */
	protected string $upload_dir;

	/** @var int[]|null */
	protected ?array $moderatable_categories_cache = null;

	/** @var int[] Memo for get_postable_category_id(), keyed by permission */
	protected array $postable_category_ids = [];

	/**
	 * Constructor
	 *
	 * @param driver_interface $db
	 * @param config           $config
	 * @param db_text          $config_text
	 * @param helper           $helper
	 * @param manager          $ext_manager
	 * @param language         $language
	 * @param auth             $auth
	 * @param template         $template
	 * @param user             $user
	 * @param cache            $cache
	 * @param log              $log
	 * @param string           $phpbb_root_path
	 * @param string           $php_ext
	 * @param string           $articles_table
	 * @param string           $categories_table
	 * @param string           $kb_logs_table
	 * @param string           $attachments_table
	 * @param string           $options_table
	 * @param string           $kb_users_table
	 * @param string           $kb_groups_table
	 * @param string           $revisions_table
	 * @param string           $article_categories_table
	 * @param string           $fields_table
	 * @param string           $field_values_table
	 * @param string           $article_field_values_table
	 * @param string           $article_co_authors_table
	 * @param string           $draft_edits_table
	 * @param group_helper     $group_helper
	 */
	public function __construct(
		driver_interface $db, config $config, db_text $config_text, helper $helper, manager $ext_manager,
		language $language, auth $auth, template $template, user $user, cache $cache, log $log,
		string $phpbb_root_path, string $php_ext, string $articles_table, string $categories_table,
		string $kb_logs_table, string $attachments_table, string $options_table, string $kb_users_table,
		string $kb_groups_table, string $revisions_table, string $article_categories_table,
		string $fields_table, string $field_values_table, string $article_field_values_table,
		string $article_co_authors_table, string $draft_edits_table, group_helper $group_helper
	)
	{
		$this->db = $db;
		$this->config = $config;
		$this->config_text = $config_text;
		$this->helper = $helper;
		$this->ext_manager = $ext_manager;
		$this->language = $language;
		$this->auth = $auth;
		$this->template = $template;
		$this->user = $user;
		$this->cache = $cache;
		$this->log = $log;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->articles_table = $articles_table;
		$this->categories_table = $categories_table;
		$this->kb_logs_table = $kb_logs_table;
		$this->attachments_table = $attachments_table;
		$this->options_table = $options_table;
		$this->kb_users_table = $kb_users_table;
		$this->kb_groups_table = $kb_groups_table;
		$this->revisions_table = $revisions_table;
		$this->article_categories_table = $article_categories_table;
		$this->fields_table = $fields_table;
		$this->field_values_table = $field_values_table;
		$this->article_field_values_table = $article_field_values_table;
		$this->article_co_authors_table = $article_co_authors_table;
		$this->draft_edits_table = $draft_edits_table;
		$this->group_helper = $group_helper;

		$this->upload_dir = upload_directory::path($this->phpbb_root_path);
		$this->log->set_log_table($this->kb_logs_table);
	}

	/**
	 * get_category_branch
	 *
	 * @param int         $category_id
	 * @param string|null $type
	 * @param string|null $order
	 * @param bool        $include_category
	 * @return array
	 */
	public function get_category_branch(int $category_id, string|null $type = null, string|null $order = null, bool $include_category = true): array
	{
		$order ??= 'descending';
		$type ??= 'all';
		$condition = match ($type)
		{
			'parents' => 'f1.left_id BETWEEN f2.left_id AND f2.right_id',
			'children' => 'f2.left_id BETWEEN f1.left_id AND f1.right_id',
			default => 'f2.left_id BETWEEN f1.left_id AND f1.right_id OR f1.left_id BETWEEN f2.left_id AND f2.right_id',
		};

		$rows = [];

		$sql = 'SELECT f2.*
			FROM ' . $this->categories_table . ' f1
			LEFT JOIN ' . $this->categories_table . ' f2 ON (' . $condition . ')
			WHERE f1.category_id = ' . $category_id . '
			ORDER BY f2.left_id ' . ($order === 'descending' ? 'ASC' : 'DESC');
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			if (!$include_category && $row['category_id'] == $category_id)
			{
				continue;
			}
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);
		return $rows;
	}

	/**
	 * @param array $ignore_id
	 * @return string
	 */
	public function get_cat_list(array $ignore_id): string
	{
		$right = 0;
		$padding_store = ['0' => ''];
		$padding = $cat_list = '';

		$sql = 'SELECT category_id, category_name, parent_id, left_id, right_id, number_articles
			FROM ' . $this->categories_table . '
			ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql, 600);

		while ($row = $this->db->sql_fetchrow($result))
		{
			if ($row['left_id'] < $right)
			{
				$padding .= '&nbsp; &nbsp;';
				$padding_store[$row['parent_id']] = $padding;
			}
			else if ($row['left_id'] > $right + 1)
			{
				$padding = (isset($padding_store[$row['parent_id']])) ? $padding_store[$row['parent_id']] : '';
			}

			$right = $row['right_id'];

			if ((in_array($row['category_id'], $ignore_id)) || $row['category_id'] == $ignore_id)
			{
				$sql_where = ($this->auth->acl_get('a_manage_kb') || $this->acl_kb_get($row['category_id'], 'kb_m_approve')) ? '' : 'AND approved = 1';
				$sql = 'SELECT COUNT(article_id) AS articles
					FROM ' . $this->articles_table . '
					WHERE article_category_id = ' . (int) $row['category_id'] . '
						' . $sql_where;
				$res = $this->db->sql_query($sql);
				$art_row = $this->db->sql_fetchrow($res);
				$this->db->sql_freeresult($res);
				// Same icon/class convention phpBB core uses for a
				// subforum link under its parent on the board index
				// (forumlist_body.html) - fa-file-o, icon-blue, icon-md -
				// so a subcategory reads as the same kind of thing a
				// visitor already recognises, not an extension-specific
				// look. No unread/read state to reflect (categories don't
				// track that), so always the "read" variant.
				$cat_list .= $padding . '<a href="' . $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $row['category_id']]) . '" class="subforum read">'
					. '<i class="' . icon::classes('file-o', 'icon-blue icon-md') . '" aria-hidden="true"></i>' . $row['category_name'] . '</a> ('
					. $this->language->lang('ARTICLES') . ': ' . $art_row['articles'] . ')<br>';
			}
		}
		$this->db->sql_freeresult($result);
		unset($padding_store);
		return $cat_list;
	}

	/**
	 * @param $category_id
	 * @return void
	 */
	public function gen_kb_auth_level($category_id): void
	{
		$rules = [
			($this->acl_kb_get($category_id, 'kb_u_add')) ? $this->language->lang('RULES_KB_ADD_CAN') : $this->language->lang('RULES_KB_ADD_CANNOT'),
		];

		if ($this->acl_kb_get($category_id, 'kb_m_delete'))
		{
			$rules = [...$rules, $this->language->lang('RULES_KB_DELETE_MOD_CAN')];
		}
		else
		{
			$rules = [...$rules, ($this->acl_kb_get($category_id, 'kb_u_delete')) ? $this->language->lang('RULES_KB_DELETE_CAN') : $this->language->lang('RULES_KB_DELETE_CANNOT')];
		}

		if ($this->acl_kb_get($category_id, 'kb_m_edit'))
		{
			$rules = [...$rules, $this->language->lang('RULES_KB_EDIT_MOD_CAN')];
		}
		else
		{
			$rules = [...$rules, ($this->acl_kb_get($category_id, 'kb_u_edit')) ? $this->language->lang('RULES_KB_EDIT_CAN') : $this->language->lang('RULES_KB_EDIT_CANNOT')];
		}

		if ($this->acl_kb_get($category_id, 'kb_m_approve'))
		{
			$rules = [...$rules, $this->language->lang('RULES_KB_APPROVE_MOD_CAN')];
		}

		foreach ($rules as $rule)
		{
			$this->template->assign_block_vars('rules', ['RULE' => $rule]);
		}
	}

	/**
	 * @param $category_id
	 * @param $permission
	 * @return bool
	 */
	public function acl_kb_get($category_id, $permission): bool
	{
		$sql = 'SELECT DISTINCT g.group_name, g.group_id, g.group_type
			FROM ' . GROUPS_TABLE . ' g
				LEFT JOIN ' . USER_GROUP_TABLE . ' ug ON (ug.group_id = g.group_id)
			WHERE ug.user_id = ' . (int) $this->user->data['user_id'] . '
				AND ug.user_pending = 0
				AND NOT (ug.group_leader = 1 AND g.group_skip_auth = 1)
			ORDER BY g.group_type DESC, g.group_id DESC';
		$result = $this->db->sql_query($sql);

		$groups = $user_groups = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$groups[$row['group_id']] = [
				'auth_setting' => ACL_NO,
			];
			$user_groups[] = $row['group_id'];
		}
		$this->db->sql_freeresult($result);

		$total = ACL_NO;

		if (count($groups))
		{
			// Get group auth settings
			$sql_ary[] = 'SELECT a.group_id, a.category_id, a.auth_setting, a.auth_option_id, ao.auth_option
					FROM ' . $this->kb_groups_table . ' a, ' . $this->options_table . ' ao
					WHERE a.auth_option_id = ao.auth_option_id AND ' . $this->db->sql_in_set('a.group_id', array_map('intval', $user_groups)) . '
						AND a.category_id = ' . $category_id . '
						AND ao.auth_option = \'' . $permission . '\'
					ORDER BY a.category_id, ao.auth_option';

			foreach ($sql_ary as $sql)
			{
				$result = $this->db->sql_query($sql);

				while ($row = $this->db->sql_fetchrow($result))
				{
					$hold_ary[$row['group_id']][$row['category_id']][$row['auth_option']] = $row['auth_setting'];
				}
				$this->db->sql_freeresult($result);
			}

			if (!empty($hold_ary))
			{
				foreach ($hold_ary as $group_id => $category_ary)
				{
					$groups[$group_id]['auth_setting'] = $category_ary[$category_id][$permission];
				}
				unset($hold_ary);
			}

			foreach ($groups as $row)
			{
				switch ($row['auth_setting'])
				{
					case ACL_NO:
					break;

					case ACL_YES:
						$total = ($total == ACL_NO) ? ACL_YES : $total;
					break;

					case ACL_NEVER:
						$total = ACL_NEVER;
					break;
				}
			}
		}

		// Grab user settings - non-role specific...
		$sql = 'SELECT a.user_id, a.category_id, a.auth_setting, a.auth_option_id, ao.auth_option
			FROM ' . $this->kb_users_table . ' a, ' . $this->options_table . ' ao
			WHERE a.auth_option_id = ao.auth_option_id
				AND a.user_id = ' . (int) $this->user->data['user_id'] . ' AND a.category_id = ' . (int) $category_id . ' AND ao.auth_option = \'' . $permission . '\'';

		$result = $this->db->sql_query($sql);
		$user_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		// Same ACL_NO/ACL_YES/ACL_NEVER precedence the permission trace
		// in acp_controller.php displays - a user setting is looked up
		// the same way there (defaulting to ACL_NO when no override row
		// exists, never trusting sql_fetchfield()'s false return, which
		// loosely equals ACL_NEVER (0) and would otherwise misroute an
		// absent override into the Never case below).
		$auth_setting = $user_row ? (int) $user_row['auth_setting'] : ACL_NO;

		switch ($auth_setting)
		{
			case ACL_NO:
				$total = ($total == ACL_NO) ? ACL_NEVER : $total;
			break;

			case ACL_YES:
				$total = ($total == ACL_NO) ? ACL_YES : $total;
			break;

			case ACL_NEVER:
				$total = ACL_NEVER;
			break;
		}

		// Take founder or admin status into account, overwriting the default values
		if ($this->user->data['user_type'] == USER_FOUNDER || $this->auth->acl_get('a_manage_kb'))
		{
			$total = ACL_YES;
		}
		return $total && $total > 0;
	}

	/**
	 * Does ANY of the given categories grant the permission? (permissive
	 * union, used for permission checks on an article that may be
	 * cross-listed into more than one category.)
	 *
	 * @param int[]  $category_ids
	 * @param string $permission
	 * @return bool
	 */
	public function acl_kb_get_any(array $category_ids, string $permission): bool
	{
		foreach ($category_ids as $category_id)
		{
			if ($this->acl_kb_get($category_id, $permission))
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Every category the current user can moderate (holds kb_m_approve
	 * in, or every category if they hold a_manage_kb). Used both for the
	 * flat article index's "show unapproved articles too" gate and for
	 * the Administration moderation queue.
	 *
	 * @return int[]
	 */
	public function get_moderatable_categories(): array
	{
		// Called from assign_nav_tabs_vars() (for the Administration tab's
		// visibility) and again by whichever controller rendered the page,
		// so memoize it for the life of this request - the underlying
		// per-category acl_kb_get() calls are still one-per-category, but
		// that set of queries now only runs once instead of twice.
		if ($this->moderatable_categories_cache !== null)
		{
			return $this->moderatable_categories_cache;
		}

		$sql = 'SELECT category_id FROM ' . $this->categories_table;
		$result = $this->db->sql_query($sql);

		$manage_all = $this->auth->acl_get('a_manage_kb');
		$category_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$category_id = (int) $row['category_id'];
			if ($manage_all || $this->acl_kb_get($category_id, 'kb_m_approve'))
			{
				$category_ids[] = $category_id;
			}
		}
		$this->db->sql_freeresult($result);

		$this->moderatable_categories_cache = $category_ids;

		return $category_ids;
	}

	/**
	 * The first category, in tree order, in which the current visitor holds a
	 * permission - where "Add article" leads from a page that has no category
	 * of its own (the all-articles view, the index, search, Manage Your
	 * Articles), since the posting form then lets them pick the category.
	 *
	 * @param string $permission kb_u_add to submit an article, kb_m_approve to submit a redirect
	 * @return int The category id, or 0 when there is none
	 */
	public function get_postable_category_id(string $permission = 'kb_u_add'): int
	{
		if (isset($this->postable_category_ids[$permission]))
		{
			return $this->postable_category_ids[$permission];
		}

		$sql = 'SELECT category_id
			FROM ' . $this->categories_table . '
			ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql);
		$category_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$category_ids[] = (int) $row['category_id'];
		}
		$this->db->sql_freeresult($result);

		$found = 0;
		foreach ($category_ids as $category_id)
		{
			if ($this->acl_kb_get($category_id, $permission))
			{
				$found = $category_id;
				break;
			}
		}

		return $this->postable_category_ids[$permission] = $found;
	}

	/**
	 * Assigns the shared six-tab (View Article Index / Manage Your
	 * Articles / Recent changes / Submit an Article / Submit a Redirect /
	 * Administration) navigation strip variables used by kb_nav_tabs.html on every
	 * front-end KB page. $cat_id, when known, is carried into the
	 * Submit tabs so they land in the same category the user is
	 * currently browsing rather than requiring a second category pick.
	 * $extra_tab_label adds a seventh, always-active tab after
	 * Administration - used only by the article page's dynamic
	 * "Reading Article #id" / "Notice" tab.
	 *
	 * @param string      $active          'index', 'my_articles', 'recent_changes', 'submit', 'redirect', 'admin', or '' for none of the six
	 * @param int         $cat_id
	 * @param string|null $extra_tab_label
	 * @return void
	 */
	public function assign_nav_tabs_vars(string $active, int $cat_id = 0, ?string $extra_tab_label = null): void
	{
		// A page with a category of its own links straight into it - but only
		// for a visitor who holds the matching permission (kb_u_add for
		// Submit, the stricter kb_m_approve for Submit a Redirect, the same
		// gate posting.php enforces; acl_kb_get() already folds in the
		// a_manage_kb/founder override), so a plain viewer isn't shown a tab
		// that only leads to a refusal page. A page with no category (the
		// all-articles view, the KB index, Manage Your Articles, search
		// results, the Administration queue) links into the first category
		// the visitor may post in: the posting form has a category select,
		// so that is only where it starts.
		$submit_cat_id = $cat_id ?: $this->get_postable_category_id('kb_u_add');
		$redirect_cat_id = $cat_id ?: $this->get_postable_category_id('kb_m_approve');
		$can_submit_tab = $submit_cat_id && $this->acl_kb_get($submit_cat_id, 'kb_u_add');
		$can_redirect_tab = $redirect_cat_id && $this->acl_kb_get($redirect_cat_id, 'kb_m_approve');

		$this->template->assign_vars([
			'U_KB_TAB_INDEX'         => $this->helper->route('phpbbmodders_knowledgebase_index'),
			'U_KB_TAB_MY_ARTICLES'   => $this->helper->route('phpbbmodders_knowledgebase_my_articles'),
			'U_KB_TAB_RECENT_CHANGES' => $this->helper->route('phpbbmodders_knowledgebase_recent_changes'),
			'U_KB_TAB_SUBMIT'        => $can_submit_tab ? $this->helper->route('phpbbmodders_knowledgebase_posting', ['id' => $submit_cat_id]) : '',
			'U_KB_TAB_REDIRECT'      => $can_redirect_tab ? $this->helper->route('phpbbmodders_knowledgebase_posting', ['id' => $redirect_cat_id, 'mode' => 'redirect']) : '',
			'U_KB_TAB_ADMIN'         => $this->helper->route('phpbbmodders_knowledgebase_queue'),
			'S_KB_CAN_SUBMIT_TAB'    => (bool) $can_submit_tab,
			'S_KB_CAN_REDIRECT_TAB'  => (bool) $can_redirect_tab,
			'S_KB_CAN_ADMIN_TAB'     => $this->auth->acl_get('a_manage_kb') || !empty($this->get_moderatable_categories()),
			'S_KB_TAB_INDEX'         => $active === 'index',
			'S_KB_TAB_MY_ARTICLES'   => $active === 'my_articles',
			'S_KB_TAB_RECENT_CHANGES' => $active === 'recent_changes',
			'S_KB_TAB_SUBMIT'        => $active === 'submit',
			'S_KB_TAB_REDIRECT'      => $active === 'redirect',
			'S_KB_TAB_ADMIN'         => $active === 'admin',
			'S_KB_EXTRA_TAB'         => $extra_tab_label !== null,
			'KB_EXTRA_TAB_LABEL'     => $extra_tab_label ?? '',
		]);
	}

	/**
	 * @return string
	 */
	public function get_article_categories_table(): string
	{
		return $this->article_categories_table;
	}

	/**
	 * @return string
	 */
	public function get_article_co_authors_table(): string
	{
		return $this->article_co_authors_table;
	}

	/**
	 * @return string
	 */
	public function get_draft_edits_table(): string
	{
		return $this->draft_edits_table;
	}

	/**
	 * @return string
	 */
	public function get_revisions_table(): string
	{
		return $this->revisions_table;
	}

	/**
	 * @return string
	 */
	public function get_fields_table(): string
	{
		return $this->fields_table;
	}

	/**
	 * @return string
	 */
	public function get_field_values_table(): string
	{
		return $this->field_values_table;
	}

	/**
	 * @return string
	 */
	public function get_article_field_values_table(): string
	{
		return $this->article_field_values_table;
	}

	/**
	 * Every category an article appears in: its primary category first,
	 * then any cross-listed categories.
	 *
	 * @param int $article_id
	 * @return int[]
	 */
	public function get_article_categories(int $article_id): array
	{
		$info = $this->get_kb_article_info($article_id);
		return array_merge([(int) $info['article_category_id']], $this->get_cross_listed_categories($article_id));
	}

	/**
	 * Only the cross-listed (non-primary) categories an article appears in.
	 *
	 * @param int $article_id
	 * @return int[]
	 */
	public function get_cross_listed_categories(int $article_id): array
	{
		$category_ids = [];

		$sql = 'SELECT category_id
			FROM ' . $this->article_categories_table . '
			WHERE article_id = ' . $article_id;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$category_ids[] = (int) $row['category_id'];
		}
		$this->db->sql_freeresult($result);

		return $category_ids;
	}

	/**
	 * Replace an article's full set of cross-listed categories. The
	 * article's own primary category is silently excluded if present,
	 * since it's already implied and shouldn't be duplicated.
	 *
	 * @param int   $article_id
	 * @param int[] $category_ids
	 * @return void
	 */
	public function set_cross_listed_categories(int $article_id, array $category_ids): void
	{
		$primary_category_id = (int) $this->get_kb_article_info($article_id)['article_category_id'];

		$sql = 'DELETE FROM ' . $this->article_categories_table . '
			WHERE article_id = ' . $article_id;
		$this->db->sql_query($sql);

		$category_ids = array_unique(array_diff(array_map('intval', $category_ids), [$primary_category_id]));
		if (empty($category_ids))
		{
			return;
		}

		$sql_ary = [];
		foreach ($category_ids as $category_id)
		{
			$sql_ary[] = [
				'article_id'  => $article_id,
				'category_id' => $category_id,
			];
		}
		$this->db->sql_multi_insert($this->article_categories_table, $sql_ary);
	}

	/**
	 * Whether a user counts as one of an article's authors - its primary
	 * author_id/author_type (kb_articles), or a co-author user, or an
	 * approved (not pending) member of a co-author group. Matches the
	 * existing single-author is_own_article pattern (a direct id compare
	 * for a user, is_team_member() for a group) rather than flattening
	 * every author to a user-id set first, so this stays one targeted
	 * query against kb_article_co_authors plus (at most) one
	 * is_team_member() call - no query against group membership unless
	 * the article actually has a group among its authors.
	 *
	 * @param int      $article_id
	 * @param int      $primary_author_id   The article's own author_id (caller already has this loaded).
	 * @param int      $primary_author_type AUTHOR_TYPE_USER or AUTHOR_TYPE_GROUP.
	 * @param int|null $user_id             Defaults to the current user.
	 * @return bool
	 */
	public function is_article_author(int $article_id, int $primary_author_id, int $primary_author_type, ?int $user_id = null): bool
	{
		$user_id ??= (int) $this->user->data['user_id'];

		if ($primary_author_type === self::AUTHOR_TYPE_USER && $primary_author_id === $user_id)
		{
			return true;
		}

		if ($primary_author_type === self::AUTHOR_TYPE_GROUP && $this->is_team_member([$primary_author_id], $user_id))
		{
			return true;
		}

		$sql = 'SELECT author_type, author_ref_id
			FROM ' . $this->article_co_authors_table . '
			WHERE article_id = ' . $article_id;
		$result = $this->db->sql_query($sql);

		$group_ids = [];
		$is_co_author = false;
		while ($row = $this->db->sql_fetchrow($result))
		{
			if ((int) $row['author_type'] === self::AUTHOR_TYPE_USER)
			{
				if ((int) $row['author_ref_id'] === $user_id)
				{
					$is_co_author = true;
				}
			}
			else
			{
				$group_ids[] = (int) $row['author_ref_id'];
			}
		}
		$this->db->sql_freeresult($result);

		return $is_co_author || (!empty($group_ids) && $this->is_team_member($group_ids, $user_id));
	}

	/**
	 * is_article_author() for many articles at once - a listing page.
	 * One query for co-authors across every article (not one per row)
	 * and one query for the viewer's own team-group memberships (not
	 * one is_team_member() call per row) instead of the per-article
	 * query pattern is_article_author() itself uses, which is the right
	 * tradeoff for a single article but not for a whole page of them.
	 *
	 * @param array    $articles Each element needs 'article_id', 'author_id', 'author_type'.
	 * @param int|null $user_id  Defaults to the current user.
	 * @return array<int, bool> article_id => is_author
	 */
	public function is_article_author_bulk(array $articles, ?int $user_id = null): array
	{
		$user_id ??= (int) $this->user->data['user_id'];

		$article_ids = array_map(static fn ($article) => (int) $article['article_id'], $articles);
		$co_authors = $this->get_co_authors_bulk($article_ids);
		$viewer_team_groups = $this->get_user_team_groups($user_id);

		$result = [];
		foreach ($articles as $article)
		{
			$article_id = (int) $article['article_id'];
			$author_id = (int) $article['author_id'];
			$author_type = (int) $article['author_type'];

			$is_author = ($author_type === self::AUTHOR_TYPE_USER && $author_id === $user_id)
				|| ($author_type === self::AUTHOR_TYPE_GROUP && in_array($author_id, $viewer_team_groups, true));

			if (!$is_author && isset($co_authors[$article_id]))
			{
				$is_author = in_array($user_id, $co_authors[$article_id]['users'], true)
					|| !empty(array_intersect($co_authors[$article_id]['groups'], $viewer_team_groups));
			}

			$result[$article_id] = $is_author;
		}

		return $result;
	}

	/**
	 * An article's co-authors (everyone besides its primary author),
	 * split by type - for pre-filling the moderator edit form and for
	 * get_co_authors_display() below.
	 *
	 * @param int $article_id
	 * @return array{users: int[], groups: int[]}
	 */
	public function get_co_authors(int $article_id): array
	{
		return $this->get_co_authors_bulk([$article_id])[$article_id] ?? ['users' => [], 'groups' => []];
	}

	/**
	 * get_co_authors() for many articles at once - one query instead of
	 * one per article, for a listing page.
	 *
	 * @param int[] $article_ids
	 * @return array<int, array{users: int[], groups: int[]}>
	 */
	public function get_co_authors_bulk(array $article_ids): array
	{
		$map = [];
		foreach ($article_ids as $article_id)
		{
			$map[(int) $article_id] = ['users' => [], 'groups' => []];
		}

		if (empty($map))
		{
			return $map;
		}

		$sql = 'SELECT article_id, author_type, author_ref_id
			FROM ' . $this->article_co_authors_table . '
			WHERE ' . $this->db->sql_in_set('article_id', array_keys($map));
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$article_id = (int) $row['article_id'];
			if ((int) $row['author_type'] === self::AUTHOR_TYPE_USER)
			{
				$map[$article_id]['users'][] = (int) $row['author_ref_id'];
			}
			else
			{
				$map[$article_id]['groups'][] = (int) $row['author_ref_id'];
			}
		}
		$this->db->sql_freeresult($result);

		return $map;
	}

	/**
	 * Replace an article's full set of co-authors. Only ever called for
	 * a moderator setting/changing authors directly (add, or a
	 * moderator-performed edit) - never from the revision-approval path,
	 * since a revision cannot change an article's authors.
	 *
	 * @param int   $article_id
	 * @param int[] $user_ids            Co-author user ids.
	 * @param int[] $group_ids           Co-author group ids (must already be configured KB team groups).
	 * @param int   $primary_author_id   Excluded if it appears in $user_ids/$group_ids - already implied, shouldn't be duplicated.
	 * @param int   $primary_author_type
	 * @return void
	 */
	public function set_co_authors(int $article_id, array $user_ids, array $group_ids, int $primary_author_id, int $primary_author_type): void
	{
		$sql = 'DELETE FROM ' . $this->article_co_authors_table . '
			WHERE article_id = ' . $article_id;
		$this->db->sql_query($sql);

		$user_ids = array_unique(array_map('intval', $user_ids));
		if ($primary_author_type === self::AUTHOR_TYPE_USER)
		{
			$user_ids = array_diff($user_ids, [$primary_author_id]);
		}

		$group_ids = array_unique(array_map('intval', $group_ids));
		if ($primary_author_type === self::AUTHOR_TYPE_GROUP)
		{
			$group_ids = array_diff($group_ids, [$primary_author_id]);
		}

		$sql_ary = [];
		foreach ($user_ids as $user_id)
		{
			$sql_ary[] = [
				'article_id'    => $article_id,
				'author_type'   => self::AUTHOR_TYPE_USER,
				'author_ref_id' => $user_id,
			];
		}
		foreach ($group_ids as $group_id)
		{
			$sql_ary[] = [
				'article_id'    => $article_id,
				'author_type'   => self::AUTHOR_TYPE_GROUP,
				'author_ref_id' => $group_id,
			];
		}

		if (!empty($sql_ary))
		{
			$this->db->sql_multi_insert($this->article_co_authors_table, $sql_ary);
		}
	}

	/**
	 * Resolves typed forum usernames to user ids, the same
	 * case/whitespace-insensitive way phpBB itself compares usernames
	 * (via username_clean).
	 *
	 * @param string[] $usernames As typed, e.g. from a comma-separated field.
	 * @return array{user_ids: int[], unresolved: string[]} unresolved holds back the original typed text for any name that didn't match a real user.
	 */
	public function resolve_usernames(array $usernames): array
	{
		$clean_to_original = [];
		foreach ($usernames as $username)
		{
			$clean_to_original[utf8_clean_string($username)] = $username;
		}

		if (empty($clean_to_original))
		{
			return ['user_ids' => [], 'unresolved' => []];
		}

		$sql = 'SELECT user_id, username_clean
			FROM ' . USERS_TABLE . '
			WHERE ' . $this->db->sql_in_set('username_clean', array_keys($clean_to_original));
		$result = $this->db->sql_query($sql);

		$user_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$user_ids[] = (int) $row['user_id'];
			unset($clean_to_original[$row['username_clean']]);
		}
		$this->db->sql_freeresult($result);

		return ['user_ids' => $user_ids, 'unresolved' => array_values($clean_to_original)];
	}

	/**
	 * Usernames for a set of user ids, for pre-filling the co-author
	 * text field when (re-)opening the edit form for an article that
	 * already has co-authors - the reverse of resolve_usernames().
	 *
	 * @param int[] $user_ids
	 * @return string[]
	 */
	public function get_usernames(array $user_ids): array
	{
		if (empty($user_ids))
		{
			return [];
		}

		$sql = 'SELECT username
			FROM ' . USERS_TABLE . '
			WHERE ' . $this->db->sql_in_set('user_id', array_map('intval', $user_ids));
		$result = $this->db->sql_query($sql);

		$usernames = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$usernames[] = $row['username'];
		}
		$this->db->sql_freeresult($result);

		return $usernames;
	}

	/**
	 * Every co-author, resolved for direct template output - user
	 * co-authors linked to their profile, group co-authors in their
	 * colour and linked to their member list, matching how phpbb.com
	 * displays multiple credited authors.
	 *
	 * @param int $article_id
	 * @return array<int, array{type: int, name: string, colour: string, url: string}>
	 */
	public function get_co_authors_display(int $article_id): array
	{
		return $this->get_co_authors_display_bulk([$article_id])[$article_id] ?? [];
	}

	/**
	 * get_co_authors_display() for many articles at once - the users
	 * and groups tables are each queried once for the whole set, not
	 * once per article, for a listing page.
	 *
	 * @param int[] $article_ids
	 * @return array<int, array<int, array{type: int, name: string, colour: string, url: string}>>
	 */
	public function get_co_authors_display_bulk(array $article_ids): array
	{
		$co_authors = $this->get_co_authors_bulk($article_ids);

		$all_user_ids = $all_group_ids = [];
		foreach ($co_authors as $entry)
		{
			$all_user_ids = array_merge($all_user_ids, $entry['users']);
			$all_group_ids = array_merge($all_group_ids, $entry['groups']);
		}
		$all_user_ids = array_unique($all_user_ids);
		$all_group_ids = array_unique($all_group_ids);

		$users_by_id = [];
		if (!empty($all_user_ids))
		{
			$sql = 'SELECT user_id, username, user_colour
				FROM ' . USERS_TABLE . '
				WHERE ' . $this->db->sql_in_set('user_id', $all_user_ids);
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$users_by_id[(int) $row['user_id']] = [
					'type'   => self::AUTHOR_TYPE_USER,
					'name'   => $row['username'],
					'colour' => $row['user_colour'],
					'url'    => append_sid("{$this->phpbb_root_path}memberlist.{$this->php_ext}", 'mode=viewprofile&u=' . (int) $row['user_id']),
				];
			}
			$this->db->sql_freeresult($result);
		}

		$groups_by_id = [];
		if (!empty($all_group_ids))
		{
			$sql = 'SELECT group_id, group_name, group_colour
				FROM ' . GROUPS_TABLE . '
				WHERE ' . $this->db->sql_in_set('group_id', $all_group_ids);
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$groups_by_id[(int) $row['group_id']] = [
					'type'   => self::AUTHOR_TYPE_GROUP,
					'name'   => $this->group_helper->get_name($row['group_name']),
					'colour' => $row['group_colour'],
					'url'    => append_sid("{$this->phpbb_root_path}memberlist.{$this->php_ext}", 'mode=group&g=' . (int) $row['group_id']),
				];
			}
			$this->db->sql_freeresult($result);
		}

		$display = [];
		foreach ($co_authors as $article_id => $entry)
		{
			$rows = [];
			foreach ($entry['users'] as $user_id)
			{
				if (isset($users_by_id[$user_id]))
				{
					$rows[] = $users_by_id[$user_id];
				}
			}
			foreach ($entry['groups'] as $group_id)
			{
				if (isset($groups_by_id[$group_id]))
				{
					$rows[] = $groups_by_id[$group_id];
				}
			}
			$display[$article_id] = $rows;
		}

		return $display;
	}

	/**
	 * Bulk-resolves user_colour/group_colour for a page of rows that
	 * each carry an author_id/author_type - one query per type instead
	 * of one per row. Feed the result to get_author_display() below.
	 *
	 * @param array $rows any rows with author_id/author_type columns
	 *                    (kb_articles rows, or article_co_authors-style
	 *                    entries)
	 * @return array<string, string> keyed 'u<id>'/'g<id>' => colour hex
	 */
	public function get_author_colours_bulk(array $rows): array
	{
		$user_ids = $group_ids = [];
		foreach ($rows as $row)
		{
			if ((int) $row['author_type'] === self::AUTHOR_TYPE_GROUP)
			{
				$group_ids[] = (int) $row['author_id'];
			}
			else
			{
				$user_ids[] = (int) $row['author_id'];
			}
		}

		$colours = [];
		if (!empty($user_ids))
		{
			$sql = 'SELECT user_id, user_colour
				FROM ' . USERS_TABLE . '
				WHERE ' . $this->db->sql_in_set('user_id', array_unique($user_ids));
			$result = $this->db->sql_query($sql);
			while ($user_row = $this->db->sql_fetchrow($result))
			{
				$colours['u' . $user_row['user_id']] = $user_row['user_colour'];
			}
			$this->db->sql_freeresult($result);
		}
		if (!empty($group_ids))
		{
			$sql = 'SELECT group_id, group_colour
				FROM ' . GROUPS_TABLE . '
				WHERE ' . $this->db->sql_in_set('group_id', array_unique($group_ids));
			$result = $this->db->sql_query($sql);
			while ($group_row = $this->db->sql_fetchrow($result))
			{
				$colours['g' . $group_row['group_id']] = $group_row['group_colour'];
			}
			$this->db->sql_freeresult($result);
		}

		return $colours;
	}

	/**
	 * A row's primary author as a coloured link to their profile (a
	 * user) or their group's memberlist page (a group-authored
	 * article, e.g. one posted under a KB team's name) - the same
	 * link/colour convention get_co_authors_display_bulk() above
	 * already uses for a group listed as a co-author.
	 *
	 * @param array                 $row     author_id/author_type/author,
	 *                                       plus username/user_colour if
	 *                                       already joined (used in
	 *                                       preference to a fresh query)
	 * @param array<string, string> $colours from get_author_colours_bulk(),
	 *                                       or [] to resolve a lone group
	 *                                       row with one query as needed
	 * @return string
	 */
	public function get_author_display(array $row, array $colours = []): string
	{
		$author_id = (int) $row['author_id'];

		if ((int) $row['author_type'] === self::AUTHOR_TYPE_GROUP)
		{
			if (array_key_exists('g' . $author_id, $colours))
			{
				$url = append_sid("{$this->phpbb_root_path}memberlist.{$this->php_ext}", 'mode=group&g=' . $author_id);
				$colour = $colours['g' . $author_id];

				return '<a href="' . $url . '"' . ($colour ? ' style="color: #' . $colour . ';"' : '') . '>' . $row['author'] . '</a>';
			}

			// No pre-resolved colour (a single row, not a bulk-fetched
			// page) - look this one group up on its own.
			$sql = 'SELECT group_name, group_colour
				FROM ' . GROUPS_TABLE . '
				WHERE group_id = ' . $author_id;
			$result = $this->db->sql_query($sql);
			$group_row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if (!$group_row)
			{
				return $row['author'];
			}

			$url = append_sid("{$this->phpbb_root_path}memberlist.{$this->php_ext}", 'mode=group&g=' . $author_id);
			$colour = $group_row['group_colour'];

			return '<a href="' . $url . '"' . ($colour ? ' style="color: #' . $colour . ';"' : '') . '>' . $this->group_helper->get_name($group_row['group_name']) . '</a>';
		}

		$colour = $colours['u' . $author_id] ?? ($row['user_colour'] ?? '');
		$name = $row['username'] ?? $row['author'];

		return get_username_string('full', $author_id, $name, $colour);
	}

	/**
	 * Every enabled custom field.
	 *
	 * @return array
	 */
	public function get_enabled_fields(): array
	{
		$fields = [];
		$sql = 'SELECT *
			FROM ' . $this->fields_table . '
			WHERE enabled = 1';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$fields[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $fields;
	}

	/**
	 * @param int $field_id
	 * @return array|null
	 */
	public function get_field(int $field_id): ?array
	{
		$sql = 'SELECT *
			FROM ' . $this->fields_table . '
			WHERE field_id = ' . $field_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	/**
	 * Every field defined, enabled or not - for the ACP field list.
	 *
	 * @return array
	 */
	public function get_all_fields(): array
	{
		$fields = [];
		$sql = 'SELECT *
			FROM ' . $this->fields_table . '
			ORDER BY field_id ASC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$fields[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $fields;
	}

	/**
	 * @param int  $field_id
	 * @param bool $enabled_only
	 * @return array
	 */
	public function get_field_values(int $field_id, bool $enabled_only = false): array
	{
		$values = [];
		$sql = 'SELECT *
			FROM ' . $this->field_values_table . '
			WHERE field_id = ' . $field_id .
			($enabled_only ? ' AND enabled = 1' : '') . '
			ORDER BY display_order ASC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$values[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $values;
	}

	/**
	 * Whether a required custom field was left without a value.
	 *
	 * A field only counts as missing when it is marked required, has at
	 * least one enabled value to choose from (otherwise nobody could ever
	 * satisfy it), and none of the submitted ids is one of those values.
	 * Submitted ids that are not enabled values of the field are ignored,
	 * the same way saving them would drop them.
	 *
	 * @param array $field            The field row, including field_required
	 * @param array $enabled_values   The field's enabled value rows
	 * @param int[] $submitted_ids    The value ids the author ticked
	 * @return bool
	 */
	public static function is_required_field_missing(array $field, array $enabled_values, array $submitted_ids): bool
	{
		if (empty($field['field_required']) || empty($enabled_values))
		{
			return false;
		}

		$valid_ids = array_map(function ($value) {
			return (int) $value['value_id'];
		}, $enabled_values);

		return !array_intersect(array_map('intval', $submitted_ids), $valid_ids);
	}

	/**
	 * The value rows (not just ids) assigned to an article for one field.
	 *
	 * @param int $article_id
	 * @param int $field_id
	 * @return array
	 */
	public function get_article_field_values(int $article_id, int $field_id): array
	{
		$values = [];
		$sql = 'SELECT v.*
			FROM ' . $this->article_field_values_table . ' a, ' . $this->field_values_table . ' v
			WHERE a.article_id = ' . $article_id . '
				AND a.value_id = v.value_id
				AND v.field_id = ' . $field_id;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$values[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $values;
	}

	/**
	 * Just the value_ids assigned to an article for one field, for
	 * pre-checking the posting-form widget.
	 *
	 * @param int $article_id
	 * @param int $field_id
	 * @return int[]
	 */
	public function get_article_field_value_ids(int $article_id, int $field_id): array
	{
		return array_map(function ($value) {
			return (int) $value['value_id'];
		}, $this->get_article_field_values($article_id, $field_id));
	}

	/**
	 * Replace an article's assigned values for one field. Submitted
	 * value_ids are validated against the field's actual enabled values -
	 * anything else is silently dropped, same defense-in-depth style as
	 * set_cross_listed_categories().
	 *
	 * @param int   $article_id
	 * @param int   $field_id
	 * @param int[] $value_ids
	 * @return void
	 */
	public function set_article_field_values(int $article_id, int $field_id, array $value_ids): void
	{
		$valid_value_ids = array_map(function ($value) {
			return (int) $value['value_id'];
		}, $this->get_field_values($field_id, true));

		$value_ids = array_intersect(array_map('intval', $value_ids), $valid_value_ids);

		$sql = 'DELETE FROM ' . $this->article_field_values_table . '
			WHERE article_id = ' . $article_id . '
				AND ' . $this->db->sql_in_set('value_id', $valid_value_ids ?: [0]);
		$this->db->sql_query($sql);

		if (empty($value_ids))
		{
			return;
		}

		$sql_ary = [];
		foreach ($value_ids as $value_id)
		{
			$sql_ary[] = [
				'article_id' => $article_id,
				'value_id'   => $value_id,
			];
		}
		$this->db->sql_multi_insert($this->article_field_values_table, $sql_ary);
	}

	/**
	 * Minimal validation for a Redirect entry's target URL. Rejects
	 * empty/unparseable values and, when a scheme is present, anything
	 * other than http/https - this value is later echoed back as a
	 * redirect target, so javascript:/data:/vbscript: etc. must not be
	 * accepted. A scheme-less (board-relative) value is allowed.
	 *
	 * @param string $url
	 * @return bool
	 */
	public function is_valid_redirect_url(string $url): bool
	{
		if ($url === '')
		{
			return false;
		}

		$parts = parse_url($url);
		if ($parts === false)
		{
			return false;
		}

		return !isset($parts['scheme']) || in_array(strtolower($parts['scheme']), ['http', 'https'], true);
	}

	/**
	 * @return string
	 */
	public function make_category_dropbox(): string
	{
		$sql = 'SELECT * FROM ' . $this->categories_table . ' ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql, 600);

		$cats_list = '';
		$padding = '';
		$padding_store = [];
		$right = 0;

		while ($row = $this->db->sql_fetchrow($result))
		{
			if ($row['left_id'] < $right)
			{
				$padding .= '&nbsp;&nbsp;';
				$padding_store[$row['parent_id']] = $padding;
			}
			else if ($row['left_id'] > $right + 1)
			{
				$padding = !isset($padding_store[$row['parent_id']]) ? '' : $padding_store[$row['parent_id']];
			}
			$right = $row['right_id'];
			$category_parent = $row['parent_id'] == 0;

			$class = $category_parent ? ' class="jumpbox-cat-link"' : ' class="jumpbox-forum-link"';
			$category_name = ($category_parent) ? $padding . $row['category_name'] : $padding . '&raquo;&nbsp;&nbsp;' . $row['category_name'];
			$cats_list .= '<li><a href="' . $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $row['category_id']]) . '" ' . $class . '>' . $category_name . '</a></li>';
		}
		$this->db->sql_freeresult($result);
		unset($padding_store);
		return $cats_list;
	}

	/**
	 * @param       $select_id
	 * @param array $ignore_id
	 * @param bool  $ignore_acl
	 * @return string
	 */
	public function make_category_select($select_id, array $ignore_id, bool $ignore_acl = false, string $permission = 'kb_u_add'): string
	{
		$sql = 'SELECT * FROM ' . $this->categories_table . ' ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql, 600);

		$cats_list = '';
		$padding = '';
		$padding_store = [];
		$right = 0;

		while ($row = $this->db->sql_fetchrow($result))
		{
			if ($row['left_id'] < $right)
			{
				$padding .= '&nbsp;&nbsp;';
				$padding_store[$row['parent_id']] = $padding;
			}
			else if ($row['left_id'] > $right + 1)
			{
				$padding = !isset($padding_store[$row['parent_id']]) ? '' : $padding_store[$row['parent_id']];
			}
			$right = $row['right_id'];
			$disabled = false;

			if (!$ignore_acl &&
				(in_array($row['category_id'], $ignore_id) || !$this->acl_kb_get($row['category_id'], $permission))
			)
			{
				$disabled = true;
			}

			$category_name = $padding . '&nbsp;&nbsp;' . $row['category_name'];
			$selected = is_array($select_id) ? (in_array($row['category_id'], $select_id) ? ' selected="selected"' : '') : ($row['category_id'] == $select_id ? ' selected="selected"' : '');
			$cats_list .= '<option value="' . $row['category_id'] . '"' . ($disabled ? ' disabled="disabled" class="disabled-option"' : $selected) . '>' . $category_name . '</option>';
		}
		$this->db->sql_freeresult($result);
		unset($padding_store);
		return $cats_list;
	}

	/**
	 * @return array
	 */
	public function allowed_extension(): array
	{
		$enabled_extensions = [];
		$extensions = json_decode($this->config_text->get('kb_extensions'), true);

		foreach ($extensions as $extension)
		{
			$enabled_extensions = array_merge($enabled_extensions, array_values($extension));
		}
		return $enabled_extensions;
	}

	/**
	 * Get the group_ids designated as KB teams via the ACP settings page.
	 *
	 * @return int[]
	 */
	public function get_kb_team_groups(): array
	{
		$groups = json_decode($this->config_text->get('kb_team_groups'), true);
		return is_array($groups) ? array_map('intval', $groups) : [];
	}

	/**
	 * Of every KB team group, only the ones the given (or current) user is
	 * actually a member of - used to scope "Manage Your Articles" to
	 * teams the viewer really belongs to, rather than every configured
	 * team.
	 *
	 * @param int|null $user_id
	 * @return int[]
	 */
	public function get_user_team_groups(?int $user_id = null): array
	{
		$team_group_ids = $this->get_kb_team_groups();
		if (empty($team_group_ids))
		{
			return [];
		}

		$user_id ??= (int) $this->user->data['user_id'];

		$sql = 'SELECT group_id
			FROM ' . USER_GROUP_TABLE . '
			WHERE user_id = ' . $user_id . '
				AND user_pending = 0
				AND ' . $this->db->sql_in_set('group_id', $team_group_ids);
		$result = $this->db->sql_query($sql);

		$group_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$group_ids[] = (int) $row['group_id'];
		}
		$this->db->sql_freeresult($result);

		return $group_ids;
	}

	/**
	 * Is the given (or current) user a member of any of the given groups?
	 *
	 * @param int[]    $group_ids
	 * @param int|null $user_id
	 * @return bool
	 */
	public function is_team_member(array $group_ids, ?int $user_id = null): bool
	{
		if (empty($group_ids))
		{
			return false;
		}

		$user_id ??= (int) $this->user->data['user_id'];

		$sql = 'SELECT ug.user_id
			FROM ' . USER_GROUP_TABLE . ' ug
			WHERE ug.user_id = ' . (int) $user_id . '
				AND ug.user_pending = 0
				AND ' . $this->db->sql_in_set('ug.group_id', $group_ids);
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return (bool) $row;
	}

	/**
	 * All current member user_ids of the given group.
	 *
	 * @param int $group_id
	 * @return int[]
	 */
	public function get_group_member_ids(int $group_id): array
	{
		$user_ids = [];

		$sql = 'SELECT user_id
			FROM ' . USER_GROUP_TABLE . '
			WHERE group_id = ' . $group_id . '
				AND user_pending = 0';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$user_ids[] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		return $user_ids;
	}

	/**
	 * Does the given article have a pending revision awaiting approval?
	 *
	 * @param int $article_id
	 * @return bool
	 */
	public function article_has_pending_revision(int $article_id): bool
	{
		return (bool) $this->get_article_revision($article_id);
	}

	/**
	 * @param int $article_id
	 * @return array|null
	 */
	public function get_article_revision(int $article_id): ?array
	{
		$sql = 'SELECT *
			FROM ' . $this->revisions_table . '
			WHERE article_id = ' . $article_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	/**
	 * Store (or replace) the pending revision for an article. At most one
	 * pending revision is kept per article.
	 *
	 * @param int    $article_id
	 * @param array  $fields article_title, article_description, article_body,
	 *                       bbcode_uid, bbcode_bitfield
	 * @param int    $submitted_by
	 * @return void
	 */
	public function save_article_revision(int $article_id, array $fields, int $submitted_by): void
	{
		$this->delete_article_revision($article_id);

		$sql_ary = array_merge($fields, [
			'article_id'     => $article_id,
			'submitted_by'   => $submitted_by,
			'submitted_date' => time(),
		]);

		$sql = 'INSERT INTO ' . $this->revisions_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
		$this->db->sql_query($sql);
	}

	/**
	 * @param int $article_id
	 * @return void
	 */
	public function delete_article_revision(int $article_id): void
	{
		$sql = 'DELETE FROM ' . $this->revisions_table . ' WHERE article_id = ' . $article_id;
		$this->db->sql_query($sql);
	}

	/**
	 * @param int $article_id
	 * @return bool
	 */
	public function article_has_draft_edit(int $article_id): bool
	{
		return (bool) $this->get_article_draft_edit($article_id);
	}

	/**
	 * A private draft of a further edit to an already-submitted article -
	 * entirely separate from a real pending revision (kb_article_revisions),
	 * which may already exist for the same article at the same time.
	 *
	 * @param int $article_id
	 * @return array|null
	 */
	public function get_article_draft_edit(int $article_id): ?array
	{
		$sql = 'SELECT *
			FROM ' . $this->draft_edits_table . '
			WHERE article_id = ' . $article_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	/**
	 * Store (or replace) the draft-of-an-edit for an article. At most one
	 * is kept per article, same as a pending revision.
	 *
	 * @param int   $article_id
	 * @param array $fields article_title, article_description, article_body,
	 *                      bbcode_uid, bbcode_bitfield, redirect_url
	 * @param int   $edited_by
	 * @return void
	 */
	public function save_article_draft_edit(int $article_id, array $fields, int $edited_by): void
	{
		$this->delete_article_draft_edit($article_id);

		$sql_ary = array_merge($fields, [
			'article_id'  => $article_id,
			'edited_by'   => $edited_by,
			'edited_date' => time(),
		]);

		$sql = 'INSERT INTO ' . $this->draft_edits_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
		$this->db->sql_query($sql);
	}

	/**
	 * @param int $article_id
	 * @return void
	 */
	public function delete_article_draft_edit(int $article_id): void
	{
		$sql = 'DELETE FROM ' . $this->draft_edits_table . ' WHERE article_id = ' . $article_id;
		$this->db->sql_query($sql);
	}

	/**
	 * The longest article description, in characters, that the posting form
	 * accepts: the "kb_description_max_length" setting, kept between 1 and
	 * the width of the article_description column.
	 *
	 * @return int
	 */
	public function get_description_max_length(): int
	{
		$configured = (int) ($this->config['kb_description_max_length'] ?? self::DESCRIPTION_COLUMN_LENGTH);

		return max(1, min(self::DESCRIPTION_COLUMN_LENGTH, $configured ?: self::DESCRIPTION_COLUMN_LENGTH));
	}

	/**
	 * Why a submitted description cannot be saved, or an empty string if it
	 * can. The description arrives HTML-escaped (request->variable()), so its
	 * length as the author sees it is measured on the decoded text, and the
	 * escaped text must also still fit the database column - "&", "<", ">"
	 * and quotes each take several characters there.
	 *
	 * @param string $description The description as read from the request
	 * @return string A translated error message, or '' when it is acceptable
	 */
	public function get_description_error(string $description): string
	{
		$length = mb_strlen(htmlspecialchars_decode($description, ENT_QUOTES), 'UTF-8');
		$max = $this->get_description_max_length();

		if ($length > $max)
		{
			return $this->language->lang('KB_DESCRIPTION_TOO_LONG', $length, $max);
		}

		if (mb_strlen($description, 'UTF-8') > self::DESCRIPTION_COLUMN_LENGTH)
		{
			return $this->language->lang('KB_DESCRIPTION_TOO_MANY_SPECIAL');
		}

		return '';
	}

	/**
	 * Put plain text into a post that has already been parsed, so BBCode,
	 * smilies and links typed into it stay literal instead of being
	 * formatted. The post is written with a placeholder where each value
	 * belongs, parsed, and only then are the placeholders swapped for the
	 * XML-escaped text.
	 *
	 * @param string $parsed_post  The post as generate_text_for_storage() left it (XML)
	 * @param array  $plain_values Placeholder => text as stored in the database (HTML-escaped)
	 * @return string The post with every placeholder replaced
	 */
	public static function insert_plain_text(string $parsed_post, array $plain_values): string
	{
		$replacements = [];
		foreach ($plain_values as $placeholder => $escaped_text)
		{
			$replacements[$placeholder] = htmlspecialchars(htmlspecialchars_decode((string) $escaped_text, ENT_QUOTES), ENT_NOQUOTES | ENT_XML1, 'UTF-8');
		}

		// One pass, so text inserted for one value is never searched for
		// another placeholder.
		return strtr($parsed_post, $replacements);
	}

	/**
	 * The usergroup authors are told to contact with questions about their
	 * articles (the "contact group" setting), or null when none is set or the
	 * chosen group no longer exists.
	 *
	 * @return array{id: int, name: string, colour: string, url: string}|null The group's id, display
	 *                                         name, colour (six hex digits, or empty) and a link to its member list
	 */
	public function get_contact_group(): ?array
	{
		$group_id = (int) ($this->config['kb_contact_group'] ?? 0);
		if ($group_id <= 0)
		{
			return null;
		}

		$sql = 'SELECT group_id, group_name, group_colour
			FROM ' . GROUPS_TABLE . '
			WHERE group_id = ' . $group_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return null;
		}

		return [
			'id'     => (int) $row['group_id'],
			'name'   => $this->group_helper->get_name($row['group_name']),
			'colour' => preg_match('/^[0-9a-f]{6}$/i', (string) $row['group_colour']) ? (string) $row['group_colour'] : '',
			'url'    => append_sid($this->phpbb_root_path . 'memberlist.' . $this->php_ext, 'mode=group&amp;g=' . $group_id),
		];
	}

	/**
	 * KB team group_id => display name, for the assign-to-team dropdown.
	 * Uses group_helper::get_name() (same as acp_controller.php) so
	 * special/translated group names resolve exactly as they do
	 * everywhere else in phpBB.
	 *
	 * @return array<int, string>
	 */
	public function get_kb_team_group_names(): array
	{
		$group_ids = $this->get_kb_team_groups();
		if (empty($group_ids))
		{
			return [];
		}

		$names = [];
		$sql = 'SELECT group_id, group_name
			FROM ' . GROUPS_TABLE . '
			WHERE ' . $this->db->sql_in_set('group_id', $group_ids);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$names[(int) $row['group_id']] = $this->group_helper->get_name($row['group_name']);
		}
		$this->db->sql_freeresult($result);
		asort($names);

		return $names;
	}

	/**
	 * `<option>` markup for every configured KB team group, for a team
	 * picker dropdown - shared between the article approve/queue pages
	 * and the ACP category editor's "default team" field.
	 *
	 * @param int $selected_team_id
	 * @return string
	 */
	public function get_team_options(int $selected_team_id): string
	{
		$options = '';
		foreach ($this->get_kb_team_group_names() as $group_id => $group_name)
		{
			$options .= '<option value="' . $group_id . '"' . ($group_id === $selected_team_id ? ' selected="selected"' : '') . '>' . $group_name . '</option>';
		}

		return $options;
	}

	/**
	 * An article's effective moderation team: its own explicit
	 * assigned_team_id if set, otherwise its category's default_team_id
	 * - "by assignment, or else by its categories". Returns 0 when
	 * neither the article nor its category has one.
	 *
	 * @param int $assigned_team_id The article's own assigned_team_id (0 if unset).
	 * @param int $category_id      The article's primary category id.
	 * @return int
	 */
	public function get_effective_team_id(int $assigned_team_id, int $category_id): int
	{
		if ($assigned_team_id)
		{
			return $assigned_team_id;
		}

		$cat_info = $this->get_cat_info($category_id);

		return (int) ($cat_info['default_team_id'] ?? 0);
	}

	/**
	 * @return string
	 */
	public function generate_filter_string(): string
	{
		$filters = [];

		$attach_extensions = json_decode($this->config_text->get('kb_extensions'), true);

		// Re-arrange the extension array
		foreach ($attach_extensions as $group => $group_info)
		{
			$filters[] = sprintf(
				"{title: '%s', extensions: '%s', max_file_size: %s}",
				addslashes(ucfirst(strtolower($group))),
				addslashes(implode(',', $group_info)),
				(int) $this->config['kb_max_filesize']
			);
		}
		return implode(',', $filters);
	}

	/**
	 * Moves an article one place up or down among its category's
	 * other articles, swapping display_order with whichever neighbour
	 * it lands on. Every article in the category is renumbered to a
	 * clean 1..N sequence as part of the same call, since display_order
	 * defaults to 0 for every article until something actually sets
	 * it - without this, "the neighbour" couldn't be found by comparing
	 * display_order values that are all still identical.
	 *
	 * @param int    $article_id
	 * @param int    $category_id
	 * @param string $direction 'up' or 'down'.
	 * @return bool false if the article was already at that end of the list.
	 */
	public function move_article_by(int $article_id, int $category_id, string $direction): bool
	{
		$sql = 'SELECT article_id
			FROM ' . $this->articles_table . '
			WHERE article_category_id = ' . $category_id . '
			ORDER BY display_order ASC, article_id ASC';
		$result = $this->db->sql_query($sql);
		$ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['article_id'];
		}
		$this->db->sql_freeresult($result);

		$position = array_search($article_id, $ids, true);
		$swap_with = ($direction === 'up') ? $position - 1 : $position + 1;

		if ($position === false || !isset($ids[$swap_with]))
		{
			return false;
		}

		[$ids[$position], $ids[$swap_with]] = [$ids[$swap_with], $ids[$position]];

		foreach ($ids as $order => $id)
		{
			$sql = 'UPDATE ' . $this->articles_table . ' SET display_order = ' . ($order + 1) . ' WHERE article_id = ' . $id;
			$this->db->sql_query($sql);
		}

		return true;
	}

	/**
	 * @param $id
	 * @param $article_title
	 * @return void
	 */
	public function kb_delete_article($id, $article_title): void
	{
		$attachment_data = [];
		include_once($this->phpbb_root_path . 'includes/functions_admin.' . $this->php_ext);

		$info = $this->get_kb_article_info($id);
		$category_id = $info['article_category_id'];

		$cat_info = $this->get_cat_info($category_id);
		$articles_count = $cat_info['number_articles'];

		$sql = 'UPDATE ' . $this->articles_table . ' SET display_order = display_order - 1
			WHERE article_category_id = ' . (int) $category_id . '
			AND display_order > ' . (int) $info['display_order'];
		$this->db->sql_query($sql);

		$sql = 'DELETE FROM ' . $this->articles_table . '
			WHERE article_id = ' . (int) $id;
		$this->db->sql_query($sql);

		$sql = 'DELETE FROM ' . $this->article_categories_table . '
			WHERE article_id = ' . (int) $id;
		$this->db->sql_query($sql);

		$sql = 'DELETE FROM ' . $this->article_field_values_table . '
			WHERE article_id = ' . (int) $id;
		$this->db->sql_query($sql);

		$this->delete_article_revision((int) $id);
		$this->delete_article_draft_edit((int) $id);

		$ids = [];
		$sql = 'SELECT attach_id, physical_filename, thumbnail
			FROM ' . $this->attachments_table . '
			WHERE article_id = ' . (int) $id;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$attachment_data[] = $row;
			$ids[] = $row['attach_id'];
		}
		$this->db->sql_freeresult($result);

		if (!empty($ids))
		{
			foreach ($attachment_data as $attachment)
			{
				unlink($this->upload_dir . $attachment['physical_filename']);
				if ($attachment['thumbnail'])
				{
					unlink($this->upload_dir . 'thumb_' . $attachment['physical_filename']);
				}
			}
			$sql = 'DELETE FROM ' . $this->attachments_table . '
				WHERE ' . $this->db->sql_in_set('attach_id', $ids);
			$this->db->sql_query($sql);
		}
		if ((int) $info['status'] !== self::STATUS_DRAFT)
		{
			$articles_count--;
			if ($articles_count >= 0)
			{
				$sql = 'UPDATE ' . $this->categories_table . '
					SET number_articles = ' . $articles_count . '
					WHERE category_id = ' . (int) $category_id;
				$this->db->sql_query($sql);
			}
			$this->config->increment('kb_num_articles', -1);
		}
		$this->cache->destroy('sql', $this->categories_table);

		delete_topics('topic_id', [$info['topic_id']], true, true, true);
		$this->log->add('admin', $this->user->data['user_id'], $this->user->data['user_ip'], 'LOG_LIBRARY_DEL_ARTICLE', time(), [$article_title, $cat_info['category_name']]);
	}

	/**
	 * @param $art_id
	 * @return mixed
	 */
	public function get_kb_article_info($art_id): mixed
	{
		$sql = 'SELECT * FROM ' . $this->articles_table . ' WHERE article_id = ' . (int) $art_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (!$row)
		{
			trigger_error($this->language->lang('ARTICLE_NO_EXISTS'));
		}
		return $row;
	}

	public function get_cat_info($category_id)
	{
		$sql = 'SELECT * FROM ' . $this->categories_table . ' WHERE category_id = ' . $category_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $row;
	}

	/**
	 * @param $k
	 * @param $article_title
	 * @param $cat_id
	 * @param $id
	 * @param $order
	 * @return void
	 */
	public function kb_move_article($k, $article_title, $cat_id, $id, $order): void
	{
		$info = $this->get_kb_article_info($k);
		// Change category id in the table of articles
		$sql = 'UPDATE ' . $this->articles_table . '
			SET article_category_id = ' . (int) $id . ', display_order = 0
			WHERE article_id = ' . (int) $k;
		$this->db->sql_query($sql);

		// Change display order in categories
		$sql = 'UPDATE ' . $this->articles_table . ' SET display_order = display_order + 1
			WHERE article_category_id = ' . (int) $id;
		$this->db->sql_query($sql);

		$sql = 'UPDATE ' . $this->articles_table . ' SET display_order = display_order - 1
			WHERE article_category_id = ' . (int) $cat_id . '
			AND display_order > ' . (int) $order;
		$this->db->sql_query($sql);

		// recalculate the number of articles in source category
		$cat_info = $this->get_cat_info($cat_id);
		$articles_count = $cat_info['number_articles'];
		$articles_count -= (int) ((int) $info['status'] !== self::STATUS_DRAFT);
		// ... and a new category
		$to_cat_info = $this->get_cat_info($id);
		$to_articles_count = $to_cat_info['number_articles'];
		$to_articles_count += (int) ((int) $info['status'] !== self::STATUS_DRAFT);
		// change in DB
		$sql = 'UPDATE ' . $this->categories_table . ' SET number_articles = ' . $articles_count . ' WHERE category_id = ' . (int) $cat_id;
		$this->db->sql_query($sql);

		$sql = 'UPDATE ' . $this->categories_table . ' SET number_articles = ' . $to_articles_count . ' WHERE category_id = ' . (int) $id;
		$this->db->sql_query($sql);
		$this->cache->destroy('sql', $this->categories_table);
		$this->log->add('admin', $this->user->data['user_id'], $this->user->data['user_ip'], 'LOG_LIBRARY_MOVED_ARTICLE', time(), [$article_title, $cat_info['category_name'], $to_cat_info['category_name']]);
	}

	/**
	 * @param $cat_id
	 * @param $fid
	 * @param $article_title
	 * @param $article_description
	 * @param $article_author
	 * @param $category_name
	 * @param $new
	 * @return void
	 */
	public function submit_article($cat_id, $fid, $article_title, $article_description, $article_author, $category_name, $new): void
	{
		$options = $topic_title = '';

		$sql = 'SELECT forum_id FROM ' . FORUMS_TABLE . ' WHERE forum_id = ' . (int) $fid;
		$result = $this->db->sql_query($sql);
		if ($this->db->sql_fetchrow($result))
		{
			$topic_title = $article_title;
		}
		else
		{
			trigger_error($this->language->lang('NO_FORUM'));
		}
		$this->db->sql_freeresult($result);

		// The title, author, description and category are user-supplied, so they
		// go in as placeholders and are swapped in as plain text after parsing -
		// otherwise BBCode typed into a description would be formatted in the
		// public announcement topic.
		$topic_text = '[b]' . $this->language->lang('ARTICLE_TITLE') . $this->language->lang('COLON') . '[/b] {KB_TITLE}';
		$topic_text .= "\n";
		$topic_text .= '[b]' . $this->language->lang('ARTICLE_AUTHOR') . $this->language->lang('COLON') . '[/b] {KB_AUTHOR}';
		$topic_text .= "\n";
		$topic_text .= '[b]' . $this->language->lang('ARTICLE_DESCRIPTION') . $this->language->lang('COLON') . '[/b] {KB_DESCRIPTION}';
		$topic_text .= "\n";
		$topic_text .= '[b]' . $this->language->lang('CATEGORY') . $this->language->lang('COLON') . '[/b] {KB_CATEGORY}';
		$topic_text .= "\n\n";
		$topic_text .= '[b][url=' . generate_board_url() . '/knowledgebase/article?k=' . $new . ']' . $this->language->lang('READ_FULL') . '[/url][/b]';

		generate_text_for_storage($topic_text, $bbcode_uid, $bitfield, $options, true, true, true);
		$topic_text = self::insert_plain_text($topic_text, [
			'{KB_TITLE}'       => $article_title,
			'{KB_AUTHOR}'      => $article_author,
			'{KB_DESCRIPTION}' => $article_description,
			'{KB_CATEGORY}'    => $category_name,
		]);

		$data = [
			'topic_title'      => $topic_title,
			'forum_id'         => $fid,
			'forum_name'       => '',
			'icon_id'          => 0,
			'poster_id'        => (int) $this->user->data['user_id'],
			'enable_bbcode'    => true,
			'enable_smilies'   => true,
			'enable_urls'      => true,
			'enable_sig'       => true,
			'notify'           => 0,
			'notify_set'       => '',
			'enable_indexing'  => true,
			'message'          => $topic_text,
			'message_md5'      => md5(time()),
			'bbcode_bitfield'  => $bitfield,
			'bbcode_uid'       => substr(md5(rand()), 0, 8),
			'post_edit_locked' => 0,
		];

		submit_post('post', $topic_title, $this->user->data['username'], 0, $poll, $data, false);

		// submit_post() fills $data['topic_id'] itself (by reference) -
		// rediscovering it after the fact with SELECT MAX(topic_id) is a
		// race: another request's topic created in between would make
		// this article record someone else's topic id instead of its own.
		$sql = 'UPDATE ' . $this->articles_table . ' SET topic_id = ' . (int) $data['topic_id'] . ' WHERE article_id = ' . (int) $new;
		$this->db->sql_query($sql);
	}

	/**
	 * Moderator-only changelog thread for an article - a running record of
	 * every submission/edit/revision, separate from the article's own
	 * public comments topic (topic_id/kb_forum_id above). Lazily creates
	 * the topic (titled after the article) the first time this is called
	 * for it, then replies to it on every later call - so an article
	 * that already existed before this feature shipped gets its topic
	 * on its next edit, same as a brand new one gets it on submission.
	 *
	 * Best-effort and entirely optional: does nothing if
	 * kb_changelog_forum_id isn't configured (default) or points at a
	 * forum that no longer exists - matching how the public comments
	 * topic (kb_forum_id/kb_anounce) is also opt-in, not a hard
	 * dependency of submitting or editing an article.
	 *
	 * @param int    $article_id
	 * @param string $article_title
	 * @param string $message BBCode-enabled changelog entry text.
	 * @return void
	 */
	public function post_changelog_entry(int $article_id, string $article_title, string $message): void
	{
		$fid = (int) $this->config['kb_changelog_forum_id'];
		if (!$fid)
		{
			return;
		}

		$sql = 'SELECT forum_id FROM ' . FORUMS_TABLE . ' WHERE forum_id = ' . $fid;
		$result = $this->db->sql_query($sql);
		$forum_exists = (bool) $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$forum_exists)
		{
			return;
		}

		if (!function_exists('submit_post'))
		{
			include($this->phpbb_root_path . 'includes/functions_posting.' . $this->php_ext);
		}

		$sql = 'SELECT changelog_topic_id FROM ' . $this->articles_table . ' WHERE article_id = ' . $article_id;
		$result = $this->db->sql_query($sql);
		$topic_id = (int) $this->db->sql_fetchfield('changelog_topic_id');
		$this->db->sql_freeresult($result);

		if (!$topic_id)
		{
			// Only the initial post needs this - a moderator following the
			// topic already has the article link from the first post, and
			// every reply already carries its own diff/context.
			$article_url = $this->helper->route('phpbbmodders_knowledgebase_article', ['k' => $article_id]);
			$message .= "\n\n" . '[url=' . $article_url . ']' . $this->language->lang('KB_CHANGELOG_VIEW_ARTICLE') . '[/url]';
		}

		$options = '';
		generate_text_for_storage($message, $bbcode_uid, $bbcode_bitfield, $options, true, true, true);

		$data = [
			'forum_id'         => $fid,
			'topic_id'         => $topic_id,
			'topic_title'      => $article_title,
			'forum_name'       => '',
			'icon_id'          => 0,
			'poster_id'        => (int) $this->user->data['user_id'],
			'enable_bbcode'    => true,
			'enable_smilies'   => true,
			'enable_urls'      => true,
			'enable_sig'       => false,
			'notify'           => 0,
			'notify_set'       => '',
			'enable_indexing'  => true,
			'message'          => htmlspecialchars_decode($message),
			'message_md5'      => md5(time()),
			'bbcode_bitfield'  => $bbcode_bitfield,
			'bbcode_uid'       => $bbcode_uid,
			// Only the extension itself should ever post here - not a
			// setting a board member could stumble into editing.
			'post_edit_locked' => 1,
			// This forum is moderator-only regardless of who triggers the
			// entry (any author submitting/editing an article ends up as
			// the poster here) - approval should depend on that, not on
			// whichever member happens to be the acting author's own
			// f_noapprove permission in this forum.
			'force_approved_state' => ITEM_APPROVED,
		];

		$poll = [];
		$subject = $topic_id ? $this->language->lang('KB_CHANGELOG_REPLY_SUBJECT', $article_title) : $article_title;
		submit_post($topic_id ? 'reply' : 'post', $subject, $this->user->data['username'], 0, $poll, $data, false);

		if (!$topic_id)
		{
			// submit_post() fills $data['topic_id'] itself (by reference)
			// on the 'post' path - same race avoided in submit_article()
			// above, not re-derived with SELECT MAX(topic_id).
			$sql = 'UPDATE ' . $this->articles_table . ' SET changelog_topic_id = ' . (int) $data['topic_id'] . ' WHERE article_id = ' . $article_id;
			$this->db->sql_query($sql);
		}
	}

	/**
	 * @param $text
	 * @param $attachments
	 * @return void
	 */
	public function parse_att(&$text, $attachments): void
	{
		krsort($attachments);
//		preg_match_all('#<!\-\- ia([0-9]+) \-\->(.*?)<!\-\- ia\1 \-\->#', $text, $matches, PREG_PATTERN_ORDER);
		preg_match_all('#<!-- ia([0-9]+) -->(.*?)<!-- ia\1 -->#', $text, $matches, PREG_PATTERN_ORDER);
		$replace = [];
		foreach ($matches[0] as $num => $capture)
		{
			$index = $matches[1][$num];
			// Both values are user-supplied (upload filename, submitted
			// comment) and end up in raw HTML output below - never trust
			// them unescaped.
			$filename = htmlspecialchars($attachments[$index]['real_filename'] ?? '');
			$comment_text = htmlspecialchars($attachments[$index]['attach_comment'] ?? '');
			$comment = ($comment_text !== '') ? '<dd>' . $comment_text . '</dd>' : '';
			if ($attachments[$index]['thumbnail'] == 1)
			{
				$replacement = '<dl class="thumbnail"><dt><a href="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $attachments[$index]['attach_id']]) . '"><img src="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $attachments[$index]['attach_id']]) . '&amp;t=1" class="postimage" alt="' . $filename . '" title="' . $filename . '"></a></dt>' . $comment . '</dl>';
			}
			else
			{
				if ($this->check_is_img($attachments[$index]['extension'], $extensions))
				{
					$replacement = '<dl class="file"><dt class="attach-image"><img src="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $attachments[$index]['attach_id']]) . '" class="postimage" alt="' . $filename . '" onclick="viewableArea(this);"></dt><dd>' . $comment . '</dd></dl>';
				}
				else if ($attachments[$index]['extension'] == 'mp3' || $attachments[$index]['extension'] == 'mp4')
				{
					$replacement = '<p><audio src="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $attachments[$index]['attach_id']]) . '" controls preload="none"></audio>';
					if ($comment_text !== '')
					{
						$replacement .= '<br>' . $comment_text;
					}
					$replacement .= '</p>';
				}
				else
				{
					$icon = ($extensions[$attachments[$index]['extension']]['upload_icon']) ? '<img src="' . generate_board_url() . '/images/upload_icons/' . $extensions[$attachments[$index]['extension']]['upload_icon'] . '" alt="">' : '';
					$replacement = '<dl class="file"><dt>' . $icon . ' <a class="postlink" href="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $attachments[$index]['attach_id']]) . '">' . $filename . '</a></dt></dl>';
				}
			}
			$replace['from'][] = $matches[0][$num];
			$replace['to'][] = (isset($attachments[$index])) ? $replacement : sprintf($this->language->lang('MISSING_INLINE_ATTACHMENT'), htmlspecialchars($matches[2][array_search($index, $matches[1])]));
			$unset_tpl[] = $index;
		}

		if (isset($replace['from']))
		{
			$text = str_replace($replace['from'], $replace['to'], $text);
		}

		if (isset($unset_tpl))
		{
			foreach ($attachments as $num => $attach)
			{
				if (array_key_exists($num, $unset_tpl))
				{
					unset($attachments[$unset_tpl[$num]]);
				}
			}
		}

		if (count($attachments))
		{
			$text .= '</div><dl class="attachbox"><dt>' . $this->language->lang('ATTACHMENTS') . '</dt>';
			foreach ($attachments as $value)
			{
				$filename = htmlspecialchars($value['real_filename'] ?? '');
				$comment_text = htmlspecialchars($value['attach_comment'] ?? '');
				$comment = ($comment_text !== '') ? '<dd>' . $comment_text . '</dd>' : '';
				if ($value['thumbnail'])
				{
					$text .= '<dd><dl class="thumbnail"><dt><a href="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $value['attach_id']]) . '"><img alt= "" src="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $value['attach_id']]) . '&amp;t=1"></a></dt>' . $comment . '</dl></dd>';
				}
				else
				{
					if ($this->check_is_img($value['extension'], $extensions))
					{
						$text .= '<dd><dl class="file">';
						$text .= '<dt class="attach-image"><img src="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $value['attach_id']]) . '" class="postimage" alt="' . $filename . '" onclick="viewableArea(this);"></dt>' . $comment;
					}
					else if ($value['extension'] == 'mp3')
					{
						$text .= '<p><audio src="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $value['attach_id']]) . '" controls preload="none"></audio>';
						if ($comment_text !== '')
						{
							$text .= '<br>' . $comment_text;
						}
						$text .= '</p>';
					}
					else
					{
						$icon = ($extensions[$value['extension']]['upload_icon']) ? '<img src="' . generate_board_url() . '/images/upload_icons/' . $extensions[$value['extension']]['upload_icon'] . '" alt="">' : '';
						$text .= '<dd><dl class="file"><dt>' . $icon . ' <a class="postlink" href="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $value['attach_id']]) . '">' . $filename . '</a></dt>' . $comment;
					}
					$text .= '</dl></dd>';
				}
			}
			$text .= '</dl><div>';
		}
	}

	/**
	 * The board's attachment upload directory, relative to the board root,
	 * without a trailing slash.
	 *
	 * phpBB 3.3.x keeps it in the "upload_path" setting; phpBB 4.0 removes that
	 * setting and keeps it in the attachment storage's "path" setting instead.
	 * Falls back to "files", phpBB's default, when neither is set.
	 */
	public function get_upload_path(): string
	{
		$path = $this->config['upload_path'] ?? $this->config['storage\\attachment\\config\\path'] ?? '';

		return rtrim((string) $path, '/') ?: 'files';
	}

	/**
	 * @param string $ext
	 * @param ?array $extensions
	 * @return bool
	 */
	public function check_is_img(string $ext, array|null &$extensions = []): bool
	{
		if (($extensions = $this->cache->get('_kb_extension')) === false)
		{
			$sql = 'SELECT e.extension, g.*
				FROM ' . EXTENSIONS_TABLE . ' e, ' . EXTENSION_GROUPS_TABLE . ' g
				WHERE e.group_id = g.group_id';
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$extension = strtolower(trim($row['extension']));

				$extensions[$extension] = [
					'display_cat'   => (int) $row['cat_id'],
					'download_mode' => $row['download_mode'] ?? 0,// phpbb 4.x
					'upload_icon'   => trim($row['upload_icon']),
					'max_filesize'  => (int) $row['max_filesize'],
					'allow_group'   => $row['allow_group'],
					'allow_in_pm'   => $row['allow_in_pm'],
					'group_name'    => $row['group_name'],
				];
			}
			$this->db->sql_freeresult($result);
			$this->cache->put('_kb_extension', $extensions);
		}

		if (phpbb_version_compare($this->config['version'], '4.0.0', '<'))
		{
			return isset($extensions[$ext]['display_cat']) && $extensions[$ext]['display_cat'] == ATTACHMENT_CATEGORY_IMAGE;
		}
		else
		{
			return isset($extensions[$ext]['display_cat']) && $extensions[$ext]['display_cat'] == \phpbb\attachment\attachment_category::IMAGE;
		}
	}
}
