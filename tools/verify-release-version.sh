#!/usr/bin/env bash
#
# Vérifie que la version passée en argument est alignée dans toutes les sources
# de vérité du plugin. Adapté des scripts G2RD Facturation / Connector / Geo.
#
# Usage : bash tools/verify-release-version.sh 1.0.0
set -euo pipefail

VERSION="${1:-}"
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Usage: $0 <version>  (ex. 1.0.0)" >&2
  exit 2
fi

cd "$(dirname "$0")/.."
MAIN="g2rd-woocommerce-promotions.php"
README="readme.txt"

fail=0
check() {
  local label="$1" found="$2"
  if [ "$found" != "$VERSION" ]; then
    echo "ERREUR: $label = '${found:-<introuvable>}' (attendu '$VERSION')"
    fail=1
  else
    echo "OK   : $label = $found"
  fi
}

HEADER=$(grep -m1 -oP '^\s*\*\s*Version:\s*\K[0-9][0-9.]*' "$MAIN" || true)
CONST=$(grep -m1 -oP "G2RD_PROMO_VERSION'\s*,\s*'\K[0-9][0-9.]*" "$MAIN" || true)
STABLE=$(grep -m1 -oP '^Stable tag:\s*\K[0-9][0-9.]*' "$README" || true)
CHANGELOG=$(grep -m1 -oP '^=\s*\K[0-9][0-9.]*(?=\s*=)' <(sed -n '/^== Changelog ==/,$p' "$README") || true)

echo "Vérification de l'alignement des versions sur $VERSION"
check "g2rd-woocommerce-promotions.php (en-tête Version)" "$HEADER"
check "g2rd-woocommerce-promotions.php (G2RD_PROMO_VERSION)" "$CONST"
check "readme.txt (Stable tag)" "$STABLE"
check "readme.txt (dernière entrée du Changelog)" "$CHANGELOG"

UPDATE_URI=$(grep -m1 -oP '^\s*\*\s*Update URI:\s*\K\S+' "$MAIN" || true)
UPDATE_HOST=$(echo "$UPDATE_URI" | sed -E 's#^[a-zA-Z][a-zA-Z0-9+.-]*://([^/:]+).*#\1#' | tr '[:upper:]' '[:lower:]')
if [ -z "$UPDATE_URI" ]; then
  echo "ERREUR: Update URI absent (attendu : URL hors wordpress.org)"
  fail=1
elif [[ "$UPDATE_HOST" =~ (^|\.)(wordpress\.org|w\.org)$ ]]; then
  echo "ERREUR: Update URI pointe vers wordpress.org : $UPDATE_URI"
  fail=1
else
  echo "OK   : Update URI = $UPDATE_URI"
fi

if [ "$fail" -ne 0 ]; then
  echo ""
  echo "Au moins une source n'est pas alignée. Mets à jour les fichiers avant de taguer."
  exit 1
fi
echo ""
echo "Toutes les sources sont alignées sur $VERSION."
