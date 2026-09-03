<?php
/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2023, sutrus ( update to phpBB 3.3 )
 * @copyright (c) 2017, Sheer
 * @license       GNU General Public License, version 2 (GPL-2.0)
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

	/** @var group_helper */
	protected group_helper $group_helper;

	/** @var string */
	protected string $upload_dir;

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
	 * @param group_helper     $group_helper
	 */
	public function __construct(
		driver_interface $db, config $config, db_text $config_text, helper $helper, manager $ext_manager,
		language $language, auth $auth, template $template, user $user, cache $cache, log $log,
		string $phpbb_root_path, string $php_ext, string $articles_table, string $categories_table,
		string $kb_logs_table, string $attachments_table, string $options_table, string $kb_users_table,
		string $kb_groups_table, string $revisions_table, string $article_categories_table, group_helper $group_helper
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
		$this->group_helper = $group_helper;

		$this->upload_dir = $this->ext_manager->get_extension_path('phpbbmodders/knowledgebase', true) . 'files/';
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
				$cat_list .= $padding . '<a href="' . $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $row['category_id']]) . '">' . $row['category_name'] . '</a> (' . $this->language->lang('ARTICLES') . ': ' . $art_row['articles'] . ')<br>';
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
		$auth_setting = $this->db->sql_fetchfield('auth_setting');

		if ($auth_setting == ACL_YES)
		{
			$total = ACL_YES;
		}
		$this->db->sql_freeresult($result);

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
	 * @return string
	 */
	public function get_article_categories_table(): string
	{
		return $this->article_categories_table;
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
	public function make_category_select($select_id, array $ignore_id, bool $ignore_acl = false): string
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
				(in_array($row['category_id'], $ignore_id) || !$this->acl_kb_get($row['category_id'], 'kb_u_add'))
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
		$articles_count--;
		if ($articles_count >= 0)
		{
			$sql = 'UPDATE ' . $this->categories_table . '
				SET number_articles = ' . $articles_count . '
				WHERE category_id = ' . (int) $category_id;
			$this->db->sql_query($sql);
		}
		$this->cache->destroy('sql', $this->categories_table);
		$this->config->increment('kb_num_articles', -1);

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
		$articles_count--;
		// ... and a new category
		$to_cat_info = $this->get_cat_info($id);
		$to_articles_count = $to_cat_info['number_articles'];
		$to_articles_count++;
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

		$topic_text = '[b]' . $this->language->lang('ARTICLE_TITLE') . $this->language->lang('COLON') . '[/b] ' . $article_title;
		$topic_text .= "\n";
		$topic_text .= '[b]' . $this->language->lang('ARTICLE_AUTHOR') . $this->language->lang('COLON') . '[/b] ' . $article_author;
		$topic_text .= "\n";
		$topic_text .= '[b]' . $this->language->lang('ARTICLE_DESCRIPTION') . $this->language->lang('COLON') . '[/b] ' . $article_description;
		$topic_text .= "\n";
		$topic_text .= '[b]' . $this->language->lang('CATEGORY') . $this->language->lang('COLON') . '[/b] ' . $category_name;
		$topic_text .= "\n\n";
		$topic_text .= '[b][url=' . generate_board_url() . '/knowledgebase/article?k=' . $new . ']' . $this->language->lang('READ_FULL') . '[/url][/b]';

		generate_text_for_storage($topic_text, $bbcode_uid, $bitfield, $options, true, true, true);

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
			'message'          => htmlspecialchars_decode($topic_text),
			'message_md5'      => md5(time()),
			'bbcode_bitfield'  => $bitfield,
			'bbcode_uid'       => substr(md5(rand()), 0, 8),
			'post_edit_locked' => 0,
		];

		submit_post('post', $topic_title, $this->user->data['username'], 0, $poll, $data, false);

		$sql = 'SELECT MAX(topic_id) AS last_topic FROM ' . TOPICS_TABLE . ' WHERE forum_id = ' . (int) $fid;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$last_topic = $row['last_topic'];
		$sql = 'UPDATE ' . $this->articles_table . ' SET topic_id = ' . (int) $last_topic . ' WHERE article_id = ' . (int) $new;
		$this->db->sql_query($sql);
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
			$comment = ($attachments[$index]['attach_comment']) ? '<dd>' . $attachments[$index]['attach_comment'] . '</dd>' : '';
			if ($attachments[$index]['thumbnail'] == 1)
			{
				$replacement = '<dl class="thumbnail"><dt><a href="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $attachments[$index]['attach_id']]) . '"><img src="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $attachments[$index]['attach_id']]) . '&amp;t=1" class="postimage" alt="' . $attachments[$index]['real_filename'] . '" title="' . $attachments[$index]['real_filename'] . '"></a></dt>' . $comment . '</dl>';
			}
			else
			{
				if ($this->check_is_img($attachments[$index]['extension'], $extensions))
				{
					$replacement = '<dl class="file"><dt class="attach-image"><img src="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $attachments[$index]['attach_id']]) . '" class="postimage" alt="' . $attachments[$index]['real_filename'] . '" onclick="viewableArea(this);"></dt><dd>' . $comment . '</dd></dl>';
				}
				else if ($attachments[$index]['extension'] == 'mp3' || $attachments[$index]['extension'] == 'mp4')
				{
					$replacement = '<p><audio src="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $attachments[$index]['attach_id']]) . '" controls preload="none"></audio>';
					if ($attachments[$index]['attach_comment'])
					{
						$replacement .= '<br>' . $attachments[$index]['attach_comment'];
					}
					$replacement .= '</p>';
				}
				else
				{
					$icon = ($extensions[$attachments[$index]['extension']]['upload_icon']) ? '<img src="' . generate_board_url() . '/images/upload_icons/' . $extensions[$attachments[$index]['extension']]['upload_icon'] . '" alt="">' : '';
					$replacement = '<dl class="file"><dt>' . $icon . ' <a class="postlink" href="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $attachments[$index]['attach_id']]) . '">' . $attachments[$index]['real_filename'] . '</a></dt></dl>';
				}
			}
			$replace['from'][] = $matches[0][$num];
			$replace['to'][] = (isset($attachments[$index])) ? $replacement : sprintf($this->language->lang('MISSING_INLINE_ATTACHMENT'), $matches[2][array_search($index, $matches[1])]);
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
				$comment = ($value['attach_comment']) ? '<dd>' . $value['attach_comment'] . '</dd>' : '';
				if ($value['thumbnail'])
				{
					$text .= '<dd><dl class="thumbnail"><dt><a href="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $value['attach_id']]) . '"><img alt= "" src="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $value['attach_id']]) . '&amp;t=1"></a></dt>' . $comment . '</dl></dd>';
				}
				else
				{
					if ($this->check_is_img($value['extension'], $extensions))
					{
						$text .= '<dd><dl class="file">';
						$text .= '<dt class="attach-image"><img src="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $value['attach_id']]) . '" class="postimage" alt="' . $value['real_filename'] . '" onclick="viewableArea(this);"></dt>' . $comment;
					}
					else if ($value['extension'] == 'mp3')
					{
						$text .= '<p><audio src="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $value['attach_id']]) . '" controls preload="none"></audio>';
						if ($value['attach_comment'])
						{
							$text .= '<br>' . $value['attach_comment'];
						}
						$text .= '</p>';
					}
					else
					{
						$icon = ($extensions[$value['extension']]['upload_icon']) ? '<img src="' . generate_board_url() . '/images/upload_icons/' . $extensions[$value['extension']]['upload_icon'] . '" alt="">' : '';
						$text .= '<dd><dl class="file"><dt>' . $icon . ' <a class="postlink" href="' . $this->helper->route('phpbbmodders_knowledgebase_kb_file', ['id' => $value['attach_id']]) . '">' . $value['real_filename'] . '</a></dt>' . $comment;
					}
					$text .= '</dl></dd>';
				}
			}
			$text .= '</dl><div>';
		}
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
