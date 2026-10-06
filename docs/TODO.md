# TODO

Project-wide overview of unfinished/planned work. Detailed plans live
under [`docs/TODO/`](TODO/).

## Active

- [phpbb.com KB parity gaps](TODO/todo-phpbb-com-kb-parity.md) - five
  feature-completeness differences from the original phpbb.com
  Knowledge Base, found via a second-opinion screenshot comparison
  after the Phase 1-5 redesign ([`docs/ui-redesign-roadmap.md`](ui-redesign-roadmap.md))
  was already implemented. Items 1, 4, and 5 are done as of
  09/28/2026: an explicit "Approved article"/"Article requiring
  approval" choice for a moderator submitting a new article, a
  "Display options" dropdown on the Administration queue (new and
  revisions by default, all articles and redirects, redirects, inactive
  articles, or one team's, with or without the active ones; reworked
  from a filter plus a "show all" checkbox on 10/05/2026), and a direct "View
  topic" moderator action on the queue and approve pages. Item 3, the
  plain-text Description, is also done as of 10/05/2026. Remaining:
  required-field validation on categories/custom fields.
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

Completed [database search validation](TODO/archive/archive1.md#database-search-validation)
is recorded separately from the historical reviews below.

The [multi-author, edit-conflict locking, moderator-author messaging, and related gaps](TODO/archive/archive1.md#authorship-and-revisions-todo) plan is complete and was archived on 10/05/2026. One note stays open by choice there: the board-index category-card view shows only a one-article teaser per category.

The [Claude full codebase review of 09/30/2026](TODO/archive/archive1.md#claude-code-review-2026-09-30) is archived: all 12 findings were resolved (pull request #17), and the checks that followed are in the [testing notes](testing.md).

The [September 26 AI validation](TODO/archive/archive1.md#ai-validation-review-2026-09-26) describes the pre-remediation snapshot. Its original findings are preserved, but the old Deny recommendation is not a current-tree verdict. The two deferred design suggestions above remain active; archiving is not a fresh validation pass.
