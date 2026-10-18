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

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/upload_directory.php';
require_once dirname(__DIR__) . '/migrations/version_2_0_28.php';

/**
 * The attachments folder in the board's store folder: made when the extension is enabled and when the
 * Configuration page opens, without warnings about a folder it does not own, and the move of attachments
 * out of the extension's own folder.
 */
class kb_upload_directory_test extends \PHPUnit\Framework\TestCase
{
	private string $root;

	protected function setUp(): void
	{
		$this->root = sys_get_temp_dir() . '/kb-upload-root-' . bin2hex(random_bytes(4)) . '/';
		mkdir($this->root, 0755);
	}

	protected function tearDown(): void
	{
		$this->remove($this->root);
	}

	private function remove(string $path): void
	{
		if (is_dir($path) && !is_link($path))
		{
			@chmod($path, 0755);
			foreach (array_diff(scandir($path), ['.', '..']) as $name)
			{
				$this->remove(rtrim($path, '/') . '/' . $name);
			}
			rmdir($path);
		}
		else if (file_exists($path) || is_link($path))
		{
			unlink($path);
		}
	}

	private function mode(string $path): string
	{
		clearstatcache();

		return substr(sprintf('%o', fileperms($path)), -4);
	}

	private function legacy_file(string $name, string $contents = 'data'): void
	{
		$legacy = upload_directory::legacy_path($this->root);
		if (!is_dir($legacy))
		{
			mkdir($legacy . 'plupload', 0755, true);
		}
		file_put_contents($legacy . $name, $contents);
	}

	public function test_the_folders_are_in_the_store_folder_and_the_old_one_is_in_the_extension()
	{
		$this->assertSame('./store/phpbbmodders_knowledgebase/files/', upload_directory::path('./'));
		$this->assertSame('./ext/phpbbmodders/knowledgebase/files/', upload_directory::legacy_path('./'));
	}

	public function test_it_makes_the_folder_and_its_plupload_folder_with_the_protection_files()
	{
		$this->assertTrue(upload_directory::ensure($this->root));

		$path = upload_directory::path($this->root);
		foreach ([$path, $path . 'plupload/'] as $directory)
		{
			$this->assertDirectoryExists($directory);
			$this->assertTrue(is_writable($directory));
			$this->assertStringContainsString('Require all denied', file_get_contents($directory . '.htaccess'));
			$this->assertFileExists($directory . 'index.htm');
		}
	}

	public function test_a_second_run_changes_nothing_and_leaves_an_edited_protection_file_alone()
	{
		upload_directory::ensure($this->root);
		$htaccess = upload_directory::path($this->root) . '.htaccess';
		file_put_contents($htaccess, "# edited by the administrator\n");
		chmod(upload_directory::path($this->root), 0775);

		$this->assertTrue(upload_directory::ensure($this->root));

		$this->assertSame("# edited by the administrator\n", file_get_contents($htaccess));
		$this->assertSame('0775', $this->mode(upload_directory::path($this->root)));
	}

	public function test_a_folder_that_is_not_writable_is_opened_up()
	{
		if (is_writable('/usr'))
		{
			$this->markTestSkipped('Running as a user who can write everywhere, so a read-only folder is still writable.');
		}
		upload_directory::ensure($this->root);
		chmod(upload_directory::path($this->root), 0555);

		$this->assertTrue(upload_directory::ensure($this->root));
		$this->assertTrue(is_writable(upload_directory::path($this->root)));
	}

	public function test_a_board_whose_folder_cannot_be_made_says_so_without_a_warning()
	{
		if (is_writable('/usr'))
		{
			$this->markTestSkipped('Running as a user who can write everywhere, so there is no folder to test with.');
		}

		// /usr is root's and not writable: the folders cannot be made there and that must stay silent.
		$this->assertFalse(upload_directory::ensure('/usr/'));
		$this->assertDirectoryDoesNotExist('/usr/store');
	}

	public function test_the_attachments_move_to_the_new_folder_and_the_protection_files_stay_behind()
	{
		$this->legacy_file('a1b2c3.png');
		$this->legacy_file('thumb_a1b2c3.png', 'thumb');
		$this->legacy_file('.htaccess', 'legacy protection');
		$this->legacy_file('index.htm', 'legacy index');

		$this->assertSame([2, 0], upload_directory::move_legacy_files($this->root));

		$legacy = upload_directory::legacy_path($this->root);
		$new = upload_directory::path($this->root);
		$this->assertSame('data', file_get_contents($new . 'a1b2c3.png'));
		$this->assertSame('thumb', file_get_contents($new . 'thumb_a1b2c3.png'));
		$this->assertFileDoesNotExist($legacy . 'a1b2c3.png');
		$this->assertSame('legacy protection', file_get_contents($legacy . '.htaccess'));
		$this->assertDirectoryExists($legacy . 'plupload');
		$this->assertStringContainsString('Require all denied', file_get_contents($new . '.htaccess'));
	}

	public function test_a_file_that_is_already_in_the_new_folder_is_never_written_over()
	{
		upload_directory::ensure($this->root);
		file_put_contents(upload_directory::path($this->root) . 'same.dat', 'new');
		$this->legacy_file('same.dat', 'old');

		$this->assertSame([0, 1], upload_directory::move_legacy_files($this->root));

		$this->assertSame('new', file_get_contents(upload_directory::path($this->root) . 'same.dat'));
		$this->assertSame('old', file_get_contents(upload_directory::legacy_path($this->root) . 'same.dat'));
	}

	public function test_a_second_move_and_a_board_with_nothing_to_move_do_nothing()
	{
		$this->assertSame([0, 0], upload_directory::move_legacy_files($this->root));

		$this->legacy_file('x.dat');
		upload_directory::move_legacy_files($this->root);

		$this->assertSame([0, 0], upload_directory::move_legacy_files($this->root));
		$this->assertSame('data', file_get_contents(upload_directory::path($this->root) . 'x.dat'));
	}

	public function test_attachments_stay_where_they_are_when_the_new_folder_cannot_be_made()
	{
		if (is_writable('/usr'))
		{
			$this->markTestSkipped('Running as a user who can write everywhere, so there is no folder to test with.');
		}
		// A store folder that exists but cannot be written to
		mkdir($this->root . 'store', 0555);
		$this->legacy_file('keep.dat');

		$this->assertSame([0, 1], upload_directory::move_legacy_files($this->root));

		$this->assertFileExists(upload_directory::legacy_path($this->root) . 'keep.dat');
	}

	public function test_the_migration_moves_the_attachments_and_sets_the_version()
	{
		$migration = new \phpbbmodders\knowledgebase\migrations\version_2_0_28(new \phpbb\config\config([]), $this->createMock(\phpbb\db\driver\driver_interface::class), $this->createMock(\phpbb\db\tools\tools_interface::class), $this->root, 'php', 'phpbb_', []);
		$this->legacy_file('moved.dat');

		$this->assertSame(['\phpbbmodders\knowledgebase\migrations\version_2_0_27'], $migration::depends_on());
		$this->assertSame([['custom', [[$migration, 'move_attachments']]], ['config.update', ['kb_version', '2.0.28']]], $migration->update_data());
		$this->assertSame([['config.update', ['kb_version', '2.0.27']]], $migration->revert_data());

		$migration->move_attachments();

		$this->assertSame('data', file_get_contents(upload_directory::path($this->root) . 'moved.dat'));
		$this->assertFileDoesNotExist(upload_directory::legacy_path($this->root) . 'moved.dat');
	}
}
