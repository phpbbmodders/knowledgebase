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
