# phpBB 4.0.x Compatibility Notes — Knowledge Base Extension

Tracks what's known about this extension's readiness for phpBB 4.0.x,
alongside its current phpBB 3.3.x support.

## Current declared support

From `composer.json`:

- `require.phpbb/phpbb`: `>=3.3.19`
- `extra.soft-require.phpbb/phpbb`: `>=3.3.19,<4.0.0@dev` — phpBB 4.0 dev
  builds are explicitly excluded today.
- `require.php`: `>=8.0`

phpBB 4.0.x is still in development, so specifics below are marked
verified vs. pending as they're confirmed against real code/changelog
evidence — nothing here is guessed.

## What this extension does

A wiki/FAQ system ("Knowledge Base") bolted onto a phpBB board:
categories and articles with their own attachments, an approval
workflow, and a search index, plus an ACP area to manage all of it.
It has its own custom permission system (separate database tables,
not phpBB's core ACL) and can optionally auto-post a forum topic when
an article is published.

## Audit summary (completed)

A file-by-file audit of DIC config, controllers, event listeners, ACP
modules, notifications, search backend, templates, and migrations was
checked against known phpBB 3.3.x internals/idioms, and against
whatever concrete 4.0 signal exists in the codebase itself.

**3.3.x today:** sound overall. The extension is built on genuinely
correct modern 3.3.x extension idioms throughout — DI-based
controllers, tagged service collections for search backends and
notifications, proper migration dependency chains, correct event
usage, Twig-native templates. It is not a "technically compiles but
doesn't integrate with phpBB" extension.

**4.0.x:** phpBB 4.0 is not public yet, so nothing below asserts
specifics that aren't backed by evidence in this codebase. The
riskiest surface is the parts that lean directly on core internals
rather than documented extension points:

- `event/listener.php` — six `core.*` event hooks
  (`core.user_setup`, `core.page_header`,
  `core.viewonline_overwrite_location`, `core.permissions`,
  `core.display_forums_modify_template_vars`,
  `core.viewforum_modify_topicrow`). Event names/signatures are
  exactly what a major version bump tends to change; no way to
  confirm without 4.0's (not yet public) event docs.
- ACP module registration/auth-string mechanism (`acp/*_info.php`,
  `acp/*_module.php`).
- Anywhere core global constants are referenced directly, e.g.
  `ATTACHMENT_CATEGORY_IMAGE`.

**Concrete 4.0 signal found (not a guess):** `inc/functions_kb.php:830-837`
(`check_is_img()`) already branches on
`phpbb_version_compare($this->config['version'], '4.0.0', '<')`,
using the legacy `ATTACHMENT_CATEGORY_IMAGE` constant on 3.3.x but a
namespaced `\phpbb\attachment\attachment_category::IMAGE` class on
4.0+. There's also a `// phpbb 4.x` comment next to a `download_mode`
field read from `EXTENSION_GROUPS_TABLE`. This is the maintainer's own
forward-looking code — real evidence that phpBB 4.0 replaces at least
the `ATTACHMENT_CATEGORY_*` global constants with a namespaced
class/enum in the attachments subsystem. It's a single, narrow
accommodation — the rest of the codebase (other search backends, the
event listener, ACP modules) has no equivalent guard and would need
the same kind of audit once 4.0's real changelog is public.

**Lowest-risk surface for 4.0:** the DI/service-collection-based
plumbing (controllers, search backends, notification types) and the
Twig templates — these lean on recent (3.2+) core investments that
4.0 is more likely to build on than replace outright.

### Bugs found along the way (not version-related, worth fixing regardless)

1. `acp/attachments_info.php` (lines 30, 35, 40) — the `orphan`,
   `extra_files`, and `lost_files` ACP modes are gated on
   `'auth' => 'ext_phpbbmodders/garage && acl_a_board'` — `phpbbmodders/garage` is a
   different, unrelated extension. This makes those three ACP pages
   permanently inaccessible for anyone without that unrelated
   extension installed (almost everyone). Should be
   `ext_phpbbmodders/knowledgebase`.
2. `acl_kb_get()` in `inc/functions_kb.php` — hardcoded
   `category_id = 1` and reads the wrong SQL field
   (`auth_option_id` instead of `auth_setting`) in its per-user
   permission-override check, making the custom per-user override
   feature effectively non-functional outside category 1.
3. Two leftover pre-Twig legacy template snippets render as literal
   broken text since Twig doesn't parse them:
   `styles/prosilver/template/kb_post_body.html:84`
   (`<!-- IF SHOW_PANEL -->...<!-- ENDIF -->`) and
   `adm/style/acp_knowledgebase_permissions.html:229`
   (`<!-- BEGIN category -->`).
4. `migrations/version_1_0_3.php::update_schema()` (lines 38-47) —
   the returned array has two `'drop_columns'` keys; the second
   silently overwrites the first in PHP, so
   `kb_src_wrdmtch.reply_id` is never actually dropped (only
   `kb_articles.bbcode_bitfield` is). Harmless (orphaned unused
   column survives) but not what the code intends.
5. Minor: `notification/type/need_approval.php:108`'s
   `is_available()` checks `$this->user->data['author_id']`, a key
   that doesn't exist in `user->data` (likely meant `user_id`) —
   makes the UCP toggle for this notification type unreliable, but
   doesn't affect actual delivery. The three notification types'
   `check_permission()` methods use `global $table_prefix;` instead
   of the DI pattern used everywhere else in this codebase. The three
   template-event files under `styles/prosilver/template/event/`
   only fire for prosilver-based styles — moving them to
   `styles/all/template/event/` would make them style-independent at
   no cost.

### Real bug found in the controller layer

`controller/posting.php` — the same category-move check appears twice
a few lines apart with inconsistent comparison operators:
`$cat_id !== $to_id` (strict, line 375) vs. `$cat_id != $to_id`
(loose, line 386). Both values come from
`request->variable(..., 0)` so both are always `int` today, making
this harmless in practice — but fragile if that ever changes.

## Reference material

- 3.3.x core-architecture deep dive, CLI dev guide, and CLI user
  reference: `/home/william/Desktop/phpbb33_docs/repos/docs/Claude/`
  and `/home/william/Desktop/phpbb33_docs/repos/docs/`
- Extension coding-guidelines/conventions docs (see
  `reference_phpbb_extension_conventions_doc` memory)
