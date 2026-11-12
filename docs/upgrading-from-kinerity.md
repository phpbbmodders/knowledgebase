# Moving from the kinerity/knowledgebase extension

This extension can import a board's knowledge base from the
`kinerity/knowledgebase` extension (also published as Crizz0's
`knowledgebase` fork; version 1.2.1 is the one tested, and 1.0.x and 1.1.x
boards are handled too). Your categories, articles and permissions come
across when you enable this extension. Nothing has to be converted by hand.

Both extensions use database tables called `kb_articles` and `kb_categories`,
with different columns. When this extension is enabled, it first copies
kinerity's tables to backup tables, `kb_kinerity_articles`,
`kb_kinerity_categories` and `kb_kinerity_article_category`, and removes the
originals. Then it creates its own tables and imports into them. The backup
tables are kept, unchanged.

## Steps

1. Back up the database.
2. In the Administration Control Panel go to **Customise → Manage extensions**
   and **Disable** `Knowledge Base` (kinerity). Do not use *Delete data*: that
   removes kinerity's tables, and with them everything there is to import. This
   extension refuses to enable while kinerity is still enabled and says so.
3. Copy this extension to `ext/phpbbmodders/knowledgebase`.
4. **Enable** it. The import runs while it is being enabled.
   - On a large knowledge base, use the command line. It has no page time
     limit and shows each step as it runs:

     ```bash
     php bin/phpbbcli.php extension:enable phpbbmodders/knowledgebase
     ```

   - In the Administration Control Panel, phpBB enables an extension in a
     single page load, so a large import can run past the server's time limit
     and end on a time-out or error page. Nothing is lost: the categories and
     articles are imported in batches, and each finished batch is kept. Go to
     **General → Purge the cache**, then enable the extension again; it carries
     on where it stopped. Repeat until phpBB says the extension was enabled.
     (Purging the cache matters: without it phpBB can stop with a "duplicate
     key" error on its list of extensions.)

   Either way, an import that stops part way carries on where it stopped the
   next time the extension is enabled, and imports nothing twice.
5. Go to **Extensions → Knowledge Base → Search** and create the search index,
   so the imported articles can be found by the Knowledge Base search.
6. Check your categories, articles and permissions under
   **Extensions → Knowledge Base**.
7. Delete the `ext/kinerity/knowledgebase` folder. Don't enable kinerity again
   on this board: its pages would read this extension's tables.

## What is imported

- **Categories**, with their order and nesting. A category's description
  becomes plain text, because this extension's category details have no
  BBCode, and is shortened to 255 characters if it is longer.
- **Articles**, with their title, text (BBCode, smilies and links as they
  were), author, dates and view count. Descriptions longer than 255 characters
  are shortened, ending in "…".
  - Approved articles are published.
  - Articles waiting for approval go to the approval queue.
  - Articles kinerity marked as deleted or denied are kept, deactivated, so a
    moderator can reactivate or delete them.
  - An article that was in several categories goes in the one kinerity created
    first (the lowest category id) and is cross-listed in the others.
  - Articles kinerity filed in no category, or only in a category that no
    longer exists, go in a new top-level category called
    "Imported articles without a category".
- **Permissions.** kinerity's permissions applied to the whole knowledge base;
  this extension's apply per category. Each group's and user's kinerity
  setting (Yes or Never, set directly or through a role) is given to them on
  every imported category:

  | kinerity permission | This extension's permission |
  | --- | --- |
  | `u_kb_read` | Can view Knowledge Base (`u_kb_view`, board-wide) |
  | `u_kb_post` | Can add articles |
  | `u_kb_noapprove` | Can add / modify articles without prior approval |
  | `u_kb_edit` | Can edit own articles |
  | `u_kb_delete` | Can delete own articles |
  | `m_kb_edit` | Can edit articles |
  | `m_kb_delete` | Can delete articles |
  | `m_kb_approve` | Can approve articles |
  | `m_kb_chgposter` | Can change the primary author of submitted articles |
  | `a_kb_manage` | Can manage Knowledge Base (`a_manage_kb`, board-wide) |

  A group or user that already has a setting for `u_kb_view` or `a_manage_kb`
  keeps it.

- **The introduction text** kinerity showed above its index. This extension's
  own introduction (Extensions → Knowledge Base → Configuration) starts out
  with kinerity's text and is switched on after an import, so visitors still
  see it. Edit it or switch it off there.

## What is removed

After the import, kinerity's permissions (with their settings in roles,
groups and users), its ACP modules, its three settings (the on/off switch, the
header link and its icon, which this extension has no equivalent of), its
notification types with their notifications and users' settings for them,
and its entry in the extensions list are removed.

kinerity's migration records are kept on purpose. Without them, enabling
kinerity again would run its first migration, which fails on this extension's
tables, and phpBB would then drop those tables while undoing it.

## Not imported

- Who last edited an article (kinerity's "edited by" name). The edit date is
  kept.
- A category's article count above 65,535, the most that column holds; the
  articles themselves are all imported.
- kinerity's per-article BBCode, smilies and links switches: the stored text
  already shows them the way kinerity did.
- Old links. Articles and categories get new ids, so links to kinerity's pages
  (`/kb/viewarticle?a=…` and the like) in posts or search engines don't lead to
  the imported articles. The backup tables record each article's old and new
  id (`article_id` and `kb_new_id`).

## The backup tables

The `kb_kinerity_*` tables hold kinerity's data as it was, plus the id each
row was given here. Purging this extension leaves them in place, and enabling
it again imports them again. Once you are happy with the result you can drop
them with your database tool, for example:

```sql
DROP TABLE phpbb_kb_kinerity_articles;
DROP TABLE phpbb_kb_kinerity_categories;
DROP TABLE phpbb_kb_kinerity_article_category;
```

(Use your board's table prefix in place of `phpbb_`.)

## If something goes wrong

Restore the database backup from step 1. Enabling stops and reports an error
rather than carrying on. kinerity's tables are only removed once every row has
been copied to the backup tables and the copies counted.
