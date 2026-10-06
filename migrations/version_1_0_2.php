<?php

/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2017, Sheer
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\migrations;

class version_1_0_2 extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return ['\phpbbmodders\knowledgebase\migrations\version_1_0_1'];
	}

	public function effectively_installed()
	{
		// kb_version stays in the config on purpose: this check reads it, so a
		// board that already ran the original sheer/knowledgebase package skips
		// the steps that package did (see version_2_0_20 and
		// docs/upgrading-from-original.md).
		return
			(isset($this->config['knowledge_base_version']) &&
				version_compare($this->config['knowledge_base_version'], '1.0.2', '>=')) ||
			(isset($this->config['kb_version']) &&
				version_compare($this->config['kb_version'], '2.0.0', '>='));
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'kb_articles' => [
					'display_order' => ['UINT', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'kb_articles' => [
					'display_order',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			// Update configs
			['config.update', ['knowledge_base_version', '1.0.2']],
			['custom', [[$this, 'set_display_order']]],
		];
	}

	/**
	 * Number the articles of every category 1, 2, 3... in the order they were
	 * submitted. One query reads all the articles, and the new positions are
	 * written back in batches, so the number of queries stays small however
	 * many articles there are.
	 */
	public function set_display_order()
	{
		$sql = 'SELECT a.article_id, a.article_category_id
			FROM ' . $this->table_prefix . 'kb_articles a, ' . $this->table_prefix . 'kb_categories c
			WHERE a.article_category_id = c.category_id
			ORDER BY a.article_category_id ASC, a.article_id ASC';
		$result = $this->db->sql_query($sql);
		$position = $orders = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$category_id = (int) $row['article_category_id'];
			$position[$category_id] = ($position[$category_id] ?? 0) + 1;
			$orders[(int) $row['article_id']] = $position[$category_id];
		}
		$this->db->sql_freeresult($result);

		foreach (array_chunk($orders, 500, true) as $batch)
		{
			$cases = '';
			foreach ($batch as $article_id => $order)
			{
				$cases .= ' WHEN ' . $article_id . ' THEN ' . $order;
			}

			$sql = 'UPDATE ' . $this->table_prefix . 'kb_articles
				SET display_order = CASE article_id' . $cases . ' END
				WHERE ' . $this->db->sql_in_set('article_id', array_keys($batch));
			$this->db->sql_query($sql);
		}
	}
}
