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
use phpbb\db\driver\driver_interface;
use phpbb\db\migration\exception as migration_exception;
use phpbb\db\tools\tools_interface;

/**
 * Moves a board's data from the kinerity/knowledgebase extension into this one.
 *
 * kinerity/knowledgebase uses the table names kb_articles and kb_categories
 * too, with different columns, so its tables are first copied to backup tables
 * (kb_kinerity_*) and dropped, before this extension creates its own
 * (set_aside(), run by migration kinerity_tables_set_aside ahead of
 * version_1_0_0). Once this extension's tables exist, the categories and
 * articles are converted from the backup tables in small batches
 * (import_categories(), import_articles()), and finish() fixes the counters,
 * carries over the board-wide permissions and removes what kinerity registered
 * on the board. The backup tables stay as they are, as a backup.
 *
 * Each backup row records the id it was given in this extension (kb_new_id),
 * so an import that stops part way carries on where it stopped.
 */
class kinerity_import
{
	/** The package this class converts from. */
	public const ORIGINAL_EXTENSION = 'kinerity/knowledgebase';

	/** Categories converted per call of import_categories(). */
	public const CATEGORY_BATCH = 200;

	/** Articles converted per call of import_articles(). */
	public const ARTICLE_BATCH = 500;

	/** Width of the category_details and article_description columns. */
	public const TEXT_COLUMN_LENGTH = 255;

	/** Largest value the number_articles column (USINT) holds. */
	public const MAX_ARTICLE_COUNT = 65535;

	/** Name of the category made for articles kinerity filed in no category. */
	public const FALLBACK_CATEGORY_NAME = 'Imported articles without a category';

	/** kinerity article_visibility values (kinerity\knowledgebase\constants). */
	public const KINERITY_DISAPPROVED = 0;
	public const KINERITY_APPROVED = 1;
	public const KINERITY_DELETED = 2;
	public const KINERITY_DENIED = 3;

	/**
	 * kinerity's board-wide permissions and the per-category Knowledge Base
	 * permission each one becomes, on every imported category.
	 */
	public const CATEGORY_PERMISSIONS = [
		'u_kb_post'      => 'kb_u_add',
		'u_kb_noapprove' => 'kb_u_add_noapprove',
		'u_kb_edit'      => 'kb_u_edit',
		'u_kb_delete'    => 'kb_u_delete',
		'm_kb_edit'      => 'kb_m_edit',
		'm_kb_delete'    => 'kb_m_delete',
		'm_kb_approve'   => 'kb_m_approve',
		'm_kb_chgposter' => 'kb_m_change_author',
	];

	/** kinerity permissions that become this extension's board-wide permissions. */
	public const GLOBAL_PERMISSIONS = [
		'u_kb_read'   => 'u_kb_view',
		'a_kb_manage' => 'a_manage_kb',
	];

	/** Every permission kinerity added, removed once the import is done. */
	public const KINERITY_PERMISSIONS = [
		'u_kb_read', 'u_kb_post', 'u_kb_edit', 'u_kb_delete', 'u_kb_noapprove',
		'm_kb_edit', 'm_kb_delete', 'm_kb_approve', 'm_kb_chgposter',
		'a_kb_manage',
	];

	/** Board settings kinerity added. None has an equivalent here. */
	public const KINERITY_CONFIG = ['knowledgebase_enable', 'knowledgebase_header_link', 'knowledgebase_font_icon'];

	/** Notification types kinerity registered. */
	public const KINERITY_NOTIFICATION_TYPES = [
		'kinerity.knowledgebase.notification.type.approve_article',
		'kinerity.knowledgebase.notification.type.article_in_queue',
		'kinerity.knowledgebase.notification.type.delete_article',
		'kinerity.knowledgebase.notification.type.deny_article',
		'kinerity.knowledgebase.notification.type.disapprove_article',
	];

	/**
	 * kb_new_id of a backup row a request has claimed but not converted yet,
	 * never a real id. Only ever seen inside that request's transaction.
	 */
	protected const CLAIMED = -1;

	/** phpBB's ACL_YES and ACL_NEVER, without needing phpBB's constants loaded. */
	protected const ACL_YES = 1;
	protected const ACL_NEVER = 0;

	protected driver_interface $db;
	protected tools_interface $db_tools;
	protected string $table_prefix;

	/**
	 * @param driver_interface $db
	 * @param tools_interface  $db_tools
	 * @param string           $table_prefix The board's table prefix, such as phpbb_
	 */
	public function __construct(driver_interface $db, tools_interface $db_tools, string $table_prefix)
	{
		$this->db = $db;
		$this->db_tools = $db_tools;
		$this->table_prefix = $table_prefix;
	}

	/**
	 * Table names: kinerity's own, and the backup copy each is moved to.
	 *
	 * @return array<string, array{original: string, backup: string}>
	 */
	public function tables(): array
	{
		return [
			'articles'         => ['original' => $this->table_prefix . 'kb_articles', 'backup' => $this->table_prefix . 'kb_kinerity_articles'],
			'categories'       => ['original' => $this->table_prefix . 'kb_categories', 'backup' => $this->table_prefix . 'kb_kinerity_categories'],
			'article_category' => ['original' => $this->table_prefix . 'kb_article_category', 'backup' => $this->table_prefix . 'kb_kinerity_article_category'],
		];
	}

	/**
	 * Whether a kinerity table, with kinerity's layout, is on the board.
	 * kb_articles and kb_categories are recognised by a column only kinerity
	 * has, so this extension's own tables of the same name never match.
	 *
	 * @param string $key articles, categories or article_category
	 * @return bool
	 */
	public function original_table_present(string $key): bool
	{
		$table = $this->tables()[$key]['original'];

		if (!$this->db_tools->sql_table_exists($table))
		{
			return false;
		}

		switch ($key)
		{
			case 'articles':
				return $this->db_tools->sql_column_exists($table, 'article_poster_id');

			case 'categories':
				return $this->db_tools->sql_column_exists($table, 'category_description');

			default:
				return true;
		}
	}

	/**
	 * Whether this board has kinerity data waiting in, or already converted
	 * from, the backup tables.
	 *
	 * @return bool
	 */
	public function has_backup(): bool
	{
		return $this->db_tools->sql_table_exists($this->tables()['articles']['backup'])
			|| $this->db_tools->sql_table_exists($this->tables()['categories']['backup']);
	}

	/**
	 * Copy each kinerity table to its backup table, check every row arrived,
	 * and only then drop the original, so this extension can create its own
	 * tables of the same name. A table already moved is left alone; on a board
	 * that never ran kinerity nothing happens.
	 *
	 * @throws migration_exception When a copy does not hold every row; the original is kept
	 */
	public function set_aside(): void
	{
		foreach ($this->tables() as $key => $names)
		{
			if (!$this->original_table_present($key))
			{
				continue;
			}

			$columns = $this->backup_columns($key);
			if (!$this->db_tools->sql_table_exists($names['backup']))
			{
				$this->db_tools->sql_create_table($names['backup'], $this->backup_schema($key));
			}

			// Leftovers of an earlier attempt that stopped before the drop
			$this->db->sql_query('DELETE FROM ' . $names['backup']);

			// kinerity 1.0.x has no article_edit_name column yet
			$select = [];
			foreach ($columns as $column)
			{
				$select[] = $this->db_tools->sql_column_exists($names['original'], $column) ? $column : "''";
			}

			$this->db->sql_query('INSERT INTO ' . $names['backup'] . ' (' . implode(', ', $columns) . ')
				SELECT ' . implode(', ', $select) . '
				FROM ' . $names['original']);

			$original_rows = $this->count_rows($names['original']);
			$backup_rows = $this->count_rows($names['backup']);
			if ($original_rows !== $backup_rows)
			{
				throw new migration_exception('The Knowledge Base copied ' . $backup_rows . ' of the ' . $original_rows . ' rows of ' . $names['original'] . ' into ' . $names['backup'] . '. Nothing was removed; enable the extension again to retry.');
			}

			$this->db_tools->sql_table_drop($names['original']);
			$this->drop_leftover_sequence($names['original']);
		}
	}

	/**
	 * On PostgreSQL, phpBB 3.3 gives an auto-increment column a sequence named
	 * after its table that dropping the table does not always remove (phpBB
	 * 4.0's database tools leave it). This extension's table of the same name
	 * would then fail to create its own, so the dropped table's goes too.
	 *
	 * @param string $table A table that has just been dropped
	 * @return void
	 */
	protected function drop_leftover_sequence(string $table): void
	{
		if ($this->db->get_sql_layer() === 'postgres')
		{
			$this->db->sql_query('DROP SEQUENCE IF EXISTS ' . $table . '_seq');
		}
	}

	/**
	 * Convert the next batch of kinerity categories, parents before children,
	 * with the permissions kinerity granted board-wide.
	 *
	 * @param array|mixed $state What the previous call returned (anything else on the first call)
	 * @return array|true State for the next call, or true when every category is converted
	 */
	public function import_categories($state)
	{
		$names = $this->tables()['categories'];
		if (!$this->db_tools->sql_table_exists($names['backup']))
		{
			return true;
		}

		$state = is_array($state) ? $state : [];
		if (!isset($state['offset']))
		{
			// This extension's tables are new when the import runs, so this
			// is 0; a board that already has categories keeps them first.
			$state['offset'] = $this->max_value($this->table_prefix . 'kb_categories', 'right_id');
		}

		// The whole tree (ids and bounds only) to find each category's parent
		$sql = 'SELECT category_id, left_id, right_id, kb_new_id
			FROM ' . $names['backup'] . '
			ORDER BY left_id ASC, category_id ASC';
		$result = $this->db->sql_query($sql);
		$tree = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		$parents = $new_ids = $pending = [];
		$stack = [];
		foreach ($tree as $row)
		{
			$id = (int) $row['category_id'];
			while ($stack && (int) end($stack)['right_id'] < (int) $row['left_id'])
			{
				array_pop($stack);
			}
			$parents[$id] = $stack ? (int) end($stack)['category_id'] : 0;
			$stack[] = $row;

			$new_ids[$id] = (int) $row['kb_new_id'];
			if (!$new_ids[$id] && count($pending) < static::CATEGORY_BATCH)
			{
				$pending[] = $id;
			}
		}

		if (!$pending)
		{
			return true;
		}

		$sql = 'SELECT *
			FROM ' . $names['backup'] . '
			WHERE ' . $this->db->sql_in_set('category_id', $pending);
		$result = $this->db->sql_query($sql);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['category_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		$grants = $this->category_grants();

		$this->db->sql_transaction('begin');
		foreach ($pending as $old_id)
		{
			// Another request converting the same batch (an enable retried while
			// the first was still running) has it already
			if (!$this->claim($names['backup'], 'category_id', $old_id))
			{
				continue;
			}

			$row = $rows[$old_id];
			$parent_old_id = $parents[$old_id];

			$sql_ary = [
				'parent_id'        => $parent_old_id ? (int) ($new_ids[$parent_old_id] ?? 0) : 0,
				'left_id'          => (int) $row['left_id'] + $state['offset'],
				'right_id'         => (int) $row['right_id'] + $state['offset'],
				'category_parents' => '',
				'category_name'    => self::fit_escaped((string) $row['category_name'], self::TEXT_COLUMN_LENGTH),
				'category_details' => self::category_details((string) $row['category_description']),
				'number_articles'  => 0,
			];
			$this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'kb_categories ' . $this->db->sql_build_array('INSERT', $sql_ary));
			$new_id = (int) $this->db->sql_nextid();
			$new_ids[$old_id] = $new_id;

			$this->insert_category_permissions($new_id, $grants);

			$this->db->sql_query('UPDATE ' . $names['backup'] . '
				SET kb_new_id = ' . $new_id . '
				WHERE category_id = ' . $old_id);
		}
		$this->db->sql_transaction('commit');

		return $state;
	}

	/**
	 * Convert the next batch of kinerity articles. An article in several
	 * kinerity categories goes in the one with the lowest id and is
	 * cross-listed in the others.
	 *
	 * @param array|mixed $state What the previous call returned (anything else on the first call)
	 * @return array|true State for the next call, or true when every article is converted
	 */
	public function import_articles($state)
	{
		$names = $this->tables();
		if (!$this->db_tools->sql_table_exists($names['articles']['backup']))
		{
			return true;
		}

		$state = is_array($state) ? $state : [];

		$sql = 'SELECT *
			FROM ' . $names['articles']['backup'] . '
			WHERE kb_new_id = 0
			ORDER BY article_id ASC';
		$result = $this->db->sql_query_limit($sql, static::ARTICLE_BATCH);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		if (!$rows)
		{
			return true;
		}

		$category_map = $this->category_map();
		$article_ids = array_map('intval', array_column($rows, 'article_id'));

		$links = [];
		if ($this->db_tools->sql_table_exists($names['article_category']['backup']))
		{
			$sql = 'SELECT article_id, category_id
				FROM ' . $names['article_category']['backup'] . '
				WHERE ' . $this->db->sql_in_set('article_id', $article_ids) . '
				ORDER BY category_id ASC';
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$new_category = $category_map[(int) $row['category_id']] ?? 0;
				// Links to a category kinerity no longer has are dropped
				if ($new_category)
				{
					$links[(int) $row['article_id']][] = $new_category;
				}
			}
			$this->db->sql_freeresult($result);
		}

		$this->db->sql_transaction('begin');
		foreach ($rows as $row)
		{
			$old_id = (int) $row['article_id'];

			// Another request converting the same batch (an enable retried while
			// the first was still running) has it already
			if (!$this->claim($names['articles']['backup'], 'article_id', $old_id))
			{
				continue;
			}

			$categories = array_values(array_unique($links[$old_id] ?? []));

			if (!$categories)
			{
				if (empty($state['fallback_category']))
				{
					$state['fallback_category'] = $this->fallback_category();
				}
				$categories = [$state['fallback_category']];
			}

			[$approved, $status] = self::map_visibility((int) $row['article_visibility']);

			$sql_ary = [
				'article_category_id' => $categories[0],
				'approved'            => $approved,
				'status'              => $status,
				'article_title'       => self::fit_escaped((string) $row['article_title'], self::TEXT_COLUMN_LENGTH),
				'article_description' => self::fit_escaped((string) $row['article_description'], self::TEXT_COLUMN_LENGTH),
				'article_date'        => (int) $row['article_time'],
				'edit_date'           => (int) $row['article_edit_time'],
				'author_id'           => (int) $row['article_poster_id'],
				'author'              => (string) $row['article_poster_name'],
				'author_type'         => functions_kb::AUTHOR_TYPE_USER,
				// Parsed (XML) text needs no bitfield; this table has none since version_1_0_3
				'bbcode_uid'          => substr((string) $row['bbcode_uid'], 0, 10),
				'article_body'        => (string) $row['article_text'],
				'views'               => (int) $row['article_views'],
			];
			$this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'kb_articles ' . $this->db->sql_build_array('INSERT', $sql_ary));
			$new_id = (int) $this->db->sql_nextid();

			$cross_listed = [];
			foreach (array_slice($categories, 1) as $category_id)
			{
				$cross_listed[] = ['article_id' => $new_id, 'category_id' => $category_id];
			}
			if ($cross_listed)
			{
				$this->db->sql_multi_insert($this->table_prefix . 'kb_article_categories', $cross_listed);
			}

			$this->db->sql_query('UPDATE ' . $names['articles']['backup'] . '
				SET kb_new_id = ' . $new_id . '
				WHERE article_id = ' . $old_id);
		}
		$this->db->sql_transaction('commit');

		return $state;
	}

	/**
	 * Mark one backup row as being converted by this request. A request that
	 * reaches the same row later waits for this transaction and then finds
	 * it taken, so no row is converted twice.
	 *
	 * @param string $table  Backup table
	 * @param string $column Its id column
	 * @param int    $id
	 * @return bool Whether this request got the row
	 */
	protected function claim(string $table, string $column, int $id): bool
	{
		$this->db->sql_query('UPDATE ' . $table . '
			SET kb_new_id = ' . self::CLAIMED . '
			WHERE ' . $column . ' = ' . $id . '
				AND kb_new_id = 0');

		return $this->db->sql_affectedrows() === 1;
	}

	/**
	 * After the categories and articles: set each category's article count,
	 * carry kinerity's board-wide read and admin permissions over to this
	 * extension's, and remove kinerity's settings, notification types and
	 * extension entry. kinerity's permissions and ACP modules are removed by
	 * the migration with phpBB's own tools afterwards.
	 *
	 * kinerity's migration records are kept on purpose: without them,
	 * enabling kinerity again would re-run its first migration, which fails on
	 * this extension's tables and then drops them when phpBB reverts it.
	 *
	 * @param config $config
	 * @return void
	 */
	public function finish(config $config): void
	{
		if (!$this->has_backup())
		{
			return;
		}

		$this->update_article_counts();
		$this->copy_global_permissions();

		foreach (self::KINERITY_CONFIG as $name)
		{
			if (isset($config[$name]))
			{
				$config->delete($name);
			}
		}

		$this->remove_notification_types();

		$this->db->sql_query('DELETE FROM ' . $this->table_prefix . "ext
			WHERE ext_name = '" . $this->db->sql_escape(self::ORIGINAL_EXTENSION) . "'");
	}

	/**
	 * Forget which backup rows were converted, so purging this extension and
	 * enabling it again imports them again into its new tables.
	 *
	 * @return void
	 */
	public function reset_import_markers(): void
	{
		foreach (['articles', 'categories'] as $key)
		{
			$backup = $this->tables()[$key]['backup'];
			if ($this->db_tools->sql_table_exists($backup))
			{
				$this->db->sql_query('UPDATE ' . $backup . ' SET kb_new_id = 0');
			}
		}
	}

	/**
	 * kinerity's article_visibility as this extension's approved flag and status.
	 * Articles waiting for approval go to the queue; kinerity's deleted and
	 * denied articles are kept, deactivated, for moderators to deal with.
	 *
	 * @param int $visibility
	 * @return array{0: int, 1: int} approved, status
	 */
	public static function map_visibility(int $visibility): array
	{
		switch ($visibility)
		{
			case self::KINERITY_APPROVED:
				return [1, functions_kb::STATUS_ACTIVE];

			case self::KINERITY_DISAPPROVED:
				return [0, functions_kb::STATUS_NEW];

			default:
				return [0, functions_kb::STATUS_INACTIVE];
		}
	}

	/**
	 * Text phpBB stored with BBCode, as plain text on one line: the parsed
	 * (XML) form phpBB 3.2 and later stores, or the older BBCode-with-uid form.
	 *
	 * @param string $stored
	 * @return string Plain text, not HTML-escaped
	 */
	public static function plain_text(string $stored): string
	{
		if ($stored === '')
		{
			return '';
		}

		if (preg_match('#^<[rt][ />]#', $stored) && class_exists(\s9e\TextFormatter\Utils::class))
		{
			$text = \s9e\TextFormatter\Utils::removeFormatting($stored);
		}
		else
		{
			// Older storage: [b:uid] tags, smiley and link comments, escaped text
			$text = preg_replace('#<!-- [a-z]+ -->#', '', $stored);
			$text = preg_replace('#<img [^>]*alt="([^"]*)"[^>]*>#', '$1', $text);
			$text = strip_tags($text);
			$text = preg_replace('#\[/?[a-z0-9*+\-]+(?:=[^\]]*)?(?::[a-z])?:[a-z0-9]{5,8}\]#i', '', $text);
			$text = htmlspecialchars_decode($text, ENT_QUOTES);
		}

		return trim(preg_replace('#\s+#u', ' ', $text));
	}

	/**
	 * A kinerity category description (stored with BBCode) as this
	 * extension's category details: plain text, HTML-escaped like user input,
	 * at most 255 characters.
	 *
	 * @param string $stored
	 * @return string
	 */
	public static function category_details(string $stored): string
	{
		return self::fit_escaped(self::encode_supplementary(htmlspecialchars(self::plain_text($stored), ENT_COMPAT, 'UTF-8')), self::TEXT_COLUMN_LENGTH);
	}

	/**
	 * Characters outside the Basic Multilingual Plane (emoji and the like) as
	 * numeric entities, the way phpBB stores them: phpBB 3.3's MySQL tables
	 * use a character set that cannot hold them. Turning stored text into
	 * plain text decodes those entities, so they are encoded again.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function encode_supplementary(string $text): string
	{
		return preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', fn($match) => '&#' . mb_ord($match[0], 'UTF-8') . ';', $text);
	}

	/**
	 * Shorten HTML-escaped text to fit a column, cutting the text itself
	 * rather than an entity, and ending a shortened text with an ellipsis.
	 *
	 * @param string $escaped Text as phpBB stores user input (HTML-escaped)
	 * @param int    $length  Column width in characters
	 * @return string
	 */
	public static function fit_escaped(string $escaped, int $length): string
	{
		if (mb_strlen($escaped, 'UTF-8') <= $length)
		{
			return $escaped;
		}

		// Each character can grow to several when escaped (& is &amp;), so
		// shorten the plain text until its escaped form fits
		$plain = htmlspecialchars_decode($escaped, ENT_COMPAT);
		for ($keep = min(mb_strlen($plain, 'UTF-8'), $length) - 1; $keep > 0; $keep--)
		{
			// Never end on part of an entity such as &#128640; (characters
			// phpBB stores as entities stay entities in $plain)
			$cut = preg_replace('/&#?[0-9a-z]*$/i', '', mb_substr($plain, 0, $keep, 'UTF-8'));
			$fitted = htmlspecialchars(rtrim($cut), ENT_COMPAT, 'UTF-8') . "\u{2026}";
			if (mb_strlen($fitted, 'UTF-8') <= $length)
			{
				return $fitted;
			}
		}

		return "\u{2026}";
	}

	/**
	 * Backup table columns copied from kinerity, in order.
	 *
	 * @param string $key
	 * @return string[]
	 */
	protected function backup_columns(string $key): array
	{
		return array_keys(array_diff_key($this->backup_schema($key)['COLUMNS'], ['kb_new_id' => true]));
	}

	/**
	 * Backup table layout: kinerity's columns (1.2.1), without auto-increment
	 * so the original ids copy as they are, plus kb_new_id, signed so a
	 * claimed row can be marked -1 (see claim()).
	 *
	 * @param string $key
	 * @return array
	 */
	protected function backup_schema(string $key): array
	{
		switch ($key)
		{
			case 'articles':
				return [
					'COLUMNS'     => [
						'article_id'            => ['UINT', 0],
						'article_poster_id'     => ['UINT', 0],
						'article_poster_name'   => ['VCHAR:255', ''],
						'article_poster_colour' => ['VCHAR:6', ''],
						'article_time'          => ['TIMESTAMP', 0],
						'article_edit_time'     => ['TIMESTAMP', 0],
						'enable_bbcode'         => ['BOOL', 1],
						'enable_smilies'        => ['BOOL', 1],
						'enable_magic_url'      => ['BOOL', 1],
						'article_title'         => ['VCHAR:255', ''],
						'article_description'   => ['MTEXT_UNI', ''],
						'article_text'          => ['MTEXT_UNI', ''],
						'bbcode_bitfield'       => ['VCHAR:255', ''],
						'bbcode_uid'            => ['VCHAR:8', ''],
						'article_visibility'    => ['TINT:3', 0],
						'article_views'         => ['UINT', 0],
						'article_edit_name'     => ['VCHAR:255', ''],
						'kb_new_id'             => ['BINT', 0],
					],
					'PRIMARY_KEY' => 'article_id',
					'KEYS'        => [
						'kb_new_id' => ['INDEX', 'kb_new_id'],
					],
				];

			case 'categories':
				return [
					'COLUMNS'     => [
						'category_id'          => ['UINT', 0],
						'left_id'              => ['UINT', 0],
						'right_id'             => ['UINT', 0],
						'category_name'        => ['VCHAR:255', ''],
						'category_description' => ['TEXT_UNI', ''],
						'bbcode_bitfield'      => ['VCHAR:255', ''],
						'bbcode_options'       => ['TIMESTAMP', 7],
						'bbcode_uid'           => ['VCHAR:8', ''],
						'kb_new_id'            => ['BINT', 0],
					],
					'PRIMARY_KEY' => 'category_id',
				];

			default:
				return [
					'COLUMNS' => [
						'category_id' => ['UINT', 0],
						'article_id'  => ['UINT', 0],
					],
					'KEYS'    => [
						'article_id' => ['INDEX', 'article_id'],
					],
				];
		}
	}

	/**
	 * kinerity category id => this extension's category id, for every
	 * category converted so far.
	 *
	 * @return array<int, int>
	 */
	protected function category_map(): array
	{
		$map = [];
		$backup = $this->tables()['categories']['backup'];
		if (!$this->db_tools->sql_table_exists($backup))
		{
			return $map;
		}

		$sql = 'SELECT category_id, kb_new_id
			FROM ' . $backup . '
			WHERE kb_new_id <> 0';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$map[(int) $row['category_id']] = (int) $row['kb_new_id'];
		}
		$this->db->sql_freeresult($result);

		return $map;
	}

	/**
	 * Make a top-level category, after every other, for kinerity articles
	 * that were in no category.
	 *
	 * @return int The new category's id
	 */
	protected function fallback_category(): int
	{
		// One already made by an earlier or a parallel request is used again
		$result = $this->db->sql_query_limit('SELECT category_id
			FROM ' . $this->table_prefix . "kb_categories
			WHERE category_name = '" . $this->db->sql_escape(self::FALLBACK_CATEGORY_NAME) . "'
				AND parent_id = 0
			ORDER BY category_id", 1);
		$existing = (int) $this->db->sql_fetchfield('category_id');
		$this->db->sql_freeresult($result);
		if ($existing)
		{
			return $existing;
		}

		$right = $this->max_value($this->table_prefix . 'kb_categories', 'right_id');

		$sql_ary = [
			'parent_id'        => 0,
			'left_id'          => $right + 1,
			'right_id'         => $right + 2,
			'category_parents' => '',
			'category_name'    => self::FALLBACK_CATEGORY_NAME,
			'category_details' => '',
			'number_articles'  => 0,
		];
		$this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'kb_categories ' . $this->db->sql_build_array('INSERT', $sql_ary));
		$category_id = (int) $this->db->sql_nextid();

		$this->insert_category_permissions($category_id, $this->category_grants());

		return $category_id;
	}

	/**
	 * What kinerity's board-wide permissions granted each group and user,
	 * directly or through a role, as this extension's per-category options.
	 * A Never anywhere wins over a Yes, as in phpBB.
	 *
	 * @return array{groups: array<int, array<int, int>>, users: array<int, array<int, int>>}
	 *         auth_option_id => ACL_YES/ACL_NEVER for each group and user id
	 */
	protected function category_grants(): array
	{
		$options = $this->kb_option_ids();
		$settings = $this->kinerity_settings(array_keys(self::CATEGORY_PERMISSIONS));

		$grants = ['groups' => [], 'users' => []];
		foreach ($settings as $type => $holders)
		{
			foreach ($holders as $holder_id => $by_option)
			{
				foreach ($by_option as $kinerity_option => $setting)
				{
					$option_id = $options[self::CATEGORY_PERMISSIONS[$kinerity_option]] ?? 0;
					if ($option_id)
					{
						$grants[$type][$holder_id][$option_id] = $setting;
					}
				}
			}
		}

		return $grants;
	}

	/**
	 * The Yes or Never each group and user holds board-wide for the given
	 * kinerity permissions, combining direct settings and roles.
	 *
	 * @param string[] $kinerity_options
	 * @return array{groups: array<int, array<string, int>>, users: array<int, array<string, int>>}
	 */
	protected function kinerity_settings(array $kinerity_options): array
	{
		$settings = ['groups' => [], 'users' => []];

		$option_names = $this->acl_option_ids($kinerity_options);
		if (!$option_names)
		{
			return $settings;
		}
		$option_names = array_flip($option_names);

		$role_settings = [];
		$sql = 'SELECT role_id, auth_option_id, auth_setting
			FROM ' . $this->table_prefix . 'acl_roles_data
			WHERE ' . $this->db->sql_in_set('auth_option_id', array_keys($option_names));
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$role_settings[(int) $row['role_id']][$option_names[(int) $row['auth_option_id']]] = (int) $row['auth_setting'];
		}
		$this->db->sql_freeresult($result);

		foreach (['groups' => 'group_id', 'users' => 'user_id'] as $type => $holder_column)
		{
			$sql = 'SELECT ' . $holder_column . ' AS holder_id, auth_option_id, auth_role_id, auth_setting
				FROM ' . $this->table_prefix . 'acl_' . $type . '
				WHERE forum_id = 0
					AND (' . $this->db->sql_in_set('auth_option_id', array_keys($option_names)) . '
						OR auth_role_id <> 0)';
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$holder_id = (int) $row['holder_id'];
				$found = [];
				if ((int) $row['auth_role_id'])
				{
					$found = $role_settings[(int) $row['auth_role_id']] ?? [];
				}
				else if (isset($option_names[(int) $row['auth_option_id']]))
				{
					$found = [$option_names[(int) $row['auth_option_id']] => (int) $row['auth_setting']];
				}

				foreach ($found as $option => $setting)
				{
					if ($setting === self::ACL_NEVER)
					{
						$settings[$type][$holder_id][$option] = self::ACL_NEVER;
					}
					else if ($setting === self::ACL_YES && !isset($settings[$type][$holder_id][$option]))
					{
						$settings[$type][$holder_id][$option] = self::ACL_YES;
					}
				}
			}
			$this->db->sql_freeresult($result);
		}

		return $settings;
	}

	/**
	 * Write a category's group and user permission rows.
	 *
	 * @param int   $category_id
	 * @param array $grants From category_grants()
	 * @return void
	 */
	protected function insert_category_permissions(int $category_id, array $grants): void
	{
		foreach (['groups' => 'group_id', 'users' => 'user_id'] as $type => $holder_column)
		{
			$rows = [];
			foreach ($grants[$type] as $holder_id => $options)
			{
				foreach ($options as $option_id => $setting)
				{
					$rows[] = [
						$holder_column   => (int) $holder_id,
						'category_id'    => $category_id,
						'auth_option_id' => (int) $option_id,
						'auth_setting'   => (int) $setting,
					];
				}
			}

			if ($rows)
			{
				$this->db->sql_multi_insert($this->table_prefix . 'kb_' . $type, $rows);
			}
		}
	}

	/**
	 * Give everyone who had kinerity's read or admin permission (directly or
	 * in a role) the same setting on this extension's u_kb_view and
	 * a_manage_kb, where they have no setting for it yet.
	 *
	 * @return void
	 */
	protected function copy_global_permissions(): void
	{
		$ids = $this->acl_option_ids(array_merge(array_keys(self::GLOBAL_PERMISSIONS), array_values(self::GLOBAL_PERMISSIONS)));
		$changed = false;

		foreach (self::GLOBAL_PERMISSIONS as $from => $to)
		{
			if (empty($ids[$from]) || empty($ids[$to]))
			{
				continue;
			}

			foreach (['acl_groups' => 'group_id', 'acl_users' => 'user_id', 'acl_roles_data' => 'role_id'] as $table => $holder_column)
			{
				$is_role_table = $table === 'acl_roles_data';
				$scope = $is_role_table ? '' : ' AND forum_id = 0 AND auth_role_id = 0';

				$existing = [];
				$sql = 'SELECT ' . $holder_column . ' AS holder_id
					FROM ' . $this->table_prefix . $table . '
					WHERE auth_option_id = ' . (int) $ids[$to] . $scope;
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					$existing[(int) $row['holder_id']] = true;
				}
				$this->db->sql_freeresult($result);

				$rows = [];
				$sql = 'SELECT ' . $holder_column . ' AS holder_id, auth_setting
					FROM ' . $this->table_prefix . $table . '
					WHERE auth_option_id = ' . (int) $ids[$from] . $scope;
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					$holder_id = (int) $row['holder_id'];
					if (isset($existing[$holder_id]))
					{
						continue;
					}
					$existing[$holder_id] = true;

					$new_row = [$holder_column => $holder_id, 'auth_option_id' => (int) $ids[$to], 'auth_setting' => (int) $row['auth_setting']];
					if (!$is_role_table)
					{
						$new_row += ['forum_id' => 0, 'auth_role_id' => 0];
					}
					$rows[] = $new_row;
				}
				$this->db->sql_freeresult($result);

				if ($rows)
				{
					$this->db->sql_multi_insert($this->table_prefix . $table, $rows);
					$changed = true;
				}
			}
		}

		if ($changed)
		{
			// Every user's cached permissions are rebuilt on their next page
			$this->db->sql_query('UPDATE ' . $this->table_prefix . "users
				SET user_permissions = '',
					user_perm_from = 0");
		}
	}

	/**
	 * Set every category's article count from its articles, as the ACP's
	 * resynchronise does.
	 *
	 * @return void
	 */
	protected function update_article_counts(): void
	{
		$counts = [];
		$sql = 'SELECT article_category_id, COUNT(article_id) AS articles
			FROM ' . $this->table_prefix . 'kb_articles
			GROUP BY article_category_id';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$counts[(int) $row['article_category_id']] = (int) $row['articles'];
		}
		$this->db->sql_freeresult($result);

		$sql = 'SELECT category_id, number_articles
			FROM ' . $this->table_prefix . 'kb_categories';
		$result = $this->db->sql_query($sql);
		$categories = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		foreach ($categories as $row)
		{
			$count = min($counts[(int) $row['category_id']] ?? 0, self::MAX_ARTICLE_COUNT);
			if ($count !== (int) $row['number_articles'])
			{
				$this->db->sql_query('UPDATE ' . $this->table_prefix . 'kb_categories
					SET number_articles = ' . $count . '
					WHERE category_id = ' . (int) $row['category_id']);
			}
		}
	}

	/**
	 * Remove kinerity's notification types with the notifications and user
	 * settings that use them; its classes are gone, so phpBB could not show them.
	 *
	 * @return void
	 */
	protected function remove_notification_types(): void
	{
		$type_ids = [];
		$sql = 'SELECT notification_type_id
			FROM ' . $this->table_prefix . 'notification_types
			WHERE ' . $this->db->sql_in_set('notification_type_name', self::KINERITY_NOTIFICATION_TYPES);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$type_ids[] = (int) $row['notification_type_id'];
		}
		$this->db->sql_freeresult($result);

		if ($type_ids)
		{
			$this->db->sql_query('DELETE FROM ' . $this->table_prefix . 'notifications
				WHERE ' . $this->db->sql_in_set('notification_type_id', $type_ids));
			$this->db->sql_query('DELETE FROM ' . $this->table_prefix . 'notification_types
				WHERE ' . $this->db->sql_in_set('notification_type_id', $type_ids));
		}

		$this->db->sql_query('DELETE FROM ' . $this->table_prefix . 'user_notifications
			WHERE ' . $this->db->sql_in_set('item_type', self::KINERITY_NOTIFICATION_TYPES));
	}

	/**
	 * This extension's per-category option ids by name.
	 *
	 * @return array<string, int>
	 */
	protected function kb_option_ids(): array
	{
		$ids = [];
		$sql = 'SELECT auth_option_id, auth_option
			FROM ' . $this->table_prefix . 'kb_options';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[$row['auth_option']] = (int) $row['auth_option_id'];
		}
		$this->db->sql_freeresult($result);

		return $ids;
	}

	/**
	 * phpBB permission option ids by name, for the names that exist.
	 *
	 * @param string[] $names
	 * @return array<string, int>
	 */
	protected function acl_option_ids(array $names): array
	{
		$ids = [];
		$sql = 'SELECT auth_option_id, auth_option
			FROM ' . $this->table_prefix . 'acl_options
			WHERE ' . $this->db->sql_in_set('auth_option', $names);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[$row['auth_option']] = (int) $row['auth_option_id'];
		}
		$this->db->sql_freeresult($result);

		return $ids;
	}

	/**
	 * @param string $table
	 * @return int
	 */
	protected function count_rows(string $table): int
	{
		$result = $this->db->sql_query('SELECT COUNT(*) AS total FROM ' . $table);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $total;
	}

	/**
	 * @param string $table
	 * @param string $column
	 * @return int The column's largest value, 0 for an empty table
	 */
	protected function max_value(string $table, string $column): int
	{
		$result = $this->db->sql_query('SELECT MAX(' . $column . ') AS max_value FROM ' . $table);
		$max = (int) $this->db->sql_fetchfield('max_value');
		$this->db->sql_freeresult($result);

		return $max;
	}
}
