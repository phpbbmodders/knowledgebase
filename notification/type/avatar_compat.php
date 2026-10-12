<?php

/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\notification\type;

/**
 * The notification avatar, in the shape the running phpBB expects.
 *
 * phpBB 3.3.x passes the avatar around as an HTML string. phpBB 4.0 passes an
 * array of avatar data (the base class hands it to its avatar helper and
 * counts it), and a string there is a fatal TypeError. user_loader::get_avatar()
 * already returns the right shape for the version in use, so a notification
 * type only has to return it unchanged and, when there is no avatar to show,
 * an empty value of the right kind. The 4.0 base class is recognised by the
 * avatar helper it gained, not by a version number, so a 4.0 development build
 * is handled the same as the release.
 *
 * The class using this trait needs a $user_loader property.
 */
trait avatar_compat
{
	/**
	 * The avatar of a user.
	 *
	 * @param int $user_id
	 * @return string|array
	 */
	protected function user_avatar(int $user_id)
	{
		return $this->user_loader->get_avatar($user_id, false, true);
	}

	/**
	 * "No avatar": an empty array on phpBB 4.0, an empty string on 3.3.x.
	 *
	 * @return string|array
	 */
	protected function no_avatar()
	{
		return $this->core_wants_avatar_arrays() ? [] : '';
	}

	/** Whether the notification base class of the running phpBB handles avatars as arrays. */
	protected function core_wants_avatar_arrays(): bool
	{
		return property_exists(\phpbb\notification\type\base::class, 'avatar_helper');
	}
}
