# Styling the Knowledge Base

The Knowledge Base takes its colours from the board style each member uses.
Two independent ACP settings change how it looks on top of that. Both are on
the configuration page, **Extensions > Knowledge Base > Configuration**.

| Setting | What it changes | Default |
|---|---|---|
| **Flat article index** | Layout and behaviour: one dropdown of every category instead of the category tree, every article in one list, and a compact layout for the article tables. It sets no colours. | off |
| **Use an alternative stylesheet** | Colours only: recolours the tabs, the header bars and the article tables over the board style. The **Stylesheet** dropdown that appears chooses which one. The choice is remembered while the setting is off. | off |

The two combine freely. Neither changes who can see or do what.

The navigation tabs are not a setting: they always have the same compact,
wrapping shape, and the selected tab is painted with the board style's own
header colours (blue in prosilver, red in Revolution, dark in ProMinoDeux).

All the screenshots below are real captures of the test board, on three board
styles: prosilver, prosilver Special Edition - Revolution (red headers, dark
outer page) and ProMinoDeux (a dark header and blue buttons). Each shows a
category page as a Knowledge Base administrator, with the tab layout described above.

## Board colours (nothing switched on)

The tabs, header bars and rows are the board style's own, so each style shows
its own palette; the selected tab uses the same colours as the header bars.

| prosilver | Revolution | ProMinoDeux |
|---|---|---|
| ![prosilver, hierarchical, board colours](../../../images/styling/prosilver-hierarchical-board.png) | ![Revolution, hierarchical, board colours](../../../images/styling/revolution-hierarchical-board.png) | ![ProMinoDeux, hierarchical, board colours](../../../images/styling/prominodeux-hierarchical-board.png) |

## Flat article index

Same colours, different layout: the category tree is replaced by a dropdown
and every article is listed. The table rows get a thin neutral divider. (The
tabs look the same as in the hierarchical layout.)

| prosilver | Revolution | ProMinoDeux |
|---|---|---|
| ![prosilver, flat, board colours](../../../images/styling/prosilver-flat-board.png) | ![Revolution, flat, board colours](../../../images/styling/revolution-flat-board.png) | ![ProMinoDeux, flat, board colours](../../../images/styling/prominodeux-flat-board.png) |

## The bundled Slate stylesheet

Slate is the muted blue-grey look of the original Knowledge Base. It works
with the flat index on or off, on any board style.

| | prosilver | Revolution | ProMinoDeux |
|---|---|---|---|
| Hierarchical | ![prosilver, hierarchical, Slate](../../../images/styling/prosilver-hierarchical-slate.png) | ![Revolution, hierarchical, Slate](../../../images/styling/revolution-hierarchical-slate.png) | ![ProMinoDeux, hierarchical, Slate](../../../images/styling/prominodeux-hierarchical-slate.png) |
| Flat | ![prosilver, flat, Slate](../../../images/styling/prosilver-flat-slate.png) | ![Revolution, flat, Slate](../../../images/styling/revolution-flat-slate.png) | ![ProMinoDeux, flat, Slate](../../../images/styling/prominodeux-flat-slate.png) |

On a phone the same pages stack and the tabs wrap; there is no sideways
scrolling in any combination tested.

| prosilver, flat, Slate | Revolution, hierarchical, board colours | ProMinoDeux, flat, Slate |
|---|---|---|
| ![prosilver on a phone](../../../images/styling/prosilver-flat-slate-mobile.png) | ![Revolution on a phone](../../../images/styling/revolution-hierarchical-board-mobile.png) | ![ProMinoDeux on a phone](../../../images/styling/prominodeux-flat-slate-mobile.png) |

## Your own stylesheet

A custom alternative is a plain `.css` file you put on the board. It appears
in the same dropdown under **Custom**.

1. Copy the CSS file to `store/phpbbmodders_knowledgebase/stylesheets/` in the
   board's root folder (create the folders if they are missing). The name may
   use letters, digits, dots, dashes and underscores, must end in `.css` and
   can be at most 64 characters, including `.css`. The file can be at most 256 KiB.
2. In the ACP tick **Use an alternative stylesheet**, choose the file under
   **Custom** and submit.

It loads after the board style's stylesheets and the extension's own, so a rule
with the same specificity wins. It changes only what it names. This example
recolours the selected tab and nothing else (shown on all three styles):

| prosilver | Revolution | ProMinoDeux |
|---|---|---|
| ![prosilver, custom selected-tab sheet](../../../images/styling/prosilver-hierarchical-custom.png) | ![Revolution, custom selected-tab sheet](../../../images/styling/revolution-hierarchical-custom.png) | ![ProMinoDeux, custom selected-tab sheet](../../../images/styling/prominodeux-hierarchical-custom.png) |

The file is [`examples/selected-tab.css.example`](examples/selected-tab.css.example);
save it as `selected-tab.css` to use it. For a fuller starting point copy
sections from the bundled Slate sheet,
[`styles/all/theme/alternatives/slate.css`](../../../../styles/all/theme/alternatives/slate.css).
It is written as the worked example: its header explains the palette and the
load order, and each of its four sections (tabs, header bars, category lists,
article tables) stands on its own. The shape of the tabs is in
[`phpbbmodders_knowledgebase.css`](../../../../styles/all/theme/phpbbmodders_knowledgebase.css),
and the layout rules of the flat index live separately in
[`phpbbmodders_knowledgebase_flat.css`](../../../../styles/all/theme/phpbbmodders_knowledgebase_flat.css),
so a colour sheet never needs to touch them.

### A board style with light header colours

The selected tab is painted with the board style's header colours and its text
is white, which suits prosilver, Revolution and ProMinoDeux. On a style whose
headers are pale, white text would be hard to read. A one-rule custom
stylesheet fixes the text for that style and leaves the header colour alone:

```css
.tabs.kb-tabs .kb-tab-active > a,
.tabs.kb-tabs .kb-tab-active > a:hover {
	color: #333;
}
```

Save it in the stylesheets folder (see above), for example as
`light-header-tabs.css`, and choose it under **Custom**. It loads after the
extension's own sheet, so it wins on equal specificity. Checked on the test board:
the tab text turns dark grey and the tab keeps the style's header colour.

### The hooks to select

Every selector should start from a `kb-*` class the extension's templates add
next to the board style's own, so a custom sheet cannot reach ordinary forum
pages.

| Hook | Where |
|---|---|
| `.tabs.kb-tabs`, `.kb-tab`, `.kb-tab-active` | The navigation tab strip, every tab, the page you are on |
| `.forabg.kb-listing` | The category list |
| `.forumbg.kb-listing` | The search results and the article tables' header box |
| `table.table1.kb-index-table` | The article tables (category page, Manage Your Articles, the review queue) |
| `tr.bg1`, `tr.bg2` | The alternating rows inside those tables (the board style's own stripe classes) |

### What happens when something goes wrong

- If the chosen custom file is removed or becomes unusable (too large, renamed
  to something not allowed, replaced by a link), the Knowledge Base shows the
  board style's own colours and the ACP page shows a warning naming the file.
  It never switches to a different stylesheet on its own.
- Turning the setting off keeps your choice, so turning it on again restores it.
- The ACP refuses to switch the setting on while the chosen file is not
  available.

### How a custom file is served (for administrators)

`store/` is not reachable by URL, so the extension serves the chosen file from
`/knowledgebase/stylesheet?f=<name>`. Only a regular file with an allowed name
directly inside that one folder is ever sent; symbolic links, sub-folders,
names containing `..` or a slash, and oversized files are answered with a
plain 404, and the response is marked `text/css` with `nosniff`. Treat the
files as trusted: CSS from an administrator can load images and fonts from any
address and restyle any Knowledge Base page.
