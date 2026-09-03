<?php
/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\acp;

class fields_module
{
	public string $page_title;
	public string $tpl_name;
	public string $u_action;

	/**
	 * Custom fields ACP module
	 *
	 * @param string $id   The module ID
	 * @param string $mode The module mode
	 * @throws \Exception
	 */
	public function main(string $id, string $mode): void
	{
		global $phpbb_container;

		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');
		$language->add_lang('acp_knowledgebase', 'phpbbmodders/knowledgebase');

		/** @var \phpbb\request\request $request */
		$request = $phpbb_container->get('request');

		$admin_controller = $phpbb_container->get('phpbbmodders.knowledgebase.controller.acp');
		$admin_controller->set_page_url($this->u_action);

		$this->tpl_name = 'acp_knowledgebase_fields';
		$this->page_title = $language->lang('KNOWLEDGE_BASE') . ' &bull; ' . $language->lang('ACP_LIBRARY_FIELDS');

		$action = $request->variable('action', '');
		$field_id = $request->variable('field_id', 0);

		switch ($action)
		{
			case 'add_field':
				$admin_controller->add_field();
			break;

			case 'toggle_field':
				$admin_controller->toggle_field($field_id);
			break;

			case 'edit_values':
				$admin_controller->edit_field_values($field_id);
			break;

			default:
				$admin_controller->show_fields();
			break;
		}
	}
}
