#!/usr/bin/env bash
#
# Joomla 3 cannot run on the PHP this project targets, so its Install from Web script is run
# in jsdom instead, with Joomla 3.10's own jQuery, jQuery Migrate and core.js (downloaded here
# from the joomla-cms 3.10.12 tag into vendor/, which is not committed).
#
#   tests/js/j3-client/run.sh http://localhost:8080/jed_j6/

set -euo pipefail

cd "$(dirname "$0")"

ENDPOINT="${1:?URL of a site with com_apps installed and sample data, e.g. http://localhost:8080/jed_j6/}"
TAG=3.10.12

mkdir -p vendor

for file in media/jui/js/jquery.min.js media/jui/js/jquery-migrate.min.js media/system/js/core.js; do
    target="vendor/$(basename "$file")"
    [ -s "$target" ] || curl -fsSL -o "$target" "https://raw.githubusercontent.com/joomla/joomla-cms/$TAG/$file"
done

[ -d node_modules/jsdom ] || npm install --silent --no-audit --no-fund

node harness.js "$ENDPOINT"
