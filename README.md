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
<td align="center"><a href="docs/images/screenshots/09-board-search-kb-block.png"><img src="docs/images/screenshots/09-board-search-kb-block.png" width="440" alt="Knowledge Base articles beside the forum posts in a board search"></a><br>Knowledge Base block in the board search</td>
</tr>
</table>

More screenshots, including the article form, the approval queue and the ACP settings: [docs/images/screenshots](docs/images/screenshots/README.md).

## Installation

1. Copy the extension to: `/ext/phpbbmodders/knowledgebase`
2. In the Administration Control Panel, navigate to: **Customise → Manage extensions**
3. Enable the **Knowledge Base** extension
4. Configure it under **Extensions → Knowledge Base**

The Knowledge Base follows each member's board style. It starts as a flat article
index (one list of every article, with a category dropdown); hierarchical category
browsing and an optional colour stylesheet (a bundled one, or your own CSS file) are
set on the same ACP page; see the [styling guide](docs/TODO/archive/styling/README.md).

A search of the board also shows the matching articles in a **Knowledge Base** block above
the forum results, with an **Also search the Knowledge Base** option in the advanced search.
Switch the block off, or set how many articles it lists, under **Extensions > Knowledge Base >
Search**.

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

## Common problems

### "The “files” and “plupload” folders could not be made writable automatically"

A red message at the top of **Extensions > Knowledge Base > Configuration**. The extension keeps attachments, and uploads that are still in progress, in `ext/phpbbmodders/knowledgebase/files/` and `files/plupload/`. The web server's user has to be able to write there. The extension tries to open the folders up itself, and cannot when another user owns them or when they are missing, for example after the extension was copied in with a tool that leaves out or removes the `files` folder.

Give the folders to the user your web server or PHP runs as (`www-data` on Debian and Ubuntu, `apache` on Fedora and CentOS, `nginx` on some nginx setups) from the board's root folder:

```bash
cd ext/phpbbmodders/knowledgebase
mkdir -p files/plupload
sudo chown -R www-data:www-data files
sudo chmod -R 775 files
```

Without shell access, create `files` and `files/plupload` with your FTP program and set both to 775 (777 only if your host allows nothing else). Reload the Configuration page: the message goes away as soon as the folders can be written. Keep the access protection for the `files` folder described above in place (the `.htaccess` file on Apache, the `deny` rule on nginx).

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
