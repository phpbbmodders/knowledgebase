<?php

/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\notification\method;

use phpbb\notification\type\type_interface;
use phpbb\user;

/**
 * Delivers a notification as an actual phpBB private message instead of
 * (or alongside) the notification bell, for the notification types that
 * support it (approve/disapprove - see SUPPORTED_TYPES). Shows up as a
 * normal "PM" column next to Notification/Email/Jabber on the UCP
 * notification options screen, same as any other method.
 *
 * Sent from whoever is actually logged in when notify() runs - correct
 * because the notification manager always calls notify() synchronously,
 * in the same request as the moderator's own action, so the acting
 * moderator is still the current user. submit_pm() itself works the
 * same way: it keys sentbox/quota bookkeeping off the global $user, not
 * off any "from" field passed in its data array.
 */
class pm extends \phpbb\notification\method\base
{
	/** @var user */
	protected user $user;

	/** @var string */
	protected string $phpbb_root_path;

	/** @var string */
	protected string $php_ext;

	protected const SUPPORTED_TYPES = [
		'phpbbmodders.knowledgebase.notification.type.approve',
		'phpbbmodders.knowledgebase.notification.type.disapprove',
	];

	public function __construct(user $user, string $phpbb_root_path, string $php_ext)
	{
		$this->user = $user;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_type()
	{
		// Must be this method's actual container service id
		// (phpbbmodders.knowledgebase.notification.method.pm, from
		// services.yml) - phpBB's notification manager keys its whole
		// method collection by service id (di\pass\collection_pass),
		// and both check_user_notification_options()'s stored
		// preferences and get_subscription_methods()'s UCP column both
		// key off whatever get_type() returns. Returning anything else
		// means this method is never actually found when the manager
		// looks it up - it silently never sends, even though the UCP
		// still shows a "PM" column and a real subscription row exists.
		return 'phpbbmodders.knowledgebase.notification.method.pm';
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param type_interface|null $notification_type
	 */
	public function is_available($notification_type = null)
	{
		return $notification_type === null || in_array($notification_type->get_type(), self::SUPPORTED_TYPES, true);
	}

	/**
	 * {@inheritdoc}
	 */
	public function notify()
	{
		if (empty($this->queue))
		{
			return;
		}

		if (!function_exists('submit_pm'))
		{
			include($this->phpbb_root_path . 'includes/functions_privmsgs.' . $this->php_ext);
		}

		/** @var type_interface $notification */
		foreach ($this->queue as $notification)
		{
			if (!in_array($notification->get_type(), self::SUPPORTED_TYPES, true)
				|| !method_exists($notification, 'get_reason')
				|| !method_exists($notification, 'get_pm_subject')
				|| !method_exists($notification, 'get_pm_message'))
			{
				continue;
			}

			// An empty reason means the moderator explicitly chose to
			// skip messaging the author - see approve.php's
			// KB_REASON_REQUIRED validation, which is the only way this
			// method's queue ever contains a notification at all.
			if ($notification->get_reason() === '')
			{
				continue;
			}

			$message = $notification->get_pm_message();
			$subject = $notification->get_pm_subject();

			$uid = $bitfield = '';
			$flags = 0;
			generate_text_for_storage($message, $uid, $bitfield, $flags, false, false, false);

			$pm_data = [
				'msg_id'                => 0,
				'from_user_id'          => (int) $this->user->data['user_id'],
				'from_user_ip'          => (string) $this->user->ip,
				'from_username'         => (string) $this->user->data['username'],
				'reply_from_root_level' => 0,
				'reply_from_msg_id'     => 0,
				'icon_id'               => 0,
				'enable_sig'            => false,
				'enable_bbcode'         => false,
				'enable_smilies'        => false,
				'enable_urls'           => false,
				'bbcode_bitfield'       => $bitfield,
				'bbcode_uid'            => $uid,
				'message'               => $message,
				'attachment_data'       => [],
				'filename_data'         => [],
				'address_list'          => ['u' => [(int) $notification->user_id => 'to']],
			];

			submit_pm('post', $subject, $pm_data);
		}

		$this->empty_queue();
	}
}
