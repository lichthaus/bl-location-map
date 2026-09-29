#!/usr/bin/env bash
#
# Build the self-hosted Protomaps dark vector basemap for BL Location Map.
#
# Extracts a global, zoom-capped subset of the Protomaps daily planet build.
# `pmtiles extract` reads the remote planet over HTTP Range and downloads only
# the tiles it needs (a few hundred MB), NOT the full ~138 GB planet.
#
# Requires the pmtiles CLI:  brew install pmtiles   (https://docs.protomaps.com)
#
# Output: bl-basemap-world-z7.pmtiles  (~180 MB)
# Deploy: place at  wp-content/bl-maps/  on each site (see basemap/README.md).

set -euo pipefail

MAXZOOM="${MAXZOOM:-7}"                      # source zoom cap; map overzooms past this
OUT="${OUT:-bl-basemap-world-z${MAXZOOM}.pmtiles}"

# Latest daily planet build. Override SRC to pin a specific date for reproducibility,
# e.g. SRC=https://build.protomaps.com/20260929.pmtiles
DATE="${DATE:-$(date -u +%Y%m%d)}"
SRC="${SRC:-https://build.protomaps.com/${DATE}.pmtiles}"

command -v pmtiles >/dev/null || { echo "pmtiles CLI not found — 'brew install pmtiles'"; exit 1; }

echo "Source : $SRC"
echo "Maxzoom: $MAXZOOM"
echo "Output : $OUT"
echo

# Dry-run first to report the size, then build the whole world up to MAXZOOM.
pmtiles extract "$SRC" /dev/null --maxzoom="$MAXZOOM" --dry-run
pmtiles extract "$SRC" "$OUT"   --maxzoom="$MAXZOOM" --download-threads=8

echo
pmtiles show "$OUT" | sed -n '1,12p'
echo
echo "Done: $OUT"
