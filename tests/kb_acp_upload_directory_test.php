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

/** The upload directory check on the ACP configuration page must not warn about a directory it does not own. */
class kb_acp_upload_directory_test extends \PHPUnit\Framework\TestCase
{
	private $directory;

	protected function setUp(): void
	{
		$this->directory = sys_get_temp_dir() . '/kb-upload-dir-' . bin2hex(random_bytes(4));
		mkdir($this->directory, 0755);
	}

	protected function tearDown(): void
	{
		chmod($this->directory, 0755);
		rmdir($this->directory);
	}

	private function make_writable(string $directory): void
	{
		$controller = (new \ReflectionClass(acp_controller::class))->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod(acp_controller::class, 'make_directory_writable');
		$method->invoke($controller, $directory);
	}

	public function test_a_writable_directory_keeps_its_mode()
	{
		chmod($this->directory, 0775);

		$this->make_writable($this->directory);

		clearstatcache();
		$this->assertSame('0775', substr(sprintf('%o', fileperms($this->directory)), -4));
	}

	public function test_a_directory_that_is_not_writable_is_opened_up()
	{
		chmod($this->directory, 0555);

		$this->make_writable($this->directory);

		$this->assertTrue(is_writable($this->directory));
	}

	public function test_a_directory_owned_by_someone_else_gives_no_warning_when_it_is_already_writable()
	{
		// /tmp belongs to root, is writable by everyone and chmod() on it fails for an ordinary user.
		$this->make_writable(sys_get_temp_dir());

		$this->assertTrue(is_writable(sys_get_temp_dir()));
	}

	public function test_a_directory_that_cannot_be_opened_up_gives_no_warning_either()
	{
		if (is_writable('/usr'))
		{
			$this->markTestSkipped('Running as a user who can write everywhere, so there is no folder to test with.');
		}

		// /usr is root's and not writable: chmod() cannot work there and must stay silent (the write test reports it).
		$this->make_writable('/usr');

		$this->assertFalse(is_writable('/usr'));
	}
}
