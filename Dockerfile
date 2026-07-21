FROM composer:2 AS composer

FROM php:8.2-apache

# Enable required Apache modules ('env' lets vhost.conf's SetEnv directives
# below expose the OTEL_* config to PHP via getenv() - Task 12)
RUN a2enmod rewrite headers env

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
  libfreetype6-dev \
  libjpeg62-turbo-dev \
  libpng-dev \
  libzip-dev \
  libicu-dev \
  libonig-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) \
  mysqli \
  pdo_mysql \
  mbstring \
  gd \
  zip \
  intl \
  exif \
  opcache \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

# OpenTelemetry native extension (Task 12). This is what makes zero-code
# auto-instrumentation possible at all in PHP (no bytecode-weaving VM like
# the JVM's) - installing it is what lets open-telemetry/opentelemetry-auto-mysqli
# (composer, see composer.json) hook every legacy mysqli_query() call with no
# changes anywhere in sections/admin/lib/. Installing it here, not just
# requiring the composer packages, is the whole point: without the extension
# loaded, the auto-* packages' own _register.php bootstraps detect its
# absence and silently no-op (see their own trigger_error() guard) - which is
# also exactly the fallback behavior a shared-hosting deploy gets for free by
# simply not having this extension installed (see docker/apache/vhost.conf's
# OTEL_* env vars, which are Docker-dev-only).
#
# Verified locally: builds in ~6s (a small C extension, no grpc/protobuf
# native deps needed - the OTLP exporter is configured for the http/protobuf
# transport, not grpc, specifically to avoid needing to also compile the much
# heavier grpc PECL extension here).
#
# Pinned to 1.2.1 (Task 12 review fix round 1) - MUST match composer.json's
# config.platform.ext-opentelemetry value exactly. Before this fix, this line
# installed unpinned `pecl install opentelemetry` (always resolves whatever is
# "latest" at build time) while composer.json separately claimed "1.2.1" for
# dependency-resolution purposes - the two had no real relationship and could
# silently drift the moment a new extension release shipped. 1.2.1 is what
# was actually installed and running when this pin was added (confirmed via
# `docker compose exec web php -r 'echo phpversion("opentelemetry");'`).
# Bump both together, deliberately, when upgrading.
RUN pecl install opentelemetry-1.2.1 \
  && docker-php-ext-enable opentelemetry

# Use custom Apache vhost
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Set recommended PHP settings
RUN { \
  echo 'upload_max_filesize = 20M'; \
  echo 'post_max_size = 20M'; \
  echo 'memory_limit = 256M'; \
  echo 'max_execution_time = 60'; \
  echo 'display_errors = Off'; \
  echo 'log_errors = On'; \
  } > /usr/local/etc/php/conf.d/bcoem.ini

WORKDIR /var/www/html

# Task 13: bake the app + its production-only dependencies into the image
# itself. Previously this image shipped NO application code at all - local
# dev only ever worked because docker-compose.yml bind-mounts the checked-out
# repo (`.:/var/www/html`) over this directory at container start, supplying
# both the code and vendor/ from the host. That's fine for local dev but
# means a `docker build`+`docker push`ed copy of this image (see the `build`
# job in .github/workflows/ci.yml) would be empty and unrunnable on its own.
# COPY-ing the source and running composer install here fixes that for a
# standalone `docker run`, while changing nothing about local dev: the bind
# mount still shadows everything below at container start, exactly as
# before this task. .dockerignore keeps vendor/tests/e2e/.git/etc. out of
# the build context, so this always installs a clean, lock-consistent,
# no-dev vendor/ regardless of whatever partial tree exists on the host
# (see the CI workflow's own comment on the committed vendor/ being partial).
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Task 13: run `vendor/bin/phinx migrate` before Apache starts. The base
# image's own entrypoint (docker-php-entrypoint -> apache2-foreground) is
# preserved unmodified and still invoked at the end of this script - see
# docker/entrypoint.sh's own header comment for why it's structured this
# way rather than replacing that behavior outright.
COPY docker/entrypoint.sh /usr/local/bin/bcoem-entrypoint.sh
RUN chmod +x /usr/local/bin/bcoem-entrypoint.sh
ENTRYPOINT ["bcoem-entrypoint.sh"]
CMD ["apache2-foreground"]
