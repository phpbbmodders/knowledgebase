<?php
/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\acp;

class fields_info
{
	public function module(): array
	{
		return [
			'filename' => '\phpbbmodders\knowledgebase\acp\fields_module',
			'version'  => '1.0.0',
			'title'    => 'ACP_LIBRARY_FIELDS',
			'modes'    => [
				'fields' => [
					'title' => 'ACP_LIBRARY_FIELDS',
					'auth'  => 'ext_phpbbmodders/knowledgebase && acl_a_board && acl_a_manage_kb',
					'cat'   => ['ACP_KNOWLEDGE_BASE'],
				],
			],
		];
	}
}
