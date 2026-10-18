#!/usr/bin/env bash
# Fails the job when WordPress wrote to debug.log (CI "PHP notices" steps).
#
# WP-CLI's own phar is not clean on newer PHP versions: php-cli-tools on 8.5,
# and Symfony Finder on 8.4 when WP-CLI cleans its download cache (one in 51
# commands that use the cache, e.g. `wp plugin install`; a job with many of
# them meets it often). Only its lines are left out; everything from
# WordPress, WooCommerce and Pergel still fails.
#
# Usage: WP_DIR=/tmp/pergel-wp bash tools/ci/notices.sh
set -euo pipefail

log="$WP_DIR/wp-content/debug.log"
if [ -s "$log" ] && grep -v 'phar://[^ ]*/wp-cli/' "$log" | grep -q .; then
	cat "$log"
	exit 1
fi
