# phpBB 4.0.x Compatibility Notes — Knowledge Base Extension

Tracks what's known about this extension's readiness for phpBB 4.0.x,
alongside its current phpBB 3.3.x support. Declared support and the bug list
were last checked against the code on 10/12/2026 (version 2.0.24).

## Current declared support

From `composer.json`:

- `require.phpbb/phpbb`: `>=3.3.19 <4.1.0`
- `extra.soft-require.phpbb/phpbb`: `>=3.3.19,<4.1.0@dev` — phpBB 3.3.x and 4.0.x,
  including 4.0 development builds. (Until 10/12/2026 both said `<4.0.0`.)
- `require.php`: `>=8.2` (also enforced when the extension is enabled, by `ext.php`; it matches phpBB
  4.0's own PHP floor and the version the CI job tests)

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

## Checked against phpBB master (4.0.0-a3-dev) - 10/12/2026

The notes above were written before 4.0 was readable. On 10/12/2026 the extension was checked
against upstream `phpbb/phpbb` `master` (`a57d60d`, 4.0.0-a3-dev, PHP `^8.2`, Symfony 7.4,
Twig 3.14) in two ways: a static comparison with 3.3.x (`e81e644`), and a smoke test on a real
4.0.0-a3-dev board (a local checkout at `ccb3ac2`, three weeks older than that master, with the
version cap lifted in a throwaway copy of the extension).

**Still fine.** All six `core.*` events exist with identical variable lists (the viewonline one
moved to `phpbb/members/controller/online.php`). The three template events exist in prosilver and
`INCLUDECSS` still accepts an expression. All 49 core functions, 20 constants and 38 classes the
extension uses still exist; the only public methods removed from those classes are
`template::clear_cache`, the Flash filters and the viewflash setter, none of which it calls.
The notification method base is unchanged. `check_is_img()` works (both the old constants and
`\phpbb\attachment\attachment_category` exist), and `download_mode`, dropped from the extension
groups table, is read with `g.*` and a default. The templates use only `if`, `for` and the
`INCLUDE*` tags, which Twig 3 accepts. On the 4.0 board the extension enabled (all 33 migrations
ran, 17 tables), and every front-end page loaded (index, categories, all articles, my articles,
search and results, article, print view, posting form, UCP notification pages), every ACP module
rendered, and an article submitted through the posting form was saved without error.

**Found, and fixed the same day** (working on both 3.3.x and 4.0):

1. **Notification avatars (was a fatal error on 4.0).** `get_avatar(): string` in
   `notification/type/approve.php`, `disapprove.php` and `need_approval.php` returned the avatar as
   an HTML string, but on 4.0 `user_loader::get_avatar()` returns an array of avatar data that the
   base class passes to its avatar helper, so all three types threw a `TypeError` ("Return value
   must be of type string, array returned"), reproduced on the 4.0 board. The types now return
   what the loader gives, without a string return type, and a group author gets an empty value of
   the right kind (`[]` on 4.0, `''` on 3.3.x) from the new `notification/type/avatar_compat.php`
   trait. The 4.0 base class is recognised by the `avatar_helper` property it gained, not by a
   version number, so a 4.0 development build behaves like the release. On the 4.0 board all
   three types, including the group-author case, now build their display data.
2. **Upload directory.** `controller/posting.php` took the plupload directories from
   `config['upload_path']`, which 4.0 removes (migration `storage_attachment` moves it to
   `storage\attachment\config\path`). `functions_kb::get_upload_path()` now reads either setting,
   falling back to `files`. Real uploads on the 4.0 board, a small image and a chunked 8.6 MB file,
   succeeded with the old code as well: the extension sets its own directories earlier in the
   request, so the stale line only reset them afterwards. It was a latent inconsistency rather
   than a break, and is now correct.
3. **Image size limits.** `textformatter/article_text.php` reads `max_post_img_width` and
   `max_post_img_height`, which 4.0 removes with Flash and image links; a missing value now counts
   as no limit. `enable_bbcode('flash')` stays; it does nothing once the BBCode is gone.

**Two more 4.0 problems, found by looking at it running** (a static comparison could not show them):

4. **Icons were blank.** phpBB 4.0 moved to Font Awesome 6 and its own icon markup; an
   `<i class="icon fa-pencil fa-fw">` element, which is all 3.3.x needs, is drawn empty because it has no
   style class (`fas`, `far`) and 4.0's `o-icon o-icon-font` classes. Every Knowledge Base button
   (the move arrows, approve, delete, edit, print, search, the caret, the menu icon, the ACP icons)
   showed as an empty box. Outline icons also changed name (`file-o` became `far fa-file`). All icon
   markup now goes through one helper, `inc/icon.php`, which returns the class list for the running
   version (4.0 is recognised by the `Icon()` Twig function it adds), exposed to the templates as
   `KbIconClass('pencil', 'icon-blue')` through a tagged Twig extension. 3.3.x output is the same
   classes as before. A check of every visible icon on every page found all 178 drawn on 4.0 and all
   391 on 3.3.x.
5. **The article page failed with a 500.** The article text renderer service calls
   `configure_user(user, config, auth)`; 4.0 declares it as `configure_user(auth, config, user)`, a
   `TypeError`. 4.0 also adds `configure_mention_helper`. The service now uses
   `textformatter/renderer.php`, a subclass whose `configure_user()` takes the three in any order and
   passes them on in the order the running phpBB declares (read from the parent's parameter types),
   and the mention helper is requested optionally so the call is simply left out on 3.3.x.

6. **Uploaded file size (an old bug, on every version).** Testing a chunked upload on 4.0 showed the
   attachment's recorded size was the size of the last chunk (611,392 bytes for a 9,000,000-byte file),
   because `controller/posting.php` stored `$_FILES['fileupload']['size']` instead of the merged file's
   size. It now stores `$upload_file->get('filesize')`. A single-request upload (70 bytes) and the
   3.3.x test board give the same result as before.

**Declared support.** The PHP floor was raised from 8.0 to 8.2 on 10/12/2026. The extension uses
nothing specific to 8.1 or 8.2 (it linted clean and passed its unit tests on PHP 8.0.30), so this is
a policy choice, not a technical need: phpBB 4.0 requires PHP 8.2 itself, and the CI job that runs
uses 8.2 (the matrix lists 8.2 to 8.5, but with the database jobs switched off only the primary job
runs; 8.4 and 8.5 were run locally), so the stated floor is what CI tests. A 3.3.x board on PHP 8.0 or 8.1
can no longer enable this extension. The phpBB constraints were then widened to `<4.1.0`, so
the extension can be enabled on 4.0.x; it was enabled on a 4.0.0-a3-dev board with the constraints
as shipped. 4.0 is still a development release, so "supported" here means "checked as below", not a
promise about later 4.0 builds.

**Checked later the same day** (the points first left open):

- **Current master.** A fresh tree built from `a57d60d` itself (the master that was read, 4.0.0-a3-dev), installed
  through the real `seed-forum` reset: the installer enabled the extension with the constraints as shipped,
  all 33 migrations ran, and every front-end page and icon behaved as on the older checkout.
- **Review flow.** Submit as an author, assign to a team, review, approve: each step works on 4.0 and the
  author gets the approval notification and a private message.
- **Email.** With the board pointed at a local SMTP sink, the pending-article email reached the moderator
  and the approval email reached the author, through 4.0's queued sending (the queue runs on cron, about
  a minute apart). One wording slip turned up: the pending-article email said "at the conference"; it now says
  "on" the board.
- **Extensions manager.** The ACP list shows the extension as enabled; its details page shows version 2.0.24,
  `>=3.3.19,<4.1.0@dev` for phpBB and `>=8.2` for PHP. Disable keeps the data, enable restores it, purge
  removes the tables, settings, modules and notification types, and enabling again installs cleanly.

**Still not checked.** 4.0's extension catalogue and Composer-based installation of extensions from the
ACP (it needs network access to a catalogue), and later 4.0 builds. The 4.0 board also had other extensions
installed; one of them (`tas2580/wiki`) breaks the UCP notification settings page on 4.0 because it overrides
the notification base constructor, which gained two arguments. The Knowledge Base types do not override it
and are unaffected. No new core event is needed for any of this.

## Reference material

On the maintainer's machine, under `/home/william/Desktop/repos/misc/docs/`:

- `Claude/phpbb-core-architecture-deep-dive.md`: phpBB 3.3.x core internals.
- `Claude/phpbb-cli-console-commands.md` and `phpbb-cli-user-reference.md`:
  the CLI.
- `Claude/phpbb-extension-coding-guidelines.md` and
  `Claude/phpbb-extension-conventions.md`: extension coding guidelines and the
  conventions seen across real extensions.
