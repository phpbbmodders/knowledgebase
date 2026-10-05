<?php
namespace phpbbmodders\knowledgebase\notification\type;

require_once __DIR__ . '/kb_extensions_test.php';
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
}
