# Self-hosted Protomaps basemap

The map uses a **self-hosted Protomaps dark vector basemap** instead of a
third-party tile provider. This removed the dependency on CARTO's dark tiles,
whose license expired (v1.0.8).

## How it works

- `assets/js/location-map.js` renders the basemap with
  [`protomaps-leaflet`](https://github.com/protomaps/protomaps-leaflet) (loaded
  from unpkg, same as Leaflet). One canvas layer draws the dark-flavor basemap
  **and** its labels — no separate label layer.
- The vector tiles come from a single `.pmtiles` archive
  (`bl-basemap-world-z7.pmtiles`, ~180 MB) served **same-origin** from
  `wp-content/bl-maps/`.
- `protomaps-leaflet` reads the archive with HTTP **Range** requests. Because it
  is same-origin there is **no CORS** to configure, and Cloudflare edge-caches
  the byte ranges. Only the tiles in view are fetched (a few hundred KB for the
  world view), not the whole 180 MB.

## Why `wp-content/bl-maps/` (not the plugin dir, not uploads)

- **Not the plugin dir** — a WordPress plugin update deletes and re-extracts the
  plugin folder, which would wipe a 180 MB file that can't live in the repo/ZIP
  (GitHub's 100 MB file limit). `wp-content/bl-maps/` survives plugin updates.
- **Not `wp-content/uploads/`** — the `cdn-uploads-rewrite` mu-plugin rewrites
  every `/uploads/` URL to the CloudFront distribution (which has no Range/CORS
  behaviour for this path). `bl-maps/` is outside `/uploads/`, so it is left
  same-origin.

The URL is resolved in PHP by `bl_locmap_pmtiles_url()` via
`content_url('bl-maps/<file>')`, so it is domain-agnostic. Override with the
`BL_LOCMAP_PMTILES_URL` constant or the `bl_locmap_pmtiles_url` filter to move
the archive to a CDN (that CDN must support Range **and** CORS preflight).

## Installing the archive on a site

The `.pmtiles` is **not** in the repo or the release ZIP. After installing/
updating the plugin, place the archive once per site:

```bash
# from the machine that has the built archive:
scp -O bl-basemap-world-z7.pmtiles \
  <install>@<install>.ssh.wpengine.net:/home/wpe-user/sites/<install>/wp-content/bl-maps/
```

(`scp -O` — the WP Engine SSH gateway only speaks the legacy SCP protocol, not
SFTP. The `wp-content/bl-maps/` directory must exist first: `mkdir -p`.)

Verify it serves with Range support:

```bash
curl -sI  https://<site>/wp-content/bl-maps/bl-basemap-world-z7.pmtiles   # 200, accept-ranges: bytes
curl -s -r 0-6 https://<site>/wp-content/bl-maps/bl-basemap-world-z7.pmtiles | xxd  # "PMTiles"
```

## Rebuilding the archive

See [`build-basemap.sh`](build-basemap.sh). It extracts a global, zoom-capped
dark basemap from the Protomaps daily planet build using the `pmtiles` CLI —
only the low-zoom tiles are downloaded (a few hundred MB of transfer), not the
138 GB planet.

The map caps at zoom 12 and vector tiles overzoom cleanly, so the source is
capped at **z7** (~180 MB) — plenty of detail for a global locations map while
keeping the archive small. Bump the cap in the script for more street-level
detail at the cost of a larger file.
