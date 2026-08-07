# Aomark Listings quick start

This guide covers the shortest supported path from activation to a working listings page. You normally need only the friendly names, categories, and fields; technical identifiers are generated automatically.

## 1. Choose a listing type

Open **Aomark Listings** in WordPress admin. Choose one of the starter types:

- **Real Estate** for properties, prices, features, galleries, and locations.
- **Directory** for businesses, services, contact details, galleries, and locations.
- **Custom Listing** for a minimal catalogue that you can extend.

Enter the singular and plural names visitors and editors should see. Aomark Listings generates the internal post type, taxonomy, field, and meta identifiers automatically.

## 2. Review fields and filters

Open the listing type and review:

- **Basics** — human-facing names and archive behavior.
- **Categories & Filters** — category/tag groups visitors can browse or filter.
- **Listing Fields** — structured values used by cards, filters, details, galleries, and maps.
- **Advanced** — stable technical identifiers. They are read-only on existing listing types because changing them requires a data migration.

A field can be used in the listing editor, visitor filters, result cards, or any combination of those contexts.

## 3. Add the first listing

Use **Add listing** from the Aomark Listings dashboard. The normal WordPress fields have predictable roles:

- Title: listing name.
- Featured image: default result-card image.
- Main editor: full description.
- Excerpt: optional short WordPress summary.
- Aomark structured fields: filterable and reusable listing data.

For a location, open the **Location** section and review its OpenStreetMap notice. Hidden Location tabs do not request tiles; opening the section automatically loads the map and sends normal browser request data, including the administrator's IP address, to the tile provider. Address search remains a separate explicit Photon action. Fine-tune the marker by dragging it or clicking the map.

## 4. Build a listings page in Elementor

Add these widgets to the same Elementor container:

1. **Listing Filter** (optional).
2. **Listing Results**.
3. **Listing Map** (optional).

Choose the same Listing Type. If the page contains one compatible Results widget, the components connect automatically. When a page has multiple result groups, assign the same **Connection ID** to the Filter, Results, and Map that belong together.

Filters submit as normal URL forms and results remain usable without JavaScript. AJAX enhances the same URLs with in-place updates.

Connection IDs keep live groups separate. The public URL can preserve one filter state per listing type, so use separate pages when two groups for the same listing type each need an independently shareable state after a full reload.

## 5. Build the single-listing template

In an Elementor single template use:

- **Listing Field** for one structured value.
- **Listing Meta** for a group of values.
- **Listing Gallery** for an image/gallery field.
- **Listing Map** with the Current Listing source.

Leave the preview Listing ID empty on the real template. Elementor automatically uses the current listing; the ID option is only an advanced editor-preview override.

## Troubleshooting

- A blank widget in Elementor edit mode explains which Listing Type, field, gallery, or location is missing.
- If filters affect the wrong result group, set a unique Connection ID on all three related widgets.
- If a map has no markers, verify that the listing has a Location field with valid coordinates.
- Check **Tools → Site Health** for listing identifier conflicts.
- Re-save **Settings → Permalinks** only as a last manual recovery step; normal structural saves schedule a safe rewrite refresh automatically.
- After upgrading from 3.4.x, clear full-page and CDN caches once so pages receive the new signed AJAX descriptors.
