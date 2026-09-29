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
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;
use phpbbmodders\knowledgebase\inc\field\kb_field_type_factory;
use phpbbmodders\knowledgebase\inc\functions_kb;

class article
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

	/** @var functions_kb */
	protected functions_kb $kb;

	/** @var kb_field_type_factory */
	protected kb_field_type_factory $field_factory;

	/** @var string */
	protected string $phpbb_root_path;

	/** @var string */
	protected string $php_ext;

	/** @var string */
	protected string $articles_table;

	/** @var string */
	protected string $attachments_table;

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
	 * @param functions_kb          $kb
	 * @param kb_field_type_factory $field_factory
	 * @param string                $phpbb_root_path
	 * @param string                $php_ext
	 * @param string                $articles_table
	 * @param string                $attachments_table
	 */
	public function __construct(
		driver_interface $db, config $config, helper $helper, language $language, auth $auth,
		request_interface $request, template $template, user $user, functions_kb $kb,
		kb_field_type_factory $field_factory,
		string $phpbb_root_path, string $php_ext, string $articles_table, string $attachments_table
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
		$this->kb = $kb;
		$this->field_factory = $field_factory;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->articles_table = $articles_table;
		$this->attachments_table = $attachments_table;
	}

	/**
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function show(): \Symfony\Component\HttpFoundation\Response
	{
		if (!$this->auth->acl_get('u_kb_view') && !$this->auth->acl_get('a_manage_kb'))
		{
			trigger_error($this->language->lang('NOT_AUTHORISED'));
		}

		$art_id = $this->request->variable('k', 0);
		$mode = $this->request->variable('mode', '');

		if (empty($art_id))
		{
			trigger_error($this->language->lang('NO_ID_SPECIFIED'));
		}

		$sql = 'SELECT a.*, u.user_colour, u.username
			FROM ' . $this->articles_table . ' a
			LEFT JOIN ' . USERS_TABLE . ' u ON (u.user_id = a.author_id AND a.author_type = 0)
			WHERE a.article_id = ' . (int) $art_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (empty($row))
		{
			return $this->render_notice('ARTICLE_NO_EXISTS');
		}

		$fid = $this->config['kb_forum_id'];
		$cat_id = $row['article_category_id'];
		$article_categories = $this->kb->get_article_categories($art_id);
		$status = (int) $row['status'];

		$effective_team_id = $this->kb->get_effective_team_id((int) $row['assigned_team_id'], (int) $cat_id);
		$can_moderate = $this->auth->acl_get('a_manage_kb') || $this->kb->acl_kb_get_any($article_categories, 'kb_m_approve');
		$can_review = $status === functions_kb::STATUS_NEW && $effective_team_id &&
			$this->kb->is_team_member([$effective_team_id]);
		$is_own_article = $this->kb->is_article_author($art_id, (int) $row['author_id'], (int) $row['author_type']);

		// A draft is private to its author/co-authors - never visible to a
		// moderator or reviewer the way every other unapproved status is,
		// since it was never actually submitted for anyone to act on.
		if ($status === functions_kb::STATUS_DRAFT)
		{
			if (!$is_own_article)
			{
				return $this->render_notice('ARTICLE_NO_EXISTS');
			}
		}
		else if (!$row['approved'] && !$can_moderate && !$can_review && !$is_own_article)
		{
			return $this->render_notice('ARTICLE_NO_EXISTS');
		}

		if ($row['is_redirect'])
		{
			$sql = 'UPDATE ' . $this->articles_table . '
				SET views = views + 1
				WHERE article_id = ' . (int) $art_id;
			$this->db->sql_query($sql);

			return new \Symfony\Component\HttpFoundation\RedirectResponse($row['redirect_url']);
		}

		$catrow = $this->kb->get_cat_info($row['article_category_id']);
		if (empty($catrow))
		{
			trigger_error($this->language->lang('CAT_NO_EXISTS'));
		}

		$this->template->assign_vars([
			'ARTICLE_CATEGORY' => '<a href="' . $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $catrow['category_id']]) . '">' . $catrow['category_name'] . '</a>',
			'CATS_DROPBOX'     => $this->kb->make_category_dropbox(),
			'S_ACTION'         => $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $cat_id]),
		]);

		foreach ($article_categories as $article_category_id)
		{
			$cross_catrow = ($article_category_id === (int) $cat_id) ? $catrow : $this->kb->get_cat_info($article_category_id);
			if (empty($cross_catrow))
			{
				continue;
			}
			$this->template->assign_block_vars('article_category', [
				'CATEGORY_NAME' => $cross_catrow['category_name'],
				'U_CATEGORY'    => $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $article_category_id]),
				'S_PRIMARY'     => $article_category_id === (int) $cat_id,
			]);
		}

		foreach ($this->kb->get_co_authors_display($art_id) as $co_author)
		{
			$this->template->assign_block_vars('co_author', [
				'NAME'   => $co_author['name'],
				'COLOUR' => $co_author['colour'],
				'URL'    => $co_author['url'],
				'S_GROUP' => $co_author['type'] === functions_kb::AUTHOR_TYPE_GROUP,
			]);
		}

		$field_display = '';
		foreach ($this->kb->get_enabled_fields() as $field)
		{
			$field_id = (int) $field['field_id'];
			$selected_values = $this->kb->get_article_field_values($art_id, $field_id);
			$field_display .= $this->field_factory->get($field['field_type'])->render_display($field, $selected_values);
		}
		$this->template->assign_var('FIELD_DISPLAY', $field_display);

		$comment_topic_id = $row['topic_id'];

		// Get comments
		if ($comment_topic_id)
		{
			$count = -1;
			$sql = 'SELECT DISTINCT p.poster_id, p.post_time, p.post_subject, p.post_text, p.bbcode_uid, p.bbcode_bitfield, u.user_id, u.username
				FROM ' . POSTS_TABLE . ' p, ' . USERS_TABLE . ' u
				WHERE p.topic_id = ' . (int) $comment_topic_id . '
				AND (p.poster_id = u.user_id)
				ORDER BY p.post_time ASC';
			$res = $this->db->sql_query($sql);
			while ($postrow = $this->db->sql_fetchrow($res))
			{
				$count++;
				if ($count > 0)
				{
					$this->template->assign_block_vars('postrow', [
						'POSTER_NAME'  => $postrow['username'],
						'POST_DATE'    => $this->user->format_date($postrow['post_time']),
						'POST_SUBJECT' => $postrow['post_subject'],
						'MESSAGE'      => generate_text_for_display($postrow['post_text'], $postrow['bbcode_uid'], $postrow['bbcode_bitfield'], 3, true),
					]);
				}
			}
			$this->db->sql_freeresult($res);

			$temp_url = append_sid("{$this->phpbb_root_path}viewtopic." . $this->php_ext, 'f=' . $fid . '&amp;t=' . $row['topic_id']);
		}
		$views = $row['views'];
		$article = $row['article_id'];
		$text = generate_text_for_display($row['article_body'], $row['bbcode_uid'], false, 3, true);

		$sql = 'SELECT *
			FROM ' . $this->attachments_table . '
			WHERE article_id = ' . (int) $art_id . '
			ORDER BY attach_id DESC';
		$result = $this->db->sql_query($sql);

		while ($attach_row = $this->db->sql_fetchrow($result))
		{
			$attachments[] = $attach_row;
		}
		$this->db->sql_freeresult($result);

		// Parse attachments
		if (isset($attachments) && count($attachments))
		{
			$this->kb->parse_att($text, $attachments);
		}

		include_once($this->phpbb_root_path . 'includes/functions_display.' . $this->php_ext);
		$rank = phpbb_get_user_rank($this->user->data, false);

		$this->template->assign_vars([
			'ARTICLE_AUTHOR'      => ((int) $row['author_type'] === 1) ? $row['author'] : get_username_string('full', $row['author_id'], $row['username'], $row['user_colour']),
			'ARTICLE_DESCRIPTION' => $row['article_description'],
			'ARTICLE_DATE'        => $this->user->format_date($row['article_date']),
			'EDIT_DATE'           => $row['edit_date'] ? $this->user->format_date($row['edit_date']) : '',
			'ART_VIEWS'           => $row['views'],
			'ARTICLE_TITLE'       => $row['article_title'],
			'ARTICLE_TEXT'        => $text,
			'VIEWS'               => $views,
			'RANK'                => $rank['title'],
			'U_EDIT_ART'          => $this->helper->route('phpbbmodders_knowledgebase_posting', ['mode' => 'edit', 'id' => $cat_id, 'k' => $art_id]),
			'U_DELETE_ART'        => $this->helper->route('phpbbmodders_knowledgebase_posting', ['mode' => 'delete', 'id' => $cat_id, 'k' => $art_id]),
			'U_APPROVE_ART'       => $this->helper->route('phpbbmodders_knowledgebase_approve', ['id' => $art_id]),
			'U_PRINT'             => $this->helper->route('phpbbmodders_knowledgebase_article', ['mode' => 'print', 'k' => $art_id]),
			'U_ARTICLE'           => '[url=' . generate_board_url() . '/knowledgebase/article?k=' . $art_id . ']' . $row['article_title'] . '[/url]',
			'U_DIRECT_LINK'       => generate_board_url() . '/knowledgebase/article?k=' . $art_id,
			'COMMENTS'            => ($comment_topic_id) ? $this->language->lang('COMMENTS') . $this->language->lang('COLON') . ' ' . $count : '',
			'U_COMMENTS'          => ($comment_topic_id) ? $temp_url : '',
			'S_CAN_EDIT'          => $this->kb->acl_kb_get_any($article_categories, 'kb_m_edit') || ($is_own_article && $this->kb->acl_kb_get_any($article_categories, 'kb_u_edit') || $this->auth->acl_get('a_manage_kb')),
			'S_CAN_DELETE'        => $this->kb->acl_kb_get_any($article_categories, 'kb_m_delete') || ($is_own_article && $this->kb->acl_kb_get_any($article_categories, 'kb_u_delete') || $this->auth->acl_get('a_manage_kb')),
			'S_CAN_APPROVE'       => $can_moderate || $can_review,
			'COUNT_COMMENTS'      => ($comment_topic_id) ? '[' . $this->language->lang('LEAVE_COMMENTS') . ']' : '',
			'U_FORUM'             => generate_board_url() . '/',
			'S_APPROVED'          => $row['approved'],
			'S_KNOWLEDGEBASE'     => true,
			'LIBRARY'             => $this->language->lang('LIBRARY'),
			'S_STATUS_NEW'        => $status === functions_kb::STATUS_NEW,
			'S_STATUS_REVIEWED'   => $status === functions_kb::STATUS_REVIEWED,
			'S_STATUS_ACTIVE'     => $status === functions_kb::STATUS_ACTIVE,
			'S_STATUS_INACTIVE'   => $status === functions_kb::STATUS_INACTIVE,
			'S_STATUS_DRAFT'      => $status === functions_kb::STATUS_DRAFT,
			'S_HAS_REVISION'      => in_array($status, [functions_kb::STATUS_ACTIVE, functions_kb::STATUS_INACTIVE], true) && $this->kb->article_has_pending_revision($art_id),
		]);

		if ($mode != 'print' && $row['approved'])
		{
			// Increase the number of views
			$sql = 'UPDATE ' . $this->articles_table . '
				SET views = views + 1
				WHERE article_id = ' . (int) $article;
			$this->db->sql_query($sql);
		}

		$this->template->assign_block_vars('navlinks', [
			'FORUM_NAME'   => $this->language->lang('LIBRARY'),
			'U_VIEW_FORUM' => $this->helper->route('phpbbmodders_knowledgebase_index'),
		]);

		foreach ($this->kb->get_category_branch($cat_id, 'parents') as $row)
		{
			$this->template->assign_block_vars('navlinks', [
				'FORUM_NAME'   => $row['category_name'],
				'U_VIEW_FORUM' => $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $row['category_id']]),
			]);
		}

		$html_template = (($mode != 'print')) ? 'kb_article_body.html' : 'kb_article_body_print.html';

		$this->kb->assign_nav_tabs_vars('', $cat_id, $this->language->lang('KB_TAB_READING_ARTICLE', $art_id));

		return $this->helper->render('@phpbbmodders_knowledgebase/' . $html_template, $this->language->lang('LIBRARY'));
	}

	/**
	 * Renders a templated "Notice" page (tab strip intact, sixth tab
	 * reading "Notice") for the article-not-found case, matching
	 * phpbb.com's own KB - scoped narrowly to this one case; every
	 * other error path in the extension keeps using trigger_error().
	 *
	 * @param string $message_lang_key
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	protected function render_notice(string $message_lang_key): \Symfony\Component\HttpFoundation\Response
	{
		$this->template->assign_vars([
			'NOTICE_MESSAGE'  => $this->language->lang($message_lang_key),
			'S_KNOWLEDGEBASE' => true,
		]);
		$this->kb->assign_nav_tabs_vars('', 0, $this->language->lang('KB_TAB_NOTICE'));

		return $this->helper->render('@phpbbmodders_knowledgebase/kb_notice_body.html', $this->language->lang('LIBRARY') . ' &raquo; ' . $this->language->lang('KB_TAB_NOTICE'));
	}
}
