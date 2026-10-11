# Tab Shape Comparison

Temporary visual comparison captured on 10/11/2026. Open
[comparison.html](comparison.html) for the six side-by-side desktop examples.
Each example also links to a full-page screenshot and a mobile screenshot.
Screenshots are stored in this directory at William's explicit request.

## What Changed For The Preview

The real disposable Knowledgebase board rendered each of prosilver,
prosilver Special Edition - Revolution, and ProMinoDeux. Each was captured
with native tab geometry and the current Classic tab geometry, but without
Classic's fixed slate palette. All five moderator navigation tabs are present.

For this comparison, the selected tab uses the active board's forum-header
background and visible header-text color. Inactive tabs use the board's
navigation background and link color. Both tab shapes use that same palette,
so the shape comparison is independent of color. These are provisional
forum-header-colored previews, not screenshots of each style's untouched
native tab colors. No final color behavior has been implemented.

`preview.css` is the commented, temporary override. The browser suppresses the
existing combined Classic stylesheet for these captures only, then applies
this override with computed colors sampled from the active board. Source
extension CSS, the Classic checkbox and Flat index settings are unchanged.
The layout/color separation and stylesheet picker remain future work.

Revolution and ProMinoDeux were copied from the local repositories and
installed on the disposable board with the existing ACP-based style installer.
The default style and saved user style choices were not changed. These two
styles remain installed for further comparison; preview CSS is not applied
to ordinary board visits.

## Files And Evidence

- `*-tabs.png`: the six desktop heading/tab detail screenshots.
- `*-desktop.png`: full pages at 1440 x 1000 viewport size.
- `*-mobile.png`: full pages at 390 x 844 viewport size.
- `results.json`: sampled colors, overflow checks, asset failures and images.
- `comparison.html`: local, self-contained image comparison page.

The exploratory capture script is
`/tmp/playwright-test-kb-tab-comparison.js`. It logs into the seeded moderator
account, selects styles per request with `?style=<id>`, and uses browser-only
CSS overrides. It does not save board settings or submit KB content. Capture
styles have IDs 1, 2 and 3 on this board; do not assume those IDs elsewhere.

The first pass's foreground-color sampling targeted the header container
rather than the visible label. That harness mistake was corrected and all
captures were regenerated. Session data is kept outside the repository.

This is a visual decision aid, not comprehensive styling regression coverage.
Publishing these preview documents does not implement a styling feature. See
[the styling handoff](../styling/discussion-handoff.md) for the agreed broader
design and unresolved upgrade behavior.
