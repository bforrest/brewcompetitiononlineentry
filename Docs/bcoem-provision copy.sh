#!/usr/bin/env bash
#
# BCOE&M dual-instance provisioning script
# Target: fresh DigitalOcean droplet, Ubuntu 24.04 LTS
# Sets up: Apache + PHP 8.3 + MariaDB, two BCOE&M instances
#   - promead.horsemenofthehops.com  -> /var/www/promead
#   - mead.horsemenofthehops.com     -> /var/www/mead
#
# BEFORE RUNNING:
#   1. Create the droplet (Basic, 1-2 GB RAM is plenty).
#   2. Point DNS A records for both hostnames at the droplet IP
#      (required for the Let's Encrypt step to succeed).
#   3. Run as root:  bash bcoem-provision.sh
#
set -euo pipefail

### ------------------------------------------------------------------
### Configuration
### ------------------------------------------------------------------
DOMAIN_BASE="horsemenofthehops.com"
INSTANCES=("promead" "mead")           # subdomain == docroot name
WEBROOT="/var/www"
ADMIN_EMAIL="bforrest30@gmail.com"     # for Let's Encrypt notices
REPO="https://github.com/geoffhumphrey/brewcompetitiononlineentry.git"

CREDS_FILE="/root/bcoem-credentials.txt"

[[ $EUID -eq 0 ]] || { echo "Run as root."; exit 1; }

### ------------------------------------------------------------------
### 1. Base packages
### ------------------------------------------------------------------
echo "==> Installing packages..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq
apt-get install -y -qq \
  apache2 mariadb-server git unzip \
  php php-mysqli php-mbstring php-gd php-curl php-zip php-xml php-intl \
  libapache2-mod-php \
  certbot python3-certbot-apache

a2enmod rewrite headers ssl >/dev/null

### ------------------------------------------------------------------
### 2. Firewall
### ------------------------------------------------------------------
echo "==> Configuring firewall..."
ufw allow OpenSSH >/dev/null
ufw allow "Apache Full" >/dev/null
ufw --force enable >/dev/null

### ------------------------------------------------------------------
### 3. Harden MariaDB (non-interactive equivalent of mysql_secure_installation)
### ------------------------------------------------------------------
echo "==> Securing MariaDB..."
mysql <<'SQL'
DELETE FROM mysql.global_priv WHERE User='';
DELETE FROM mysql.global_priv WHERE User='root' AND Host NOT IN ('localhost','127.0.0.1','::1');
DROP DATABASE IF EXISTS test;
FLUSH PRIVILEGES;
SQL

### ------------------------------------------------------------------
### 4. Fetch latest BCOE&M release (auto-detects newest tag)
### ------------------------------------------------------------------
echo "==> Downloading BCOE&M..."
SRC=$(mktemp -d)
git clone --quiet "$REPO" "$SRC/bcoem"
cd "$SRC/bcoem"
LATEST_TAG=$(git describe --tags "$(git rev-list --tags --max-count=1)")
git checkout --quiet "$LATEST_TAG"
echo "    Using release: $LATEST_TAG"

### ------------------------------------------------------------------
### 5. Per-instance setup: docroot, database, vhost
### ------------------------------------------------------------------
: > "$CREDS_FILE"; chmod 600 "$CREDS_FILE"
echo "BCOE&M credentials — generated $(date)" >> "$CREDS_FILE"

for NAME in "${INSTANCES[@]}"; do
  FQDN="${NAME}.${DOMAIN_BASE}"
  DOCROOT="${WEBROOT}/${NAME}"
  DB_NAME="bcoem_${NAME}"
  DB_USER="bcoem_${NAME}"
  DB_PASS=$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)

  echo "==> Setting up ${FQDN}..."

  # Docroot
  rm -rf "$DOCROOT"
  mkdir -p "$DOCROOT"
  cp -a "$SRC/bcoem/." "$DOCROOT/"
  rm -rf "$DOCROOT/.git"
  chown -R www-data:www-data "$DOCROOT"
  find "$DOCROOT" -type d -exec chmod 755 {} \;
  find "$DOCROOT" -type f -exec chmod 644 {} \;

  # Database + user
  mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

  # Apache vhost
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

  # Record credentials for the setup wizard
  {
    echo ""
    echo "--- ${FQDN} ---"
    echo "Setup URL:   https://${FQDN}/setup.php"
    echo "DB host:     localhost"
    echo "DB name:     ${DB_NAME}"
    echo "DB user:     ${DB_USER}"
    echo "DB password: ${DB_PASS}"
  } >> "$CREDS_FILE"
done

a2dissite 000-default.conf >/dev/null 2>&1 || true
systemctl reload apache2
rm -rf "$SRC"

### ------------------------------------------------------------------
### 6. TLS certificates (requires DNS already pointing here)
### ------------------------------------------------------------------
echo "==> Requesting Let's Encrypt certificates..."
CERT_DOMAINS=()
for NAME in "${INSTANCES[@]}"; do CERT_DOMAINS+=(-d "${NAME}.${DOMAIN_BASE}"); done
if certbot --apache --non-interactive --agree-tos -m "$ADMIN_EMAIL" \
    --redirect "${CERT_DOMAINS[@]}"; then
  echo "    Certificates installed; HTTP now redirects to HTTPS."
else
  echo "    WARNING: certbot failed (DNS not propagated yet?)."
  echo "    Re-run later:  certbot --apache ${CERT_DOMAINS[*]}"
fi

### ------------------------------------------------------------------
### 7. PHP tuning for BCOE&M (label/paperwork PDF generation, uploads)
### ------------------------------------------------------------------
PHP_INI=$(php -r 'echo php_ini_loaded_file();')
sed -i \
  -e 's/^upload_max_filesize.*/upload_max_filesize = 16M/' \
  -e 's/^post_max_size.*/post_max_size = 20M/' \
  -e 's/^memory_limit.*/memory_limit = 256M/' \
  -e 's/^max_execution_time.*/max_execution_time = 120/' \
  "$PHP_INI"
systemctl restart apache2

### ------------------------------------------------------------------
### Done
### ------------------------------------------------------------------
echo ""
echo "=========================================================="
echo " Provisioning complete (BCOE&M ${LATEST_TAG})."
echo ""
echo " Database credentials saved to: ${CREDS_FILE}"
cat "$CREDS_FILE"
echo ""
echo " Next steps:"
echo "   1. Visit each Setup URL above and complete the"
echo "      8-step BCOE&M setup wizard using those DB credentials."
echo "   2. Configure SMTP (Mailgun/SES/Brevo) in each site's"
echo "      admin settings — don't rely on PHP mail()."
echo "   3. Set up backups: DigitalOcean droplet backups, plus"
echo "      nightly mysqldump of both databases off-server."
echo "=========================================================="
