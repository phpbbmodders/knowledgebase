# Archived Reviews

Completed plans and closed reviews. The full text of each is in [archive1.md](archive1.md); the
current open work is in the [master TODO](../../TODO.md).

The [UI redesign roadmap](archive1.md#ui--feature-redesign-roadmap) - five phases modelled on phpbb.com's Knowledge Base - is implemented and was archived on 10/05/2026. Its one open item, a prosilver-native style for upstream submission, was finished on 10/06/2026: the templates now follow the board style, and the extension's original look became an optional ACP setting (since replaced by the alternative stylesheet picker, see the [styling guide](styling/README.md)).

The last deferred finding from the [September 26 AI validation](archive1.md#ai-extension-validation---2026-09-26), the `kb_version` setting that no migration removes, was closed on 10/05/2026 as intentional: it is the version marker that 23 migrations read to skip steps a board already has, and the one the original-package upgrade relies on, so it stays. Each of those migrations now says so. The other finding, the N+1 queries in `migrations/version_1_0_2.php`, was fixed the same day.

The [phpbb.com KB parity gaps](archive1.md#phpbbcom-kb-parity-gaps) - five feature-completeness differences found by comparing screenshots with the original phpbb.com Knowledge Base - are all done and were archived on 10/05/2026.

Completed [database search validation](archive1.md#database-search-validation)
is recorded separately from the historical reviews.

The [multi-author, edit-conflict locking, moderator-author messaging, and related gaps](archive1.md#multi-author-edit-conflict-locking-moderator-author-messaging-and-related-gaps) plan is complete and was archived on 10/05/2026. One note stays open by choice there: the board-index category-card view shows only a one-article teaser per category.

The [Claude full codebase review of 09/30/2026](archive1.md#claude-full-codebase-review---2026-09-30) is archived: all 12 findings were resolved (pull request #17), and the checks that followed are in the [testing notes](../../testing.md).

The [styling design and tab shape](archive1.md#styling-design-and-tab-shape-completed-10132026) work is complete and was archived on 10/13/2026: Flat index, the alternative stylesheet picker with the bundled Slate sheet and custom files, the Classic tab shape and the board-coloured selected tab. The guide is [styling/README.md](styling/README.md).

The [September 26 AI validation](archive1.md#ai-extension-validation---2026-09-26) describes the pre-remediation snapshot. Its original findings are preserved, but the old Deny recommendation is not a current-tree verdict. Both of its deferred design suggestions were closed on 10/05/2026, as described above; archiving is not a fresh validation pass.
