# Archived Reviews

Historical validation reports, retained with their original scope and recommendations.

<a id="ai-validation-review-2026-09-26"></a>

## AI extension validation - 2026-09-26

Archived: 10/02/2026. Validation date: 09/26/2026. Historical findings from the pre-remediation snapshot. Later commits changed the reviewed search escaping, prefix handling, filename fallback, permissions, topic tracking, and package metadata; the old Deny recommendation is not a current-tree verdict. The two deliberately deferred design suggestions remain active in docs/TODO.md. This archive operation is not a new validation pass and does not declare every historical finding resolved.

In [c]controller/search.php[/c]:

[code]
if ($return_chars == -1 || utf8_strlen($text_only_message) < ($return_chars + 3))
{
	$row['article_body'] = generate_text_for_display($message, $row['bbcode_uid'], $row['bbcode_bitfield'], $parse_flags, false);
	$row['article_body'] = strtr($row['article_body'], ['&lt;' => '<', '&gt;' => '>']);
}
[/code]

[valdeny]
The call to [c]generate_text_for_display()[/c] already returns display-ready HTML while keeping literal angle brackets from article text escaped. The following replacement restores every escaped bracket. The value is rendered as [c]searchrow.MESSAGE[/c] in a phpBB Twig environment without automatic escaping, so stored article text can become active HTML on the search-results page. Remove the [c]strtr()[/c] call.
[/valdeny]

[hr][/hr]

In [c]inc/functions_kb.php[/c]:

[code]
$auth_setting = $this->db->sql_fetchfield('auth_setting');

if ($auth_setting == ACL_YES)
{
	$total = ACL_YES;
}
[/code]

[valdeny]
The user-specific permission branch ignores [c]ACL_NEVER[/c] and lets [c]ACL_YES[/c] overwrite a group-level Never. This differs from the permission trace in [c]controller/acp_controller.php[/c], and it can grant add, edit, delete, or approval access to a user whom an administrator explicitly denied. Apply the same [c]ACL_NO[/c], [c]ACL_YES[/c], and [c]ACL_NEVER[/c] precedence used by the trace.
[/valdeny]

[hr][/hr]

In [c]inc/functions_kb.php[/c]:

[code]
submit_post('post', $topic_title, $this->user->data['username'], 0, $poll, $data, false);

$sql = 'SELECT MAX(topic_id) AS last_topic FROM ' . TOPICS_TABLE . ' WHERE forum_id = ' . (int) $fid;
$result = $this->db->sql_query($sql);
[/code]

[valdeny]
The public discussion topic and the changelog topic are both rediscovered with [c]MAX(topic_id)[/c] after [c]submit_post()[/c]. Another request can create a topic between those operations, causing the article to store an unrelated topic ID. [c]submit_post()[/c] updates the referenced [c]$data[/c] array with [c]topic_id[/c]; use that value in both paths.
[/valdeny]

[hr][/hr]

In [c]event/listener.php[/c]:

[code]
$forum_row['FORUM_NAME'] = htmlspecialchars_decode($this->config_text->get('kb_forum_prefix'), ENT_HTML5) . ' ' . $forum_row['FORUM_NAME'];

$topic_row['TOPIC_TITLE'] = htmlspecialchars_decode($this->config_text->get('kb_topic_prefix'), ENT_HTML5) . ' ' . $topic_row['TOPIC_TITLE'];
[/code]

[valdeny]
These values were stored through phpBB's request API in an HTML-safe form. Decoding them before assigning them to template variables allows a crafted administrator-supplied prefix to inject active markup into the forum index or topic list. Keep the prefixes escaped, or accept only a narrowly validated formatting syntax.
[/valdeny]

[hr][/hr]

In [c]controller/approve.php[/c]:

[code]
$reason = $this->request->variable('reason', '', true);
$skip_message = $this->request->variable('skip_message', false);

if ($reason === '' && !$skip_message)
{
	trigger_error(sprintf($this->language->lang('KB_REASON_REQUIRED'), '&lt;a href="' . $article_url . '"&gt;', '&lt;/a&gt;'), E_USER_WARNING);
}
[/code]

[valdeny]
The form requires a reason or an explicit decision not to message the author, but delivery uses a notification method that is disabled by default and controlled by the recipient's notification preferences. The required reason is therefore not reliably delivered. Send a required moderation PM directly, or change the form and language so they do not promise delivery.
[/valdeny]

[hr][/hr]

In [c]styles/prosilver/template/kb_queue_body.html[/c]:

[code]
&lt;form method="post" action="{{ art_row.S_ACTION }}"&gt;
	{{ S_FORM_TOKEN }}
	&lt;select name="action"&gt;
		&lt;option value="deny"&gt;{{ lang('DISAPPROVE') }}&lt;/option&gt;
	&lt;/select&gt;
&lt;/form&gt;
[/code]

[valdeny]
The queue offers approve and deny actions but supplies neither [c]reason[/c] nor [c]skip_message[/c]. The controller rejects those submissions, so the choices cannot complete from this page. Link these choices to the full approval form or add the required controls here.
[/valdeny]

[hr][/hr]

In [c]controller/kb_file.php[/c]:

[code]
$filename = preg_replace(['/[^\x20-\x7e]/', '/%/', '/\//', '/\\\/'], '', $filename);
return (!empty($filename)) ?: 'File';
[/code]

[valinfo]
For a non-empty cleaned filename, this expression returns boolean [c]true[/c], not the filename. The Content-Disposition fallback can therefore become [c]1[/c]. Return [c]$filename ?: 'File'[/c].
[/valinfo]

[hr][/hr]

In [c]controller/category.php[/c]:

[code]
$first_letter = $this->request->variable('l', '', true);

$sql_where .= ' AND a.article_title LIKE "' . $this->db->sql_escape($first_letter) . '%"';
[/code]

[valinfo]
The text operand of [c]LIKE[/c] is delimited with double quotes. SQL modes and database engines do not treat double quotes consistently as string delimiters. Use a single-quoted SQL string literal while retaining [c]sql_escape()[/c].
[/valinfo]

[hr][/hr]

In [c]controller/acp_controller.php[/c]:

[code]
$cfg_array = (isset($_REQUEST['config'])) ? $this->request->variable('config', ['' => ''], true) : [];
$updated = $this->request->variable('updated', false);
[/code]

[valinfo]
The direct [c]$_REQUEST[/c] access bypasses phpBB's request abstraction. Use the request service to test for the variable, or call [c]variable()[/c] directly with the intended default.
[/valinfo]

[hr][/hr]

In [c]migrations/version_1_0_2.php[/c]:

[code]
$this->db->sql_query($sql);
$result = $this->db->sql_query($sql);
while ($row = $this->db->sql_fetchrow($result))
{
	$res = $this->db->sql_query($sql);
}
[/code]

[valinfo]
The category query is executed twice, with the first result discarded. The method then performs a select for every category and an update for every article. This is an N+1 query pattern that can make migration time grow sharply on larger installations. Historical released migrations should not be edited in place; account for this when planning any supported migration replacement or maintenance path.
[/valinfo]

[hr][/hr]

In [c]migrations/version_2_0_10.php[/c]:

[code]
public function update_data()
{
	return [
		['config.update', ['kb_version', '2.0.10']],
	];
}
[/code]

[valinfo]
The extension version is still stored in phpBB configuration, and no later migration removes [c]kb_version[/c]. Use migration dependencies and package metadata for version state, then remove this config value in a future migration.
[/valinfo]

[hr][/hr]

In [c]composer.json[/c]:

[code]
"version": "2.0.7",

"require": {
	"phpbb/phpbb": ">=3.3.11"
}
[/code]

[valdeny]
The package declares version 2.0.7 while this tree contains migrations through 2.0.10. Update the package version to the intended release before submission so installed migration state and release metadata agree.
[/valdeny]

[valinfo]
[c]composer validate --no-check-publish[/c] reports both the explicit [c]version[/c] field and the unbounded [c]phpbb/phpbb[/c] constraint as warnings. The manifest also duplicates the phpBB requirement under [c]extra.soft-require[/c]. Keep one bounded compatibility declaration in the skeleton's expected location.
[/valinfo]

[hr][/hr]

In [c]language/en/acp_knowledgebase.php[/c]:

[code]
'KB_CONFIG_UPDATED' => 'Settings successfully updated.',
'NO_CAT_DESCR'      => 'You have not created category description.',
'LOG_CATS_SYNC'     => '&lt;strong&gt;Synchronized category&lt;/strong&gt;&lt;br&gt; %1s',
[/code]

[valinfo]
The following keys have no use outside their definitions: [c]KB_CONFIG_UPDATED[/c], [c]NO_CAT_DESCR[/c], [c]LOG_CATS_MOVE_DOWN[/c], [c]LOG_CATS_MOVE_UP[/c], [c]LOG_CATS_DEL_CATS[/c], and [c]LOG_CATS_SYNC[/c]. Remove dead keys and their translations, or restore their intended call sites.
[/valinfo]

[hr][/hr]

In [c]language/en/knowledgebase_lng.php[/c]:

[code]
'ARTICLE_BODY'         => 'Article text',
'ARTICLE_BODY_EXPLAIN' => 'Enter text of article',
'EMPTY_QUERY'          => 'You have not entered any search query',
[/code]

[valinfo]
The following keys have no use outside their definitions: [c]ARTICLE_BODY[/c], [c]ARTICLE_BODY_EXPLAIN[/c], [c]COULDNT_GET_CAT_DATA[/c], [c]COULDNT_UPDATE_ORDER[/c], [c]EMPTY_QUERY[/c], [c]NO_DESCR[/c], [c]NO_NEED_APPROVE[/c], [c]RETURN_LIBRARY[/c], [c]RETURN_NEW_CAT[/c], [c]RULES_KB_ADD_NOAPPROVE[/c], and [c]RULES_KB_ADD_NOAPPROVE_CANNOT[/c]. Remove dead keys and their translations, or restore their intended call sites.
[/valinfo]

[hr][/hr]

In the source notes:

[code]
Reading Article #1" [trailing space]
Knowledge Base article page [trailing space]
[/code]

[valinfo]
These lines contain trailing whitespace. This is a formatting issue and does not cause denial by itself.
[/valinfo]

[hr][/hr]

Recommendation: Deny
