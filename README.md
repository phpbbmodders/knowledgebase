# Knowledge Base

A knowledge base for phpBB: articles organised in categories, with attachments, Markdown or BBCode text, revisions, an approval workflow, search, and its own permission system, all managed from the ACP.

## Screenshots

<table>
<tr>
<td align="center"><a href="docs/images/screenshots/01-flat-index.png"><img src="docs/images/screenshots/01-flat-index.png" width="440" alt="The flat article index"></a><br>The flat article index (the default)</td>
<td align="center"><a href="docs/images/screenshots/08-category-tree.png"><img src="docs/images/screenshots/08-category-tree.png" width="440" alt="The category tree"></a><br>Hierarchical category browsing</td>
</tr>
<tr>
<td align="center"><a href="docs/images/screenshots/02-article.png"><img src="docs/images/screenshots/02-article.png" width="440" alt="An article"></a><br>An article</td>
<td align="center"><a href="docs/images/screenshots/05-approval-queue.png"><img src="docs/images/screenshots/05-approval-queue.png" width="440" alt="The approval queue"></a><br>The approval queue</td>
</tr>
</table>

More screenshots, including the article form, search results and the ACP settings: [docs/images/screenshots](docs/images/screenshots/README.md).

## Installation

1. Copy the extension to: `/ext/phpbbmodders/knowledgebase`
2. In the Administration Control Panel, navigate to: **Customise → Manage extensions**
3. Enable the **Knowledge Base** extension
4. Configure it under **Extensions → Knowledge Base**

The Knowledge Base follows each member's board style. It starts as a flat article
index (one list of every article, with a category dropdown); hierarchical category
browsing and an optional colour stylesheet (a bundled one, or your own CSS file) are
set on the same ACP page; see the [styling guide](docs/TODO/archive/styling/README.md).

Upgrading from the original `sheer/knowledgebase` extension? See
[Upgrading from the original](docs/upgrading-from-original.md): your data carries
over.

Attachments are stored under `ext/phpbbmodders/knowledgebase/files/`,
protected by that directory's `.htaccess`. **This only works on
Apache** - nginx ignores `.htaccess` entirely. If you're running
nginx, add an explicit deny rule for that path in your server config,
e.g.:

```nginx
location ~ ^/ext/phpbbmodders/knowledgebase/files/ {
	deny all;
	return 403;
}
```

## Automated testing

See [Testing](docs/testing.md) for local regression tests and disposable
MySQL/PostgreSQL fixtures. The current GitHub workflow runs coding and
extension validation checks, not these regression tests.

[![Tests](https://github.com/phpbbmodders/knowledgebase/actions/workflows/tests.yml/badge.svg)](https://github.com/phpbbmodders/knowledgebase/actions/workflows/tests.yml)

## TODO

Ideas not yet built, practical and speculative alike: [`docs/TODO.md`](docs/TODO.md).

## Acknowledgments

Based on the original [knowledgebase](https://github.com/sutrus/knowledgebase) extension by sutrus (Zdenek Kamenik), itself building on earlier work by Sheer's [Knowledge Base](https://github.com/AlexSheer/phpbb3.2-Knowledge-Base) extension.

- Code review, bug fixes, and documentation assisted by [Claude](https://www.anthropic.com/claude).
- Code review, bug fixes, and documentation assisted by [Codex](https://openai.com/codex/).

## License

Licensed under the [GNU General Public License v2](license.txt)
