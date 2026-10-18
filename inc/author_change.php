<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
/**
 * Transfer primary article ownership without rewriting content or revisions.
 * The caller must check the category permission and resolve a valid author.
 */
namespace phpbbmodders\knowledgebase\inc;

class author_change
{
	public function __construct(
		protected \phpbb\db\driver\driver_interface $db,
		protected \phpbb\cache\driver\driver_interface $cache,
		protected \phpbb\log\log_interface $log,
		protected \phpbb\user $user,
		protected string $articles_table,
		protected string $co_authors_table,
		protected string $draft_edits_table,
		protected string $search_results_table
	)
	{
	}

	/**
	 * Change the primary author atomically and record the old/new identities.
	 * Returns false for an unchanged owner. Throws if the article changed or
	 * a private draft edit exists; these must never transfer to another owner.
	 */
	public function transfer(array $article, int $author_id, int $author_type, string $author_name): bool
	{
		if ((int) $article['author_id'] === $author_id && (int) $article['author_type'] === $author_type)
		{
			return false;
		}
		if ((int) $article['status'] === functions_kb::STATUS_DRAFT)
		{
			throw new \RuntimeException('KB_CHANGE_AUTHOR_PRIVATE_DRAFT');
		}

		$article_id = (int) $article['article_id'];
		$this->db->sql_transaction('begin');
		try
		{
			$sql = 'UPDATE ' . $this->articles_table . ' SET ' . $this->db->sql_build_array('UPDATE', [
				'author_id' => $author_id, 'author_type' => $author_type, 'author' => $author_name,
			]) . '
				WHERE article_id = ' . $article_id . '
				AND author_id = ' . (int) $article['author_id'] . '
				AND author_type = ' . (int) $article['author_type'] . '
				AND status = ' . (int) $article['status'] . '
				AND NOT EXISTS (SELECT article_id FROM ' . $this->draft_edits_table . '
					WHERE article_id = ' . $article_id . ')';
			$this->db->sql_query($sql);
			if (!$this->db->sql_affectedrows())
			{
				throw new \RuntimeException('KB_CHANGE_AUTHOR_CONFLICT');
			}

			// The new primary author must not also be listed as a co-author.
			$this->db->sql_query('DELETE FROM ' . $this->co_authors_table . '
				WHERE article_id = ' . $article_id . '
				AND author_type = ' . $author_type . '
				AND author_ref_id = ' . $author_id);
			$log_id = $this->log->add('admin', (int) $this->user->data['user_id'], $this->user->ip,
				'LOG_LIBRARY_CHANGED_AUTHOR', time(), [
					$article['article_title'],
					$article['author'] . ' (' . (int) $article['author_type'] . ':' . (int) $article['author_id'] . ')',
					$author_name . ' (' . $author_type . ':' . $author_id . ')',
				]);
			if (!$log_id)
			{
				throw new \RuntimeException('KB_CHANGE_AUTHOR_LOG_FAILED');
			}
			$this->db->sql_transaction('commit');
		}
		catch (\Throwable $error)
		{
			$this->db->sql_transaction('rollback');
			throw $error;
		}

		// Author filters are cached separately from the full-text word index.
		// Invalidate KB results only; their word matches need no rebuilding.
		$result = $this->db->sql_query('SELECT search_key FROM ' . $this->search_results_table);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$this->cache->destroy('_kb_search_results_' . $row['search_key']);
		}
		$this->db->sql_freeresult($result);
		return true;
	}
}
