<?php
/**
 *
 * knowledgebase [English]
 *
 * @copyright (c) 2013 phpBB Group
 * @license       http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, [
//Configuration
'KB_NOT_WRITABLE'                            => 'The “files” and “plupload” folders could not be made writable automatically.<br>Please make them writable by the web server manually.',
'KB_FONT_ICON'                               => 'Knowledge Base link icon',
'KB_FONT_ICON_EXPLAIN'                       => 'Enter the name of a <a href="https://fontawesome.com/v4.7.0/icons/" target="_blank"><strong>Font Awesome</strong></a> icon to use for the Knowledge Base link in the header.<br> Leave this field blank for no Knowledge Base icon.',
'KB_FONT_ICON_INVALID'                       => 'The Knowledge Base link icon contained invalid characters.',
'KB_CONFIG_EXPLAIN'                          => 'Here you can set the basic settings.',
'ANOUNCE'                                    => 'Announce new articles in a forum',
'ANOUNCE_EXPLAIN'                            => 'A topic with a brief article description and a link to the article is automatically created in the forum you choose whenever a new article is added. Choose &ldquo;None (disabled)&rdquo; to turn announcements off.',
'KB_NONE_DISABLED'                           => '-- None (disabled) --',
'KB_CHANGELOG_FORUM'                         => 'Changelog forum',
'KB_CHANGELOG_FORUM_EXPLAIN'                 => 'A moderator-only changelog topic per article is posted here on every submission, edit, and revision - separate from the announcement forum above, which is meant for regular members. Leave unset to disable this entirely.',
'PER_PAGE'                                   => 'The number of articles on the page',
'PER_PAGE_EXPLAIN'                           => 'The number of articles per page on “Manage Your Articles” and in search results.',
'SORT_TYPE'                                  => 'Sorting order',
'SORT_TYPE_EXPLAIN'                          => 'How the articles in a category are ordered for readers. Hover over a choice for details.',
'KB_SORT_POSITION'                           => 'Position',
'KB_SORT_FIXED'                              => 'Fixed',
'KB_SORT_FIXED_EXPLAIN'                      => 'One manual order for everyone, set by moderators with the up and down arrows next to each article.',
'KB_SORT_SELECTABLE'                         => 'Selectable',
'KB_SORT_SELECTABLE_EXPLAIN'                 => 'Readers choose the order themselves: by author, post time, subject or views, ascending or descending. The arrows are hidden.',
'KB_SORT_ALPHABET'                           => 'Alphabetical',
'KB_SORT_ALPHABET_EXPLAIN'                   => 'Articles are listed A to Z by title, with a row of letters above the list that shows only the titles starting with the letter chosen. The arrows are hidden.',
'KB_MANAGE_EXTENSIONS_EXPLAIN'               => 'Choose which of the board\'s attachment file extensions are allowed for Knowledge Base uploads. Administrators with attachment-management permission can also add extensions here. To rename or delete extensions, or manage extension groups, use the board\'s Attachments settings (Posting tab).',
'KB_TEAMS'                                   => 'Teams',
'KB_TEAMS_EXPLAIN'                           => 'Select which usergroups can be assigned articles for review. Members of a checked group can review articles assigned to that group, and can post articles under the group\'s name instead of their own.',
'KB_CONTACT_GROUP'                          => 'Contact group',
'KB_CONTACT_GROUP_EXPLAIN'                  => 'Authors are told on the "Manage Your Articles" page to send a private message to a member of this group with questions about their articles, with a link to the group\'s member list. Leave on "None" to show no such sentence.',
'KB_DESCRIPTION_MAX_LENGTH'                 => 'Description maximum length',
'KB_DESCRIPTION_MAX_LENGTH_EXPLAIN'         => 'The longest article description, in characters, that authors can enter when submitting an article or a redirect. It cannot be more than 255, the width of the database column.',
'KB_FIELDS_EXPLAIN'                          => 'Custom fields let you tag articles with extra structured information, such as which phpBB version(s) an article applies to. Members can filter articles by field when browsing a category.',
'KB_FIELD_NAME'                              => 'Field name',
'KB_FIELD_KEY'                               => 'Field key',
'KB_FIELD_KEY_EXPLAIN'                       => 'A short, unique machine name (letters, numbers, underscores only) - not shown to members.',
'KB_FIELD_TYPE'                              => 'Field type',
'KB_FIELD_TYPE_MULTI_SELECT'                 => 'Checkbox list',
'KB_FIELD_TYPE_SINGLE_DROPDOWN'              => 'Single choice (dropdown)',
'KB_FIELD_TYPE_SINGLE_RADIO'                 => 'Single choice (radio buttons)',
'KB_FIELD_ADD'                               => 'Add field',
'KB_FIELD_EDIT_VALUES'                       => 'Edit',
'KB_FIELD_VALUES_EXPLAIN'                    => 'Add, enable/disable, or delete the values members can choose from for this field. Disabling a value hides it from the posting form but keeps it on articles that already have it.',
'KB_FIELD_VALUE_ENABLED'                     => 'Enabled',
'KB_FIELD_NEW_VALUE'                         => 'Add a new value',
'KB_FIELD_INVALID'                           => 'Please enter a valid field name, key, and type.',
'KB_FIELD_KEY_TAKEN'                         => 'That field key is already in use.',
'KB_FIELD_ADDED'                             => 'The field has been added.',
'KB_FIELD_NOT_FOUND'                         => 'That field does not exist.',
'KB_FIELD_UPDATED'                           => 'Settings successfully updated.',
'KB_FIELD_DELETED'                           => 'The field has been deleted.',
'KB_FIELD_DELETE_CONFIRM'                    => 'Delete the field &ldquo;%1$s&rdquo;? Its %2$d values and their use on %3$d articles will be removed. The articles themselves are not deleted. This cannot be undone.',
'KB_FIELD_REQUIRED'                          => 'Required',
'KB_FIELD_REQUIRED_EXPLAIN'                  => 'Authors must select at least one value of this field when they submit an article or a redirect. A draft can still be saved without one. A field with no enabled values is never enforced.',
'KB_FIELD_MAKE_REQUIRED'                     => 'make required',
'KB_FIELD_MAKE_OPTIONAL'                     => 'make optional',
'KB_NO_FIELDS'                               => 'No custom fields have been defined yet.',
'KB_HIERARCHICAL_INDEX'                      => 'Hierarchical category browsing',
'KB_HIERARCHICAL_INDEX_EXPLAIN'              => 'Off by default: the Knowledge Base then shows the flat article index, a single dropdown listing every category and one list of every article across every category, with a compact layout for the article tables. When enabled, the index shows the category tree with its subcategories instead. The colours stay those of the board style either way.',
'KB_ALT_STYLE_ENABLE'                        => 'Use an alternative stylesheet',
'KB_ALT_STYLE_ENABLE_EXPLAIN'                => 'Recolours the Knowledge Base&rsquo;s tabs, header bars and article tables over the board style. Off by default: the Knowledge Base then uses the colours of each member&rsquo;s own board style. The stylesheet you choose below is remembered while this is off.',
'KB_ALT_STYLE'                               => 'Stylesheet',
'KB_ALT_STYLE_EXPLAIN'                       => 'Bundled stylesheets ship with the extension. To add your own, copy a .css file (letters, digits, dots, dashes and underscores in its name, up to 256 KiB) into <code>%s</code> on the board and it appears here under Custom. The commented <code>slate.css</code> in the extension&rsquo;s <code>styles/all/theme/alternatives/</code> folder is a starting point.',
'KB_ALT_STYLE_BUNDLED_GROUP'                 => 'Bundled',
'KB_ALT_STYLE_CUSTOM_GROUP'                  => 'Custom (from the board&rsquo;s store folder)',
'KB_ALT_STYLE_SLATE'                         => 'Slate',
'KB_ALT_STYLE_INVALID'                       => 'The stylesheet you chose is not available. Choose one from the list.',
'KB_ALT_STYLE_MISSING'                       => 'The stylesheet &ldquo;%s&rdquo; is no longer available, so the board style&rsquo;s own colours are being used. Choose another stylesheet or put the file back.',
'FORUM_PREFIX'                               => 'Forum Prefix',
'FORUM_PREFIX_EXPLAIN'                       => 'The text displayed before the forum name.<br>You can use html entities e.g. &amp;bull; &bull;&nbsp;&nbsp;&nbsp;&amp;laquo; &laquo;&nbsp;&nbsp;&nbsp;&amp;raquo; &raquo;',
'TOPIC_PREFIX'                               => 'Topic Prefix',
'TOPIC_PREFIX_EXPLAIN'                       => 'The text displayed before the topic name.<br>You can use html entities e.g. &amp;bull; &bull;&nbsp;&nbsp;&nbsp;&amp;laquo; &laquo;&nbsp;&nbsp;&nbsp;&amp;raquo; &raquo;',

//Manage category
'ACP_LIBRARY_MANAGE_EXPLAIN'                 => 'Each category can have an unlimited number of subcategories. From here you can add, edit, move, or delete a category, and select one below to manage its subcategories. If a category\'s article count looks wrong, use the refresh icon to recalculate it from the articles it actually contains.',
'CATEGORY_LIST'                              => 'Categories list',
'SELECT_CATEGORY'                            => 'Select a category',
'SELECTION_CATEGORY'                         => 'Select a category',
'ADD_CATEGORY'                               => 'Create a new category',
'ADD_CATEGORY_EXPLAIN'                       => 'Create a new category',
'CATEGORY_ADDED'                             => 'Category successfully added. Now you can %sset permissions%s for this category.',
'CATEGORY_DELETED'                           => 'Category deleted successfully.',
'CATEGORY_EDITED'                            => 'Category successfully edited',
'CAT_PARENT'                                 => 'Parent Category',
'CAT_NAME'                                   => 'Category name',
'CAT_DESCR'                                  => 'Category description',
'CAT_DEFAULT_TEAM'                           => 'Default team',
'CAT_DEFAULT_TEAM_EXPLAIN'                   => 'An article in this category with no team assigned to it directly falls into this team’s queue instead.',
'CAT_DEFAULT_TEAM_NONE'                      => 'None',
'COPY_CAT_PERMISSIONS'                       => 'Copy category permissions',
'COPY_CAT_PERMISSIONS_EXPLAIN'               => 'Copies the permissions of the category you choose to this category.',
'KB_ROOT'                                    => 'Root category',
'NO_CAT_NAME'                                => 'You have not specified a category name.',
'DELETE_SUBCATS'                             => 'Delete categories and articles',
'DEL_CATEGORY'                               => 'Delete category',
'DEL_CATEGORY_EXPLAIN'                       => 'This form allows you to delete a category. You can decide where to move its articles and subcategories.',
'LIBRARY_EDIT_CAT'                           => 'Edit the category',
'LIBRARY_EDIT_CAT_EXPLAIN'                   => 'Here you can rename the category, give it a brief description and move it, with its content, to another category.',
'MOVE_SUBCATS_TO'                            => 'Move subcategories',
'NO_CATS_IN_KB'                              => 'The Knowledge Base has no categories.',
'NO_DESTINATION_CATEGORY'                    => 'The destination category could not be found.',
'NO_PARENT'                                  => 'No parent',

//Article
'ARTICLE_MANAGE_EXPLAIN'                     => 'Here you can delete articles or move them to other categories, as well as view or edit them (in a separate window).',
'ARTICLE_MOVE_EXPLAIN'                       => 'Select the category you want to move the article to.',
'EDIT_DATE'                                  => 'Edited',
'MOVE_ARTICLES_TO'                           => 'Move article',
'DELETE_ALL_ARTICLES'                        => 'Delete article',
'NO_ARTICLES_IN_KB'                          => 'The Knowledge Base currently has no articles.',
'NO_ARTICLES_IN_CATEGORY'                    => 'This category currently has no articles.',

//Permission
'ACP_LIBRARY_PERMISSIONS'                    => 'Permissions',
'ACP_LIBRARY_PERMISSIONS_MASK'               => 'Permissions trace',
'ACP_LIBRARY_PERMISSIONS_NO_CATS'            => 'To set permissions, you must create at least one category.',
'ACP_LIBRARY_PERMISSIONS_EXPLAIN'            => 'Here you can change user and group access to each category of the Knowledge Base. To assign moderator or administrator rights, use the relevant permissions pages.',
'ALL_CATS'                                   => 'All Categories',
'NO_COPY_PERMISSIONS'                        => 'Don’t copy permissions',
// User Permissions
'kb_u_add'                                   => 'Can add articles',
'kb_u_edit'                                  => 'Can edit own articles',
'kb_u_delete'                                => 'Can delete own articles',
'kb_u_add_noapprove'                         => 'Can add / modify articles without prior approval',
// Moderator Permissions
'kb_m_edit'                                  => 'Can edit articles',
'kb_m_delete'                                => 'Can delete articles',
'kb_m_approve'                               => 'Can approve articles',

//Search
'YES_SEARCH_EXPLAIN'                         => 'Enables user facing search functionality including member search.',
'PER_PAGE_SEARCH'                            => 'Search Results',
'PER_PAGE_SEARCH_EXPLAIN'                    => 'The number of items displayed on the search results page.',
'SEARCH_TYPE_EXPLAIN'                        => 'The Knowledge Base lets you choose the backend used to search article text. By default it uses its own fulltext search.',

//Attachments
'ATTACHMENTS_EXPLAIN'                        => 'Lists the attachment files in articles and lets you delete them.',
'ACP_LIBRARY_ATTACHMENTS_LOST_FILES_EXPLAIN' => 'Finds attachment entries whose files are missing from the server. Those entries are deleted.',
'PRUNE_ATTACHMENTS_EXPLAIN'                  => 'This looks for orphaned files on the server and deletes any it finds. Do you want to continue?',
'ORPHAN_EXPLAIN'                             => 'Lists uploaded files that are not attached to any article. They usually appear here because a member uploaded a file but never published the article. You can delete them, or attach one to an article by entering the article ID.',
'FILES_DELETED_SUCCESS'                      => 'Attachment files deleted successfully!',
'NO_FILES_SELECTED'                          => 'No file selected.',
'PRUNE_ATTACHMENTS_FINISHED'                 => 'No unused files were found.',
'PRUNE_ATTACHMENTS_PROGRESS'                 => 'Unused files are being checked. Do not stop the process!<br>These files were deleted:',
'PRUNE_ATTACHMENTS_FAIL'                     => '<br>These files could not be deleted:',
'POST_ROW_ARTICLE_INFO'                      => ' with number %1$d…',
'RESYNC_ATTACHMENTS_FINISHED'                => 'The attachment files were successfully synchronized (verification of the accuracy of entries in the database)',
'RESYNC_ATTACHMENTS_PROGRESS'                => 'The verification of entries in the database is in progress! Don’t stop the process!',
'SYNC_OK'                                    => 'Category successfully synchronized.',
'THUMBNAIL_EXPLAIN'                          => 'The thumbnails sizes are defined in default phpBB page in ACP (&laquo;Attachment files settings&raquo;).',
'UPLOAD_DENIED_ARTICLE'                      => 'An article with this ID does not exist.',
'UPLOADING_FILE_TO_ARTICLE'                  => 'Upload file “%1$s” to article',

//Log
'ACP_LIBRARY_LOGS_EXPLAIN'                   => 'This is a list of actions performed in the Knowledge Base. You can sort the list by user name, date, IP address or action. You can delete individual entries or clear the entire log as a whole.',
'LOG_CLEAR_KB'                               => '<strong>Cleared the Knowledge Base logs</strong>',
'LOG_CATS_ADD'                               => '<strong>Add category</strong><br> %s',
'LOG_CATS_DEL_ARTICLES'                      => '<strong>Remove Category articles</strong><br> %s',
'LOG_CATS_DEL_MOVE_POSTS_MOVE_CATS'          => '<strong>Remove Category</strong> %3$s, <strong>Article moved to</strong> %1$s <strong>and subcategories</strong> %2$s',
'LOG_CATS_DEL_MOVE_POSTS'                    => '<strong>Remove Category</strong> %2$s<br><strong>and moved to an article in</strong> %1$s',
'LOG_KB_FIELD_DELETE'                        => '<strong>Deleted custom field</strong><br> %s',
'LOG_CATS_DEL_CAT'                           => '<strong>Remove Category</strong><br> %s',
'LOG_CATS_DEL_MOVE_POSTS_CATS'               => '<strong>Remove Category </strong> %2$s<br><strong>with subcategories, articles moved to</strong> %1$s',
'LOG_CATS_DEL_POSTS_MOVE_CATS'               => '<strong>Remove Category </strong> %2$s <strong>with articles, subcategory moved to</strong> %1$s',
'LOG_CATS_DEL_POSTS_CATS'                    => '<strong>Remove Category with articles and subcategories</strong><br> %s',
'LOG_CATS_EDIT'                              => '<strong>Changed category information</strong><br> %1$s',
'LOG_CATS_CAT_MOVED_TO'                      => '<strong>Category</strong> %1$s <strong>moved to</strong> %2$s',
'LOG_CATS_MOVE_UP'                            => '<strong>Category</strong> %1$s <strong>moved up, past</strong> %2$s',
'LOG_CATS_MOVE_DOWN'                          => '<strong>Category</strong> %1$s <strong>moved down, past</strong> %2$s',
'LOG_CATS_SYNC'                               => '<strong>Synchronized article count for category</strong><br> %s',
'LOG_KB_CONFIG_SEARCH'                       => '<strong>Knowledge Base Search settings changed</strong>',
'LOG_LIBRARY_ADD_ARTICLE'                    => 'Added article &laquo;<strong>%1s</strong>&raquo; in category<br> <strong>%2s</strong>',
'LOG_LIBRARY_DEL_ARTICLE'                    => 'Removed article &laquo;<strong>%1s</strong>&raquo; from category<br> <strong>%2s</strong>',
'LOG_LIBRARY_EDIT_ARTICLE'                   => 'Edited article &laquo;<strong>%1s</strong>&raquo; in category<br> <strong>%2s</strong>',
'LOG_LIBRARY_MOVED_ARTICLE'                  => 'Moved article <strong>%1s</strong> from category <strong>%2s</strong><br>to category <strong>%3s</strong>',
'LOG_LIBRARY_APPROVED_ARTICLE'               => 'Approved article <strong>%1s</strong> in category <strong>%2s</strong><br>created by user <strong>%3s</strong>',
'LOG_LIBRARY_REJECTED_ARTICLE'               => 'Disapproved article <strong>%1s</strong> in category <strong>%2s</strong><br>created by user <strong>%3s</strong>',
'LOG_LIBRARY_PERMISSION_DELETED'             => 'Remove user/group access to category <strong>%1s</strong><br> %2s',
'LOG_LIBRARY_PERMISSION_ADD'                 => 'Adding or changing user/group access to category <strong>%1s</strong><br> %2s',
'LOG_LIBRARY_CONFIG'                         => '<strong>Reconfigured the Knowledge Base</strong>',

'KB_TRACE_GROUP_NEVER_TOTAL_NO_LOCAL'  => 'This group’s permission for this category is set to <strong>NEVER</strong> which becomes the new total value because it wasn’t set yet (set to <strong>NO</strong>).',
'KB_TRACE_GROUP_NEVER_TOTAL_YES_LOCAL' => 'This group’s permission for this category is set to <strong>NEVER</strong> which overwrites the total <strong>YES</strong> to a <strong>NEVER</strong> for this user.',
'KB_TRACE_GROUP_NO_LOCAL'              => 'The permission is <strong>NO</strong> for this group within this category so the old total value is kept.',
'KB_TRACE_GROUP_YES_TOTAL_NEVER_LOCAL' => 'This group’s permission for this category is set to <strong>YES</strong> but the total <strong>NEVER</strong> cannot be overwritten.',
'KB_TRACE_GROUP_YES_TOTAL_NO_LOCAL'    => 'This group’s permission for this category is set to <strong>YES</strong> which becomes the new total value because it wasn’t set yet (set to <strong>NO</strong>).',
'KB_TRACE_GROUP_YES_TOTAL_YES_LOCAL'   => 'This group’s permission for this category is set to <strong>YES</strong> and the total permission is already set to <strong>YES</strong>, so the total result is kept.',

// Same as the _LOCAL versions above, but for the global (non-category) trace view -
// controller/acp_controller.php picks between the two sets based on whether a category_id is present.
'KB_TRACE_GROUP_NEVER_TOTAL_NO'  => 'This group’s permission is set to <strong>NEVER</strong> which becomes the new total value because it wasn’t set yet (set to <strong>NO</strong>).',
'KB_TRACE_GROUP_NEVER_TOTAL_YES' => 'This group’s permission is set to <strong>NEVER</strong> which overwrites the total <strong>YES</strong> to a <strong>NEVER</strong> for this user.',
'KB_TRACE_GROUP_NO'              => 'The permission is <strong>NO</strong> for this group so the old total value is kept.',
'KB_TRACE_GROUP_YES_TOTAL_NEVER' => 'This group’s permission is set to <strong>YES</strong> but the total <strong>NEVER</strong> cannot be overwritten.',
'KB_TRACE_GROUP_YES_TOTAL_NO'    => 'This group’s permission is set to <strong>YES</strong> which becomes the new total value because it wasn’t set yet (set to <strong>NO</strong>).',
'KB_TRACE_GROUP_YES_TOTAL_YES'   => 'This group’s permission is set to <strong>YES</strong> and the total permission is already set to <strong>YES</strong>, so the total result is kept.',

'KB_TRACE_USER_FOUNDER' => 'The user is a founder, therefore admin permissions are always set to <strong>YES</strong>.',
'KB_TRACE_USER_ADMIN'   => 'The user is a board administrator with permission <strong>Can manage Knowledge Base</strong>, therefore admin permissions are always set to <strong>YES</strong>.',

'KB_TRACE_USER_KEPT_LOCAL'              => 'The user’s permission for this category is <strong>NO</strong> so the old total value is kept.',
'KB_TRACE_USER_NEVER_TOTAL_NEVER_LOCAL' => 'The user’s permission for this category is set to <strong>NEVER</strong> and the total value is set to <strong>NEVER</strong>, so nothing is changed.',
'KB_TRACE_USER_NEVER_TOTAL_NO_LOCAL'    => 'The user’s permission for this category is set to <strong>NEVER</strong> which becomes the total value because it was set to NO.',
'KB_TRACE_USER_NEVER_TOTAL_YES_LOCAL'   => 'The user’s permission for this category is set to <strong>NEVER</strong> and overwrites the previous <strong>YES</strong>.',
'KB_TRACE_USER_NO_TOTAL_NO_LOCAL'       => 'The user’s permission for this category is <strong>NO</strong> and the total value was set to NO so it defaults to <strong>NEVER</strong>.',
'KB_TRACE_USER_YES_TOTAL_NEVER_LOCAL'   => 'The user’s permission for this category is set to <strong>YES</strong> but the total <strong>NEVER</strong> cannot be overwritten.',
'KB_TRACE_USER_YES_TOTAL_NO_LOCAL'      => 'The user’s permission for this category is set to <strong>YES</strong> which becomes the total value because it was set to <strong>NO</strong>.',
'KB_TRACE_USER_YES_TOTAL_YES_LOCAL'     => 'The user’s permission for this category is set to <strong>YES</strong> and the total value is set to <strong>YES</strong>, so nothing is changed.',

'KB_TRACE_USER_KEPT'              => 'The user’s permission is <strong>NO</strong> so the old total value is kept.',
'KB_TRACE_USER_NEVER_TOTAL_NEVER' => 'The user’s permission is set to <strong>NEVER</strong> and the total value is set to <strong>NEVER</strong>, so nothing is changed.',
'KB_TRACE_USER_NEVER_TOTAL_NO'    => 'The user’s permission is set to <strong>NEVER</strong> which becomes the total value because it was set to NO.',
'KB_TRACE_USER_NEVER_TOTAL_YES'   => 'The user’s permission is set to <strong>NEVER</strong> and overwrites the previous <strong>YES</strong>.',
'KB_TRACE_USER_NO_TOTAL_NO'       => 'The user’s permission is <strong>NO</strong> and the total value was set to NO so it defaults to <strong>NEVER</strong>.',
'KB_TRACE_USER_YES_TOTAL_NEVER'   => 'The user’s permission is set to <strong>YES</strong> but the total <strong>NEVER</strong> cannot be overwritten.',
'KB_TRACE_USER_YES_TOTAL_NO'      => 'The user’s permission is set to <strong>YES</strong> which becomes the total value because it was set to <strong>NO</strong>.',
'KB_TRACE_USER_YES_TOTAL_YES'     => 'The user’s permission is set to <strong>YES</strong> and the total value is set to <strong>YES</strong>, so nothing is changed.',

'KNOWLEDGE_BASE' => 'Knowledge Base',
]);
