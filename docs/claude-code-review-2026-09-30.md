# Claude Full Codebase Review - 2026-09-30

Scope: the complete current working tree at
`/home/william/Desktop/repos/knowledgebase`, on `main` at `98af128`, including
unchanged tracked code and all current uncommitted changes. The review covered
the extension entry points, public and ACP controllers, services, permissions,
notifications, search backends, migrations, templates, JavaScript, and package
configuration. Documentation and language files were checked where they affect
runtime behavior or the current changes.

Verdict (original, at `98af128`): **Changes required.**

Update 10/05/2026: the fixes for findings 1-12 are on the
`kb-acp-extensions-alias-and-review-followup` branch (pull request #17); the
commit for each is listed under its heading. The
finding text below is the original review and is left unchanged. Items not yet
checked in a browser are marked as such.

## Findings

### P1 - Destructive ACP actions do not validate CSRF tokens

**Status: resolved in d7b0c6c.** Form-token and link-hash checks added to the ACP actions; regression tests in `tests/kb_acp_csrf_test.php`. In a browser check on 10/05/2026, 17 forged requests from an administrator session were rejected and genuine requests were accepted (see [testing.md](testing.md)).

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

### P1 - The custom-field add form posts to an undefined action variable

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

### P1 - MySQL keyword search sends PHP variable names as literal SQL

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

### P1 - PostgreSQL index creation targets the posts table with invalid columns

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

### P1 - Combined keyword and author search appends an undefined author ID

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

### P1 - Edit and delete incorrectly require article-add permission

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

### P1 - Attachment downloads do not follow article visibility rules

**Status: resolved in ebcb42c.** `controller/kb_file.php` now applies the same rules as the article page: drafts are visible only to their author, and unapproved articles only to the author, the assigned reviewer, or a moderator; approved articles keep their previous access. Tests: `tests/kb_attachment_permissions_test.php`. Checked on a live board on 10/05/2026 for a draft, a pending and an approved article (see [testing.md](testing.md)).

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

### P1 - Draft deletion, movement, and ACP recounts corrupt article totals

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

### P1 - Extensions added as "Not assigned" disappear from Manage Extensions

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

### P1 - Adding an extension leaves core and Knowledge Base caches stale

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

### P2 - Permission-deny notification filtering uses unrelated ACL options

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

### P2 - Migration 1.0.3 silently drops one of its schema operations

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

## Previously Reported Regressions Rechecked

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

## Validation Performed

- Ran `php -l` over every PHP file in the repository: passed.
- Ran `node --check` over every JavaScript file: passed.
- Ran `composer validate --no-check-publish`: passed, with Composer's warning
  that an explicit package `version` field is discouraged.
- Ran `git diff --check`: passed.
- Inspected the seeded SQLite board schema to verify the current text-rendering
  migration state; the removed `bbcode_bitfield` is not a defect in this tree.

## Coverage Limits

The repository has a GitHub workflow but no checked-in automated test suite.
This was a source-level full-tree review, not a live browser acceptance pass.
No MySQL or PostgreSQL service was available in this pass, so those backend
failures are established from the generated SQL rather than an executed DBMS
trace. Fresh-install, upgrade, purge/re-enable, notification delivery, browser,
and database-specific lifecycle behavior remain unverified at runtime.

### Update 10/05/2026

The new regression tests (`tests/*_test.php`) were run in disposable MySQL and
PostgreSQL containers as described in [testing.md](testing.md): 71 tests, 344
assertions, with the database-backed cases executed rather than skipped. Run
against the pre-fix code, each test file that can load there fails; the two
migration test files cannot load on `main` because the migrations they test do
not exist there.

Browser and PostgreSQL checks were run on 10/05/2026 and are recorded in
[testing.md](testing.md): submission and deletion, attachment downloads for
each role, forged ACP requests, in-board notification delivery, and a full
PostgreSQL install, disable/re-enable, purge, reinstall and 2.0.14 to 2.0.15
upgrade.

Later on 10/05/2026 the custom-field add form, image thumbnails and e-mail
notification delivery were checked, and the browser checks were repeated on
MySQL and PostgreSQL boards. Those checks turned up three defects that were
fixed separately (see [testing.md](testing.md)). Nothing from this review is
still listed as unverified, apart from larger datasets and delivery through a
real mail server.
