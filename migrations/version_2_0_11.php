<?php

/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\migrations;

class version_2_0_11 extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return [
			'\phpbbmodders\knowledgebase\migrations\version_2_0_10',
		];
	}

	public function effectively_installed()
	{
		return
			(isset($this->config['kb_version']) &&
				version_compare($this->config['kb_version'], '2.0.11', '>='));
	}

	public function update_schema()
	{
		return [];
	}

	public function revert_schema()
	{
		return [];
	}

	public function update_data()
	{
		return [
			// Default off - preserves the existing opt-in-via-UCP
			// behaviour for every install already running this feature.
			['config.add', ['kb_require_author_pm', 0]],
			['config.update', ['kb_version', '2.0.11']],
		];
	}
}
