#!/usr/bin/env bash
# Requests each path on the CI server and fails unless it answers 200; the
# paths after --missing must answer 404.
#
#   tools/ci/smoke.sh / /blog/ '/?s=pergel' --missing /pergel-budget-missing/
#
# Needs SITE_URL. php -S segfaults now and then on a request that works
# (tools/ci/serve.sh starts it again); curl then gets no answer (000), so a
# path is asked once more after the server is back. A crash that repeats
# still fails.
set -u
expect=200
failed=0
for path in "$@"; do
	if [ "$path" = --missing ]; then expect=404; continue; fi
	code=$(curl -s -o /dev/null -w '%{http_code}' "$SITE_URL$path")
	if [ "$code" = 000 ]; then
		echo "000 $path (no answer; asking again once the server is back)"
		for i in $(seq 1 30); do curl -s -o /dev/null "$SITE_URL/wp-includes/images/blank.gif" && break; sleep 1; done
		code=$(curl -s -o /dev/null -w '%{http_code}' "$SITE_URL$path")
	fi
	echo "$code $path"
	[ "$code" = "$expect" ] || failed=1
done
exit "$failed"
