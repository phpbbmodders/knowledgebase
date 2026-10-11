# Knowledge Base Styling Discussion Handoff

Recorded 10/11/2026 for Claude or another contributor to continue if usage
limits interrupt the conversation.

## Authorization And Status

**Discussion-only.** William requested this document, not implementation.
Get an explicit instruction to implement before changing production CSS,
templates, controllers, migrations or board settings. No production styling
changes have been implemented. William subsequently authorized a temporary
browser-only tab comparison; its screenshots, preview CSS and comparison page
are in [docs/tabs](../tabs/README.md). That preview does not authorize the full
picker implementation. William subsequently authorized publishing these
documents and linking them from `docs/TODO.md`; production implementation
still requires a separate instruction.

## Agreed Design

- **Flat index** keeps its current browsing behavior: an all-articles index
  and category dropdown instead of hierarchical subcategory browsing. Move
  Classic's alternative tab/table layout into this option, without its colors.
- Keep a separate, board-wide **alternative styling** checkbox in ACP
  Configuration. It is unchecked by default. Checking it reveals a stylesheet
  dropdown, following existing reveal-more-options behavior on that page.
  The selector resembles the UCP board-style selector, but is not in UCP.
- Ship Classic's current **color scheme** as a dropdown choice and an example
  of KB-specific CSS overrides. Its colors apply independently of Flat index.
- Checkbox off uses each member's active board style, not just the board's
  global default style. Necessary KB layout rules remain; unrelated fixed
  colors should not override the active board palette.
- Alternatives can adjust selected areas only: tabs, headers, rows or borders.
  They must not change browsing behavior, permissions or functionality.
- Remember the selected stylesheet while alternative styling is disabled.
  Exact new ACP labels have not been finalized.

Earlier proposals to remove the picker, or ship Classic layout and colors
together as one alternative, were superseded. Do not implement those versions.

## Upgrade Decision Still Needed

Preserving an existing Classic-enabled board's color selection was discussed.
Preserving existing Flat index settings instead of silently enabling flat
browsing was also proposed. With the final layout/color split, an existing
Classic-on/Flat-off board cannot retain every old visual detail without
changing its browsing behavior. Explain that tradeoff and confirm upgrade
behavior before writing a migration. Do not silently enable Flat index,
rewrite applied historical migrations, or reset a database to upgrade.

## Commented CSS Example

William explicitly requested well-commented CSS. Classic colors should double
as a readable example:

- Header describing purpose, load order, color-only scope and partial overrides.
- Sections for tabs, listing/table headers, rows, borders and interaction states.
- Identify KB selector hooks and explain what each group affects.
- Explain non-obvious specificity and any genuinely necessary `!important`.
- Explain how to copy only selected sections into a custom alternative.
- Explain reasoning rather than narrating obvious declarations.

Layout rules belong with Flat index, not the color-only example. Scope
overrides to KB elements so ordinary forum pages remain unaffected.

## Current Source

Checkout: `/home/william/Desktop/repos/knowledgebase`.

Templates already combine board classes with KB hooks: `tabs kb-tabs`,
`forabg kb-listing`, `forumbg kb-listing`, and `table1 kb-index-table`.
The current Classic sheet mixes layout and colors; it is not already
color-only.

Relevant files:

- `styles/all/theme/phpbbmodders_knowledgebase.css`: base CSS, including some
  hard-coded colors and broad selectors. Change only what this work requires.
- `styles/all/theme/phpbbmodders_knowledgebase_classic.css`: current alternatives.
- `styles/all/template/event/overall_header_stylesheets_after.html`: loads base
  CSS on KB pages, then Classic conditionally through `S_KB_CLASSIC_LOOK`.
- `styles/all/template/kb_nav_tabs.html`, `kb_cat_list.html`, and
  `kb_search_results.html`: native classes plus KB hooks.
- `controller/acp_controller.php` and
  `adm/style/acp_knowledgebase_config.html`: existing ACP options.
- `event/listener.php`: current Classic template flag.
- `controller/index.php` and `controller/category.php`: Flat index behavior.
- `migrations/version_2_0_21.php`: existing Classic config migration.

Correction to an earlier explanation: the base CSS white `!important`
background belongs to `.kb_codebox`, not the whole KB page. Inspect actual
computed styles instead of attributing every white surface to that one rule.

## Stylesheet Locations

Bundled alternatives belong in the extension's `styles/all/theme/`, loaded
through phpBB's normal `INCLUDECSS` mechanism. Style-specific support can use
the corresponding extension `styles/<style>/theme/` directory and inheritance.

Admin-added alternatives were discussed, but upload/editor functionality and
automatic discovery are not approved or fully designed. The proposed
persistent location is `store/phpbbmodders_knowledgebase/stylesheets/` under
the board root. That exact name is a design proposal, not a phpBB convention.

`store/` is protected from direct web access. CSS there needs a controlled
serving mechanism, strict filename/path validation, CSS content type and
deliberate handling of relative asset URLs. Do not weaken server protections
or assume `INCLUDECSS` can serve arbitrary stored files. Confirm this scope
before adding it; bundled Classic colors do not require such an endpoint.

References checked in the discussion:

- [phpBB 3.3 CSS and template conventions](https://area51.phpbb.com/docs/dev/3.3.x/extensions/tutorial_key_concepts.html).
- [Extension-owned persistent files](https://area51.phpbb.com/docs/dev/master/extensions/tutorial_advanced.html):
  master documentation describes user content in `files`, `images`, `store`;
  verify target-branch details before implementation.
- [Installation security guidance](https://area51.phpbb.com/phpBB/docs/INSTALL.html):
  direct access to `store/` should be blocked.

## Illustrated Documentation

William requested a dedicated documentation subdirectory with screenshots.
Use `docs/styling/` for the guide and `docs/images/styling/` for screenshots,
following the repository's canonical documentation-image location.

Illustrate hierarchical versus Flat index layouts, native versus Classic
colors, and a small override affecting one area only. Include prosilver and
Revolution, with mobile examples. Identify the board style and KB settings
in every example. Link to the commented CSS. Use actual browser screenshots
of implemented behavior, not mockups presented as verification.

Revolution checkout: `/home/william/Desktop/repos/prosilver_se_revolution`.
Its `style.cfg` declares `parent = prosilver`; its stylesheet uses red forum
headers and dark-gray outer page backgrounds. This contrast is useful for
finding forced KB colors. Preserve existing notes/screenshots in `docs/v/`.

## Eventual Verification

After implementation authorization, test Flat index on/off independently of
alternative styling on/off. Test prosilver, Revolution and the already-used
ProMinoDeux style; desktop/tablet/phone; long labels, deep breadcrumbs,
empty/populated categories, tables, search results and articles. Check selected
tabs, hover, keyboard focus, contrast, wrapping, overflow and overlap. Check
ordinary forum pages for leaked CSS. Record both verified and untested cases.

An ACP warning/fallback for missing alternatives was suggested, not finalized.
A live preview was optional discussion, not agreed implementation scope.

## Existing Work And Preservation

Disposable board: `/home/william/Desktop/repos/seeded-board/kb`.
URL: `http://localhost:8092/knowledgebase`.
Its `ext/phpbbmodders/knowledgebase` is mounted from the source checkout.
Do not delete unrelated `files/` content, untracked `docs/v/`, or local config.

Naming, author-PM preferences and comments/changelog testing are complete and
merged separately in [PR #45](https://github.com/phpbbmodders/knowledgebase/pull/45).
Do not redo those changes or count their checks as styling verification.
Related evidence: `docs/author-pm-and-forum-testing.md` and
`docs/testing-methodology-for-claude.md`. Verify current Git and board state
before resuming; an old report or configured URL is not current runtime proof.
