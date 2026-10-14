<?php

/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
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
use phpbbmodders\knowledgebase\inc\feed;
use phpbbmodders\knowledgebase\inc\functions_kb;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Recent changes: the articles readers can open, newest first, as a page and as
 * an RSS or Atom feed.
 *
 * Only live articles appear: approved, active, not a redirect. Drafts, articles
 * waiting for review and switched-off articles are not public, and the page and
 * the feed are.
 */
class recent_changes
{
	protected driver_interface $db;
	protected config $config;
	protected helper $helper;
	protected language $language;
	protected auth $auth;
	protected request_interface $request;
	protected template $template;
	protected user $user;
	protected pagination $pagination;
	protected functions_kb $kb;
	protected string $articles_table;
	protected string $categories_table;

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
	 * The feed formats the ACP offers.
	 *
	 * @return string The feed class's RSS, ATOM or BOTH
	 */
	protected function setting(): string
	{
		return isset($this->config['kb_feed_format']) ? (string) $this->config['kb_feed_format'] : feed::BOTH;
	}

	/**
	 * SQL condition for the articles anyone may see.
	 *
	 * @return string
	 */
	protected function visible_where(): string
	{
		return 'a.approved = 1 AND a.status = ' . functions_kb::STATUS_ACTIVE . ' AND a.is_redirect = 0';
	}

	/**
	 * The time an article last changed: its last edit, or when it was written if
	 * it was never edited.
	 *
	 * @return string SQL expression
	 */
	protected function changed_sql(): string
	{
		return 'CASE WHEN a.edit_date > a.article_date THEN a.edit_date ELSE a.article_date END';
	}

	/**
	 * Live articles, most recently changed first.
	 *
	 * @param int $limit
	 * @param int $start
	 * @return array Article rows with category_name and changed_time
	 */
	protected function get_changes(int $limit, int $start = 0): array
	{
		$sql = 'SELECT a.*, c.category_name, ' . $this->changed_sql() . ' AS changed_time
			FROM ' . $this->articles_table . ' a
			LEFT JOIN ' . $this->categories_table . ' c ON (c.category_id = a.article_category_id)
			WHERE ' . $this->visible_where() . '
			ORDER BY changed_time DESC, a.article_id DESC';
		$result = $this->db->sql_query_limit($sql, $limit, $start);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * Whether an article row was edited after it was written.
	 *
	 * @param array $row
	 * @return bool
	 */
	protected static function is_update(array $row): bool
	{
		return (int) $row['edit_date'] > (int) $row['article_date'];
	}

	/**
	 * The recent changes page.
	 *
	 * @return Response
	 */
	public function page(): Response
	{
		if (!$this->auth->acl_get('u_kb_view') && !$this->auth->acl_get('a_manage_kb'))
		{
			trigger_error($this->language->lang('NOT_AUTHORISED'));
		}

		$sql = 'SELECT COUNT(a.article_id) AS total
			FROM ' . $this->articles_table . ' a
			WHERE ' . $this->visible_where();
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		$per_page = max(1, (int) ($this->config['kb_articles_per_page'] ?: 10));
		$start = $this->pagination->validate_start($this->request->variable('start', 0), $per_page, $total);

		foreach ($this->get_changes($per_page, $start) as $row)
		{
			$this->template->assign_block_vars('changes', [
				'ARTICLE_TITLE'  => $row['article_title'],
				'DESCRIPTION'    => $row['article_description'],
				'CATEGORY_NAME'  => $row['category_name'],
				'AUTHOR'         => $this->kb->get_author_display($row),
				'CHANGED_TIME'   => $this->user->format_date((int) $row['changed_time']),
				'U_ARTICLE'      => $this->helper->route('phpbbmodders_knowledgebase_article', ['k' => (int) $row['article_id']]),
				'U_CATEGORY'     => $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => (int) $row['article_category_id']]),
				'S_UPDATED'      => self::is_update($row),
			]);
		}

		$this->pagination->generate_template_pagination($this->helper->route('phpbbmodders_knowledgebase_recent_changes'), 'pagination', 'start', $total, $per_page, $start);

		// The feed links come from the page header listener, which also advertises them to feed readers
		$this->template->assign_vars([
			'TOTAL_ITEMS'         => $this->language->lang('TOTAL_ITEMS', $total),
			'CURRENT_PAGE_NUMBER' => $this->pagination->get_on_page($per_page, $start),
			'S_KNOWLEDGEBASE'     => true,
		]);

		$this->template->assign_block_vars('navlinks', [
			'FORUM_NAME'   => $this->language->lang('LIBRARY'),
			'U_VIEW_FORUM' => $this->helper->route('phpbbmodders_knowledgebase_index'),
		]);

		$this->kb->assign_nav_tabs_vars('recent_changes');

		return $this->helper->render('@phpbbmodders_knowledgebase/kb_recent_changes_body.html', $this->language->lang('LIBRARY') . ' &raquo; ' . $this->language->lang('KB_TAB_RECENT_CHANGES'));
	}

	/**
	 * The feed, when its format is offered.
	 *
	 * @param string $_format feed::RSS or feed::ATOM
	 * @return Response
	 */
	public function feed(string $_format): Response
	{
		// A feed reader is a guest, so the guest's right to read the Knowledge Base decides
		if (!$this->auth->acl_get('u_kb_view') && !$this->auth->acl_get('a_manage_kb'))
		{
			return new Response($this->language->lang('NOT_AUTHORISED'), 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
		}

		if (!feed::offers($this->setting(), $_format))
		{
			return new Response($this->language->lang('PAGE_NOT_FOUND'), 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
		}

		$rows = $this->get_changes(feed::LIMIT);
		$entries = [];
		foreach ($rows as $row)
		{
			$link = $this->helper->route('phpbbmodders_knowledgebase_article', ['k' => (int) $row['article_id']], false, false, UrlGeneratorInterface::ABSOLUTE_URL);
			$summary = $this->language->lang(self::is_update($row) ? 'KB_FEED_UPDATED_IN' : 'KB_FEED_ADDED_TO', (string) $row['category_name']);
			if ((string) $row['article_description'] !== '')
			{
				$summary .= ' ' . $row['article_description'];
			}

			$entries[] = [
				'id'      => $link,
				'title'   => $row['article_title'],
				'link'    => $link,
				'summary' => $summary,
				'author'  => (string) $row['author'],
				'time'    => (int) $row['changed_time'],
			];
		}

		$meta = [
			'title'       => $this->language->lang('KB_FEED_TITLE', $this->config['sitename']),
			'description' => $this->language->lang('KB_RECENT_CHANGES_EXPLAIN'),
			'link'        => $this->helper->route('phpbbmodders_knowledgebase_recent_changes', [], false, false, UrlGeneratorInterface::ABSOLUTE_URL),
			'self'        => $this->helper->route('phpbbmodders_knowledgebase_recent_changes_feed', ['_format' => $_format], false, false, UrlGeneratorInterface::ABSOLUTE_URL),
			'updated'     => $rows ? (int) $rows[0]['changed_time'] : time(),
		];

		return new Response(feed::build($_format, $meta, $entries), 200, [
			'Content-Type'  => ($_format === feed::ATOM ? 'application/atom+xml' : 'application/rss+xml') . '; charset=UTF-8',
			'Cache-Control' => 'public, max-age=300',
		]);
	}
}
