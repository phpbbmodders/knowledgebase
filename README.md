# Knowledge Base

A wiki/FAQ system for phpBB: categories and articles with attachments, an approval workflow, a search index, and its own permission system, all managed from the ACP.

## Installation

1. Copy the extension to: `/ext/phpbbmodders/knowledgebase`
2. In the Administration Control Panel, navigate to: **Customise → Manage extensions**
3. Enable the **Knowledge Base** extension
4. Configure it under **Extensions → Knowledge Base**

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
