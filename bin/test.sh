#!/usr/bin/env bash
#
# Run the PHPUnit suite against the Local site's PHP + MySQL.
#
#   bin/test.sh                                   # whole suite
#   bin/test.sh --testsuite Models                # Models | Services | Controllers
#   bin/test.sh --filter DataIntegrityTest        # one class (or method)
#
# Any arguments are passed straight to PHPUnit. The script:
#   - runs Local's PHP for this site (see bin/local-php.sh) and puts a `php` shim on
#     PATH for this run only (WordPress' test bootstrap shells out to `php`)
#   - checks the site's MySQL is running and the test database exists
#   - installs the WordPress test library if it's missing (e.g. /tmp was cleared)
#
# Tests use a separate database (wordpress_test) and roll back after each test;
# they never touch the site's data.
#
# Overrides: WP_TEST_DB_NAME (wordpress_test), WP_TEST_DB_USER (root),
#            WP_TEST_DB_PASS (root), WP_TESTS_DIR, WP_CORE_DIR, plus those of
#            bin/local-php.sh (HM_PHP, LOCAL_SITE_ID).
#
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SITE_DIR="$(cd "$PLUGIN_DIR/../../../../.." && pwd)"
WP_DIR="$PLUGIN_DIR/../../.."
cd "$PLUGIN_DIR"

DB_NAME="${WP_TEST_DB_NAME:-wordpress_test}"
DB_USER="${WP_TEST_DB_USER:-root}"
DB_PASS="${WP_TEST_DB_PASS:-root}"
# Must match phpunit.xml
export WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
export WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"

die() { echo "test.sh: $*" >&2; exit 1; }

# --- PHP ---------------------------------------------------------------------
"$PLUGIN_DIR/bin/local-php.sh" -v >/dev/null 2>&1 \
    || die "could not run PHP via bin/local-php.sh (try: bin/local-php.sh -v)"

SHIM_DIR="$(mktemp -d)"
trap 'rm -rf "$SHIM_DIR"' EXIT
cat > "$SHIM_DIR/php" <<EOF
#!/usr/bin/env bash
exec "$PLUGIN_DIR/bin/local-php.sh" "\$@"
EOF
chmod +x "$SHIM_DIR/php"
export PATH="$SHIM_DIR:$PATH"

[[ -x vendor/bin/phpunit ]] || die "vendor/bin/phpunit missing. Run: bin/local-php.sh \$(command -v composer) install"

# --- MySQL -------------------------------------------------------------------
# The site's php.ini points mysqli at the site's socket; reuse it.
SOCKET="$(php -r 'echo ini_get("mysqli.default_socket");')"
if [[ -n "$SOCKET" ]]; then
    [[ -S "$SOCKET" ]] || die "MySQL socket $SOCKET not found. Start the site in Local first."
    DB_HOST="localhost:$SOCKET"
else
    DB_HOST="${WP_TEST_DB_HOST:-localhost}"
fi

php -r '
    [$host, $user, $pass, $name] = array_slice($argv, 1);
    [$h, $sock] = array_pad(explode(":", $host, 2), 2, null);
    $db = @new mysqli($h, $user, $pass, "", 0, $sock);
    if ($db->connect_errno) { fwrite(STDERR, "test.sh: cannot connect to MySQL: {$db->connect_error}\n"); exit(1); }
    $db->query("CREATE DATABASE IF NOT EXISTS `" . $db->real_escape_string($name) . "`");
' "$DB_HOST" "$DB_USER" "$DB_PASS" "$DB_NAME" || exit 1

# --- WordPress test library --------------------------------------------------
if [[ ! -f "$WP_TESTS_DIR/includes/functions.php" || ! -f "$WP_CORE_DIR/wp-settings.php" ]]; then
    WP_VERSION="$(php -r 'include $argv[1]; echo $wp_version;' "$WP_DIR/wp-includes/version.php")"
    echo "Installing WordPress $WP_VERSION test library into $WP_TESTS_DIR ..."
    # The installer skips anything whose folder exists, so a half-finished earlier
    # attempt (e.g. offline) left them empty for good. Start those over.
    [[ -f "$WP_CORE_DIR/wp-settings.php" ]] || rm -rf "$WP_CORE_DIR" /tmp/wordpress.tar.gz
    [[ -f "$WP_TESTS_DIR/includes/functions.php" ]] || rm -rf "$WP_TESTS_DIR"
    # last arg "true": skip DB creation (done above; avoids needing mysqladmin)
    bash bin/install-wp-tests.sh "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" "$WP_VERSION" true >/dev/null
fi

export WP_TEST_DB_HOST="$DB_HOST"
exec php vendor/bin/phpunit "$@"
