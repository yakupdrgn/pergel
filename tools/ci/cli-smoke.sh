#!/usr/bin/env bash
# Runs every `wp pergel settings` and `wp pergel modules` command against a
# site set up like tools/ci/setup-wp.sh and checks output and exit codes.
#
#   tools/ci/cli-smoke.sh
#
# pergel_settings and the Site Logo are restored at the end.
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WP_DIR="${WP_DIR:-/tmp/pergel-wp}"
REPO_PHP="$REPO"
WP_PHP="$WP_DIR"
if command -v cygpath > /dev/null; then REPO_PHP="$(cygpath -m "$REPO")"; WP_PHP="$(cygpath -m "$WP_DIR")"; fi

wp() { command wp --path="$WP_PHP" "$@"; }

work="$(mktemp -d)"
work_php="$work"
if command -v cygpath > /dev/null; then work_php="$(cygpath -m "$work")"; fi
wp option get pergel_settings --format=json > "$work/backup.json"
logo=""
svg=""
previous_logo="$(wp option get site_logo 2> /dev/null || true)"
restore() {
	[ -z "$logo" ] || wp eval-file "$REPO_PHP/tools/seo/logo.php" remove "$logo" "$previous_logo"
	[ -z "$svg" ] || wp post delete "$svg" --force --quiet
	wp option delete pergel_settings --quiet
	wp option add pergel_settings --format=json --autoload=yes < "$work/backup.json" > /dev/null
	rm -rf "$work"
}
trap restore EXIT

failed=0
# expect <exit code> <text the output must contain> <command ...>
expect() {
	local code="$1" text="$2" out status=0
	shift 2
	out="$("$@" 2>&1)" || status=$?
	if [ "$status" = "$code" ] && [[ "$out" == *"$text"* ]]; then
		echo "ok    $*"
	else
		echo "FAIL  $* (exit $status, want $code; want text: $text)"
		echo "$out" | sed 's/^/      /'
		failed=1
	fi
}

echo "::group::wp pergel settings get / set"
expect 0 '"brand"' wp pergel settings get
expect 0 '"social"' wp pergel settings get brand
expect 0 'performance.preload_fonts = true' wp pergel settings set performance.preload_fonts yes
expect 0 'true' wp pergel settings get performance.preload_fonts
expect 1 'Unknown setting: nope' wp pergel settings get nope
expect 1 'Unknown setting: brand.nope' wp pergel settings get brand.nope
expect 0 'brand.phone = "+90 212 000 00 00"' wp pergel settings set brand.phone '+90 212 000 00 00'
expect 0 '+90 212 000 00 00' wp pergel settings get brand.phone
expect 0 '"+90 212 000 00 00"' wp pergel settings get brand.phone --format=json
expect 0 'brand.country = "TR"' wp pergel settings set brand.country tr
expect 0 'performance.preload_fonts = false' wp pergel settings set performance.preload_fonts off
expect 0 'brand.social = ["https://a.example/","https://b.example/"]' wp pergel settings set brand.social $'https://a.example/\nhttps://b.example/'
expect 0 'brand.social = ["https://c.example/"]' wp pergel settings set brand.social '["https://c.example/"]' --format=json
expect 1 'Enter a valid email address' wp pergel settings set brand.email nope
expect 1 'wrong type' wp pergel settings set performance.preload_fonts maybe
expect 1 'Invalid JSON' wp pergel settings set brand.social '[nope' --format=json
expect 1 'Unknown setting: brand' wp pergel settings set brand x
expect 1 'not an image on this site' wp pergel settings set brand.logo_inverse 999999
svg="$(wp eval-file "$REPO_PHP/tools/seo/logo.php" svg)"
expect 0 "brand.logo_inverse = $svg" wp pergel settings set brand.logo_inverse "$svg"
expect 1 'Social networks do not show SVG images' wp pergel settings set seo.og_image "$svg"
expect 0 'is an SVG' wp pergel settings import - <<< "{\"seo\":{\"og_image\":$svg}}"
expect 0 '0' wp pergel settings get seo.og_image
logo="$(wp eval-file "$REPO_PHP/tools/seo/logo.php" add)"
expect 0 "brand.logo_inverse = $logo" wp pergel settings set brand.logo_inverse "$logo"
expect 0 '' wp pergel settings get brand.email
echo "::endgroup::"

echo "::group::wp pergel settings export / import"
expect 0 'written to' wp pergel settings export "$work_php/export.json"
wp pergel settings export > "$work/stdout.json"
cmp -s "$work/export.json" "$work/stdout.json" && echo "ok    export file = export stdout" || { echo "FAIL  export file differs from stdout"; failed=1; }
printf '%s' '{"brand":{"name":"Örnek A.Ş.","logo_inverse":999999}}' > "$work/part.json"
expect 0 'Dry run: 2 field(s) would change' wp pergel settings import "$work_php/part.json" --dry-run
expect 0 '"Örnek A.Ş."' wp pergel settings import "$work_php/part.json" --dry-run
expect 0 '' wp pergel settings get brand.name
expect 0 '2 field(s) changed' wp pergel settings import "$work_php/part.json"
expect 0 'Örnek A.Ş.' wp pergel settings get brand.name
expect 0 '0' wp pergel settings get brand.logo_inverse
expect 0 'brand.name: "Örnek A.Ş." → ' wp pergel settings import "$work_php/export.json"
expect 0 "$logo" wp pergel settings get brand.logo_inverse
expect 1 '2 invalid field(s). Nothing was saved' wp pergel settings import - <<< '{"brand":{"email":"x","phone":"x","name":"Changed"}}'
expect 1 'Unknown settings: brand.nam, foo' wp pergel settings import - <<< '{"brand":{"nam":"x"},"foo":{}}'
expect 1 'JSON object of setting groups' wp pergel settings import - <<< '[1,2]'
expect 1 'Invalid JSON' wp pergel settings import - <<< '{'
expect 1 'Cannot read' wp pergel settings import "$work_php/missing.json"
expect 0 '' wp pergel settings get brand.name
echo "::endgroup::"

echo "::group::wp pergel modules"
expect 0 'performance' wp pergel modules list
expect 0 '"module":"cli","switch":"always","loaded":"yes"' wp pergel modules list --format=json
expect 0 'performance, seo disabled' wp pergel modules disable performance seo
expect 0 '"module":"seo","switch":"off","loaded":"no"' wp pergel modules list --format=json
expect 0 'false' wp pergel settings get modules.performance
expect 0 'performance, seo enabled' wp pergel modules enable performance seo
expect 0 '"module":"seo","switch":"on","loaded":"yes"' wp pergel modules list --format=json
expect 1 'No switch for: cli, nope' wp pergel modules disable cli nope
echo "::endgroup::"

exit "$failed"
