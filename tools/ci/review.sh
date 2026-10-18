#!/usr/bin/env bash
# Plugin Check and Theme Check on what the zips hold.
#
#   tools/ci/review.sh              (after tools/ci/setup-wp.sh)
#
# The engine and theme come from `git archive HEAD`, so the files that
# .gitattributes leaves out (tests, .gitkeep) are not checked and uncommitted
# changes are not either. Both tools exit 0 with findings; this script fails
# on any Plugin Check finding and on Theme Check's REQUIRED and WARNING lines,
# apart from the ones accepted in tools/ci/review.php.
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WP_DIR="${WP_DIR:-/tmp/pergel-wp}"
DIST="${DIST:-/tmp/pergel-dist}"
PLUGIN_CHECK="${PLUGIN_CHECK:-2.1.0}"
THEME_CHECK="${THEME_CHECK:-20260901}"
WP_PHP="$WP_DIR"
if command -v cygpath > /dev/null; then WP_PHP="$(cygpath -m "$WP_DIR")"; fi

wp() { command wp --path="$WP_PHP" "$@"; }
retry() { for i in 1 2 3; do "$@" && return 0; echo "Retrying in 15 s: $*"; sleep 15; done; return 1; }

echo "::group::Engine and theme as zipped"
rm -rf "$DIST"
mkdir -p "$DIST"
git -C "$REPO" archive HEAD engine theme | tar -x -C "$DIST"
rm -rf "$WP_DIR/wp-content/plugins/pergel-engine" "$WP_DIR/wp-content/themes/pergel"
cp -r "$DIST/engine" "$WP_DIR/wp-content/plugins/pergel-engine"
cp -r "$DIST/theme" "$WP_DIR/wp-content/themes/pergel"
retry wp plugin install plugin-check --version="$PLUGIN_CHECK" --activate
retry wp plugin install theme-check --version="$THEME_CHECK" --activate
echo "::endgroup::"

status=0

echo "::group::Plugin Check $PLUGIN_CHECK"
# plugin_updater_detected: the Update URI header is deliberate; it keeps
# WordPress.org from offering another plugin's update for this slug.
found="$(wp plugin check pergel-engine --format=csv --fields=file,line,type,code,message --ignore-codes=plugin_updater_detected)"
printf '%s\n' "$found"
echo "::endgroup::"
if printf '%s\n' "$found" | grep -q -v -e '^$' -e '^FILE: ' -e '^file,line,'; then
	echo "::error::Plugin Check found problems in the engine (listed above)."
	status=1
fi

echo "::group::Theme Check $THEME_CHECK"
wp theme-check run pergel --format=json > "$DIST/theme-check.json"
echo "::endgroup::"
php "$REPO/tools/ci/review.php" "$DIST/theme-check.json" || status=1

exit "$status"
