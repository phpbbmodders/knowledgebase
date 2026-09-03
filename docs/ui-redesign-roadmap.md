# UI / Feature Redesign Roadmap

## Reference

Modeled on phpbb.com's own Knowledge Base
(`https://www.phpbb.com/support/docs/en/3.3/kb/`). Its public pages
use a docs-site table/sidebar layout (flat category-dropdown filter,
title+description+views+author/date article-index table, a compact
article metadata sidebar) rather than this extension's current
forum-post-styled UI (categories as subforums, articles as topics, an
article page styled like a single forum post).

The logged-in Support/Documentation Team views (View Article Index,
Reading Article, Manage Your Articles, Submit an Article, Submit a
Redirect, Administration) also showed a materially richer feature set
than this extension currently has:

- A status workflow (New → Reviewed → Active / Inactive / Revision)
  replacing the current simple `approved` boolean
- Assigning articles to a named team (Support/Styles/MOD-style) for
  moderation routing
- Multi-category tagging (an article can belong to several categories,
  not exactly one)
- phpBB-version tagging (which phpBB version(s) an article applies to)
- "Redirect" entries — a second, bodyless content type that just
  points at a URL

## Implementation constraints

1. **Team assignment reuses phpBB usergroups**, not a parallel "teams"
   concept. Which groups count as KB teams is chosen via checkboxes in
   this extension's own ACP settings screen, following the same
   checkbox-list UI pattern the file-extension-groups setting already
   uses (`acp_controller.php::settings()` /
   `adm/style/acp_knowledgebase_config.html`).
2. **The taggable phpBB-version list is admin-configurable**, built as
   a new generic custom-field system modeled on (not literally
   reusing) phpBB core's own Custom Profile Fields pattern: pluggable,
   namespaced field entries (a prefix so other field types can be
   added later without key collisions), each individually
   enable/disable-able via ACP. phpBB-version tagging is the first
   field type built on this, with a sensible default entry (e.g.
   3.3.x) rather than phpbb.com's hardcoded fixed checkbox list — the
   mechanism itself is generic, not version-specific, so it can carry
   other tag types later.
3. **Every article-submission form must support the full regular
   posting-page feature set** (BBCode, smilies, URLs, etc., including
   but not limited to BBCode) — not a stripped-down form like
   phpbb.com's redirect-submission page (BBCode/emoticons disabled).
   Applies to the existing posting form and to the new Redirect entry
   form in Phase 4.

## Phases

Built one at a time, with a checkpoint before starting the next.

1. **Status workflow + team assignment** — New → Reviewed → Active /
   Inactive / Revision, replacing the current `approved` boolean.
   Touches the existing `kb_m_approve` permission,
   `controller/approve.php`, and the
   `need_approval`/`approve`/`disapprove` notification types. Team
   assignment: phpBB usergroups, selectable via ACP checkboxes as
   described above. Highest risk (permissions + notifications), so
   goes first.
2. **Multi-category tagging** — an article belongs to several
   categories at once instead of exactly one. Needs a join table and
   touches everywhere article-category is currently assumed 1:1
   (listing, breadcrumbs, per-category permissions).
3. **phpBB-version tagging** — the first field type on the CPF-inspired
   generic custom-field system described above (admin-configurable,
   namespaced/prefixed entries, individually toggle-able, ships with
   one default version entry rather than a hardcoded list), tagged per
   article, shown on the article page and filterable.
4. **Redirect entries** — a second content type: title/description/
   categories/version tags but no body, just a URL redirect. Reuses
   most of the article plumbing (`is_redirect` flag + `redirect_url`
   column) and must carry the full posting-feature set per constraint
   3 above.
5. **The restyle pass** — table-style article index, compact article
   metadata sidebar, prominent category-dropdown filter — once the
   data model from phases 1-4 is final.

Detailed per-phase implementation design (schema, exact state
transitions, ACP UI specifics) happens as its own scoping pass
immediately before that phase starts, not all up front.

## Phase 1 design notes (in progress)

Decisions settled so far, ahead of writing the migration/code:

- **Author is polymorphic: a user or a group.** `author_id` stops
  meaning "always a phpBB user" and can reference either a user or a
  group (needs a discriminator alongside the id — e.g. an
  `author_type` column). This replaces the author identity outright
  (not a separate display-only field) — matches phpbb.com's "Written
  By: phpBB Team" pattern, generalized to real group entities instead
  of a special-cased string.
- **Group-authored "own article" rights**: any member of the author
  group gets the same `kb_u_edit`/`kb_u_delete` "own article" rights
  an individual author would have — the group is treated as
  collectively owning the article.
- **Group-authored notifications**: every current member of the author
  group receives the approve/disapprove notification, resolved the
  same way `find_users_for_notification()` already resolves
  `kb_m_approve` permission holders via group membership. Each member
  can still individually opt out through their own UCP notification
  settings — this falls out of the existing
  `check_user_notification_options()` filtering already used by all
  three notification types, so no new opt-out mechanism is needed.
- **Approval-on-submit gating unchanged**: whether an article needs
  approval when submitted still depends on `kb_u_add_noapprove`,
  checked the same way (via `acl_kb_get()`, which already resolves
  through the acting user's own group memberships) regardless of
  whether the article's author is set to a user or a group.
- **Reapproval on edit is existing behavior, not new**: `posting.php`
  already recomputes `approved` from the *editing user's own*
  `kb_u_add_noapprove` permission on every submit (add or edit) — an
  editor without that permission already sends an article back to
  pending today. This carries over to group-authored articles with no
  new mechanism, since the check is already per-editor, not
  per-article-author.

Still open: the exact New/Reviewed/Active/Inactive/Revision state
transitions (what moves an article between states, and who's allowed
to trigger each transition), and the ACP checkbox UI specifics for
picking team usergroups.
