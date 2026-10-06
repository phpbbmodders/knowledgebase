<?php
namespace phpbbmodders\knowledgebase;

require_once __DIR__ . '/kb_extensions_test.php';
if (!defined('IN_PHPBB')) define('IN_PHPBB', true);
require_once (getenv('PHPBB_ROOT_PATH') ?: dirname(__DIR__, 4)) . '/includes/functions.php';
require_once dirname(__DIR__) . '/ext.php';
if (!defined('PHPBB_VERSION')) define('PHPBB_VERSION', '3.3.19');

class kb_ext_enableable_test extends \PHPUnit\Framework\TestCase
{
	private function ext(bool $original_enabled): ext
	{
		$language = $this->getMockBuilder(\phpbb\language\language::class)->disableOriginalConstructor()->getMock();
		$language->method('lang')->willReturnCallback(fn() => implode('|', func_get_args()));
		$manager = $this->getMockBuilder(\phpbb\extension\manager::class)->disableOriginalConstructor()->getMock();
		$manager->method('is_enabled')->willReturnCallback(fn($name) => $original_enabled && $name === 'sheer/knowledgebase');
		$container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
		$container->method('get')->willReturnCallback(fn($id) => ['language' => $language, 'ext.manager' => $manager][$id]);

		$ext = (new \ReflectionClass(ext::class))->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty(\phpbb\extension\base::class, 'container');
		$property->setAccessible(true);
		$property->setValue($ext, $container);

		return $ext;
	}

	public function test_it_can_be_enabled_when_the_original_package_is_not_enabled()
	{
		$this->assertTrue($this->ext(false)->is_enableable());
	}

	public function test_it_refuses_while_the_original_package_is_enabled_and_says_which()
	{
		$this->assertSame(['INSTALL_FAILED_ORIGINAL_ENABLED|sheer/knowledgebase'], $this->ext(true)->is_enableable());
	}
}
