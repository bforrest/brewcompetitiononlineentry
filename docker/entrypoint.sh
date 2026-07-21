#!/bin/sh
set -e

# Task 13: run Phinx migrations before Apache starts, then hand off to the
# php:8.2-apache base image's own entrypoint (docker-php-entrypoint, still
# installed unmodified at /usr/local/bin/docker-php-entrypoint) so none of
# its behavior is lost - it still normalizes a bare "-D FOREGROUND"-style
# CMD into "apache2-foreground" for us, same as before this file existed.
#
# Only run migrations when we're actually about to start Apache (CMD is
# "apache2-foreground", or a flag docker-php-entrypoint would itself
# rewrite into that) - NOT when the container is started for something else
# (e.g. `docker compose run web bash` for debugging), matching
# docker-php-entrypoint's own condition for what counts as "starting the
# server" (see that script, unchanged, at the path above).
if [ "$1" = 'apache2-foreground' ] || [ "${1#-}" != "$1" ]; then

    # docker-compose.yml's `depends_on: db: condition: service_healthy`
    # already gates this container's start on the db service's OWN
    # healthcheck passing (healthcheck.sh --connect --innodb_initialized,
    # run inside the db container). That does not by itself prove THIS
    # container can reach the db container over the Docker network with
    # the app's actual DB_USER/DB_PASSWORD/DB_NAME - a short retry loop
    # here is cheap, verifiable insurance against that gap rather than an
    # assumption. See task-13-report.md for what was actually observed
    # live (attempts needed, if any) the first time this ran against a
    # fresh `docker compose up -d --build`.
    echo "entrypoint: waiting for database connectivity..."
    attempt=0
    max_attempts=30
    until php -r '
        $h = getenv("DB_HOST") ?: "localhost";
        $u = getenv("DB_USER") ?: "";
        $p = getenv("DB_PASSWORD") ?: "";
        $d = getenv("DB_NAME") ?: "";
        $c = @mysqli_connect($h, $u, $p, $d);
        exit($c ? 0 : 1);
    ' >/dev/null 2>&1; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge "$max_attempts" ]; then
            echo "entrypoint: database not reachable after ${max_attempts} attempts, giving up" >&2
            exit 1
        fi
        sleep 2
    done
    echo "entrypoint: database reachable after ${attempt} attempt(s), running migrations..."

    php /var/www/html/vendor/bin/phinx migrate -c /var/www/html/phinx.php

fi

exec docker-php-entrypoint "$@"
