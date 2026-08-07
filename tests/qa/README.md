# Disposable WordPress Playground QA

These files create local, throwaway WordPress sites. They do not target the
live Moderne Kuce site and `tests/qa` must stay outside the release ZIP.

Run each command from the plugin repository root. Replace `[PORT]` with an
unused local port.

## Current clean install

This mounts the working tree as `aomark-listings`, installs current Elementor
and WooCommerce from WordPress.org, activates Hello Elementor, runs the fixture,
and opens the generated public QA page.

```powershell
& "C:\Program Files\nodejs\npx.cmd" @wp-playground/cli@latest server `
  --wp=latest `
  --php=8.3 `
  --blueprint=tests/qa/current-clean-install-blueprint.json `
  --blueprint-may-read-adjacent-files `
  --mount=".:/wordpress/wp-content/plugins/aomark-listings" `
  --login `
  --port=[PORT]
```

## Dependency-degraded install

This repeats the native model, CPT, taxonomy, permalink, query, renderer, and
map-data checks without installing Elementor. WooCommerce remains active only
as a coexistence check.

```powershell
& "C:\Program Files\nodejs\npx.cmd" @wp-playground/cli@latest server `
  --wp=latest `
  --php=8.3 `
  --blueprint=tests/qa/dependency-degraded-blueprint.json `
  --blueprint-may-read-adjacent-files `
  --mount=".:/wordpress/wp-content/plugins/aomark-listings" `
  --login `
  --port=[PORT]
```

Both fixtures write their machine-readable result to the
`aomark_listings_qa_result` option and print the same JSON from the `runPHP`
step. A failed assertion stores `status: fail` and aborts the Blueprint.

`browser-runtime.php` is copied only into the disposable site's `mu-plugins`
directory by the current clean-install Blueprint. At request time it replaces
the fixture page's stored sample markup with freshly rendered real filter,
results, map, and meta controls so WordPress content sanitization and `wpautop`
cannot rewrite those controls. It is not part of the production package
allowlist.

## Declared minimum versions

`minimum-core-blueprint.json` runs the native engine on WordPress 6.0 and PHP
7.4 without Elementor or WooCommerce. `minimum-elementor-blueprint.json` adds
the declared Elementor 3.5.0 widget floor. Run them with the same source mount,
changing only the Blueprint and version flags:

```powershell
& "C:\Program Files\nodejs\npx.cmd" @wp-playground/cli@latest run-blueprint `
  --wp=6.0 `
  --php=7.4 `
  --blueprint=tests/qa/minimum-core-blueprint.json `
  --blueprint-may-read-adjacent-files `
  --mount-dir="." "/wordpress/wp-content/plugins/aomark-listings"

& "C:\Program Files\nodejs\npx.cmd" @wp-playground/cli@latest run-blueprint `
  --wp=6.0 `
  --php=7.4 `
  --blueprint=tests/qa/minimum-elementor-blueprint.json `
  --blueprint-may-read-adjacent-files `
  --mount-dir="." "/wordpress/wp-content/plugins/aomark-listings"
```

## Exact-ZIP Plugin Check

First create the future release artifact at exactly
`dist/aomark-listings.zip`. The ZIP must contain one top-level
`aomark-listings/` directory. Then run:

```powershell
& "C:\Program Files\nodejs\npx.cmd" @wp-playground/cli@latest server `
  --wp=latest `
  --php=8.3 `
  --blueprint=tests/qa/plugin-check-blueprint.json `
  --blueprint-may-read-adjacent-files `
  --mount="dist:/tmp/aomark-dist" `
  --login `
  --port=[PORT]
```

The Plugin Check Blueprint installs only Plugin Check from WordPress.org and
loads the exact ZIP without activating Aomark Listings or runtime dependencies.
Select **Aomark Listings**, keep General, Plugin Repo, Security, Performance,
Accessibility, Error, and Warning enabled, and leave optional AI analysis off.
The release gate is the completed message `Checks complete. No errors found.`

## Advanced security and bounded-scale gates

The advanced fixture creates 300 published listings plus protected records and
runs 63 named assertions for schema backup/idempotency, visibility, roles,
nonces, signed descriptors, tamper rejection, input bounds, five 60-item pages,
and the 200-marker server cap:

```powershell
& "C:\Program Files\nodejs\npx.cmd" @wp-playground/cli@latest run-blueprint `
  --wp=latest `
  --php=8.3 `
  --blueprint=tests/qa/advanced-gates-blueprint.json `
  --blueprint-may-read-adjacent-files `
  --mount-dir="." "/wordpress/wp-content/plugins/aomark-listings"
```

This is a bounded abuse/regression gate, not a claim of 5,000-record MySQL,
Core Web Vitals, or production-host load coverage.

## Exact 3.5.1 to 3.5.3 basename upgrade

These fixtures install the immutable 3.5.1 release ZIP, activate its historical
`aomark-real-estate.php` entry point, overlay the exact current ZIP, and prove
single-site or network activation migrates to `aomark-listings.php`. They also
hash the real `aomark_listings_models` registry before the overlay and compare
it afterwards. Network mode seeds the plugin's real default per-site registry
because network activation intentionally leaves that option lazy until a site
uses it.

Mount the directory containing
`aomark-listings-3.5.1-wporg-final-20260731.zip` at `/tmp/aomark-old`, the current
`dist` directory at `/tmp/aomark-new`, and `tests/qa` at `/tmp/aomark-qa`:

- immutable 3.5.1 SHA-256: `0181D246CF09CB6A56ABE1E74BC3936EC896008D8800417E76D1B92BCB80B20E`
- reviewed 3.5.3 SHA-256: `04D3AED041160C68EA958ED92DE1C5AD4CE06467031F5D867EC2A2900146C0F8`

Both Blueprints verify these checksums before installing either package, so a
different ZIP with the same version string cannot pass the upgrade gate.

```powershell
& "C:\Program Files\nodejs\npx.cmd" @wp-playground/cli@latest run-blueprint `
  --wp=latest `
  --php=8.3 `
  --blueprint=tests/qa/legacy-basename-upgrade-blueprint.json `
  --mount-dir="[OLD_ZIP_DIRECTORY]" "/tmp/aomark-old" `
  --mount-dir="dist" "/tmp/aomark-new" `
  --mount-dir="tests/qa" "/tmp/aomark-qa"

& "C:\Program Files\nodejs\npx.cmd" @wp-playground/cli@latest run-blueprint `
  --wp=latest `
  --php=8.3 `
  --site-url=http://playground.test `
  --blueprint=tests/qa/legacy-basename-network-upgrade-blueprint.json `
  --mount-dir="[OLD_ZIP_DIRECTORY]" "/tmp/aomark-old" `
  --mount-dir="dist" "/tmp/aomark-new" `
  --mount-dir="tests/qa" "/tmp/aomark-qa"
```

Both commands must exit `0`. The fixture also proves the legacy file no longer
registers a second plugin header and that canonical deactivate/reactivate paths
continue to work.
