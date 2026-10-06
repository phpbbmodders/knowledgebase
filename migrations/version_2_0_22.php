<?php

/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\migrations;

class version_2_0_22 extends \phpbb\db\migration\container_aware_migration
{
	/** How many articles are re-parsed per query. */
	protected const BATCH = 50;

	public static function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_2_0_21'];
	}

	/**
	 * Markdown articles were stored with a hard line break at every newline in
	 * a paragraph, so text written in wrapped lines came out ragged. The
	 * Markdown formatter no longer does that, but already stored articles keep
	 * the breaks until their text is parsed again. This parses the Markdown
	 * source of every Markdown article, pending revision and draft of an edit
	 * once more and saves it only where the result changed, so running it twice
	 * is harmless. BBCode articles are not touched.
	 */
	public function update_data()
	{
		return [
			['custom', [[$this, 'reparse_markdown_articles']]],
			['config.update', ['kb_version', '2.0.22']],
		];
	}

	public function reparse_markdown_articles()
	{
		foreach (['kb_articles', 'kb_article_revisions', 'kb_article_draft_edits'] as $table)
		{
			$this->reparse_table($this->table_prefix . $table);
		}
	}

	/**
	 * @param string $table Full table name; all three have article_id and article_markdown
	 */
	protected function reparse_table(string $table): void
	{
		$sql = 'SELECT article_id
			FROM ' . $table . '
			WHERE article_markdown = 1
			ORDER BY article_id ASC';
		$result = $this->db->sql_query($sql);
		$article_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$article_ids[] = (int) $row['article_id'];
		}
		$this->db->sql_freeresult($result);

		// Most boards have none, and a new install never does: do not build
		// the formatter for nothing.
		if (!$article_ids)
		{
			return;
		}

		$article_text = $this->get_article_text();

		foreach (array_chunk($article_ids, self::BATCH) as $batch)
		{
			$sql = 'SELECT article_id, article_body
				FROM ' . $table . '
				WHERE ' . $this->db->sql_in_set('article_id', $batch);
			$result = $this->db->sql_query($sql);
			$rows = $this->db->sql_fetchrowset($result);
			$this->db->sql_freeresult($result);

			foreach ($rows as $row)
			{
				$text = $article_text->for_edit($row['article_body']);
				$uid = $bitfield = $options = '';
				$article_text->store($text, true, $uid, $bitfield, $options);

				if ($text !== $row['article_body'])
				{
					$sql = 'UPDATE ' . $table . '
						SET ' . $this->db->sql_build_array('UPDATE', ['article_body' => $text]) . '
						WHERE article_id = ' . (int) $row['article_id'];
					$this->db->sql_query($sql);
				}
			}
		}
	}

	/**
	 * The article text helper, built from the board's own services.
	 *
	 * The extension's own services are not in the container while the
	 * extension is being enabled (the container was compiled before it was
	 * enabled), and an update that disables and re-enables it runs this
	 * migration in exactly that state. So the Markdown parser is assembled
	 * here from core services and handed to the helper in a small container
	 * of its own.
	 *
	 * @return \phpbbmodders\knowledgebase\textformatter\article_text
	 */
	protected function get_article_text(): \phpbbmodders\knowledgebase\textformatter\article_text
	{
		$parser_key = '_phpbbmodders_knowledgebase_text_formatter_parser_v2';
		$factory = new \phpbbmodders\knowledgebase\textformatter\factory(
			$this->container->get('text_formatter.data_access'),
			$this->container->get('cache.driver'),
			$this->container->get('dispatcher'),
			$this->container->get('config'),
			$this->container->get('text_formatter.s9e.link_helper'),
			$this->container->get('log'),
			$this->container->getParameter('text_formatter.cache.dir'),
			$parser_key,
			'_phpbbmodders_knowledgebase_text_formatter_renderer_v2'
		);

		$local = new \Symfony\Component\DependencyInjection\Container();
		$local->set('phpbbmodders.knowledgebase.text_formatter.parser', new \phpbb\textformatter\s9e\parser(
			$this->container->get('cache.driver'),
			$parser_key,
			$factory,
			$this->container->get('dispatcher')
		));

		return new \phpbbmodders\knowledgebase\textformatter\article_text($local, $this->container->get('config'), $this->container->get('text_formatter.utils'));
	}
}
