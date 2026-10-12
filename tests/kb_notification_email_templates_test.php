<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\notification\type;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/notification/type/avatar_compat.php';
require_once dirname(__DIR__) . '/notification/type/need_approval.php';
require_once dirname(__DIR__) . '/notification/type/approve.php';
require_once dirname(__DIR__) . '/notification/type/disapprove.php';

class kb_notification_email_templates_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider notification_types */
	public function test_the_e_mail_template_each_type_asks_for_is_shipped($class)
	{
		$notification = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
		$template = $notification->get_email_template();

		$this->assertStringStartsWith('@phpbbmodders_knowledgebase/', $template);
		$name = substr($template, strlen('@phpbbmodders_knowledgebase/')) . '.txt';
		$folders = glob(dirname(__DIR__) . '/language/*/email', GLOB_ONLYDIR);
		$this->assertNotEmpty($folders);
		foreach ($folders as $folder)
		{
			$this->assertFileExists($folder . '/' . $name, 'Missing e-mail template for ' . $class . ' in ' . basename(dirname($folder)));
		}
	}

	public function notification_types()
	{
		return [[need_approval::class], [approve::class], [disapprove::class]];
	}

	/** @dataProvider email_templates */
	public function test_each_email_names_the_board_without_calling_it_a_conference_and_ends_with_the_signature(string $file)
	{
		$text = file_get_contents(dirname(__DIR__) . '/language/en/email/' . $file);

		$this->assertStringNotContainsStringIgnoringCase('conference', $text);
		$this->assertStringStartsWith('Subject: ', $text);
		$this->assertStringContainsString('{EMAIL_SIG}', $text);
	}

	public function email_templates(): array
	{
		return [['user_need_approval.txt'], ['article_approve.txt'], ['article_disapprove.txt']];
	}
}
