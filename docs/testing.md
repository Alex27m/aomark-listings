# Test plan

Use this matrix for release-candidate testing. The legacy fixture should be exported from an unmodified 3.4.0 installation before upgrading.

## Fake sites

### A — Fresh Real Estate

- Create a Real Estate listing type through the guided admin.
- Add at least 30 listings with prices, galleries, locations, and several missing coordinates.
- Verify create/edit/delete, cards, fixed filters, visitor filters, all sort modes, numbered pagination, Load More, map popups, archive, and single pages.
- Repeat filter, reset, sort, and pagination checks with both pretty and plain permalinks, including a custom-taxonomy archive.

### B — Directory and privacy

- Include phone, email, URL, published, draft, and private listings.
- Test as administrator, editor, subscriber, and anonymous visitor.
- Tamper with the public AJAX descriptor, field IDs, model, fixed filters, page, and limits.
- Confirm anonymous output includes only published listings and only server-authorized fields.

### C — Multiple views

- Add Real Estate and Directory views to one page.
- Add two independent Filter–Results–Map groups for the same listing type.
- Verify different listing types never consume each other's URL filters on initial load, AJAX updates, or browser Back/Forward.
- Give same-type groups different Connection IDs and verify live filters, sort, pagination, maps, and concurrent requests do not cross-update.
- Confirm the documented URL limitation: a full reload applies the one public `alm_*` state to that listing type. Use separate pages when both same-type states must be independently shareable.

### D — Legacy 3.4.0 upgrade

- Import the saved registry, posts, terms, meta, and Elementor documents.
- Upgrade twice to prove the migration is idempotent.
- Verify identifiers, URLs, HTML contracts, filters, and all six saved widget types.
- Confirm no Elementor document requires re-saving.

### E — Scale and abuse

- Generate roughly 5,000 listings with varied taxonomy/meta values.
- Exercise the maximum accepted page, per-page value, term choices, field filters, keyword length, and map markers.
- Confirm over-limit requests are rejected before `WP_Query` and do not produce PHP warnings or memory spikes.

### F — Degraded and accessibility

- Disable JavaScript and verify filter, reset, sort, and numbered pagination.
- Verify hidden Location tabs make no OpenStreetMap request, then confirm opening Location automatically loads tiles and exposes the documented browser request-data/IP disclosure.
- Simulate Photon timeout/429 and blocked OpenStreetMap tiles.
- Test zero listing types, deleted fields, missing coordinates, and stale Elementor references.
- Complete keyboard-only checks, visible focus, results announcements, location combobox navigation, high contrast, zoom, and mobile layouts.

## Automated CI gates

- PHP syntax on PHP 7.4, 8.3, and 8.5.
- PHP model contracts for identifier bounds, duplicate presets, value parsing, signed descriptors, schema backup, immutable technical values, and request model scoping.
- JavaScript syntax checks for all local and vendored scripts.
- Static release contracts for versions, local Leaflet assets/license, AJAX integrity boundaries, public query status, widget names, group isolation primitives, privacy disclosure, and forbidden CDN patterns.
- Official WordPress Plugin Check.

## Manual release gates

The following checks require a real WordPress/Elementor browser environment and are not claimed as automated CI coverage:

- Run the fresh, privacy/roles, multiple-view, legacy-upgrade, scale/abuse, no-JavaScript, and accessibility scenarios above.
- Test the declared WordPress range, including the current `Tested up to` version, with representative Elementor versions.
- Check PHP notices with `WP_DEBUG` and `SCRIPT_DEBUG` enabled.
- Verify keyboard navigation, focus, announcements, responsive layouts, browser Back/Forward, and blocked external services.
- Inspect the release ZIP: local Leaflet CSS, JavaScript, images, and license must be present; development-only files and remote executable assets must be absent.
- Run WPCS/PHPCS when the project adopts a pinned ruleset; the current workflow uses Plugin Check and does not represent PHPCS as an automated gate.

## 3.5.2 release evidence (2026-08-03)

Completed in disposable local WordPress environments, never on the live QA
site:

- latest WordPress/PHP 8.3 clean install and dependency-degraded source runs;
- WordPress 6.0/PHP 7.4 core-only and Elementor 3.5 compatibility runs;
- 63 advanced assertions with 300 public records, protected-record leakage
  checks, roles/nonces, descriptor tampering, request bounds, five pages of 60,
  and the 200-marker cap;
- exact 3.5.1 to exact 3.5.2 single-site and multisite plugin-basename upgrades,
  including pre/post SHA-256 comparison of the real models option;
- exact-ZIP filter, sort, pagination, Back/Forward, 320 px no-overflow, admin tab
  keyboard behavior, field labels, media group names, and Suite selector
  coexistence;
- Axe Core 4.12.1 over desktop/mobile frontend and all four listing/model admin
  tabs: zero label, ARIA, color-contrast, serious, or critical findings outside
  overlapping Leaflet map markers. The marker target-size finding is retained as
  a documented WCAG 2.5.8 geographic-placement exception; marker Enter opens the
  matching popup link, and listing links remain available outside the map;
- current official Plugin Check with General, Plugin Repo, Security,
  Performance, Accessibility, Error, and Warning enabled: `Checks complete. No
  errors found.`

Not claimed by this evidence: NVDA/VoiceOver testing, a 5,000-record MySQL load
run, Core Web Vitals under production traffic, Elementor Pro, or every block
theme. Those remain follow-up compatibility/performance work and are not hidden
behind the 300-record bounded fixture.
