#!/usr/bin/env bash
#
# Run PHP the way the Local (by Flywheel) site serving this plugin runs it:
# same PHP version, its bundled shared libraries, and the site's php.ini (which
# points mysqli/pdo at the site's MySQL socket).
#
#   bin/local-php.sh database/seeders.php clinical
#   bin/local-php.sh -v
#
# Overrides:
#   HM_PHP=/path/to/php     use this PHP binary instead (no Local lookup)
#   LOCAL_SITE_ID=xxxxxxxx  Local site id, if auto-detection picks the wrong site
#
set -euo pipefail

if [[ -n "${HM_PHP:-}" ]]; then
    exec "$HM_PHP" "$@"
fi

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# <site>/app/public/wp-content/plugins/<plugin>
SITE_DIR="$(cd "$PLUGIN_DIR/../../../../.." && pwd)"

if [[ -d "$HOME/.config/Local" ]]; then
    LOCAL_DIR="$HOME/.config/Local"                          # Linux
elif [[ -d "$HOME/Library/Application Support/Local" ]]; then
    LOCAL_DIR="$HOME/Library/Application Support/Local"      # macOS
else
    echo "local-php: Local's config directory not found. Set HM_PHP=/path/to/php." >&2
    exit 1
fi

# Look up the site id and PHP version in Local's sites.json
read -r SITE_ID PHP_VERSION < <(python3 - "$LOCAL_DIR/sites.json" "$SITE_DIR" "${LOCAL_SITE_ID:-}" <<'PY'
import json, os, sys
sites = json.load(open(sys.argv[1]))
site_dir, wanted = os.path.realpath(sys.argv[2]), sys.argv[3]
for sid, s in sites.items():
    path = os.path.realpath(os.path.expanduser(s.get("path", "")))
    if (wanted and sid == wanted) or (not wanted and path == site_dir):
        print(sid, s["services"]["php"]["version"])
        break
else:
    sys.exit("local-php: no Local site found for " + site_dir + " (set LOCAL_SITE_ID)")
PY
)

PHP_ROOT=""
for candidate in "$LOCAL_DIR"/lightning-services/php-"$PHP_VERSION"+*/bin/*; do
    if [[ -x "$candidate/bin/php" ]]; then
        PHP_ROOT="$candidate"
        break
    fi
done
if [[ -z "$PHP_ROOT" ]]; then
    echo "local-php: PHP $PHP_VERSION not found under $LOCAL_DIR/lightning-services" >&2
    exit 1
fi

INI="$LOCAL_DIR/run/$SITE_ID/conf/php/php.ini"
INI_ARGS=()
[[ -f "$INI" ]] && INI_ARGS=(-c "$INI")

if [[ -d "$PHP_ROOT/shared-libs" ]]; then
    export LD_LIBRARY_PATH="$PHP_ROOT/shared-libs${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
    export DYLD_LIBRARY_PATH="$PHP_ROOT/shared-libs${DYLD_LIBRARY_PATH:+:$DYLD_LIBRARY_PATH}"
fi

exec "$PHP_ROOT/bin/php" "${INI_ARGS[@]}" "$@"
