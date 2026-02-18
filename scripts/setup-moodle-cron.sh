#!/usr/bin/env bash
set -e

echo "=== Setting up Moodle Cron ==="

# Load environment configuration to get SUDO
source "$(dirname "$0")/load-env.sh"

# Use SUDO if defined
DOCKER_CMD="${SUDO} docker"

# Check if Moodle container is running
if ! ${DOCKER_CMD} ps --format '{{.Names}}' | grep -q "^moodle$"; then
    echo "❌ Error: Moodle container is not running"
    exit 1
fi

# Test Moodle cron manually first
echo "Testing Moodle cron..."
${DOCKER_CMD} exec moodle php /var/www/html/admin/cli/cron.php > /dev/null 2>&1 && echo "✅ Moodle cron executable works" || {
    echo "❌ Error: Moodle cron failed to execute"
    exit 1
}

# Create log directory if it doesn't exist
mkdir -p /var/log

# Save current crontab and remove existing moodle-cron entries
crontab -l 2>/dev/null | grep -v "exec moodle php /var/www/html/admin/cli/cron.php" > /tmp/current_cron || touch /tmp/current_cron

echo "Adding Moodle cron job to crontab..."

# Add Moodle cron entry (runs every minute)
# We use path to docker to ensure it works from cron environment
DOCKER_PATH=$(command -v docker)
cat >> /tmp/current_cron << EOF

# Moodle cron - runs every minute
* * * * * sudo ${DOCKER_PATH} exec moodle php /var/www/html/admin/cli/cron.php >> /tmp/moodle-cron.log 2>&1
EOF

# Install the new crontab
crontab /tmp/current_cron
echo "✅ Moodle cron job added/updated in crontab"

# Clean up temporary file
rm -f /tmp/current_cron

echo ""
echo "✅ Moodle cron setup complete!"
echo ""
echo "Cron will run every 1 minute automatically."
echo "You can check logs with:"
echo "  tail -f /var/log/moodle-cron.log"
echo ""
echo "To manually trigger cron now:"
echo "  docker exec moodle php /var/www/html/admin/cli/cron.php"
