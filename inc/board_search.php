<?php

/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\inc;

use phpbb\controller\helper;
use phpbb\db\driver\driver_interface;
use phpbb\user;
use phpbbmodders\knowledgebase\search\kb_search_backend_factory;
use phpbbmodders\knowledgebase\textformatter\article_text;

/**
 * Runs a keyword search over the approved articles and turns the top matches into rows for
 * the block on the board's search results. The articles are found by the search engine
 * chosen in the ACP, the same one the Knowledge Base's own search page uses.
 */
class board_search
{
	/** Longest stretch of text shown for a match, in characters */
	public const SNIPPET_LENGTH = 200;

	/** @var driver_interface */
	protected driver_interface $db;

	/** @var helper */
	protected helper $helper;

	/** @var user */
	protected user $user;

	/** @var functions_kb */
	protected functions_kb $kb;

	/** @var article_text */
	protected article_text $article_text;

	/** @var kb_search_backend_factory */
	protected kb_search_backend_factory $factory;

	/** @var string */
	protected string $articles_table;

	/** @var string */
	protected string $phpbb_root_path;

	/** @var string */
	protected string $php_ext;

	/**
	 * @param driver_interface          $db
	 * @param helper                    $helper
	 * @param user                      $user
	 * @param functions_kb              $kb
	 * @param article_text              $article_text
	 * @param kb_search_backend_factory $factory
	 * @param string                    $articles_table
	 * @param string                    $phpbb_root_path
	 * @param string                    $php_ext
	 */
	public function __construct(driver_interface $db, helper $helper, user $user, functions_kb $kb, article_text $article_text, kb_search_backend_factory $factory, string $articles_table, string $phpbb_root_path, string $php_ext)
	{
		$this->db = $db;
		$this->helper = $helper;
		$this->user = $user;
		$this->kb = $kb;
		$this->article_text = $article_text;
		$this->factory = $factory;
		$this->articles_table = $articles_table;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Whether the active search engine has an index to search in.
	 *
	 * @return bool
	 */
	public function index_created(): bool
	{
		return $this->factory->get_active()->index_created();
	}

	/**
	 * Find the best matches for some words.
	 *
	 * @param string $keywords What was typed
	 * @param string $terms    'all' or 'any'
	 * @param string $fields   'all', 'titleonly' or 'msgonly'
	 * @param int    $max      Most matches to return
	 * @return array|bool False when the words cannot be searched on (all too short or too common).
	 *                        Otherwise ['total' => matches, 'rows' => rows], a row having TITLE and
	 *                        SNIPPET (marked, safe to print), CATEGORY, DATE, U_VIEW and U_CAT.
	 */
	public function find(string $keywords, string $terms, string $fields, int $max)
	{
		$search = $this->factory->get_active();
		if (!$search->split_keywords($keywords, $terms) || !$search->get_search_query())
		{
			return false;
		}

		$sort_by_sql = ['a' => 'author', 't' => 'article_date', 'c' => 'article_category_id', 's' => 'article_title'];
		$ids = [];
		$start = 0;
		$total = (int) $search->keyword_search('posts', $fields, $terms, $sort_by_sql, 't', 'd', '0', [], 0, [], '', $ids, $start, $max);

		return ['total' => $total, 'rows' => $ids ? $this->build_rows($ids, $keywords) : []];
	}

	/**
	 * The text of an article as plain words, escaped for printing. The text is stored as
	 * formatter XML (BBCode or Markdown), so it is rendered first and then stripped of its tags.
	 *
	 * @param array $row An article row with article_body, bbcode_uid and article_markdown
	 * @return string
	 */
	protected function plain_text(array $row): string
	{
		$html = $this->article_text->display($row['article_body'], (string) $row['bbcode_uid'], !empty($row['article_markdown']));
		$html = preg_replace('#<(?:br\s*/?|/p|/li|/h[1-6]|/tr|/div|/blockquote)>#i', ' ', $html);
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return utf8_htmlspecialchars(trim(preg_replace('#\s+#u', ' ', $text)));
	}

	/**
	 * @param array  $ids      Article ids, in the order the search engine gave them
	 * @param string $keywords
	 * @return array The rows, as described for find()
	 */
	protected function build_rows(array $ids, string $keywords): array
	{
		include_once($this->phpbb_root_path . 'includes/functions_content.' . $this->php_ext);

		$hilit = phpbb_clean_search_string(str_replace(['+', '-', '|', '(', ')', '&quot;'], ' ', $keywords));
		$words = [];
		foreach (array_filter(explode(' ', $hilit), 'strlen') as $word)
		{
			$word = str_replace('\*', '\w*?', preg_quote(phpbb_clean_search_string($word), '#'));
			$word = preg_replace('#(^|\s)\\\\w\*\?(\s|$)#', '$1\w+?$2', $word);
			if ($word !== '')
			{
				$words[] = $word;
			}
		}
		$pattern = $words ? '#(?!<.*)(?<!\w)(' . implode('|', $words) . ')(?!\w|[^<>]*(?:</s(?:cript|tyle))?>)#isu' : '';

		$sql = 'SELECT article_id, article_title, article_body, bbcode_uid, article_markdown, article_category_id, article_date
			FROM ' . $this->articles_table . '
			WHERE ' . $this->db->sql_in_set('article_id', $ids) . '
				AND approved = 1';
		$result = $this->db->sql_query($sql);
		$found = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$found[(int) $row['article_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		$rows = [];
		foreach ($ids as $id)
		{
			if (!isset($found[(int) $id]))
			{
				continue;
			}

			$row = $found[(int) $id];
			$snippet = get_context($this->plain_text($row), $words ? array_map('stripslashes', $words) : [], self::SNIPPET_LENGTH);
			$title = $row['article_title'];
			if ($pattern !== '')
			{
				$mark = '<span class="posthilit">$1</span>';
				$title = preg_replace($pattern, $mark, $title);
				$snippet = preg_replace($pattern, $mark, $snippet);
			}

			$category = $this->kb->get_cat_info((int) $row['article_category_id']);
			$rows[] = [
				'TITLE'    => $title,
				'SNIPPET'  => $snippet,
				'CATEGORY' => $category ? $category['category_name'] : '',
				'DATE'     => $this->user->format_date($row['article_date']),
				'U_VIEW'   => $this->helper->route('phpbbmodders_knowledgebase_article', ['k' => (int) $id]),
				'U_CAT'    => $this->helper->route('phpbbmodders_knowledgebase_category', ['id' => (int) $row['article_category_id']]),
			];
		}

		return $rows;
	}
}
