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

const [plugin, readme, assets, models, admin, query, render, frontend, privacy] = await Promise.all([
	read('aomark-real-estate.php'),
	read('readme.txt'),
	read('includes/listings-assets.php'),
	read('includes/listings-models.php'),
	read('includes/listings-admin.php'),
	read('includes/listings-query.php'),
	read('includes/listings-render.php'),
	read('assets/js/listings.js'),
	read('includes/listings-privacy.php'),
]);

const headerVersion = plugin.match(/^ \* Version:\s*([^\r\n]+)$/m)?.[1];
const constantVersion = plugin.match(/define\(\s*'AOMARK_LISTINGS_VERSION',\s*'([^']+)'\s*\)/)?.[1];
const stableVersion = readme.match(/^Stable tag:\s*([^\r\n]+)$/m)?.[1];

assert.ok(headerVersion, 'The plugin header must declare a version');
assert.equal(headerVersion, constantVersion, 'Header and runtime versions must match');
assert.equal(headerVersion, stableVersion, 'Plugin and readme stable versions must match');
assert.match(readme, /^Tested up to:\s*\d+\.\d+$/m, 'readme.txt must declare Tested up to');
assert.equal((readme.match(/^Tags:/gm) || []).length, 1, 'readme.txt must contain one Tags header');
assert.ok((readme.match(/^Tags:\s*(.+)$/m)?.[1].split(',') || []).length <= 5, 'WordPress.org allows at most five tags');

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
assert.match(assets, /aomark-listings-leaflet/, 'Leaflet handles must be plugin-prefixed');
assert.match(assets, /static \$registered = false;/, 'Asset localization must be idempotent');

assert.match(render, /function aomark_listings_create_signed_descriptor/, 'Results need signed public descriptors');
assert.match(render, /function aomark_listings_verify_signed_descriptor/, 'AJAX must verify signed descriptors');
assert.match(render, /hash_equals\(/, 'Descriptor signatures require timing-safe comparison');
assert.match(render, /aomark_listings_verify_signed_descriptor\( \$descriptor, 'results' \)/, 'Results descriptors must be purpose-bound');
assert.match(render, /aomark_listings_verify_signed_descriptor\( \$map_descriptor, 'map' \)/, 'Map descriptors must be purpose-bound');
assert.match(query, /'post_status'\s*=>\s*'publish'/, 'Public listing queries must explicitly request published posts');
assert.match(query, /'has_password'\s*=>\s*false/, 'Public listing queries must exclude protected posts before pagination');
assert.match(query, /'compare'\s*=>\s*'REGEXP'/, 'Map queries must reject malformed coordinate metadata before numeric casts');
assert.match(query, /\[\.,\]/, 'Map queries must retain legacy decimal-comma coordinates');
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
assert.match(render, /PHP_QUERY_RFC3986/, 'AJAX pagination links must use document-relative, model-scoped query URLs');
assert.match(render, /data-model-id=.*data-query-map=/, 'Bare query maps must expose their model for AJAX matching');

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
assert.match(privacy, /wp_add_privacy_policy_content/, 'The plugin must offer WordPress privacy-policy guidance');

process.stdout.write('Static compatibility contracts passed.\n');
