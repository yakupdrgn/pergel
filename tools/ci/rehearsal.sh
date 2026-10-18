#!/usr/bin/env bash
# Builds the rehearsal site from a project package and checks that the build
# can be repeated (F5): the package is clean, the import makes everything in
# one go, a second import changes nothing and exporting the site gives the
# package back, file for file.
#
#   tools/ci/rehearsal.sh [package]   (default: examples/ornek-mimarlik)
#
# Run it on an empty site set up with SEED=0 tools/ci/setup-wp.sh; the import
# stops at a page it did not make. What the import made is left in place for
# the smoke and budget runs that follow.
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WP_DIR="${WP_DIR:-/tmp/pergel-wp}"
PACKAGE="${1:-$REPO/examples/ornek-mimarlik}"
WP_PHP="$WP_DIR"
PACKAGE_PHP="$PACKAGE"
if command -v cygpath > /dev/null; then WP_PHP="$(cygpath -m "$WP_DIR")"; PACKAGE_PHP="$(cygpath -m "$PACKAGE")"; fi

wp() { command wp --path="$WP_PHP" "$@"; }

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
work_php="$work"
if command -v cygpath > /dev/null; then work_php="$(cygpath -m "$work")"; fi

echo "::group::Validate"
wp pergel package validate "$PACKAGE_PHP" --strict
echo "::endgroup::"

echo "::group::First import"
wp pergel import "$PACKAGE_PHP" --strict
echo "::endgroup::"

echo "::group::Second import changes nothing"
out="$(wp pergel import "$PACKAGE_PHP" --strict)"
echo "$out"
if grep -qE '^(create|update|replace|adopt|set|skip|stale|remove)\b' <<< "$out"; then
	echo "::error::The second import of $(basename "$PACKAGE") changed the site; the package does not build the same site twice."
	exit 1
fi
echo "::endgroup::"

echo "::group::Export gives the package back"
# Export into a copy, so that files the reader ignores (brief.md, research/)
# stay and only what the export rewrites can differ.
cp -r "$PACKAGE" "$work/export"
wp pergel export "$work_php/export" --strict
if ! diff -r "$PACKAGE" "$work/export"; then
	echo "::error::Exporting the site does not give $(basename "$PACKAGE") back; adopt the export's output or fix the exporter."
	exit 1
fi
echo "::endgroup::"
