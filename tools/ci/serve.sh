#!/usr/bin/env bash
# Runs PHP's built-in server for CI and starts it again if it dies, so one
# crashed request fails one check instead of every check after it.
#
# Usage: bash tools/ci/serve.sh [php flags] > /tmp/php-server.log 2>&1 &
# Stop:  pkill -f tools/ci/serve.sh; pkill -f 'php.* -S 127.0.0.1:8080'
#
# Needs WP_DIR. Each exit is logged with its status (139 = segfault).
set -u
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
while :; do
	php "$@" -S 127.0.0.1:8080 -t "$WP_DIR" "$REPO/tools/ci/router.php"
	echo "[serve] php -S exited with status $? at $(date -u +%H:%M:%S); starting it again"
	sleep 1
done
