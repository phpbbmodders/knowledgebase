# Forum search integration

Status: direction confirmed 10/12/2026, not started. Applies to the Knowledge Base and, in its own
repository, to the Wiki (`phpbbmodders/wiki`, `docs/TODO/todo-forum-search-integration.md`).

## Goal

A member who searches the board with phpBB's normal search also finds Knowledge Base articles,
without leaving that page. The extension keeps its own search engine and index; the board search
only shows its results beside the forum results ("option 1": grouped results, not one mixed list).

## What a member sees

- The normal post results are unchanged. Above them, after a keyword search, a block reads
  "Knowledge Base (12)" with the top few matching articles (title, category, a snippet with the
  word highlighted, link) and a "View all 12" link to the existing Knowledge Base results page.
- The Wiki adds its own block in the same place ("Wiki (3)").
- A block appears only when it has matches. A user who may not read any matching article sees no
  block and no count, and an extension that is disabled adds nothing.
- Advanced search gets an "Also search the Knowledge Base" checkbox, ticked by default; unticking
  it hides the block for that search.
- Only keyword searches show a block. "New posts", "Unanswered topics", "Your posts" and
  author-only searches are about posts and show none.

## How

- A listener on the core search events (identical on phpBB 3.3.x and 4.0): after the core keyword
  search, read the same keywords from the request and ask this extension's own engine for the top N
  matches and the total. Hand the result to a block in the template event
  `search_results_header_after`; add the checkbox in `search_body_search_options_append`.
- Permissions come from the extension's own checks (the per-category read rights and the article
  status rules the engine already applies), so nothing a member cannot read is counted or shown.
- No content is copied into phpBB's forum tables and no core file changes. If an extension point
  turns out to be missing, write the request up under `docs/events/` instead of patching core.

## Work

1. Listener, block template, checkbox, language strings, accessible markup (a labelled region and a
   heading), and an ACP setting for how many articles the block shows (default 5) and for switching
   the block off.
2. Cheap query: only the top N and the count, no snippet work for results that are not shown;
   consider short-lived caching per keyword and user group.
3. Tests: block shown and hidden, permission filtering (a restricted category never appears in the
   list or the count), the checkbox, non-keyword searches, an extension with search switched off,
   core pagination of the post results untouched.
4. Verify in a browser on 3.3.x and 4.0 with SQLite, MySQL and PostgreSQL; record what was and was
   not covered in `docs/testing.md`.

## Open decisions

- Whether this extension's own search page and ACP engine settings stay as an advanced
  Knowledge-Base-only search (planned) or are retired later.
- The Wiki gets a search first: the Knowledge Base's search is ported to it (see the Wiki's own
  plan). Decide there whether to copy the engine or move it into one shared package so a fix lands
  in both.

## Not in scope

One mixed, paginated list of posts and articles (would need a hidden "shadow" topic per article
and an exact copy of the article permissions into a forum; rejected for the risk of leaking
restricted text), Sphinx, and changing how phpBB indexes posts.
