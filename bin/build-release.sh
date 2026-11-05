#!/usr/bin/env bash
#
# Build an installable plugin zip.
#
#   bin/build-release.sh             release/hospital-manager-<version>.zip
#   bin/build-release.sh --staging   release/hospital-manager-<version>-staging.zip
#
# The staging zip also carries the seeders, Faker and Tools > Hospital Manager Seed.
# Never install it on a production site: demo accounts have published passwords.
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
#            COMPOSER (composer PHP script; default: composer on PATH, else Local's).
#
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PLUGIN_DIR"

die() { echo "build-release.sh: $*" >&2; exit 1; }

STAGING=0
case "${1:-}" in
    "") ;;
    --staging) STAGING=1 ;;
    *) die "unknown option: $1 (usage: bin/build-release.sh [--staging])" ;;
esac

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
COMPOSER_BIN="${COMPOSER:-$(command -v composer || true)}"
if [[ -z "$COMPOSER_BIN" && -f /opt/Local/resources/extraResources/bin/composer/composer.phar ]]; then
    COMPOSER_BIN=/opt/Local/resources/extraResources/bin/composer/composer.phar
fi
[[ -n "$COMPOSER_BIN" ]] || die "composer not found (set COMPOSER)"
# Composer itself reads $COMPOSER as the path of composer.json
unset COMPOSER

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
RSYNC_RULES=()
if [[ "$STAGING" == 1 ]]; then
    # rsync uses the first matching rule, so these win over .distignore
    RSYNC_RULES=(--exclude=/database/seeders/README.md --include=/database/seeders/*** --include=/database/seeders.php)
fi
rsync -a "${RSYNC_RULES[@]}" --exclude-from=.distignore ./ "$STAGE/"

# --- 3. production dependencies ----------------------------------------------
echo "==> Removing dev dependencies"
( cd "$STAGE" && "$HM_PHP" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet )
rm -f "$STAGE/composer.json" "$STAGE/composer.lock"

# Safety net: what may and must ship for each build
FORBIDDEN=(database/reset-data.php database/run-seeders.php database/bootstrap-wp.php tests vendor/phpunit vendor/mockery .env .claude debug.log)
SUFFIX=""
if [[ "$STAGING" == 1 ]]; then
    SUFFIX="-staging"
    [[ -d vendor/fakerphp/faker ]] || die "vendor/fakerphp is missing: run composer install first"
    cp -a vendor/fakerphp "$STAGE/vendor/"
    for f in database/seeders/SeedTool.php database/seeders.php vendor/fakerphp/faker/src/Faker/Factory.php; do
        [[ -e "$STAGE/$f" ]] || die "staging release is missing $f"
    done
else
    FORBIDDEN+=(database/seeders database/seeders.php vendor/fakerphp)
fi
for f in "${FORBIDDEN[@]}"; do
    [[ ! -e "$STAGE/$f" ]] || die "release would contain $f"
done
[[ -f "$STAGE/assets/js/dist/bundle.js" && -f "$STAGE/assets/css/dist/frontend.css" ]] || die "release is missing the built frontend"

# --- 4. zip -------------------------------------------------------------------
mkdir -p release
ZIP="$PLUGIN_DIR/release/hospital-manager-$VERSION$SUFFIX.zip"
rm -f "$ZIP"
( cd "$STAGE_ROOT" && zip -qr "$ZIP" hospital-manager )
echo "==> Built $ZIP ($(du -h "$ZIP" | cut -f1))"
