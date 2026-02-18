#!/usr/bin/env bash
set -e

# Wait for DB
until mysqladmin ping -h"$MYSQL_HOST" --skip-ssl --silent 2>/dev/null; do
  echo "Waiting for MySQL (Moodle)..."
  sleep 3
done

# Initialize Moodle if config.php is missing
if [ ! -f config.php ]; then
  echo "Installing Moodle..."
  
  # Moodle CLI install
  php admin/cli/install.php \
    --lang=en \
    --chmod=2777 \
    --dbtype=mysqli \
    --dbhost="$MYSQL_HOST" \
    --dbname="$MYSQL_DATABASE" \
    --dbuser="$MYSQL_USER" \
    --dbpass="$MYSQL_PASSWORD" \
    --wwwroot="${MOODLE_URL:-http://localhost:8080}" \
    --dataroot=/var/moodledata \
    --adminuser="${MOODLE_ADMIN_USER:-admin}" \
    --adminpass="${MOODLE_ADMIN_PASSWORD:-Moodle123!}" \
    --adminemail="${MOODLE_ADMIN_EMAIL:-admin@example.com}" \
    --fullname="Moodle LTI Lab" \
    --shortname="Moodle" \
    --non-interactive \
    --agree-license

  touch .installation_complete
  chown -R www-data:www-data /var/www/html /var/moodledata
fi

exec "$@"
