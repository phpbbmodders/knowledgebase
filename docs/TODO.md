# TODO

Project-wide overview of unfinished/planned work. Detailed plans live
under [`docs/TODO/`](TODO/).

## Active

- Two known, deliberately-deferred `[valinfo]` findings from
  [archived September 26 AI validation](TODO/archive/archive1.md#ai-validation-review-2026-09-26)
  (neither is a bug, both are design suggestions): `migrations/version_1_0_2.php`
  has an N+1 query pattern (a per-category query inside a loop, plus a
  discarded first result) - real on a large install, but it's a
  historical already-released migration, and editing a released
  migration in place is itself the riskier move, so this needs an
  actual migration-replacement/maintenance-path decision, not a quick
  patch. (Two other released migrations were since edited in place at
  the owner's direction - `1_0_0`'s PostgreSQL string quoting and
  `1_0_3`'s duplicate `drop_columns` key - so there is precedent, but
  those were bugs; this one is a performance suggestion, so it stays
  deferred unless the owner asks for it.) Separately, `kb_version` is stored in phpBB config with no
  migration ever removing it; the suggestion is to make package
  metadata/migration dependencies authoritative instead and clean up
  the config value in a later migration - migration `2_0_11` still
  writes it, so this remains unaddressed by choice for now.

## Archived Reviews

The [phpbb.com KB parity gaps](TODO/archive/archive1.md#phpbb-com-kb-parity-gaps) - five feature-completeness differences found by comparing screenshots with the original phpbb.com Knowledge Base - are all done and were archived on 10/05/2026.

Completed [database search validation](TODO/archive/archive1.md#database-search-validation)
is recorded separately from the historical reviews below.

The [multi-author, edit-conflict locking, moderator-author messaging, and related gaps](TODO/archive/archive1.md#authorship-and-revisions-todo) plan is complete and was archived on 10/05/2026. One note stays open by choice there: the board-index category-card view shows only a one-article teaser per category.

The [Claude full codebase review of 09/30/2026](TODO/archive/archive1.md#claude-code-review-2026-09-30) is archived: all 12 findings were resolved (pull request #17), and the checks that followed are in the [testing notes](testing.md).

The [September 26 AI validation](TODO/archive/archive1.md#ai-validation-review-2026-09-26) describes the pre-remediation snapshot. Its original findings are preserved, but the old Deny recommendation is not a current-tree verdict. The two deferred design suggestions above remain active; archiving is not a fresh validation pass.
