#!/usr/bin/env python3
"""Prepare and restore author-change fixtures on a seeded SQLite board."""

import json
import pathlib
import sqlite3
import sys

root = pathlib.Path(sys.argv[1]).resolve()
operation = sys.argv[2]
if "/seeded-board/kb-author-change-" not in str(root):
    raise SystemExit("Not a disposable author-change fixture board")
if not (root / ".seed-forum-seeds.json").is_file():
    raise SystemExit("Board must be built through seed-forum")
db = sqlite3.connect(root / "store/sqlite.db")
db.row_factory = sqlite3.Row
saved = root / "store/author-change-test-snapshot.json"


def rows(sql, parameters=()):
    """Read rows as dictionaries for deterministic preservation checks."""
    return [dict(row) for row in db.execute(sql, parameters)]


def snapshot():
    """Read the article, its revision, co-authors, and audit-entry count."""
    return {
        "article": rows("SELECT * FROM phpbb_kb_articles WHERE article_id=2")[
            0
        ],
        "revisions": rows(
            "SELECT * FROM phpbb_kb_article_revisions WHERE article_id=2"
        ),
        "coauthors": rows(
            (
                "SELECT * FROM phpbb_kb_article_co_authors WHERE article_id=2"
                " ORDER BY author_type,author_ref_id"
            )
        ),
        "logs": db.execute(
            (
                "SELECT COUNT(*) FROM phpbb_kb_log WHERE log_operation='LOG_L"
                "IBRARY_CHANGED_AUTHOR'"
            )
        ).fetchone()[0],
    }


if operation == "prepare":
    if saved.exists():
        raise SystemExit(
            "Restore the previous fixture run before preparing again"
        )
    original = snapshot()
    moderator = db.execute(
        "SELECT user_id FROM phpbb_users "
        "WHERE username='kb_transfer_moderator'"
    ).fetchone()
    target = db.execute(
        "SELECT user_id FROM phpbb_users WHERE username='kb_author2'"
    ).fetchone()[0]
    teams = [row["author_ref_id"] for row in original["coauthors"]
             if row["author_type"] == 1]
    if not moderator or not original["revisions"] or not teams:
        raise SystemExit("Build the board with the updated KB seed script")
    grant = db.execute(
        "SELECT u.auth_setting FROM phpbb_kb_users u "
        "JOIN phpbb_kb_options o ON o.auth_option_id=u.auth_option_id "
        "WHERE u.user_id=? AND u.category_id=? "
        "AND o.auth_option='kb_m_change_author'",
        (moderator[0], original["article"]["article_category_id"]),
    ).fetchone()
    if not grant or grant[0] != 1:
        raise SystemExit("The seed must grant scoped author-transfer access")
    saved.write_text(json.dumps(original), encoding="utf-8")
    result = original.copy()
    result.update(target_user=target, team_id=teams[0])
elif operation == "snapshot":
    result = snapshot()
elif operation == "restore":
    original = json.loads(saved.read_text(encoding="utf-8"))
    with db:
        article = original["article"]
        db.execute(
            (
                "UPDATE phpbb_kb_articles SET author_id=?,author_type=?,autho"
                "r=? WHERE article_id=2"
            ),
            (article["author_id"], article["author_type"], article["author"]),
        )
        db.execute(
            "DELETE FROM phpbb_kb_article_co_authors WHERE article_id=2"
        )
        for row in original["coauthors"]:
            db.execute(
                "INSERT INTO phpbb_kb_article_co_authors VALUES (?,?,?)",
                tuple(row.values()),
            )
    with db:
        db.execute("DELETE FROM phpbb_kb_article_revisions WHERE article_id=2")
        for revision in original["revisions"]:
            columns = ",".join(revision)
            placeholders = ",".join("?" for _ in revision)
            db.execute(
                f"INSERT INTO phpbb_kb_article_revisions ({columns}) "
                f"VALUES ({placeholders})",
                tuple(revision.values()),
            )
    saved.unlink()
    result = {"restored": True}
else:
    raise SystemExit("Use prepare, snapshot, or restore")
print(json.dumps(result))
