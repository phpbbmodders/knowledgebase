<?php

/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @copyright (c) 2017, Sheer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\notification\type;

use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\group\helper as group_helper;
use phpbb\user_loader;

class approve extends \phpbb\notification\type\base
{
	use avatar_compat;

	/**
	 * Notification option data (for outputting to the user)
	 *
	 * @var bool|array False if the service should use its default data
	 *                 Array of data (including keys 'id', 'lang', and 'group')
	 */
	public static $notification_option = [
		'lang'  => 'NOTIFICATION_TYPE_ARTICLE_APPROVE',
		'group' => 'NOTIFICATION_GROUP_MISCELLANEOUS',
	];
	/** @var helper */
	protected helper $helper;
	/** @var user_loader */
	protected user_loader $user_loader;
	/** @var group_helper */
	protected group_helper $group_helper;
	/** @var config */
	protected config $config;
	/** @var string */
	protected string $kb_options_table;
	/** @var string */
	protected string $kb_users_table;
	/** @var string */
	protected string $kb_groups_table;

	/**
	 * Get the id of the item
	 *
	 * @param array $type_data
	 */
	public static function get_item_id($type_data): int
	{
		return (int) $type_data['item_id'];
	}

	/**
	 * Get the id of the parent
	 *
	 * @param array $type_data
	 */
	public static function get_item_parent_id($type_data): int
	{
		return 0;
	}

	/**
	 * Set the controller helper
	 *
	 * @param helper $helper
	 * @return void
	 */
	public function set_controller_helper(helper $helper): void
	{
		$this->helper = $helper;
	}

	public function set_user_loader(user_loader $user_loader): void
	{
		$this->user_loader = $user_loader;
	}

	/**
	 * @param group_helper $group_helper
	 * @return void
	 */
	public function set_group_helper(group_helper $group_helper): void
	{
		$this->group_helper = $group_helper;
	}

	/**
	 * @param config $config
	 * @return void
	 */
	public function set_config(config $config): void
	{
		$this->config = $config;
	}

	/**
	 * @param string $kb_options_table
	 * @param string $kb_users_table
	 * @param string $kb_groups_table
	 * @return void
	 */
	public function set_tables(string $kb_options_table, string $kb_users_table, string $kb_groups_table): void
	{
		$this->kb_options_table = $kb_options_table;
		$this->kb_users_table = $kb_users_table;
		$this->kb_groups_table = $kb_groups_table;
	}

	/**
	 * Get notification type name
	 *
	 * @return string
	 */
	public function get_type(): string
	{
		return 'phpbbmodders.knowledgebase.notification.type.approve';
	}

	/**
	 * Is this type available to the current user (defines whether or not it will be shown in the UCP Edit notification
	 * options)
	 *
	 * @return bool True/False whether or not this is available to the user
	 */
	public function is_available(): bool
	{
		return true;
	}

	/**
	 * Find the users who want to receive notifications
	 *
	 * @param array $type_data
	 * @param array $options Options for finding users for notification
	 *
	 * @return array
	 */
	public function find_users_for_notification($type_data, $options = []): array
	{
		$options = array_merge(['ignore_users' => [],], $options);

		$users = $this->get_author_user_ids((int) $type_data['author_id'], (int) ($type_data['author_type'] ?? 0));
		return $this->check_user_notification_options($users, $options);
	}

	/**
	 * @param int $group_id
	 * @return string
	 */
	protected function get_author_group_name(int $group_id): string
	{
		$sql = 'SELECT group_name
			FROM ' . GROUPS_TABLE . '
			WHERE group_id = ' . $group_id;
		$result = $this->db->sql_query($sql);
		$group_name = $this->db->sql_fetchfield('group_name');
		$this->db->sql_freeresult($result);

		return $group_name ? $this->group_helper->get_name($group_name) : '';
	}

	/**
	 * @param int $author_id
	 * @param int $author_type 0 = user, 1 = group
	 * @return array
	 */
	protected function get_author_user_ids(int $author_id, int $author_type): array
	{
		if ($author_type !== 1)
		{
			return [$author_id];
		}

		$user_ids = [];
		$sql = 'SELECT user_id
			FROM ' . USER_GROUP_TABLE . '
			WHERE group_id = ' . $author_id . '
				AND user_pending = 0';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$user_ids[] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		return $user_ids;
	}

	/**
	 * Users needed to query before this notification can be displayed
	 *
	 * @return array Array of user_ids
	 */
	public function users_to_query(): array
	{
		return [$this->get_data('moderator')];
	}

	/**
	 * Get the user's avatar
	 */
	public function get_avatar()
	{
		return $this->user_avatar((int) $this->get_data('moderator'));
	}

	/**
	 * Get the HTML formatted title of this notification
	 *
	 * @return string
	 */
	public function get_title(): string
	{
		$username = $this->user_loader->get_username($this->get_data('moderator'), 'no_profile');
		return $this->language->lang('NOTIFICATION_ARTICLE_APPROVE', $username);
	}

	/**
	 * Get the url to this item
	 *
	 * @return string URL
	 */
	public function get_url(): string
	{
		return $this->helper->route('phpbbmodders_knowledgebase_article', [
			'k' => $this->get_data('item_id'),
		]);
	}

	/**
	 * Get email template
	 *
	 * @return string
	 */
	public function get_email_template(): string
	{
		return '@phpbbmodders_knowledgebase/article_approve';
	}

	/**
	 * Get email template variables
	 *
	 * @return array
	 */
	public function get_email_template_variables(): array
	{
		$username = ((int) $this->get_data('author_type')) === 1
			? $this->get_author_group_name((int) $this->get_data('author_id'))
			: $this->user_loader->get_username($this->get_data('author_id'), 'username');
		return [
			'USERNAME'       => htmlspecialchars_decode($username),
			'MODERATOR'      => htmlspecialchars_decode($this->user_loader->get_username($this->get_data('moderator'), 'username')),
			'ARTICLE_TITLE'  => htmlspecialchars_decode(censor_text($this->get_data('title'))),
			'U_VIEW_ARTICLE' => generate_board_url() . '/knowledgebase/article?k=' . $this->get_data('item_id'),
		];
	}

	/**
	 * Get the HTML formatted reference of the notification
	 *
	 * @return string
	 */
	public function get_reference(): string
	{
		return $this->language->lang('NOTIFICATION_REFERENCE', censor_text($this->get_data('title')));
	}

	/**
	 * Trim the user array passed down to 3 users if the array contains
	 * more than 4 users.
	 *
	 * @param array $users Array of users
	 * @return array Trimmed array of user_ids
	 */
	public function trim_user_ary(array $users): array
	{
		if (count($users) > 4)
		{
			array_splice($users, 3);
		}
		return $users;
	}

	/**
	 * Function for preparing the data for insertion in an SQL query
	 * (The service handles' insertion)
	 *
	 * @param array $type_data       Data from insert_need_approval
	 * @param array $pre_create_data Data from pre_create_insert_array()
	 *
	 * @return void Array of data ready to be inserted into the database
	 */
	public function create_insert_array($type_data, $pre_create_data = []): void
	{
		$this->set_data('author_id', $type_data['author_id']);
		$this->set_data('author_type', $type_data['author_type'] ?? 0);
		$this->set_data('title', $type_data['title']);
		$this->set_data('moderator', $type_data['moderator']);
		$this->set_data('item_id', $type_data['item_id']);
		// Empty means the moderator explicitly chose not to message the
		// author - see approve.php's KB_REASON_REQUIRED validation. Read
		// by notification\method\pm, not by the bell.
		$this->set_data('reason', $type_data['reason'] ?? '');

		parent::create_insert_array($type_data, $pre_create_data);
	}

	/**
	 * Whether the moderator wrote a reason (vs. explicitly skipping the
	 * author message) - get_data() is protected, so notification\method\pm
	 * needs a public accessor to check this before sending anything.
	 *
	 * @return string
	 */
	public function get_reason(): string
	{
		return (string) $this->get_data('reason');
	}

	/**
	 * PM subject when this notification is delivered via
	 * notification\method\pm instead of (or alongside) the bell.
	 *
	 * @return string
	 */
	public function get_pm_subject(): string
	{
		return $this->language->lang('KB_PM_APPROVE_SUBJECT', censor_text($this->get_data('title')));
	}

	/**
	 * @return string
	 */
	public function get_pm_message(): string
	{
		$moderator_name = $this->user_loader->get_username($this->get_data('moderator'), 'username');

		return $this->language->lang(
			'KB_PM_APPROVE_MESSAGE',
			$moderator_name,
			censor_text($this->get_data('title')),
			(string) $this->get_data('reason')
		);
	}

	/**
	 * @param     $auth
	 * @param int $category_id
	 * @return array
	 */
	public function check_permission($auth, int $category_id = 0): array
	{
		$sql_where = ($category_id) ? ' AND category_id = ' . $category_id : '';

		$moderators = $groups = $exclude = [];

		$sql = 'SELECT auth_option_id
			FROM ' . $this->kb_options_table . '
			WHERE auth_option LIKE \'' . $auth . '\'
			AND is_local = 1';
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$auth_option_id = $row['auth_option_id'];
		$this->db->sql_freeresult($result);

		$sql = 'SELECT user_id FROM ' . $this->kb_users_table . '
			WHERE auth_option_id = ' . (int) $auth_option_id . '
				AND auth_setting = 1
				' . $sql_where;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$moderators[] = $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		$sql = 'SELECT group_id
			FROM ' . $this->kb_groups_table . '
			WHERE auth_option_id = ' . (int) $auth_option_id . '
				AND auth_setting = 1
				' . $sql_where;
		$result = $this->db->sql_query($sql);

		while ($group_row = $this->db->sql_fetchrow($result))
		{
			$groups[] = $group_row['group_id'];
		}
		$this->db->sql_freeresult($result);

		$sql = 'SELECT user_id
			FROM ' . $this->kb_users_table . '
			WHERE auth_option_id = ' . (int) $auth_option_id . '
				AND auth_setting = 0
				' . $sql_where;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$exclude[] = $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		if (count($groups))
		{
			// USERS_TABLE.group_id is only a user's default group - a
			// moderator whose kb_m_approve group membership is secondary
			// would never match here. USER_GROUP_TABLE covers every group a
			// user actually belongs to, same as this extension's own
			// permission checks already do elsewhere.
			$sql = 'SELECT user_id
				FROM ' . USER_GROUP_TABLE . '
				WHERE user_pending = 0
					AND ' . $this->db->sql_in_set('group_id', $groups);
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				if (!in_array($row['user_id'], $exclude))
				{
					$moderators[] = $row['user_id'];
				}
			}
			$this->db->sql_freeresult($result);
		}

		if ($this->user->data['user_type'] == USER_FOUNDER)
		{
			$moderators[] = $this->user->data['user_id'];
		}
		return array_unique($moderators);
	}
}
