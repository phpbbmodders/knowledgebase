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

## Database Search Validation

Completed: 10/03/2026.

Original task: execute the repaired fulltext search and index lifecycle,
including the `version_2_0_15` index repair, against MySQL and PostgreSQL.

The full regression suite passed with 67 tests and 316 assertions using
disposable MySQL 8.0.42 and PostgreSQL 16.15 databases. Coverage includes
index creation/deletion/recreation, immediate index-status refresh,
Knowledgebase-only result-cache clearing, article statistics, keyword
and author filters, approval filtering, cross-listed categories, field
scopes, empty results, pagination beyond the last result, transaction
cleanup, and repeatable legacy-index repair without deleting matches.

SQLite legacy-schema tests remain part of the suite. See
[Testing](../../testing.md) for repeatable commands. The integration tests
skip when their fixture ports are not provided. GitHub CI currently runs
coding and extension validation checks, not this database suite.

These tests do not establish complete fresh-install/upgrade/purge
coverage or live browser/notification delivery coverage. No live-board
database was reset or modified for this validation.

<a id="authorship-and-revisions-todo"></a>

## Multi-author, edit-conflict locking, moderator-author messaging, and related gaps

Archived: 10/05/2026. Completed: items 1-7 were done 09/25/2026; the "Your articles" author column, a follow-up to item 1, was done 10/05/2026. One note stays open by choice: the board-index category-card view shows only a one-article teaser per category and was not changed. The detailed plan follows unchanged except that its headings are one level deeper.

rebuild of its Knowledge Base (a Symfony app, not a phpBB extension).
Reviewed and compared against the current extension state in chat
before this file was written; scoped here rather than implemented,
pending a go-ahead. Distinct from
[the phpbb.com KB parity gaps TODO](#phpbb-com-kb-parity-gaps) - that one
covers small UI/validation polish found via screenshot comparison,
these are substantial new-feature clusters found via the real PR
descriptions. Items 1-4 are the major new-feature clusters; items 5-7
were originally flagged as "worth confirming" against the live code and
have since been verified as real, confirmed gaps - each now has its own
scope writeup with the exact code checked.

### 1. Multi-author support

**Done (09/25/2026)**, with two deliberately deferred pieces noted at
the end. Decisions (asked in chat): moderators only (matching the reference text
and the existing "post as team" gate) can add/remove co-authors, via a
comma-separated username text field plus a checkbox list of the
existing configured KB team groups (not phpbb.com's single
mixed-content free-text field). Design departure from phpbb.com,
grounded in this extension's own existing precedent rather than copied
blind: no "KB bot" placeholder is needed, since this extension's
`author_type` already lets the primary author be a group on its own
(unlike phpbb.com's model, which always needs a real user in
`author_user_id`). Implemented:

- `migrations/version_2_0_10.php` - new `kb_article_co_authors` table
  (`article_id`, `author_type`, `author_ref_id`), mirroring
  `kb_article_categories`' exact shape (a join table alongside the
  primary field already on `kb_articles`) - the closest, most
  directly-analogous precedent already in this codebase for a
  "one article, many related X" relationship, found by grep rather than
  inventing a new pattern.
- `inc/functions_kb.php` - `is_article_author()` (the replacement for
  every single-author ownership check - a targeted query against
  `kb_article_co_authors` plus at most one `is_team_member()` call, not
  a full flatten-then-check, matching the existing pattern's own
  efficiency characteristics), `get_co_authors()`/`set_co_authors()`
  (replace-all, deduped against the primary author, mirroring
  `set_cross_listed_categories()`), `get_co_authors_display()`
  (resolved for template output - user co-authors linked to their
  profile, group co-authors in their colour linked to their member
  list, verified the real `mode=group&g=` memberlist URL against phpBB
  core source rather than guessing), and `resolve_usernames()`/
  `get_usernames()` (forward/reverse username-to-id lookups using
  `utf8_clean_string()`, the same case/whitespace-insensitive
  comparison phpBB itself uses).
- Ownership checks rewritten in all four places that actually had one -
  `controller/posting.php`'s `is_own_article`, `article.php`'s
  visibility check, `category.php`'s per-row edit/delete flags (a
  fourth occurrence this item's original scope note missed - found by
  grep before starting, not assumed), and `index.php::my_articles()`'s
  `sql_where` (extended with an `IN (SELECT ...)` subquery, since that
  one's a SQL-level check across many rows, not a single PHP boolean).
- Setting co-authors: `controller/posting.php`, gated behind the same
  `acl_kb_get($cat_id, 'kb_m_approve')`/`a_manage_kb` check that already
  gates "post as team" - read only inside that permission gate
  regardless of what a non-moderator's request actually contains,
  matching how `post_as_group` already enforces this server-side, not
  just by hiding the UI. Available on both submission and a direct edit
  of a not-yet-published article; deliberately *not* wired into the
  revision-save path, matching the reference text's "a revision cannot change the
  authors." An unresolved username is a hard `trigger_error()` stop
  before any write happens, not a soft `$error[]` entry - traced through
  and confirmed this whole save branch ends in a `trigger_error()`
  success redirect that never looks at `$error`, so that would have
  silently swallowed the validation message entirely.
- The edit form pre-fills both fields from the article's actual current
  co-authors on a fresh GET load (not on a preview/failed-submit
  re-render, which show back what was just typed instead) - without
  this, `set_co_authors()`'s replace-all semantics would silently wipe
  existing co-authors the moment a moderator resubmitted the form
  without retyping them.
- Display: the article page shows every co-author, linked, groups in
  their colour (`controller/article.php`,
  `styles/prosilver/template/kb_article_body.html`). The category
  listing (`controller/category.php`,
  `styles/prosilver/template/kb_cat_body.html`) now also shows every
  co-author next to the primary author, comma-separated, same
  linked/coloured treatment (09/25/2026 follow-up - this was the first
  of the two originally-deferred items below).
- New `KB_CO_AUTHORS`/`_EXPLAIN`/`KB_CO_AUTHOR_GROUPS`/
  `KB_UNKNOWN_USERNAME` language keys in en/ru/fr/cs.

**"Your articles" author column - done (10/05/2026).**
`index.php::my_articles()` ("Your articles") originally showed no author,
on the reasoning that every row was the viewer's own article, and was left
as-is when co-authors were added - only its query was extended to include
co-authored articles. That reasoning stopped holding once co-authored and
team-credited articles appeared in the list: a row could be someone else's
article with nothing saying so. The page now has an Author column that shows
the primary author followed by the co-authors, the same way the category
listing does (co-author lookups batched into one query per page).

**Still not done, on purpose:**
- The board-index category-card view (`index.php::main()`) shows only its
  one most-recent-article-per-category teaser, not a full listing, and
  wasn't touched.

**Query batching (09/25/2026 follow-up) - now done.** `category.php`'s
per-row listing loop previously ran up to four separate queries per
displayed article (`is_article_author()`'s co-author lookup,
`get_co_authors_display()`'s redundant co-author lookup, plus a
`USERS_TABLE` and a `GROUPS_TABLE` query), worse than a single "extra
query" - a real, page-size-multiplied cost. Fixed with bulk backend
methods, each running exactly one query per page load regardless of row
count:

- `inc/functions_kb.php` - `get_co_authors_bulk(array $article_ids)`
  (one `kb_article_co_authors` query with `WHERE article_id IN (...)`,
  grouped in PHP), `is_article_author_bulk(array $articles, ?int
  $user_id = null)` (one `get_co_authors_bulk()` call plus one
  `get_user_team_groups()` call for the whole page), and
  `get_co_authors_display_bulk(array $article_ids)` (one `USERS_TABLE`
  and one `GROUPS_TABLE` query across every requested article, not one
  pair per article). The existing single-article
  `get_co_authors()`/`get_co_authors_display()` now delegate to their
  bulk counterparts with a one-element array, so no caller outside
  `category.php` needed to change.
- `controller/category.php::cat()` - the listing loop now runs in two
  passes: the result set is collected into `$art_rows` first, the three
  bulk methods are called once each outside any loop, then a second
  pass over `$art_rows` builds each `art_row` block var from the
  returned maps instead of making a per-row DB call.

phpbb.com's KB credits an article to any mix of several forum users and
team groups, not just one. This extension's schema
(`migrations/version_1_0_0.php`) supports exactly one author: a single
`author_id`/`author`/`author_type` (user or group) triple - confirmed
directly against the schema and `controller/posting.php`'s author
handling, not assumed.

phpbb.com's model:
- `author_user_id` stays the *first* author (unchanged on revisions).
- Two separate JSON lists hold co-author users and co-author groups.
- "Who counts as an author": anyone directly credited, plus any
  *approved* (not pending) member of a credited group that's still a
  team group. Authors can see their article while hidden, edit it, and
  find it under "Your articles" - the same rights this extension's
  `KbAuthorship`-equivalent single-author check already grants today,
  just extended to a set instead of one id.
- Who can change authors: only the team, via a text field accepting
  comma-separated forum usernames and team group names ("phpBB Team"
  as the bot-equivalent). A revision cannot change the authors.
- Groups-only credit: the KB bot (or an equivalent placeholder) becomes
  the first author internally, not shown in the UI.
- Display: article page, listings, and "Your articles" show every
  author; groups shown in their colour, linked to their member list.

Scope: a real schema change (new tables or JSON columns for co-author
users/groups, alongside the existing single `author_id`), a rewrite of
every current single-author ownership check
(`controller/posting.php`'s `is_own_article`, `article.php`'s
visibility check, `index.php::my_articles()`'s `sql_where`) to test set
membership instead of equality, and template changes everywhere
`ARTICLE_AUTHOR`/similar is currently rendered as one value. This is
the largest item in this file - probably its own multi-step
implementation, not a single pass.

### 2. Edit-conflict detection (optimistic locking)

**Done (09/25/2026).** Used the revision's existing `submitted_date`
column as the fingerprint - no new schema needed. Implemented in
`controller/posting.php::post_article()`:

- A fresh GET-load of the edit form for a `STATUS_ACTIVE` article reads
  the *current* pending revision's `submitted_date` (0 if none) into
  `$revision_fingerprint`, carried through the form as a hidden
  `revision_fingerprint` field (`kb_post_body.html`).
- A preview or submit instead keeps whatever fingerprint the form
  itself already started with (read from the submitted hidden field),
  so the conflict check compares against the *original* basis the
  editor opened, not a value that's already been silently refreshed.
- Right before `save_article_revision()` - which itself unconditionally
  deletes-and-replaces whatever revision is currently there - the
  submitted fingerprint is compared against the revision's actual
  current `submitted_date`. A mismatch means someone else's revision
  landed in between; the save is refused via `trigger_error()` (new
  `KB_REVISION_CONFLICT` key) rather than silently overwritten.
- Scoped to exactly the case the reference text describes and this item's own
  scope named: editing a *live* (`STATUS_ACTIVE`) article, where the
  edit becomes a pending revision. Editing a not-yet-published
  (`NEW`/`REVIEWED`) article still writes directly to the article row
  with no equivalent lock - noted as a related but explicitly
  out-of-scope gap, not fixed here, since neither the reference text nor this
  item's own scope covered it and two people concurrently editing an
  article nobody but its own author has seen yet is a much rarer
  scenario.

phpbb.com's edit form carries a fingerprint of the revision it started
from. If the pending revision is replaced (someone else's save lands
first) while a second editor is still working, their save is refused
rather than silently overwriting the newer revision.

This extension currently only has a boolean check -
`article_has_pending_revision()` (`inc/functions_kb.php:1025`) - which
tells a caller *whether* a revision exists, not whether it's the
*same* revision they started editing against. Two people opening the
same pending revision to edit it and both saving would let the second
save silently clobber the first with no warning, regardless of
authorship.

Scope: carry a fingerprint (e.g. a hash of the revision's id + its own
last-modified timestamp, or a monotonic revision counter) as a hidden
field through `posting.php`'s edit form, and check it against the
current stored value before applying the save - refusing with a clear
"this was changed since you opened it" error rather than overwriting.
Independent of #1 - worth doing regardless of whether multi-author
lands.

### 3. Real PM to the author, not just a bell notification

**Done (09/25/2026).** Decisions (asked in chat): both the bell and a
real PM, delivered through phpBB's actual notification-method
framework so the author gets a real UCP toggle for it (Notification /
Email / Jabber / **PM**), not a hardcoded always-on PM. Reason required
or explicit skip, same as phpbb.com. Implemented:

- `notification/method/pm.php` (new) - extends
  `\phpbb\notification\method\base`, the same base class core's own
  `email`/`jabber` methods extend. `is_available()` scopes to just this
  extension's `approve`/`disapprove` types (not every notification type
  board-wide) - the same per-type scoping mechanism email already uses
  to only apply to types with an email template. `notify()` sends via
  core's real `submit_pm()`, skipping any notification whose reason is
  empty (the explicit-skip case). Registered in `config/services.yml`
  tagged `notification.method`, matching core's own
  `notification.method.email`/`.jabber` registration exactly.
- `notification/type/approve.php` / `disapprove.php` - `is_available()`
  flipped from a hardcoded `false` (with dead commented-out logic in
  `disapprove.php`) to `true`, since a type has to be
  UCP-configurable before any method's per-user toggle can exist for it
  at all. Added `get_reason()` (a required public accessor -
  `get_data()` itself is `protected`, caught this via a real read of
  phpBB core's `notification\type\base` rather than assuming a magic
  property or protected call would work) plus `get_pm_subject()` /
  `get_pm_message()` for the PM method to call.
- `controller/approve.php` - `approve_article()` validates
  reason-or-explicit-skip for `approve`/`deny` before running the
  action; on failure, the error links straight to the article's own
  approve page (the only place the reason field exists), since a
  queue-row action has no reason field to submit and would otherwise
  hit the same validation with no way to explain why.
- `styles/prosilver/template/kb_approve_body.html` - reason
  textarea + "Don't message the author" checkbox, shown whenever
  approve or deny is available.
- New `KB_PM_*`/`KB_REASON_*`/`KB_SKIP_MESSAGE`/
  `NOTIFICATION_METHOD_PHPBBMODDERS_KNOWLEDGEBASE_PM` language keys in
  en/ru/fr/cs (ru/fr/cs AI-translated, unreviewed by native speakers).

phpbb.com sends an actual phpBB private message to the author(s) when
a moderator acts on their article - prefilled, with a mandatory reason
(`{PROVIDE A REASON!}` must be replaced or the message explicitly
skipped), sent through the forum's own PM system from the acting team
member. The action and its log entry save first; whether the PM itself
sent, and its text, are logged separately so a failed send can be
redone by hand. Once multi-author (#1) lands, each user-author gets
their own copy - the bot, groups, and deleted accounts get none.

This extension's current author-facing feedback on approve/deny
(`notification/type/approve.php`, `notification/type/disapprove.php`)
is the phpBB notification-bell system, not an actual PM. Worth an
explicit decision, not an assumption that the bell already covers
this - a PM is more visible (email-forwardable, not tied to being
logged in with the bell noticed) and lets the moderator write real
context, versus a fixed notification-bell string.

Scope: if wanted, a new step in `controller/approve.php`'s action
handlers using phpBB's own `submit_pm()`-equivalent core flow, a reason
textarea in `kb_approve_body.html`/`kb_queue_body.html` with a "skip
message" option, and separate logging of send success/failure the way
phpbb.com does. Could replace or supplement the existing bell
notification - needs a decision either way before scoping further.

### 4. Automatic per-article changelog thread

**Done (09/25/2026).** Decision (asked in chat): a real second,
moderator-only topic per article (closer to phpbb.com's actual
design), not folded into the existing admin log. Scoped to exactly what
the reference text's own "Editing" section and this item's own scope text describe
- submission and every edit/revision-submit - not the full
`approve.php` moderation-action lifecycle (approve/deny/deactivate/
etc.), which the reference text describes separately under "Administration" without
mentioning topic-posting at all. Implemented:

- `migrations/version_2_0_9.php` - adds `kb_articles.changelog_topic_id`
  and a new `kb_changelog_forum_id` config (default `0` = disabled,
  same optionality as the existing `kb_forum_id`/`kb_anounce` public
  comments topic).
- `inc/functions_kb.php::post_changelog_entry()` (new) - lazily creates
  the topic (titled after the article) the first time it's called for
  that article, replies to it on every later call. Best-effort: does
  nothing if the config is unset or points at a forum that no longer
  exists, deliberately *not* a hard prerequisite of submitting or
  editing (unlike phpbb.com's own stricter "nothing saves if that post
  fails" guarantee) - matching how this extension's existing sibling
  mechanism (the public comments topic) is already optional/best-effort
  too, not adding a new failure mode the codebase doesn't already have
  elsewhere. Reuses the exact `submit_post()` call shape
  `submit_article()` already uses for the public comments topic, traced
  from phpBB core source (`'post'` vs `'reply'` mode, what
  `$data_ary['topic_id']` needs to be for each) rather than guessed.
- `controller/posting.php` - three call sites: new article submission,
  revision submitted (live article), and direct edit (not-yet-published
  article) - each posts its own changelog message.
- ACP settings (`controller/acp_controller.php`,
  `adm/style/acp_knowledgebase_config.html`) - a "Changelog forum"
  forum-picker, independent of the existing announcement-forum toggle
  (this one isn't gated behind `kb_anounce`, since it's a separate
  feature with its own on/off via being unset).
- New `KB_CHANGELOG_FORUM`/`_EXPLAIN` (ACP) and
  `KB_CHANGELOG_REPLY_SUBJECT`/`KB_CHANGELOG_SUBMITTED`/
  `KB_CHANGELOG_REVISION_SUBMITTED`/`KB_CHANGELOG_EDITED` (user-facing)
  language keys in en/ru/fr/cs.

Every save on phpbb.com posts to a dedicated queue-topic thread for
that article ("New revision submitted" / "Article edited", with a
changelog against the published version and a link to the revision).
Nothing saves if that post fails. Articles from before this existed
get their first topic created on their next edit.

This is a different mechanism from what already exists here: this
extension's `topic_id`/`kb_forum_id` (`controller/article.php`,
`inc/functions_kb.php`) is the article's *public comments* topic, not
an internal moderation-changelog thread. Worth being explicit about
that distinction before scoping - reusing the comments topic for
changelog replies would mix public and moderator-only content in the
same thread, which phpbb.com's design deliberately avoids by using a
separate queue forum.

Scope: needs a decision on whether this extension should introduce a
second, moderator-only topic per article (more forum-structure
overhead, closer to phpbb.com's actual design) or fold a lighter
version of the same idea into the existing revision/approval log
already recorded via `ACTION_EDIT`/`ACTION_REVISION`-equivalent
logging - the log already exists in some form, whether it's
forum-topic-visible is the open question.

### 5. "By category" team-queue fallback

**Done (09/25/2026).** Decision (asked in chat): one team per category,
a single `default_team_id` column, no separate override UI. Implemented:

- `migrations/version_2_0_8.php` - adds `kb_categories.default_team_id`.
- `inc/functions_kb.php` - new `get_effective_team_id(int $assigned_team_id, int $category_id): int`
  (explicit wins, falls back to the category's `default_team_id`,
  short-circuits before the category lookup when already explicit) and
  `get_team_options(int $selected_team_id): string` (dropdown markup,
  shared with the existing per-article assign dropdown).
- ACP category editor (`acp/manage_module.php`,
  `controller/acp_controller.php::update_category_data()`,
  `adm/style/acp_knowledgebase_category.html`) - a "Default team"
  dropdown on the category add/edit form, validated against
  `get_kb_team_groups()` the same way the existing per-article assign
  action already is.
- `controller/article.php::show()`'s `$can_review` and
  `controller/approve.php`'s `queue()`, `approve_article()`, and
  `do_review()` all now use the effective team - fixing not just the
  display flags but the actual `do_review()` permission gate, which
  previously blocked review entirely (even for a full category
  moderator) until *someone* manually ran the "Assign" action first.
- The existing "Assign" button/action is untouched and still available
  for an explicit per-article override even when a category default
  applies - only *whether an article can be reviewed* now honours the
  fallback, not *whether the Assign button shows*.
- New `CAT_DEFAULT_TEAM`/`_EXPLAIN`/`_NONE` language keys added to
  en/ru/fr/cs (ru/fr/cs are AI-translated, unreviewed by native
  speakers, matching this extension's existing translation practice).

Confirmed a real gap, not just an access-control overlap. Searched the
whole codebase for anything joining `article_category_id` to a team:
`assigned_team_id` only exists as a per-article column
(`migrations/version_2_0_3.php`), set purely manually via
`approve.php`'s `set_team()`/dropdown, defaulting to `0`
(unassigned). There's no `kb_categories` column or table anywhere
associating a category with a default team, and nothing computes one at
read time. `approve.php::queue()`'s `$moderatable` scoping is *access
control* (which categories a moderator may act on) - a completely
separate mechanism from phpbb.com's *default bucket assignment* (which
named team an unassigned article's queue row groups under). An
unassigned article here has no team association at all until a
moderator manually sets one.

Scope: a new per-category "default team" column (`kb_categories`
migration), a fallback in wherever an article's effective team is read
(`approve.php::queue()`'s per-row team display, `article.php`'s
`$can_review`) - explicit `assigned_team_id` wins when set, falling
back to the category's default team otherwise. Needs a decision on
whether categories map to teams one-to-one or need their own override
UI in the ACP category editor.

### 6. 404 fingerprinting - hidden articles ARE distinguishable from missing ones

**Done (09/25/2026).** Decision (asked in chat): show the same
"Notice: article doesn't exist" page rather than keep the silent
bounce-to-category redirect. `CAT_NO_EXISTS` was deliberately left as
its own separate error path, not collapsed in - only the "exists but
hidden from this viewer" branch changed. Fixed in
`controller/article.php::show()` - the `redirect()` call is now
`return $this->render_notice('ARTICLE_NO_EXISTS');`, the same call the
"truly missing" branch already used.

Confirmed a real, if narrow, information-disclosure gap, not just a
naming difference. Traced `controller/article.php::show()`'s full
branch set:

- Truly-missing article (`empty($row)`, line 136-138): renders the
  templated "Notice" page via `render_notice('ARTICLE_NO_EXISTS')`.
- **Exists but hidden from the current viewer** (unapproved and not a
  moderator/reviewer/author, line 153-156): `redirect()`s to the
  article's own category page - a 302 to a different destination, with
  no notice page at all.
- Article's own category has since been deleted (line 170-172):
  `trigger_error(CAT_NO_EXISTS)`, phpBB's generic error box.
- No `u_kb_view` permission at all (line 115-117): `trigger_error(NOT_AUTHORISED)`, also generic.

These four outcomes are three genuinely different response
shapes (a rendered notice page vs. a redirect vs. two different
generic error boxes), not a unified 404. Concretely: probing article
ids by number, an unprivileged user can already tell "this id doesn't
exist" (notice page) apart from "this id exists but I can't see it"
(redirect to its category) purely from the response type - exactly the
distinction phpbb.com's design collapses into one identical outcome so
a hidden article can't be fingerprinted this way. Low severity (reveals
existence, not content), but a real, confirmed gap.

Scope: route the "exists but hidden" branch through the same
`render_notice('ARTICLE_NO_EXISTS')` call the "truly missing" branch
already uses, instead of `redirect()`-ing to the category page. Needs a
decision on whether losing the current "bounce to category" UX
(arguably a mild convenience today) is an acceptable tradeoff for
closing the fingerprinting gap - the missing-article and
category-deleted paths could likely collapse into the same notice too,
for full parity with phpbb.com's "one 404 for every reason" design.

### 7. View-count increment is a read-then-write race, not atomic

**Done (09/25/2026).** Fixed in `controller/article.php` - both spots
below now use `SET views = views + 1` directly in the SQL, with the
PHP-side `$views`/`$views++` write-path variables removed (the
`$views = $row['views'];` display variable used for the `VIEWS`
template var stays, since it still needs the pre-increment count for
this page load).

Confirmed. Both places this extension increments a view count use the
same non-atomic pattern - read the count into a PHP variable earlier
in the request, increment it in PHP, write the literal computed number
back:

`controller/article.php:158-164` (redirect articles):
```php
$views = (int) $row['views'] + 1;
$sql = 'UPDATE ' . $this->articles_table . '
    SET views = ' . $views . '
    WHERE article_id = ' . (int) $art_id;
```

`controller/article.php:291-298` (regular articles, correctly gated to
`$mode != 'print' && $row['approved']` - that part already matches
phpbb.com's "only public articles count" rule):
```php
if ($mode != 'print' && $row['approved'])
{
    $views++;
    $sql = 'UPDATE ' . $this->articles_table . '
        SET views = ' . $views . '
        WHERE article_id = ' . (int) $article;
    $this->db->sql_query($sql);
}
```

Two concurrent requests for the same article can both read the same
starting count, both compute the same `+1`, and both write the same
result back - losing one increment under real concurrent traffic on a
popular article, unlike phpbb.com's database-level `UPDATE ... SET
views = views + 1`, which lets the database serialize the increment
itself regardless of how many requests land at once.

Scope: small, mechanical fix in both spots - replace the read-then-write
with `'SET views = views + 1'` directly in the SQL (dropping the PHP-side
`$views`/`$views++` entirely), matching the article's own
last-modified-time-is-untouched behavior that's already correct today.

<a id="claude-code-review-2026-09-30"></a>

## Claude Full Codebase Review - 2026-09-30

Archived: 10/05/2026. Review date: 09/30/2026. All 12 findings were resolved (pull request #17) and the checks that followed were recorded in the testing notes; the final verdict is below. The original review follows unchanged except that its headings are one level deeper and its links to `testing.md` and `TODO.md` now point two folders up.

Scope: the complete current working tree at
`/home/william/Desktop/repos/knowledgebase`, on `main` at `98af128`, including
unchanged tracked code and all current uncommitted changes. The review covered
the extension entry points, public and ACP controllers, services, permissions,
notifications, search backends, migrations, templates, JavaScript, and package
configuration. Documentation and language files were checked where they affect
runtime behavior or the current changes.

Verdict (original, at `98af128`): **Changes required.**

Final verdict (10/05/2026): **all 12 findings resolved.** The fixes were merged
in pull request #17; the commit for each is listed under its heading. The
finding text below is the original review and is left unchanged. Defects found
by the later browser checks were fixed separately (pull requests #20, #21 and
#24) and are described in [testing.md](../../testing.md).

### Findings

#### P1 - Destructive ACP actions do not validate CSRF tokens

**Status: resolved in d7b0c6c.** Form-token and link-hash checks added to the ACP actions; regression tests in `tests/kb_acp_csrf_test.php`. In a browser check on 10/05/2026, 17 forged requests from an administrator session were rejected and genuine requests were accepted (see [testing.md](../../testing.md)).

Locations: `acp/fields_module.php:42-57`,
`controller/acp_controller.php:707-805`,
`controller/acp_controller.php:845-942`,
`controller/acp_controller.php:980-1026`,
`controller/acp_controller.php:1317-1431`,
`controller/acp_controller.php:3174-3203`, and
`controller/acp_controller.php:3264-3272`

Several ACP paths mutate data or delete files without calling
`check_form_key()` or `check_link_hash()`:

- Custom-field creation, enable/disable, value creation, and value deletion.
- Orphan attachment deletion and reassignment.
- Extra-file deletion and lost-file database cleanup.
- Search-index creation, deletion, continuation, and cancellation.

Some templates emit `S_FORM_TOKEN`, and the search module calls
`add_form_key('kb_acp_search')`, but the receiving controller never validates
those tokens. The search link-hash check is present only as commented-out code.
The custom-field toggle is also a state-changing GET link with no hash.

Failure scenario: an authenticated administrator can be induced to submit or
visit a forged ACP request that changes custom fields, removes attachment files
or rows, or starts/deletes a search index.

Repair direction: add a dedicated form key for every ACP form, validate it
before any mutation, and use `check_link_hash()` for state-changing links.
Carry a validated token or hash through each batched/meta-refresh continuation.

#### P1 - The custom-field add form posts to an undefined action variable

**Status: resolved in d7b0c6c.** The template now uses `U_ACTION`, which the controller assigns. A forged add-field request was rejected, and the form itself was submitted successfully, in browser checks on 10/05/2026.

Locations: `controller/acp_controller.php:1306-1311` and
`adm/style/acp_knowledgebase_fields.html:4-4`

`show_fields()` assigns the add endpoint to `U_ACTION`, but the template's
form uses `S_ACTION`. No code in the fields module assigns `S_ACTION`.

Failure scenario: submitting the add-field form from the normal field list
posts back to the current page without `action=add_field`. The module takes its
default `show_fields()` branch, so the requested field is not created and the
form appears to do nothing.

Repair direction: use one template variable consistently, preferably
`U_ACTION` to match the controller, and add an ACP functional test that creates
each supported field type. This is separate from the missing CSRF validation
above; both defects must be fixed.

#### P1 - MySQL keyword search sends PHP variable names as literal SQL

**Status: resolved in 289f621.** The query is now built by concatenation, with the search string quoted and escaped. Tests: `tests/kb_search_sql_test.php`, and `tests/kb_search_database_test.php` against a real MySQL server.

Location: `search/backend/kb_fulltext_mysql.php:451-456`

The final query is built with a single-quoted PHP string containing
`$sql_select`, `$sql_match`, `$sql_where_options`, and `$sql_sort`. Those
variables are not interpolated; their names are sent literally to MySQL.

Failure scenario: any keyword search using the MySQL full-text backend produces
invalid SQL. Because the method enables `sql_return_on_error(true)`, the failure
can be masked as empty or unusable search results instead of a clear error.

Repair direction: concatenate the validated SQL fragments, as the other
backends do, and add a MySQL integration test that creates the index and runs a
keyword query with category, author, date, and sort options.

#### P1 - PostgreSQL index creation targets the posts table with invalid columns

**Status: resolved in 289f621** (index creation targets the articles table with per-column names) **and ca00963** (index create/delete/recreate lifecycle and stats reset). Tests: `tests/kb_search_database_test.php` against a real PostgreSQL server.

Locations: `search/backend/kb_fulltext_postgres.php:617-635` and
`search/backend/kb_fulltext_postgres.php:743-763`

`create_index()` creates indexes on phpBB's `POSTS_TABLE`, but refers to
Knowledge Base columns such as `article_title`, `article_description`, and
`article_body`. It also refers to a nonexistent `article_content` column. Two
indexes reuse the same `_post_content` name. Finally, the names created by this
method use `_post_*`, while `get_stats()` only recognizes `_article_*` names.

Failure scenario: the PostgreSQL ACP "Create index" action fails on the first
invalid column. Even if the target table were corrected independently, the
duplicate and mismatched names would prevent complete index creation and
detection.

Repair direction: create distinct GIN expression indexes on
`$this->articles_table` using only real article columns. Use one naming scheme
consistently in creation, detection, and deletion, then cover the complete
PostgreSQL index lifecycle with an integration test.

#### P1 - Combined keyword and author search appends an undefined author ID

**Status: resolved in 289f621.** `controller/search.php` filters on `a.author_id` with `sql_in_set()`; the MySQL and PostgreSQL backends quote the author name. Tests: `tests/kb_search_sql_test.php`, `tests/kb_search_database_test.php`.

Location: `controller/search.php:316-325`

When both `$author` and `$keywords` are supplied, the final result query appends
`AND author_id = $author_id`, but `$author_id` is never defined. The controller
has an `$author_id_ary`, and the selected search backend already receives that
array.

Failure scenario: a combined author-and-keyword search emits a PHP warning and
builds an invalid or incomplete SQL predicate, so otherwise valid matches are
not returned.

Repair direction: remove the redundant final condition if backend results are
author-filtered, or use `sql_in_set('a.author_id', $author_id_ary)` with the
qualified column name.

#### P1 - Edit and delete incorrectly require article-add permission

**Status: resolved in ebcb42c.** The add-permission gate in `controller/posting.php` now applies only to add mode. Tests: `tests/kb_posting_permissions_test.php`. Editing and deleting were also exercised in a browser on 10/05/2026.

Location: `controller/posting.php:187-204` and
`controller/posting.php:232-272`

The controller checks `kb_u_add` before loading an existing article or
calculating the edit/delete authorization. This gate applies to every
non-redirect request, including `mode=edit` and `mode=delete`.

Failure scenario: a user who is explicitly allowed to edit or delete an article
but is not allowed to add new articles is rejected before the relevant
permission is evaluated. The same problem affects moderators with
`kb_m_edit`/`kb_m_delete` but no `kb_u_add`.

Repair direction: require `kb_u_add` only for new article and new draft paths.
For existing articles, load the article first and enforce the already-computed
edit or delete permission.

#### P1 - Attachment downloads do not follow article visibility rules

**Status: resolved in ebcb42c.** `controller/kb_file.php` now applies the same rules as the article page: drafts are visible only to their author, and unapproved articles only to the author, the assigned reviewer, or a moderator; approved articles keep their previous access. Tests: `tests/kb_attachment_permissions_test.php`. Checked on a live board on 10/05/2026 for a draft, a pending and an approved article (see [testing.md](../../testing.md)).

Locations: `controller/article.php:140-165` and
`controller/kb_file.php:117-138`

The article controller makes drafts private to authors/co-authors and permits
pending-article access to the author, eligible moderators, and assigned-team
reviewers. The attachment controller only loads `article_category_id` and
`approved`; for every unapproved article it allows only administrators or users
with `kb_m_approve`.

This creates both overexposure and false denials:

- An administrator or approval moderator can download an attachment from
  someone else's private draft by attachment ID even though the article page is
  intentionally hidden from them.
- Draft and pending-article authors/co-authors can be denied their own files.
- An assigned-team reviewer can read the article but be denied its attachment.

Repair direction: load the article status, author fields, and assigned team,
then reuse one shared article-visibility decision for both article pages and
attached files.

#### P1 - Draft deletion, movement, and ACP recounts corrupt article totals

**Status: resolved in ebcb42c.** Deleting or moving a draft no longer changes category or global counts, and the ACP list and recount no longer overwrite `kb_num_articles` with a filtered or draft-inclusive number. Tests: `tests/kb_article_counts_test.php`, `tests/kb_acp_counts_test.php`.

Locations: `controller/posting.php:857-877`,
`controller/posting.php:916-927`, `inc/functions_kb.php:1892-1901`, and
`controller/acp_controller.php:1105-1113` and
`controller/acp_controller.php:3046-3054`

New drafts deliberately do not increment category or global article counts.
Other paths do not preserve that rule:

- Deleting any article decrements both counts, including a draft that was never
  counted.
- Moving an edited draft decrements the source category and increments the
  destination category, transferring a count that does not exist.
- Opening the ACP article list resets `kb_num_articles` from all article rows,
  including drafts. The search-settings recount has the same all-row behavior.

Failure scenario: deleting or moving drafts makes category counts inaccurate,
potentially negative, and visiting an ACP page can silently change the global
count to include private drafts.

Repair direction: define one "counted article" predicate, excluding
`STATUS_DRAFT`, and use it consistently in insert, promotion, move, delete, and
recount operations. Add lifecycle tests for draft create, move, publish, and
delete.

#### P1 - Extensions added as "Not assigned" disappear from Manage Extensions

**Status: resolved in 5877c26.** The extension queries use a `LEFT JOIN` and show such rows as "Not assigned". Tests: `tests/kb_extensions_test.php`.

Locations: `controller/acp_controller.php:379-393`,
`controller/acp_controller.php:414-440`,
`controller/acp_controller.php:486-499`, and
`adm/style/acp_knowledgebase_extensions.html:14-21`

The add form offers phpBB's `group_id = 0` "Not assigned" option. Both the
display query and save query then use an inner join between `EXTENSIONS_TABLE`
and `EXTENSION_GROUPS_TABLE`. There is no group row for ID zero.

Failure scenario: an extension added as "Not assigned" is inserted successfully
but immediately disappears from this page and cannot be enabled for Knowledge
Base uploads.

Repair direction: select every extension with a left join and represent group
zero with a stable internal bucket and the localized `NOT_ASSIGNED` label.
Alternatively, remove that option if unassigned extensions are intentionally
unsupported.

#### P1 - Adding an extension leaves core and Knowledge Base caches stale

**Status: resolved in 5877c26.** Adding an extension destroys the `_extensions` and `_kb_extension` caches. The same commit also requires the core `a_attach` permission and a valid group for adding. Tests: `tests/kb_extensions_test.php`.

Location: `controller/acp_controller.php:414-444`

`add_extension()` writes directly to phpBB's core `EXTENSIONS_TABLE` but does
not invalidate phpBB's `_extensions` cache or the extension's `_kb_extension`
cache used by image detection.

Failure scenario: an administrator adds and enables an image extension, but
upload validation and Knowledge Base image handling can continue using the old
extension metadata until an unrelated cache purge occurs.

Repair direction: after the insert, destroy `_extensions` and `_kb_extension`.
Also invalidate the Knowledge Base cache after save operations whose group
changes affect display-category behavior.

#### P2 - Permission-deny notification filtering uses unrelated ACL options

**Status: resolved in a05867c.** The deny lookup in the three notification types is limited to the requested `auth_option_id`. Tests: `tests/kb_notification_permissions_test.php`. On 10/05/2026 the need-approval, approval and rejection notifications were delivered and shown in the interface, and private messages were created with the PM setting on.

Locations: `notification/type/need_approval.php:180-202`,
`notification/type/approve.php:406-428`, and
`notification/type/disapprove.php:397-419`

The notification recipient helper correctly finds groups granted the requested
`auth_option_id`, but its user-deny query filters only by `auth_setting = 0` and
category. It does not constrain the denial to the requested permission.

Failure scenario: a moderator granted `kb_m_approve` can be excluded from
approval notifications merely because that user has an explicit Never setting
for some unrelated Knowledge Base permission in the same category. The helper
also duplicates permission logic instead of applying the same precedence as
runtime ACL checks.

Repair direction: scope the deny query to the requested `auth_option_id` and
centralize recipient permission evaluation so notifications and runtime access
cannot drift apart.

#### P2 - Migration 1.0.3 silently drops one of its schema operations

**Status: resolved.** `migrations/version_1_0_3.php` now declares one `drop_columns` key covering both tables, so `reply_id` is removed by 1.0.3 itself (`version_2_0_2` still drops it for installs that already ran 1.0.3; phpBB skips a column that no longer exists). This edits an already-released migration, done at William's explicit request. The collision scenario in the original text does not apply to the shipped schema: the 1.0.0 `un_mtch` index never included `reply_id`, so the replacement index only widens it. Tests: `tests/kb_legacy_search_migration_test.php` (both new tests fail against the old file).

Location: `migrations/version_1_0_3.php:30-48` and
`migrations/version_1_0_3.php:65-87`

`update_schema()` declares the `drop_columns` array key twice. PHP keeps only
the second value, so the intended removal of `reply_id` from
`kb_src_wrdmtch` never reaches the migration framework. `add_key()` then
replaces the unique index with one that omits `reply_id`.

Failure scenario: upgraded databases retain a dead legacy column. If existing
rows differ only by `reply_id`, creation of the new narrower unique index can
also fail because those rows become duplicates under the new key.

Repair direction: combine both tables under one `drop_columns` key. Test an
upgrade fixture containing legacy rows that collide after `reply_id` is
removed, and define the intended deduplication behavior before creating the new
unique index.

### Previously Reported Regressions Rechecked

The following findings from the September 26 review are corrected in the
current tree:

- Search result entity lookup no longer reverses article and author entity
  types.
- Effective Knowledge Base ACL handling now accounts for `ACL_NEVER`.
- Article prefix rendering no longer broadly decodes escaped HTML.
- Discussion-topic creation uses the topic ID returned by `submit_post()`
  instead of querying `MAX(topic_id)`.
- Attachment display has a filename fallback.
- Queue actions now route through content-aware article review pages.

These checks do not reduce the priority of the current findings above.

### Validation Performed

- Ran `php -l` over every PHP file in the repository: passed.
- Ran `node --check` over every JavaScript file: passed.
- Ran `composer validate --no-check-publish`: passed, with Composer's warning
  that an explicit package `version` field is discouraged.
- Ran `git diff --check`: passed.
- Inspected the seeded SQLite board schema to verify the current text-rendering
  migration state; the removed `bbcode_bitfield` is not a defect in this tree.

### Coverage Limits

The repository has a GitHub workflow but no checked-in automated test suite.
This was a source-level full-tree review, not a live browser acceptance pass.
No MySQL or PostgreSQL service was available in this pass, so those backend
failures are established from the generated SQL rather than an executed DBMS
trace. Fresh-install, upgrade, purge/re-enable, notification delivery, browser,
and database-specific lifecycle behavior remain unverified at runtime.

#### Update 10/05/2026

The new regression tests (`tests/*_test.php`) were run in disposable MySQL and
PostgreSQL containers as described in [testing.md](../../testing.md): 71 tests, 344
assertions at the time of the first run (76 tests, 355 assertions once the later
fixes in pull requests #20, #21 and #24 were added), with the database-backed
cases executed rather than skipped. Run
against the pre-fix code, each test file that can load there fails; the two
migration test files cannot load on `main` because the migrations they test do
not exist there.

Browser and PostgreSQL checks were run on 10/05/2026 and are recorded in
[testing.md](../../testing.md): submission and deletion, attachment downloads for
each role, forged ACP requests, in-board notification delivery, and a full
PostgreSQL install, disable/re-enable, purge, reinstall and 2.0.14 to 2.0.15
upgrade.

Later on 10/05/2026 the custom-field add form, image thumbnails and e-mail
notification delivery were checked, and the browser checks were repeated on
MySQL and PostgreSQL boards. Those checks turned up three defects that were
fixed separately (see [testing.md](../../testing.md)). Nothing from this review is
still listed as unverified, apart from larger datasets and delivery through a
real mail server.

## phpbb.com KB parity gaps

Archived: 10/05/2026. Completed: item 1 (submit-as choice), 4 (Administration filtering, since reworked into a Display options dropdown) and 5 (View topic action) on 09/28/2026; items 2 (required custom fields) and 3 (plain-text Description) on 10/05/2026.

Source: a second-opinion comparison of the Phase 1-5 implementation
against detailed screenshots/analysis of the original phpbb.com
Knowledge Base (the same reference material behind
[`docs/ui-redesign-roadmap.md`](../../ui-redesign-roadmap.md)). None of
these are bugs - they're feature-completeness differences from the
reference implementation, identified after the fact from more
complete screenshots than were available when Phases 1-5 were built.
Scoped here rather than implemented, pending a go-ahead.

### 1. Explicit "submit as" choice for moderators

**Done (09/28/2026).** Decision (asked in chat): just the binary
New-vs-Active toggle phpbb.com actually offers, not a broader status
picker. Implemented:

- `controller/posting.php::post_article()` - `$can_add_noapprove`
  (whether the submitter holds `kb_u_add_noapprove`) and the new
  `$submit_as_approved` (that permission AND the submitter's explicit
  choice, defaulting to `true`/checked so an unmodified form submission
  behaves exactly as before this existed) are both computed once,
  early, so the template can show the choice on a fresh GET load too -
  not only after a submit attempt. `approved`/`status` on the new
  article row, and the post-submit outcome (search indexing, the
  public comments topic, the success message, redirect target, and
  whether moderators get a needs-approval notification) all now follow
  `$submit_as_approved`, not the raw permission - a moderator who
  explicitly picks "Article requiring approval" gets exactly that
  outcome despite holding the permission to skip it.
- `kb_post_body.html` - a radio pair, shown only when
  `!S_EDIT_POST and $can_add_noapprove` (editing must never change an
  article's workflow status, and a submitter without the permission has
  no choice to make), matching phpbb.com's own wording: "Approved
  article" / "Article requiring approval".
- New `KB_SUBMIT_AS`/`KB_SUBMIT_AS_APPROVED`/`KB_SUBMIT_AS_PENDING`
  language keys in en/ru/fr/cs (`cs`/`fr` grounded in real phpBB
  approve/approved terminology from `phpbb-cz`/`phpbbdocs-languages`;
  `ru` has no local phpBB reference to check against and stays an
  unverified AI translation, same caveat as the other recent additions).

Verified live as `kb_moderator` (holds `kb_u_add_noapprove`): choosing
"Article requiring approval" correctly saved the article as
New/unapproved and it appeared in the Administration queue; leaving the
default "Approved article" selected still auto-published exactly as
before (a real regression check, not just reading the diff). Confirmed
`kb_author1` (no `kb_u_add_noapprove`) never sees the choice at all.

phpbb.com's submission form gave moderators (and only moderators) an
explicit choice at submit time: "Article requiring approval" or
"Approved article" - regardless of whether they hold
`kb_u_add_noapprove`. This extension's `controller/posting.php`
instead auto-publishes purely based on whether the submitter holds
`kb_u_add_noapprove` (`$can_add_noapprove` in `post_article()`), with
no UI choice offered.

Scope: a new radio/checkbox in `kb_post_body.html`, shown only when
the submitter already has `kb_u_add_noapprove` (a user without it has
no choice to make - their submission always needs approval), letting
them deliberately route their own submission through the approval
queue instead of auto-publishing. Needs a decision on whether this
should also let a moderator submit *as* another article's status
directly, or just toggle the initial New vs. Active outcome.

### 2. Required category / phpBB-version validation

**Done (10/05/2026).** A custom field can be marked required (migration
`version_2_0_19`, `field_required` on `kb_fields`, off by default, so the seeded
phpBB Version field stays optional). The ACP field list has a Required column
with a make-required/make-optional link, and the add-field form has a Required
checkbox. When a field is required, submitting or previewing an article or a
redirect without choosing one of its enabled values is refused with "Please
select at least one value for <field>", and the form shows "Please select at
least one." under the field. A draft is exempt, a field with no enabled values
is never enforced, and moderator actions (review, approve, deactivate) are not
checked because only the posting form enforces it. The primary category was
already implicitly required. See the testing notes. The original analysis
follows.

phpbb.com marked both "Categories" and "For phpBB Version" as
"Please select at least one" - required fields. This extension treats
the primary category as required (implicit, since posting always
targets a specific category id) but never enforces that at least one
custom-field value (e.g. a phpBB-version tag) is selected, since
custom fields are a generic, optional-by-default system (Phase 3).

Scope: likely a new per-field `required` flag on `kb_fields` (ACP
toggle), validated in `posting.php` alongside the existing
`NO_TITLE`/`NO_TEXT`/`KB_REDIRECT_INVALID_URL` checks - not a
hardcoded "version is always required" rule, since the field system is
deliberately generic and other installs may not want every field
mandatory.

### 3. BBCode/emoticons restriction on the Description field

**Done (10/05/2026).** The Description stays a one-line box and is plain
text: the posting form says "BBCode and emoticons are disabled", the KB pages
already showed it literally, and the announcement topic no longer formats it
(the title, author, description and category go into that post as plain text
after parsing, so BBCode, smilies and links typed into them stay literal). The
length limit is now enforced on the server too, with an ACP setting
("Description maximum length", default and ceiling 255, the width of the
database column). Details are in the testing notes. The original analysis
follows.

phpbb.com disabled BBCode and emoticons specifically on the
Description field (kept it plain-text), while the article body allows
full formatting. This extension currently allows full BBCode on both
fields - not an oversight, but also not a decision that was
specifically re-examined against this detail; the roadmap's constraint
3 was about *not* stripping the article-submission form down the way
phpbb.com's *redirect* form was stripped, and didn't separately address
the description field.

Scope: decide whether to restrict Description specifically (would need
its own BBCode-disabled rendering path in `posting.php`'s
`generate_text_for_storage()`/`generate_text_for_display()` calls,
since currently title/description/body are treated uniformly) or leave
it as a deliberate improvement over the original.

### 4. Richer Administration filtering

**Done (09/28/2026).** Added a `filter` GET param to
`approve.php::queue()`, narrowing the existing "needs attention" WHERE
clause additively: `redirect` (`is_redirect = 1`), `inactive`
(`status = STATUS_INACTIVE`), or `team_<id>` (`assigned_team_id =
<id>`, one option per configured KB team group). A `<select
onchange="this.form.submit()">` in `kb_queue_body.html`, matching the
existing field-filter pattern already used on the category listing
page (`kb_cat_body.html`), with a "Reset filter" link shown whenever a
filter is active. The pagination URL carries the active filter through
paging.

Verified live: the team filter correctly narrowed a 5-article queue
down to exactly the one article assigned to that team, and "Reset
filter" returned to the full unfiltered list.

**Follow-up fix (09/28/2026):** the filters only ever narrowed within
the base "needs attention" query - an already-published, auto-approved
redirect (Active, no pending revision) never entered the candidate set
in the first place, so "Redirects only" showed nothing for it even
though it matched. Added a "Show all articles, not just those needing
attention" checkbox (`show_all` param) that, when checked, drops the
needs-attention restriction entirely while keeping the selected filter
and the existing moderatable-category permission scoping - matching
what phpbb.com's "all articles + redirects" option implies. Off by
default, so the queue's ordinary behavior is unchanged. Verified live:
`filter=redirect` alone still correctly shows nothing for an
auto-published redirect; `filter=redirect&show_all=1` correctly shows
it with a working Deactivate action.

**Reworked (10/05/2026):** the filter plus "show all" checkbox became one
"Display options" dropdown with a Go button, matching phpbb.com's: "View new
and revisions (default)", "View all articles and redirects", "View
redirects", "View inactive articles", and for each team "View assigned to
<team>" and "Including active and redirects". Two behaviours changed with it:
the default view no longer lists inactive articles (they have their own
option, and an inactive article with a pending revision still shows by default),
and "View redirects" lists every redirect, including auto-published ones.
Old `?show_all=1` links still work. See the testing notes.

phpbb.com's Administration page had a "Display Options" filter
separate from the per-article action menu: all articles + redirects,
redirects only, inactive only, or scoped to one specific team. This
extension's `approve.php::queue()` is a single combined "needs
attention" listing (not `STATUS_ACTIVE` with no pending revision) with
no filter control.

Scope: a filter dropdown in `kb_queue_body.html` posting back to
`queue()` with a `filter` param, narrowing the existing WHERE clause
(status, `is_redirect`, or `assigned_team_id`) - additive to the
current query, not a rewrite.

### 5. "View article's topic" as a direct moderator action

**Done (09/28/2026).** Added a `U_TOPIC` link to both
`controller/approve.php` methods (`queue()`'s `art_row` block,
`approve_article()`'s main vars), reusing the exact
`append_sid(...viewtopic...)` construction `article.php` already uses
for `U_COMMENTS`, gated on the article having a non-zero `topic_id`.
Reuses phpBB core's own `VIEW_TOPIC` language key rather than adding a
new one - "View topic" already says the same thing "View article's
topic" would. Rendered in `kb_queue_body.html` (a `button2` link
alongside Assign/Reject/etc. per row) and `kb_approve_body.html` (a
standalone link above the status block, outside the action form since
it's a plain view action, not a moderation one).

Verified live end-to-end: submitted a real article through
`posting.php` (not the direct-SQL seed fixtures, which never create a
comments topic), confirmed its `topic_id` was set, and confirmed the
link on both the approve page and the queue correctly opens
`viewtopic.php?f=<kb_forum_id>&t=<topic_id>` with the right content.

phpbb.com's per-article Status dropdown (both in the Administration
list and on the article page itself) included a direct "View article's
topic" action. This extension already links to the associated topic
from the public article page (`U_COMMENTS` in `controller/article.php`,
via `topic_id`) when comments exist, but doesn't surface it as a
distinct action in the moderation queue or the single-article approve
page (`kb_approve_body.html`, `kb_queue_body.html`).

Scope: small - add a `U_TOPIC` link (reusing the same
`append_sid(...viewtopic...)` construction `article.php` already does)
to both templates, gated on the article having a `topic_id`.
