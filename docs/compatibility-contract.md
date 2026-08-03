# Backward compatibility contract

Aomark Listings treats the following values as public compatibility contracts. A normal update or friendly label change must not rewrite them.

## Stored data

- Model registry option: `aomark_listings_models`.
- Schema version option: `aomark_listings_schema_version`.
- Existing model IDs, post type keys, taxonomy slugs, field IDs, and meta keys.
- Existing post meta values and location key suffixes: `_address`, `_lat`, and `_lng`.

Schema upgrades are additive and idempotent. The first schema-v2 upgrade stores a non-autoloaded `aomark_listings_models_backup_v1` snapshot when an existing registry is present.

## Elementor

Existing widget names remain stable:

- `aomark_listing_results`
- `aomark_listing_filter`
- `aomark_listing_map`
- `aomark_listing_field`
- `aomark_listing_gallery`
- `aomark_listing_meta`

Existing control IDs and saved Elementor values remain valid. New controls must have safe defaults. Existing pages must not require an Elementor re-save after upgrading.

## Frontend and URLs

Existing CSS classes remain available. Public filter URLs continue to use `alm_*` parameters, including `alm_keyword`, `alm_sort`, `alm_page`, `alm_model`, taxonomy keys, and `alm_min_*` / `alm_max_*` field ranges.

`alm_model` scopes public filter state so one listing type cannot consume another type's URL values. Connection IDs isolate live groups in JavaScript, but the current public URL contract represents one state per listing type. Two same-type groups that require independent reloadable/shareable states belong on separate pages until URL parameters are namespaced in a future, explicitly versioned contract.

The existing `aomark_listings_results` AJAX action remains registered. Its versioned descriptor is an integrity boundary, not a replacement public API for arbitrary meta queries.

## Developer filters

These filters remain supported:

- `aomark_listings_geocoder_endpoint`
- `aomark_listings_geocoder_country_code`
- `aomark_listings_editor_map_default_center`
- `aomark_listings_editor_map_default_zoom`
- `aomark_listings_tile_url`
- `aomark_listings_tile_attribution`
- `aomark_listings_filter_term_limit`

## Migration rules

- Friendly singular/plural labels may change without changing technical identifiers.
- Existing technical identifiers are read-only in the normal editor. A future identifier change requires an explicit migration with an impact preview.
- Duplicate presets receive a new namespace and must never share a post type or taxonomy registration.
- Unknown saved model references fail closed; they do not silently use the first configured model.
