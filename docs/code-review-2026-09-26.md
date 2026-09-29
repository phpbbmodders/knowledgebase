# Code review - 2026-09-26

Scope: full repository at `/home/william/Desktop/repos/knowledgebase`, including unchanged tracked files and the current uncommitted working tree, reviewed against `HEAD` (`a1ebdc0 Ignore local phpBB UI KnowledgeBase reference screenshots`). This supersedes the earlier diff-only pass.

## Findings

### P0 - Full-body search results turn escaped article markup back into executable HTML

- `controller/search.php:346-357`
- `styles/prosilver/template/kb_search_results.html:69-76`

When a full article body is returned, `generate_text_for_display()` produces display-ready HTML and preserves literal user-entered angle brackets as entities. The following `strtr()` changes every `&lt;` and `&gt;` back to literal brackets. The result is assigned to `searchrow.MESSAGE` and rendered in a phpBB Twig environment where automatic escaping is disabled.

An article containing escaped markup such as an image with an event handler can therefore become active HTML on the search-results page. Remove the blanket entity reversal. BBCode output from `generate_text_for_display()` is already ready for the template.

### P1 - Runtime category ACL evaluation ignores explicit user-level Never permissions

- `inc/functions_kb.php:382-420`
- `controller/acp_controller.php:2490-2508`

`acl_kb_get()` applies group `ACL_NEVER`, but the user-specific query only handles `ACL_YES`. A direct user `ACL_NEVER` is ignored, while a direct user `ACL_YES` also overwrites a group-level Never. The ACP permission trace implements the expected precedence: a user Never forces the result to Never, and a user Yes does not override an existing Never.

The runtime and trace can therefore disagree, and a user can retain add, edit, delete, or approval powers that an administrator explicitly denied. Apply the same `ACL_NO`/`ACL_YES`/`ACL_NEVER` precedence in `acl_kb_get()` that the trace displays.

### P1 - Decoding configured forum and topic prefixes creates stored XSS

- `controller/acp_controller.php:355-360`
- `event/listener.php:139-164`

The request API stores the prefix values in an HTML-safe form. The two event handlers call `htmlspecialchars_decode()` before prepending those values to `FORUM_NAME` and `TOPIC_TITLE`, which are rendered as HTML by phpBB templates. A crafted prefix saved by an administrator can therefore inject markup or script-bearing attributes into the forum index and topic listing for other users.

Keep the stored prefix escaped when assigning it to template data. If formatting is intended, define and validate a narrow format rather than decoding arbitrary HTML.

### P1 - Approval and denial reasons are not reliably sent to authors

- `controller/approve.php:273-289`
- `controller/approve.php:618-626`
- `notification/method/pm.php:66-103`
- `notification/type/approve.php:117-119`
- `notification/type/disapprove.php:119-121`

The approval form requires a moderator to enter a reason or explicitly skip messaging, but private-message delivery is implemented as a phpBB notification method. Notification methods are subscription channels, and this method inherits the default-disabled state. An author receives the PM only after enabling `notification.method.phpbbmodders_knowledgebase_pm` in notification preferences.

A moderator can therefore be forced to write a reason even though the promised message is never delivered. Ordinary board or email notifications may still be sent, including on the skip-message path. Use direct PM delivery for a required moderation message, or make the form and language accurately describe preference-controlled notifications.

### P1 - New topic IDs are discovered with a race-prone `MAX()` query

- `inc/functions_kb.php:1816-1825`
- `inc/functions_kb.php:1902-1914`

Both the public discussion topic and the moderator changelog topic are created with `submit_post()` and then rediscovered with `SELECT MAX(topic_id)` in the forum. If another request creates a topic before that query, the article records the other topic's ID. Later comments or changelog entries can then be posted to an unrelated topic.

`submit_post()` receives `$data` by reference and fills `$data['topic_id']`. Store that value directly in both paths.

### P1 - Package metadata says 2.0.7 while migrations advance the extension to 2.0.10

- `composer.json:6`
- `migrations/version_2_0_8.php:54`
- `migrations/version_2_0_9.php:58`
- `migrations/version_2_0_10.php:63`

The working tree contains migrations through 2.0.10, but `composer.json` still identifies the package as 2.0.7. Packaging this tree as-is would advertise a version older than its installed migration state and can confuse update checks and support diagnostics. Set the package metadata to the intended release version before release.

### P2 - Queue approve and deny choices cannot complete from the queue

- `styles/prosilver/template/kb_queue_body.html:22-47`
- `controller/approve.php:276-289`

The queue form offers `approve` and `deny`, but it has neither a reason field nor a skip-message control. The controller rejects both submissions and returns an error containing a link to the article approval page. The UI presents actions that cannot succeed where they are offered.

Remove those choices from the compact form and link to the full approval page, or add the required controls to the queue form.

### P2 - ASCII filename fallback returns `true` instead of the cleaned filename

- `controller/kb_file.php:187-190`

`filenameFallback()` uses `return (!empty($filename)) ?: 'File';`. For every non-empty cleaned filename, that expression returns the boolean `true`, which is then coerced to a string by the response header code. Downloads that need the ASCII fallback can receive `1` instead of a useful filename.

Return `$filename ?: 'File'`.

## Validation observations

- `composer validate --no-check-publish` succeeds, with warnings about the explicit `version` field and the unbounded `phpbb/phpbb` constraint in `require`.
- All PHP files pass `php -l`; all JavaScript files pass `node --check`.
- English language keys are present in the Czech, French, and Russian packs. Seventeen English keys have no static or dynamic use outside their definitions.
- All six subscribed `core.*` events exist in the cached phpBB 3.3 event list.
- The phpBB coding standard is not installed in the local PHPCS configuration, so a phpBB-standard PHPCS run was not available.
- No phpBB runtime, database migration, or browser workflow tests were run during this source review.

## Verdict

Changes required. The stored-XSS path and permission-precedence error are release blockers.

No extension source files were modified by this review.
