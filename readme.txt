=== Aomark Listings ===
Contributors: aomark
Tags: elementor, listings, custom post type, directory, real estate, map
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 3.4.0
License: GPLv2 or later

Aomark Listings is an Elementor-first listing engine with configurable listing models, generated custom post types, native meta fields, filters, results grids and maps.

== Description ==

Aomark Listings lets site builders create reusable listing models from presets such as Real Estate, Directory and Custom Listing.

Each model can define:

* Custom post type slug and labels
* Public taxonomies
* Native post meta fields stored as aomark_* keys
* Filterable fields and card fields
* Gallery and map location fields

The plugin registers modular Elementor widgets:

* Listing Results
* Listing Filter
* Listing Map
* Listing Field
* Listing Gallery
* Listing Meta

Version 3.0.0 is a clean break from the older site-specific real estate implementation. It no longer depends on Houzez, fave_* fields, SSRE shortcodes or theme-provided property structures.

== Changelog ==

= 3.4.0 =
* Replaced manual latitude/longitude controls with an interactive Leaflet location editor.
* Added debounced Photon address search through a cached, authenticated WordPress AJAX proxy.
* Added draggable and click-to-position map pins while keeping coordinates internal.

= 3.3.2 =
* Added configurable field placeholders and applied them to listing editor inputs, textareas, selects and locations.

= 3.3.1 =
* Made field IDs fully automatic and removed the internal identifier from the user-facing field editor.

= 3.3.0 =
* Added smart field ID and meta-key generation from Label with manual override protection.
* Added a compact bottom add-field action and converted taxonomies to collapsible accordions.
* Added server-side field identifier fallbacks for reliable saves without JavaScript.

= 3.2.3 =
* Switched the WordPress menu to the dedicated Aomark icon asset and refined the dashboard wordmark size.

= 3.2.2 =
* Updated the WordPress admin menu icon to use the original Aomark brand symbol.

= 3.2.1 =
* Replaced the simplified dashboard mark with the original Aomark Suite wordmark.

= 3.2.0 =
* Unified the model dashboard and listing editor with the Aomark Suite dark-glass design system.
* Matched Suite cards, pill tabs, gradient buttons, fields, focus states and responsive behavior.
* Updated the shared Aomark wordmark and menu icon assets and improved native select contrast.

= 3.1.1 =
* Reworked model fields as compact accordions with icon-only destructive actions.
* Added configurable editor tabs, including automatic real-estate Features grouping.
* Removed the redundant Elementor model tab and fixed dark select option states.

= 3.1.0 =
* Added complete responsive Style controls for all six Elementor widgets.
* Added selectable filter taxonomies and fields, customizable placeholders and reset behavior.
* Added granular listing card content, image, toolbar, pagination, empty and loading controls.
* Added dynamic field formatting, Select2 meta fields, gallery display options and configurable map interactions/popups.
* Fixed responsive grid and map height overrides and improved AJAX map/reset behavior.

= 3.0.0 =
* Rebuilt plugin as Aomark Listings.
* Added configurable listing models with Real Estate, Directory and Custom presets.
* Added generated CPTs, taxonomies and native metabox fields.
* Added modular Elementor widgets for results, filters, maps, fields, galleries and meta rows.
* Removed old Houzez/fave/SSRE compatibility layer.
