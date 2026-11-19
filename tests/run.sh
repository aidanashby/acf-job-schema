#!/usr/bin/env bash
# Run every test against a local WordPress Studio site with ACF active.
#
#   AJS_SITE=<Studio site folder> bash tests/run.sh            deploy, build fixtures, run all checks
#   AJS_SITE=<Studio site folder> bash tests/run.sh teardown   remove fixtures and test post types
#
# Studio's PHP can only see files inside the site folder, so each script is copied to the site root,
# run with `studio wp eval-file`, then deleted.
set -euo pipefail

: "${AJS_SITE:?Set AJS_SITE to the Studio site folder}"
here=$(cd "$(dirname "$0")" && pwd)
repo=$(dirname "$here")
scratch=$(mktemp -d)
trap 'rm -rf "$scratch"; rm -f "$AJS_SITE"/ajs-*.php' EXIT

studio() { (cd "$AJS_SITE" && cmd.exe //c "studio $*" | tr -d '\r'); }
run_php() { cp "$here/fixtures/$1.php" "$AJS_SITE/ajs-$1.php"; studio wp eval-file "ajs-$1.php"; }

if [ "${1:-}" = teardown ]; then
	cp "$here/fixtures/teardown.php" "$AJS_SITE/ajs-teardown.php"
	studio wp eval-file ajs-teardown.php
	rm -f "$AJS_SITE/wp-content/mu-plugins/ajs-test-types.php"
	studio wp rewrite flush
	exit 0
fi

echo "== Builder (plain PHP)"
php "$here/build-test.php"

echo "== Deploy plugin and test post types"
rm -rf "$AJS_SITE/wp-content/plugins/acf-job-schema"
cp -r "$repo/acf-job-schema" "$AJS_SITE/wp-content/plugins/acf-job-schema"
cp "$here/fixtures/mu-test-types.php" "$AJS_SITE/wp-content/mu-plugins/ajs-test-types.php"
studio wp plugin activate acf-job-schema

echo "== Fixtures"
cp "$here/fixtures/teardown.php" "$AJS_SITE/ajs-teardown.php"
run_php setup | tee "$scratch/setup.log" | grep -v '^MANIFEST:' || true
grep '^MANIFEST:' "$scratch/setup.log" | sed 's/^MANIFEST://' > "$scratch/manifest.json"
[ -s "$scratch/manifest.json" ] || { echo "FAIL: setup printed no manifest"; exit 1; }

echo "== WordPress checks"
run_php check-wp | tee "$scratch/wp.log"
! grep -q '^FAIL' "$scratch/wp.log"

echo "== Front end"
python "$here/check-front-end.py" "$scratch/manifest.json"
