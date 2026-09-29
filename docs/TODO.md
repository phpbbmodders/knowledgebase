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
  approval" choice for a moderator submitting a new article, a filter
  dropdown on the Administration queue (redirects/inactive/one team,
  plus a "show all" toggle so the filter can reach already-published
  articles too, not just ones needing attention), and a direct "View
  topic" moderator action on the queue and approve pages. Remaining:
  required-field validation on categories/custom fields, and a BBCode
  restriction on the Description field.
- [Multi-author, edit-conflict locking, moderator-author messaging, and related gaps](TODO/todo-phpbb-com-kb-authorship-and-revisions.md) -
  substantial new-feature clusters found by comparing phpbb.com's real
  Knowledge Base notes against this extension. All seven
  items are done as of 09/25/2026: multiple credited authors (users and
  team groups, not just one - moderator-only to set, via a schema
  change plus a rewrite of every ownership check in the extension); a
  real moderator-only per-article changelog topic (separate forum,
  posted to on submission/edit/revision, configurable in the ACP);
  optimistic locking on the pending-revision edit race; author
  messaging on approve/deny now sends a real PM through phpBB's own
  notification-method framework (a genuine UCP toggle alongside
  Notification/Email/Jabber) as well as the existing bell, with a
  reason-or-explicit-skip requirement; and three smaller confirmed gaps
  - no category-to-team fallback for unassigned articles, a hidden
  article being distinguishable from a nonexistent one by response
  type, and a non-atomic view-count increment. Co-authors now show on
  the article page and the category listing, both linked and with
  groups in their colour, and the category listing's per-row author/
  co-author lookups are now batched into one query each per page load
  instead of one pair per row. One small thing remains intentionally
  undone - see the file's "Still not done, on purpose" note under item
  1: "Your articles" was never showing an author column at all before
  this feature existed, so it wasn't given one now either (its
  underlying query was still fixed to include co-authored articles).
