#!/bin/sh

# Usage: ./push.sh "commit message" 1.2.3

if [ $# -lt 2 ]; then
    echo "Usage: $0 \"commit message\" version"
    exit 1
fi

MSG="$1"
VER="$2"

# Adjust if needed
SLUG="shift8-gravitysap"
MAIN_FILE="shift8-gravitysap.php"

# ---- 0) Sanity checks (tolerate bullets/spacing/CRLF) ----
if ! grep -Eq '^[[:space:]]*\*?[[:space:]]*Stable tag:[[:space:]]*'"$VER"'[[:space:]]*\r?$' readme.txt; then
  echo "ERROR: readme.txt must contain: Stable tag: $VER"
  echo "       Found: $(grep -E '^[[:space:]]*\*?[[:space:]]*Stable tag:' readme.txt | head -n1 | sed 's/\r$//')"
  exit 1
fi

if ! grep -Eq '^[[:space:]]*\*?[[:space:]]*Version:[[:space:]]*'"$VER"'[[:space:]]*\r?$' "$MAIN_FILE"; then
  echo "ERROR: $MAIN_FILE must contain: Version: $VER"
  echo "       Found: $(grep -E '^[[:space:]]*\*?[[:space:]]*Version:' "$MAIN_FILE" | head -n1 | sed 's/\r$//')"
  exit 1
fi

# ---- 1) Push git first (unchanged) ----
git add .
git commit -m "$MSG"
git push origin main

# ---- 2) Rsync to SVN working copy (unchanged paths) ----
# NOTE: do not mkdir/rsync into tags/$VER; svn cp will create the tag.
rsync -ravzp --exclude-from './push.exclude' ./ ./svn/trunk
rsync -ravzp ./assets/ ./svn/assets

cd svn

# ---- 3) Add/clean changes in SVN working copy (keep your lines; add quiet cleanup) ----
svn --username shift8 add trunk/* || true
svn --username shift8 add assets/* || true
# Recursively add any nested new files and remove missing ones (quiet; no impact if none)
svn add --force trunk assets >/dev/null 2>&1 || true
svn rm $(svn status | awk '/^\!/ {print $2}') 2>/dev/null || true

# ---- 4) Commit trunk changes (unchanged) ----
svn commit -m "$MSG"

# ---- 5) Create tag server-side (URL→URL), then verify remote tag exists ----
if svn ls "https://plugins.svn.wordpress.org/$SLUG/tags/$VER/" >/dev/null 2>&1; then
  echo "Tag $VER already exists remotely; skipping creation."
else
  svn cp \
    "https://plugins.svn.wordpress.org/$SLUG/trunk" \
    "https://plugins.svn.wordpress.org/$SLUG/tags/$VER" \
    -m "Tagging version $VER"
fi

# Verify the tag really exists now
if ! svn ls "https://plugins.svn.wordpress.org/$SLUG/tags/$VER/" >/dev/null 2>&1; then
  echo "ERROR: Remote tag /tags/$VER/ not found after tagging."
  echo "Hint: check credentials/network or create manually with:"
  echo "  svn cp https://plugins.svn.wordpress.org/$SLUG/trunk https://plugins.svn.wordpress.org/$SLUG/tags/$VER -m \"Tagging version $VER\""
  cd ../
  exit 1
fi

# ---- 6) Keep your original WC tag+commit (safe no-op if tag dir absent locally) ----
# This preserves your flow/output while server-side tag already guarantees remote exists.
svn cp trunk "tags/$VER" 2>/dev/null || true
svn --username shift8 ci -m "$MSG version $VER" || true

# ---- 7) Back to original dir (unchanged) ----
cd ../

