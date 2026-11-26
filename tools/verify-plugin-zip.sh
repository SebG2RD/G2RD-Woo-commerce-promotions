#!/usr/bin/env bash
#
# Valide le ZIP de release : intégrité, racine unique, fichiers requis / interdits.
# Adapté des scripts G2RD Facturation / Geo.
#
# Usage : bash tools/verify-plugin-zip.sh dist/g2rd-woocommerce-promotions-1.0.0.zip
set -euo pipefail

ZIP="${1:-}"
if [ -z "$ZIP" ] || [ ! -f "$ZIP" ]; then
  echo "Usage: $0 <chemin/vers/plugin.zip>" >&2
  exit 2
fi
ZIP="$(cd "$(dirname "$ZIP")" && pwd)/$(basename "$ZIP")"
cd "$(dirname "$0")/.."

SLUG="g2rd-woocommerce-promotions"
fail=0

echo "Intégrité de l'archive"
unzip -tq "$ZIP" >/dev/null && echo "OK   : archive valide" || { echo "ERREUR: archive corrompue"; exit 1; }

LIST="$(unzip -Z1 "$ZIP" | grep -v '/$')"

echo "Dossier racine unique"
ROOTS="$(echo "$LIST" | awk -F/ '{print $1}' | sort -u)"
if [ "$ROOTS" != "$SLUG" ]; then
  echo "ERREUR: dossiers racine trouvés : $(echo "$ROOTS" | tr '\n' ' ') (attendu : $SLUG)"
  fail=1
else
  echo "OK   : racine $SLUG/"
fi

require() {
  if echo "$LIST" | grep -qxF "$SLUG/$1"; then
    echo "OK   : $1"
  else
    echo "ERREUR: fichier requis absent : $1"
    fail=1
  fi
}
forbid() {
  if echo "$LIST" | grep -qE "^$SLUG/$1"; then
    echo "ERREUR: fichier de dev présent : $1"
    fail=1
  else
    echo "OK   : absent $1"
  fi
}

echo "Fichiers requis"
require "g2rd-woocommerce-promotions.php"
require "uninstall.php"
require "readme.txt"
require "includes/Rules.php"
require "includes/Repository.php"
require "includes/Pricing.php"
require "includes/Orders.php"
require "includes/functions.php"
require "includes/Admin.php"
require "assets/admin.css"
require "assets/admin.js"

echo "Fichiers de dev interdits"
forbid "tests/"
forbid "tools/"
forbid "docs/"
forbid "vendor/"
forbid "node_modules/"
forbid "\.ci-refs/"
forbid "(.*/)?\.git"
forbid "(.*/)?\.github/"
forbid "(.*/)?\.env"
forbid "composer\.(json|lock)"
forbid "phpcs"
forbid "phpstan"
forbid ".*\.(zip|log|sh|ps1|pem|key|bak)$"

echo "Taille"
SIZE=$(stat -c%s "$ZIP" 2>/dev/null || stat -f%z "$ZIP")
echo "     : $((SIZE / 1024)) Ko"
if [ "$SIZE" -gt $((5 * 1024 * 1024)) ]; then
  echo "ERREUR: ZIP > 5 Mo, vérifier les exclusions"
  fail=1
fi

if [ "$fail" -ne 0 ]; then
  echo ""
  echo "Le ZIP n'est pas conforme."
  exit 1
fi
echo ""
echo "ZIP conforme."
