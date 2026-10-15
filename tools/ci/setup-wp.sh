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

echo "::group::WordPress $WP_VERSION in $WP_DIR"
rm -rf "$WP_DIR"
mkdir -p "$WP_DIR"
wp core download --version="$WP_VERSION" --skip-content --force
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
# visit while the update transients are empty; with slow DNS on the runner
# that holds up the single-threaded php -S and the block editor check.
cat > "$WP_DIR/wp-content/mu-plugins/pergel-ci-offline.php" <<'PHP'
<?php
// Pergel CI: no update checks from admin pages (tools/ci/setup-wp.sh).
remove_action( 'admin_init', '_maybe_update_core' );
remove_action( 'admin_init', '_maybe_update_plugins' );
remove_action( 'admin_init', '_maybe_update_themes' );
PHP
wp theme activate pergel
wp plugin activate pergel-engine
MSYS_NO_PATHCONV=1 wp rewrite structure '/%postname%/' # keep Git Bash from rewriting the argument
wp eval-file "$REPO_PHP/tools/fixtures/seed.php"
wp pergel info
echo "::endgroup::"
