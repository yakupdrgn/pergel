#!/usr/bin/env bash
# SEO matrix: Pergel alone, then with each free SEO plugin active in turn.
#
#   tools/seo/matrix.sh [scenario ...]     (default: all six)
#
# Scenarios: none rank-math yoast aioseo seopress tsf. Needs a site set up
# like tools/ci/setup-wp.sh (seeded content, pretty permalinks) and wp-cli.
# INSTALL=1 installs the plugins from wordpress.org first (CI); locally they
# must already be in wp-content/plugins. Each plugin is deactivated again
# after its run; pergel_settings and the Site Logo are restored at the end.
# Options and tables the plugins create stay, as they do on any site.
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WP_DIR="${WP_DIR:-/tmp/pergel-wp}"
SITE_URL="${SITE_URL:-http://127.0.0.1:8080}"
INSTALL="${INSTALL:-0}"
REPO_PHP="$REPO"
WP_PHP="$WP_DIR"
if command -v cygpath > /dev/null; then REPO_PHP="$(cygpath -m "$REPO")"; WP_PHP="$(cygpath -m "$WP_DIR")"; fi

wp() { command wp --path="$WP_PHP" "$@"; }

declare -A SLUG=(
	[rank-math]=seo-by-rank-math
	[yoast]=wordpress-seo
	[aioseo]=all-in-one-seo-pack
	[seopress]=wp-seopress
	[tsf]=autodescription
)
SCENARIOS=("$@")
[ ${#SCENARIOS[@]} -gt 0 ] || SCENARIOS=(none rank-math yoast aioseo seopress tsf)

PHONE='+90 212 555 00 00'
SOCIAL='https://www.linkedin.com/company/pergel-matrix'

if [ "$INSTALL" = 1 ]; then
	wp plugin install "${SLUG[@]}" --quiet
fi

backup="$(mktemp)"
wp option get pergel_settings --format=json > "$backup"
active=""
logo=""
previous_logo="$(wp option get site_logo 2> /dev/null || true)"
restore() {
	[ -z "$active" ] || wp plugin deactivate "$active" --quiet || true
	[ -z "$logo" ] || wp eval-file "$REPO_PHP/tools/seo/logo.php" remove "$logo" "$previous_logo"
	wp option delete pergel_settings --quiet
	wp option add pergel_settings --format=json --autoload=yes < "$backup" > /dev/null
	wp rewrite flush --quiet
	rm -f "$backup"
}
trap restore EXIT

# A company site has a logo; Yoast SEO prints no Organization without one.
logo="$(wp eval-file "$REPO_PHP/tools/seo/logo.php" add)"
# Brand details every Organization node must carry, from Pergel or a plugin.
wp eval "Pergel\\Settings\\Settings::update( array( 'brand' => array( 'phone' => '$PHONE', 'social' => array( '$SOCIAL' ) ) ) );"

# Settings a company site would make: the site represents an organization.
setup() {
	case "$1" in
		rank-math)
			# The wizard's "Skip" (no account): Rank Math stays off on the front end until then.
			wp option update rank_math_registration_skip 1 --quiet
			# The wizard asks whether the site is a person or a company; it defaults to person.
			wp eval '$o = (array) get_option( "rank-math-options-titles", array() ); $o["knowledgegraph_type"] = "company"; update_option( "rank-math-options-titles", $o );'
			;;
		seopress)
			# SEOPress prints no Organization until the knowledge graph type is set.
			wp eval '$o = (array) get_option( "seopress_social_option_name", array() ); $o["seopress_social_knowledge_type"] = "Organization"; update_option( "seopress_social_option_name", $o );'
			;;
	esac
}

failed=()
for scenario in "${SCENARIOS[@]}"; do
	echo "::group::SEO matrix: $scenario"
	if [ "$scenario" != none ]; then
		active="${SLUG[$scenario]}"
		wp plugin activate "$active" --quiet
		setup "$scenario"
	fi
	wp rewrite flush --quiet
	node "$REPO/tools/seo/check.mjs" --base "$SITE_URL" --scenario "$scenario" --phone "$PHONE" --social "$SOCIAL" || failed+=("$scenario")
	if [ -n "$active" ]; then
		wp plugin deactivate "$active" --quiet
		active=""
	fi
	echo "::endgroup::"
done

if [ ${#failed[@]} -gt 0 ]; then
	echo "SEO matrix failed: ${failed[*]}"
	exit 1
fi
echo "SEO matrix clean: ${SCENARIOS[*]}"
