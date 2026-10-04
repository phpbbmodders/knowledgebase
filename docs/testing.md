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
