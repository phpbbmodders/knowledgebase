<?php

/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\event;

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbbmodders\knowledgebase\inc\board_search;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Shows the Knowledge Base articles that match a board search in a block of their own above
 * the forum results, and adds the "Also search the Knowledge Base" option to the advanced
 * search form. Nothing is copied into the forum tables and the forum results are not touched.
 */
class board_search_listener implements EventSubscriberInterface
{
	/** @var auth */
	protected auth $auth;

	/** @var config */
	protected config $config;

	/** @var helper */
	protected helper $helper;

	/** @var language */
	protected language $language;

	/** @var request_interface */
	protected request_interface $request;

	/** @var template */
	protected template $template;

	/** @var board_search */
	protected board_search $board_search;

	/**
	 * @param auth              $auth
	 * @param config            $config
	 * @param helper            $helper
	 * @param language          $language
	 * @param request_interface $request
	 * @param template          $template
	 * @param board_search      $board_search
	 */
	public function __construct(auth $auth, config $config, helper $helper, language $language, request_interface $request, template $template, board_search $board_search)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->helper = $helper;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->board_search = $board_search;
	}

	public static function getSubscribedEvents(): array
	{
		return [
			'core.search_modify_forum_select_list'    => 'add_option',
			'core.search_modify_url_parameters'       => 'keep_option',
			'core.search_results_modify_search_title' => 'add_block',
		];
	}

	/**
	 * Whether the board search may bring in the Knowledge Base at all: the Knowledge Base search
	 * and the block are both on and the member may read the Knowledge Base.
	 *
	 * @return bool
	 */
	protected function enabled(): bool
	{
		return $this->config['kb_search'] && $this->config['kb_search_board']
			&& ($this->auth->acl_get('u_kb_view') || $this->auth->acl_get('a_manage_kb'));
	}

	/**
	 * Whether this search asked for the Knowledge Base. A search that does not come from the
	 * advanced form, such as the search box in the header, includes it; the form sends "kb_form"
	 * so that an unticked box can be told from a form that does not have one.
	 *
	 * @return bool
	 */
	protected function wanted(): bool
	{
		return $this->request->variable('kb_form', 0) ? (bool) $this->request->variable('kb_also', 0) : true;
	}

	/**
	 * Show the "Also search the Knowledge Base" option on the advanced search form.
	 *
	 * @return void
	 */
	public function add_option(): void
	{
		if ($this->enabled())
		{
			$this->template->assign_var('S_KB_BOARD_SEARCH', true);
		}
	}

	/**
	 * Carry an unticked option through the links of the results (pages, search in results).
	 *
	 * @param \phpbb\event\data $event
	 * @return void
	 */
	public function keep_option($event): void
	{
		if ($this->enabled() && !$this->wanted())
		{
			$event['u_search'] = $event['u_search'] . '&amp;kb_form=1';
		}
	}

	/**
	 * Show the Knowledge Base block on the first page of the results of a search for words.
	 *
	 * @param \phpbb\event\data $event
	 * @return void
	 */
	public function add_block($event): void
	{
		$keywords = (string) $event['keywords'];
		if ($keywords === '' || $event['start'] > 0 || !$this->enabled() || !$this->wanted())
		{
			return;
		}

		// Searches that are not for words in the forums: the pre-made ones, one author's
		// posts, and a search inside one topic
		if ($this->request->variable('search_id', '') !== '' || $this->request->variable('author', '', true) !== '' || $this->request->variable('author_id', 0) || $this->request->variable('t', 0))
		{
			return;
		}

		if (!$this->board_search->index_created())
		{
			return;
		}

		$terms = ($this->request->variable('terms', 'all') === 'any' && $this->request->variable('add_keywords', '', true) === '') ? 'any' : 'all';
		$fields = $this->request->variable('sf', 'all');
		$fields = in_array($fields, ['titleonly', 'msgonly'], true) ? $fields : 'all';

		$max = max(1, (int) $this->config['kb_search_board_max']);
		$found = $this->board_search->find($keywords, $terms, $fields, $max);
		if ($found === false || $found['total'] < 1 || empty($found['rows']))
		{
			return;
		}

		foreach ($found['rows'] as $row)
		{
			$this->template->assign_block_vars('kb_matches', $row);
		}
		$this->template->assign_vars([
			'S_KB_BOARD_BLOCK'      => true,
			'KB_BOARD_TOTAL'        => $this->language->lang('KB_BOARD_BLOCK_TITLE', (int) $found['total']),
			'KB_BOARD_VIEW_ALL'     => $this->language->lang('KB_BOARD_VIEW_ALL', (int) $found['total']),
			'U_KB_BOARD_VIEW_ALL'   => $this->helper->route('phpbbmodders_knowledgebase_search', [
				'keywords' => html_entity_decode($keywords, ENT_COMPAT),
				'terms'    => $terms,
				'sf'       => $fields,
			]),
		]);
	}
}
