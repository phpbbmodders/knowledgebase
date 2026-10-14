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
use phpbb\db\driver\driver_interface;
use phpbb\extension\manager;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\user;
use phpbbmodders\knowledgebase\inc\functions_kb;
use phpbbmodders\knowledgebase\inc\upload_directory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class kb_file
{
	/** @var driver_interface */
	protected driver_interface $db;

	/** @var language */
	protected language $language;

	/** @var request_interface */
	protected request_interface $request;

	/** @var manager */
	protected manager $ext_manager;

	/** @var auth */
	protected auth $auth;

	/** @var user */
	protected user $user;

	/** @var functions_kb */
	protected functions_kb $kb;

	/** @var string */
	protected string $attachments_table;

	/** @var string */
	protected string $articles_table;

	/** @var string */
	protected string $phpbb_root_path;

	/**
	 * Constructor
	 *
	 * @param driver_interface  $db
	 * @param language          $language
	 * @param request_interface $request
	 * @param manager           $ext_manager
	 * @param auth              $auth
	 * @param user              $user
	 * @param functions_kb      $kb
	 * @param string            $attachments_table
	 * @param string            $articles_table
	 * @param string            $phpbb_root_path
	 */
	public function __construct(
		driver_interface $db, language $language, request_interface $request, manager $ext_manager,
		auth $auth, user $user, functions_kb $kb, string $attachments_table, string $articles_table,
		string $phpbb_root_path
	)
	{
		$this->phpbb_root_path = $phpbb_root_path;
		$this->db = $db;
		$this->language = $language;
		$this->request = $request;
		$this->ext_manager = $ext_manager;
		$this->auth = $auth;
		$this->user = $user;
		$this->kb = $kb;
		$this->attachments_table = $attachments_table;
		$this->articles_table = $articles_table;
	}

	/**
	 * @return Response
	 */
	public function main(): Response
	{
		$attach_id = (int) $this->request->variable('id', 0);
		$thumbnail = $this->request->variable('t', false);

		$this->language->add_lang('viewtopic');

		if (!$attach_id)
		{
			send_status_line(404, 'Not Found');
			trigger_error('NO_ATTACHMENT_SELECTED');
		}

		if (!$this->auth->acl_get('u_kb_view') && !$this->auth->acl_get('a_manage_kb'))
		{
			send_status_line(403, 'Forbidden');
			trigger_error('NOT_AUTHORISED');
		}

		$sql = 'SELECT attach_id, article_id, poster_id, is_orphan, physical_filename, real_filename, extension, mimetype, filesize, filetime
			FROM ' . $this->attachments_table . '
			WHERE attach_id = ' . $attach_id;
		$result = $this->db->sql_query($sql);
		$attachment = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$attachment)
		{
			send_status_line(404, 'Not Found');
			trigger_error('ERROR_NO_ATTACHMENT');
		}

		if ($attachment['article_id'])
		{
			// Attached to a saved article: same view/approval gate the article itself is behind.
			$sql = 'SELECT article_category_id, approved, status, author_id, author_type, assigned_team_id
				FROM ' . $this->articles_table . '
				WHERE article_id = ' . (int) $attachment['article_id'];
			$result = $this->db->sql_query($sql);
			$article = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if (!$article)
			{
				send_status_line(403, 'Forbidden');
				trigger_error('NOT_AUTHORISED');
			}

			$article_id = (int) $attachment['article_id'];
			$is_own_article = $this->kb->is_article_author($article_id, (int) $article['author_id'], (int) $article['author_type']);
			$status = (int) $article['status'];
			$effective_team_id = $this->kb->get_effective_team_id((int) $article['assigned_team_id'], (int) $article['article_category_id']);
			$can_review = $status === functions_kb::STATUS_NEW && $effective_team_id && $this->kb->is_team_member([$effective_team_id]);
			$can_moderate = $this->auth->acl_get('a_manage_kb') || $this->kb->acl_kb_get_any($this->kb->get_article_categories($article_id), 'kb_m_approve');

			// Drafts remain private even to moderators, matching the article page.
			$can_view = $status === functions_kb::STATUS_DRAFT
				? $is_own_article
				: ($article['approved'] || $is_own_article || $can_review || $can_moderate);
			if (!$can_view)
			{
				send_status_line(403, 'Forbidden');
				trigger_error('NOT_AUTHORISED');
			}
		}
		else if ((int) $attachment['poster_id'] !== (int) $this->user->data['user_id'])
		{
			// Orphaned attachment (uploaded but not yet attached to a saved article): only its own uploader may preview it.
			send_status_line(403, 'Forbidden');
			trigger_error('NOT_AUTHORISED');
		}

		$attachment['physical_filename'] = utf8_basename($attachment['physical_filename']);

		if ($thumbnail)
		{
			$attachment['physical_filename'] = 'thumb_' . $attachment['physical_filename'];
		}

		$response = new Response();
		// Content-type header
		$response->headers->set('Content-Type', $attachment['mimetype']);
		// Display file types in browser and force download for others
		if (str_contains($attachment['mimetype'], 'image'))
		{
			$disposition = $response->headers->makeDisposition(
				ResponseHeaderBag::DISPOSITION_INLINE,
				$attachment['real_filename'],
				$this->filenameFallback($attachment['real_filename'])
			);
		}
		else
		{
			$disposition = $response->headers->makeDisposition(
				ResponseHeaderBag::DISPOSITION_ATTACHMENT,
				$attachment['real_filename'],
				$this->filenameFallback($attachment['real_filename'])
			);
		}
		$response->headers->set('Content-Disposition', $disposition);

		// Set expires header for browser cache
		$time = new \Datetime();
		$response->setExpires($time->modify('+1 month'));

		$upload_file = upload_directory::path($this->phpbb_root_path) . $attachment['physical_filename'];
		$response->setContent(file_get_contents($upload_file));

		return $response;
	}

	/**
	 * Remove non valid characters
	 * https://github.com/symfony/http-foundation/commit/c7df9082ee7205548a97031683bc6550b5dc9551
	 */
	/**
	 * @param $filename
	 * @return string
	 */
	protected function filenameFallback($filename): string
	{
		$filename = preg_replace(['/[^\x20-\x7e]/', '/%/', '/\//', '/\\\/'], '', $filename);
		return $filename ?: 'File';
	}
}
