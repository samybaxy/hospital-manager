#!/usr/bin/env bash
#
# Build an installable plugin zip: release/hospital-manager-<version>.zip
#
#   bin/build-release.sh
#
# Steps:
#   1. production frontend build (assets/js/dist, assets/css/dist; not in git)
#   2. copy the plugin to a staging folder, leaving out everything in .distignore
#      (tests, sources, dev tooling, and the seed/reset scripts that change data)
#   3. composer install --no-dev in the staging copy (drops PHPUnit, Mockery, Faker...)
#   4. zip it
#
# Install the zip from Plugins > Add New > Upload Plugin, or unzip it into
# wp-content/plugins/. Updating over an existing install keeps all data: tables
# are upgraded on the next page load (CreateHospitalTables::SCHEMA_VERSION).
#
# Overrides: HM_PHP (PHP binary; default: bin/local-php.sh if it works, else php),
#            COMPOSER (composer binary; default: composer on PATH).
#
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PLUGIN_DIR"

die() { echo "build-release.sh: $*" >&2; exit 1; }

VERSION="$(sed -n 's/^ \* Version: *//p' hospital-manager.php | head -1 | tr -d '[:space:]')"
[[ -n "$VERSION" ]] || die "could not read Version from hospital-manager.php"

# --- PHP / Composer ----------------------------------------------------------
if [[ -z "${HM_PHP:-}" ]]; then
    if "$PLUGIN_DIR/bin/local-php.sh" -v >/dev/null 2>&1; then
        HM_PHP="$PLUGIN_DIR/bin/local-php.sh"
    else
        HM_PHP="$(command -v php || true)"
    fi
fi
[[ -n "$HM_PHP" ]] || die "no PHP found (set HM_PHP)"
COMPOSER="${COMPOSER:-$(command -v composer || true)}"
[[ -n "$COMPOSER" ]] || die "composer not found (set COMPOSER)"

# --- 1. frontend --------------------------------------------------------------
echo "==> Building frontend (production)"
command -v npx >/dev/null || die "npx not found (install Node.js)"
[[ -d node_modules ]] || die "node_modules missing: run yarn install (or npm install) first"
npx vite build --mode production
[[ -f assets/js/dist/bundle.js ]] || die "build did not produce assets/js/dist/bundle.js"

# --- 2. staging copy ----------------------------------------------------------
STAGE_ROOT="$(mktemp -d)"
trap 'rm -rf "$STAGE_ROOT"' EXIT
STAGE="$STAGE_ROOT/hospital-manager"
echo "==> Copying plugin to $STAGE"
rsync -a --exclude-from=.distignore ./ "$STAGE/"

# --- 3. production dependencies ----------------------------------------------
echo "==> Removing dev dependencies"
( cd "$STAGE" && "$HM_PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet )
rm -f "$STAGE/composer.json" "$STAGE/composer.lock"

# Safety net: none of these may ship
for f in database/reset-data.php database/seeders.php database/run-seeders.php tests vendor/phpunit vendor/fakerphp debug.log; do
    [[ ! -e "$STAGE/$f" ]] || die "release would contain $f"
done

# --- 4. zip -------------------------------------------------------------------
mkdir -p release
ZIP="$PLUGIN_DIR/release/hospital-manager-$VERSION.zip"
rm -f "$ZIP"
( cd "$STAGE_ROOT" && zip -qr "$ZIP" hospital-manager )
echo "==> Built $ZIP ($(du -h "$ZIP" | cut -f1))"
