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
require_once dirname(__DIR__) . '/ext.php';

use phpbbmodders\knowledgebase\ext;

/**
 * The PHP and phpBB versions the extension claims to support are stated in
 * four places (composer.json, ext.php, the CI matrix and the notes); these
 * tests keep them from drifting apart.
 */
class kb_declared_support_test extends \PHPUnit\Framework\TestCase
{
	private const ROOT = __DIR__ . '/..';

	private function composer(): array
	{
		return json_decode(file_get_contents(self::ROOT . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
	}

	public function test_composer_requires_php_8_3_36_or_newer()
	{
		$this->assertSame('>=8.3.36', $this->composer()['require']['php']);
	}

	public function test_the_extension_refuses_to_enable_below_the_same_minimum()
	{
		$this->assertSame('8.3.36', ext::MIN_PHP_VERSION);
		$this->assertSame('>=' . ext::MIN_PHP_VERSION, $this->composer()['require']['php']);
	}

	/** @dataProvider php_versions */
	public function test_php_versions_are_judged_against_the_minimum(string $running, bool $expected)
	{
		$this->assertSame($expected, ext::php_version_meets_minimum($running), $running);
	}

	public function php_versions(): array
	{
		return [
			'8.0 is too old'            => ['8.0.30', false],
			'8.1 is too old'            => ['8.1.31', false],
			'8.2 development build'     => ['8.2.0-dev', false],
			'first 8.2 release'         => ['8.2.0', false],
			'8.2 patch release'         => ['8.2.27', false],
			'first 8.3 release'         => ['8.3.0', false],
			'below security minimum'    => ['8.3.35', false],
			'security minimum'          => ['8.3.36', true],
			'later 8.3 patch release'   => ['8.3.37', true],
			'8.4'                       => ['8.4.26', true],
			'a future major version'    => ['9.0.0', true],
		];
	}

	public function test_the_php_running_the_tests_meets_the_minimum()
	{
		$extension = (new \ReflectionClass(ext::class))->newInstanceWithoutConstructor();

		$this->assertTrue($extension->check_php_version());
	}

	public function test_the_ci_matrix_starts_at_the_declared_minimum()
	{
		$workflow = file_get_contents(self::ROOT . '/.github/workflows/tests.yml');

		$this->assertSame(1, preg_match("/PHP_VERSION_MATRIX: '(\\[[^']+\\])'/", $workflow, $matrix));
		$versions = json_decode($matrix[1], true);
		usort($versions, 'version_compare');

		$this->assertSame(substr(ext::MIN_PHP_VERSION, 0, 3), $versions[0], 'the lowest PHP the tests run on is the lowest one claimed');
		$this->assertGreaterThanOrEqual(3, count($versions));
		$this->assertContains('8.5', $versions, 'the newest PHP the hosts run is tested too');
		$this->assertSame(1, preg_match("/PRIMARY_PHP_VERSION: '([0-9.]+)'/", $workflow, $primary));
		$this->assertContains($primary[1], $versions);
	}

	public function test_the_phpbb_requirement_and_soft_requirement_agree_and_state_the_same_minimum_as_ext_php()
	{
		$composer = $this->composer();
		$hard = $composer['require']['phpbb/phpbb'];
		$soft = $composer['extra']['soft-require']['phpbb/phpbb'];

		$this->assertSame('>=' . ext::MIN_PHPBB_VERSION, explode(' ', $hard)[0]);
		$this->assertSame('>=' . ext::MIN_PHPBB_VERSION, explode(',', $soft)[0]);
		// Both caps allow phpBB 3.3.x and 4.0.x and stop at the same bound, so one cannot be changed without the other.
		$this->assertSame('<4.1.0', explode(' ', $hard)[1]);
		$this->assertSame('<4.1.0@dev', explode(',', $soft)[1]);
	}

	public function test_the_compatibility_notes_state_the_same_php_floor()
	{
		$notes = file_get_contents(self::ROOT . '/docs/phpbb-4.0-compatibility.md');

		$this->assertStringContainsString('`require.php`: `>=8.3.36`', $notes);
		$this->assertStringNotContainsString('`>=8.0`', $notes);
	}
}
