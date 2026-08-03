import assert from 'node:assert/strict';
import { readFile, stat } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (relativePath) => readFile(path.join(root, relativePath), 'utf8');

async function mustExist(relativePath) {
	const details = await stat(path.join(root, relativePath));
	assert.ok(details.isFile(), `${relativePath} must be a file`);
}

const [plugin, legacyBootstrap, readme, assets, models, admin, query, render, frontend, adminFrontend, privacy, frontendCss, adminCss] = await Promise.all([
	read('aomark-listings.php'),
	read('aomark-real-estate.php'),
	read('readme.txt'),
	read('includes/listings-assets.php'),
	read('includes/listings-models.php'),
	read('includes/listings-admin.php'),
	read('includes/listings-query.php'),
	read('includes/listings-render.php'),
	read('assets/js/listings.js'),
	read('assets/js/admin-listings.js'),
	read('includes/listings-privacy.php'),
	read('assets/css/listings.css'),
	read('assets/css/admin-listings.css'),
]);

const headerVersion = plugin.match(/^ \* Version:\s*([^\r\n]+)$/m)?.[1];
const constantVersion = plugin.match(/define\(\s*'AOMARK_LISTINGS_VERSION',\s*'([^']+)'\s*\)/)?.[1];
const stableVersion = readme.match(/^Stable tag:\s*([^\r\n]+)$/m)?.[1];

assert.ok(headerVersion, 'The plugin header must declare a version');
assert.equal(headerVersion, constantVersion, 'Header and runtime versions must match');
assert.equal(headerVersion, stableVersion, 'Plugin and readme stable versions must match');
assert.doesNotMatch(legacyBootstrap, /^\s*\*\s+Plugin Name:/m, 'The legacy bootstrap must not register a duplicate plugin');
assert.match(legacyBootstrap, /require_once __DIR__ \. '\/aomark-listings\.php'/, 'The legacy bootstrap must load the canonical main file');
assert.match(legacyBootstrap, /active_sitewide_plugins/, 'The legacy bootstrap must migrate network-active installations');
assert.match(readme, /^Tested up to:\s*\d+\.\d+$/m, 'readme.txt must declare Tested up to');
assert.equal((readme.match(/^Tags:/gm) || []).length, 1, 'readme.txt must contain one Tags header');
assert.ok((readme.match(/^Tags:\s*(.+)$/m)?.[1].split(',') || []).length <= 5, 'WordPress.org allows at most five tags');
assert.match(readme, /^Contributors:\s*alex2703$/m, 'WordPress.org contributor must match the owner profile');

for (const file of [
	'assets/vendor/leaflet/LICENSE',
	'assets/vendor/leaflet/dist/leaflet.css',
	'assets/vendor/leaflet/dist/leaflet.js',
	'assets/vendor/leaflet/dist/images/layers-2x.png',
	'assets/vendor/leaflet/dist/images/layers.png',
	'assets/vendor/leaflet/dist/images/marker-icon-2x.png',
	'assets/vendor/leaflet/dist/images/marker-icon.png',
	'assets/vendor/leaflet/dist/images/marker-shadow.png',
]) {
	await mustExist(file);
}

const localLeaflet = await read('assets/vendor/leaflet/dist/leaflet.js');
const ownedRuntime = [plugin, assets, admin, models, query, render, frontend, privacy].join('\n');
assert.doesNotMatch(localLeaflet, /sourceMappingURL=/, 'Vendored Leaflet must not reference an omitted source map');
assert.doesNotMatch(ownedRuntime, /unpkg\.com|cdnjs\.cloudflare\.com\/ajax\/libs\/leaflet/i, 'Leaflet executable assets must remain local');
assert.doesNotMatch(ownedRuntime, /\{s\}\.tile\.openstreetmap\.org/, 'OpenStreetMap standard tiles must not use subdomains');
assert.match(frontend, /https:\/\/tile\.openstreetmap\.org\/\{z\}\/\{x\}\/\{y\}\.png/, 'Frontend maps must use the current standard tile URL');
assert.match(assets, /function aomark_listings_tile_url/, 'Tile templates must be filterable through a validated helper');
assert.match(assets, /function aomark_listings_tile_attribution/, 'Tile attribution must be filterable and sanitized');
assert.match(assets, /aomark-listings-leaflet/, 'Leaflet handles must be plugin-prefixed');
assert.match(assets, /static \$registered = false;/, 'Asset localization must be idempotent');
assert.match(models, /data-aomark-location-load-map/, 'Admin maps must require an explicit click-to-load action');
assert.match(adminFrontend, /loadMapButton\.on\('click', loadMap\)/, 'Admin map tiles must load only after the consent button is used');
assert.match(models, /data-aomark-location-search-address/, 'Photon search must expose an explicit user action');
assert.match(adminFrontend, /searchButton\.on\('click',[\s\S]*searchAddress\(query\)/, 'Photon requests must start from the explicit search action');
assert.doesNotMatch(adminFrontend, /setTimeout\(function\(\)\{ searchAddress\(query\); \}, 650\)/, 'Typing alone must not contact Photon');
assert.doesNotMatch(models + admin, /aomark-tabs|aomark-tab-panel|class="aomark-tab(?:\s|")/, 'Listings tab classes must not collide with Suite globals');
assert.match(models, /aomark_listings_admin_field_control_id/, 'Structured admin controls need stable label targets');
assert.match(models, /label for="' \. esc_attr\( \$control_id \)/, 'Structured admin controls need explicit label associations');
assert.match(models, /aomark-listings-location-coordinates/, 'Location editors need a keyboard coordinate alternative');
assert.match(models, /role="group" aria-labelledby=/, 'Media controls need field-specific accessible grouping');
assert.match(models, /Choose image for %s/, 'Image picker controls need field-specific accessible names');
assert.match(adminFrontend, /data-aomark-metabox-tab[\s\S]*ArrowLeft[\s\S]*ArrowRight/, 'Metabox tabs need keyboard navigation');
assert.match(adminFrontend, /panelAttribute[\s\S]*prop\('hidden', true\)/, 'Inactive tab panels must leave the accessibility tree');
assert.match(frontendCss, /--alm-accent:\s*#7e22ce/, 'Default action color must meet AA contrast with white text');
assert.match(frontendCss, /@media \(forced-colors: active\)/, 'Frontend controls need forced-colors support');
assert.match(adminCss, /@media \(forced-colors: active\)/, 'Admin controls need forced-colors support');
assert.doesNotMatch(frontendCss, /outline:\s*3px solid color-mix\(in srgb, var\(--alm-accent\) 45%, #fff\)/, 'Focus indicators must retain at least 3:1 contrast');
assert.match(adminFrontend, /tileLayer\.on\('tileerror'[\s\S]*mapTilesUnavailable/, 'Admin tile failures need an accessible fallback');
assert.match(adminFrontend, /lat >= -90[\s\S]*lng <= 180;/, 'Valid zero coordinates must remain accepted');

assert.match(render, /function aomark_listings_create_signed_descriptor/, 'Results need signed public descriptors');
assert.match(render, /function aomark_listings_verify_signed_descriptor/, 'AJAX must verify signed descriptors');
assert.match(render, /hash_equals\(/, 'Descriptor signatures require timing-safe comparison');
assert.match(render, /aomark_listings_verify_signed_descriptor\( \$descriptor, 'results' \)/, 'Results descriptors must be purpose-bound');
assert.match(render, /aomark_listings_verify_signed_descriptor\( \$map_descriptor, 'map' \)/, 'Map descriptors must be purpose-bound');
assert.match(render, /function aomark_listings_default_results_url/, 'Filters need an archive-safe public action resolver');
assert.match(render, /'results_url'\s*=>\s*\$results_url/, 'Sanitized results settings must preserve the resolved filter action');
assert.match(render, /'results_url'\s*=>\s*\$settings\['results_url'\]/, 'Integrated AJAX filters must reuse the signed public action');
for (const resolverPrimitive of ['is_post_type_archive', 'get_post_type_archive_link', 'is_tax', 'get_term_link', 'get_queried_object', 'instanceof WP_Post']) {
	assert.ok(render.includes(resolverPrimitive), `Public filter actions must retain ${resolverPrimitive}`);
}
assert.match(render, /function aomark_listings_route_query_args/, 'Plain-permalink WordPress routes need a bounded query allowlist');
assert.match(render, /data-aomark-route-param="yes"/, 'GET forms must retain plain-permalink routing controls');
assert.match(render, /aomark_listings_results_page_url\([\s\S]*\$results_url/, 'AJAX pagination must retain its signed public base URL');
assert.match(render, /\$map_only[\s\S]*if \( ! \$map_only \)[\s\S]*aomark_listings_render_results_inner/, 'Map-only AJAX must skip the results query');
assert.match(frontend, /body\.append\('map_only', '1'\)/, 'Map-only frontend requests must declare their response mode');
assert.match(query, /'post_status'\s*=>\s*'publish'/, 'Public listing queries must explicitly request published posts');
assert.match(query, /'has_password'\s*=>\s*false/, 'Public listing queries must exclude protected posts before pagination');
assert.match(query, /'compare'\s*=>\s*'REGEXP'/, 'Map queries must reject malformed coordinate metadata before numeric casts');
assert.match(query, /\[\.,\]/, 'Map queries must retain legacy decimal-comma coordinates');
assert.match(query, /min\( 200, absint\( \$settings\['map_limit'\]/, 'Public map queries must remain capped at 200 pins');
assert.match(query, /array_slice\([\s\S]*0, 100/, 'Gallery metadata reads must remain bounded');
assert.match(query, /function aomark_listings_request_targets_model/, 'URL filters must be scoped to their model');
assert.match(models, /AOMARK_LISTINGS_SCHEMA_VERSION = 2/, 'The compatibility schema version must remain explicit');
assert.match(models, /\$field\['key'\] \. '_address'/, 'Registry validation must reserve location-derived storage keys');
assert.match(admin, /function aomark_listings_admin_validate_technical_values/, 'Existing technical identifiers need server-side immutability checks');
assert.match(admin, /function aomark_listings_admin_validate_posted_structure/, 'Malformed admin model rows must fail closed');
assert.match(frontend, /if \(model && model !== formModel\)/, 'History form synchronization must remain scoped by model as well as connection');
assert.match(admin, /data-aomark-technical-input readonly/, 'Technical identifiers must be read-only in the normal editor');
assert.match(admin, /'' !== \$edit_id && isset\( \$models\[ \$edit_id \] \)/, 'Admin editing must support the valid model ID zero');
assert.match(models + await read('includes/listings-elementor-controls.php'), /'' !== \$default \? \$default : \( \$keys\[0\] \?\? '' \)/, 'Elementor model defaults must preserve the valid ID zero');

for (const primitive of ['WeakMap', 'AbortController', 'popstate', 'data-aomark-connection', 'filtersForWidget']) {
	assert.ok(frontend.includes(primitive), `Frontend isolation must retain ${primitive}`);
}
assert.match(frontend, /if \(options\.append !== true\)[\s\S]*snapshotForWidget\(widget, state\)/, 'Load More must not replace the URL-addressable history snapshot with a partial page');
assert.doesNotMatch(frontend, /status\.focus\(/, 'Hidden live status nodes must never receive focus');
assert.match(frontend, /focusElement\(responseMeta\.firstAdded \|\| inner\)/, 'Load More must move focus to newly appended content');
assert.match(frontend, /routeSignature[\s\S]*key\.indexOf\('alm_'\) !== 0/, 'AJAX interception must distinguish plain and custom-taxonomy route queries');
assert.match(frontend, /data-aomark-route-param/, 'Reset and history synchronization must preserve route controls');
assert.match(frontend, /retryOptions\.focusResults = true/, 'A successful retry must restore focus after replacing its button');
assert.match(frontend, /retryOptions\.focusRetry = true/, 'A repeated failed retry must focus its replacement retry button');
assert.match(frontend, /mapUnavailable[\s\S]*aomark:listings:map-error/, 'Missing map libraries must expose an accessible failure state');
assert.match(frontend, /tileLayer\.on\('tileerror'[\s\S]*mapTilesUnavailable/, 'Blocked tile providers must expose an accessible failure state');
assert.match(frontend, /sort: state\.sort, focusSort: true/, 'AJAX sorting must restore focus to the replacement select');
assert.match(frontend, /options\.focusSort === true[\s\S]*data-aomark-listings-sort/, 'AJAX sort focus must not jump to the results region');
assert.match(render, /data-model-id=.*data-query-map=/, 'Bare query maps must expose their model for AJAX matching');
assert.match(render, /data-aomark-listings-results-inner role="region"[\s\S]*tabindex="-1"/, 'AJAX results need a visible programmatic focus target');
assert.match(render, /'checkbox' === \$field\['type'\][\s\S]*<option value="1"[\s\S]*<option value="0"/, 'Checkbox filters must expose understandable Yes and No values');

const widgets = new Map([
	['widgets/listing-results.php', 'aomark_listing_results'],
	['widgets/listing-filter.php', 'aomark_listing_filter'],
	['widgets/listing-map.php', 'aomark_listing_map'],
	['widgets/listing-field.php', 'aomark_listing_field'],
	['widgets/listing-gallery.php', 'aomark_listing_gallery'],
	['widgets/listing-meta.php', 'aomark_listing_meta'],
]);

for (const [file, widgetName] of widgets) {
	const source = await read(file);
	assert.ok(source.includes(`return '${widgetName}';`), `${file} must preserve the ${widgetName} Elementor contract`);
}

for (const disclosure of ['Photon', 'OpenStreetMap', 'privacy policy', 'IP address', 'User-Agent']) {
	assert.ok(readme.includes(disclosure), `readme.txt must disclose ${disclosure}`);
}
assert.match(readme, /leaflet-src\.js/, 'Minified Leaflet must link to human-readable source');
assert.match(readme, /retained by default/, 'The readme must disclose uninstall data retention');
assert.match(privacy, /wp_add_privacy_policy_content/, 'The plugin must offer WordPress privacy-policy guidance');

process.stdout.write('Static compatibility contracts passed.\n');
