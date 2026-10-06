# phpbb.com KB parity gaps

Source: a second-opinion comparison of the Phase 1-5 implementation
against detailed screenshots/analysis of the original phpbb.com
Knowledge Base (the same reference material behind
[`docs/ui-redesign-roadmap.md`](../ui-redesign-roadmap.md)). None of
these are bugs - they're feature-completeness differences from the
reference implementation, identified after the fact from more
complete screenshots than were available when Phases 1-5 were built.
Scoped here rather than implemented, pending a go-ahead.

## 1. Explicit "submit as" choice for moderators

**Done (09/28/2026).** Decision (asked in chat): just the binary
New-vs-Active toggle phpbb.com actually offers, not a broader status
picker. Implemented:

- `controller/posting.php::post_article()` - `$can_add_noapprove`
  (whether the submitter holds `kb_u_add_noapprove`) and the new
  `$submit_as_approved` (that permission AND the submitter's explicit
  choice, defaulting to `true`/checked so an unmodified form submission
  behaves exactly as before this existed) are both computed once,
  early, so the template can show the choice on a fresh GET load too -
  not only after a submit attempt. `approved`/`status` on the new
  article row, and the post-submit outcome (search indexing, the
  public comments topic, the success message, redirect target, and
  whether moderators get a needs-approval notification) all now follow
  `$submit_as_approved`, not the raw permission - a moderator who
  explicitly picks "Article requiring approval" gets exactly that
  outcome despite holding the permission to skip it.
- `kb_post_body.html` - a radio pair, shown only when
  `!S_EDIT_POST and $can_add_noapprove` (editing must never change an
  article's workflow status, and a submitter without the permission has
  no choice to make), matching phpbb.com's own wording: "Approved
  article" / "Article requiring approval".
- New `KB_SUBMIT_AS`/`KB_SUBMIT_AS_APPROVED`/`KB_SUBMIT_AS_PENDING`
  language keys in en/ru/fr/cs (`cs`/`fr` grounded in real phpBB
  approve/approved terminology from `phpbb-cz`/`phpbbdocs-languages`;
  `ru` has no local phpBB reference to check against and stays an
  unverified AI translation, same caveat as the other recent additions).

Verified live as `kb_moderator` (holds `kb_u_add_noapprove`): choosing
"Article requiring approval" correctly saved the article as
New/unapproved and it appeared in the Administration queue; leaving the
default "Approved article" selected still auto-published exactly as
before (a real regression check, not just reading the diff). Confirmed
`kb_author1` (no `kb_u_add_noapprove`) never sees the choice at all.

phpbb.com's submission form gave moderators (and only moderators) an
explicit choice at submit time: "Article requiring approval" or
"Approved article" - regardless of whether they hold
`kb_u_add_noapprove`. This extension's `controller/posting.php`
instead auto-publishes purely based on whether the submitter holds
`kb_u_add_noapprove` (`$can_add_noapprove` in `post_article()`), with
no UI choice offered.

Scope: a new radio/checkbox in `kb_post_body.html`, shown only when
the submitter already has `kb_u_add_noapprove` (a user without it has
no choice to make - their submission always needs approval), letting
them deliberately route their own submission through the approval
queue instead of auto-publishing. Needs a decision on whether this
should also let a moderator submit *as* another article's status
directly, or just toggle the initial New vs. Active outcome.

## 2. Required category / phpBB-version validation

phpbb.com marked both "Categories" and "For phpBB Version" as
"Please select at least one" - required fields. This extension treats
the primary category as required (implicit, since posting always
targets a specific category id) but never enforces that at least one
custom-field value (e.g. a phpBB-version tag) is selected, since
custom fields are a generic, optional-by-default system (Phase 3).

Scope: likely a new per-field `required` flag on `kb_fields` (ACP
toggle), validated in `posting.php` alongside the existing
`NO_TITLE`/`NO_TEXT`/`KB_REDIRECT_INVALID_URL` checks - not a
hardcoded "version is always required" rule, since the field system is
deliberately generic and other installs may not want every field
mandatory.

## 3. BBCode/emoticons restriction on the Description field

phpbb.com disabled BBCode and emoticons specifically on the
Description field (kept it plain-text), while the article body allows
full formatting. This extension currently allows full BBCode on both
fields - not an oversight, but also not a decision that was
specifically re-examined against this detail; the roadmap's constraint
3 was about *not* stripping the article-submission form down the way
phpbb.com's *redirect* form was stripped, and didn't separately address
the description field.

Scope: decide whether to restrict Description specifically (would need
its own BBCode-disabled rendering path in `posting.php`'s
`generate_text_for_storage()`/`generate_text_for_display()` calls,
since currently title/description/body are treated uniformly) or leave
it as a deliberate improvement over the original.

## 4. Richer Administration filtering

**Done (09/28/2026).** Added a `filter` GET param to
`approve.php::queue()`, narrowing the existing "needs attention" WHERE
clause additively: `redirect` (`is_redirect = 1`), `inactive`
(`status = STATUS_INACTIVE`), or `team_<id>` (`assigned_team_id =
<id>`, one option per configured KB team group). A `<select
onchange="this.form.submit()">` in `kb_queue_body.html`, matching the
existing field-filter pattern already used on the category listing
page (`kb_cat_body.html`), with a "Reset filter" link shown whenever a
filter is active. The pagination URL carries the active filter through
paging.

Verified live: the team filter correctly narrowed a 5-article queue
down to exactly the one article assigned to that team, and "Reset
filter" returned to the full unfiltered list.

**Follow-up fix (09/28/2026):** the filters only ever narrowed within
the base "needs attention" query - an already-published, auto-approved
redirect (Active, no pending revision) never entered the candidate set
in the first place, so "Redirects only" showed nothing for it even
though it matched. Added a "Show all articles, not just those needing
attention" checkbox (`show_all` param) that, when checked, drops the
needs-attention restriction entirely while keeping the selected filter
and the existing moderatable-category permission scoping - matching
what phpbb.com's "all articles + redirects" option implies. Off by
default, so the queue's ordinary behavior is unchanged. Verified live:
`filter=redirect` alone still correctly shows nothing for an
auto-published redirect; `filter=redirect&show_all=1` correctly shows
it with a working Deactivate action.

**Reworked (10/05/2026):** the filter plus "show all" checkbox became one
"Display options" dropdown with a Go button, matching phpbb.com's: "View new
and revisions (default)", "View all articles and redirects", "View
redirects", "View inactive articles", and for each team "View assigned to
<team>" and "Including active and redirects". Two behaviours changed with it:
the default view no longer lists inactive articles (they have their own
option, and an inactive article with a pending revision still shows by default),
and "View redirects" lists every redirect, including auto-published ones.
Old `?show_all=1` links still work. See the testing notes.

phpbb.com's Administration page had a "Display Options" filter
separate from the per-article action menu: all articles + redirects,
redirects only, inactive only, or scoped to one specific team. This
extension's `approve.php::queue()` is a single combined "needs
attention" listing (not `STATUS_ACTIVE` with no pending revision) with
no filter control.

Scope: a filter dropdown in `kb_queue_body.html` posting back to
`queue()` with a `filter` param, narrowing the existing WHERE clause
(status, `is_redirect`, or `assigned_team_id`) - additive to the
current query, not a rewrite.

## 5. "View article's topic" as a direct moderator action

**Done (09/28/2026).** Added a `U_TOPIC` link to both
`controller/approve.php` methods (`queue()`'s `art_row` block,
`approve_article()`'s main vars), reusing the exact
`append_sid(...viewtopic...)` construction `article.php` already uses
for `U_COMMENTS`, gated on the article having a non-zero `topic_id`.
Reuses phpBB core's own `VIEW_TOPIC` language key rather than adding a
new one - "View topic" already says the same thing "View article's
topic" would. Rendered in `kb_queue_body.html` (a `button2` link
alongside Assign/Reject/etc. per row) and `kb_approve_body.html` (a
standalone link above the status block, outside the action form since
it's a plain view action, not a moderation one).

Verified live end-to-end: submitted a real article through
`posting.php` (not the direct-SQL seed fixtures, which never create a
comments topic), confirmed its `topic_id` was set, and confirmed the
link on both the approve page and the queue correctly opens
`viewtopic.php?f=<kb_forum_id>&t=<topic_id>` with the right content.

phpbb.com's per-article Status dropdown (both in the Administration
list and on the article page itself) included a direct "View article's
topic" action. This extension already links to the associated topic
from the public article page (`U_COMMENTS` in `controller/article.php`,
via `topic_id`) when comments exist, but doesn't surface it as a
distinct action in the moderation queue or the single-article approve
page (`kb_approve_body.html`, `kb_queue_body.html`).

Scope: small - add a `U_TOPIC` link (reusing the same
`append_sid(...viewtopic...)` construction `article.php` already does)
to both templates, gated on the article having a `topic_id`.
