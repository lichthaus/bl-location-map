# BL Location Map

WordPress plugin that renders an interactive global map of Boart Longyear corporate offices and authorized distributors. Dark "Operations Console" style matching the BL Diamond Bit Configurator.

- **Corporate offices** — orange pulsing markers
- **Distributors** — blue markers
- **Drag / zoom** the map; **click** a marker to open a slide-in detail panel (no popups covering the map)
- **Search**, **region filter**, and **type toggle** (Corporate / Distributor / All)
- **Marker clustering** when zoomed out
- Mobile responsive (panel becomes full-width)

## Installation

1. Download the latest ZIP from the [Releases](https://github.com/lichthaus/bl-location-map/releases) page (or zip this repo).
2. WordPress admin → **Plugins → Add New → Upload Plugin** → select the ZIP → **Activate**.
3. Place the shortcode on your contact page:

```
[bl_location_map]
```

The plugin auto-checks `update.json` on the `main` branch and surfaces new versions in the WP Admin → Plugins update notifier.

## Shortcode attributes

| Attribute | Default  | Description |
|-----------|----------|-------------|
| `header`  | `true`   | Show the built-in "Global Locations" title bar. Set `false` if your page already carries its own heading. |
| `height`  | `78vh`   | Map container height. Accepts `px`, `vh`, `rem`, `em`, `%`. |
| `class`   | _(empty)_ | Extra CSS class added to the wrapper, useful for theme overrides. |

Examples:

```
[bl_location_map]
[bl_location_map header="false"]
[bl_location_map height="600px"]
[bl_location_map header="false" height="80vh" class="my-contact-map"]
```

## Updating the locations dataset

Locations live in [`locations.json`](locations.json). Each row must include numeric `lat` and `lng`; rows without coordinates are silently skipped.

```json
{
  "region": "North America",
  "type": "Corporate",          // "Corporate" or "Distributor"
  "company": "Boart Longyear …",
  "address": "…",
  "lat": 40.7608,
  "lng": -111.8910,
  "contact": "Sales",
  "phone": "+1 …",
  "email": "info@example.com",
  "web": "www.example.com"
}
```

After editing, bump `BL_LOCMAP_VERSION` in [`bl-location-map.php`](bl-location-map.php) and the `version` field in [`update.json`](update.json), then commit & push — connected sites will see the update notice.

## Local preview

Open [`index.html`](index.html) directly in a browser (no server needed — the dataset is inlined for offline preview).

## File layout

```
bl-location-map/
├── bl-location-map.php           Plugin entry, shortcode, asset enqueuing, auto-updater
├── assets/
│   ├── css/location-map.css      Dark "Operations Console" styles, scoped to .bl-locmap
│   └── js/location-map.js        Leaflet init, filtering, panel logic
├── locations.json                Source of truth for all locations
├── index.html                    Standalone offline preview (data inlined)
├── update.json                   Auto-updater manifest
└── README.md
```

## Tech notes

- **Map library:** [Leaflet 1.9.4](https://leafletjs.com/) (MIT, no API key)
- **Plugin:** [Leaflet.markercluster 1.5.3](https://github.com/Leaflet/Leaflet.markercluster) (MIT)
- **Tiles:** CARTO Dark Matter (free, attribution required — already included)
- **Data injection:** PHP loads `locations.json`, hands it to JS via `wp_localize_script` plus an inline `<script type="application/json">` fallback. **No fetch calls** — works on cached pages and behind picky CDNs.
- **Asset loading:** CSS/JS are only enqueued on pages where the `[bl_location_map]` shortcode is present (checks both `post_content` and Elementor's `_elementor_data`).

## License

Proprietary — © Boart Longyear Drilling Products
