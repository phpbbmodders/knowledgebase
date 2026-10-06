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

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';
require_once dirname(__DIR__) . '/controller/approve.php';

class kb_queue_display_test extends \PHPUnit\Framework\TestCase
{
	/**
	 * @dataProvider display_provider
	 */
	public function test_the_display_option_resolves_to_what_the_queue_lists(string $filter, bool $show_all, string $view, int $team_id, bool $include_active, string $value)
	{
		$this->assertSame(
			['view' => $view, 'team_id' => $team_id, 'include_active' => $include_active, 'value' => $value],
			approve::resolve_queue_display($filter, $show_all)
		);
	}

	public function display_provider(): array
	{
		return [
			'default needs attention'          => ['', false, 'attention', 0, false, ''],
			'all articles and redirects'       => ['all', false, 'all', 0, true, 'all'],
			'every redirect'                   => ['redirect', false, 'redirect', 0, true, 'redirect'],
			'inactive articles'                => ['inactive', false, 'inactive', 0, true, 'inactive'],
			'team, needing attention'          => ['team_9', false, 'team', 9, false, 'team_9'],
			'team, including active'           => ['team_9_all', false, 'team', 9, true, 'team_9_all'],
			'old show_all link, default'       => ['', true, 'all', 0, true, 'all'],
			'old show_all link, redirects'     => ['redirect', true, 'redirect', 0, true, 'redirect'],
			'old show_all link, inactive'      => ['inactive', true, 'inactive', 0, true, 'inactive'],
			'old show_all link, team'          => ['team_9', true, 'team', 9, true, 'team_9_all'],
			'unknown value'                    => ['bogus', false, 'attention', 0, false, ''],
			'unknown value with old show_all'  => ['bogus', true, 'attention', 0, false, ''],
			'team zero'                        => ['team_0', false, 'attention', 0, false, ''],
			'team id with junk after it'       => ['team_9x', false, 'attention', 0, false, ''],
			'team id with a leading zero'      => ['team_09', false, 'attention', 0, false, ''],
			'negative team id'                 => ['team_-3', false, 'attention', 0, false, ''],
			'injection attempt in the team id' => ['team_9 OR 1=1', false, 'attention', 0, false, ''],
		];
	}
}
