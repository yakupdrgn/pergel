#!/usr/bin/env bash
# Installs a throwaway WordPress with Pergel for CI smoke and budget runs.
#
#   tools/ci/setup-wp.sh            (configured through the variables below)
#
# The same steps as the local site: Pergel theme and engine active, the
# performance probe as a must-use plugin, fixed content from
# tools/fixtures/seed.php, pretty permalinks, no WP-Cron, no update checks
# from admin pages. Serve it with
#   php -S 127.0.0.1:8080 -t "$WP_DIR" tools/ci/router.php
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WP_DIR="${WP_DIR:-/tmp/pergel-wp}"
WP_VERSION="${WP_VERSION:-latest}"
SITE_URL="${SITE_URL:-http://127.0.0.1:8080}"
DB_NAME="${DB_NAME:-wordpress}"
DB_USER="${DB_USER:-wordpress}"
DB_PASS="${DB_PASS:-wordpress}"
DB_HOST="${DB_HOST:-127.0.0.1:3306}" # The database must exist and be empty.
# Copy instead of symlinking where symlinks are unreliable (Git Bash on Windows).
LINK_MODE="${LINK_MODE:-symlink}"
# PHP needs a native path on Windows (Git Bash); same path on Linux.
REPO_PHP="$REPO"
WP_PHP="$WP_DIR"
if command -v cygpath > /dev/null; then REPO_PHP="$(cygpath -m "$REPO")"; WP_PHP="$(cygpath -m "$WP_DIR")"; fi

wp() { command wp --path="$WP_PHP" "$@"; }
# Downloads from wordpress.org fail now and then on the runner (DNS timeouts).
retry() { for i in 1 2 3; do "$@" && return 0; echo "Retrying in 15 s: $*"; sleep 15; done; return 1; }

echo "::group::WordPress $WP_VERSION in $WP_DIR"
rm -rf "$WP_DIR"
mkdir -p "$WP_DIR"
retry wp core download --version="$WP_VERSION" --skip-content --force
wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" \
	--skip-check --force --extra-php <<'PHP'
define( 'DISABLE_WP_CRON', true );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
PHP
wp core install --url="$SITE_URL" --title="Pergel CI" --admin_user=admin \
	--admin_password="$(head -c 18 /dev/urandom | base64)" --admin_email=ci@example.com --skip-email
echo "::endgroup::"

echo "::group::Pergel theme, engine and probe"
mkdir -p "$WP_DIR/wp-content/themes" "$WP_DIR/wp-content/plugins" "$WP_DIR/wp-content/mu-plugins"
if [ "$LINK_MODE" = copy ]; then
	cp -r "$REPO/theme" "$WP_DIR/wp-content/themes/pergel"
	cp -r "$REPO/engine" "$WP_DIR/wp-content/plugins/pergel-engine"
else
	ln -s "$REPO/theme" "$WP_DIR/wp-content/themes/pergel"
	ln -s "$REPO/engine" "$WP_DIR/wp-content/plugins/pergel-engine"
fi
cat > "$WP_DIR/wp-content/mu-plugins/pergel-perf-probe.php" <<PHP
<?php
// Pergel CI: performance probe from the repo (tools/ci/setup-wp.sh).
require '$REPO_PHP/tools/budget/perf-probe.php';
PHP
# Admin pages would otherwise ask api.wordpress.org for updates on every
# visit while the update transients are empty, which slows the single-threaded
# php -S and makes CI depend on the network.
cat > "$WP_DIR/wp-content/mu-plugins/pergel-ci-offline.php" <<'PHP'
<?php
// Pergel CI: no update checks from admin pages (tools/ci/setup-wp.sh).
remove_action( 'admin_init', '_maybe_update_core' );
remove_action( 'admin_init', '_maybe_update_plugins' );
remove_action( 'admin_init', '_maybe_update_themes' );
PHP
# One line when a web request starts and one when it ends, so a request that
# hangs or kills php -S can be named from the CI artifacts.
cat > "$WP_DIR/wp-content/mu-plugins/pergel-ci-requests.php" <<'PHP'
<?php
// Pergel CI: request log in /tmp/pergel-requests.log (tools/ci/setup-wp.sh).
if ( PHP_SAPI === 'cli-server' ) {
	$pergel_ci_line = static function ( string $what ): void {
		$headers = array();
		foreach ( array( 'PATTERN', 'VARIATION', 'HEADER', 'FOOTER', 'PERF' ) as $name ) {
			if ( ! empty( $_SERVER[ "HTTP_X_PERGEL_$name" ] ) ) {
				$headers[] = strtolower( $name ) . '=' . $_SERVER[ "HTTP_X_PERGEL_$name" ];
			}
		}
		$ms = round( ( microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'] ) * 1000 );
		file_put_contents(
			'/tmp/pergel-requests.log',
			sprintf( "%s %s %dms pid=%d %s %s %s
", gmdate( 'H:i:s' ), $what, $ms, getmypid(), $_SERVER['REQUEST_METHOD'] ?? '', $_SERVER['REQUEST_URI'] ?? '', implode( ' ', $headers ) ),
			FILE_APPEND
		);
	};
	$pergel_ci_line( 'start' );
	register_shutdown_function( $pergel_ci_line, 'end  ' );
}
PHP
wp theme activate pergel
wp plugin activate pergel-engine
MSYS_NO_PATHCONV=1 wp rewrite structure '/%postname%/' # keep Git Bash from rewriting the argument
wp eval-file "$REPO_PHP/tools/fixtures/seed.php"
wp pergel info
echo "::endgroup::"
