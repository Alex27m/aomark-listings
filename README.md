# Aomark Listings

[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)](https://wordpress.org/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://www.php.net/)
[![License: GPL v2 or later](https://img.shields.io/badge/License-GPL--2.0--or--later-blue.svg)](LICENSE)

An Elementor-first WordPress listings engine for real-estate, directory, and custom listing sites. Version 3.5.1 keeps the existing data model and public URLs while making setup safer, clearer, progressively enhanced, and reliable with both pretty and plain permalinks.

## What it provides

- Guided Real Estate, Directory, and Custom Listing starters.
- Generated custom post types, public taxonomies, and native WordPress post meta.
- Text, textarea, number, price, select, checkbox, image, gallery, location, URL, email, and date fields.
- Search, filtering, sorting, numbered pagination, Load More, results cards, and maps.
- Normal URL behavior without JavaScript, enhanced in place when JavaScript is available.
- Six Elementor widgets: Listing Results, Filter, Map, Field, Gallery, and Meta.
- Locally bundled Leaflet; no executable JavaScript or CSS is loaded from a CDN.

The model engine and native listing editor work without Elementor. Elementor is required only for the visual widgets.

## Quick start

1. Install and activate the plugin.
2. Open **Aomark Listings** and choose a starter listing type.
3. Review its friendly names, categories, filters, and fields.
4. Publish a listing.
5. Add **Listing Results** in Elementor; add Filter and Map if needed.
6. Use the same Listing Type and, for pages with multiple groups, the same unique Connection ID on related widgets.

Existing technical identifiers are read-only because changing them without a migration can disconnect stored content and Elementor documents. Friendly labels remain editable.

See the full [quick-start guide](docs/quick-start.md), [compatibility contract](docs/compatibility-contract.md), and [release test matrix](docs/testing.md).

When upgrading from 3.4.x, clear full-page and CDN caches once. Version 3.5 rejects stale unsigned AJAX descriptors by design; the server-rendered fallback continues to work while caches refresh.

## Multiple widget groups

Connection IDs isolate live Filter–Results–Map groups, including stale-request cancellation and map updates. Different listing types are also isolated in normal URL and browser-history behavior.

The public `alm_*` URL format stores one state per listing type. If two groups for the same listing type must each retain an independent state after a full page reload, place them on separate pages. Their live AJAX interactions can still be isolated on one page with different Connection IDs.

## Location and external services

Leaflet 1.9.4 is included locally under `assets/vendor/leaflet/` with its license. Its human-readable source is available as [leaflet-src.js](https://unpkg.com/leaflet@1.9.4/dist/leaflet-src.js) and in the [official 1.9.4 source tag](https://github.com/Leaflet/Leaflet/tree/v1.9.4). The optional location features use two documented external services by default:

- [Photon](https://photon.komoot.io/) for editor address search. An authenticated editor's address query, optional country code, site URL/plugin version in the User-Agent, and normal server request metadata are sent from WordPress to Photon. Searches require at least three characters, are rate limited, request at most five results, and successful responses are cached for 12 hours. See [Komoot's privacy policy](https://www.komoot.com/privacy).
- [OpenStreetMap tiles](https://operations.osmfoundation.org/policies/tiles/) for admin and frontend maps. Admin tiles are explicit click-to-load; frontend tiles load only on pages where a site owner placed the Map widget. A browser loading a map sends normal request data—such as IP address, User-Agent, referrer, and tile coordinates—to `tile.openstreetmap.org`. See the [OSMF privacy policy](https://osmfoundation.org/wiki/Privacy_Policy) and [attribution requirements](https://www.openstreetmap.org/copyright).

The plugin adds suggested disclosure text to WordPress' Privacy Policy Guide and sends no analytics or telemetry to Aomark.

The geocoder can be changed or self-hosted without editing plugin files:

```php
add_filter( 'aomark_listings_geocoder_endpoint', function () {
	return 'https://photon.example.com/api/';
} );

add_filter( 'aomark_listings_geocoder_country_code', function () {
	return 'rs';
} );
```

The HTTPS tile template and required attribution are filterable too:

```php
add_filter( 'aomark_listings_tile_url', function () {
	return 'https://tiles.example.com/{z}/{x}/{y}.png';
} );

add_filter( 'aomark_listings_tile_attribution', function () {
	return '&copy; Example Maps';
} );
```

The initial location-editor view is also filterable:

```php
add_filter( 'aomark_listings_editor_map_default_center', function () {
	return [ 44.0165, 21.0059 ];
} );

add_filter( 'aomark_listings_editor_map_default_zoom', function () {
	return 7;
} );
```

## Compatibility and security

- Existing model IDs, post types, taxonomy slugs, field IDs, meta keys, CSS classes, Elementor widget names, and public `alm_*` parameters remain compatibility contracts.
- Unknown model references fail closed instead of falling back to another type.
- Public AJAX settings use purpose-bound HMAC descriptors and server-side allowlists.
- Anonymous listing queries are limited to published content; privileged post previews require WordPress capabilities.
- Query sizes, pages, filters, terms, map markers, geocoder requests, and remote response bodies are bounded.
- Schema upgrades are additive and idempotent, with a one-time rollback snapshot of an existing registry.

## Development checks

The plugin has no build step. With PHP and Node.js installed:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/model-contract.php
find assets -name '*.js' -print0 | xargs -0 -n1 node --check
node tests/static-contracts.mjs
npm run check
git diff --check
```

GitHub Actions runs PHP syntax checks on PHP 7.4, 8.3, and 8.5, the PHP model contracts, JavaScript syntax, static compatibility contracts, a deterministic exact-package gate, and the official WordPress Plugin Check action. WordPress integration, browser, legacy-upgrade, accessibility, and scale scenarios remain explicit manual release checks in [docs/testing.md](docs/testing.md).

## License

Aomark Listings is licensed under the [GNU General Public License v2.0 or later](LICENSE). Leaflet retains its own included license notice.
