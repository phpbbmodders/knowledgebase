<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
/**
 * Permission-checked primary-author transfer form for submitted KB articles.
 */
namespace phpbbmodders\knowledgebase\controller;

use phpbbmodders\knowledgebase\inc\functions_kb;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class change_author
{
	public function __construct(
		protected \phpbb\db\driver\driver_interface $db,
		protected \phpbb\controller\helper $helper,
		protected \phpbb\language\language $language,
		protected \phpbb\auth\auth $auth,
		protected \phpbb\request\request_interface $request,
		protected \phpbb\template\template $template,
		protected \phpbb\user $user,
		protected functions_kb $kb,
		protected \phpbbmodders\knowledgebase\inc\author_change $author_change,
		protected string $articles_table
	)
	{
	}

	/** Render the form, or validate a POST and return to the article. */
	public function main(): Response
	{
		if ((int) $this->user->data['user_id'] === ANONYMOUS ||
			(!$this->auth->acl_get('u_kb_view') && !$this->auth->acl_get('a_manage_kb')))
		{
			return new Response($this->language->lang('NOT_AUTHORISED'), 403);
		}
		$id = $this->request->variable('k', 0);
		$result = $this->db->sql_query('SELECT * FROM ' . $this->articles_table . ' WHERE article_id = ' . (int) $id);
		$article = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (!$article || (int) $article['status'] === functions_kb::STATUS_DRAFT)
		{
			return new Response($this->language->lang('ARTICLE_NO_EXISTS'), 404);
		}
		if (!$this->kb->acl_kb_get_any($this->kb->get_article_categories($id), 'kb_m_change_author'))
		{
			return new Response($this->language->lang('NOT_AUTHORISED'), 403);
		}
		if ($this->kb->article_has_draft_edit($id))
		{
			$this->template->assign_vars([
				'NOTICE_MESSAGE' => $this->language->lang('KB_CHANGE_AUTHOR_PRIVATE_DRAFT'),
				'S_KNOWLEDGEBASE' => true,
			]);
			$this->kb->assign_nav_tabs_vars('', 0, $this->language->lang('KB_TAB_NOTICE'));
			return $this->helper->render('@phpbbmodders_knowledgebase/kb_notice_body.html', $this->language->lang('KB_TAB_NOTICE'), 409);
		}

		$errors = [];
		$type = $this->request->variable('author_type', functions_kb::AUTHOR_TYPE_USER);
		$username = $this->request->variable('username', '', true);
		$group_id = $this->request->variable('group_id', 0);
		$teams = $this->kb->get_kb_team_group_names();
		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key('kb_change_author'))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}
			if ($this->request->variable('old_author_id', -1) !== (int) $article['author_id'] ||
				$this->request->variable('old_author_type', -1) !== (int) $article['author_type'])
			{
				$errors[] = $this->language->lang('KB_CHANGE_AUTHOR_CONFLICT');
			}
			$target = $this->resolve_author($type, $username, $group_id, $teams);
			if (!$target)
			{
				$errors[] = $this->language->lang('KB_CHANGE_AUTHOR_INVALID');
			}
			if (!$errors)
			{
				try
				{
					$this->author_change->transfer($article, $target['id'], $type, $target['name']);
					return new RedirectResponse($this->helper->route('phpbbmodders_knowledgebase_article', ['k' => $id]));
				}
				catch (\RuntimeException $error)
				{
					if (!in_array($error->getMessage(), ['KB_CHANGE_AUTHOR_PRIVATE_DRAFT', 'KB_CHANGE_AUTHOR_CONFLICT', 'KB_CHANGE_AUTHOR_LOG_FAILED'], true))
					{
						throw $error;
					}
					$errors[] = $this->language->lang($error->getMessage());
				}
			}
		}
		add_form_key('kb_change_author');
		foreach ($teams as $team_id => $name)
		{
			$this->template->assign_block_vars('author_team', ['ID' => $team_id, 'NAME' => $name, 'S_SELECTED' => $team_id === $group_id]);
		}
		$this->kb->assign_nav_tabs_vars('', 0, $this->language->lang('KB_CHANGE_AUTHOR'));
		$this->template->assign_vars([
			'S_KNOWLEDGEBASE' => true,
			'ARTICLE_TITLE' => $article['article_title'],
			'ARTICLE_AUTHOR' => $this->kb->get_author_display($article),
			'OLD_AUTHOR_ID' => $article['author_id'], 'OLD_AUTHOR_TYPE' => $article['author_type'],
			'AUTHOR_TYPE' => $type, 'AUTHOR_USERNAME' => $username,
			'ERROR' => implode('<br>', $errors),
			'U_CHANGE_AUTHOR' => $this->helper->route('phpbbmodders_knowledgebase_change_author', ['k' => $id]),
			'U_ARTICLE' => $this->helper->route('phpbbmodders_knowledgebase_article', ['k' => $id]),
		]);
		return $this->helper->render('@phpbbmodders_knowledgebase/kb_change_author_body.html', $this->language->lang('KB_CHANGE_AUTHOR'));
	}

	/** Resolve only active human accounts or an explicitly configured KB team. */
	protected function resolve_author(int $type, string $username, int $group_id, array $teams): ?array
	{
		if ($type === functions_kb::AUTHOR_TYPE_GROUP)
		{
			return isset($teams[$group_id]) ? ['id' => $group_id, 'name' => $teams[$group_id]] : null;
		}
		if ($type !== functions_kb::AUTHOR_TYPE_USER || trim($username) === '')
		{
			return null;
		}
		$result = $this->db->sql_query('SELECT user_id, username FROM ' . USERS_TABLE . "
			WHERE username_clean = '" . $this->db->sql_escape(utf8_clean_string($username)) . "'
			AND user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ')
			AND user_id <> ' . ANONYMOUS);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $row ? ['id' => (int) $row['user_id'], 'name' => $row['username']] : null;
	}
}
