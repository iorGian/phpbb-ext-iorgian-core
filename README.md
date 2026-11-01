# Iorgian Core (iorgian/core)

Shared base library for the `iorgian` extensions for phpBB 3.3.x (4.0 ready).

It adds no visible features by itself: it provides reusable services so that every `iorgian` extension stays small and consistent. Used by [Iorgian Multi Prefixes](https://github.com/iorGian/phpbb-ext-iorgian-multiprefixes).

> **Alpha release.** This extension is under active development and may contain errors. It is not recommended for use on a live forum. Test it on a local or staging installation first and make a database backup before enabling it.

## Requirements

- phpBB 3.3.0 or newer
- PHP 7.4 or newer

## Installation

1. Extract the release archive inside your forum's `ext/` directory (it already contains `iorgian/core/`), or copy this folder to `ext/iorgian/core`.
2. ACP > Customise > Manage extensions: enable **Iorgian Core**.
3. Install it **before** any extension that depends on it.

When upgrading, replace the folder, purge the cache (ACP > General > Purge the cache) and, if the new version includes migrations, disable and re-enable the extension (do not delete its data).

## Services

| Service | Class | Purpose |
|---|---|---|
| `iorgian.core.compat` | `iorgian\core\service\compat` | Isolate phpBB 3.3 / 4.0 differences |
| `iorgian.core.acl` | `iorgian\core\permission\acl_helper` | Local forum ACL and group membership |
| `iorgian.core.colors` | `iorgian\core\util\colors` | Color validation and contrast |
| `iorgian.core.color_field` | `iorgian\core\util\color_field` | ACP color field (selector + hex text + default button) |
| `iorgian.core.badge` | `iorgian\core\util\badge` | Shared colored badge renderer |
| `iorgian.core.forums` | `iorgian\core\util\forums` | Uncached forum tree and select options |
| `iorgian.core.groups` | `iorgian\core\util\groups` | User group names |
| `iorgian.core.ordering` | `iorgian\core\util\ordering` | Move up / down ordering helper |
| `iorgian.core.acp_helper` | `iorgian\core\service\acp_helper` | ACP form keys, messages, delete confirmation, move links |
| `iorgian.core.position` | `iorgian\core\util\position` | Before / after / below option |
| `iorgian.core.sort_params` | `iorgian\core\util\sort_params` | Keep sorting parameters in links |
| `iorgian.core.range_help` | `iorgian\core\util\range_help` | "Choose between X and Y" help text |
| `iorgian.core.keys`, `iorgian.core.envelope` | `iorgian\core\service\*` | Zero-knowledge key storage and envelope validation |

It also fires the `iorgian.core.topics_moved` event once topics have been moved (with `topic_ids`).

## Notes

- Routes of the iorgian extensions avoid the words `store`, `files`, `cache`, `includes`, `vendor` and `phpbb`, which the standard phpBB nginx configuration blocks.
- Languages: English, Romanian, Spanish.

See `changelog.md` for the full description, to do list and change history.

## Author

Georgian Iordache (iorG19), <https://www.phpbb.com/customise/db/author/iorg19>

## License

GNU General Public License, version 2 (GPL-2.0-only). See `license.txt`.
