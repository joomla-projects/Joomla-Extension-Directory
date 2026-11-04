#!/usr/bin/env bash
#
# OWASP ZAP baseline scan: spiders the site and runs ZAP's passive rules only. It sends no attack
# payloads, so it is safe to point at any test copy of the site.
#
#   tests/security/zap-baseline.sh http://host.docker.internal:8080/jed/ [output-dir]
#
# Needs Docker. From inside the container "localhost" is the container itself, so use
# host.docker.internal (Docker Desktop, OrbStack) or the host's address for a site on this machine.
set -eu

TARGET="${1:?usage: zap-baseline.sh <target-url> [output-dir]}"
OUT="${2:-$(pwd)/zap-baseline-report}"

mkdir -p "$OUT"
chmod 777 "$OUT"

# -m: spider minutes. -I: do not fail the run on findings (read the report). -a: include alpha rules.
docker run --rm -v "$OUT":/zap/wrk:rw ghcr.io/zaproxy/zaproxy:stable \
    zap-baseline.py -t "$TARGET" -J report.json -r report.html -w report.md -m 4 -I -a

echo "Report: $OUT/report.html"
