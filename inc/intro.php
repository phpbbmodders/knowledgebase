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

use phpbb\config\config;
use phpbb\config\db_text;
use phpbb\template\template;
use phpbbmodders\knowledgebase\textformatter\article_text;

/**
 * The introduction text shown at the top of the Knowledge Base index.
 *
 * An administrator switches it on and edits it on the ACP configuration page,
 * in BBCode or Markdown like an article. It is stored parsed, in the
 * kb_intro_text config_text entry, with its BBCode uid (kb_intro_uid) and
 * whether it is Markdown (kb_intro_markdown); kb_intro_enabled switches it on.
 * Migration version_2_0_33 adds it switched off, holding KB_INTRO_DEFAULT_TEXT
 * from language/<lang>/default_content.php (the text kinerity/knowledgebase
 * showed on its index) in the board's default language.
 */
class intro
{
	protected config $config;
	protected db_text $config_text;
	protected article_text $article_text;
	protected template $template;

	/**
	 * @param config       $config
	 * @param db_text      $config_text
	 * @param article_text $article_text
	 * @param template     $template
	 */
	public function __construct(config $config, db_text $config_text, article_text $article_text, template $template)
	{
		$this->config = $config;
		$this->config_text = $config_text;
		$this->article_text = $article_text;
		$this->template = $template;
	}

	/**
	 * The stored form of plain text with no BBCode, as phpBB's parser would
	 * store it, for the default text a migration writes.
	 *
	 * @param string $text Plain text, not escaped
	 * @return string
	 */
	public static function stored_plain_text(string $text): string
	{
		return '<t>' . htmlspecialchars($text, ENT_COMPAT, 'UTF-8') . '</t>';
	}

	/**
	 * Whether the introduction is switched on.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool
	{
		return !empty($this->config['kb_intro_enabled']);
	}

	/**
	 * Whether the stored text is Markdown.
	 *
	 * @return bool
	 */
	public function is_markdown(): bool
	{
		return !empty($this->config['kb_intro_markdown']);
	}

	/**
	 * Put the introduction on the page being built, when it is switched on
	 * and not empty: KB_INTRO (HTML) and S_KB_INTRO.
	 *
	 * @return void
	 */
	public function assign(): void
	{
		$html = $this->is_enabled() ? $this->html() : '';

		$this->template->assign_vars([
			'S_KB_INTRO' => $html !== '',
			'KB_INTRO'   => $html,
		]);
	}

	/**
	 * The introduction as HTML, whether or not it is switched on.
	 *
	 * @return string Empty when there is no text
	 */
	public function html(): string
	{
		$stored = (string) $this->config_text->get('kb_intro_text');
		if (trim(strip_tags($stored)) === '')
		{
			return '';
		}

		return $this->article_text->display($stored, (string) ($this->config['kb_intro_uid'] ?? ''), $this->is_markdown());
	}

	/**
	 * The introduction as the administrator typed it, escaped for a textarea.
	 *
	 * @return string
	 */
	public function for_edit(): string
	{
		return $this->article_text->for_edit((string) $this->config_text->get('kb_intro_text'));
	}

	/**
	 * Save the introduction from the ACP form.
	 *
	 * @param bool   $enabled
	 * @param string $text     Submitted text, escaped by the request layer
	 * @param bool   $markdown True to parse it as Markdown, false for BBCode
	 * @return void
	 */
	public function save(bool $enabled, string $text, bool $markdown): void
	{
		$uid = $bitfield = '';
		$options = 0;
		if (trim($text) !== '')
		{
			$this->article_text->store($text, $markdown, $uid, $bitfield, $options);
		}
		else
		{
			$text = '';
		}

		$this->config_text->set('kb_intro_text', $text);
		$this->config->set('kb_intro_uid', (string) $uid);
		$this->config->set('kb_intro_markdown', $markdown ? 1 : 0);
		$this->config->set('kb_intro_enabled', $enabled ? 1 : 0);
	}
}
