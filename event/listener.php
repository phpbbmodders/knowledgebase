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

namespace phpbbmodders\knowledgebase\event;

/**
 * @ignore
 */

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\config\db_text;
use phpbb\controller\helper;
use phpbb\language\language;
use phpbb\template\template;
use phpbbmodders\knowledgebase\inc\alternative_styles;
use phpbbmodders\knowledgebase\inc\feed;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event listener
 */
class listener implements EventSubscriberInterface
{
	/** @var config */
	protected config $config;

	/** @var db_text */
	protected db_text $config_text;

	/** @var helper */
	protected helper $helper;

	/* @var \phpbb\language\language */
	protected language $language;

	/** @var auth */
	protected auth $auth;

	/** @var template */
	protected template $template;

	/* @var string */
	protected string $php_ext;

	/** @var alternative_styles */
	protected alternative_styles $alt_styles;

	/**
	 * Constructor
	 *
	 * @param config   $config
	 * @param db_text  $config_text
	 * @param helper   $helper
	 * @param language $language
	 * @param auth     $auth
	 * @param template $template
	 * @param string   $php_ext
	 * @param alternative_styles $alt_styles
	 */
	public function __construct(
		config $config, db_text $config_text, helper $helper, language $language, auth $auth, template $template,
		string $php_ext, alternative_styles $alt_styles
	)
	{
		$this->config = $config;
		$this->config_text = $config_text;
		$this->helper = $helper;
		$this->language = $language;
		$this->auth = $auth;
		$this->template = $template;
		$this->php_ext = $php_ext;
		$this->alt_styles = $alt_styles;
	}

	/**
	 * @return string[]
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'core.user_setup'                          => 'load_language_on_setup',
			'core.page_header'                         => 'add_page_header_link',
			'core.viewonline_overwrite_location'       => 'viewonline_page',
			'core.permissions'                         => 'add_permission',
			'core.display_forums_modify_template_vars' => 'display_forums_modify_template_vars',
			'core.viewforum_modify_topicrow'           => 'viewforum_modify_topicrow',
		];
	}

	/**
	 * Load common language files during user setup
	 *
	 * @param \phpbb\event\data $event Event object
	 */
	public function load_language_on_setup(\phpbb\event\data $event): void
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'phpbbmodders/knowledgebase',
			'lang_set' => 'knowledgebase_lng',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Add a link to the controller in the forum navbar
	 */
	public function add_page_header_link($event): void
	{
		$this->template->assign_vars(array_merge([
			'KB_FONT_ICON' => $this->config['kb_font_icon'],
			'U_LIBRARY'    => ($this->auth->acl_get('u_kb_view') || $this->auth->acl_get('a_manage_kb')) ? $this->helper->route('phpbbmodders_knowledgebase_index') : '',
		], $this->feed_template_vars(), $this->stylesheet_template_vars()));
	}

	/**
	 * The recent changes feed links: the address of each feed format the ACP
	 * offers, for a visitor who may read the Knowledge Base. A format that is
	 * not offered, or a visitor who may not read, gets an empty address, so
	 * the header advertises nothing.
	 *
	 * @return array
	 */
	protected function feed_template_vars(): array
	{
		$vars = ['U_KB_FEED_RSS' => '', 'U_KB_FEED_ATOM' => ''];

		if (!$this->auth->acl_get('u_kb_view') && !$this->auth->acl_get('a_manage_kb'))
		{
			return $vars;
		}

		$setting = $this->config['kb_feed_format'] ?? feed::BOTH;
		if (feed::offers($setting, feed::RSS))
		{
			$vars['U_KB_FEED_RSS'] = $this->helper->route('phpbbmodders_knowledgebase_recent_changes_feed', ['_format' => feed::RSS]);
		}
		if (feed::offers($setting, feed::ATOM))
		{
			$vars['U_KB_FEED_ATOM'] = $this->helper->route('phpbbmodders_knowledgebase_recent_changes_feed', ['_format' => feed::ATOM]);
		}

		return $vars;
	}

	/**
	 * Template variables that decide which Knowledge Base stylesheets load.
	 *
	 * The flat index brings its own layout sheet. A chosen alternative
	 * stylesheet is a bundled file (loaded through INCLUDECSS by its name
	 * relative to the theme folder) or a custom file (loaded by the URL of the
	 * stylesheet controller, versioned by the file's modification time so an
	 * edited file is fetched again). An alternative that is off, unset or
	 * missing contributes nothing, so the board's own style shows.
	 *
	 * @return array
	 */
	protected function stylesheet_template_vars(): array
	{
		$vars = [
			'S_KB_FLAT_INDEX'            => !$this->config['kb_hierarchical_index'],
			'KB_ALT_STYLE_BUNDLED_FILE'  => '',
			'U_KB_ALT_STYLE_CUSTOM'      => '',
		];

		$active = $this->alt_styles->active();
		if ($active === null)
		{
			return $vars;
		}

		[$kind, $name] = $active;
		if ($kind === alternative_styles::BUNDLED)
		{
			$vars['KB_ALT_STYLE_BUNDLED_FILE'] = 'alternatives/' . $name . '.css';
		}
		else
		{
			$vars['U_KB_ALT_STYLE_CUSTOM'] = $this->helper->route('phpbbmodders_knowledgebase_stylesheet', [
				'f' => $name,
				'v' => (int) filemtime((string) $this->alt_styles->custom_file_path($name)),
			]);
		}

		return $vars;
	}

	/**
	 * Show users viewing Knowledge Base page on the Who Is Online page
	 *
	 * @param \phpbb\event\data $event Event object
	 */
	public function viewonline_page(\phpbb\event\data $event): void
	{
		if ($event['on_page'][1] === 'app' && strrpos($event['row']['session_page'], 'app.' . $this->php_ext . '/knowledgebase') === 0)
		{
			$event['location'] = $this->language->lang('LIBRARY');
			$event['location_url'] = $this->helper->route('phpbbmodders_knowledgebase_index', ['name' => 'index']);
		}
	}

	/**
	 * Modifies the names of the forums on index
	 *
	 * @param \phpbb\event\data $event Event object
	 */
	public function display_forums_modify_template_vars(\phpbb\event\data $event): void
	{
		$forum_row = $event['forum_row'];
		if (($this->config['kb_anounce']) &&
			($forum_row['FORUM_ID'] == $this->config['kb_forum_id']) &&
			(!empty($this->config_text->get('kb_forum_prefix'))))
		{
			$forum_row['FORUM_NAME'] = $this->decode_prefix_entities($this->config_text->get('kb_forum_prefix')) . ' ' . $forum_row['FORUM_NAME'];
		}
		$event['forum_row'] = $forum_row;
	}

	/**
	 * Modifies the names of the topics
	 *
	 * @param \phpbb\event\data $event Event object
	 */
	public function viewforum_modify_topicrow(\phpbb\event\data $event): void
	{
		$topic_row = $event['topic_row'];
		if ($this->config['kb_anounce'] && $topic_row['FORUM_ID'] == $this->config['kb_forum_id'] &&
			!empty($this->config_text->get('kb_topic_prefix')))
		{
			$topic_row['TOPIC_TITLE'] = $this->decode_prefix_entities($this->config_text->get('kb_topic_prefix')) . ' ' . $topic_row['TOPIC_TITLE'];
		}
		$event['topic_row'] = $topic_row;
	}

	/**
	 * The ACP help text for these two prefixes explicitly advertises
	 * typing HTML entities like &bull;/&laquo;/&raquo; into them
	 * (FORUM_PREFIX_EXPLAIN/TOPIC_PREFIX_EXPLAIN). request->variable()
	 * stores the prefix HTML-safe, escaping a literal "&" in "&bull;"
	 * to "&amp;bull;" - undoing just that one substitution restores the
	 * entity so it renders, without touching "&lt;"/"&gt;", so a "<"/">"
	 * the admin actually typed stays inert text instead of becoming a
	 * real tag. A blanket htmlspecialchars_decode() would undo both,
	 * turning a crafted prefix into active markup for every visitor.
	 *
	 * @param string $prefix
	 * @return string
	 */
	protected function decode_prefix_entities(string $prefix): string
	{
		return str_replace('&amp;', '&', $prefix);
	}

	/**
	 * Add permissions to the ACP -> Permissions settings page
	 * This is where permissions are assigned language keys and
	 * categories (where they will appear in the Permissions table):
	 * actions|content|forums|misc|permissions|pm|polls|post
	 * post_actions|posting|profile|settings|topic_actions|user_group
	 *
	 * Developers note: To control access to ACP, MCP and UCP modules, you
	 * must assign your permissions in your module_info.php file. For example,
	 * to allow only users with the a_mange_kb permission
	 * access to your ACP module, you would set this in your acp/main_info.php:
	 *    'auth' => 'ext_phpbbmodders/knowledgebase && acl_a_board && acl_a_manage_kb'
	 *
	 * @param \phpbb\event\data $event Event object
	 */
	public function add_permission(\phpbb\event\data $event): void
	{
		$permissions = $event['permissions'];
		$categories = $event['categories'];
		$permissions['a_manage_kb'] = ['lang' => 'ACL_A_MANAGE_KB', 'cat' => 'knowledgebase'];
		$permissions['u_kb_view'] = ['lang' => 'ACL_U_KB_VIEW', 'cat' => 'knowledgebase'];
		$event['permissions'] = $permissions;
		$event['categories'] = array_merge($categories, ['knowledgebase' => 'KNOWLEDGEBASE']);
	}
}
