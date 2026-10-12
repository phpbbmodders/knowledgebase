# Knowledge Base Styling Discussion Handoff

Recorded 10/11/2026 for Claude or another contributor to continue if usage
limits interrupt the conversation.

## Implementation Status (10/12/2026)

William authorized implementation on 10/12/2026 with these decisions: the
Classic layout folds into Flat index and its colours become an option in the
new stylesheet picker; custom stylesheets from the board's `store/` folder are
in the first version; the test board needs no upgrade mapping, only testing on
every supported database; the tab shape and colours wait on Kailey's decision
(a day or two) and do not block the rest.

Done (migration `version_2_0_24`, version 2.0.24):

- Flat index (`kb_flat_index`, unchanged) now loads
  `phpbbmodders_knowledgebase_flat.css`, the Classic tab and table layout with
  no colours of its own.
- `kb_classic_look` and `phpbbmodders_knowledgebase_classic.css` are gone. The
  colours became the bundled **Slate** alternative,
  `styles/all/theme/alternatives/slate.css`, a commented colour-only example.
  "Slate" is a working name; it is one language key (`KB_ALT_STYLE_SLATE`).
- ACP: the **Use an alternative stylesheet** switch reveals a **Stylesheet**
  dropdown (Bundled and Custom groups). The choice is remembered while the
  switch is off; an unavailable choice is refused when switching on; a vanished
  file falls back to the board style with an ACP warning.
- Custom CSS from `store/phpbbmodders_knowledgebase/stylesheets/` is discovered
  and served by `controller/stylesheet.php` (strict names, no symbolic links,
  256 KiB limit, `text/css` with `nosniff`, ETag/304).
- The article tables now sit in prosilver's forum-header box and use its
  `bg1`/`bg2` row classes. Before, the native look had white header text on a
  white page, so the headings were invisible; they now follow each board style.
- Guide with screenshots: [README.md](README.md).

Not done, still open: the final tab shape, and whether the selected tab takes
the board's forum-header colours (the proposal below and
[docs/tabs](../tabs/README.md)); both wait on Kailey. The base sheet's
remaining fixed colours (status badges, the copy-button and code box, grey
borders) were reviewed: they are self-contained badges or neutral greys and
were left as they are.

## Authorization And Status

**Superseded by the implementation status above.** This was a discussion-only record when written.
Get an explicit instruction to implement before changing production CSS,
templates, controllers, migrations or board settings. No production styling
changes have been implemented. William subsequently authorized a temporary
browser-only tab comparison; its screenshots, preview CSS and comparison page
are in [docs/tabs](../tabs/README.md). That preview does not authorize the full
picker implementation. William subsequently authorized publishing these
documents and linking them from `docs/TODO.md`; production implementation
still requires a separate instruction.

The full proposal below incorporates William's clarification that this is a
private repository with only a test board, not external or production
installations. "Site admin" refers to the phpbbmodders extensions team leader.
William requested this proposal be recorded; that is not authorization to
implement it. New proposal details are distinguished from earlier agreements.

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

## Additional Layout Proposal

Keep hierarchical browsing as the default and remove the old Classic look
option. Selected tabs in either layout use the member's active board style's
forum-header colors, as illustrated by the temporary previews. Retain needed
KB layout rules, but remove fixed colors that interfere with board styling.
These color details are proposed behavior, not an implemented result.

## Test-Board Migration Proposal

The previous public-upgrade compatibility concern is superseded: there are no
unknown installations or production appearance settings to preserve.

- Add the new alternative-styling configuration, disabled by default.
- Remove the obsolete Classic look setting through a new migration.
- Leave the current Flat index setting unchanged.
- Do not automatically map the old Classic setting to a color selection.
- Preserve articles, permissions and other unrelated settings.

Do not rewrite applied historical migrations or reset the database merely to
apply the new configuration. Implementation still needs authorization.

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

### Custom Alternatives Proposal

For the initial version, administrators manually install CSS files. Do not add
an upload interface or browser editor. The proposed persistent location is
`store/phpbbmodders_knowledgebase/stylesheets/` under the board root so custom
files can survive extension replacement. That name is a design proposal, not
a phpBB convention.

- Automatically list valid CSS files directly inside the directory, clearly
  distinguishing custom choices from bundled choices in the ACP dropdown.
- Reject subdirectories, symlinks and invalid filenames.
- Serve selected custom CSS through a controlled extension endpoint with
  strict filename/path validation and a CSS content type.
- Initially support self-contained CSS without relative asset references or
  imports; asset handling requires a separate design.
- Remember the selected file while alternative styling is disabled.
- If the selected file disappears, fall back to board styling and show an
  ACP warning rather than silently selecting a different alternative.

`store/` is protected from direct web access. Do not weaken server protections
or assume `INCLUDECSS` can serve arbitrary stored files. Bundled Classic
colors do not require this endpoint. Discovery, serving and fallback are now
part of the full proposal, but are not implemented or authorized for coding.

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
colors, and a small override affecting one area only. Include prosilver,
Revolution and ProMinoDeux, with mobile examples. Identify the board style and
KB settings in every example. Link to the commented CSS. Use actual browser screenshots
of implemented behavior, not mockups presented as verification.

Revolution checkout: `/home/william/Desktop/repos/prosilver_se_revolution`.
Its `style.cfg` declares `parent = prosilver`; its stylesheet uses red forum
headers and dark-gray outer page backgrounds. This contrast is useful for
finding forced KB colors.

## Eventual Verification

After implementation authorization, test Flat index on/off independently of
styling disabled, bundled Classic colors and a partial custom override.
Test prosilver, Revolution and the already-used
ProMinoDeux style; desktop/tablet/phone; long labels, deep breadcrumbs,
empty/populated categories, tables, search results and articles. Check selected
tabs, hover, keyboard focus, contrast, wrapping, overflow and overlap. Check
ordinary forum pages for leaked CSS. Record both verified and untested cases.

Verify ACP selection persistence while disabled, custom-file discovery,
missing-file fallback and warnings, and the custom CSS endpoint's serving
protections, including invalid filenames, traversal and symlink rejection.
Verify the migration leaves Flat index and unrelated data unchanged.

A live preview was optional discussion, not agreed implementation scope.

Keep this handoff and `docs/TODO.md` current with approved scope,
implementation status and remaining work. Preserve the existing comparison
under `docs/tabs/`; future guide screenshots belong in `docs/images/styling/`.

## Existing Work And Preservation

Disposable board: `/home/william/Desktop/repos/seeded-board/kb`.
URL: `http://localhost:8092/knowledgebase`.
Its `ext/phpbbmodders/knowledgebase` is mounted from the source checkout.
Do not delete unrelated `files/` content or local config.

Naming, author-PM preferences and comments/changelog testing are complete and
merged separately in [PR #45](https://github.com/phpbbmodders/knowledgebase/pull/45).
Do not redo those changes or count their checks as styling verification.
Related evidence: `docs/author-pm-and-forum-testing.md` and
`docs/testing-methodology-for-claude.md`. Verify current Git and board state
before resuming; an old report or configured URL is not current runtime proof.
