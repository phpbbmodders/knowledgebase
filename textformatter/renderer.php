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

/**
 * The article text renderer: phpBB's own, with configure_user() made independent
 * of the order of its arguments.
 *
 * phpBB 3.3.x declares configure_user(user, config, auth) and phpBB 4.0 declares
 * configure_user(auth, config, user), and the service definition has to name the
 * arguments in one order. This class takes the three in any order and hands them
 * to the parent in the order the running phpBB declares, found from the parent's
 * own parameter types, so one service definition works on both.
 */
class renderer extends \phpbb\textformatter\s9e\renderer
{
	/**
	 * @param object $first  One of \phpbb\user, \phpbb\config\config, \phpbb\auth\auth
	 * @param object $second Another of them
	 * @param object $third  The last of them
	 * @return void
	 */
	public function configure_user($first, $second, $third): void
	{
		$given = [$first, $second, $third];
		$ordered = [];
		foreach ((new \ReflectionMethod(parent::class, 'configure_user'))->getParameters() as $parameter)
		{
			$type = $parameter->getType();
			$wanted = $type instanceof \ReflectionNamedType ? $type->getName() : null;
			foreach ($given as $candidate)
			{
				if ($wanted !== null && $candidate instanceof $wanted)
				{
					$ordered[] = $candidate;
					break;
				}
			}
		}

		parent::configure_user(...$ordered);
	}
}
