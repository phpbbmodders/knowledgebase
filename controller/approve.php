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
use phpbb\cache\driver\driver_interface as cache;
use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\db\driver\driver_interface;
use phpbb\language\language;
use phpbb\log\log;
use phpbb\notification\manager;
use phpbb\pagination;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;
use RuntimeException;
use phpbbmodders\knowledgebase\inc\functions_kb;
use phpbbmodders\knowledgebase\search\kb_search_backend_factory;

class approve
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

	/** @var cache */
	protected cache $cache;

	/** @var log */
	protected log $log;

	/** @var manager */
	protected manager $notification_manager;

	/** @var pagination */
	protected pagination $pagination;

	/** @var functions_kb */
	protected functions_kb $kb;

	/** @var kb_search_backend_factory */
	protected kb_search_backend_factory $search_factory;

	/** @var string */
	protected string $phpbb_root_path;

	/** @var string */
	protected string $php_ext;

	/** @var string */
	protected string $articles_table;

	/**
	 * Constructor
	 *
	 * @param driver_interface          $db
	 * @param config                    $config
	 * @param helper                    $helper
	 * @param language                  $language
	 * @param auth                      $auth
	 * @param request_interface         $request
	 * @param template                  $template
	 * @param user                      $user
	 * @param cache                     $cache
	 * @param log                       $log
	 * @param manager                   $notification_manager
	 * @param pagination                $pagination
	 * @param functions_kb              $kb
	 * @param kb_search_backend_factory $search_factory
	 * @param string                    $phpbb_root_path
	 * @param string                    $php_ext
	 * @param string                    $articles_table
	 */
	public function __construct(
		driver_interface $db, config $config, helper $helper, language $language, auth $auth,
		request_interface $request, template $template, user $user, cache $cache, log $log,
		manager $notification_manager, pagination $pagination, functions_kb $kb, kb_search_backend_factory $search_factory,
		string $phpbb_root_path, string $php_ext, string $articles_table
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
		$this->cache = $cache;
		$this->log = $log;
		$this->notification_manager = $notification_manager;
		$this->pagination = $pagination;
		$this->kb = $kb;
		$this->search_factory = $search_factory;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->articles_table = $articles_table;
	}

	/**
	 * "Administration" moderation queue: every article across every
	 * category the current user moderates that needs attention (not
	 * Active with no pending revision), with an inline per-row action
	 * select that submits to the existing approve_article() endpoint -
	 * this method only ever renders the listing, it never duplicates
	 * the do_assign()/do_review()/etc. action handling above.
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function queue(): \Symfony\Component\HttpFoundation\Response
	{
		add_form_key('kb_approve');

		$moderatable = $this->kb->get_moderatable_categories();
		if (!$this->auth->acl_get('a_manage_kb') && empty($moderatable))
		{
			trigger_error($this->language->lang('NOT_AUTHORISED'));
		}

		$start = $this->request->variable('start', 0);
		$per_page = $this->config['kb_articles_per_page'] ?: 10;

		// A moderator sees an article here if they moderate its primary
		// category OR any category it's cross-listed into (Phase 2) -
		// matching how direct moderation access already works via
		// acl_kb_get_any() across every one of the article's categories.
		$sql_where = $this->auth->acl_get('a_manage_kb') ? '' : ('AND (' . $this->db->sql_in_set('article_category_id', $moderatable) . '
			OR article_id IN (SELECT article_id FROM ' . $this->kb->get_article_categories_table() . ' WHERE ' . $this->db->sql_in_set('category_id', $moderatable) . '))');
		$sql_where .= ' AND (status <> ' . functions_kb::STATUS_ACTIVE . '
			OR article_id IN (SELECT article_id FROM ' . $this->kb->get_revisions_table() . '))';

		$sql = 'SELECT COUNT(article_id) AS article_count
			FROM ' . $this->articles_table . '
			WHERE 1=1 ' . $sql_where;
		$result = $this->db->sql_query($sql);
		$article_count = (int) $this->db->sql_fetchfield('article_count');
		$this->db->sql_freeresult($result);

		$pagination_url = $this->helper->route('phpbbmodders_knowledgebase_queue');
		if ($article_count)
		{
			$this->pagination->generate_template_pagination($pagination_url, 'pagination', 'start', $article_count, $per_page, $start);
		}

		$sql = 'SELECT *
			FROM ' . $this->articles_table . '
			WHERE 1=1 ' . $sql_where . '
			ORDER BY article_date DESC';
		$result = $this->db->sql_query_limit($sql, $per_page, $start);
		while ($art_row = $this->db->sql_fetchrow($result))
		{
			$art_id = (int) $art_row['article_id'];
			$status = (int) $art_row['status'];
			$category_info = $this->kb->get_cat_info((int) $art_row['article_category_id']);
			$has_revision = $status === functions_kb::STATUS_ACTIVE && $this->kb->article_has_pending_revision($art_id);

			$this->template->assign_block_vars('art_row', [
				'ID'                     => $art_id,
				'ARTICLE_TITLE'          => $art_row['article_title'],
				'U_ARTICLE'              => $this->helper->route('phpbbmodders_knowledgebase_article', ['k' => $art_id]),
				'CATEGORY'               => $category_info['category_name'] ?? '',
				'ARTICLE_AUTHOR'         => $art_row['author'],
				'ARTICLE_DATE'           => $this->user->format_date($art_row['article_date']),
				'S_ACTION'               => $this->helper->route('phpbbmodders_knowledgebase_approve', ['id' => $art_id]),
				'S_STATUS_NEW'           => $status === functions_kb::STATUS_NEW,
				'S_STATUS_REVIEWED'      => $status === functions_kb::STATUS_REVIEWED,
				'S_STATUS_ACTIVE'        => $status === functions_kb::STATUS_ACTIVE,
				'S_STATUS_INACTIVE'      => $status === functions_kb::STATUS_INACTIVE,
				'S_HAS_REVISION'         => $has_revision,
				'S_IS_ASSIGNED'          => (bool) $art_row['assigned_team_id'],
			]);
		}
		$this->db->sql_freeresult($result);

		$this->template->assign_vars([
			'TOTAL_ITEMS'         => $this->language->lang('TOTAL_ITEMS', $article_count),
			'CURRENT_PAGE_NUMBER' => $this->pagination->get_on_page($per_page, $start),
			'S_KNOWLEDGEBASE'     => true,
		]);

		$this->template->assign_block_vars('navlinks', [
			'FORUM_NAME'   => $this->language->lang('LIBRARY'),
			'U_VIEW_FORUM' => $this->helper->route('phpbbmodders_knowledgebase_index'),
		]);

		$this->kb->assign_nav_tabs_vars('admin');

		return $this->helper->render('@phpbbmodders_knowledgebase/kb_queue_body.html', $this->language->lang('LIBRARY') . ' &raquo; ' . $this->language->lang('KB_TAB_ADMINISTRATION'));
	}

	/**
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function approve_article(): \Symfony\Component\HttpFoundation\Response
	{
		if ($this->user->data['user_id'] == ANONYMOUS)
		{
			login_box('', $this->language->lang('LOGIN_EXPLAIN_APPROVE'));
		}

		$art_id = $this->request->variable('id', 0);
		$action = $this->request->variable('action', '');

		$kb_article_info = $this->kb->get_kb_article_info($art_id);
		$kb_category_info = $this->kb->get_cat_info($kb_article_info['article_category_id']);
		$category_name = $kb_category_info['category_name'];
		$status = (int) $kb_article_info['status'];

		$is_moderator = $this->user->data['user_type'] == USER_FOUNDER ||
			$this->auth->acl_get('a_manage_kb') ||
			$this->kb->acl_kb_get_any($this->kb->get_article_categories($art_id), 'kb_m_approve');

		$redirect = $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $kb_article_info['article_category_id']]);

		add_form_key('kb_approve');

		if ($action)
		{
			if (!check_form_key('kb_approve'))
			{
				trigger_error('FORM_INVALID');
			}

			if (!$is_moderator && $action != 'review')
			{
				trigger_error('RULES_KB_APPROVE_MOD_CANNOT');
			}

			switch ($action)
			{
				case 'assign':
					$this->do_assign($art_id, $status);
				break;

				case 'review':
					$this->do_review($art_id, $status, $kb_article_info, $is_moderator);
				break;

				case 'approve':
					$this->do_approve($art_id, $status, $kb_article_info, $kb_category_info, $redirect);
				break;

				case 'deny':
					$this->do_deny($art_id, $status, $kb_article_info, $kb_category_info, $redirect);
				break;

				case 'deactivate':
					$this->do_set_active($art_id, $status, functions_kb::STATUS_ACTIVE, functions_kb::STATUS_INACTIVE, $redirect);
				break;

				case 'reactivate':
					$this->do_set_active($art_id, $status, functions_kb::STATUS_INACTIVE, functions_kb::STATUS_ACTIVE, $redirect);
				break;

				case 'approve_revision':
					$this->do_approve_revision($art_id, $status, $kb_article_info, $redirect);
				break;

				case 'reject_revision':
					$this->do_reject_revision($art_id, $status, $redirect);
				break;
			}
		}

		$has_revision = $status === functions_kb::STATUS_ACTIVE && $this->kb->article_has_pending_revision($art_id);
		$revision = $has_revision ? $this->kb->get_article_revision($art_id) : null;

		$this->template->assign_vars([
			'ARTICLE_AUTHOR'        => $kb_article_info['author'],
			'ARTICLE_TITLE'         => $kb_article_info['article_title'],
			'ARTICLE_DESCRIPTION'   => $kb_article_info['article_description'],
			'ARTICLE_CATEGORY'      => $kb_category_info['category_name'],
			'S_STATUS_NEW'          => $status === functions_kb::STATUS_NEW,
			'S_STATUS_REVIEWED'     => $status === functions_kb::STATUS_REVIEWED,
			'S_STATUS_ACTIVE'       => $status === functions_kb::STATUS_ACTIVE,
			'S_STATUS_INACTIVE'     => $status === functions_kb::STATUS_INACTIVE,
			'S_HAS_REVISION'        => $has_revision,
			// The moderator must see what they're actually approving,
			// not just the currently-live title/description above.
			'REVISION_TITLE'        => $revision ? $revision['article_title'] : '',
			'REVISION_DESCRIPTION'  => $revision ? $revision['article_description'] : '',
			'REVISION_TEXT'         => $revision ? generate_text_for_display($revision['article_body'], $revision['bbcode_uid'], false, 3, true) : '',
			'S_IS_MODERATOR'        => $is_moderator,
			'S_IS_ASSIGNED'         => (bool) $kb_article_info['assigned_team_id'],
			'S_CAN_REVIEW'          => $status === functions_kb::STATUS_NEW && (bool) $kb_article_info['assigned_team_id'] &&
				($is_moderator || $this->kb->is_team_member([(int) $kb_article_info['assigned_team_id']])),
			'S_TEAM_OPTIONS'        => $this->get_team_options((int) $kb_article_info['assigned_team_id']),
			'S_ACTION'              => $this->helper->route('phpbbmodders_knowledgebase_approve', ['id' => $art_id]),
		]);

		$this->kb->assign_nav_tabs_vars('admin', (int) $kb_article_info['article_category_id']);

		return $this->helper->render('@phpbbmodders_knowledgebase/kb_approve_body.html', ($this->language->lang('LIBRARY') . ' &raquo; ' . $this->language->lang('APPROVE')));
	}

	/**
	 * @param int $art_id
	 * @param int $status
	 * @return void
	 */
	protected function do_assign(int $art_id, int $status): void
	{
		if ($status !== functions_kb::STATUS_NEW)
		{
			return;
		}

		$team_id = $this->request->variable('team_id', 0);
		if (!in_array($team_id, $this->kb->get_kb_team_groups(), true))
		{
			trigger_error('NO_SUCH_TEAM', E_USER_WARNING);
		}

		$sql = 'UPDATE ' . $this->articles_table . ' SET assigned_team_id = ' . $team_id . ' WHERE article_id = ' . $art_id;
		$this->db->sql_query($sql);
	}

	/**
	 * @param int   $art_id
	 * @param int   $status
	 * @param array $kb_article_info
	 * @param bool  $is_moderator
	 * @return void
	 */
	protected function do_review(int $art_id, int $status, array $kb_article_info, bool $is_moderator): void
	{
		if ($status !== functions_kb::STATUS_NEW || empty($kb_article_info['assigned_team_id']))
		{
			return;
		}

		if (!$is_moderator && !$this->kb->is_team_member([(int) $kb_article_info['assigned_team_id']]))
		{
			trigger_error('RULES_KB_APPROVE_MOD_CANNOT');
		}

		$sql = 'UPDATE ' . $this->articles_table . ' SET status = ' . functions_kb::STATUS_REVIEWED . ' WHERE article_id = ' . $art_id;
		$this->db->sql_query($sql);
	}

	/**
	 * @param int    $art_id
	 * @param int    $status
	 * @param array  $kb_article_info
	 * @param array  $kb_category_info
	 * @param string $redirect
	 * @return void
	 */
	protected function do_approve(int $art_id, int $status, array $kb_article_info, array $kb_category_info, string $redirect): void
	{
		if ($status !== functions_kb::STATUS_REVIEWED)
		{
			return;
		}

		include_once($this->phpbb_root_path . 'includes/functions_posting.' . $this->php_ext);

		try
		{
			$kb_search = $this->search_factory->get_active();
		}
		catch (RuntimeException $e)
		{
			if (str_starts_with($e->getMessage(), 'No service found'))
			{
				trigger_error('NO_SUCH_SEARCH_MODULE');
			}
			else
			{
				throw $e;
			}
		}

		$sql = 'UPDATE ' . $this->articles_table . ' SET status = ' . functions_kb::STATUS_ACTIVE . ', approved = 1 WHERE article_id = ' . $art_id;
		$this->db->sql_query($sql);

		if (isset($kb_search))
		{
			$this->cache->purge();
			$kb_search->index('add', $art_id, $kb_article_info['article_body'], $kb_article_info['article_title'], $kb_article_info['article_description'], (int) $kb_article_info['author_id']);
		}

		if (!empty($this->config['kb_forum_id']) && $this->config['kb_anounce'])
		{
			$this->kb->submit_article($kb_article_info['article_category_id'], $this->config['kb_forum_id'], $kb_article_info['article_title'], $kb_article_info['article_description'], $kb_article_info['author'], $kb_category_info['category_name'], $art_id);
		}

		$this->log->add('admin', $this->user->data['user_id'], $this->user->data['user_ip'], 'LOG_LIBRARY_APPROVED_ARTICLE', time(), [$kb_article_info['article_title'], $kb_category_info['category_name'], $kb_article_info['author']]);

		$this->delete_need_approval_notification($art_id, $kb_article_info);
		$this->send_author_notification($art_id, $kb_article_info, 'phpbbmodders.knowledgebase.notification.type.approve');

		meta_refresh(2, $redirect);
		trigger_error('ARTICLE_APPROVED_SUCESS');
	}

	/**
	 * @param int    $art_id
	 * @param int    $status
	 * @param array  $kb_article_info
	 * @param array  $kb_category_info
	 * @param string $redirect
	 * @return void
	 */
	protected function do_deny(int $art_id, int $status, array $kb_article_info, array $kb_category_info, string $redirect): void
	{
		if (!in_array($status, [functions_kb::STATUS_NEW, functions_kb::STATUS_REVIEWED], true))
		{
			return;
		}

		// Reuse the same cleanup a manual delete already does (attachments,
		// category counts, cross-listings, field values, pending
		// revisions) rather than a bare row delete that leaves those
		// behind.
		$this->kb->kb_delete_article($art_id, $kb_article_info['article_title']);

		$this->log->add('admin', $this->user->data['user_id'], $this->user->data['user_ip'], 'LOG_LIBRARY_REJECTED_ARTICLE', time(), [$kb_article_info['article_title'], $kb_category_info['category_name'], $kb_article_info['author']]);

		$this->delete_need_approval_notification($art_id, $kb_article_info);
		$this->send_author_notification($art_id, $kb_article_info, 'phpbbmodders.knowledgebase.notification.type.disapprove');

		meta_refresh(2, $redirect);
		trigger_error('ARTICLE_DISAPPROVED_SUCESS');
	}

	/**
	 * @param int    $art_id
	 * @param int    $status
	 * @param int    $from_status
	 * @param int    $to_status
	 * @param string $redirect
	 * @return void
	 */
	protected function do_set_active(int $art_id, int $status, int $from_status, int $to_status, string $redirect): void
	{
		if ($status !== $from_status)
		{
			return;
		}

		$approved = $to_status === functions_kb::STATUS_ACTIVE ? 1 : 0;
		$sql = 'UPDATE ' . $this->articles_table . ' SET status = ' . $to_status . ', approved = ' . $approved . ' WHERE article_id = ' . $art_id;
		$this->db->sql_query($sql);
		$this->cache->destroy('sql', $this->articles_table);

		meta_refresh(2, $redirect);
		trigger_error($to_status === functions_kb::STATUS_ACTIVE ? 'ARTICLE_REACTIVATED' : 'ARTICLE_DEACTIVATED');
	}

	/**
	 * @param int    $art_id
	 * @param int    $status
	 * @param array  $kb_article_info
	 * @param string $redirect
	 * @return void
	 */
	protected function do_approve_revision(int $art_id, int $status, array $kb_article_info, string $redirect): void
	{
		if ($status !== functions_kb::STATUS_ACTIVE)
		{
			return;
		}

		$revision = $this->kb->get_article_revision($art_id);
		if (!$revision)
		{
			return;
		}

		$sql_data = [
			'article_title'       => $revision['article_title'],
			'article_description' => $revision['article_description'],
			'article_body'        => $revision['article_body'],
			'bbcode_uid'          => $revision['bbcode_uid'],
			'redirect_url'        => $revision['redirect_url'] ?? '',
			'edit_date'           => time(),
		];

		$sql = 'UPDATE ' . $this->articles_table . '
			SET ' . $this->db->sql_build_array('UPDATE', $sql_data) . '
			WHERE article_id = ' . $art_id;
		$this->db->sql_query($sql);

		$this->kb->delete_article_revision($art_id);

		try
		{
			$kb_search = $this->search_factory->get_active();
			$kb_search->index('edit', $art_id, $revision['article_body'], $revision['article_title'], $revision['article_description'], (int) $kb_article_info['author_id']);
		}
		catch (RuntimeException $e)
		{
			if (!str_starts_with($e->getMessage(), 'No service found'))
			{
				throw $e;
			}
		}

		$this->cache->destroy('sql', $this->articles_table);

		meta_refresh(2, $redirect);
		trigger_error('ARTICLE_REVISION_APPROVED');
	}

	/**
	 * @param int    $art_id
	 * @param int    $status
	 * @param string $redirect
	 * @return void
	 */
	protected function do_reject_revision(int $art_id, int $status, string $redirect): void
	{
		if ($status !== functions_kb::STATUS_ACTIVE)
		{
			return;
		}

		$this->kb->delete_article_revision($art_id);

		meta_refresh(2, $redirect);
		trigger_error('ARTICLE_REVISION_REJECTED');
	}

	/**
	 * @param int   $art_id
	 * @param array $kb_article_info
	 * @return void
	 */
	protected function delete_need_approval_notification(int $art_id, array $kb_article_info): void
	{
		$this->notification_manager->delete_notifications('phpbbmodders.knowledgebase.notification.type.need_approval', [
			'author_id'           => $kb_article_info['author_id'],
			'title'               => $kb_article_info['article_title'],
			'article_category_id' => $kb_article_info['article_category_id'],
			'item_id'             => $art_id,
		]);
	}

	/**
	 * @param int    $art_id
	 * @param array  $kb_article_info
	 * @param string $notification_type
	 * @return void
	 */
	protected function send_author_notification(int $art_id, array $kb_article_info, string $notification_type): void
	{
		$this->notification_manager->add_notifications($notification_type, [
			'author_id'   => $kb_article_info['author_id'],
			'author_type' => $kb_article_info['author_type'],
			'title'       => $kb_article_info['article_title'],
			'moderator'   => $this->user->data['user_id'],
			'item_id'     => $art_id,
		]);
	}

	/**
	 * @param int $selected_team_id
	 * @return string
	 */
	protected function get_team_options(int $selected_team_id): string
	{
		$options = '';
		foreach ($this->kb->get_kb_team_group_names() as $group_id => $group_name)
		{
			$options .= '<option value="' . $group_id . '"' . ($group_id === $selected_team_id ? ' selected="selected"' : '') . '>' . $group_name . '</option>';
		}
		return $options;
	}
}
