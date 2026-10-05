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

Without database fixture ports, the four MySQL/PostgreSQL integration
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
  the pending article. With `kb_require_author_pm` turned on, a rejection also
  created private messages.
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

Only the English mail was read. The Czech, French and Russian folders ship the
same three file names, so the fix applies to them too, but their text was not
checked.

Not covered: larger datasets and delivery through a real mail server.

## Markdown articles - 10/05/2026

An article can now be written in Markdown instead of BBCode, chosen per article
with a "Use Markdown" checkbox on the posting form. It is stored with an `article_markdown`
flag on `kb_articles`, `kb_article_revisions` and `kb_article_draft_edits`
(migration `version_2_0_16`), parsed and rendered by a Knowledge Base only
instance of phpBB's s9e formatter with the LiteDown and PipeTables plugins, so
forum posts are never parsed as Markdown. Differences from the Pages extension's
method: header ids are left off, because a heading such as "Search" would get
`id="search"` and collide with the page's own search form.

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
article (it quotes the Markdown source as plain text), print view, and the
wording of the new text in the French, Russian and Czech language files, which
has not been reviewed by a native speaker.
