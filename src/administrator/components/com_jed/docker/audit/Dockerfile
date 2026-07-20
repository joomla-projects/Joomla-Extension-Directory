# Sandbox image for auditing third-party JED extensions: unzips untrusted
# extension code against a jorobo-scaffolded Joomla install, runs phpstan, and
# hands the source back out for a host-side Claude security review. Run with
# `docker run --rm --network none` (see DockerRunner) - everything below is baked
# in at build time because the container gets no runtime network access.
#
# Rebuild periodically to pick up newer Joomla core releases and tooling versions.
FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        unzip \
        zip \
        git \
        curl \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /opt/jed-audit

COPY tooling-composer.json composer.json
RUN composer install --no-dev --no-interaction --optimize-autoloader

# Bake in the latest stable Joomla core package. GitHub's "latest release" API
# always resolves to the current stable tag, so this stays correct as long as the
# image is rebuilt periodically.
RUN JOOMLA_ASSET_URL=$(curl -fsSL https://api.github.com/repos/joomla/joomla-cms/releases/latest \
        | grep -o '"browser_download_url": *"[^"]*Stable-Full_Package\.zip"' \
        | head -n1 \
        | sed -E 's/.*"(https[^"]+)"/\1/') \
    && test -n "$JOOMLA_ASSET_URL" \
    && curl -fsSL "$JOOMLA_ASSET_URL" -o /opt/joomla-latest.zip

COPY phpstan.neon.dist /opt/jed-audit/phpstan.neon.dist
COPY entrypoint.sh /opt/jed-audit/entrypoint.sh
RUN chmod +x /opt/jed-audit/entrypoint.sh

ENTRYPOINT ["/opt/jed-audit/entrypoint.sh"]
