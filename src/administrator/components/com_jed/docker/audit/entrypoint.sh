#!/bin/sh
#
# Entrypoint for the jed-audit sandbox image. Runs with --network none: everything
# it needs (jorobo, phpstan, a Joomla core copy) was baked into the image at build
# time. Reads the extension zip from /audit/input (read-only bind mount) and writes
# the phpstan report plus the extension source back to /audit/output (read-write
# bind mount) for the host to pick up.
set -eu

WORK=/audit/work
mkdir -p "$WORK"
cd "$WORK"

# 1. Scaffold a jorobo project; this is what creates the src/ folder we unzip into.
cp -r /opt/jed-audit/vendor "$WORK/vendor"
cp /opt/jed-audit/composer.json "$WORK/composer.json"
# jorobo may prompt interactively on first run in some versions; redirect stdin
# from /dev/null so it falls back to defaults instead of hanging the container.
vendor/bin/jorobo init < /dev/null

# 2. Unzip the extension under test into src/.
mkdir -p src
unzip -q /audit/input/extension.zip -d src/

# 3. Unzip the baked-in latest stable Joomla core package into joomla/.
mkdir -p joomla
unzip -q /opt/joomla-latest.zip -d joomla/

# 4. Run phpstan against the extension source. Findings are the expected output -
#    never fail the container on phpstan's own findings-exit-code.
set +e
vendor/bin/phpstan analyse src/ --configuration=/opt/jed-audit/phpstan.neon.dist --error-format=table > /audit/output/phpstan.txt
vendor/bin/phpstan analyse src/ --configuration=/opt/jed-audit/phpstan.neon.dist --error-format=json > /audit/output/phpstan.json
set -e

# 5. Copy the extension source back out so the host can run the Claude review
#    (the container itself has no network access to call the Anthropic API).
cp -r src /audit/output/src

exit 0
