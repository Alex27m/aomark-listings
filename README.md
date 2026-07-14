# Aomark Listings

[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)](https://wordpress.org/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://www.php.net/)
[![License: GPL v2 or later](https://img.shields.io/badge/License-GPL--2.0--or--later-blue.svg)](LICENSE)

An Elementor-first WordPress listings engine for building real-estate, directory, and custom listing websites without depending on a theme-specific property framework.

## Features

- Configurable listing models with generated custom post types.
- Real Estate, Directory, and Custom Listing presets.
- Public, hierarchical or tag-style taxonomies.
- Native WordPress meta fields with automatic internal identifiers.
- Text, textarea, number, price, select, checkbox, image, gallery, location, URL, email, and date field types.
- Filterable fields, card metadata, editor tabs, suffixes, placeholders, and select options.
- AJAX listing results with sorting, pagination, and load-more behavior.
- Interactive Leaflet maps with OpenStreetMap tiles.
- Address search powered by Photon, with draggable and click-to-position map pins.
- Responsive Elementor Style controls for filters, result cards, fields, galleries, metadata, and maps.
- Aomark Suite-aligned WordPress admin interface.

## Elementor widgets

- Listing Results
- Listing Filter
- Listing Map
- Listing Field
- Listing Gallery
- Listing Meta

The model engine and native listing editor remain available without Elementor. Elementor is required to use the included frontend widgets.

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer
- Elementor for the included visual widgets

## Installation

1. Download a release ZIP or clone this repository into `wp-content/plugins/aomark-listings`.
2. Activate **Aomark Listings** from the WordPress Plugins screen.
3. Open **Aomark Listings** in the WordPress admin menu.
4. Create a model from a preset or configure a custom model.
5. Add and edit listing entries, then place the Aomark Listings widgets in Elementor.

## Location services

The location editor and map widget use:

- [Leaflet](https://leafletjs.com/) for interactive maps.
- [OpenStreetMap](https://www.openstreetmap.org/copyright) for map tiles and geographic data.
- [Photon](https://github.com/komoot/photon) for address search.

Address searches run through an authenticated WordPress AJAX endpoint with debouncing and transient caching. The default public Photon endpoint is suitable for moderate usage but does not guarantee availability. High-traffic installations should use a dedicated or self-hosted geocoder.

The endpoint and optional country restriction can be changed without editing the plugin:

```php
add_filter( 'aomark_listings_geocoder_endpoint', function () {
	return 'https://photon.example.com/api/';
} );

add_filter( 'aomark_listings_geocoder_country_code', function () {
	return 'rs';
} );
```

The initial editor map position is also filterable:

```php
add_filter( 'aomark_listings_editor_map_default_center', function () {
	return [ 44.0165, 21.0059 ];
} );

add_filter( 'aomark_listings_editor_map_default_zoom', function () {
	return 7;
} );
```

## Development

The plugin has no build step. Run the syntax checks locally with:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
find assets -name '*.js' -print0 | xargs -0 -n1 node --check
```

GitHub Actions runs equivalent checks for every push and pull request.

## Contributing

Issues and focused pull requests are welcome. Please describe the expected behavior, reproduction steps, WordPress/PHP versions, and any relevant Elementor setup.

## License

Aomark Listings is licensed under the [GNU General Public License v2.0 or later](LICENSE).
