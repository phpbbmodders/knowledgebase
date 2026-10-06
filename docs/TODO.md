# TODO

Project-wide overview of unfinished/planned work. Detailed plans live
under [`docs/TODO/`](TODO/).

## Active

Nothing is currently open.

## Archived Reviews

The [UI redesign roadmap](TODO/archive/archive1.md#ui-feature-redesign-roadmap) - five phases modelled on phpbb.com's Knowledge Base - is implemented and was archived on 10/05/2026. Its one open item, a prosilver-native style for upstream submission, was finished on 10/06/2026: the templates now follow the board style, and the extension's original look is an optional ACP setting.

The last deferred finding from the [September 26 AI validation](TODO/archive/archive1.md#ai-validation-review-2026-09-26), the `kb_version` setting that no migration removes, was closed on 10/05/2026 as intentional: it is the version marker that 23 migrations read to skip steps a board already has, and the one the original-package upgrade relies on, so it stays. Each of those migrations now says so. The other finding, the N+1 queries in `migrations/version_1_0_2.php`, was fixed the same day.

The [phpbb.com KB parity gaps](TODO/archive/archive1.md#phpbb-com-kb-parity-gaps) - five feature-completeness differences found by comparing screenshots with the original phpbb.com Knowledge Base - are all done and were archived on 10/05/2026.

Completed [database search validation](TODO/archive/archive1.md#database-search-validation)
is recorded separately from the historical reviews below.

The [multi-author, edit-conflict locking, moderator-author messaging, and related gaps](TODO/archive/archive1.md#authorship-and-revisions-todo) plan is complete and was archived on 10/05/2026. One note stays open by choice there: the board-index category-card view shows only a one-article teaser per category.

The [Claude full codebase review of 09/30/2026](TODO/archive/archive1.md#claude-code-review-2026-09-30) is archived: all 12 findings were resolved (pull request #17), and the checks that followed are in the [testing notes](testing.md).

The [September 26 AI validation](TODO/archive/archive1.md#ai-validation-review-2026-09-26) describes the pre-remediation snapshot. Its original findings are preserved, but the old Deny recommendation is not a current-tree verdict. The two deferred design suggestions above remain active; archiving is not a fresh validation pass.
