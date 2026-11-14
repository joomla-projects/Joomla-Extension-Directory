#!/usr/bin/env bash
#
# Rough load check for com_apps with ApacheBench (ab). Usage:
#
#   tests/load/com_apps.sh http://localhost:8080/jed_j6 [requests] [concurrency]
#
# Prints requests per second and latency for the four kinds of request, first with a cold origin
# cache (the cache directory is NOT cleared here: clear it from System > Clear Cache, or the
# first request of each kind is simply the cold one) and then repeated warm. Run it against a
# copy of the site, never against production.

set -euo pipefail

BASE="${1:?site URL, e.g. http://localhost:8080/jed_j6}"
N="${2:-400}"
C="${3:-20}"
Q="index.php?format=json&option=com_apps&release=6.0&filter_version=all"

for view in "view=dashboard" "view=dashboard&ordering=score" "view=category&id=33" "view=extension&id=1586" "view=dashboard&filter_search=backup"; do
    url="$BASE/$Q&$view"
    curl -s -o /dev/null "$url"
    printf '%-45s ' "$view"
    ab -q -n "$N" -c "$C" -H 'Accept-Encoding: gzip' "$url" 2>&1 \
        | awk '/Requests per second/ {rps=$4} /Failed requests/ {failed=$3} /99%/ {p99=$2} END {printf "%8s req/s  p99 %4s ms  failed %s\n", rps, p99, failed}'
done
