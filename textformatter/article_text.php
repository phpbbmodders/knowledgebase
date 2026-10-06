<?php

/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\textformatter;

use phpbb\config\config;
use phpbb\textformatter\s9e\utils;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stores, renders and edits the body of an article in either of its two
 * formats: BBCode (the board's normal post formatting) or Markdown.
 *
 * The format is a per-article flag. BBCode articles go through phpBB's own
 * generate_text_for_*() functions exactly as before; Markdown articles go
 * through a Markdown-enabled formatter that is separate from the one used for
 * forum posts. BBCode, smilies and links still work inside Markdown.
 */
class article_text
{
	/** @var ContainerInterface */
	protected $container;

	/** @var config */
	protected $config;

	/** @var utils */
	protected $utils;

	/**
	 * The parser and renderer are fetched from the container only when a
	 * Markdown article is actually handled, so pages that never touch one do
	 * not pay for building the formatter.
	 *
	 * @param ContainerInterface $container
	 * @param config             $config
	 * @param utils              $utils
	 */
	public function __construct(ContainerInterface $container, config $config, utils $utils)
	{
		$this->container = $container;
		$this->config = $config;
		$this->utils = $utils;
	}

	/**
	 * Prepare submitted text for storage, in place.
	 *
	 * @param string $text     Submitted text; replaced by the stored form
	 * @param bool   $markdown True to parse as Markdown, false for BBCode
	 * @param string $uid      Receives the BBCode uid (always empty in 3.3)
	 * @param string $bitfield Receives the BBCode bitfield (always empty in 3.3)
	 * @param mixed  $options  Receives the BBCode/smilies/links option flags
	 * @return void
	 */
	public function store(string &$text, bool $markdown, &$uid, &$bitfield, &$options): void
	{
		if (!$markdown)
		{
			generate_text_for_storage($text, $uid, $bitfield, $options, true, true, true);
			return;
		}

		$text = $this->parse_markdown($text);
		$uid = $bitfield = '';
		$options = OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS;
	}

	/**
	 * Turn stored text into HTML for display.
	 *
	 * @param string $stored   Stored article text
	 * @param string $uid      Stored BBCode uid
	 * @param bool   $markdown Whether the article is a Markdown article
	 * @param bool   $censor   Apply the board's word censors
	 * @return string
	 */
	public function display(string $stored, string $uid, bool $markdown, bool $censor = true): string
	{
		// A Markdown flag on text that is not stored XML cannot be rendered
		// by the formatter; show it the ordinary way rather than fail.
		if (!$markdown || !preg_match('#^<[rt][ >]#', $stored))
		{
			return generate_text_for_display($stored, $uid, false, 3, $censor);
		}

		/** @var \phpbb\textformatter\s9e\renderer $renderer */
		$renderer = $this->container->get('phpbbmodders.knowledgebase.text_formatter.renderer');
		$old_censor = $renderer->get_viewcensors();

		if ($old_censor !== $censor)
		{
			$renderer->set_viewcensors($censor);
		}

		try
		{
			return $renderer->render($stored);
		}
		finally
		{
			if ($old_censor !== $censor)
			{
				$renderer->set_viewcensors($old_censor);
			}
		}
	}

	/**
	 * Turn stored Markdown text back into what the author typed, escaped for
	 * use inside a textarea.
	 *
	 * @param string $stored Stored article text of a Markdown article
	 * @return string
	 */
	public function for_edit(string $stored): string
	{
		if (!preg_match('#^<[rt][ >]#', $stored))
		{
			return htmlspecialchars($stored, ENT_COMPAT, 'UTF-8');
		}

		return htmlspecialchars($this->utils->unparse($stored), ENT_COMPAT, 'UTF-8');
	}

	/**
	 * @param string $text Submitted text, still escaped by the request layer
	 * @return string Stored XML
	 */
	protected function parse_markdown(string $text): string
	{
		/** @var \phpbb\textformatter\s9e\parser $parser */
		$parser = $this->container->get('phpbbmodders.knowledgebase.text_formatter.parser');

		$parser->enable_bbcodes();
		$parser->enable_magic_url();
		$parser->enable_smilies();
		$parser->enable_bbcode('img');
		$parser->enable_bbcode('flash');
		$parser->enable_bbcode('quote');
		$parser->enable_bbcode('url');
		$parser->set_vars([
			'max_font_size'  => $this->config['max_post_font_size'],
			'max_img_height' => $this->config['max_post_img_height'],
			'max_img_width'  => $this->config['max_post_img_width'],
			'max_smilies'    => $this->config['max_post_smilies'],
			'max_urls'       => $this->config['max_post_urls'],
		]);

		return $parser->parse(htmlspecialchars_decode($text, ENT_QUOTES));
	}
}
