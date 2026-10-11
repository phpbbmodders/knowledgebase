# Author PMs, Comments And Changelog

## Behavior

The English navigation and page title use **Knowledge Base**.

Approval and rejection private messages default on in UCP notification
preferences. Members can disable either independently. An explicit saved
opt-out remains effective; missing preferences use the default. The former
ACP forced-delivery option is removed. Migration `version_2_0_23` removes its
obsolete configuration key without changing user notification preferences.
The moderator's existing option to skip messaging remains unchanged.

The custom PM method supports approval and rejection only. Other notification
types do not gain PM delivery. Styling, tabs and the classic-look option are
unchanged.

## Verification On 10/11/2026

Codex tested the disposable SQLite board at `http://localhost:8092` with
visible Chromium and the seeded author, reader and moderator accounts.
Twelve workflow checks passed:

- Navigation and heading use Knowledge Base.
- Both author PM choices are checked with no previously saved PM preference.
- Default-on approval delivers an actual PM visible in the author's inbox.
- The reader opens the generated public comments thread.
- The reader posts a comment visible to the author.
- The author replies and the reader sees that reply.
- The moderator reads the generated private submission changelog.
- The reader is denied the changelog forum, topic and reply form.
- The moderator appends a private forum reply visible in that topic.
- Saved UCP opt-outs persist and stop both approval and rejection PMs.
- Re-enabling the preference delivers an actual rejection PM.
- An author edit automatically appends a revision entry and body diff to
  the same changelog topic.

The live ACP configuration form renders without the former author-PM control.
Disable/re-enable applied the migration without purging article data; the
migration record exists and the obsolete config key is absent.

PHPUnit: 277 tests, 647 assertions, 16 skipped database-dependent tests.
Changed PHP files pass syntax checks and the diff passes whitespace checks.
The added unit tests cover default PM availability and the migration's
configuration-only update. No MySQL/PostgreSQL compatibility claim is made
for this run.

## Inspecting The Examples

On this local board, article 267 is **Comments and changelog verified
walkthrough**. Its comments topic is 18 and its changelog topic is 17:

- Article: `http://localhost:8092/knowledgebase/article?k=267`
- Public comments: `http://localhost:8092/viewtopic.php?t=18`
- Private changelog: `http://localhost:8092/viewtopic.php?t=17`

The comments contain a reader question and author reply. The changelog contains
the generated submission entry, a moderator reply, and a generated revision
diff. Log in as the seeded moderator to inspect the private thread. The
fixture grants changelog access to KB Team and administrators, not every
review team; this run proves the ordinary reader is denied, not every possible
group combination.

Approval reasons are delivered to the author; they are not automatically
appended to the changelog. That behavior was observed, not changed.

Screenshots and JSON results are in the local workspace's `kb-browser/`
directory. The exploratory browser scripts are
`/tmp/playwright-test-kb-comments-pm.js` and
`/tmp/playwright-test-kb-changelog-revision.js`. They write disposable-board
content, so do not run them against production. Topic/article IDs are local
examples, not stable identifiers after a reset.

Browser harness corrections included native forum Submit naming, the required
assignment/review sequence, phpBB's generic denial message container, and a
read-only retry for a redirect navigation race. These were not application
fixes. Existing unrelated configuration and generated files were preserved.
