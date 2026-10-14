# Testing

Run commands from the extension checkout. Use PHP with the extensions
needed by phpBB, plus SQLite, MySQLi, and PostgreSQL support for the full
suite. `PHPBB_ROOT_PATH` must point to a phpBB installation with its Composer
dependencies installed.

```bash
export PHPBB_ROOT_PATH=/path/to/phpbb
curl --fail --location --max-time 60 https://phar.phpunit.de/phpunit-9.6.37.phar --output /tmp/kb-phpunit.phar
php /tmp/kb-phpunit.phar --do-not-cache-result --test-suffix _test.php tests
```

Without database fixture ports, the five MySQL/PostgreSQL integration
cases skip. The remaining tests include in-memory SQLite regression
checks. The current GitHub workflow runs coding and extension validation
checks; it does not run this suite.

## Disposable Database Fixtures

The integration tests connect only to localhost and the database named
`kb_fixture`. They drop and recreate their fixture tables. Never point
them at a database containing data you need to keep.

The following containers publish only on localhost and keep their database
data in temporary memory-backed storage. They deliberately use no password
for these disposable fixtures. They must not be exposed on a public interface.

```bash
docker run --detach --rm --name codex-kb-mysql-fixture --tmpfs /var/lib/mysql:rw -p 127.0.0.1::3306 -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_DATABASE=kb_fixture mysql:8.0
docker run --detach --rm --name codex-kb-postgres-fixture --tmpfs /var/lib/postgresql/data:rw -p 127.0.0.1::5432 -e POSTGRES_HOST_AUTH_METHOD=trust -e POSTGRES_DB=kb_fixture postgres:16-alpine
```

Wait until both readiness checks succeed before running the tests:

```bash
docker exec codex-kb-mysql-fixture mysqladmin --user=root ping --silent
docker exec codex-kb-postgres-fixture pg_isready --username=postgres --dbname=kb_fixture
export KB_TEST_MYSQL_PORT="$(docker inspect --format '{{(index (index .NetworkSettings.Ports "3306/tcp") 0).HostPort}}' codex-kb-mysql-fixture)"
export KB_TEST_POSTGRES_PORT="$(docker inspect --format '{{(index (index .NetworkSettings.Ports "5432/tcp") 0).HostPort}}' codex-kb-postgres-fixture)"
php /tmp/kb-phpunit.phar --do-not-cache-result --test-suffix _test.php tests
```

Remove only these fixture containers when finished:

```bash
docker rm --force codex-kb-mysql-fixture codex-kb-postgres-fixture
unset KB_TEST_MYSQL_PORT KB_TEST_POSTGRES_PORT
```

The tests execute the real backend SQL and schema changes. Search-result
cache read/write helpers are mocked so the tests do not require phpBB user
sessions. They are not a full board install/upgrade/purge or browser test.

## Lifecycle and Browser Validation - 10/03/2026

Codex exercised separate phpBB 3.3.19 board copies using PHP 8.4.26.
The working board and its database were not reset or modified.

- SQLite: fresh installation reached KB migration 2.0.15. Disable/re-enable
  retained eleven articles, three pending revisions, and one private draft edit.
  Purge removed KB tables, configuration, ACL options, ACP modules, and migration
  records while retaining the board users. A subsequent installation at 2.0.14
  upgraded to 2.0.15, preserved a legacy search match, and accepted a second
  description match for the same article and word.
- MySQL 8.0.42: fresh installation at migration 2.0.14, upgrade to 2.0.15,
  disable/re-enable, purge, and clean reinstall at 2.0.15 passed. Row-by-row
  assertions confirmed articles, revisions, private draft edits, and native
  search matches survived upgrade and disable/re-enable. Purge removed KB
  tables, configuration, ACL options, ACP modules, and migration records while
  preserving board users. Fixture seeding required an explicit empty
  `category_parents` value; this was corrected in seed-forum.
- PostgreSQL 16.15: the initial fresh installation failed in
  `version_1_0_0::remove_knowlege_base_ext()`. The legacy module cleanup uses
  double-quoted string values, which PostgreSQL interprets as identifiers.
  The database confirmed schema completion but incomplete migration data and
  no `kb_version`. The installer returned
  exit code zero despite reporting this SQL error, so exit status alone is not
  sufficient evidence of success. William authorized a narrowly scoped
  exception to the historical-migration rule: only the seven SQL string
  values were changed to single-quoted literals, preserving cleanup behavior
  and schema. After that correction, fresh installation at 2.0.14, upgrade to
  2.0.15, disable/re-enable, purge, and clean reinstall at 2.0.15 passed.
  Row-by-row assertions confirmed article, revision, private draft-edit, and
  native search-match preservation. Purge removed KB-owned tables,
  configuration, ACL options, ACP modules, and migration records while
  preserving core users. Two regression tests cover SQL literal quoting and
  repeatable cleanup that retains unrelated data.

Browser checks on the disposable SQLite board confirmed:

- Guests were denied KB access under the fixture permissions.
- A view-only reader saw published articles, could not open the posting form,
  and could not read another user's private draft.
- The author saw the private draft in Manage Your Articles and could load the
  saved draft-edit body, with the saved-draft notice displayed.
- The moderator could open the five-item pending article/revision queue but
  could not read the author's private draft.

These browser checks covered navigation, form rendering, and access control,
not submission/deletion round trips, uploads, CSRF rejection through the
browser, or actual notification delivery. Browser coverage was not repeated
on MySQL or PostgreSQL. The localhost-only fixture server and memory-backed
database containers were stopped after testing.

## Browser and PostgreSQL Validation - 10/05/2026

Claude ran these checks on the seeded SQLite board at `localhost:8092` (nginx
and PHP-FPM, PHP 8.4.26, phpBB 3.3.19, Knowledge Base 2.0.15 from the pull
request #17 branch) and on a throwaway board installed on PostgreSQL
(`postgres:16-alpine` container). The seeded board was restored from a backup
afterward and the PostgreSQL container was removed.

Browser checks, using the `kb_author1`, `kb_moderator` and `admin` fixture
accounts:

- Saving a draft left the category and global article counts unchanged.
  Submitting it showed "added successfully, but require prior approval",
  raised both counts by one, and created a need-approval notification for
  `kb_moderator`.
- The moderator assigned the article to the team, marked it reviewed, approved
  it with a reason, and rejected another article. The author's notification
  list showed both the approval and the rejection, and the moderator's showed
  the pending article. At the time of that run, the former
  `kb_require_author_pm` override also caused rejection private messages.
  Approval/rejection PMs now default on through UCP notification preferences,
  and explicit opt-outs are respected instead of overridden. See
  [Author PMs, comments and changelog](#author-pms-comments-and-changelog---10112026)
  for the current browser checks and example comment/changelog threads.
- Deleting a draft left the counts unchanged. Deleting an approved article
  lowered both counts by one and removed its attachment row and stored file; a
  later download returned 404.
- A PNG uploaded through the posting form's plupload control was linked to the
  saved draft. Downloads: as the owner, 200 in the browser. Other roles were
  checked over real login sessions with `curl`: while a draft, the moderator,
  reader, second author and guest all got 403; while pending, only the
  moderator got 200; once approved, the reader and second author got 200 and
  the guest 403 (guests have no Knowledge Base access in this fixture).
- 17 forged same-origin requests from an administrator session (no form token,
  or a bad link hash) against the settings, extensions, attachments, orphan,
  extra-files, lost-files, field, search-settings, search-index and reorder
  actions were all rejected with "The submitted form was invalid". Control
  requests with real tokens and links were accepted, and an extension added
  without a group appeared as "Not assigned".

phpBB treats a form submitted in the same second it was created as not
human-made (`check_form_key()` in `includes/functions.php`), so scripted
submissions need a pause of more than one second after the page loads. This is
core behavior, not a Knowledge Base fault.

PostgreSQL board, driven with phpBB's CLI:

- A fresh core install, then enabling the extension, reached `kb_version`
  2.0.15 with 17 Knowledge Base tables, 13 ACP modules and 24 migration rows,
  with no installer errors.
- Disable and re-enable kept the seeded rows (one category, two articles, one
  search match, one private draft edit). Purge removed every Knowledge Base
  table, config entry, ACL option, module and migration record while keeping the
  board's users, and a clean reinstall followed.
- Upgrade from 2.0.14 to 2.0.15 (with `version_2_0_15.php` held back, then
  restored): the Knowledge Base `un_mtch` index changed from three columns to
  the four-column version, the existing match row and article were kept, a
  second match differing only by `descr_match` was accepted, and an exact
  duplicate was rejected. phpBB core's own search index of the same name was
  left alone.

Later the same day, on the seeded SQLite board at `localhost:8092`:

- **Custom fields.** As `admin` in the browser, the ACP custom-field add form
  (whose action is the `action=add_field` URL) created a field that then
  appeared in the list. The field's edit-values form saved a new value.
- **Settings and thumbnails.** The ACP settings form saved attachments on,
  thumbnails on, three files per article and a 2 MB limit. A 1200x800 PNG
  (about 265 KB) uploaded through the posting form produced a 400x267 thumbnail
  (`thumbnail` set on the attachment row), and the download route served the
  thumbnail with `t=1` and the original without it. Deleting the article removed
  the attachment row and both image files.

### MySQL and PostgreSQL browser checks - 10/05/2026

Claude repeated the browser checks on a MySQL 8.0 board and a PostgreSQL 16
board. Each was installed with phpBB's CLI installer into a disposable
container, had the extension enabled and the fixture seed run, and was then
served through the `localhost:8092` nginx and PHP-FPM site by pointing its
`config.php` at that database. The seeded SQLite board was put back afterward.

On both boards, as `kb_author1`, `kb_moderator` and `admin`:

- Saving a draft left the counts unchanged. Submitting raised the category and
  global counts by one and created a need-approval notification for the
  moderator.
- The moderator assigned an article to the team, marked it reviewed and approved
  it, and rejected another. The author received the approval and rejection
  notifications and private messages, and the need-approval notifications were
  cleared. On MySQL this failed until the fix in pull request 20 (below);
  PostgreSQL was only run with that fix in place.
- A 1200x800 PNG uploaded through the posting form produced a 400x267 thumbnail.
  The reader and a second author could download the original and the
  thumbnail once the article was approved, and a guest got 403.
- Deleting an approved article lowered both counts by one and removed its
  attachment row and image files.
- Switching the search backend to the board's fulltext backend and building the
  index from the ACP worked (MySQL: four FULLTEXT indexes on
  `phpbb_kb_articles`; PostgreSQL: four GIN indexes). Keyword, author and
  keyword-plus-author searches returned the right articles, an author who did
  not match returned none, and there were no SQL errors. The switch only worked
  after the fix in pull request 21 (below).
- All 17 forged ACP requests were rejected. The custom-field add form and the
  add-extension form with real tokens were accepted, and an extension added
  without a group showed as "Not assigned" (the `LEFT JOIN` and `COALESCE`
  queries).

Attachment access and draft deletion were checked on both boards in a second
pass. For an article with an attachment, the owner got 200 while it was a draft
and every other role (moderator, reader, second author, guest) got 403; once the
author submitted it, only the moderator got 200 and the others stayed at 403.
Saving a draft left the category and global counts unchanged, and deleting a
draft left them unchanged too. The non-owner roles were checked over real login
sessions with `curl`; the owner's download and all the form actions were done in
the browser.

Two defects turned up that the SQLite checks and the tests did not catch:

- Approving or rejecting a pending article failed on MySQL with `Truncated
  incorrect DOUBLE value: 'MySQL check article' [1292]`, after the article had
  already been changed. `delete_need_approval_notification()` passed the
  article's author id, title and category id to the notification manager as item
  ids. Fixed in pull request 20.
- The ACP search backend switch always failed at its confirm step with "The
  submitted form was invalid", because the confirm page did not carry the form
  token. This is not specific to either database; the check had been in the
  original code. Fixed in pull request 21.

### E-mail notification delivery - 10/05/2026

Claude checked e-mail delivery on the seeded SQLite board at `localhost:8092`
with a local mail catcher (the `axllent/mailpit` Docker image) as the SMTP
server. E-mail was enabled in the board settings, and `kb_moderator` and
`kb_author1` were given the e-mail and bell methods for the Knowledge Base
notification types, as the UCP notification settings page would save. The
board's mail queue runs from the `/cron/cron.task.core.queue` route, so the
mail appeared after that route was requested, not at the moment of the action.

- **Article approved** and **article rejected**: each reached the author's
  address with the right subject, the moderator's name and the article title,
  and the approval mail included the article link.
- **Article awaiting approval**: no mail was sent for any of three submissions.
  The notification type asked for the e-mail template
  `@phpbbmodders_knowledgebase/need_approve`, but the shipped file is
  `language/en/email/user_need_approval.txt`. After pointing the type at the
  real file name, the mail reached the moderator with the article title and
  link. A new test checks that every notification type's e-mail template file
  exists.

Only the English mail was read. (The Czech, French and Russian folders shipped the
same three file names at the time; those language folders were removed on
10/05/2026, see below.)

Not covered: larger datasets and delivery through a real mail server.

## Markdown articles - 10/05/2026

An article can now be written in Markdown instead of BBCode, chosen per article
with a "Use Markdown" checkbox on the posting form. It is stored with an `article_markdown`
flag on `kb_articles`, `kb_article_revisions` and `kb_article_draft_edits`
(migration `version_2_0_16`), parsed and rendered by a Knowledge Base only
instance of phpBB's s9e formatter with the LiteDown and PipeTables plugins, so
forum posts are never parsed as Markdown. Differences from the Pages extension's
method: header ids were left off at first, because a heading such as "Search" would get
`id="search"` and collide with the page's own search form (they now carry a prefix, see
"Markdown rendering fixes" below).

Claude checked it in a browser on the seeded SQLite board at `localhost:8092`:

- The checkbox is off by default, and ticking it hides the BBCode toolbar.
- Preview, submit and the article page render a heading, bold text, a list and a
  table. BBCode typed inside Markdown still works, and a raw `<script>` tag is
  shown as text.
- The edit form gives back the Markdown the author typed, with the box ticked,
  not the stored XML.
- A draft of an edit and a pending revision keep their own format. The review
  page renders both the live text and the revision as Markdown, and approving
  the revision keeps the flag on the article.
- Unticking the box and submitting stores a BBCode revision. Once approved,
  `**not bold**` and `# not a heading` show as literal text and `[b]` works.
- Search finds a Markdown article by a word in its body, in both the short and
  full result views, with a clean snippet and no markup showing.
- An existing BBCode article is unchanged.

On a fresh MySQL 8.0 board and a fresh PostgreSQL 16 board, built from the same
code, the new migration ran as part of enabling the extension (KB 2.0.16, the
flag on all three tables). On each, a Markdown article was previewed, submitted,
rendered correctly, and loaded back into the edit form as Markdown, and a draft
edit kept the flag.

The new tests (`kb_article_text_test.php`, `kb_markdown_migration_test.php`,
`kb_posting_markdown_test.php`) cover the helper's decisions with mocked
formatter services, the migration on SQLite, and the posting controller's
routing between the two formats. The real formatter itself was exercised only
through the browser checks above. The full suite passes against disposable
MySQL and PostgreSQL containers: 88 tests, 404 assertions.

Not covered: approving a Markdown revision and searching a Markdown article on
MySQL and PostgreSQL boards, the changelog topic's quoted diff for a Markdown
article (it quotes the Markdown source as plain text), and print view.

## Flat article index audit - 10/05/2026

With `kb_flat_index` on, `/knowledgebase` redirects to the all-categories page
(`category?id=0`), which shows a "narrow by category" dropdown and one flat list
of articles. Claude requested every Knowledge Base page as a moderator on the
seeded SQLite board with the setting on and off, and compared the layout
markers on each (the forum-style subcategory list, the dropdown, the tab bar
and the breadcrumb trail).

- **Fixed:** the category pages below the all-categories page. Choosing a
  category from the dropdown opened `category?id=N`, which dropped the dropdown
  and, for a category with subcategories, showed the forum-style "Category /
  Articles / Last article" list; a subcategory's breadcrumbs showed the whole
  chain of parents. With the setting on, those pages now keep the dropdown (with
  the current category selected), show no subcategory list and do not query it,
  and show only the category itself in the breadcrumbs.
- **Unchanged and correct:** the index redirect and the all-categories page. With
  the setting off, the normal hierarchical layout is identical to before
  (subcategory list on `id=1`, no dropdown, full breadcrumbs on `id=3`).
- **Not index pages, left alone:** the article page, "Your articles", the
  Administration queue, the search form and results, the posting form and the
  review page. None of them shows a category list or dropdown in either mode;
  they keep the tab bar and a breadcrumb trail.

In the browser, changing the dropdown from "Advanced Topics" to "Getting
Started" opened that category with the flat layout and the dropdown still
showing the selection.

Claude also repeated the category-page check on throwaway MySQL 8.0 and
PostgreSQL 16 boards with this change checked out: with `kb_flat_index` off a
category page carried no "narrow down" dropdown, and with it on the dropdown
appeared (checked by fetching `category?id=1` as an author on each board).

There is no automated test for this: it is a rendering change in the category
controller and template, checked through the page audit and the browser.

## Contact group on "Manage Your Articles" - 10/05/2026

phpbb.com's "Manage Your Articles" page ends its introduction with "If you have
any questions pertaining to your articles, please PM a Support Team Member."
The Knowledge Base page now has the same second sentence: "...please PM a member
of <group>", with the group's name linking to its member list and shown in the
group's own colour. The group is set in the ACP (Configuration, Teams section,
"Contact group"; migration `version_2_0_17` adds the `kb_contact_group` setting,
default none). With none chosen, or when the chosen group no longer exists, the
sentence is not shown and the page reads as it did before.

Claude checked it on the seeded SQLite board at `localhost:8092`:

- The ACP select lists every usergroup by its translated name with "None"
  selected by default. Saving "KB Team" stored its id.
- The author's page then showed the sentence with a link to
  `memberlist.php?mode=group&g=11`, which lists the group's members. With the
  group given the colour `E67300`, the link rendered in that colour.
- A group id that no longer exists hid the sentence without any error. A made-up
  id submitted through the form was stored as "none".

New tests cover the lookup (none set, deleted group, translated name, link,
colour allow-list), the ACP validation, and the migration. The page was also
checked on MySQL 8.0 and PostgreSQL 16 boards (see the database checks below).

## Status explanations on "Manage Your Articles" - 10/05/2026

The status key at the top of "Manage Your Articles" used to repeat each badge
name beside the badge. phpBB.com explains each status in a sentence, and the
page now does the same: New (waiting for a team member to start reviewing),
Reviewed (reviewed, awaiting approval), Active (visible to the public),
Inactive (hidden; a revision can be submitted to have it reviewed again) and
Revision pending (a revision is waiting for approval). Draft already had its
own explanation. The sentences follow what the code does: `STATUS_REVIEWED` is
set by the moderator's review action, and both active and inactive articles can
carry a pending revision.

### Database checks

The full suite passed against disposable MySQL 8.0 and PostgreSQL 16
containers with the fixture ports set (106 tests, 431 assertions, nothing
skipped). Claude also ran the page on all three supported databases at
`localhost:8092`: the seeded SQLite board, and throwaway MySQL 8.0 and
PostgreSQL 16 boards installed from this branch. On each, logged in as an
author, the key showed all six explanations and the contact-group sentence
linked to the chosen group's member list (the select was saved through the ACP
on MySQL and PostgreSQL; on MySQL a before/after comparison showed
`kb_contact_group` changing from 0 to the group id and no other `kb_` setting
changing). The flat article index from the separate flat-index branch was also
checked on MySQL and PostgreSQL, with that branch checked out: the category
dropdown was absent on a category page with `kb_flat_index` off and present with
it on. The temporary boards and containers were
removed and the SQLite config restored afterwards.

## Administration queue "Display options" - 10/05/2026

The queue's filter dropdown plus "Show all articles" checkbox became one
"Display options" dropdown with a Go button, as on phpbb.com. The choices:
"View new and revisions (default)", "View all articles and redirects", "View
redirects", "View inactive articles", and for each team "View assigned to
<team>" and "Including active and redirects" (filter values `''`, `all`,
`redirect`, `inactive`, `team_<id>`, `team_<id>_all`). Two behaviour changes,
both chosen by the owner: the default view no longer lists inactive articles
(an inactive article with a pending revision still appears), and "View
redirects" lists every redirect rather than only those needing attention. Drafts
never appear. Old `filter=...&show_all=1` links resolve to the matching new
choice.

New tests (`kb_queue_display_test.php`, 17 cases) cover how a requested value
resolves, including the old `show_all` links and unknown or hostile values
(`team_0`, `team_9x`, `team_9 OR 1=1`, negative ids), which fall back to the
default. The full suite then passed against disposable MySQL 8.0 and
PostgreSQL 16 containers (123 tests, 448 assertions, nothing skipped).

Claude also ran the real page as the administrator on all three supported
databases (the seeded SQLite board, and throwaway MySQL 8.0 and PostgreSQL 16
boards installed from this branch). The seed-forum Knowledgebase fixtures were
enlarged for this with 72 generated articles (pending approval, reviewed,
active, pending revision, inactive and redirect, spread over the three
categories, half assigned to the KB Team group), giving 82 submitted articles
and more than one page of ten for every choice. On each database, every one
of the six choices and five old or invalid URLs was walked page by page and
returned exactly the article ids that an independent SQL-based check expected
(41 needing attention over 5 pages, 82 for all over 9, 13 redirects over 2, 12
inactive over 2, and the KB Team's 18, or 36 including active, over 2 and 4),
with no duplicates, full pages before the last, the right option selected, and
pagination links that carry the canonical choice and never the old `show_all`
flag. The Go button, the "Teams" option group and the missing old checkbox were
confirmed too. The temporary boards and containers were removed; the SQLite
board was reseeded with the enlarged fixtures.

## Plain-text Description - 10/05/2026

phpBB.com keeps the Description plain: its form says "BBCode is disabled" and
"Emoticons are disabled". Ours already showed the description literally on
every Knowledge Base page (Claude put `[b]bold[/b] :) [url=...]link[/url]` into
a seeded article's description and fetched the article and category pages
logged in), but nothing said so, and the announcement topic did not follow
suit. `submit_article()` spliced the title, author, description and category
into a BBCode post and parsed the whole thing, so BBCode, links and smilies
typed into a description were formatted in the public topic. Claude confirmed
that with phpBB's own formatter before changing it, and also found that
swapping brackets for `&#91;` entities does not help (phpBB decodes them and
parses the BBCode anyway).

What changed:

- Those four values now go into the announcement post as placeholders that are
  swapped in as XML-escaped plain text after parsing. The step that decoded the
  finished post with `htmlspecialchars_decode()` was removed, because it turned
  `&lt;` and `&amp;` in a description back into raw `<` and `&` inside the
  stored XML.
- The posting form shows "A good description tells users what they should
  expect to find. BBCode and emoticons are disabled." under the label, on the
  article and redirect forms.
- A length limit is enforced on the server for submitting, previewing and
  saving a draft. It counts characters as typed ("&" is one), and separately
  refuses text that would not fit the 255-character column once escaped. The
  form's `maxlength` follows the setting.
- A new ACP setting, "Description maximum length" (migration `version_2_0_18`,
  `kb_description_max_length`, default 255), kept between 1 and 255.

The full suite then passed against disposable MySQL 8.0 and PostgreSQL 16
containers: 148 tests, 479 assertions, nothing skipped.

New tests (`kb_description_test.php`, `kb_acp_description_length_test.php`,
`kb_description_migration_test.php`) cover the limit and its clamping, the
error cases (multibyte, escaped length, over the column), the plain-text
insertion (literal BBCode, XML escaping, well-formed output, no re-scanning of
inserted text) and the migration.

Claude then drove the real posting form as the moderator on the seeded SQLite
board and on throwaway MySQL 8.0 and PostgreSQL 16 boards installed from this
branch, once at the default of 255 and once with the limit lowered to 120
through the ACP. On each: a description and title containing BBCode, a link, a
smiley, `<`, `&` and quotes were accepted, shown literally on the article page,
and stored as plain text in a well-formed announcement post; an over-limit
description was refused with the right message on submit, preview, redirect and
draft (the form kept what was typed); a description of exactly the limit was
accepted; 100 ampersands (500 characters escaped) were refused as special
characters; and the form's `maxlength` and hint were right. In the ACP the field
showed the current value, 120 was saved as 120, 9999 as 255 and 0 as 1.

Not covered: the changelog topic, which
is deliberately BBCode (it quotes a diff of the body) and was not changed; the
article title's length, which is still limited only by the form's `maxlength`.

## Required custom fields - 10/05/2026

phpBB.com marks "Categories" and "For phpBB Version" as "Please select at least
one". Our primary category is already implicitly required (every article is
posted into a category), but custom fields, including the seeded phpBB Version
field, were always optional. A field can now be marked required: migration
`version_2_0_19` adds `field_required` to `kb_fields` (off for every field, so a
board is unchanged until an administrator turns it on), the ACP field list has a
Required column with a make-required/make-optional link, and the add-field form
has a Required checkbox.

The rules, as chosen by the owner:

- Submitting or previewing an article or a redirect without selecting an enabled
  value of a required field is refused with "Please select at least one value for
  <field>", and the form shows "Please select at least one." under the field.
- A draft is exempt, like it already is for the article text.
- A field with no enabled values is never enforced (nobody could satisfy it), and
  ids that are not enabled values of the field do not count.
- Only the author's submission is checked. Moderator actions on an existing
  article (review, approve, deactivate) go through a different controller and are
  not blocked by a field that became required after the article was written.

New tests (`kb_required_field_test.php`, 11 cases, and
`kb_required_field_migration_test.php`) cover the check and the migration. The full
suite passed against disposable MySQL 8.0 and PostgreSQL 16 containers: 161 tests,
496 assertions, nothing skipped.

Claude then ran the real ACP and posting form on the seeded SQLite board and on
throwaway MySQL 8.0 and PostgreSQL 16 boards installed from this branch (the SQLite
board was also upgraded from 2.0.18 with `db:migrate`). On each: the field list
showed the Required column with the seeded field optional, and an article without a
value was accepted; the ACP link made the field required and the form showed the
hint; submitting without a value was refused naming "phpBB Version", with a value it
was accepted and the value saved, preview showed the same error, a draft saved
without a value, a redirect was refused without and accepted with a value, and a
made-up value id did not satisfy it; a moderator's "mark reviewed" on an article with
no value still worked; the link made the field optional again; and a new required
field added through the ACP form was stored as required, while a required field with
no enabled values did not block submitting.

Not covered: the revert of the migration against a real database (only its schema
array is tested).

## Czech, French and Russian language folders removed - 10/05/2026

The extension no longer ships `language/cs`, `language/fr` and `language/ru`; only
English remains. The French pack was translated by Galixte and the Czech and Russian
packs came with the original sheer/knowledgebase package, but the roughly 30 strings
added in 2.0.16-2.0.19 (Markdown, contact group, status explanations, Display
options, plain-text description and required fields) were written without a native
speaker, and no one maintains the rest. They are still in git history (everything
before the commit that removed them).

phpBB falls back from a missing language folder to English per file, not per string,
so a user whose board language is French, Russian or Czech sees English for the whole
extension rather than raw key names. Claude checked this by loading both extension
language files through phpBB's own language service with the user language set to
`fr`, `ru` and `cs`: each returned the English text for a new key
(`KB_DISPLAY_OPTIONS`), an ACP key (`KB_FIELD_REQUIRED`) and an older key
(`KB_MANAGE_ARTICLES_EXPLAIN`). The full suite still passes on MySQL and PostgreSQL
fixtures (the e-mail template test discovers whichever language folders exist).

## Display-order migration: N+1 queries removed - 10/05/2026

The September 26 validation flagged `migrations/version_1_0_2.php`: numbering each
category's articles ran one query per category plus one `UPDATE` per article, after
a duplicated first query whose result was thrown away. It only runs when a board
upgrades from 1.0.0 or 1.0.1 (a board already at 1.0.2 or 2.0.0 skips it), so it is
rare, but on such a board with many articles it was one round trip per article.
The owner asked for it, and two other released migrations had already been edited
in place for bugs, so it was fixed in place.

It now reads every article once (joined to its category, so an article whose
category no longer exists is still left at 0, as before), counts 1, 2, 3... per
category in submission order, and writes the numbers back with one `UPDATE ... SET
display_order = CASE article_id WHEN ... END` per 500 articles. That is 1 query
plus 1 per 500 articles, whatever the number of categories. The old code had no
`ORDER BY`, so the numbering followed whatever order the database returned;
the new code orders by article id, which is the submission order the old code got
in practice on MySQL and SQLite.

`kb_display_order_migration_test.php` (7 cases) checks interleaved categories with
out-of-order ids, an empty category, an orphaned article and an empty board on SQLite,
MySQL 8.0 and PostgreSQL 16, and counts the queries on 1,201 articles in 40
categories (4: one `SELECT` and three `UPDATE`s). Run against the old code, the same
test took 1,243 queries and, on PostgreSQL, gave a different numbering, because the
old code did not order its results. The full suite passes against the MySQL and
PostgreSQL containers: 168 tests, 509 assertions, nothing skipped.

Not covered: an upgrade of a real board from 1.0.1 (the tests exercise the method
on the three databases, not the whole migration chain).

## Taking over from the original sheer/knowledgebase package - 10/05/2026

The `kb_version` setting that the September 26 validation said no migration removes
turned out to be the bridge for boards that ran the original package, and testing
that bridge found it did not work. Claude built a throwaway MySQL 8.0 board with
the original (`sheer/knowledgebase` 2.0.1-RC6, the baseline commit this fork was
imported from), gave it 2 categories and 3 articles, disabled the original, put the
fork in its place and enabled it:

- `version_1_0_0::effectively_installed()` returned nothing, so the first migration
  always ran, unlike every later one. It failed on the original's tables, and phpBB
  then reverted it, whose `revert_schema()` drops every KB table. **`DROP TABLE
  phpbb_kb_articles` appeared in MySQL's query log: all articles were lost.**
- Even without that, the original registers its ACP modules (`\sheer\knowledgebase\...`
  and `ext_sheer/knowledgebase`), its search backend class name and its notification
  types under its own name, and keeps uploaded files inside its own extension folder.

What changed (the owner chose to support the upgrade properly, for original 2.0.x
only):

- `version_1_0_0` is now skipped when `kb_version` is 2.0.0 or higher, or the older
  `knowledge_base_version` is 1.0.0 or higher, like the migrations after it.
- New migration `version_2_0_20` renames the ACP modules and their permission string,
  the search backend class name and the notification types and users' notification
  settings (and switches the types back on, because the original disabled them),
  and copies the uploaded files from `ext/sheer/knowledgebase/files/` into this
  extension's `files/` folder when the old folder still exists, before touching the
  database. On a board that never ran the original it does nothing.
- Enabling the extension is refused, with a message, while `sheer/knowledgebase` is
  still enabled (`ext.php`).
- Every migration that reads `kb_version` now says why it stays.
- `docs/upgrading-from-original.md` describes the upgrade.

The original cannot be installed on SQLite (`near "INDEX": syntax error`) or on
PostgreSQL (`column "KNOWLEGE_BASE" does not exist`), so only MySQL-family boards can
have come from it; this is stated in the guide.

Tests: `kb_adopt_original_test.php` (renames, idempotence, a board that never ran the
original, search backend values, notification types including a clash, file copying
including no overwrite, a missing or shared folder and a failed copy stopping before
any database change, and the first-migration skip) on SQLite, MySQL and PostgreSQL;
`kb_ext_enableable_test.php`. The full suite passes against the MySQL and PostgreSQL
containers.

Claude then repeated the whole upgrade on the MySQL board, with a used board's extras
added (a notification type, a user setting, a sent notification and an uploaded file
with its thumbnail). Enabling the fork while the original was enabled was refused and
changed nothing. After disabling the original and enabling the fork: the 3 articles and
2 categories were intact, no migration was left unfinished, no ACP module or permission
string still named the original, all 13 KB ACP modules loaded and appeared in the
sidebar, the search backend and notification type names were converted (and the type
enabled), the user setting and the sent notification followed, both files were copied
and the originals left in place, and the front end showed the original's categories and
articles. Fresh installs of this branch also pass on SQLite, MySQL 8.0 and PostgreSQL 16
(`kb_version` 2.0.20, 83 seeded articles, 13 modules, none named after the original).

Not covered: original versions other than 2.0.1-RC6 (the 1.0.x line in particular);
boards with real attachments or large data; a custom style that overrides the original's
templates.

## Single-choice custom field types - 10/05/2026

Only the checkbox list existed, so every custom field allowed several values. Two types
now allow exactly one, chosen from the same admin-managed list: **Single choice
(dropdown)** and **Single choice (radio buttons)**. Free-form types (text, number,
date, link) were deliberately not built: they need new storage, and a free-text value
would reach a published article without the revision approval that content gets,
because category and tag changes are saved immediately. A yes/no field needs no type
of its own: make a single choice with the values "Yes" and "No".

- The values, the ACP value editor, storage (`kb_article_field_values`), the category
  filter, the article page and the required flag are shared with the checkbox list.
- The type interface gained `normalize_submitted_ids()`, called both when checking a
  required field and when saving, so a tampered request that sends several values
  stores only the first real one. The placeholder entry submits 0 and is skipped, so it
  cannot hide a real choice sent after it. The checkbox list keeps every value.
- An optional dropdown starts with a "select" placeholder; an optional radio field has a
  "None" button so a choice can be cleared. A required radio field has no "None" button,
  and a required field of either type shows "Please select at least one." and is refused
  when empty.
- `kb_multi_select_field_type::get_type()` now returns `static::class`, so each subclass
  reports its own class (it returned the checkbox list's for all of them).

New tests (`kb_single_select_field_test.php`, 12 cases) cover the normalisation, the
dropdown and radio markup (selection, escaping, placeholder and None rules, the required
hint), each type reporting its own class and name, and all three being registered. The
full suite passes against the MySQL and PostgreSQL containers.

Claude then ran the real ACP and posting form on the seeded SQLite board and on throwaway
MySQL 8.0 and PostgreSQL 16 boards: the ACP type list offered all three types, a dropdown
field and a radio field were created with values through the ACP, both widgets appeared on
the form, one value of each was stored, a request sending several values stored only the
first of each, a placeholder sent before a real choice did not hide it, "no choice" was
accepted when optional and stored nothing, and a made-up value id stored nothing. The
article page showed both values, the category page listed the new field's values and
narrowed to the article, and the edit form preselected the stored values. After making the
fields required through the ACP, an empty dropdown and an empty radio field were each
refused naming the field, real choices were accepted, the radio field lost its "None"
button, and a draft still saved with them empty.

Not covered: a field changed from one type to another (the ACP cannot change a field's type
after creation); the wording of the new labels in languages other than English (only
English ships).

## Deleting a custom field - 10/06/2026

The ACP Custom Fields list gains a **Delete** link between Edit and Deactivate/Reactivate,
on every field. It opens phpBB's confirmation page, which says how much goes with it,
for example: Delete the field "Audience"? Its 3 values and their use on 1 articles will be
removed. The articles themselves are not deleted. This cannot be undone. Confirming removes
the field, all its values and every article's assignment of those values in one transaction;
the articles and every other field are untouched. The deletion is written to the Knowledge Base
admin log (`phpbb_kb_log`, shown on the KB Logs page as "Deleted custom field"), where the
extension's other admin actions already go. Deactivating stays the reversible choice, and the
seeded phpBB Version field can be deleted like any other (a later update does not bring it
back, because its migration has already run).

`kb_acp_delete_field_test.php` (5 cases, on a real in-memory SQLite database) covers the
confirmation message and counts, that showing the page changes nothing, that confirming removes
exactly the right rows, the log call and a missing field. The full suite passes against the
MySQL and PostgreSQL containers.

Claude then ran the real ACP on SQLite, MySQL 8.0 and PostgreSQL 16 (SQLite on a throwaway
database file, so the board being used for manual testing was not touched). Each field row
read Edit, Delete, Deactivate. With a dropdown field tagged on one article beside the seeded
phpBB Version tag: the confirmation page named the field with 3 values and 1 article, opening
the link alone and then cancelling deleted nothing, and confirming reported the field deleted.
The field, its values and its assignment were gone, both articles remained, the version tag and
every other field's values were untouched, the field disappeared from the ACP list, the posting
form and the category filter, the log entry named it and the KB Logs page showed it in words,
and deleting it a second time was refused as not found. The seeded phpBB Version field, with its
assignments, was then deleted the same way.

Not covered: deleting a field while an author has its posting form open (the submit is simply
ignored for a field that no longer exists).

## One set of templates in styles/all, with the classic look as an option - 10/06/2026

The UI redesign roadmap's last open item was a style native to prosilver that could be
submitted upstream. The extension used to ship its page templates, scripts and CSS in
`styles/prosilver`, with its own `.kb-tab` button tabs and striped `.kb-index-table`, so
every prosilver-based board got that look and a board style that does not inherit from
prosilver got no page templates at all. Now:

- **`styles/all`** holds everything: the 13 page templates, the shared scripts, the three
  event templates and the stylesheets. `styles/prosilver` no longer exists. phpBB looks in
  a style's own folder, then its parents', then `styles/all`, so one set serves prosilver
  and every style based on it (and styles based on nothing).
- **The tab strip and the three article tables use prosilver's own classes** (`tabs`/`tab`/
  `activetab`, and `table1`), so they take on each board style's colours. The `kb-*` classes
  stay on the same elements as hooks.
- **`phpbbmodders_knowledgebase.css`** is the base sheet for every look: layout fixes for the
  article page, the copy button, the status badges, button fixes, the narrow article-table
  columns.
- **`phpbbmodders_knowledgebase_classic.css`** is the original look layered on top: slate
  button tabs, the striped table with a dark header, and the forum-style category list (and
  search results box) in the same muted slate instead of prosilver's blue. It loads only when the new ACP
  setting **Use the classic Knowledge Base look** (`kb_classic_look`, migration
  `version_2_0_21`, off by default) is ticked, on any board style. The setting reaches the
  template as `S_KB_CLASSIC_LOOK` from the page-header listener.
- This changes how the extension looks on boards that upgrade: it follows the board style
  unless the setting is ticked.

`kb_style_layout_test.php` (29 cases) guards the layout: `styles/all` is the only style
folder, every expected template, script and stylesheet is there, nothing refers to
`styles/prosilver`, the classic sheet is included only inside the setting's condition, the
base sheet carries none of the classic rules and the classic sheet none of the shared ones,
and the tab strip and tables carry both the prosilver classes and the `kb-*` hooks.
`kb_classic_look_test.php` covers the migration and the variable the listener assigns. The
full suite passes against the MySQL and PostgreSQL containers.

Claude checked it live. On the SQLite test board, every Knowledge Base page (index,
category, article, posting form, queue, "Manage Your Articles", search) loaded the base
sheet and its scripts from `styles/all` with status 200, with the classic sheet present
only when the setting was on. In the browser, prosilver showed the native light-blue tabs
with the setting off, and the slate tabs and striped table with it on. On a throwaway MySQL
board with the two custom styles installed in the web root, the same pages under
`prosilver_se_revolution` and `ProMinoDeux` (both based on prosilver) found the templates
and sheets in `styles/all` in both modes, and under `prosilver_se_revolution` the native
tabs took that style's colours. The real ACP checkbox was driven on fresh SQLite, MySQL and
PostgreSQL installs: unticked by default, saved on, saved off, saved on again, and the form
reflected each.

Not covered: a board style that inherits from nothing (not available to test), RTL and
very narrow phone widths beyond what the inherited prosilver rules give, and the print
stylesheet (only its path was updated and checked).

## Markdown rendering fixes - 10/06/2026

An article written in Markdown (a 35 KB phpBB CLI reference) showed three problems:

- **A white, invisible title.** Markdown's `# Heading` becomes an `<h1>`, and prosilver
  paints every `h1` white with the board-name margins, because it expects the board name in
  the header there. A forum post never contains one. `h3` was small capitals meant for a
  panel title. The base stylesheet now gives headings in article text their own sizes and
  spacing, and the `h1` the text colour (so it works on any board style); the colours of
  `h2` to `h6` stay the board style's own.
- **Ragged paragraphs.** phpBB's formatter turns every newline into a `<br>`, so text
  written in wrapped lines broke at every source line (114 stray breaks in that article).
  The Markdown formatter now leaves a newline inside a paragraph, a list item or a quote as
  a space, as Markdown does. Two trailing spaces still make a line break. BBCode articles
  are unchanged.
- **A table of contents that went nowhere.** Links such as `[Basic usage](#basic-usage)`
  had no heading to jump to. Headings now get an id built from their text (the same rule
  as GitHub's: lower case, other characters collapsed to a hyphen) with a `kb-` prefix, and
  a link that points at a fragment of the same page gets the same prefix, so the author's
  links work unchanged and a heading called "Search" cannot take the id of the page's own
  search box. The Markdown source is unchanged (editing shows `(#basic-usage)`). Two headings
  with the same text get the same id, and the link goes to the first.

Articles already stored kept their old breaks and had no ids until parsed again, so migration
`version_2_0_22` parses every Markdown article, pending revision and draft of an edit once
more and saves only the ones whose result changed (running it twice is harmless; BBCode
articles are skipped; the formatter is assembled from core services, because the
extension's own services are missing from the container while it is being enabled, which
the first version of the migration tripped over on a fresh install). The formatter's cache
keys were renamed (`..._v2`) so a cached parser or renderer from before the change is not
used. On the board with that article, the stored text lost its 114 `<br/>` and kept the same
visible text and the same 542 tags.

`kb_markdown_rendering_test.php` (16 cases) covers the fragment prefixing (including
`#Top`, links to other pages and a hostile fragment) and the migration (all three tables,
only Markdown rows, no change on a second run, more rows than one batch, and no formatter
built when there is nothing to do); `kb_style_layout_test.php` guards the heading rules. The
full suite passes against the MySQL and PostgreSQL containers.

Claude then checked it on SQLite, MySQL 8.0 and PostgreSQL 16 boards by posting a Markdown
article through the real form: a wrapped paragraph reflowed with no `<br>`, a hard break
stayed a `<br>`, a list item continuation was not broken, `h1` to `h3` rendered as headings
with prefixed ids, the table of contents link pointed at the prefixed id, a link to another
page kept its fragment, no id appeared twice, editing showed the source as typed, and a BBCode
article still broke at every newline. Rewinding each board's article, pending revision and
draft of an edit to the old form and re-running the migration repaired all three and left
the BBCode article alone. In the browser, the article's title appeared in dark text with a
rule under it, paragraphs ran the full width, and the table of contents was in place.

Not covered: the `#anchor` links of articles that repeat a heading's text, backslash line
breaks (not supported by the formatter), and the revision-review page and posting preview
in the browser (they use the same `.content` rules and renderer).

## Adding an article from the all-articles view - 10/06/2026

With "Flat article index" on, `category?id=0` lists every article and has no category of
its own, so the Add article and Submit a Redirect buttons and tabs were missing. They now
appear there and open the form with the first category the user may post in (tree order)
preselected. The posting form shows the Category select for new articles and redirects too
(it used to be edit-only), greying out categories the user cannot post in. A submitted
category is honoured only when the user has the permission there; a forged or unknown one
falls back to the preselected category. Redirects use the moderator `kb_m_approve`
permission, articles use `kb_u_add`.

`kb_postable_category_test.php` (6 cases) covers the choice: tree order rather than id
order, no postable category gives 0, the two permissions are independent, the answer is
memoized, and the search stops at the first permitted category.

Claude checked it live on fresh SQLite, MySQL and PostgreSQL installs with the setting on.
An author saw both buttons and tabs on the all-articles view, pointing at the first
category they could post in; the form preselected it and disabled the others; a chosen
category was used; a bogus category id was ignored. A moderator's redirect was filed in the
chosen category. A reader saw no add buttons and the posting form refused them. The full
suite passes against the MySQL and PostgreSQL containers (258 tests, no skips).

## phpBB Modders file headers on the PHP files - 10/06/2026

105 of the 116 PHP files now carry the org header (`Knowledge Base extension for the phpBB
Forum Software package`, `@copyright (c) 2026, phpBB Modders`, the GPL-2.0 `@license`). Files that
already named Sheer or sutrus keep those `@copyright` lines unchanged beneath the new one; the
35 test files and the 2.0.x migrations that had no header got the full one. The 11 files that
come from phpBB core (`search/`, `language/en/`) were left alone.

Only comments changed. Claude compared every touched file with its committed version token by
token, ignoring comments and whitespace, and found no difference; every other doc comment is
identical, all files pass `php -l`, and the full suite passes against the MySQL and PostgreSQL
containers (275 tests, 800 assertions). The live board still serves its pages.

## Flat index layout and the alternative stylesheet picker - 10/12/2026

The old "classic look" setting is split in two. Its layout (wrapping, compact tabs and
tables) belongs to the flat article index and loads `phpbbmodders_knowledgebase_flat.css`;
its colours became the bundled **Slate** alternative, chosen in a new ACP picker. Custom
CSS files from `store/phpbbmodders_knowledgebase/stylesheets/` are listed in the same
dropdown and served by `controller/stylesheet.php`. Migration `version_2_0_24` adds
`kb_alt_style_enabled` (0) and `kb_alt_style` (`bundled:slate`) and removes
`kb_classic_look` without mapping it. The article tables also moved into prosilver's
forum-header box with `bg1`/`bg2` rows: in the native look the header text was white on a
white page.

`kb_alternative_style_test.php` (68 cases) covers: which bundled and custom files are
listed (symbolic links, folders, oversized, badly named and non-CSS entries are not); names
that could leave the folder, including encoded and absolute paths and a null byte; a custom
folder that is itself a link; parsing of stored choices; what is active with the setting
off, on, and after the file vanishes; the variables the page header gives the templates for
each flat/colour combination; the controller's headers, 304 reply and 404s; what the ACP
saves (the choice is kept while off, a forged choice never replaces it, switching on with an
unavailable file is refused); the dropdown markup; and the migration. Removing the
symbolic-link check or the double-dot rule makes the matching tests fail. The layout tests
now check that the flat sheet has no colour declarations, that Slate has no layout
declarations and is commented, and the load order of the sheets. The full suite passes
against the MySQL and PostgreSQL containers: 345 tests, 959 assertions, nothing skipped.

Claude checked it live on the SQLite test board and on throwaway MySQL 8.0 and PostgreSQL 16
boards (fresh installs through the phpBB CLI installer, and an update from the previous
code with the old setting switched on, then `db:migrate`). On all three, through the real
ACP: no classic checkbox; the switch and dropdown present with Bundled and Custom groups;
saving on, saving off (choice kept), refusing an unavailable choice (nothing changed), a
forged choice ignored, and a custom file chosen; unsafe files not listed. On MySQL and
PostgreSQL the front end loaded exactly the expected sheets for all six combinations of flat
layout and bundled/custom colours, with the custom link after the board's own stylesheets;
the custom file came back as `text/css`; ten unsafe requests (link, oversized, `..` name,
wrong extension, folder, `../`, encoded `../`, absolute path, missing, empty) all returned
404; and removing the chosen file made the page load only the base sheet while the ACP
showed a warning and kept its controls. The update left the flat index setting unchanged on
both. On SQLite, conditional requests returned 304.

Screenshots in `docs/images/styling/` come from a headless Chromium script on the test
board: three board styles (prosilver, Revolution, ProMinoDeux), hierarchical and flat,
board colours and Slate, a phone width for each, and a custom selected-tab sheet. Computed
colours matched expectations (own palette per style with no sheet, Slate's `#536482` with
it, the custom green only on the selected tab) and no page scrolled sideways. The code box on
the article page is a self-contained white box with dark text on all three styles.

Not covered: the final tab shape and colour (waiting on Kailey), keyboard focus and hover
states and print layout under Slate, board styles other than these three, a style that
inherits from nothing, and right-to-left languages. The test board's nginx was not checked
for serving `store/` (the extension serves custom files itself and never exposes the
folder). Fresh SQLite installs were not repeated; the SQLite board was updated with
`db:migrate`.

## Sorting order setting: clear names, tooltips and screen-reader text - 10/12/2026

The ACP "Sorting order" choices are now **Fixed** (was "forcibly"), **Selectable** and
**Alphabetical**, each with a one-sentence explanation. The explanation is the
tooltip on the choice and is also read out with the radio button through `aria-describedby`
(a visually hidden span, not `display: none`). The three radios sit in a `radiogroup` labelled
by "Sorting order:"; the old label pointed at the first radio only. The ACP's key
`ALPHABET` ("alphabetic navigation") was the same key the reader pages use for the A-Z row,
so it was renamed `KB_SORT_ALPHABET` and `FORCIBLY`/`SELECTABLE` became `KB_SORT_FIXED`/
`KB_SORT_SELECTABLE`. On the reader side, the alphabetical navigation is now a labelled
`navigation` landmark, every letter link has a "Articles starting with X" tooltip, and the
selected letter's link carries `aria-current`.

`kb_acp_sort_type_test.php` (7 cases) checks the names and explanations, that the old keys are
gone, the group, label, tooltip and description wiring of each radio, the hidden-text CSS,
and the navigation markup. Claude checked the real ACP page in headless Chromium: the group is
named "Sorting order:" and described by its explanation, each radio has a matching label,
tooltip and description, and the text is 1 px wide (hidden but present). On the reader page
with alphabetical navigation switched on, the landmark is labelled, all 26 letters have the
tooltip, only the chosen letter is marked current and the move arrows are absent. The setting
was put back to Fixed afterwards. A screen reader itself was not run.

## Announcement forum: one choice instead of a checkbox and a forum - 10/12/2026

"Announce new articles in a forum" was a checkbox that revealed a forum select, while the
changelog forum was a select with a "None (disabled)" choice. Both now work the changelog way:
one select per setting, "None (disabled)" first and the default. Choosing a forum switches
announcements on (`kb_anounce` 1 and `kb_forum_id`); choosing None switches them off (both 0), so
the two can no longer disagree (before, a ticked box with no forum made the posting page report a
configuration warning). The prefix options appear when a forum is chosen. A board that had kept a
forum while announcements were off now shows None, matching what it does. The old checkbox text
was adjusted: "If enabled, ... you choose below" became "A topic ... in the forum you choose
whenever a new article is added. Choose None (disabled) to turn announcements off." Both selects
now have a label bound to them. Fresh installs already default both to 0 (None).

`kb_acp_announcement_forum_test.php` (7 cases) covers saving (a forum turns it on; None, nothing,
a negative id and non-numeric input turn it off), the page markup and the wording.
Claude drove the real ACP on fresh MySQL 8.0 and PostgreSQL 16 boards and on the SQLite test
board: defaults 0/0/0 in the database, both selects start on None with the prefix options hidden,
no checkbox, choosing a forum reveals the prefix options, saving writes the forum and the
on-flag, choosing None hides them and saves 0 for both, and a reload shows what was saved. The
SQLite board's own values were put back afterwards.

## Author PMs, comments and changelog - 10/11/2026

Recorded from a Codex session (pull request #45, merged). The behaviour:

- The English navigation and page title say **Knowledge Base**.
- Approval and rejection private messages default on in the UCP notification preferences. Members
  can switch either off independently; an explicit saved opt-out stays in effect, and a missing
  preference uses the default. The former ACP forced-delivery option is gone; migration
  `version_2_0_23` removes its configuration key (`kb_require_author_pm`) and leaves every user's
  preferences alone. A moderator's existing option to skip messaging is unchanged.
- The custom PM method supports approval and rejection only; other notification types do not gain
  PM delivery.

Checked on the disposable SQLite board with visible Chromium and the seeded author, reader and
moderator accounts; twelve workflow checks passed:

- navigation and heading say Knowledge Base;
- both author PM choices are checked with no saved preference;
- default-on approval delivers a real PM that shows in the author's inbox;
- the reader opens the generated public comments thread, posts a comment the author sees, and
  sees the author's reply;
- the moderator reads the private submission changelog; the reader is denied the changelog forum,
  topic and reply form; the moderator appends a private reply;
- saved UCP opt-outs persist and stop both approval and rejection PMs; re-enabling delivers a real
  rejection PM;
- an author edit appends a revision entry and body diff to the same changelog topic.

The ACP configuration form renders without the old author-PM control, and disabling and
re-enabling the extension applied the migration without purging article data (the migration
record exists and the key is absent). The PHPUnit run at the time was 277 tests with 16
database-dependent cases skipped, so it made no MySQL/PostgreSQL claim. Claude later ran the full
suite on MySQL 8.0 and PostgreSQL 16: 277 tests, 805 assertions, nothing skipped.

Example threads from that run (local IDs, not stable after a reset): article 267 "Comments and
changelog verified walkthrough", comments topic 18, changelog topic 17, at
`http://localhost:8092/knowledgebase/article?k=267`, `viewtopic.php?t=18` and `viewtopic.php?t=17`.
Log in as the seeded moderator for the private changelog. The comments hold a reader question and
an author reply; the changelog holds the submission entry, a moderator reply and a revision diff.
The fixture grants changelog access to KB Team and administrators, so this proves an ordinary
reader is denied, not every possible group combination. Approval reasons go to the author; they
are not appended to the changelog (observed, not changed).

The browser scripts were exploratory (`/tmp/playwright-test-kb-comments-pm.js` and
`/tmp/playwright-test-kb-changelog-revision.js`, since lost) and wrote content to the disposable
board, so never run such scripts against a real board. Harness corrections made on the way (native
forum Submit naming, the required assignment/review order, phpBB's generic denial container, a
read-only retry for a redirect race) were not application fixes.

## Fixture and browser testing method - 10/11/2026

A handoff from Codex's local fixture and browser run, not a claim that every feature passes. The
PHPUnit and database suites above remain the primary checks; do not substitute this for them.

**Setup.** The fixture tooling is `/home/william/Desktop/repos/seed-forum` (seed script
`bin/seed-kb-fixtures.php`, runner config `config/local.yml`, inventory
`docs/knowledgebase-fixtures.md`). The disposable board is
`/home/william/Desktop/repos/seeded-board/kb` (SQLite `store/sqlite.db`) at
`http://localhost:8092/knowledgebase`, with the extension bind-mounted from this checkout (the
mount is lost on reboot and must be recreated with `sudo mount --bind`). After the final reset in
that run the board held 265 articles, 37 categories, four main trees six levels deep, three team
groups, 41 pending revisions, two private draft edits, five custom fields and eight attachment
rows. Outgoing mail is disabled. Fixture attachment files are written to the extension's mounted
`files/` directory, which is not wholly disposable.

**Resetting.** Confirm the runner config targets the disposable board; never `--reset` a working
or production board. Stop all browser sessions first (a browser using SQLite during installation
once caused a lock failure). From `seed-forum`: `php -l bin/seed-kb-fixtures.php`,
`git diff --check -- bin/seed-kb-fixtures.php`, `bin/reset-board.sh --validate`, then
`bin/reset-board.sh --reset`. Check installer output and live HTTP, browser and database state,
not just the exit status, and log in again afterwards because old cookies, ACP SIDs and form
tokens belong to the old installation. For a display-only change use
`bin/reset-board.sh --purge-cache` instead of a full reset.

**Browser method.** Visible or headless Chromium through the Playwright skill
(`node <skill>/run.js script.js`), one browser context per role (guest, reader, both authors,
moderator, admin). The public fixture password for `kb_reader`, `kb_author1`, `kb_author2`,
`kb_moderator` and `admin` is `KbTest1234!`; keep the board local and never commit session state
or form tokens. For the ACP, follow the real ACP link from the logged-in board and re-authenticate;
a bare `/adm/index.php` does not establish the session. Wait until a phpBB form is more than a
second older than its hidden `creation_time` before submitting, or it fails as a false login or
form error. Make changes through the real controls, then check SQLite rows and files independently;
use `.timeout 5000` for read-only SQLite queries and never write expected results into the
database. The scripts in `/tmp` were exploratory and have since been lost; inspect prerequisites
and mutations before reusing any like them.

**What the run exercised (153 browser checks, 2 initial failures).** Every category as a reader
with all breadcrumbs (four roots, sibling branches, empty categories, deep leaves); reader posting
denial, draft privacy, author permissions, read-only categories, team-only contribution and
category-scoped moderation; all ten queue options with paging; named BBCode and Markdown articles,
group-authored articles, a Markdown anchor, keyword search and custom-field filtering; upload,
required-field errors, private draft, submission, assignment, team review, reason enforcement,
approval, private further edit, revision comparison and approval, deactivation, reactivation,
group and co-author publication, rejection and deletion; attachment gates per role and article
status, image loading, thumbnails and removal on delete; copy-link and the print view; desktop,
tablet and phone widths with overflow checks; every ACP module, saving the configuration, the
hierarchical and flat views, and a configuration save without its CSRF token being refused; a
disposable deep category (copied permissions and default team) renamed, moved across trees and
deleted; a disposable custom field and value added, toggled and deleted; an attachment extension
added, disabled and re-enabled; the Operations Team permission editor and mask; deleting and
recreating the native search index; cleaning the deliberately missing attachment row; and tree
interval, depth, field-assignment and counter checks. Approval and rejection bell notifications,
PMs and emails were observed through a temporary Mailpit container on `127.0.0.1` only, which the
final reset removed; pending-review email delivery was not confirmed and must not be described as
passing. Codex's visual report and per-suite JSON lived under
`/home/william/Documents/Codex/2026-10-09/look-at-home-william-desktop-repos/kb-browser/`; treat
those screenshots as historical views from before the final reset.

**Two defects found and fixed (pull request #44).** (1) Preview cleared the selected custom-field
values and cross-listed categories, so Submit then failed the required-field checks;
`controller/posting.php` redisplayed submitted selections only for `$submit`, not `$preview`. (2)
Advanced Search disabled category options for a reader who could browse them, because
`controller/search.php` called `make_category_select(0, [], false)`, whose default permission is
the contribution right `kb_u_add`; search now builds its options after the existing read and
search checks without contribution ACL. Do not give a reader posting permission to make a test
pass. The regression script is `tests/browser/kb_selection_regressions.js` (seven checks: Preview
on a new and an existing article, cleared optional choices, category choice on desktop and phone,
real search results, reader posting denial, guest denial); run it with
`node <skill>/run.js tests/browser/kb_selection_regressions.js`, with `KB_TEST_URL`,
`KB_STATE_DIR` and `KB_ARTIFACT_DIR` overriding the URL, saved role states and output folder.
The article custom-field layout was also corrected in `controller/article.php` (each nonempty field
ends in a line break; empty fields add none), with six browser checks at 1440, 768 and 390 px.

**Avoiding false failures and false passes.** Uploaded images are outside `.content`; inspect
`img.postimage`. The print button uses its rendered label and icon, not an invented title; check
the clipboard for copy-link. Match real localized text and capitalization (field toggling shows
"make required", cleanup says "successfully synchronized"). The search page has two `#keywords`
elements; use the search form's input. A no-match term must respect the word-length limit, since a
longer word gives a validation message, not an empty result. When indexing, wait for the finished
index and cleared progress, not the first nonzero count. Separate real defects from harness
errors: fix a bad selector and rerun, and never relabel a real failure as a pass.

**Not covered.** Bulk extra-file deletion (needs a separately isolated upload directory), permission
writes and ACL_NEVER inheritance, category deletion with article and subcategory moves, every
upload type, size and count boundary, stale-edit conflicts, revision rejection, every notification
preference combination, alternate search backends, other database and browser engines, and the
install, upgrade, disable and purge lifecycle in that run. The seeded changelog forum is not proof
of every changelog operation. The intended standard is visible, role-aware workflows with explicit
failures and a clear list of what remains, never "all features passed".

## phpBB 4.0 compatibility fixes - 10/12/2026

A check against phpBB master (4.0.0-a3-dev) found three things, now fixed so the same code runs
on 3.3.x and 4.0 (details in [phpbb-4.0-compatibility.md](phpbb-4.0-compatibility.md)): the three
notification types returned the avatar as a string where 4.0 needs an array (a fatal `TypeError`),
the plupload directories came from the `upload_path` setting that 4.0 removes, and two image size
limits that 4.0 removes were read without a default.

`kb_phpbb4_compat_test.php` (13 cases) simulates both cores by overriding only the version check:
each type returns the loader's avatar unchanged in either shape, a group author gets `''` on 3.3.x
and `[]` on 4.0, the 4.0 base class is recognised by its avatar helper, the upload path comes from
`upload_path`, the 4.0 storage setting or the `files` default (including a trailing slash and
empty values), and the image limits tolerate being absent. The full suite passes against the MySQL
and PostgreSQL containers: 372 tests, 1028 assertions, nothing skipped.

Claude checked it live on a copy of a local phpBB 4.0.0-a3-dev board (three weeks older than the
master that was read) with the cap in `composer.json` lifted in the copy only. All three
notification types, including a group author, build their display data after the fix (before it,
each threw the `TypeError`); `get_upload_path()` returns `files` from the storage setting; a small
image and an 8.6 MB file (uploaded in four chunks) both uploaded through the posting form; and
every front-end page and ACP module still loaded. The old code also uploaded fine on 4.0 because
the extension sets its own directories earlier in the request, so the upload-path change corrects
a latent inconsistency rather than a break. On the 3.3.x test board the notification pages still
render for a member with Knowledge Base notifications (8 of them for `kb_author1`) with no error.

Not covered: email delivery of the notifications on 4.0, the 4.0 extension catalogue and Composer
handling, and the current master (the live board was three weeks older). The declared phpBB and PHP
constraints were left unchanged.

## PHP 8.2 minimum - 10/12/2026

`composer.json` now requires PHP `>=8.2` (it said `>=8.0`), and `ext.php` refuses to enable the
extension below the same version; its check now compares against `MIN_PHP_VERSION` instead of a
second hard-coded number, through `ext::php_version_meets_minimum()`. This is a policy choice. The
extension uses nothing specific to 8.1 or 8.2: all 126 PHP files linted clean and the 372 unit tests
passed on PHP 8.0.30 (Docker `php:8.0-cli`; the 17 database cases skip there for lack of drivers)
before the change. phpBB 4.0 needs 8.2 itself and the only CI job that runs (see the note under "PHP 8.5") uses
PHP 8.2, so the stated floor is the version CI tests. A 3.3.x board on PHP 8.0 or 8.1 can no longer enable it.

`kb_declared_support_test.php` (14 cases) keeps the claims from drifting: the Composer and
`ext.php` minimums are the same; eight PHP version strings are judged correctly (8.0, 8.1 and an
8.2 development build refused, 8.2.0 and later accepted); the PHP running the tests passes; the CI
matrix starts at the declared minimum, has at least three versions and includes the primary one;
the phpBB requirement and soft requirement carry the same minimum and the same `<4.0.0` cap; and the
compatibility notes state the same floor. The full suite passes against the MySQL and PostgreSQL
containers (386 tests, 1052 assertions, nothing skipped), and a fresh MySQL board enabled the
extension and recorded version 2.0.24. The phpBB cap was not changed.

## Running on phpBB 4.0 and allowing it in the constraints - 10/12/2026

`composer.json` now allows phpBB 4.0.x: the requirement is `>=3.3.19 <4.1.0` and the soft requirement
`>=3.3.19,<4.1.0@dev` (both said `<4.0.0`). To back that, the extension was run, with the constraints
exactly as shipped, on a local phpBB 4.0.0-a3-dev board (three weeks older than the master that was
read) and checked end to end. It found three more problems, all fixed so one code base serves 3.3.x
and 4.0:

- Every icon was drawn blank, because 4.0 uses Font Awesome 6 and needs different markup. All icons
  now come from `inc/icon.php` through the template function `KbIconClass()` (a tagged Twig
  extension, `inc/twig_icon.php`); 3.3.x output is unchanged.
- The article page returned a 500: the text renderer service passes `configure_user()` its arguments
  in the 3.3.x order and 4.0 declares them in another. `textformatter/renderer.php` accepts any order.
- An uploaded file's recorded size was that of its last chunk, on every version; it is now the merged
  file's size.

New tests: `kb_icon_test.php` (11 cases: 3.3.x classes identical to the old markup, 4.0 markup, the four
renamed outline icons, a free-typed icon name passed through, the Twig function rendered through a real
Twig environment and its service tag, and no hand-written icon class left in any template or PHP
file) and `kb_renderer_argument_order_test.php` (7 cases: all six argument orders reach the parent
correctly, and the service definition). The migration tests now pass the seventh constructor
argument 4.0 added, the test autoloader stays quiet for a class that does not exist (as the real one
does), and three tests that build their schema with a 3.3.x-only class skip themselves on 4.0.

The whole suite now also runs against the 4.0 core classes (`PHPBB_ROOT_PATH` set to the 4.0 board's
root): 404 tests, no failures, 28 skipped (16 database cases without a database, 12 that use the
3.3.x-only schema tools). Against the 3.3.x core with the MySQL and PostgreSQL containers it is 404 tests,
1092 assertions, nothing skipped.

On the 4.0 board, with a real browser: all 178 visible icons across the front-end and ACP pages draw
(all 391 on the 3.3.x board); every page and ACP module returns 200; the ACP settings work and save,
flat index and Slate load the right sheets, a custom stylesheet is served as `text/css` with
`nosniff` and unsafe names get 404; articles submit and render (Markdown headings, code box), the
alphabetical navigation has its landmark and 26 tooltips; approval and rejection notifications show
in the bell and the notification list with avatars; a small image and a 9 MB file uploaded in nine
chunks both succeed with the right size. Fresh MySQL and PostgreSQL boards on 3.3.x still render every
page with every icon drawn.

Not covered: email delivery of notifications on 4.0, the 4.0 extension catalogue and Composer
handling, the current master (the board was three weeks older), and the approve/deny review flow
beyond building its notifications (the approve page needs a review-team assignment first).

## The remaining 4.0 checks - 10/12/2026

The points left open in the 4.0 run were checked on a tree built from the current phpBB master (`a57d60d`,
4.0.0-a3-dev) and installed through the real `seed-forum` reset (`reset-board.sh --reset`, with an
extra config that points at that tree and has no nginx site). The installer enabled the extension with
the shipped constraints, ran the 33 migrations, and the two seed scripts produced the usual inventory (265
articles, 37 categories); every front-end page returned 200 with all its icons drawn.

- **Review flow, in a browser:** a regular author submitted an article (the required custom fields had to be
  chosen first); the moderator was notified; the moderator assigned it to a team, a team member reviewed it
  and it was approved, with the status moving 0, 1, 2; the author received the approval notification and a
  private message, and the approved article page and notification list rendered.
- **Email:** with a local SMTP sink as the board's mail server and email notification preferences ticked,
  the pending-article email (to the moderator) and the approval email (to the author) were delivered. 4.0
  queues mail and sends it from cron, so the messages arrived after the queue interval, not during the
  request. The pending-article email read "at the conference"; the template now says "on", and a test checks
  that none of the three email templates uses that word and each starts with a subject and ends with the
  signature.
- **Extensions manager:** the ACP listing and the details page load and show the right version and
  requirements; disable, enable, purge and enable again behave as expected (data kept across disable,
  everything removed by purge, a clean reinstall after).

The `seed-forum` reset itself worked on 4.0 once given a 4.0 source tree; only its download step is 3.3.x-only.
Not covered: the 4.0 catalogue and Composer-based installation from the ACP, and later 4.0 builds.

## ACP configuration page: no chmod warnings - 10/12/2026

Opening the Knowledge Base configuration page printed two PHP warnings, `chmod(): Operation not
permitted`, for the upload folder and its `plupload` sub-folder. The page called `chmod(..., 0777)` on
both every time, and `chmod()` fails with a warning on a folder the web server user does not own even
when its mode is already fine (here the folders were `0777` but owned by another user). The page now
leaves a folder alone when it is already writable and only tries to open it up when it is not, without a
warning if that is not allowed either; the write test that follows still reports a folder that
cannot be written.

`kb_acp_upload_directory_test.php` (4 cases) covers a writable folder keeping its mode, a read-only
folder being opened up, a folder owned by someone else that is already writable (`/tmp`) and one that cannot be
opened up (`/usr`) both raising no warning; the last two fail with the old unconditional `chmod()`.
The configuration page showed no warning on the 3.3.x test board and on the current-master 4.0 board, and
the suite passes on both cores (411 tests; 3.3.x with MySQL and PostgreSQL, nothing skipped).

## PHP 8.5 - 10/12/2026

PHP 8.2 stays the minimum (it is the oldest branch still getting security fixes and the one phpBB 4.0
needs), and 8.5 is now listed in the CI matrix next to 8.2, 8.3 and 8.4 because the maintainers' hosting
runs 8.5.x. In practice only the primary 8.2 job runs in CI: the database jobs are switched off in
`.github/workflows/tests.yml` (`RUN_MYSQL_JOBS: 0` and the others), and the matrix only expands for
those, so 8.3 to 8.5 are covered by local runs (8.4 is this machine's PHP, 8.5 in Docker), not by CI. On PHP 8.5.11 (Docker `php:8.5-cli`) all 126 PHP files lint clean and the unit suite passes
(411 tests, 18 skipped: 17 database cases without a database driver, and one that needs a user who
cannot write everywhere). The only deprecation notices come from phpBB 3.3.19 itself and its Twig
(dynamic properties in the SQLite driver, implicit nullable parameters); none come from this
extension. `ReflectionProperty::setAccessible()` is deprecated in 8.5 and has had no effect since 8.1, so
the tests no longer call it. A test that expected `/usr` to be unwritable now skips when the tests run as
root, as they do in a container.

## Flat index by default, with a hierarchical checkbox - 10/13/2026

The flat article index is now the default. `kb_flat_index` is replaced by `kb_hierarchical_index` (migration
`version_2_0_26`, version 2.0.26), off by default; the ACP checkbox is **Hierarchical category browsing**
and brings back the category tree. The old setting is removed and not mapped; nobody has installed the
extension yet, so there is nothing to preserve.

On the 3.3.19 SQLite test board (the migration ran with `db:migrate`): `kb_hierarchical_index` was 0, `kb_flat_index`
was gone and `kb_version` read 2.0.26. A browser run of 10 checks confirmed that the ACP shows the new
checkbox unticked and no old one, that `/knowledgebase` lands on the flat all-categories page with its tab strip, that
ticking the box shows the category tree on the home page and sends `category?id=0` back to it, and that
unticking returns to the flat page. The earlier 23-check tab run (five tabs as an administrator, three styles, 1100 px
and 390 px, flat, hierarchical, and Slate) was repeated with the new checkbox and passed. A re-run of the
default checks on a board that already had the box ticked failed its first two default-state checks, as expected, and
the board was put back to the default afterwards.

The 10 default-and-checkbox checks were then run on the five other boards, built fresh from the branch: phpBB 3.3.19 with MySQL 8.0 and PostgreSQL 16, and
phpBB 4.0.0-a3-dev with SQLite, MySQL 8.0 and PostgreSQL 16 (six boards in all, 10 of 10 on each). The 23-check tab run was done on the 3.3.19 SQLite board only.

`kb_hierarchical_default_test.php` (11 cases) covers the migration's steps, dependency, skip rule and revert,
the version matching `composer.json`, that no code outside the old migrations reads `kb_flat_index`, the three
controller choices, and the ACP checkbox with its label keys; `kb_alternative_style_test.php` now expects the
flat layout by default and the new setting. The suite passes with 435 tests (17 skipped: database cases without the
MySQL and PostgreSQL fixtures, which this change does not touch).

## Enabling on phpBB 4.0 with MySQL - 10/14/2026

Enabling the extension on a fresh phpBB 4.0.0-a3-dev board with MySQL 8.0 stopped with `Can't DROP 'un_mtch'`. Migration
`version_1_0_3` replaces the word-match table's unique key on MySQL with `DROP INDEX un_mtch`, but phpBB 4.0's schema tool
names MySQL indexes with a table prefix (`kbsrcwrdmt_un_mtch`), so that name does not exist there; phpBB 3.3 names it
`un_mtch`, which is why the 3.3 MySQL boards were unaffected. The migration now asks the table which unique keys cover
the match columns (`SHOW INDEX`) and drops those, whatever they are called, then adds the four-column key as before; the
PostgreSQL and SQLite statements are unchanged. The migration had not been applied anywhere with the old behaviour
that this changes, since it could not finish on 4.0 with MySQL and was already correct on 3.3.

On a fresh 4.0 MySQL board (Docker `mysql:8.0`) the extension now enables, the word-match table ends with a
four-column unique key and the KB fixtures seed. `kb_search_key_migration_test.php` (5 cases, 4 of which fail on the old
code) covers the key being dropped under the 3.3 name and under the 4.0 name, no old key at all, an unrelated unique key
being left alone, and the PostgreSQL and SQLite statements; the suite passes with 417 tests (17 skipped: database cases
without the MySQL and PostgreSQL fixtures).

## Native search key on phpBB 4.0 with PostgreSQL and SQLite - 10/14/2026

Testing the board search on fresh phpBB 4.0.0-a3-dev boards found that the native search left articles only partly indexed on
PostgreSQL 16 and on SQLite: 93 indexed words and 351 word matches against 451 and 5,599 on the phpBB 3.3 boards
and on 4.0 with MySQL, with the same 265 articles. Words that only the later articles hold (such as "guide" and "practical")
were missing, so a search for them found nothing. The word-match table there had the original three-column unique key
(`kbsrcwrdmt_un_mtch` over article_id, word_id, title_match) and no four-column one. Migrations 1.0.3 and 2.0.15 drop and add a key
called `un_mtch`, but phpBB 4.0 names the key with a table prefix, so they do not find it. With only the three-column key, a word in
both the title and the description of an article is refused by the database, the indexer does not report the refusal,
and the rest of that article's words are lost (the same effect as the one fixed in the Wiki's port of this engine).

Migration `version_2_0_25` reads the table's unique keys by their columns (`SHOW INDEX` on MySQL, `pg_indexes` on
PostgreSQL, `PRAGMA` on SQLite), leaves a board that already has the four-column key alone, and otherwise drops the narrower key and adds
`un_mtch` over all four columns; other databases are not touched. On fresh 4.0 boards (Docker `postgres:16-alpine`, and SQLite) the extension
now ends with a single four-column unique key and the native index has 451 words and 5,599 matches, as on the other boards.

`kb_match_key_repair_test.php` (7 cases) covers the three-column key being replaced on MySQL and on PostgreSQL, a board that already has the key being left alone,
an unrelated unique key being kept, a database the migration does not read, the version steps, and a real SQLite table: the word that a
three-column key refuses as a description match inserts after the repair, and a second run changes nothing. The suite passes with 424 tests
(17 skipped: database cases without the MySQL and PostgreSQL fixtures).

## Knowledge Base matches in the board search - 10/14/2026

A **Knowledge Base (N)** block above the forum results of a keyword search, an **Also search the Knowledge Base** option in the advanced search, and
two ACP settings under **Extensions > Knowledge Base > Search** (the block on or off, and how many articles it lists; migration `version_2_0_27`,
version 2.0.27). The articles are found by the search engine chosen in the ACP, the same one the Knowledge Base's own search page uses; the
snippets come from the rendered article text, because articles are stored as formatter XML.

`kb_board_search_test.php` (27 cases) covers the migration, the block shown with its count and its link to all matches, 14 kinds of search that show no
block (no words, a later page, either switch off, no read permission, the box unticked, a pre-made search, a search by author or author id, a search inside
one topic, no index, words it cannot search on, no matches, a count with nothing to list), the size taken from the ACP, the option and the way an unticked box
is carried through the result links, the three core events hooked, the templates and ACP fields, and the snippet text (tags removed, entities escaped again, a
script tag left as text). A 26-check browser run covers the ACP fields, the option, the block above the forum posts with highlighting and a count that agrees
with the Knowledge Base's own search, the header search box, an unticked box and its links, no block for pre-made, author, topic or no-match searches, titles-only
and text-only counts, the block size, a member who may not read the Knowledge Base, and both switches. It passes on phpBB 3.3.19 and 4.0.0-a3-dev, each with SQLite, MySQL 8.0
and PostgreSQL 16 (native search on all, and MySQL fulltext and PostgreSQL fulltext on their own databases): 11 runs, 26 of 26 each. The boards were built fresh from this
branch and seeded with seed-forum's fixtures, which now include forum questions about backups, clean URLs and installation for the block to sit beside. The suite passes
with 462 tests (17 skipped: database cases without the MySQL and PostgreSQL fixtures, which this change does not touch).

Two bugs in older migrations showed up while building the 4.0 boards and were fixed in their own pull requests (#51 and #52, recorded above). The Knowledge Base's own search page shows its snippets as the markup the author typed (for example `[b]` tags and `#` headings), where the block shows plain text; this change does not touch that page.
