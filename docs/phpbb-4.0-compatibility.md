# phpBB 4.0.x Compatibility Notes — Knowledge Base Extension

Tracks what's known about this extension's readiness for phpBB 4.0.x,
alongside its current phpBB 3.3.x support. Declared support and the bug list
were last checked against the code on 10/12/2026 (version 2.0.24).

## Current declared support

From `composer.json`:

- `require.phpbb/phpbb`: `>=3.3.19 <4.0.0`
- `extra.soft-require.phpbb/phpbb`: `>=3.3.19,<4.0.0@dev` — phpBB 4.0 and its
  dev builds are explicitly excluded today.
- `require.php`: `>=8.0`

phpBB 4.0.x is still in development, so specifics below are marked
verified vs. pending as they're confirmed against real code/changelog
evidence — nothing here is guessed.

## What this extension does

A knowledge base for a phpBB board: articles organised in categories, with
attachments, Markdown or BBCode text, revisions, an approval workflow with
review teams, and a search index, plus an ACP area to manage all of it. It has
its own custom permission system (separate database tables, not phpBB's core
ACL), can optionally announce new articles in a forum and keep a private
changelog topic per article, and can recolour itself with an optional
stylesheet (see the [styling guide](styling/README.md)).

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

**Concrete 4.0 signal found (not a guess):** `check_is_img()` in
`inc/functions_kb.php` already branches on
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

### Bugs found along the way — all fixed

These turned up during the audit and were not version-related. Each was
checked against the current code on 10/12/2026.

| # | Problem | Status |
|---|---|---|
| 1 | The `orphan`, `extra_files` and `lost_files` modes in `acp/attachments_info.php` were gated on another extension (`phpbbmodders/garage`), so those three ACP pages were unreachable for nearly everyone. | Fixed: all five modes name `ext_phpbbmodders/knowledgebase`. |
| 2 | `acl_kb_get()` in `inc/functions_kb.php` hardcoded `category_id = 1` and read `auth_option_id` instead of `auth_setting` in its per-user override check, so per-user overrides only worked in category 1. | Fixed in `68aea42`. |
| 3 | Two leftover pre-Twig snippets rendered as literal text (`<!-- IF SHOW_PANEL -->` in the posting template, `<!-- BEGIN category -->` in the ACP permissions template). | Fixed in `d2d120f` and, for the posting template, by the template rewrite in `5690db6`. None remain. |
| 4 | `migrations/version_1_0_3.php::update_schema()` had two `'drop_columns'` keys, so the second silently replaced the first and `kb_src_wrdmtch.reply_id` was never dropped. | Fixed in `9f103fc`: one key drops both columns. |
| 5 | `need_approval::is_available()` read a key that does not exist in `user->data` (`author_id`); the notification types used `global $table_prefix` instead of injected table names; the template-event files sat in a prosilver-only folder. | Fixed in `cb8345c` and `32a8ba9`; the events now live in `styles/all/template/event/`, which is the extension's only style folder. |
| 6 | `controller/posting.php` compared the category-move ids with `!==` in one place and `!=` a few lines later. Harmless while both are ints, fragile otherwise. | Fixed in `c53137a`: both comparisons are strict. |

## Reference material

On the maintainer's machine, under `/home/william/Desktop/repos/misc/docs/`:

- `Claude/phpbb-core-architecture-deep-dive.md`: phpBB 3.3.x core internals.
- `Claude/phpbb-cli-console-commands.md` and `phpbb-cli-user-reference.md`:
  the CLI.
- `Claude/phpbb-extension-coding-guidelines.md` and
  `Claude/phpbb-extension-conventions.md`: extension coding guidelines and the
  conventions seen across real extensions.
