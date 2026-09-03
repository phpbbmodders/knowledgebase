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
use phpbb\extension\manager;
use phpbb\files\factory;
use phpbb\language\language;
use phpbb\log\log;
use phpbb\notification\manager as notification_manager;
use phpbb\plupload\plupload;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;
use RuntimeException;
use phpbbmodders\knowledgebase\inc\field\kb_field_type_factory;
use phpbbmodders\knowledgebase\inc\functions_kb;
use phpbbmodders\knowledgebase\search\kb_search_backend_factory;

class posting
{
	/** @var driver_interface */
	protected driver_interface $db;

	/** @var config */
	protected config $config;

	/** @var helper */
	protected helper $helper;

	/** @var manager */
	protected manager $ext_manager;

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

	/** @var factory */
	protected factory $files_factory;

	/* @var plupload */
	protected plupload $plupload;

	/** @var notification_manager */
	protected notification_manager $notification_manager;

	/** @var functions_kb */
	protected functions_kb $kb;

	/** @var kb_search_backend_factory */
	protected kb_search_backend_factory $search_factory;

	/** @var kb_field_type_factory */
	protected kb_field_type_factory $field_factory;

	/** @var string */
	protected string $phpbb_root_path;

	/** @var string */
	protected string $php_ext;

	/** @var string */
	protected string $logs_table;

	/** @var string */
	protected string $articles_table;

	/** @var string */
	protected string $categories_table;

	/** @var string */
	protected string $attachments_table;

	/** @var string */
	protected string $upload_dir;

	/**
	 * Constructor
	 *
	 * @param driver_interface          $db
	 * @param config                    $config
	 * @param helper                    $helper
	 * @param manager                   $ext_manager
	 * @param language                  $language
	 * @param auth                      $auth
	 * @param request_interface         $request
	 * @param template                  $template
	 * @param user                      $user
	 * @param cache                     $cache
	 * @param log                       $log
	 * @param factory                   $files_factory
	 * @param plupload                  $plupload
	 * @param notification_manager      $notification_manager
	 * @param functions_kb              $kb
	 * @param kb_search_backend_factory $search_factory
	 * @param kb_field_type_factory     $field_factory
	 * @param string                    $phpbb_root_path
	 * @param string                    $php_ext
	 * @param string                    $logs_table
	 * @param string                    $articles_table
	 * @param string                    $categories_table
	 * @param string                    $attachments_table
	 */
	public function __construct(
		driver_interface $db, config $config, helper $helper, manager $ext_manager, language $language, auth $auth,
		request_interface $request, template $template, user $user, cache $cache, log $log, factory $files_factory,
		plupload $plupload, notification_manager $notification_manager, functions_kb $kb,
		kb_search_backend_factory $search_factory, kb_field_type_factory $field_factory,
		string $phpbb_root_path, string $php_ext, string $logs_table, string $articles_table,
		string $categories_table, string $attachments_table
	)
	{
		$this->db = $db;
		$this->config = $config;
		$this->helper = $helper;
		$this->ext_manager = $ext_manager;
		$this->language = $language;
		$this->auth = $auth;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->cache = $cache;
		$this->log = $log;
		$this->files_factory = $files_factory;
		$this->plupload = $plupload;
		$this->notification_manager = $notification_manager;
		$this->kb = $kb;
		$this->search_factory = $search_factory;
		$this->field_factory = $field_factory;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->logs_table = $logs_table;
		$this->articles_table = $articles_table;
		$this->categories_table = $categories_table;
		$this->attachments_table = $attachments_table;

		$this->upload_dir = $this->ext_manager->get_extension_path('phpbbmodders/knowledgebase', true) . 'files/';
	}

	public function post_article(): \Symfony\Component\HttpFoundation\Response
	{
		if (!$this->auth->acl_get('u_kb_view') && !$this->auth->acl_get('a_manage_kb'))
		{
			trigger_error($this->language->lang('NOT_AUTHORISED'));
		}

		$this->language->add_lang(['plupload', 'posting']);
		$this->log->set_log_table($this->logs_table);

		$fid = $this->config['kb_forum_id'];

		if (empty($this->config['kb_forum_id']) && $this->config['kb_anounce'])
		{
			trigger_error('WARNING_DEFAULT_CONFIG');
		}

		$cat_id = (int) $this->request->variable('id', 0);
		$art_id = (int) $this->request->variable('k', 0);
		$mode = $this->request->variable('mode', '');

		// Redirect entries are moderator-only, same gate as team assignment
		// in Phase 1 - no new permission invented for this.
		$is_redirect = $mode === 'redirect';
		if ($is_redirect)
		{
			if (!$this->auth->acl_get('a_manage_kb') && !$this->kb->acl_kb_get($cat_id, 'kb_m_approve'))
			{
				trigger_error('NOT_AUTHORISED');
			}
		}
		else if (!$this->kb->acl_kb_get($cat_id, 'kb_u_add'))
		{
			trigger_error('RULES_KB_ADD_CANNOT');
		}

		add_form_key('kb_posting');

		$row = '';
		$delete_allowed = $edit_allowed = $article_author_id = false;
		if ($mode === 'edit' || $mode === 'delete')
		{
			$sql = 'SELECT DISTINCT a.*, c.category_name, c.category_id
				FROM ' . $this->articles_table . ' a, ' . $this->categories_table . ' c
				WHERE article_id = ' . $art_id . '
					AND (c.category_id = a.article_category_id)';
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if (empty($row))
			{
				trigger_error('ARTICLE_NO_EXISTS');
			}

			$article_author_id = $row['author_id'];

			$is_own_article = ((int) $row['author_type'] === 1)
				? $this->kb->is_team_member([(int) $article_author_id])
				: $this->user->data['user_id'] == $article_author_id;

			$article_categories = $this->kb->get_article_categories($art_id);

			$edit_allowed = ($this->kb->acl_kb_get_any($article_categories, 'kb_m_edit') || (
					$is_own_article &&
					$this->kb->acl_kb_get_any($article_categories, 'kb_u_edit'))
			);

			$delete_allowed = ($this->kb->acl_kb_get_any($article_categories, 'kb_m_delete') || (
					$is_own_article &&
					$this->kb->acl_kb_get_any($article_categories, 'kb_u_delete'))
			);
		}

		// Select which method we'll use to obtain the post_id or topic_id information
		try
		{
			$kb_search = $this->search_factory->get_active();
		}
		catch (RuntimeException $e)
		{
			$kb_search = false;
			if (str_starts_with($e->getMessage(), 'No service found'))
			{
				trigger_error('NO_SUCH_SEARCH_MODULE');
			}
			else
			{
				throw $e;
			}
		}

		$error = [];
		$submit = $this->request->is_set_post('submit');
		$preview = $this->request->is_set_post('preview');
		$cancel = $this->request->is_set_post('cancel');
		$delete = $this->request->is_set_post('delete');
		$edit = $mode == 'edit';

		$action = $this->helper->route('phpbbmodders_knowledgebase_posting', ['id' => $cat_id]);

		if ($mode == 'delete' || $delete)
		{
			if (!$delete_allowed)
			{
				trigger_error('RULES_KB_MOD_DELETE_CANNOT');
			}

			$s_hidden_fields = build_hidden_fields([
					'mode' => 'delete',
					'k'    => $art_id,
				]
			);

			if (confirm_box(true))
			{
				$art_info = $this->kb->get_kb_article_info($art_id);
				$this->kb->kb_delete_article($art_id, $art_info['article_title']);
				if ($kb_search)
				{
					$author_ids[] = $art_info['author_id'];
					$art_ids[] = $art_id;
					$kb_search->index_remove($art_ids, $author_ids);
				}
				$msg = $this->language->lang('ARTICLE_DELETED');
				$root = $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $cat_id]);
				$msg .= '<br><br>' . sprintf($this->language->lang('RETURN_CAT'), '<a href="' . $root . '">', '</a>');
				$this->cache->destroy('sql', $this->categories_table);
				$this->cache->destroy('sql', $this->articles_table);
				meta_refresh(2, $root);
				trigger_error($msg);
			}
			else
			{
				confirm_box(false, $this->language->lang('CONFIRM_DELETE_ARTICLE'), $s_hidden_fields);
			}
		}

		if ($mode == 'edit')
		{
			$to_id = (int) $this->request->variable('to_id', 0);
			if (empty($art_id))
			{
				trigger_error($this->language->lang('NO_ID_SPECIFIED'));
			}

			$action = $this->helper->route('phpbbmodders_knowledgebase_posting', ['mode' => 'edit', 'k' => $art_id, 'id' => $cat_id]);

			$uid = $bitfield = $options = '';

			$article_title = (string) $row['article_title'];
			$article_text = (string) $row['article_body'];
			$article_description = (string) $row['article_description'];
			$article_author = (string) $row['author'];
			$article_author_type = (int) ($row['author_type'] ?? 0);
			$views = (int) $row['views'];
			$article_date = (int) $row['article_date'];
			$order = (int) $row['display_order'];
			$article_status = (int) ($row['status'] ?? functions_kb::STATUS_NEW);
			$is_redirect = $is_redirect || !empty($row['is_redirect']);
			$redirect_url = (string) ($row['redirect_url'] ?? '');

			$article_text = $this->decode_message($article_text, $row['bbcode_uid']);

			if (!$edit_allowed)
			{
				trigger_error('RULES_KB_MOD_EDIT_CANNOT');
			}
		}

		if (!$is_redirect)
		{
			$this->kb_parse_attachments($art_id, $attachment_data, $preview, $edit, $submit);
		}

		$bbcode_status = true;
		$smilies_status = true;
		$img_status = true;
		$url_status = true;

		$allowed_bbcode = $allowed_smilies = $allowed_urls = true;

		$article_title = (isset($article_title)) ? $this->request->variable('subject', $article_title, true) : $this->request->variable('subject', '', true);
		$article_text = (isset($article_text)) ? $this->request->variable('message', $article_text, true) : $this->request->variable('message', '', true);
		$article_description = (isset($article_description)) ? $this->request->variable('descr', $article_description, true) : $this->request->variable('descr', '', true);
		$redirect_url = (isset($redirect_url)) ? $this->request->variable('redirect_url', $redirect_url, true) : $this->request->variable('redirect_url', '', true);

		$articles_count = 0;
		$category_name = '';
		if ($row = $this->kb->get_cat_info($cat_id))
		{
			$articles_count = $row['number_articles'];
			$category_name = $row['category_name'];
		}
		else
		{
			trigger_error($this->language->lang('CAT_NO_EXISTS'));
		}

		// Cross-listed categories: only ever kept for a category the user
		// could have posted a new article into directly - the same guard
		// make_category_select() already uses to disable options in the UI.
		// (The article's actual primary category is excluded by
		// set_cross_listed_categories() itself, regardless of what's sent
		// here, so a category move happening in the same submit is handled
		// correctly without duplicating that check here.)
		$cross_categories = array_filter(array_map('intval', $this->request->variable('cross_categories', [0])));
		$cross_categories = array_values(array_filter($cross_categories, function ($category_id) {
			return $this->kb->acl_kb_get($category_id, 'kb_u_add');
		}));

		// What to show as pre-selected in the multi-select: whatever was
		// just submitted if this is a (possibly failed) submit attempt,
		// otherwise the article's existing cross-listings on a fresh edit
		// load, or nothing for a brand new article.
		$cross_categories_display = $submit ? $cross_categories : (($art_id) ? $this->kb->get_cross_listed_categories($art_id) : []);

		// Custom fields: read every field's submitted values in one go, then
		// pre-render each enabled field's posting widget for the template.
		$enabled_fields = $this->kb->get_enabled_fields();
		$all_field_values = $this->request->variable('field_values', [0 => [0]]);
		$field_widgets = '';
		foreach ($enabled_fields as $field)
		{
			$field_id = (int) $field['field_id'];
			$field_values = $this->kb->get_field_values($field_id, true);
			$submitted_ids = array_map('intval', $all_field_values[$field_id] ?? []);
			$selected_ids = $submit ? $submitted_ids : (($art_id) ? $this->kb->get_article_field_value_ids($art_id, $field_id) : []);

			$field_widgets .= $this->field_factory->get($field['field_type'])->render_posting_field($field, $field_values, $selected_ids);
		}

		include($this->phpbb_root_path . 'includes/functions_posting.' . $this->php_ext);
		generate_smilies('inline', 0);
		include($this->phpbb_root_path . 'includes/functions_display.' . $this->php_ext);
		display_custom_bbcodes();

		if ($submit)
		{
			if (!check_form_key('kb_posting'))
			{
				trigger_error('FORM_INVALID');
			}

			$has_content = $is_redirect ? $this->kb->is_valid_redirect_url($redirect_url) : (bool) $article_text;
			if ($article_title && $has_content)
			{
				if (!$is_redirect)
				{
					// to enable bbcode, urls and smilies parsing, be enabled it when using
					// generate_text_for_storage function
					generate_text_for_storage($article_text, $bbcode_uid, $bbcode_bitfield, $options, true, true, true);
				}
				else
				{
					$bbcode_uid = $bbcode_bitfield = '';
				}

				// Post-as-team: only offered to users who can already moderate this category's approvals.
				$post_as_group = 0;
				if ($this->auth->acl_get('a_manage_kb') || $this->kb->acl_kb_get($cat_id, 'kb_m_approve'))
				{
					$post_as_group = $this->request->variable('post_as_group', 0);
				}
				$posting_as_group = $post_as_group && in_array($post_as_group, $this->kb->get_kb_team_groups(), true);
				$author_type = $posting_as_group ? functions_kb::AUTHOR_TYPE_GROUP : functions_kb::AUTHOR_TYPE_USER;
				$author_id = $posting_as_group ? $post_as_group : (int) $this->user->data['user_id'];
				$author_name = $posting_as_group ? ($this->kb->get_kb_team_group_names()[$post_as_group] ?? '') : $this->user->data['username'];

				$can_add_noapprove = $this->kb->acl_kb_get($cat_id, 'kb_u_add_noapprove');

				$sql_data = [
					'article_category_id' => $cat_id,
					'article_title'       => $article_title,
					'article_description' => $article_description,
					'article_date'        => time(),
					'author_id'           => $author_id,
					'author_type'         => $author_type,
					'bbcode_uid'          => substr(md5(rand()), 0, 8),
					'article_body'        => $article_text,
					'is_redirect'         => $is_redirect ? 1 : 0,
					'redirect_url'        => $is_redirect ? $redirect_url : '',
					'views'               => 0,
					'author'              => $author_name,
					'approved'            => $can_add_noapprove ? 1 : 0,
					'status'              => $can_add_noapprove ? functions_kb::STATUS_ACTIVE : functions_kb::STATUS_NEW,
				];

				$root = $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $cat_id]);

				if ($mode == 'edit')
				{
					$sql_data['author_id'] = $article_author_id;
					$sql_data['author'] = $article_author;
					$sql_data['author_type'] = $article_author_type;
					$sql_data['views'] = $views;
					$sql_data['article_date'] = $article_date;
					if ($article_status === functions_kb::STATUS_INACTIVE)
					{
						// Deliberately deactivated by a moderator - editing shouldn't
						// silently reactivate it; that's a separate explicit action.
						unset($sql_data['approved'], $sql_data['status']);
					}
					// New/Reviewed: approved/status stay as computed above (from the
					// editor's own kb_u_add_noapprove permission), same as a fresh submit.
					$redirect = $this->helper->route('phpbbmodders_knowledgebase_article', ['k' => $art_id]);

					// Only move into a category that actually exists and that the
					// editor could post into directly - same guard the cross-listing
					// select already applies. An invalid/unauthorised destination is
					// silently ignored rather than trusted from the request.
					if ($to_id !== $cat_id && (!$this->kb->get_cat_info($to_id) || !$this->kb->acl_kb_get($to_id, 'kb_u_add')))
					{
						$to_id = $cat_id;
					}

					$moving_category = ($cat_id !== $to_id);
					if ($moving_category)
					{
						$sql_data['article_category_id'] = $to_id;
						$sql_data['display_order'] = 0;
					}

					if ($article_status === functions_kb::STATUS_ACTIVE)
					{
						// Live article: queue the content edit as a pending revision rather
						// than touching the published content.
						$this->kb->save_article_revision($art_id, [
							'article_title'       => $article_title,
							'article_description' => $article_description,
							'article_body'        => $article_text,
							'bbcode_uid'          => $bbcode_uid,
							'bbcode_bitfield'     => $bbcode_bitfield,
							'redirect_url'        => $redirect_url,
						], (int) $this->user->data['user_id']);

						if ($moving_category)
						{
							$sql = 'UPDATE ' . $this->articles_table . '
								SET article_category_id = ' . $to_id . ', display_order = 0
								WHERE article_id = ' . $art_id;
							$this->db->sql_query($sql);
						}

						$msg = $this->language->lang('ARTICLE_REVISION_SUBMITTED');
					}
					else
					{
						$sql_data['edit_date'] = time();

						$sql = 'UPDATE ' . $this->articles_table . '
							SET ' . $this->db->sql_build_array('UPDATE', $sql_data) . '
							WHERE article_id = ' . $art_id;
						$this->db->sql_query($sql);

						// Upd search index
						if ($kb_search)
						{
							$kb_search->index('edit', $art_id, $article_text, $article_title, $article_description, (int) $article_author_id);
						}

						$msg = $this->language->lang('ARTICLE_EDITED');
					}

					if ($moving_category)
					{
						$sql = 'UPDATE ' . $this->categories_table . '
							SET number_articles = number_articles - 1
							WHERE category_id = ' . $cat_id;
						$this->db->sql_query($sql);

						$sql = 'UPDATE ' . $this->categories_table . '
							SET number_articles = number_articles + 1
							WHERE category_id = ' . $to_id;
						$this->db->sql_query($sql);

						$sql = 'UPDATE ' . $this->articles_table . ' SET display_order = display_order + 1
							WHERE article_category_id = ' . $to_id;
						$this->db->sql_query($sql);

						$sql = 'UPDATE ' . $this->articles_table . ' SET display_order = display_order - 1
							WHERE article_category_id = ' . $cat_id . '
							AND display_order > ' . $order;
						$this->db->sql_query($sql);
					}

					$this->kb->set_cross_listed_categories($art_id, $cross_categories);
					$this->save_article_field_values($art_id, $enabled_fields, $all_field_values);
					$this->insert_attachments($attachment_data, $art_id);

					$msg .= '<br><br>' . sprintf($this->language->lang('RETURN_ARTICLE'), '<a href="' . $redirect . '">', '</a>');
					$msg .= '<br><br>' . sprintf($this->language->lang('RETURN_CAT'), '<a href="' . $root . '">', '</a>');
					$this->log->add('admin', $this->user->data['user_id'], $this->user->data['user_ip'], 'LOG_LIBRARY_EDIT_ARTICLE', time(), [$article_title, $category_name]);
				}
				else
				{
					$sql = 'UPDATE ' . $this->articles_table . ' SET display_order = display_order + 1
						WHERE article_category_id = ' . $cat_id;
					$this->db->sql_query($sql);

					$sql_data = array_merge($sql_data, ['display_order' => 1]);
					$sql = 'INSERT INTO ' . $this->articles_table . '
						' . $this->db->sql_build_array('INSERT', $sql_data);
					$this->db->sql_query($sql);
					$new = $this->db->sql_nextid();
					$this->kb->set_cross_listed_categories($new, $cross_categories);
					$this->save_article_field_values($new, $enabled_fields, $all_field_values);
					$this->insert_attachments($attachment_data, $new);

					$articles_count++;
					$this->config->increment('kb_num_articles', 1);
					$sql = 'UPDATE ' . $this->categories_table . '
						SET number_articles = ' . $articles_count . '
						WHERE category_id = ' . $cat_id;
					$this->db->sql_query($sql);

					if ($can_add_noapprove)
					{
						$redirect = $this->helper->route('phpbbmodders_knowledgebase_article', ['k' => $new]);

						if (isset($kb_search))
						{
							// Add search index
							$kb_search->index('add', (int) $new, $article_text, $article_title, $article_description, $author_id);
						}

						if (!empty($this->config['kb_forum_id']) && $this->config['kb_anounce'])
						{
							$this->kb->submit_article($cat_id, $fid, $article_title, $article_description, $author_name, $category_name, $new);
						}

						$msg = $this->language->lang('ARTICLE_SUBMITTED');
						$msg .= '<br><br>' . sprintf($this->language->lang('RETURN_ARTICLE'), '<a href="' . $redirect . '">', '</a>');
					}
					else
					{
						$msg = $this->language->lang('ARTICLE_NEED_APPROVE');
						$redirect = $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $cat_id]);

						// Add notification
						$notification_data = [
							'author_id'           => $author_id,
							'author_type'         => $author_type,
							'title'               => $article_title,
							'article_category_id' => $cat_id,
							'item_id'             => $new,
						];
						$this->notification_manager->add_notifications('phpbbmodders.knowledgebase.notification.type.need_approval', $notification_data);
					}

					$this->cache->destroy('sql', $this->categories_table);
					$this->cache->destroy('sql', $this->articles_table);

					$msg .= '<br><br>' . sprintf($this->language->lang('RETURN_CAT'), '<a href="' . $root . '">', '</a>');
					$this->log->add('admin', $this->user->data['user_id'], $this->user->data['user_ip'], 'LOG_LIBRARY_ADD_ARTICLE', time(), [$article_title, $category_name]);
				}
				meta_refresh(2, $redirect);
				trigger_error($msg);
			}
			else
			{
				if (!$article_title)
				{
					$error[] = $this->language->lang('NO_TITLE');
				}

				if ($is_redirect)
				{
					if (!$has_content)
					{
						$error[] = $this->language->lang('KB_REDIRECT_INVALID_URL');
					}
				}
				else if (!$article_text)
				{
					$error[] = $this->language->lang('NO_TEXT');
				}
			}
		}
		if ($cancel)
		{
			redirect($this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $cat_id]));
		}

		if ($preview)
		{
			if (!$article_title)
			{
				$error[] = $this->language->lang('NO_TITLE');
			}

			if ($is_redirect)
			{
				if (!$this->kb->is_valid_redirect_url($redirect_url))
				{
					$error[] = $this->language->lang('KB_REDIRECT_INVALID_URL');
				}
			}
			else if (!$article_text)
			{
				$error[] = $this->language->lang('NO_TEXT');
			}

			$uid = $bitfield = $options = '';
			$preview_text = $article_text;

			if (!$is_redirect)
			{
				generate_text_for_storage($preview_text, $uid, $bitfield, $options, true, true, true);

				$preview_text = generate_text_for_display($preview_text, $uid, $bitfield, $options);

				// Parse attachments
				if (count($attachment_data))
				{
					$this->kb->parse_att($preview_text, $attachment_data);
				}
			}

			$this->template->assign_vars([
					'PREVIEW_MESSAGE' => $preview_text,
					'PREVIEW_SUBJECT' => $article_title,
				]
			);
		}

		$this->template->assign_vars([
				'L_POST_A'          => ($mode == 'edit') ? $this->language->lang('EDIT_ARTICLE') : ($is_redirect ? $this->language->lang('KB_SUBMIT_REDIRECT') : $this->language->lang('ADD_ARTICLE')),
				'CATEGORY_NAME'     => $category_name,
				'DESCR'             => $article_description,
				'TOPIC_TITLE'       => $article_title,
				'SUBJECT'           => $article_title,
				'MESSAGE'           => $article_text,
				'S_IS_REDIRECT'     => $is_redirect,
				'REDIRECT_URL'      => $redirect_url,
				'ERROR'             => (count($error)) ? implode('<br>', $error) : '',
				'S_DISPLAY_PREVIEW' => !count($error) && $preview,
				'POST_DATE'         => $this->user->format_date(time()),
				'PREVIEW_SUBJECT'   => (isset($article_title)) ? $article_title : '',
				'PREVIEW_MESSAGE'   => (isset($preview_text)) ? $preview_text : '',
				'S_BBCODE_ALLOWED'  => ($bbcode_status) ? 1 : 0,
				'BBCODE_STATUS'     => ($bbcode_status) ? sprintf($this->language->lang('BBCODE_IS_ON'), '<a href="' . $this->helper->route('phpbb_help_bbcode_controller') . '">', '</a>') : sprintf($this->language->lang('BBCODE_IS_OFF'), '<a href="' . $this->helper->route('phpbb_help_bbcode_controller') . '">', '</a>'),
				'IMG_STATUS'        => ($img_status) ? $this->language->lang('IMAGES_ARE_ON') : $this->language->lang('IMAGES_ARE_OFF'),
				'SMILIES_STATUS'    => ($smilies_status) ? $this->language->lang('SMILIES_ARE_ON') : $this->language->lang('SMILIES_ARE_OFF'),
				'URL_STATUS'        => ($bbcode_status && $url_status) ? $this->language->lang('URL_IS_ON') : $this->language->lang('URL_IS_OFF'),
				'S_LINKS_ALLOWED'   => $url_status,
				'S_BBCODE_IMG'      => $img_status,
				'S_BBCODE_URL'      => $url_status,
				'S_BBCODE_QUOTE'    => true,
				'S_EDIT_POST'       => $mode == 'edit',
				'S_CAN_DELETE'      => isset($delete_allowed) && $delete_allowed,

				'S_FORM_ENCTYPE' => ($this->config['kb_allow_attachments']) ? ' enctype="multipart/form-data"' : '',

				'S_PLUPLOAD'              => (bool) $this->config['kb_allow_attachments'],
				'S_RESIZE'                => $this->plupload->generate_resize_string(),
				'FILESIZE'                => $this->config['kb_max_filesize'],
				'FILTERS'                 => $this->kb->generate_filter_string(),
				'CHUNK_SIZE'              => $this->plupload->get_chunk_size(),
				'S_PLUPLOAD_URL'          => generate_board_url() . '/knowledgebase/posting?id=' . $cat_id,
				'MAX_ATTACHMENTS'         => (!$this->auth->acl_get('a_manage_kb')) ? $this->config['kb_max_attachments'] : 0,
				'ATTACH_ORDER'            => 'desc',
				'L_TOO_MANY_ATTACHMENTS'  => $this->language->lang('TOO_MANY_ATTACHMENTS', $this->config['kb_max_attachments']),
				'S_ATTACH_DATA'           => (count($attachment_data)) ? json_encode($attachment_data) : '[]',
				'MAX_ATTACHMENT_FILESIZE' => $this->config['kb_max_filesize'] > 0 ? $this->language->lang('MAX_ATTACHMENT_FILESIZE', get_formatted_filesize($this->config['kb_max_filesize'])) : '',

				'CATS_BOX'       => '<option value="0" disabled="disabled">' . $this->language->lang('CATEGORIES_LIST') . '</option>' . $this->kb->make_category_select($cat_id, [], false),
				'CROSS_CATS_BOX' => $this->kb->make_category_select($cross_categories_display, [$cat_id], false),

				'S_CAN_POST_AS_TEAM' => $this->auth->acl_get('a_manage_kb') || $this->kb->acl_kb_get($cat_id, 'kb_m_approve'),
				'TEAM_OPTIONS'       => $this->get_team_select_options(),

				'FIELD_WIDGETS' => $field_widgets,

				'U_KB'           => $this->helper->route('phpbbmodders_knowledgebase_index'),
				'S_POST_ACTION'  => $action,
				'S_POST_ARTICLE' => true,
			]
		);

		$this->template->assign_block_vars('navlinks', [
				'FORUM_NAME'   => $this->language->lang('LIBRARY'),
				'U_VIEW_FORUM' => $this->helper->route('phpbbmodders_knowledgebase_index'),
			]
		);

		foreach ($this->kb->get_category_branch($cat_id, 'parents') as $row)
		{
			$this->template->assign_block_vars('navlinks', [
					'FORUM_NAME'   => $row['category_name'],
					'U_VIEW_FORUM' => $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $row['category_id']]),
				]
			);
		}

		$title = $mode == 'edit' ? $this->language->lang('EDIT_ARTICLE') : ($is_redirect ? $this->language->lang('KB_SUBMIT_REDIRECT') : $this->language->lang('ADD_ARTICLE'));

		$this->kb->assign_nav_tabs_vars($is_redirect ? 'redirect' : 'submit', $cat_id);

		return $this->helper->render('@phpbbmodders_knowledgebase/kb_post_body.html', ($this->language->lang('LIBRARY') . ' &raquo; ' . $title));
	}

	/**
	 * @param        $message
	 * @param string $bbcode_uid
	 * @return array|string|null
	 */
	public function decode_message($message, string $bbcode_uid = ''): array|string|null
	{
		if ($bbcode_uid)
		{
			$match = ['<br>', '[/*:m:' . $bbcode_uid . ']', ':u:' . $bbcode_uid, ':o:' . $bbcode_uid, ':' . $bbcode_uid];
			$replace = ["\n", '', '', '', ''];
		}
		else
		{
			$match = ['<br>'];
			$replace = ["\n"];
		}

		$message = str_replace($match, $replace, $message);
		$match = get_preg_expression('bbcode_htm');
		$replace = ['\1', '\1', '\2', '\1', '', ''];
		return preg_replace($match, $replace, $message);
	}

	/**
	 * @return string
	 */
	public function get_team_select_options(): string
	{
		$options = '<option value="0">' . $this->language->lang('POST_AS_MYSELF') . '</option>';
		foreach ($this->kb->get_kb_team_group_names() as $group_id => $group_name)
		{
			$options .= '<option value="' . $group_id . '">' . $group_name . '</option>';
		}
		return $options;
	}

	/**
	 * @param int   $article_id
	 * @param array $enabled_fields
	 * @param array $all_field_values field_id => submitted value_ids
	 * @return void
	 */
	protected function save_article_field_values(int $article_id, array $enabled_fields, array $all_field_values): void
	{
		foreach ($enabled_fields as $field)
		{
			$field_id = (int) $field['field_id'];
			$this->kb->set_article_field_values($article_id, $field_id, $all_field_values[$field_id] ?? []);
		}
	}

	/**
	 * @param $art_id
	 * @param $attachment_data
	 * @param $preview
	 * @param $edit
	 * @param $submit
	 * @return void
	 */
	public function kb_parse_attachments($art_id, &$attachment_data, $preview, $edit, $submit): void
	{
		$delete_file = $this->request->is_set_post('delete_file');
		$add_file = $this->request->is_set_post('add_file');
		$filename = $this->request->file('fileupload');
		$json_response = new \phpbb\json_response();
		$thumbnail = false;

		$this->plupload->set_upload_directories($this->upload_dir, $this->upload_dir . '/plupload');

		$attachment_data = $this->request->variable('attachment_data', [['' => '']], true, \phpbb\request\request_interface::POST);

		// First of all adjust comments if changed
		$actual_comment_list = $this->request->variable('comment_list', [''], true);

		foreach ($actual_comment_list as $comment_key => $comment)
		{
			if (!isset($attachment_data[$comment_key]))
			{
				continue;
			}

			if ($attachment_data[$comment_key]['attach_comment'] != $comment)
			{
				$attachment_data[$comment_key]['attach_comment'] = $comment;
			}
		}

		if ($delete_file)
		{
			$index = array_keys($this->request->variable('delete_file', [0]));
			$index = (!empty($index)) ? $index[0] : false;

			if ($index !== false && !empty($attachment_data[$index]))
			{
				$attach_id = (int) $attachment_data[$index]['attach_id'];

				// Never trust the attach_id the browser sent on its own -
				// only delete it if it actually belongs to this article, or
				// (for a not-yet-saved article) is an orphaned upload from
				// the current user's own posting session.
				$sql = 'SELECT physical_filename, thumbnail, article_id, poster_id, is_orphan
					FROM ' . $this->attachments_table . ' WHERE attach_id = ' . $attach_id;
				$result = $this->db->sql_query($sql);
				$attach_row = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);

				$owns_attachment = $attach_row && (
					($art_id && (int) $attach_row['article_id'] === (int) $art_id) ||
					(!$art_id && $attach_row['is_orphan'] && (int) $attach_row['poster_id'] === (int) $this->user->data['user_id'])
				);

				if ($owns_attachment)
				{
					unlink($this->upload_dir . $attach_row['physical_filename']);
					if ($attach_row['thumbnail'])
					{
						unlink($this->upload_dir . 'thumb_' . $attach_row['physical_filename']);
					}

					$sql = 'DELETE FROM ' . $this->attachments_table . ' WHERE attach_id = ' . $attach_id;
					$this->db->sql_query($sql);
					unset($attachment_data[$index]);

					$attachment_data = array_values($attachment_data);
				}

				$json_response->send($attachment_data);
			}
		}
		else if ($add_file)
		{
			$error = [];

			if ((empty($filename) || $filename['name'] === 'none'))
			{
				$error[] = $this->language->lang('NO_UPLOAD_FORM_FOUND');
			}

			$num_attachments = count($attachment_data);
			if ($num_attachments >= $this->config['kb_max_attachments'] && !$this->auth->acl_get('a_manage_kb'))
			{
				$error[] = sprintf($this->language->lang('MAX_NUM_ATTACHMENTS'), $num_attachments);
			}

			if (!count($error))
			{
				$allowed_extensions = $this->kb->allowed_extension();
				$fileupload = $this->files_factory->get('files.upload')
					->set_disallowed_content([])
					->set_allowed_extensions($allowed_extensions)
					->set_max_filesize($this->config['kb_max_filesize'])
				;

				$upload_file = (isset($this->files_factory)) ? $fileupload->handle_upload('files.types.form', 'fileupload') : $fileupload->form_upload('fileupload');

				$ext = $upload_file->get('extension');
				if (!in_array($upload_file->get('extension'), $allowed_extensions))
				{
					$error[] = sprintf($this->language->lang('DISALLOWED_EXTENSION'), $ext);
				}
				else
				{
					$is_image = $this->kb->check_is_img($upload_file->get('extension'));
					$upload_file->clean_filename('unique', $this->user->data['user_id'] . '_');
					$result = $upload_file->move_file($this->upload_dir, false, !$is_image);

					if (count($upload_file->error))
					{
						$upload_file->remove();
						$error = array_merge($error, $upload_file->error);
						$result = false;
					}

					if ($result)
					{
						if ($this->config['kb_allow_thumbnail'] && $is_image)
						{
							include($this->phpbb_root_path . 'includes/functions_posting.' . $this->php_ext);
							$thumbnail = create_thumbnail($this->upload_dir . $upload_file->get('realname'), $this->upload_dir . 'thumb_' . $upload_file->get('realname'), $upload_file->get('mimetype'));
						}

						$sql_ary = [
							'poster_id'         => $this->user->data['user_id'],
							'physical_filename' => $upload_file->get('realname'),
							'real_filename'     => $upload_file->get('uploadname'),
							'filesize'          => $filename['size'],
							'filetime'          => time(),
							'extension'         => $upload_file->get('extension'),
							'mimetype'          => $upload_file->get('mimetype'),
							'attach_comment'    => '',
							'thumbnail'         => ($thumbnail) ? 1 : 0,
						];

						$this->db->sql_query('INSERT INTO ' . $this->attachments_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
						$new = $this->db->sql_nextid();
						$new_entry = [
							'attach_id'         => $new,
							'is_orphan'         => 1,
							'real_filename'     => $upload_file->get('uploadname'),
							'physical_filename' => $upload_file->get('realname'),
							'filesize'          => $filename['size'],
							'filetime'          => time(),
							'extension'         => $upload_file->get('extension'),
							'mimetype'          => $upload_file->get('mimetype'),
							'attach_comment'    => '',
							'thumbnail'         => ($thumbnail) ? 1 : 0,
						];
						$attachment_data = array_merge([$new_entry], $attachment_data);
						$download_url = 'kb_file?id=' . $new;
						$json_response->send(['data' => $attachment_data, 'download_url' => $download_url]);
					}
				}
			}

			if (count($error))
			{
				$json_response->send([
					'jsonrpc' => '2.0',
					'id'      => 'id',
					'error'   => [
						'code'    => 105,
						'message' => current($error),
					],
				]);
			}
		}
		$this->get_kb_submitted_attachments($art_id, $attachment_data, $preview, $edit, $submit);
		$this->plupload->set_upload_directories($this->config['upload_path'], $this->config['upload_path'] . '/plupload');
	}

	/**
	 * @param $art_id
	 * @param $attachment_data
	 * @param $preview
	 * @param $edit
	 * @param $submit
	 * @return void
	 */
	public function get_kb_submitted_attachments($art_id, &$attachment_data, $preview, $edit, $submit): void
	{
		if ($art_id && !$preview && !($edit && $submit))
		{
			$sql = 'SELECT *
				FROM ' . $this->attachments_table . '
				WHERE article_id = ' . (int) $art_id . '
				AND is_orphan = 0
				ORDER BY attach_id DESC';
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$attachment_data[] = $row;
			}
			$this->db->sql_freeresult($result);
		}

		if (count($attachment_data))
		{
			($this->config['display_order']) ? krsort($attachment_data) : ksort($attachment_data);
		}

		if (count($attachment_data))
		{
			$s_inline_attachment_options = '';
			$i = 0;
			foreach ($attachment_data as $count => $attach_row)
			{
				$hidden = '';
				$attach_row['real_filename'] = utf8_basename($attach_row['real_filename']);

				foreach ($attach_row as $key => $value)
				{
					$hidden .= '<input type="hidden" name="attachment_data[' . $count . '][' . $key . ']" value="' . $value . '" />';
				}

				$this->template->assign_block_vars('attach_row', [
						'FILENAME'     => utf8_basename($attach_row['real_filename']),
						'A_FILENAME'   => addslashes(utf8_basename($attach_row['real_filename'])),
						'FILE_COMMENT' => $attach_row['attach_comment'],
						'ATTACH_ID'    => $attach_row['attach_id'],
						'S_IS_ORPHAN'  => $attach_row['is_orphan'],
						'ASSOC_INDEX'  => $count,
						'FILESIZE'     => get_formatted_filesize($attach_row['filesize']),

						'U_VIEW_ATTACHMENT' => 'kb_file?id=' . $attach_row['attach_id'],
						'S_HIDDEN'          => $hidden,
					]
				);

				$s_inline_attachment_options .= '<option value="' . $i . '">' . utf8_basename($attach_row['real_filename']) . '</option>';
				$i++;
			}
			$this->template->assign_var('S_INLINE_ATTACHMENT_OPTIONS', $s_inline_attachment_options);
		}
	}

	/**
	 * @param $attachment_data
	 * @param $id
	 * @return void
	 */
	public function insert_attachments($attachment_data, $id): void
	{
		if (count($attachment_data))
		{
			foreach ($attachment_data as $attach_row)
			{
				$attach_sql = [
					'is_orphan'      => 0,
					'attach_comment' => $attach_row['attach_comment'],
					'article_id'     => $id,
				];
				$sql = 'UPDATE ' . $this->attachments_table . ' SET ' . $this->db->sql_build_array('UPDATE', $attach_sql) . '
					WHERE attach_id = ' . (int) $attach_row['attach_id'] . '
						AND poster_id = ' . (int) $this->user->data['user_id'];
				$this->db->sql_query($sql);
			}
		}
	}
}
