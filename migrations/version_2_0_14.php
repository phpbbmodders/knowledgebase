<?php

/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\migrations;

class version_2_0_14 extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return [
			'\phpbbmodders\knowledgebase\migrations\version_2_0_13',
		];
	}

	public function effectively_installed()
	{
		// kb_version stays in the config on purpose: it is this extension's
		// version marker, and this check reads it to skip a step the board
		// already has (see version_2_0_20 and docs/upgrading-from-original.md).
		return
			(isset($this->config['kb_version']) &&
				version_compare($this->config['kb_version'], '2.0.14', '>='));
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
			['config.update', ['kb_version', '2.0.14']],

			// "Manage Extensions": which board attachment extensions are
			// allowed for KB uploads - split out of the Configuration page
			// onto its own page, same as core's own Manage attachment
			// extensions/extension groups pages are separate from its
			// general Attachment settings page.
			['module.add', ['acp', 'KNOWLEDGE_BASE', [
				'module_basename' => '\phpbbmodders\knowledgebase\acp\attachments_module',
				'module_langname' => 'ACP_LIBRARY_MANAGE_EXTENSIONS',
				'module_mode'     => 'extensions',
				'module_auth'     => 'ext_phpbbmodders/knowledgebase && acl_a_board && acl_a_manage_kb',
			]]],
		];
	}
}
