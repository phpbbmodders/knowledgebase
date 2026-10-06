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
require_once dirname(__DIR__) . '/search/backend/kb_search_backend_interface.php';
require_once dirname(__DIR__) . '/search/kb_search_backend_factory.php';

class kb_acp_description_length_test extends \PHPUnit\Framework\TestCase
{
	private function valid(int $submitted): int
	{
		$controller = (new \ReflectionClass(acp_controller::class))->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod(acp_controller::class, 'valid_description_max_length');
		$method->setAccessible(true);

		return $method->invoke($controller, $submitted);
	}

	/** @dataProvider submitted_provider */
	public function test_a_submitted_limit_is_kept_within_one_and_the_column_width(int $submitted, int $expected)
	{
		$this->assertSame($expected, $this->valid($submitted));
	}

	public function submitted_provider(): array
	{
		return [
			'default'            => [255, 255],
			'lower'              => [120, 120],
			'smallest allowed'   => [1, 1],
			'zero'               => [0, 1],
			'negative'           => [-40, 1],
			'one past the column' => [256, 255],
			'far above'          => [100000, 255],
		];
	}
}
