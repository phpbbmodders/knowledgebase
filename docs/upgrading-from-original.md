# Upgrading from the original sheer/knowledgebase extension

This extension can take over a board that runs the original
`sheer/knowledgebase` package (version 2.0.x; the 2.0.1-RC6 release it was
imported from is the one tested). Your categories, articles, permissions,
settings and attachments carry over. Nothing is converted by hand.

Only MySQL and MariaDB boards can have come from the original: its own
database setup fails on PostgreSQL and SQLite, so those cannot be upgraded
from it. Boards on the original's older 1.0.x line were not tested.

## Steps

1. Back up the database and the folder `ext/sheer/knowledgebase/files/`.
2. In the Administration Control Panel go to **Customise → Manage extensions**
   and **Disable** `Knowledge Base` by sheer. Do not use *Delete data*: that
   removes the tables this extension will keep using. This extension refuses to
   enable while the original is still enabled and says so.
3. Copy this extension to `ext/phpbbmodders/knowledgebase`.
4. **Enable** it. Its migrations notice the original's data, skip the steps the
   original already did, add what is new, and then convert the original's
   leftovers (below).
5. Open **Extensions → Knowledge Base** and check your articles, categories and
   settings.
6. Once everything looks right you can delete `ext/sheer/knowledgebase`.

## What is converted

- The ACP modules the original registered, so the Knowledge Base menu points at
  this extension.
- The search backend setting, which the original stored with its own class name.
- The notification types and each user's notification settings for them. The
  types are switched on again, because the original switched them off when it was
  disabled.
- The uploaded files in `ext/sheer/knowledgebase/files/`, which are copied into
  `ext/phpbbmodders/knowledgebase/files/` when the old folder is still there.
  Files already present are not overwritten, and the originals are left alone. If
  a file cannot be copied, enabling stops before anything in the database is
  changed; fix the folder's permissions and enable again. If you have already
  deleted the old folder, copy its `files/` contents across yourself before
  enabling.

The disabled `sheer/knowledgebase` entry in the extensions list, its migration
records and its old log lines are harmless and are left as they are.

## If something goes wrong

Restore the database backup from step 1. Enabling stops and reports an error
rather than carrying on. When a migration fails partway, phpBB reverts it, and a
revert can drop tables that migration manages, so a backup is the only way to put
the board back exactly as it was.
