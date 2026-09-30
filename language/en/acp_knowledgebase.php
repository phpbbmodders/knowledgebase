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
'KB_NOT_WRITABLE'                            => 'The "files" or "plupload" folders cannot be automatically set to write permissions.<br>Please set the folders permissions to 0777 manually.',
'KB_FONT_ICON'                               => 'Knowledge Base link icon',
'KB_FONT_ICON_EXPLAIN'                       => 'Enter the name of a <a href="https://fontawesome.com/v4.7.0/icons/" target="_blank"><strong>Font Awesome</strong></a> icon to use for the Knowledge Base link in the header.<br> Leave this field blank for no Knowledge Base icon.',
'KB_FONT_ICON_INVALID'                       => 'The Knowledge Base link icon contained invalid characters.',
'KB_CONFIG_EXPLAIN'                          => 'Here you can set the basic settings.',
'ANOUNCE'                                    => 'Announce new articles in a forum',
'ANOUNCE_EXPLAIN'                            => 'If enabled, a topic with a brief article description and a link to the article is automatically created in the forum you choose below whenever a new article is added.',
'KB_FORUM_EXPLAIN'                           => 'Select a forum in which to set up announcements of articles.',
'KB_NONE_DISABLED'                           => '-- None (disabled) --',
'KB_CHANGELOG_FORUM'                         => 'Changelog forum',
'KB_CHANGELOG_FORUM_EXPLAIN'                 => 'A moderator-only changelog topic per article is posted here on every submission, edit, and revision - separate from the announcement forum above, which is meant for regular members. Leave unset to disable this entirely.',
'PER_PAGE'                                   => 'The number of articles on the page',
'PER_PAGE_EXPLAIN'                           => 'The number of articles on the management page articles and view page search.',
'SORT_TYPE'                                  => 'Sorting order',
'SORT_TYPE_EXPLAIN'                          => 'Method of sorting articles',
'KB_SORT_POSITION'                           => 'Position',
'FORCIBLY'                                   => 'forcibly',
'SELECTABLE'                                 => 'selectable',
'ALPHABET'                                   => 'alphabetic navigation',
'EXTENSION_GROUP_EXPLAIN'                    => 'Allows to manage permissions for extensions od attachment files. To delete extensions, select only these you want keep actves in le drop down menu on the left side. Use CTRL + click to select more than one extension. To add a new extension, use the default phpBB page in ACP.',
'KB_TEAMS'                                   => 'Teams',
'KB_TEAMS_EXPLAIN'                           => 'Select which usergroups can be assigned articles for review. Members of a checked group can review articles assigned to that group, and can post articles under the group\'s name instead of their own.',
'KB_FIELDS_EXPLAIN'                          => 'Custom fields let you tag articles with extra structured information, such as which phpBB version(s) an article applies to. Members can filter articles by field when browsing a category.',
'KB_FIELD_NAME'                              => 'Field name',
'KB_FIELD_KEY'                               => 'Field key',
'KB_FIELD_KEY_EXPLAIN'                       => 'A short, unique machine name (letters, numbers, underscores only) - not shown to members.',
'KB_FIELD_TYPE'                              => 'Field type',
'KB_FIELD_TYPE_MULTI_SELECT'                 => 'Checkbox list',
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
'KB_NO_FIELDS'                               => 'No custom fields have been defined yet.',
'KB_FLAT_INDEX'                              => 'Flat article index',
'KB_FLAT_INDEX_EXPLAIN'                      => 'When enabled, the article index shows a single dropdown listing every category instead of hierarchical subcategory browsing, and the index page lists every article across every category by default.',
'KB_REQUIRE_PM'                              => 'Require author private message',
'KB_REQUIRE_PM_EXPLAIN'                      => 'When enabled, the private message sent to an author on approve/deny is always delivered, regardless of whether the author has turned it off in their own notification settings. When disabled (default), it stays an ordinary opt-in notification method like Email or Jabber.',
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
'CATEGORY_ADDED'                             => 'Category successfully added. Now you can %sset permission%s to this category.',
'CATEGORY_DELETED'                           => 'Category deleted successfully.',
'CATEGORY_EDITED'                            => 'Category successfully edited',
'CAT_PARENT'                                 => 'Parent Category',
'CAT_NAME'                                   => 'Category name',
'CAT_DESCR'                                  => 'Category description',
'CAT_DEFAULT_TEAM'                           => 'Default team',
'CAT_DEFAULT_TEAM_EXPLAIN'                   => 'An article in this category with no team assigned to it directly falls into this team’s queue instead.',
'CAT_DEFAULT_TEAM_NONE'                      => 'None',
'COPY_CAT_PERMISSIONS'                       => 'Copy category permissions',
'COPY_CAT_PERMISSIONS_EXPLAIN'               => 'Allows to copy category permissions to the current category.',
'KB_ROOT'                                    => 'Root category',
'NO_CAT_NAME'                                => 'You have not specified category name.',
'DELETE_SUBCATS'                             => 'Delete categories and articles',
'DEL_CATEGORY'                               => 'Delete category',
'DEL_CATEGORY_EXPLAIN'                       => 'This form below allows you delete a category. You can decide where to move all the articles in it or subcategory.',
'LIBRARY_EDIT_CAT'                           => 'Edit the category',
'LIBRARY_EDIT_CAT_EXPLAIN'                   => 'Here you can rename the category, give it a brief description and move to another category (with the content).',
'MOVE_SUBCATS_TO'                            => 'Move subcategories',
'NO_CATS_IN_KB'                              => 'Knowledge Base has no categories.',
'NO_DESTINATION_CATEGORY'                    => 'Category recipient could not be found',
'NO_PARENT'                                  => 'No parent',

//Article
'ARTICLE_MANAGE_EXPLAIN'                     => 'Here you can delete articles or move them to other categories, as well as view or edit them (in a separate window).',
'ARTICLE_MOVE_EXPLAIN'                       => 'Select category to which you want move article.',
'EDIT_DATE'                                  => 'Edited',
'MOVE_ARTICLES_TO'                           => 'Move article',
'DELETE_ALL_ARTICLES'                        => 'Delete article',
'NO_ARTICLES_IN_KB'                          => 'The Knowledge Base currently has no articles.',
'NO_ARTICLES_IN_CATEGORY'                    => 'This category currently has no articles.',

//Permission
'ACP_LIBRARY_PERMISSIONS'                    => 'Permissions',
'ACP_LIBRARY_PERMISSIONS_MASK'               => 'Permissions trace',
'ACP_LIBRARY_PERMISSIONS_NO_CATS'            => 'To set permissions, you must create at least one category.',
'ACP_LIBRARY_PERMISSIONS_EXPLAIN'            => 'Here you can change for each user and group access to each category of the library. To assign moderators or administrator rights to use the definition of the relevant page.',
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
'SEARCH_TYPE_EXPLAIN'                        => 'Extension Knowledge Base allows you to choose the backend that is used for searching text in post contents. By default the search will use Knowledge Base own fulltext search.',

//Attachments
'ATTACHMENTS_EXPLAIN'                        => 'Allows to see and delete attachment files in articles.',
'ACP_LIBRARY_ATTACHMENTS_LOST_FILES_EXPLAIN' => 'Allows to find lost files on server. If files aren’t present on server entries will be deleted.',
'PRUNE_ATTACHMENTS_EXPLAIN'                  => 'It will be verify the existance of orphean files on server. If the file exists, it will be deleted. Would you confirm?',
'ORPHAN_EXPLAIN'                             => 'Allows to see lost files. In general these files are displayed here because users send files but don’t publish articles. It possible to delete these files or attach these to articles. You must enter the ID article to attach a lost file to an article.',
'FILES_DELETED_SUCCESS'                      => 'Attachment files deleted successfully!',
'NO_FILES_SELECTED'                          => 'No file selected.',
'PRUNE_ATTACHMENTS_FINISHED'                 => 'No supplementary files was found.',
'PRUNE_ATTACHMENTS_PROGRESS'                 => 'Unused files are checked. Don’t stop the process!<br>The next files were deleted:',
'PRUNE_ATTACHMENTS_FAIL'                     => '<br>It was impossible to delete the next files:',
'POST_ROW_ARTICLE_INFO'                      => ' with number %1$d…',
'RESYNC_ATTACHMENTS_FINISHED'                => 'The attachment files were successfully synchronized (verification of the accuracy of entries in the database)',
'RESYNC_ATTACHMENTS_PROGRESS'                => 'The verification of entries in the database is in progress! Don’t stop the process!',
'SYNC_OK'                                    => 'Category successfully synchronized.',
'THUMBNAIL_EXPLAIN'                          => 'The thumbnails sizes are defined in default phpBB page in ACP (&laquo;Attachment files settings&raquo;).',
'UPLOAD_DENIED_ARTICLE'                      => 'Article with this ID doesn’t exist.',
'UPLOADING_FILE_TO_ARTICLE'                  => 'Upload file “%1$s” to article',

//Log
'ACP_LIBRARY_LOGS_EXPLAIN'                   => 'This is a list of actions performed with the library. You can sort the list by user name, date, IP-address or action. You can delete individual entries or clear the entire log as a whole.',
'LOG_CLEAR_KB'                               => '<strong>Cleaned logs library </strong>',
'LOG_CATS_ADD'                               => '<strong>Add category</strong><br> %s',
'LOG_CATS_DEL_ARTICLES'                      => '<strong>Remove Category articles</strong><br> %s',
'LOG_CATS_DEL_MOVE_POSTS_MOVE_CATS'          => '<strong>Remove Category</strong> %3$s, <strong>Article moved to</strong> %1$s <strong>and subcategories</strong> % 2$s',
'LOG_CATS_DEL_MOVE_POSTS'                    => '<strong>Remove Category</strong> %2$s<br><strong>and moved to an article in</strong> % 1$s',
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
'LOG_LIBRARY_REJECTED_ARTICLE'               => 'Rejected article <strong>%1s</strong> in category <strong>%2s</strong><br>created by user <strong>%3s</strong>',
'LOG_LIBRARY_PERMISSION_DELETED'             => 'Remove user/group access to category <strong>%1s</strong><br> %2s',
'LOG_LIBRARY_PERMISSION_ADD'                 => 'Adding or changing user/group access to category <strong>%1s</strong><br> %2s',
'LOG_LIBRARY_CONFIG'                         => '<strong>Reconfigured library</strong>',

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
