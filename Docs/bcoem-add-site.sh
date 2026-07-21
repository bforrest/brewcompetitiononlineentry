#!/usr/bin/env bash
#
# bcoem-add-site.sh — add one more BCOE&M instance to a server already
# provisioned by bcoem-provision.sh (Apache + PHP + MariaDB present).
#
# Default: gatlin.horsemenofthehops.com -> /var/www/gatlin
# Override:  bash bcoem-add-site.sh <name>     (e.g. "cider")
#
# BEFORE RUNNING: point a DNS A record for the new hostname at this
# server's IP, or the Let's Encrypt step will fail (retryable).
#
set -euo pipefail

### ------------------------------------------------------------------
### Configuration
### ------------------------------------------------------------------
NAME="${1:-gatlin}"                    # subdomain == docroot name
DOMAIN_BASE="horsemenofthehops.com"
FQDN="${NAME}.${DOMAIN_BASE}"
WEBROOT="/var/www"
DOCROOT="${WEBROOT}/${NAME}"
ADMIN_EMAIL="bforrest30@gmail.com"
REPO="https://github.com/geoffhumphrey/brewcompetitiononlineentry.git"
CREDS_FILE="/root/bcoem-credentials.txt"

DB_NAME="bcoem_${NAME}"
DB_USER="bcoem_${NAME}"
DB_PASS=$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)

[[ $EUID -eq 0 ]] || { echo "Run as root."; exit 1; }
command -v apache2 >/dev/null || { echo "Apache not found — run bcoem-provision.sh first."; exit 1; }
command -v mysql   >/dev/null || { echo "MariaDB not found — run bcoem-provision.sh first."; exit 1; }
[[ -e "$DOCROOT" ]] && { echo "${DOCROOT} already exists — aborting."; exit 1; }

### ------------------------------------------------------------------
### 1. Fetch latest BCOE&M release
### ------------------------------------------------------------------
echo "==> Downloading BCOE&M..."
SRC=$(mktemp -d)
git clone --quiet "$REPO" "$SRC/bcoem"
cd "$SRC/bcoem"
LATEST_TAG=$(git describe --tags "$(git rev-list --tags --max-count=1)")
git checkout --quiet "$LATEST_TAG"
echo "    Using release: $LATEST_TAG"

### ------------------------------------------------------------------
### 2. Docroot
### ------------------------------------------------------------------
echo "==> Creating docroot ${DOCROOT}..."
mkdir -p "$DOCROOT"
cp -a "$SRC/bcoem/." "$DOCROOT/"
rm -rf "$DOCROOT/.git" "$SRC"
chown -R www-data:www-data "$DOCROOT"
find "$DOCROOT" -type d -exec chmod 755 {} \;
find "$DOCROOT" -type f -exec chmod 644 {} \;

### ------------------------------------------------------------------
### 3. Database + user
### ------------------------------------------------------------------
echo "==> Creating database ${DB_NAME}..."
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

### ------------------------------------------------------------------
### 4. Apache vhost
### ------------------------------------------------------------------
echo "==> Creating vhost for ${FQDN}..."
cat > "/etc/apache2/sites-available/${NAME}.conf" <<VHOST
<VirtualHost *:80>
    ServerName ${FQDN}
    DocumentRoot ${DOCROOT}

    <Directory ${DOCROOT}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${NAME}_error.log
    CustomLog \${APACHE_LOG_DIR}/${NAME}_access.log combined
</VirtualHost>
VHOST
a2ensite "${NAME}.conf" >/dev/null
systemctl reload apache2

### ------------------------------------------------------------------
### 5. TLS certificate (requires DNS already pointing here)
### ------------------------------------------------------------------
echo "==> Requesting Let's Encrypt certificate for ${FQDN}..."
if certbot --apache --non-interactive --agree-tos -m "$ADMIN_EMAIL" \
    --redirect -d "$FQDN"; then
  echo "    Certificate installed; HTTP redirects to HTTPS."
else
  echo "    WARNING: certbot failed (DNS not propagated yet?)."
  echo "    Re-run later:  certbot --apache -d ${FQDN}"
fi

### ------------------------------------------------------------------
### 6. Record credentials
### ------------------------------------------------------------------
touch "$CREDS_FILE"; chmod 600 "$CREDS_FILE"
{
  echo ""
  echo "--- ${FQDN} (added $(date)) ---"
  echo "Setup URL:   https://${FQDN}/setup.php"
  echo "DB host:     localhost"
  echo "DB name:     ${DB_NAME}"
  echo "DB user:     ${DB_USER}"
  echo "DB password: ${DB_PASS}"
} >> "$CREDS_FILE"

echo ""
echo "=========================================================="
echo " ${FQDN} provisioned (BCOE&M ${LATEST_TAG})."
echo ""
grep -A5 -- "--- ${FQDN}" "$CREDS_FILE" | tail -6
echo ""
echo " Next: visit the Setup URL and complete the 8-step wizard."
echo "=========================================================="
