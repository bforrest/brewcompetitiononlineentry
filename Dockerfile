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
RUN pecl install opentelemetry \
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
