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
use phpbbmodders\knowledgebase\textformatter\article_text;

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

	/** @var article_text */
	protected article_text $article_text;

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
	 * @param article_text              $article_text
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
		plupload $plupload, notification_manager $notification_manager, functions_kb $kb, article_text $article_text,
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
		$this->article_text = $article_text;
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

		// A new article (or redirect) is filed under the category its form's
		// select names, so "Add article" can start from a page with no
		// category of its own. The choice only counts for a category that
		// exists and in which the visitor may post that kind of entry;
		// anything else is ignored and the category in the address stays.
		if ($mode !== 'edit' && $mode !== 'delete')
		{
			$chosen_cat_id = (int) $this->request->variable('to_id', 0);
			if ($chosen_cat_id && $chosen_cat_id !== $cat_id && $this->kb->get_cat_info($chosen_cat_id)
				&& $this->kb->acl_kb_get($chosen_cat_id, $mode === 'redirect' ? 'kb_m_approve' : 'kb_u_add'))
			{
				$cat_id = $chosen_cat_id;
			}
		}

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
		else if ($mode !== 'edit' && $mode !== 'delete' && !$this->kb->acl_kb_get($cat_id, 'kb_u_add'))
		{
			trigger_error('RULES_KB_ADD_CANNOT');
		}

		// Computed here (not just inside the submit branch below) since
		// the template needs to know whether to show the submit-as
		// choice on a plain GET load too, not only after a submit.
		// phpbb.com gives a submitter who holds kb_u_add_noapprove an
		// explicit choice at submit time rather than always
		// auto-publishing - defaults to the pre-existing auto-publish
		// behaviour (checked/true) so an unmodified form submission
		// behaves exactly as before this was added, and a fresh GET
		// load shows that same default. Someone without the permission
		// has no choice to make - their submission always needs
		// approval, same as today.
		$can_add_noapprove = $this->kb->acl_kb_get($cat_id, 'kb_u_add_noapprove');
		$submit_as_approved = $can_add_noapprove ? $this->request->variable('submit_as_approved', true) : false;

		// The article's actual primary category - not necessarily $cat_id,
		// which is just whichever category (primary or cross-listed) this
		// request was reached through. Everything that decides where the
		// article actually lives must key off this, not $cat_id, or
		// editing through a cross-listed link can silently reassign the
		// primary category. Defaults to $cat_id for a brand new article,
		// where that ambiguity doesn't exist yet; overwritten below once
		// an existing article is loaded.
		$article_category_id = $cat_id;

		add_form_key('kb_posting');

		// Whether the article body is Markdown (otherwise BBCode). Set from the
		// article, its pending revision or its draft edit when one is loaded,
		// and from the form once the form has been submitted.
		$article_markdown = $original_article_markdown = false;

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

			$is_own_article = $this->kb->is_article_author($art_id, (int) $article_author_id, (int) $row['author_type']);

			$article_categories = $this->kb->get_article_categories($art_id);

			// A draft is private to its author/co-authors - not even a
			// moderator with kb_m_edit/kb_m_delete can open or remove
			// someone else's draft, unlike every other status.
			$is_draft = (int) $row['status'] === functions_kb::STATUS_DRAFT;

			$edit_allowed = $is_draft
				? ($is_own_article && $this->kb->acl_kb_get_any($article_categories, 'kb_u_edit'))
				: ($this->kb->acl_kb_get_any($article_categories, 'kb_m_edit') || (
						$is_own_article &&
						$this->kb->acl_kb_get_any($article_categories, 'kb_u_edit'))
				);

			$delete_allowed = $is_draft
				? ($is_own_article && $this->kb->acl_kb_get_any($article_categories, 'kb_u_delete'))
				: ($this->kb->acl_kb_get_any($article_categories, 'kb_m_delete') || (
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
		$savedraft = $this->request->is_set_post('savedraft');
		$preview = $this->request->is_set_post('preview');
		$cancel = $this->request->is_set_post('cancel');
		$delete = $this->request->is_set_post('delete');
		$edit = $mode == 'edit';

		// Preserves ?mode=redirect on the form's own POST target, same as
		// the mode=edit branch below does for edits - without it, a
		// Preview/Submit POST back to this page loses redirect mode
		// entirely and the request re-validates as a normal article.
		$action = $this->helper->route('phpbbmodders_knowledgebase_posting', $is_redirect ? ['id' => $cat_id, 'mode' => 'redirect'] : ['id' => $cat_id]);

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
			$article_category_id = (int) $row['article_category_id'];

			// Captured before $row gets reassigned to category info below
			// (get_cat_info() reuses the same variable) - the changelog
			// diff still needs the article's original stored body/uid
			// further down, once $row no longer holds it.
			$original_article_body = (string) $row['article_body'];
			$original_bbcode_uid = (string) $row['bbcode_uid'];
			$article_markdown = $original_article_markdown = !empty($row['article_markdown']);

			$article_text = $this->decode_article_text($article_text, (string) $row['bbcode_uid'], $article_markdown);

			// Reopening the edit form for an Active article with a pending
			// revision must continue editing that revision, not silently
			// start over from the still-live (old) published content -
			// the revision *is* the author's current work, just already
			// submitted. Only applies on a fresh load (not a submit or
			// preview, which already carry the form's own current values).
			$has_revision = false;
			if ($article_status === functions_kb::STATUS_ACTIVE && !$submit && !$preview)
			{
				$revision = $this->kb->get_article_revision($art_id);
				if ($revision)
				{
					$has_revision = true;
					$article_title = (string) $revision['article_title'];
					$article_description = (string) $revision['article_description'];
					$redirect_url = (string) $revision['redirect_url'];
					$article_markdown = !empty($revision['article_markdown']);
					$article_text = $this->decode_article_text((string) $revision['article_body'], (string) $revision['bbcode_uid'], $article_markdown);
				}
			}

			// A draft-of-an-edit is entirely separate from a real pending
			// revision - private scratch space for an in-progress edit of
			// an already-submitted article, never visible to a moderator
			// and never interfering with any real pending revision that
			// already exists. Only applies on a fresh load (not a submit
			// or preview, which already carry the form's own current
			// values) and never for a STATUS_DRAFT article itself, which
			// already IS the draft. Takes precedence over the pending
			// revision loaded above, since it's more recent work - the
			// author started it after already submitting that revision.
			$has_draft_edit = false;
			if ($article_status !== functions_kb::STATUS_DRAFT && !$submit && !$preview)
			{
				$draft_edit = $this->kb->get_article_draft_edit($art_id);
				if ($draft_edit)
				{
					$has_draft_edit = true;
					$article_title = (string) $draft_edit['article_title'];
					$article_description = (string) $draft_edit['article_description'];
					$redirect_url = (string) $draft_edit['redirect_url'];
					$article_markdown = !empty($draft_edit['article_markdown']);
					$article_text = $this->decode_article_text((string) $draft_edit['article_body'], (string) $draft_edit['bbcode_uid'], $article_markdown);
				}
			}

			if (!$edit_allowed)
			{
				trigger_error('RULES_KB_MOD_EDIT_CANNOT');
			}
		}

		// Optimistic-locking fingerprint for the pending-revision race: two
		// editors both starting from the same (or no) pending revision and
		// both saving must not let the second save silently clobber the
		// first. A fresh GET load takes the fingerprint from the revision
		// that actually exists right now (0 if there is none); a preview or
		// submit instead carries forward whatever fingerprint the form
		// itself started with, so the conflict check below still compares
		// against the *original* basis, not whatever's current by then.
		$revision_fingerprint = $this->request->variable('revision_fingerprint', 0);
		if ($mode === 'edit' && !$submit && !$preview && ($article_status ?? 0) === functions_kb::STATUS_ACTIVE)
		{
			$current_revision = $this->kb->get_article_revision($art_id);
			$revision_fingerprint = $current_revision ? (int) $current_revision['submitted_date'] : 0;
		}

		// Co-author fields: on a fresh GET load, pre-fill from the
		// article's actual current co-authors so a moderator resubmitting
		// the form without touching these fields round-trips the same
		// set back (set_co_authors() below is a full replace, same as
		// set_cross_listed_categories()) rather than silently wiping them.
		// A submit or preview instead shows back whatever was just typed.
		$co_author_usernames_display = $this->request->variable('co_author_usernames', '', true);
		$co_author_groups_prefill = array_map('intval', $this->request->variable('co_author_groups', [0]));
		if ($mode === 'edit' && !$submit && !$preview)
		{
			$existing_co_authors = $this->kb->get_co_authors($art_id);
			$co_author_groups_prefill = $existing_co_authors['groups'];
			if (!empty($existing_co_authors['users']))
			{
				$co_author_usernames_display = implode(', ', $this->kb->get_usernames($existing_co_authors['users']));
			}
		}

		$attachment_data = [];
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

		// An unticked checkbox is not sent at all, so the form carries a marker
		// to tell "unticked" apart from "this request is not a form post".
		if ($this->request->is_set_post('article_markdown_form'))
		{
			$article_markdown = $this->request->variable('article_markdown', false);
		}

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
		// just submitted if this is a preview or (possibly failed) submit,
		// otherwise the article's existing cross-listings on a fresh edit
		// load, or nothing for a brand new article.
		$cross_categories_display = ($submit || $preview) ? $cross_categories : (($art_id) ? $this->kb->get_cross_listed_categories($art_id) : []);

		// Custom fields: read every field's submitted values in one go, then
		// pre-render each enabled field's posting widget for the template.
		$enabled_fields = $this->kb->get_enabled_fields();
		$all_field_values = $this->request->variable('field_values', [0 => [0]]);
		$field_widgets = '';
		$missing_required_fields = [];
		foreach ($enabled_fields as $field)
		{
			$field_id = (int) $field['field_id'];
			$field_values = $this->kb->get_field_values($field_id, true);
			$field_type = $this->field_factory->get($field['field_type']);
			$submitted_ids = $field_type->normalize_submitted_ids(array_map('intval', $all_field_values[$field_id] ?? []));
			if (functions_kb::is_required_field_missing($field, $field_values, $submitted_ids))
			{
				$missing_required_fields[] = $field['field_name'];
			}
			$selected_ids = ($submit || $preview) ? $submitted_ids : (($art_id) ? $this->kb->get_article_field_value_ids($art_id, $field_id) : []);

			$field_widgets .= $field_type->render_posting_field($field, $field_values, $selected_ids);
		}

		include($this->phpbb_root_path . 'includes/functions_posting.' . $this->php_ext);
		generate_smilies('inline', 0);
		include($this->phpbb_root_path . 'includes/functions_display.' . $this->php_ext);
		display_custom_bbcodes();

		if ($submit || $savedraft)
		{
			if (!check_form_key('kb_posting'))
			{
				trigger_error('FORM_INVALID');
			}

			// A draft-in-progress doesn't need finished content yet - only
			// a title, so it can be found again later. A real submission
			// (including promoting an existing draft) still needs it.
			$has_content = $is_redirect ? $this->kb->is_valid_redirect_url($redirect_url) : (bool) $article_text;
			$description_error = $this->kb->get_description_error($article_description);
			// A draft is unfinished by definition, so only a real submission
			// needs every required field filled in.
			$required_fields_missing = !$savedraft && !empty($missing_required_fields);
			if ($article_title && $description_error === '' && !$required_fields_missing && ($savedraft || $has_content))
			{
				if (!$is_redirect)
				{
					// to enable bbcode, urls and smilies parsing, be enabled it when using
					// generate_text_for_storage function
					$this->article_text->store($article_text, $article_markdown, $bbcode_uid, $bbcode_bitfield, $options);
				}
				else
				{
					$bbcode_uid = $bbcode_bitfield = '';
				}

				// Post-as-team, and co-authors below: both only offered to
				// users who can already moderate this category's approvals -
				// "only the team" can credit anyone besides themselves,
				// matching the reference text's own stated behaviour.
				$is_moderator_for_cat = $this->auth->acl_get('a_manage_kb') || $this->kb->acl_kb_get($cat_id, 'kb_m_approve');

				$post_as_group = 0;
				if ($is_moderator_for_cat)
				{
					$post_as_group = $this->request->variable('post_as_group', 0);
				}
				$posting_as_group = $post_as_group && in_array($post_as_group, $this->kb->get_kb_team_groups(), true);
				$author_type = $posting_as_group ? functions_kb::AUTHOR_TYPE_GROUP : functions_kb::AUTHOR_TYPE_USER;
				$author_id = $posting_as_group ? $post_as_group : (int) $this->user->data['user_id'];
				$author_name = $posting_as_group ? ($this->kb->get_kb_team_group_names()[$post_as_group] ?? '') : $this->user->data['username'];

				// Co-authors: a revision cannot change them (the reference text), so
				// this only ever runs for the add path and the direct-edit
				// (not-yet-published) path below - never through
				// save_article_revision(). Unresolved usernames become a
				// validation error rather than being silently dropped.
				$co_author_user_ids = $co_author_group_ids = [];
				if ($is_moderator_for_cat)
				{
					$co_author_usernames = $this->request->variable('co_author_usernames', '', true);
					$co_author_group_ids = array_intersect(
						array_map('intval', $this->request->variable('co_author_groups', [0])),
						$this->kb->get_kb_team_groups()
					);

					if ($co_author_usernames !== '')
					{
						$requested_names = array_filter(array_map('trim', explode(',', $co_author_usernames)), 'strlen');
						if (!empty($requested_names))
						{
							$resolved = $this->kb->resolve_usernames($requested_names);
							$co_author_user_ids = array_values($resolved['user_ids']);

							// A hard stop here, not $error[] - this whole
							// branch ends in a trigger_error() success
							// redirect further down that never looks at
							// $error, so that's the only way an unresolved
							// name would actually be surfaced rather than
							// silently dropped.
							if (!empty($resolved['unresolved']))
							{
								trigger_error(sprintf($this->language->lang('KB_UNKNOWN_USERNAME'), implode(', ', $resolved['unresolved'])), E_USER_WARNING);
							}
						}
					}
				}

				$sql_data = [
					'article_category_id' => $cat_id,
					'article_title'       => $article_title,
					'article_description' => $article_description,
					'article_date'        => time(),
					'author_id'           => $author_id,
					'author_type'         => $author_type,
					'bbcode_uid'          => substr(md5(rand()), 0, 8),
					'article_body'        => $article_text,
					'article_markdown'    => (int) ($article_markdown && !$is_redirect),
					'is_redirect'         => $is_redirect ? 1 : 0,
					'redirect_url'        => $is_redirect ? $redirect_url : '',
					'views'               => 0,
					'author'              => $author_name,
					'approved'            => ($savedraft || !$submit_as_approved) ? 0 : 1,
					'status'              => $savedraft ? functions_kb::STATUS_DRAFT : ($submit_as_approved ? functions_kb::STATUS_ACTIVE : functions_kb::STATUS_NEW),
				];

				$root = $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $cat_id]);

				if ($mode == 'edit')
				{
					$sql_data['author_id'] = $article_author_id;
					$sql_data['author'] = $article_author;
					$sql_data['author_type'] = $article_author_type;
					$sql_data['views'] = $views;
					$sql_data['article_date'] = $article_date;
					// Editing must never change an article's workflow status -
					// New/Reviewed stays exactly where it was regardless of the
					// editor's own kb_u_add_noapprove permission, and a moderator
					// deactivation isn't silently undone by an edit either. Status
					// changes only ever happen through an explicit moderation
					// action (approve.php), for an Active article the revision
					// queue below, or - the one deliberate exception - promoting
					// a draft into a real submission via $submit below, where
					// $sql_data['approved']/['status'] were computed above
					// specifically to make that transition happen.
					if ($article_status !== functions_kb::STATUS_DRAFT)
					{
						unset($sql_data['approved'], $sql_data['status']);
					}
					// The article's real primary category, not wherever this
					// edit happened to be reached from (see $article_category_id
					// above) - fixes a bug where editing via a cross-listed
					// category link would silently reassign the primary category.
					$sql_data['article_category_id'] = $article_category_id;
					$redirect = $this->helper->route('phpbbmodders_knowledgebase_article', ['k' => $art_id]);

					// Only move into a category that actually exists and that the
					// editor could post into directly - same guard the cross-listing
					// select already applies. An invalid/unauthorised destination is
					// silently ignored rather than trusted from the request.
					if ($to_id !== $article_category_id && (!$this->kb->get_cat_info($to_id) || !$this->kb->acl_kb_get($to_id, 'kb_u_add')))
					{
						$to_id = $article_category_id;
					}

					$moving_category = ($article_category_id !== $to_id);
					if ($moving_category)
					{
						$sql_data['article_category_id'] = $to_id;
						$sql_data['display_order'] = 0;
					}

					if ($savedraft && $article_status !== functions_kb::STATUS_DRAFT)
					{
						// A private draft of a further edit to an already-
						// submitted article - kept entirely separate from any
						// real pending revision (which may already exist for
						// an Active article) until actually submitted, and
						// scoped to the same core content fields the existing
						// revision system itself covers. Category moves and
						// cross-listing/custom-field/attachment changes made
						// while drafting are deliberately not stashed - only
						// a real submit applies those.
						$this->kb->save_article_draft_edit($art_id, [
							'article_title'       => $article_title,
							'article_description' => $article_description,
							'article_body'        => $article_text,
							'bbcode_uid'          => $bbcode_uid,
							'bbcode_bitfield'     => $bbcode_bitfield,
							'article_markdown'    => (int) ($article_markdown && !$is_redirect),
							'redirect_url'        => $redirect_url,
						], (int) $this->user->data['user_id']);

						$msg = $this->language->lang('KB_DRAFT_SAVED');
						$redirect = $this->helper->route('phpbbmodders_knowledgebase_my_articles');
						$root = $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => $cat_id]);

						$msg .= '<br><br>' . sprintf($this->language->lang('RETURN_CAT'), '<a href="' . $root . '">', '</a>');
						meta_refresh(2, $redirect);
						trigger_error($msg);
					}
					else if ($article_status === functions_kb::STATUS_ACTIVE)
					{
						// Refuse rather than silently overwrite if the pending
						// revision changed since this edit was opened -
						// save_article_revision() itself unconditionally
						// deletes-and-replaces whatever's there.
						$current_revision = $this->kb->get_article_revision($art_id);
						$current_fingerprint = $current_revision ? (int) $current_revision['submitted_date'] : 0;
						if ($current_fingerprint !== $revision_fingerprint)
						{
							trigger_error($this->language->lang('KB_REVISION_CONFLICT'), E_USER_WARNING);
						}

						// Live article: queue the content edit as a pending revision rather
						// than touching the published content.
						$this->kb->save_article_revision($art_id, [
							'article_title'       => $article_title,
							'article_description' => $article_description,
							'article_body'        => $article_text,
							'bbcode_uid'          => $bbcode_uid,
							'bbcode_bitfield'     => $bbcode_bitfield,
							'article_markdown'    => (int) ($article_markdown && !$is_redirect),
							'redirect_url'        => $redirect_url,
						], (int) $this->user->data['user_id']);

						if ($moving_category)
						{
							$sql = 'UPDATE ' . $this->articles_table . '
								SET article_category_id = ' . $to_id . ', display_order = 0
								WHERE article_id = ' . $art_id;
							$this->db->sql_query($sql);
						}

						$changelog_message = $this->append_changelog_body_diff(
							$this->language->lang('KB_CHANGELOG_REVISION_SUBMITTED', $this->user->data['username']),
							$original_article_body, $original_bbcode_uid, $original_article_markdown,
							$article_text, $bbcode_uid, $article_markdown && !$is_redirect
						);
						$this->kb->post_changelog_entry($art_id, $article_title, $changelog_message);

						// Superseded by the real pending revision just queued.
						$this->kb->delete_article_draft_edit($art_id);

						$msg = $this->language->lang('ARTICLE_REVISION_SUBMITTED');
					}
					else if ($article_status === functions_kb::STATUS_DRAFT && $submit)
					{
						// Promoting a draft into a real submission - the same
						// outcome as adding a brand new article (search index,
						// changelog, announce/notify per the submit-as-approved
						// choice), just via UPDATE on the existing draft row
						// instead of INSERT. The author (set when the draft was
						// first saved) is deliberately left as the shared
						// override above already set it - promoting isn't a
						// chance to reassign authorship.
						$sql_data['article_date'] = time();
						$sql_data['edit_date'] = time();

						$sql = 'UPDATE ' . $this->articles_table . '
							SET ' . $this->db->sql_build_array('UPDATE', $sql_data) . '
							WHERE article_id = ' . $art_id;
						$this->db->sql_query($sql);

						// A draft was never counted in its category's
						// number_articles - promoting it is this article's
						// first time being counted, same as a brand new
						// article. Counted against its category as of before
						// any move below, so the shared moving_category
						// transfer further down (which assumes the article
						// was already counted in its old category) balances
						// out correctly if this submit also moves it.
						$this->config->increment('kb_num_articles', 1);
						$sql = 'UPDATE ' . $this->categories_table . '
							SET number_articles = number_articles + 1
							WHERE category_id = ' . $article_category_id;
						$this->db->sql_query($sql);

						if ($is_moderator_for_cat)
						{
							$this->kb->set_co_authors($art_id, $co_author_user_ids, $co_author_group_ids, (int) $sql_data['author_id'], (int) $sql_data['author_type']);
						}

						$this->kb->post_changelog_entry($art_id, $article_title, $this->language->lang('KB_CHANGELOG_SUBMITTED', $this->user->data['username']));

						if ($submit_as_approved)
						{
							if ($kb_search)
							{
								$kb_search->index('add', $art_id, $article_text, $article_title, $article_description, (int) $sql_data['author_id']);
							}

							if (!empty($this->config['kb_forum_id']) && $this->config['kb_anounce'])
							{
								$this->kb->submit_article($cat_id, $fid, $article_title, $article_description, $sql_data['author'], $category_name, $art_id);
							}

							$msg = $this->language->lang('ARTICLE_SUBMITTED');
						}
						else
						{
							$msg = $this->language->lang('ARTICLE_NEED_APPROVE');

							$notification_data = [
								'author_id'           => $sql_data['author_id'],
								'author_type'         => $sql_data['author_type'],
								'title'               => $article_title,
								'article_category_id' => $cat_id,
								'item_id'             => $art_id,
							];
							$this->notification_manager->add_notifications('phpbbmodders.knowledgebase.notification.type.need_approval', $notification_data);
						}
					}
					else
					{
						$sql_data['edit_date'] = time();

						$sql = 'UPDATE ' . $this->articles_table . '
							SET ' . $this->db->sql_build_array('UPDATE', $sql_data) . '
							WHERE article_id = ' . $art_id;
						$this->db->sql_query($sql);

						// Co-authors can only change here (a moderator's direct
						// edit of a not-yet-published article) or on add below -
						// never through the revision path above, matching
						// the reference text's "a revision cannot change the authors".
						if ($is_moderator_for_cat)
						{
							$this->kb->set_co_authors($art_id, $co_author_user_ids, $co_author_group_ids, (int) $article_author_id, (int) $article_author_type);
						}

						// A draft being saved as a draft again has nothing to
						// index or log yet - it was never submitted, so there's
						// no search entry or changelog thread to update.
						if (!$savedraft)
						{
							if ($kb_search)
							{
								$kb_search->index('edit', $art_id, $article_text, $article_title, $article_description, (int) $article_author_id);
							}

							$changelog_message = $this->append_changelog_body_diff(
								$this->language->lang('KB_CHANGELOG_EDITED', $this->user->data['username']),
								$original_article_body, $original_bbcode_uid, $original_article_markdown,
								$article_text, $bbcode_uid, $article_markdown && !$is_redirect
							);
							$this->kb->post_changelog_entry($art_id, $article_title, $changelog_message);

							// Superseded by the real edit just applied.
							$this->kb->delete_article_draft_edit($art_id);
						}

						if ($savedraft)
						{
							$msg = $this->language->lang('KB_DRAFT_SAVED');
							$redirect = $this->helper->route('phpbbmodders_knowledgebase_my_articles');
						}
						else
						{
							$msg = $this->language->lang('ARTICLE_EDITED');
						}
					}

					if ($moving_category)
					{
						// Unsubmitted drafts were never included in the category counters.
						if ($article_status !== functions_kb::STATUS_DRAFT || $submit)
						{
							$sql = 'UPDATE ' . $this->categories_table . '
								SET number_articles = number_articles - 1
									WHERE category_id = ' . $article_category_id;
								$this->db->sql_query($sql);

							$sql = 'UPDATE ' . $this->categories_table . '
								SET number_articles = number_articles + 1
									WHERE category_id = ' . $to_id;
								$this->db->sql_query($sql);
						}

						$sql = 'UPDATE ' . $this->articles_table . ' SET display_order = display_order + 1
							WHERE article_category_id = ' . $to_id;
						$this->db->sql_query($sql);

						$sql = 'UPDATE ' . $this->articles_table . ' SET display_order = display_order - 1
							WHERE article_category_id = ' . $article_category_id . '
							AND display_order > ' . $order;
						$this->db->sql_query($sql);
					}

					$this->kb->set_cross_listed_categories($art_id, $cross_categories);
					$this->save_article_field_values($art_id, $enabled_fields, $all_field_values);
					$this->insert_attachments($attachment_data, $art_id);

					// Saving a draft (whether still a draft, or just promoted)
					// redirects to Manage Your Articles, not this same "back to
					// article" link, and isn't a moderation-relevant admin-log
					// event when it's still just a draft.
					if (!$savedraft)
					{
						$msg .= '<br><br>' . sprintf($this->language->lang('RETURN_ARTICLE'), '<a href="' . $redirect . '">', '</a>');
					}
					$msg .= '<br><br>' . sprintf($this->language->lang('RETURN_CAT'), '<a href="' . $root . '">', '</a>');
					if (!$savedraft)
					{
						$this->log->add('admin', $this->user->data['user_id'], $this->user->data['user_ip'], 'LOG_LIBRARY_EDIT_ARTICLE', time(), [$article_title, $category_name]);
					}
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
					if ($is_moderator_for_cat)
					{
						$this->kb->set_co_authors((int) $new, $co_author_user_ids, $co_author_group_ids, $author_id, $author_type);
					}
					$this->save_article_field_values($new, $enabled_fields, $all_field_values);
					$this->insert_attachments($attachment_data, $new);

					// A draft doesn't count yet - it isn't visible anywhere
					// that count is shown, and gets counted for real once
					// promote-on-submit runs, below.
					if (!$savedraft)
					{
						$articles_count++;
						$this->config->increment('kb_num_articles', 1);
						$sql = 'UPDATE ' . $this->categories_table . '
							SET number_articles = ' . $articles_count . '
							WHERE category_id = ' . $cat_id;
						$this->db->sql_query($sql);
					}

					if ($savedraft)
					{
						// Nothing to index, announce, or log to the changelog -
						// a draft was never submitted, so there's nothing yet
						// for a moderator or search visitor to see.
						$msg = $this->language->lang('KB_DRAFT_SAVED');
						$redirect = $this->helper->route('phpbbmodders_knowledgebase_my_articles');
					}
					else
					{
						$this->kb->post_changelog_entry($new, $article_title, $this->language->lang('KB_CHANGELOG_SUBMITTED', $this->user->data['username']));

						if ($submit_as_approved)
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
					}

					$this->cache->destroy('sql', $this->categories_table);
					$this->cache->destroy('sql', $this->articles_table);

					$msg .= '<br><br>' . sprintf($this->language->lang('RETURN_CAT'), '<a href="' . $root . '">', '</a>');
					// A draft is private and freely re-editable - not yet a
					// moderation-relevant event worth recording in the admin log.
					if (!$savedraft)
					{
						$this->log->add('admin', $this->user->data['user_id'], $this->user->data['user_ip'], 'LOG_LIBRARY_ADD_ARTICLE', time(), [$article_title, $category_name]);
					}
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

				if ($description_error !== '')
				{
					$error[] = $description_error;
				}

				if ($required_fields_missing)
				{
					foreach ($missing_required_fields as $field_name)
					{
						$error[] = $this->language->lang('KB_FIELD_REQUIRED_ERROR', $field_name);
					}
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

			$description_error = $this->kb->get_description_error($article_description);
			if ($description_error !== '')
			{
				$error[] = $description_error;
			}

			foreach ($missing_required_fields as $field_name)
			{
				$error[] = $this->language->lang('KB_FIELD_REQUIRED_ERROR', $field_name);
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
				$this->article_text->store($preview_text, $article_markdown, $uid, $bitfield, $options);

				$preview_text = $this->article_text->display($preview_text, $uid, $article_markdown);

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
				'DESCR_MAX_LENGTH'  => $this->kb->get_description_max_length(),
				'TOPIC_TITLE'       => $article_title,
				'SUBJECT'           => $article_title,
				'MESSAGE'           => $article_text,
				'S_ARTICLE_MARKDOWN' => $article_markdown,
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
				'REVISION_FINGERPRINT' => $revision_fingerprint,

				// Shown only on a new-article submission (never edit -
				// editing must never change an article's workflow status)
				// and only to a submitter who actually holds
				// kb_u_add_noapprove - anyone else has no choice to make.
				'S_CAN_CHOOSE_SUBMIT_AS' => $mode != 'edit' && $can_add_noapprove,
				'S_SUBMIT_AS_APPROVED'   => $submit_as_approved,

				// Always available: a brand new article, continuing to edit
				// one that's already saved as a draft, or a private draft
				// of a further edit to an already-submitted article (kept
				// entirely separate from any real pending revision until
				// it's actually submitted).
				'S_CAN_SAVE_DRAFT' => true,
				'S_HAS_DRAFT_EDIT' => $has_draft_edit ?? false,
				// Only when the revision is actually what's loaded into the
				// form - a draft-of-an-edit (above) takes precedence, so if
				// both exist this stays false and the draft-edit notice
				// shows instead.
				'S_EDITING_REVISION' => ($has_revision ?? false) && !($has_draft_edit ?? false),

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

				'CATS_BOX'       => '<option value="0" disabled="disabled">' . $this->language->lang('CATEGORIES_LIST') . '</option>' . $this->kb->make_category_select($article_category_id, [], false, $is_redirect && $mode !== 'edit' ? 'kb_m_approve' : 'kb_u_add'),
				'CROSS_CATS_BOX' => $this->kb->make_category_select($cross_categories_display, [$article_category_id], false),

				'S_CAN_POST_AS_TEAM' => $this->auth->acl_get('a_manage_kb') || $this->kb->acl_kb_get($cat_id, 'kb_m_approve'),
				'TEAM_OPTIONS'       => $this->get_team_select_options(),

				'S_CAN_SET_CO_AUTHORS'  => $this->auth->acl_get('a_manage_kb') || $this->kb->acl_kb_get($cat_id, 'kb_m_approve'),
				'CO_AUTHOR_USERNAMES'   => $co_author_usernames_display,

				'FIELD_WIDGETS' => $field_widgets,

				'U_KB'           => $this->helper->route('phpbbmodders_knowledgebase_index'),
				'S_POST_ACTION'  => $action,
				'S_POST_ARTICLE' => true,
				'S_KNOWLEDGEBASE' => true,
			]
		);

		foreach ($this->kb->get_kb_team_group_names() as $group_id => $group_name)
		{
			$this->template->assign_block_vars('co_author_group', [
				'GROUP_ID'   => $group_id,
				'GROUP_NAME' => $group_name,
				'S_CHECKED'  => in_array($group_id, $co_author_groups_prefill, true),
			]);
		}

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
	 * Appends an original-vs-new quoted diff of the article body to a
	 * changelog message, when the body actually changed - the reference text's "a
	 * changelog against the published article". Skipped when the edit
	 * didn't touch the body (a title/description-only edit stays a
	 * plain notice, not a no-op diff of two identical quotes).
	 *
	 * Both bodies are decoded first (decode_article_text()) - the stored
	 * form of each carries its own bbcode_uid-tagged markers, which only
	 * mean something paired with that specific uid. Stripped down to the
	 * plain source the author typed, they're safe to embed in this new
	 * post, which gets its own fresh uid when it's saved. The changelog
	 * topic is always a BBCode post, so a Markdown article's source is
	 * quoted as plain text.
	 *
	 * @param string $base_message
	 * @param string $old_body
	 * @param string $old_bbcode_uid
	 * @param bool   $old_markdown
	 * @param string $new_body
	 * @param string $new_bbcode_uid
	 * @param bool   $new_markdown
	 * @return string
	 */
	private function append_changelog_body_diff(string $base_message, string $old_body, string $old_bbcode_uid, bool $old_markdown, string $new_body, string $new_bbcode_uid, bool $new_markdown): string
	{
		$old_body = $this->decode_article_text($old_body, $old_bbcode_uid, $old_markdown);
		$new_body = $this->decode_article_text($new_body, $new_bbcode_uid, $new_markdown);

		if ($old_body === $new_body)
		{
			return $base_message;
		}

		return $base_message . "\n\n"
			. '[quote="' . $this->language->lang('KB_CHANGELOG_ORIGINAL_TEXT') . '"]' . $old_body . '[/quote]' . "\n"
			. '[quote="' . $this->language->lang('KB_CHANGELOG_NEW_TEXT') . '"]' . $new_body . '[/quote]';
	}

	/**
	 * Stored article text back into what the author typed, ready for the
	 * edit form's textarea.
	 *
	 * @param string $stored
	 * @param string $bbcode_uid
	 * @param bool   $markdown   Whether the article is a Markdown article
	 * @return string
	 */
	private function decode_article_text(string $stored, string $bbcode_uid, bool $markdown): string
	{
		return $markdown ? $this->article_text->for_edit($stored) : (string) $this->decode_message($stored, $bbcode_uid);
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
			$field_type = $this->field_factory->get($field['field_type']);
			$this->kb->set_article_field_values($article_id, $field_id, $field_type->normalize_submitted_ids(array_map('intval', $all_field_values[$field_id] ?? [])));
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

		// These two submit independently of the main article submit
		// button, so they need their own form-token check rather than
		// relying on the one guarding $submit further down.
		if (($delete_file || $add_file) && !check_form_key('kb_posting'))
		{
			trigger_error('FORM_INVALID');
		}

		$this->plupload->set_upload_directories($this->upload_dir, $this->upload_dir . '/plupload');

		$attachment_data = $this->request->variable('attachment_data', [['' => '']], true, request_interface::POST);

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
							'filesize'          => $upload_file->get('filesize'),
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
							'filesize'          => $upload_file->get('filesize'),
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
		$upload_path = $this->kb->get_upload_path();
		$this->plupload->set_upload_directories($upload_path, $upload_path . '/plupload');
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
				$filename = htmlspecialchars($attach_row['real_filename']);
				$comment = htmlspecialchars($attach_row['attach_comment'] ?? '');

				foreach ($attach_row as $key => $value)
				{
					$hidden .= '<input type="hidden" name="attachment_data[' . $count . '][' . $key . ']" value="' . htmlspecialchars((string) $value) . '" />';
				}

				$this->template->assign_block_vars('attach_row', [
						'FILENAME'     => $filename,
						'A_FILENAME'   => addslashes($filename),
						'FILE_COMMENT' => $comment,
						'ATTACH_ID'    => $attach_row['attach_id'],
						'S_IS_ORPHAN'  => $attach_row['is_orphan'],
						'ASSOC_INDEX'  => $count,
						'FILESIZE'     => get_formatted_filesize($attach_row['filesize']),

						'U_VIEW_ATTACHMENT' => 'kb_file?id=' . $attach_row['attach_id'],
						'S_HIDDEN'          => $hidden,
					]
				);

				$s_inline_attachment_options .= '<option value="' . $i . '">' . $filename . '</option>';
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
				// Only claim an attachment that's still an unclaimed upload from
				// this user, or already belongs to this same article (a resave
				// while editing) - otherwise a user could resubmit an attach_id
				// from one of their own older articles and move it here.
				$sql = 'UPDATE ' . $this->attachments_table . ' SET ' . $this->db->sql_build_array('UPDATE', $attach_sql) . '
					WHERE attach_id = ' . (int) $attach_row['attach_id'] . '
						AND poster_id = ' . (int) $this->user->data['user_id'] . '
						AND (is_orphan = 1 OR article_id = ' . (int) $id . ')';
				$this->db->sql_query($sql);
			}
		}
	}
}
