# Testing Methodology For Claude

This is a handoff of Codex's local fixture and browser testing on 10/11/2026,
not an assertion that every Knowledgebase feature or combination passes.
Use [testing.md](testing.md) for the existing PHPUnit and database-suite
instructions. Do not substitute this browser run for those suites.

## Scope And Current State

- Extension checkout: `/home/william/Desktop/repos/knowledgebase`.
- Fixture tooling: `/home/william/Desktop/repos/seed-forum`.
- Changed seed: `/home/william/Desktop/repos/seed-forum/bin/seed-kb-fixtures.php`.
- Disposable board: `/home/william/Desktop/repos/seeded-board/kb`.
- SQLite database: `/home/william/Desktop/repos/seeded-board/kb/store/sqlite.db`.
- Board: <http://localhost:8092/knowledgebase>.
- Runner configuration: `/home/william/Desktop/repos/seed-forum/config/local.yml`.
- Fixture inventory and boundaries:
  `/home/william/Desktop/repos/seed-forum/docs/knowledgebase-fixtures.md`.

The final reset succeeded. The restored board contains 265 articles,
37 categories, four independent main trees with six-level paths, three team
groups, 41 pending revisions, two private draft edits, five custom fields and
eight attachment rows. Browser-created workflow articles were removed by the
reset. Outgoing email is disabled and the temporary mail catcher was stopped.

The article custom-field layout was then corrected in `controller/article.php`:
each nonempty rendered field ends with a line break. Empty fields do not add
blank lines. Six browser checks passed on two articles at 1440, 768 and 390 px.
The two defects below were subsequently fixed and passed seven focused browser
regressions. No commit or push had been made at the end of that testing run.

Preserve unrelated changes, including `seed-forum/config/bare.yml`, and existing
untracked content. Fixture attachment files are generated in the extension's
mounted `files/` directory; that directory is not wholly disposable.

## Evidence

The local visual report is:

`/home/william/Documents/Codex/2026-10-09/look-at-home-william-desktop-repos/kb-browser/report.html`

Its directory contains full-page PNGs and per-suite JSON results. At the end
of this run there were **153 passing checks and two failing checks**. This
count was the initial result, before the two fixes. Seven additional focused
browser regressions now pass and resolve those failures. The initial JSON
records are retained as historical evidence. The original count includes
13 ACP screen-opening checks, which prove rendering only, not
every operation available on those screens. The report separates suites.

- `walkthrough-results.json`: 86 restored-board navigation and access checks.
- `action-results.json`: 23 passing publication checks and the Preview failure.
- `acp-results.json`: 13 ACP screens opened without a general/SQL/fatal error.
- `admin-action-results.json`: nine configuration/category/field operations.
- `maintenance-results.json`: six passing maintenance/search checks and the
  reader's category-selection failure.
- `permissions-results.json`: two group/category permission-detail inspections.
- `final-results.json`: eight restored-board structural and browser checks.
- `field-lines-results.json`: six custom-field layout checks.

Treat screenshots from publishing and mail tests as historical test-run views.
They precede the final reset and custom-field line-break correction. The
`field-lines-*` images show the corrected layout. View the PNGs; do not infer
visual quality solely from assertions or HTTP status.

## Reset And Isolation

1. Read the runner configuration and confirm it targets the disposable board.
   Never apply `--reset` to a working or production board.
2. Stop all browser actions against the board before rebuilding it. A browser
   session using SQLite during installation caused a database-lock failure in
   an earlier attempt. An isolated rerun succeeded.
3. From `/home/william/Desktop/repos/seed-forum`, run:

   ```bash
   php -l bin/seed-kb-fixtures.php
   git diff --check -- bin/seed-kb-fixtures.php
   bin/reset-board.sh --validate
   bin/reset-board.sh --reset
   ```

4. Check installer/seed output and live HTTP/browser/database state, not just
   exit status. Wait for the reset process to finish before opening the board.
5. Log in again after resetting. Previous cookies, ACP SIDs and form tokens
   belong to the old installation and must not be reused.

For display-only changes, `bin/reset-board.sh --purge-cache` refreshes the board
without reseeding. The runner reapplies web-group cache permissions. Do not
perform a full reset just to refresh a template or display change.

## Browser Method

Codex used the installed Playwright skill with visible Chromium:

```bash
node /home/william/.agents/skills/playwright-skill/run.js /tmp/playwright-test-kb-walkthrough.js
```

Other run scripts were `/tmp/playwright-test-kb-actions.js`,
`/tmp/playwright-test-kb-admin-actions.js`,
`/tmp/playwright-test-kb-maintenance.js`,
`/tmp/playwright-test-kb-permissions.js`, `/tmp/playwright-test-kb-index.js`,
`/tmp/playwright-test-kb-final.js`, and `/tmp/playwright-test-kb-field-lines.js`.
These are machine-specific exploratory scripts, not committed CI tests.
Files in `/tmp` may disappear. Inspect bindings, prerequisites and mutations
before reusing them; do not blindly rerun the mutation scripts.

Use separate browser contexts for guest, reader, both authors, moderator and
admin. Public fixture credentials are `KbTest1234!` for `kb_reader`,
`kb_author1`, `kb_author2`, `kb_moderator`, and installer `admin`. Keep the
board local. Session-state files and raw form tokens must not be committed.

For ACP access, follow the real Administration Control Panel link from the
logged-in board and complete reauthentication. Follow the session-bearing
module links from the actual ACP menu. Navigating to a naked `/adm/index.php`
does not establish the same session.

Wait for each phpBB form to be old enough before submitting it: the run waited
until current epoch seconds exceeded its hidden `creation_time` by more than
one second. Immediate submission produced false login/form failures.

Use actual UI controls for mutations, then independently check SQLite rows and
files. Read-only SQLite assertions used `.timeout 5000` to tolerate brief
indexing locks. Do not write expected results into the database to make a
browser test pass.

## What Was Exercised

- Open every category as a reader and verify all ancestor breadcrumbs. Check
  four distinct roots, sibling branches, empty categories and deep leaves.
- Check reader posting denial, draft privacy, author permissions, read-only
  categories, team-only contribution and category-scoped moderation.
- Inspect all ten queue options: default, all, redirects, inactive, and two
  filters for each of the three teams. Verify multiple pages and row visibility.
- Read named BBCode/Markdown and group-authored articles; follow a Markdown
  anchor; check keyword search and custom-field filtering.
- Exercise upload, required-field errors, incomplete private draft, submission,
  assignment, team review, reason enforcement, approval, private further edit,
  revision comparison/approval, deactivation/reactivation, group and co-author
  publication, rejection and deletion.
- Check attachment HTTP gates for each relevant role and article status.
  Verify image loading, original/thumbnail dimensions, and removal after
  article deletion. A moderator still must not read another author's draft.
- Check copy-link clipboard content and the actual print view.
- Inspect desktop, tablet and phone screenshots and document overflow.
- Open every ACP module; save configuration; switch hierarchical/classic and
  flat/non-classic views; reject a configuration save without its CSRF token.
- Create a disposable deep category with copied permissions/default team;
  rename it, move it across main trees, and delete it while empty.
- Add/toggle/delete a disposable custom field; add/disable/delete a temporary
  value; add/disable/reenable an attachment extension.
- Inspect the Operations Team permission editor and mask for Team Runbooks.
  This was not comprehensive permission-writing or inheritance coverage.
- Delete and recreate the active native search index. Select the native form
  explicitly, wait through progress, and verify completion in the UI and
  database. The ACP also displays unsupported backend index forms on SQLite.
- Clean the deliberately missing attachment row and verify all real-file rows
  survive. Check both aged orphan fixtures after the final reset.
- Verify tree interval uniqueness/parent nesting, maximum depth in each root,
  required field assignments, inactive approval flags, category counters,
  global submitted count and team status/group-author variety.

## Notifications Without External Delivery

Approval/rejection bell notifications, private messages and emails were
observed. A temporary Mailpit container published SMTP and its web UI only on
`127.0.0.1`; the board temporarily pointed to that SMTP port. Messages never
went to an external mail server. Screenshots show the local inbox and the
author's PM inbox. The final reset removed these temporary board settings,
and `codex-kb-fixtures-mailpit` was stopped. Pending-review email delivery was
not confirmed. Do not describe it as passing.

## Defects Reproduced And Fixed

The fixes are in `controller/posting.php` and `controller/search.php`.
Preview now redisplays current submitted selections, including empty choices.
Search builds category options without contribution ACL checks, after the
existing global read/search permission checks have passed. Posting and guest
permissions are unchanged.

The regression script is `tests/browser/kb_selection_regressions.js`.
It previews without saving changes
and uses fresh author/reader states from the fixture walkthrough:

```bash
node /home/william/.agents/skills/playwright-skill/run.js /home/william/Desktop/repos/knowledgebase/tests/browser/kb_selection_regressions.js
```

`KB_TEST_URL`, `KB_STATE_DIR` and `KB_ARTIFACT_DIR` override the local URL, saved
role-state directory and output directory. Defaults are the local board,
`/tmp`, and `/tmp/kb-selection-regressions`. This needs Playwright, not new
project runtime dependencies. Seven checks cover new/existing article Preview,
cleared optional choices, desktop and phone category selection, actual
author/category search results, reader posting denial and guest access denial.
PHP syntax checks also passed. No full PHPUnit suite was run for these fixes.

### Preview Clears Selections

Before the fix, selecting custom-field values and cross-listed categories and
clicking Preview rendered Markdown but cleared those selections. Submit then
failed the required-field checks unless they were reselected.

`controller/posting.php` previously redisplayed submitted selections only for
`$submit`, not `$preview`, in `$cross_categories_display` and `$selected_ids`.
Evidence: `action-results.json` and `action-failure-6.png`. Codex reselected the
values to continue the other tests, while retaining this failed result.

### Read-Only Category Search Choices Are Disabled

Before the fix, Advanced Search disabled category options for `kb_reader`
despite the reader being able to browse them. `controller/search.php` called
`make_category_select(0, [], false)`, whose default permission is `kb_u_add`.
It checked contribution rights rather than read/search access.

Evidence: `maintenance-results.json` and `maintenance-6.png`. Do not enable
posting permissions for the reader merely to make this test pass.

## Avoid False Failures And False Passes

- Uploaded images are outside `.content`; inspect `img.postimage`, not only
  article text. The original screenshot already showed a loaded image.
- The print button uses the rendered Print label/icon, not an invented
  `title="Print view"`. Copy-link testing also reads the clipboard.
- Match actual localized messages and capitalization. For example, field
  toggling displays `make required`, and cleanup says `successfully synchronized`.
- There are two `#keywords` elements on the search page. Use the search form's
  text input rather than an ambiguous page-wide ID locator.
- A no-match search term must respect the configured word-length limit.
  An overlong word produces a validation message, not a no-results screen.
- Click/navigation failures during indexing need outcome checks. Wait for the
  completed index and cleared progress state, not merely the first nonzero rows.
- Separate genuine extension defects from harness errors. Correct a bad
  selector and rerun; do not relabel a real failure as passing.

## Remaining Coverage

Bulk extra-file deletion was intentionally not run against the mounted source
directory, where unrelated files exist. Use a separately isolated upload
directory for that destructive test. The deliberate extra-file fixture exists.

Not verified exhaustively: permission writes and ACL_NEVER inheritance,
category deletion with article/subcategory movement, every upload type/size/
count boundary, stale-edit conflicts, revision rejection, every notification
preference combination, alternate search backends, other database/browser
engines, and install/upgrade/disable/purge lifecycle behavior in this run.
The seeded changelog forum is not proof of every changelog operation.
Historical lifecycle results in `testing.md` remain separate evidence.

Do not call this "all features passed." The intended standard is visible,
role-aware workflows with explicit failures and a clear list of what remains.
