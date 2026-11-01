# Changelog – Iorgian Core (iorgian/core)

## Description

**Iorgian Core** is the shared base library for the `iorgian` phpBB extensions. It does not add visible
features by itself: it provides reusable services so that every extension (starting with
`iorgian/multiprefixes`) can stay small and consistent.

Requires: phpBB >= 3.3.0, PHP >= 7.4. Designed to keep the 3.3.x / 4.0 differences in one place.

## Features

- **Compatibility layer** (`iorgian.core.compat`): single place that knows whether phpBB 3.x or 4.x is running.
- **Permission helper** (`iorgian.core.acl`): local forum ACL checks and user group membership.
- **Colors** (`iorgian.core.colors`): hex color validation/normalization and automatic black/white text contrast.
- **Color field** (`iorgian.core.color_field`): ACP color field with the browser's color selector (real-time, no hex codes needed), a text field for typed #rgb / #rrggbb codes and an optional "Default" button. Shared by Multi Prefixes and Tags.
- **Badges** (`iorgian.core.badge`): shared colored badge renderer, with its own stylesheet (`.iorgian-badge`).
- **Forums** (`iorgian.core.forums`): forum tree and `<option>` list read directly from the database (no query cache, so new forums show up immediately).
- **Groups** (`iorgian.core.groups`): list of user groups with translated names.
- **Ordering** (`iorgian.core.ordering`): generic move up / move down logic for admin lists.
- **ACP helper** (`iorgian.core.acp_helper`): form keys, success / error messages with the back link, delete confirmation and signed move up / down links for ACP controllers.
- **Position** (`iorgian.core.position`): validates and names the "before / after / below the title" option.
- **Sort parameters** (`iorgian.core.sort_params`): keeps `st`, `sk` and `sd` in links built by extensions.
- **Range help** (`iorgian.core.range_help`): translated "choose at least X / up to Y / between X and Y" text (English, Romanian, Spanish).
- **Topics moved event** (`iorgian.core.topics_moved`): fired once after topics have been moved, with `topic_ids`; every topic already has its new forum in the database.
- **Keys** (`iorgian.core.keys` + `/iorgian/keys/*`): stores each user's public key and a private-key blob wrapped in the browser with an Argon2id-derived key. The server cannot decrypt anything.
- **Envelope validator** (`iorgian.core.envelope`): lets servers refuse anything that is not a well-formed envelope.
- **Browser crypto** (`IorgianCrypto`): identity creation, unlock, passphrase change, authenticated multi-recipient encryption (`crypto_box` + `crypto_secretbox`), key fingerprints. Requires libsodium-wrappers, which dependent extensions must ship locally.
- **Enable-time checks**: minimum PHP and phpBB versions, with translated error messages (English, Romanian, Spanish).

## To do

- [ ] Rewrite **TopicTags** (replacement for RobertHeim/phpbb-ext-topictags) on top of this library, fixing its known bugs.
- [ ] Cross-extension awareness: Tags detect when Multi Prefixes is installed and warn, for example, when a new tag has the same name as an existing prefix.
- [ ] Bring the options that Multi Prefixes already has (per-forum availability, group restrictions, min/max, colors) to tags.
- [ ] Shared migration helpers.
- [ ] (Optional) Font Awesome icon support in badges.

## History

### 0.3.0-a1 (2026-10-24)
- New services `acp_helper`, `position`, `sort_params` and `range_help`, and the `iorgian.core.topics_moved` event (listener `topics_listener`), so the extensions stop repeating the same code.
- New language keys `IORGIAN_CORE_HELP_MIN`, `IORGIAN_CORE_HELP_MAX`, `IORGIAN_CORE_HELP_MIN_MAX` (en, es, ro).
- Only additions: nothing existing was renamed or removed, so `pmsafe`, `tags` and `multiprefixes` keep working unchanged. Migration `update_version_030` updates the stored version.

### 0.2.4-a1 (2026-10-18)
- New service `iorgian.core.color_field`: the ACP color field (selector + hex text + optional "Default" button) used by Multi Prefixes and Tags, so the code lives in one place. Extensions print the HTML returned by `render($name, $raw_value, $allow_reset)` and validate the posted value with `iorgian.core.colors`.
- `iorgian.core.colors::picker_value()`: valid value for `<input type="color">` (always `#rrggbb`, with a fallback).
- New language key `IORGIAN_CORE_COLOR_RESET` (en, es, ro).
- Only additions: nothing existing was renamed or removed, so `pmsafe` and any other dependent extension keep working. Migration `update_version_024` updates the stored version.

### 0.2.3-a1 (2026-10-15)
- The endpoint that saves the key bundle is now `/iorgian/keys/save` (was `/iorgian/keys/store`). The standard phpBB nginx configuration blocks every URL that contains the words `store`, `files`, `cache`, `includes`, `vendor` or `phpbb`, so the old path answered "403 Forbidden" with an HTML page. Avoid those words in any route of the iorgian extensions.
- Migration `update_version_023` updates the stored version.

### 0.2.2-a1 (2026-10-12)
- Envelope is now a compact binary structure with a single base64 layer instead of JSON inside base64: roughly 35% smaller (2 recipients, short message: 616 instead of 924 characters; 50 recipients: about 5.7k instead of 9.3k). A 40,000-character message now fits under phpBB's default 60,000-character limit.
- `iorgian.core.envelope` validates the binary layout (version, sender, recipient count, duplicated key ids, minimum lengths).
- Migration `update_version_022` updates the stored version.

### 0.2.1-a1 (2026-10-12)
- Envelopes now index recipients by an opaque key id (hash of the public key) in random order instead of user id, so BCC recipients are not revealed. `encrypt()` takes an array of public keys; `decrypt()` no longer needs the reader's user id. Envelopes are armored as base64 (JSON; replaced by a binary layout in 0.2.2-a1).
- Added `iorgian.core.envelope`: strict server-side validation of the envelope shape.
- Migration `update_version_021` updates the stored version.
- Added `tests/test_crypto.js`: 43 checks of the browser crypto module (identity, unlock, passphrase change, multi-recipient encryption, tampering, envelope shape) run under Node in a browser-like context.

### 0.2.0-a1 (2026-10-11)
- Added `iorgian_user_keys` table (migration `add_user_keys`), `keys` service, JSON endpoints under `/iorgian/keys/` (own bundle, public keys, store) with CSRF hash.
- Key material is deleted when a user is deleted (`core.delete_user_after`).
- Added `iorgian_crypto.js` browser module (libsodium). No private-message code lives in core: that belongs to separate extensions.

### 0.1.1-a1 (2026-10-10)
- Added `badge`, `forums`, `groups` and `ordering` services and the shared badge stylesheet.
- `composer.json`, `README.md` and this changelog are now in English.

### 0.1.0-dev (2026-10-09)
- Initial skeleton: `compat`, `acl` and `colors` services, requirement checks, base migration.
