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
require_once dirname(__DIR__) . '/inc/icon.php';
require_once dirname(__DIR__) . '/inc/twig_icon.php';

use phpbbmodders\knowledgebase\inc\icon;
use phpbbmodders\knowledgebase\inc\twig_icon;

/** The same icon, drawn the way the running phpBB needs: 3.3.x (Font Awesome 4) or 4.0 (Font Awesome 6). */
class kb_icon_test extends \PHPUnit\Framework\TestCase
{
	private const ROOT = __DIR__ . '/..';

	/** The icon helper pretending to run on phpBB 3.3.x. */
	private function three(): string
	{
		return kb_test_icon_three::class;
	}

	/** The icon helper pretending to run on phpBB 4.0. */
	private function four(): string
	{
		return kb_test_icon_four::class;
	}

	public function test_on_phpbb_3_3_the_classes_are_exactly_what_the_templates_always_had()
	{
		$three = $this->three();

		$this->assertSame('icon fa-pencil fa-fw', $three::classes('pencil'));
		$this->assertSame('icon fa-pencil fa-fw icon-blue', $three::classes('pencil', 'icon-blue'));
		$this->assertSame('icon fa-file-o fa-fw icon-blue icon-md', $three::classes('file-o', 'icon-blue icon-md'));
		$this->assertSame('icon fa-book fa-fw acp-icon fa-2x', $three::classes('book', 'acp-icon fa-2x'));
	}

	public function test_on_phpbb_4_0_an_icon_gets_the_font_awesome_6_markup_and_a_style_class()
	{
		$four = $this->four();

		$this->assertSame('o-icon o-icon-font icon fa-fw fa-pencil fas icon-blue', $four::classes('pencil', 'icon-blue'));
		$this->assertSame('o-icon o-icon-font icon fa-fw fa-search fas', $four::classes('search'));
	}

	/** @dataProvider outline_icons */
	public function test_on_phpbb_4_0_outline_icons_use_their_new_names_and_the_regular_style(string $old, string $new)
	{
		$four = $this->four();

		$this->assertSame('o-icon o-icon-font icon fa-fw fa-' . $new . ' far', $four::classes($old));
		// ...and nothing changes on 3.3.x.
		$three = $this->three();
		$this->assertSame('icon fa-' . $old . ' fa-fw', $three::classes($old));
	}

	public function outline_icons(): array
	{
		return [
			'blank page'    => ['file-o', 'file'],
			'text page'     => ['file-text-o', 'file-lines'],
			'folder'        => ['folder-o', 'folder'],
			'thumbs up'     => ['thumbs-o-up', 'thumbs-up'],
		];
	}

	public function test_a_name_an_administrator_typed_is_passed_through_unchanged_on_4_0()
	{
		$four = $this->four();

		$this->assertSame('o-icon o-icon-font icon fa-fw fa-circle-o-notch fas', $four::classes('circle-o-notch'));
	}

	public function test_the_real_helper_recognises_phpbb_4_0_by_its_icon_function()
	{
		$this->assertSame(class_exists(\phpbb\template\twig\extension\icon::class), icon::is_font_awesome_6());
	}

	public function test_the_template_function_returns_the_same_classes()
	{
		$twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(['t' => "{{ KbIconClass('pencil', 'icon-blue') }}|{{ KbIconClass('book') }}"]));
		$twig->addExtension(new twig_icon());

		$this->assertSame(icon::classes('pencil', 'icon-blue') . '|' . icon::classes('book'), $twig->render('t'));
	}

	public function test_the_function_is_registered_for_the_template_engine_on_both_phpbb_versions()
	{
		$services = file_get_contents(self::ROOT . '/config/services.yml');

		$this->assertMatchesRegularExpression('/phpbbmodders\.knowledgebase\.twig\.icon:\s+class: phpbbmodders\\\\knowledgebase\\\\inc\\\\twig_icon\s+tags:\s+- \{ name: twig\.extension \}/', $services);
		$this->assertSame('KbIconClass', (new twig_icon())->getFunctions()[0]->getName());
	}

	public function test_no_template_or_php_file_writes_an_icon_class_list_by_hand()
	{
		$offenders = [];
		$files = array_merge(glob(self::ROOT . '/styles/all/template/*.html'), glob(self::ROOT . '/styles/all/template/event/*.html'), glob(self::ROOT . '/adm/style/*.html'));
		foreach (['controller', 'inc', 'event', 'notification/type', 'search'] as $folder)
		{
			$files = array_merge($files, glob(self::ROOT . '/' . $folder . '/*.php'));
		}
		foreach ($files as $file)
		{
			if (basename($file) === 'icon.php')
			{
				continue;
			}
			if (preg_match('/class="(?!\{\{)[^"]*\bicon\b[^"]*\bfa-/', file_get_contents($file)))
			{
				$offenders[] = basename($file);
			}
		}

		$this->assertSame([], $offenders, 'these draw an icon the Font Awesome 4 way, which is blank on phpBB 4.0');
	}
}

class kb_test_icon_three extends icon
{
	public static function is_font_awesome_6(): bool
	{
		return false;
	}
}

class kb_test_icon_four extends icon
{
	public static function is_font_awesome_6(): bool
	{
		return true;
	}
}
