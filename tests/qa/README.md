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
