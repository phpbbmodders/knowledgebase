<?php
namespace phpbbmodders\knowledgebase\controller;

function redirect($url)
{
	throw new \RuntimeException($url);
}

require dirname(__DIR__) . '/controller/index.php';
if (!class_exists(\phpbb\controller\helper::class))
{
	$root = getenv('PHPBB_ROOT_PATH') ?: dirname(__DIR__, 4);
	require $root . '/vendor/autoload.php';
	spl_autoload_register(function ($class) use ($root) {
		if (strpos($class, 'phpbb\\') === 0)
		{
			require $root . '/' . str_replace('\\', '/', $class) . '.php';
		}
	});
}

class kb_alias_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider route_paths */
	public function test_alias_preserves_route_base($base, $query, $expected)
	{
		$helper = $this->getMockBuilder(\phpbb\controller\helper::class)
			->disableOriginalConstructor()->onlyMethods(['route'])->getMock();
		$helper->expects($this->once())->method('route')
			->with('phpbbmodders_knowledgebase_index', [], false)->willReturn($base);
		$request = $this->createMock(\phpbb\request\request_interface::class);
		$request->method('server')->with('QUERY_STRING')->willReturn($query);
		$reflection = new \ReflectionClass(index::class);
		$controller = $reflection->newInstanceWithoutConstructor();
		foreach (['helper' => $helper, 'request' => $request] as $name => $value)
		{
			$property = $reflection->getProperty($name);
			$property->setAccessible(true);
			$property->setValue($controller, $value);
		}
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage($expected);
		$controller->kb_alias_path('article');
	}

	public function route_paths()
	{
		return [
			['/app.php/knowledgebase', 'k=7&name=a%26b', '/app.php/knowledgebase/article?k=7&name=a%26b'],
			['/forum/app.php/knowledgebase?sid=abc', '', '/forum/app.php/knowledgebase/article'],
			['/forum/knowledgebase', 'k=7', '/forum/knowledgebase/article?k=7'],
		];
	}
}
