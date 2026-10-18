#!/usr/bin/env bash
# Runs every `wp pergel settings`, `wp pergel modules`, `wp pergel search`,
# `wp pergel starter`, `wp pergel package`, `wp pergel pattern`,
# `wp pergel import` and `wp pergel export` command against a site set up
# like tools/ci/setup-wp.sh and checks output and exit codes.
#
#   tools/ci/cli-smoke.sh
#
# pergel_settings, the Site Logo, the reading settings, the site title,
# tagline and theme are restored at the end, and what the import made is
# deleted. The starter is installed and removed again, so do not run this on a
# site where the corporate starter is in use.
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
reading="$(wp eval 'echo implode( " ", array( get_option( "show_on_front" ), (int) get_option( "page_on_front" ), (int) get_option( "page_for_posts" ) ) );')"
blogname="$(wp option get blogname)"
blogdescription="$(wp option get blogdescription)"
theme="$(wp eval 'echo get_stylesheet();')"
smoke_package=pergel-smoke-paket
restore() {
	wp theme activate "$theme" --quiet
	rm -rf "$(wp eval 'echo get_theme_root();')/$smoke_package"
	local made
	made="$(wp post list --post_type=page,attachment,wp_navigation --post_status=any,trash --meta_key=_pergel_package --meta_value="$smoke_package" --field=ID --format=ids)"
	[ -z "$made" ] || wp post delete $made --force --quiet
	wp option update blogname "$blogname" --quiet
	wp option update blogdescription "$blogdescription" --quiet
	[ -z "$logo" ] || wp eval-file "$REPO_PHP/tools/seo/logo.php" remove "$logo" "$previous_logo"
	[ -z "$svg" ] || wp post delete "$svg" --force --quiet
	wp option delete pergel_settings --quiet
	wp option add pergel_settings --format=json --autoload=yes < "$work/backup.json" > /dev/null
	read -r show front posts <<< "$reading"
	wp option update show_on_front "$show" --quiet
	wp option update page_on_front "$front" --quiet
	wp option update page_for_posts "$posts" --quiet
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

echo "::group::wp pergel search"
expect 0 'Index rebuilt' wp pergel search reindex
expect 0 '{"key":"ready","value":"yes"}' wp pergel search status --format=json
expect 0 'Pergel sample post' wp pergel search query 'PERGEL sample'
expect 0 'Results: 0' wp pergel search query 'pergelyokboyle'
# Switching the module back on starts a rebuild (tests/integration/SearchTest.php);
# WP-Cron may finish it before the next command, so only the result is checked.
expect 0 'search disabled' wp pergel modules disable search
expect 0 'search enabled' wp pergel modules enable search
expect 0 'Index rebuilt' wp pergel search reindex
expect 0 'Pergel sample post' wp pergel search query 'sample'
echo "::endgroup::"

# By path, so it also works when setup-wp.sh copies the plugin (LINK_MODE=copy).
echo "::group::wp pergel starter"
starter="$REPO_PHP/starters/corporate"
expect 0 'Dry run, nothing was saved. Starter corporate: 10 create, 1 keep, 2 set' wp pergel starter install "$starter" --dry-run
expect 0 'Starter corporate: 10 create, 1 keep, 2 set.' wp pergel starter install "$starter"
expect 0 'Starter corporate: 13 keep.' wp pergel starter install "$starter"
expect 0 '"action":"reset","type":"page","key":"home"' wp pergel starter install "$starter" --reset --format=json
expect 0 'page-landing' wp post list --post_type=page --meta_key=_pergel_starter_key --meta_value=about --fields=page_template --format=csv
expect 0 'Dry run, nothing was saved. Starter corporate: 10 delete, 3 set.' wp pergel starter remove corporate --dry-run
expect 0 'Starter corporate: 10 delete, 3 set.' wp pergel starter remove corporate --yes
expect 0 'posts' wp option get show_on_front
expect 0 'nothing to do' wp pergel starter remove corporate --yes
expect 1 'starter.json not found' wp pergel starter install "$work_php/missing"
expect 1 'starter.json not found' wp pergel starter install nope
echo "::endgroup::"

echo "::group::wp pergel package / pattern"
package="$REPO_PHP/tests/fixtures/package"
expect 0 'Package ornek-firma: 5 pages, 2 content files, 2 images; 0 problems, 0 notes.' wp pergel package validate "$package" --strict
expect 0 '"problems": []' wp pergel package validate "$package" --format=json
cp -r "$REPO/tests/fixtures/package" "$work/package"
printf 'Eski.
' > "$work/package/content/eski.md"
expect 0 'note: content/eski.md: no page in sitemap.yaml uses this file' wp pergel package validate "$work_php/package"
expect 1 'problem: content/eski.md: no page in sitemap.yaml uses this file' wp pergel package validate "$work_php/package" --strict
expect 1 'not a directory' wp pergel package validate "$work_php/missing"
expect 0 'hero-split' wp pergel pattern list
expect 0 'faq-accordion' wp pergel pattern list --category=pergel-faq --format=csv
expect 0 '- pattern: hero-split' wp pergel pattern fields hero-split
expect 0 '"type": "items"' wp pergel pattern fields pergel/faq-accordion --format=json
expect 1 '"pergel/nope" is not a Pergel pattern' wp pergel pattern fields nope
echo "::endgroup::"

echo "::group::wp pergel import"
if ! wp plugin is-active woocommerce 2> /dev/null; then
	expect 1 'type: a cart page needs WooCommerce' wp pergel import "$package"
fi
# The fixture under its own name and addresses, without the cart page.
cp -r "$REPO/tests/fixtures/package" "$work/import"
sed -i "s/^name: ornek-firma/name: $smoke_package/; s/^locale: tr_TR/locale: en_US/" "$work/import/package.yaml"
cat > "$work/import/sitemap.yaml" << 'YAML'
pages:
  - slug: ""
    title: Ana sayfa
    content: content/home.md
  - slug: pergel-smoke-hakkimizda
    title: Hakkımızda
    content: content/about.md
    children:
      - slug: ekip
        title: Ekibimiz
        template: page-landing
YAML
expect 0 'Dry run, nothing was saved. Package pergel-smoke-paket:' wp pergel import "$work_php/import" --dry-run
expect 0 'none' wp eval 'echo get_page_by_path( "pergel-smoke-hakkimizda" ) ? "found" : "none";'
expect 0 'Success: Package pergel-smoke-paket: 7 create' wp pergel import "$work_php/import"
expect 0 "$smoke_package" wp eval 'echo get_stylesheet();'
expect 0 'Ekibimiz' wp eval 'echo get_the_title( get_page_by_path( "pergel-smoke-hakkimizda/ekip" ) );'
expect 0 'Success: Package pergel-smoke-paket: 16 keep;' wp pergel import "$work_php/import"
sed -i '/children:/,$d' "$work/import/sitemap.yaml"
expect 0 '1 stale' wp pergel import "$work_php/import"
expect 0 '1 remove' wp pergel import "$work_php/import" --prune
expect 0 'Success: Package pergel-smoke-paket: 15 keep;' wp pergel import "$work_php/import"
expect 1 'not a directory' wp pergel import "$work_php/missing"
echo "::endgroup::"

echo "::group::wp pergel export"
# Imports a package and fails when the import would change anything.
import_unchanged() {
	local out
	out="$(wp pergel import "$1" 2>&1)" || { echo "$out"; return 1; }
	echo "$out"
	! grep -qE '^(create|update|replace|adopt|set|skip|stale|remove)\b' <<< "$out"
}
expect 0 'Dry run, nothing was written. Package pergel-smoke-paket:' wp pergel export "$work_php/export" --name="$smoke_package" --dry-run
expect 1 '' test -e "$work/export"
expect 0 'Success: Package pergel-smoke-paket:' wp pergel export "$work_php/export" --name="$smoke_package" --strict
expect 0 'pattern: hero-split' cat "$work/export/content/home.md"
expect 0 '"radius"' cat "$work/export/brand/tokens.json"
expect 0 'Success: Package pergel-smoke-paket:' import_unchanged "$work_php/export"
expect 0 ' keep; 0 notes.' wp pergel export "$work_php/export" --name="$smoke_package" --strict
rm "$work/export/content/home.md"
expect 0 'create	file	content/home.md' wp pergel export "$work_php/export" --name="$smoke_package"
expect 1 'holds no package.yaml' wp pergel export "$work_php" --name="$smoke_package"
expect 1 'holds the package pergel-smoke-paket' wp pergel export "$work_php/export" --name=baska-paket
echo "::endgroup::"

exit "$failed"
