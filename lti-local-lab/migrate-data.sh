#!/bin/bash
#
# Migration Script - Old Setup → New Docker Setup
# Purpose: Automate data migration from old development environment
#
# Usage: ./migrate-data.sh
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

log_success() {
    echo -e "${GREEN}✓${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

log_error() {
    echo -e "${RED}✗${NC} $1"
}

log_header() {
    echo
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE} $1${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo
}

# Check if running from correct directory
if [ ! -f "docker-compose.yml" ]; then
    log_error "Please run this script from /root/qbnox-lti-platform/lti-local-lab/"
    exit 1
fi

log_header "📦 LTI Platform Data Migration Script"

echo "This script will help you migrate data from your old setup to the new Docker environment."
echo

# Step 1: Identify old setup
log_header "Step 1: Identify Old Setup"

echo "Where is your old setup?"
echo "1) Docker containers (old docker-compose or standalone containers)"
echo "2) Native installation (Apache/Nginx + MySQL on host)"
echo "3) Production server (remote server)"
echo
read -p "Enter choice [1-3]: " SETUP_TYPE

case $SETUP_TYPE in
    1)
        log_info "Using Docker export method"
        EXPORT_METHOD="docker"
        ;;
    2)
        log_info "Using native installation export method"
        EXPORT_METHOD="native"
        ;;
    3)
        log_info "Using remote server export method"
        EXPORT_METHOD="remote"
        ;;
    *)
        log_error "Invalid choice"
        exit 1
        ;;
esac

# Create backup directory
BACKUP_DIR="/tmp/lti-migration-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
log_success "Created backup directory: $BACKUP_DIR"

# Step 2: Export databases
log_header "Step 2: Export Databases"

if [ "$EXPORT_METHOD" = "docker" ]; then
    # List Docker containers
    log_info "Available Docker containers:"
    docker ps -a | grep -E "CONTAINER|mysql|moodle|pressbooks"
    echo

    read -p "Enter OLD MySQL container name/ID: " OLD_MYSQL_CONTAINER
    read -p "Enter MySQL root password [default: root]: " OLD_MYSQL_PASSWORD
    OLD_MYSQL_PASSWORD=${OLD_MYSQL_PASSWORD:-root}

    # Export Moodle database
    log_info "Exporting Moodle database..."
    if docker exec "$OLD_MYSQL_CONTAINER" mysqldump -uroot -p"$OLD_MYSQL_PASSWORD" moodle > "$BACKUP_DIR/moodle_backup.sql" 2>/dev/null; then
        log_success "Moodle database exported: $(du -h $BACKUP_DIR/moodle_backup.sql | awk '{print $1}')"
    else
        log_warning "Moodle database export failed (may not exist in old setup)"
    fi

    # Export Pressbooks database
    log_info "Exporting Pressbooks database..."
    if docker exec "$OLD_MYSQL_CONTAINER" mysqldump -uroot -p"$OLD_MYSQL_PASSWORD" pressbooks > "$BACKUP_DIR/pressbooks_backup.sql" 2>/dev/null; then
        log_success "Pressbooks database exported: $(du -h $BACKUP_DIR/pressbooks_backup.sql | awk '{print $1}')"
    else
        log_warning "Pressbooks database export failed (may not exist in old setup)"
    fi

elif [ "$EXPORT_METHOD" = "native" ]; then
    log_info "Exporting from native MySQL installation..."

    read -p "MySQL username [default: root]: " MYSQL_USER
    MYSQL_USER=${MYSQL_USER:-root}

    # Export Moodle database
    log_info "Exporting Moodle database..."
    if mysqldump -u"$MYSQL_USER" -p moodle > "$BACKUP_DIR/moodle_backup.sql" 2>/dev/null; then
        log_success "Moodle database exported"
    else
        log_warning "Moodle database export failed"
    fi

    # Export Pressbooks database
    log_info "Exporting Pressbooks database..."
    if mysqldump -u"$MYSQL_USER" -p pressbooks > "$BACKUP_DIR/pressbooks_backup.sql" 2>/dev/null; then
        log_success "Pressbooks database exported"
    else
        log_warning "Pressbooks database export failed"
    fi

elif [ "$EXPORT_METHOD" = "remote" ]; then
    read -p "Remote server SSH address (user@host): " REMOTE_HOST

    log_info "Exporting databases from remote server..."

    # Export Moodle
    log_info "Exporting Moodle database..."
    ssh "$REMOTE_HOST" "docker exec mysql mysqldump -uroot -proot moodle" > "$BACKUP_DIR/moodle_backup.sql" 2>/dev/null || \
    ssh "$REMOTE_HOST" "mysqldump -uroot -p moodle" > "$BACKUP_DIR/moodle_backup.sql" 2>/dev/null

    # Export Pressbooks
    log_info "Exporting Pressbooks database..."
    ssh "$REMOTE_HOST" "docker exec pressbooks mysqldump -uroot -proot pressbooks" > "$BACKUP_DIR/pressbooks_backup.sql" 2>/dev/null || \
    ssh "$REMOTE_HOST" "mysqldump -uroot -p pressbooks" > "$BACKUP_DIR/pressbooks_backup.sql" 2>/dev/null

    log_success "Databases exported from remote server"
fi

# Step 3: Export files (optional)
log_header "Step 3: Export Files (Optional)"

echo "Do you want to export Moodle files (www/html, moodledata)?"
read -p "Export Moodle files? [y/N]: " EXPORT_MOODLE_FILES

if [ "$EXPORT_MOODLE_FILES" = "y" ] || [ "$EXPORT_MOODLE_FILES" = "Y" ]; then
    log_info "Exporting Moodle files..."

    if [ "$EXPORT_METHOD" = "docker" ]; then
        read -p "Enter OLD Moodle container name/ID: " OLD_MOODLE_CONTAINER
        docker cp "$OLD_MOODLE_CONTAINER:/var/www/html" "$BACKUP_DIR/moodle_files"
        docker cp "$OLD_MOODLE_CONTAINER:/var/moodledata" "$BACKUP_DIR/moodledata" 2>/dev/null || log_warning "moodledata not found"
        log_success "Moodle files exported"
    elif [ "$EXPORT_METHOD" = "native" ]; then
        read -p "Enter Moodle installation path [/var/www/html]: " MOODLE_PATH
        MOODLE_PATH=${MOODLE_PATH:-/var/www/html}
        sudo cp -r "$MOODLE_PATH" "$BACKUP_DIR/moodle_files"
        sudo cp -r /var/moodledata "$BACKUP_DIR/moodledata" 2>/dev/null || log_warning "moodledata not found"
        log_success "Moodle files exported"
    fi
fi

echo "Do you want to export Pressbooks files (uploads, themes, plugins)?"
read -p "Export Pressbooks files? [y/N]: " EXPORT_PB_FILES

if [ "$EXPORT_PB_FILES" = "y" ] || [ "$EXPORT_PB_FILES" = "Y" ]; then
    log_info "Exporting Pressbooks files..."

    if [ "$EXPORT_METHOD" = "docker" ]; then
        read -p "Enter OLD Pressbooks container name/ID: " OLD_PB_CONTAINER
        docker cp "$OLD_PB_CONTAINER:/var/www/pressbooks" "$BACKUP_DIR/pressbooks_files" 2>/dev/null || \
        docker cp "$OLD_PB_CONTAINER:/var/www/html" "$BACKUP_DIR/pressbooks_files"
        log_success "Pressbooks files exported"
    elif [ "$EXPORT_METHOD" = "native" ]; then
        read -p "Enter Pressbooks installation path: " PB_PATH
        sudo cp -r "$PB_PATH" "$BACKUP_DIR/pressbooks_files"
        log_success "Pressbooks files exported"
    fi
fi

# Step 4: Start new Docker setup
log_header "Step 4: Prepare New Docker Setup"

echo "Starting new Docker containers..."
docker-compose up -d

log_info "Waiting for containers to be ready..."
sleep 10

# Wait for MySQL to be ready
log_info "Waiting for MySQL to initialize..."
until docker exec mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
    echo -n "."
    sleep 2
done
echo
log_success "MySQL is ready"

# Wait for Pressbooks to be ready
log_info "Waiting for Pressbooks to initialize..."
until docker exec pressbooks test -f /var/www/pressbooks/.env 2>/dev/null; do
    echo -n "."
    sleep 2
done
echo
log_success "Pressbooks is ready"

# Step 5: Import databases
log_header "Step 5: Import Databases"

if [ -f "$BACKUP_DIR/moodle_backup.sql" ]; then
    log_info "Importing Moodle database..."
    docker exec -i mysql mysql -uroot -proot moodle < "$BACKUP_DIR/moodle_backup.sql"
    log_success "Moodle database imported"
else
    log_warning "No Moodle database backup found, skipping"
fi

if [ -f "$BACKUP_DIR/pressbooks_backup.sql" ]; then
    log_info "Importing Pressbooks database..."
    docker exec -i mysql mysql -uroot -proot pressbooks < "$BACKUP_DIR/pressbooks_backup.sql"
    log_success "Pressbooks database imported"
else
    log_warning "No Pressbooks database backup found, skipping"
fi

# Step 6: Import files (if exported)
log_header "Step 6: Import Files"

if [ -d "$BACKUP_DIR/moodle_files" ]; then
    log_info "Importing Moodle files..."
    docker-compose stop moodle
    docker run --rm -v lti-local-lab_moodle-data:/target -v "$BACKUP_DIR/moodle_files":/source alpine sh -c "cp -r /source/* /target/"
    if [ -d "$BACKUP_DIR/moodledata" ]; then
        docker run --rm -v lti-local-lab_moodledata:/target -v "$BACKUP_DIR/moodledata":/source alpine sh -c "cp -r /source/* /target/"
    fi
    docker-compose start moodle
    log_success "Moodle files imported"
else
    log_info "No Moodle files to import, skipping"
fi

if [ -d "$BACKUP_DIR/pressbooks_files" ]; then
    log_info "Importing Pressbooks files..."
    docker-compose stop pressbooks
    docker run --rm -v lti-local-lab_pressbooks-data:/target -v "$BACKUP_DIR/pressbooks_files":/source alpine sh -c "cp -r /source/* /target/"
    docker-compose start pressbooks
    log_success "Pressbooks files imported"
else
    log_info "No Pressbooks files to import, skipping"
fi

# Step 7: Fix permissions
log_header "Step 7: Fix File Permissions"

log_info "Setting correct file permissions..."
docker exec moodle chown -R www-data:www-data /var/www/html 2>/dev/null || true
docker exec moodle chown -R www-data:www-data /var/moodledata 2>/dev/null || true
docker exec pressbooks chown -R www-data:www-data /var/www/pressbooks 2>/dev/null || true
log_success "File permissions updated"

# Step 8: Update configuration
log_header "Step 8: Update Configuration"

log_info "Updating Moodle configuration..."
docker exec moodle bash -c "sed -i \"s/\\\$CFG->dbhost = .*/\\\$CFG->dbhost = 'mysql';/\" /var/www/html/config.php" 2>/dev/null || true
docker exec moodle bash -c "sed -i \"s/\\\$CFG->wwwroot = .*/\\\$CFG->wwwroot = 'http:\/\/localhost:8080';/\" /var/www/html/config.php" 2>/dev/null || true
log_success "Moodle configuration updated"

log_info "Updating Pressbooks configuration..."
docker exec pressbooks bash -c "cd /var/www/pressbooks && wp dotenv set DB_HOST mysql --allow-root" 2>/dev/null || true
docker exec pressbooks bash -c "cd /var/www/pressbooks && wp dotenv set WP_HOME http://localhost:8081 --allow-root" 2>/dev/null || true
log_success "Pressbooks configuration updated"

# Step 9: Verification
log_header "Step 9: Verification"

echo "Running verification checks..."
echo

# Check database connectivity
log_info "Checking database connectivity..."
if docker exec mysql mysql -uroot -proot -e "SELECT 1" >/dev/null 2>&1; then
    log_success "MySQL connection successful"
else
    log_error "MySQL connection failed"
fi

# Check Moodle database
if docker exec mysql mysql -uroot -proot -e "USE moodle; SELECT COUNT(*) FROM mdl_user;" >/dev/null 2>&1; then
    MOODLE_USERS=$(docker exec mysql mysql -uroot -proot -e "USE moodle; SELECT COUNT(*) FROM mdl_user;" 2>/dev/null | tail -1)
    log_success "Moodle database: $MOODLE_USERS users found"
else
    log_warning "Moodle database check failed"
fi

# Check Pressbooks database
if docker exec mysql mysql -uroot -proot -e "USE pressbooks; SELECT COUNT(*) FROM wp_users;" >/dev/null 2>&1; then
    PB_USERS=$(docker exec mysql mysql -uroot -proot -e "USE pressbooks; SELECT COUNT(*) FROM wp_users;" 2>/dev/null | tail -1)
    log_success "Pressbooks database: $PB_USERS users found"
else
    log_warning "Pressbooks database check failed"
fi

# Check LTI tables
if docker exec mysql mysql -uroot -proot -e "USE pressbooks; SHOW TABLES LIKE 'wp_lti%';" >/dev/null 2>&1; then
    LTI_TABLES=$(docker exec mysql mysql -uroot -proot -e "USE pressbooks; SHOW TABLES LIKE 'wp_lti%';" 2>/dev/null | tail -n +2 | wc -l)
    log_success "LTI tables found: $LTI_TABLES"
else
    log_warning "No LTI tables found"
fi

# Check HTTP access
log_info "Checking HTTP access..."
sleep 5
if curl -sf http://localhost:8080 >/dev/null 2>&1; then
    log_success "Moodle accessible at http://localhost:8080"
else
    log_warning "Moodle not accessible yet (may need more time)"
fi

if curl -sf http://localhost:8081 >/dev/null 2>&1; then
    log_success "Pressbooks accessible at http://localhost:8081"
else
    log_warning "Pressbooks not accessible yet (may need more time)"
fi

# Final summary
log_header "✅ Migration Complete!"

echo "Migration Summary:"
echo "═══════════════════════════════════════════════"
echo
echo "📁 Backup Location: $BACKUP_DIR"
echo "📊 Database Status:"
[ -f "$BACKUP_DIR/moodle_backup.sql" ] && echo "   ✓ Moodle database: $(du -h $BACKUP_DIR/moodle_backup.sql | awk '{print $1}')"
[ -f "$BACKUP_DIR/pressbooks_backup.sql" ] && echo "   ✓ Pressbooks database: $(du -h $BACKUP_DIR/pressbooks_backup.sql | awk '{print $1}')"
echo
echo "🌐 Access URLs:"
echo "   • Moodle:     http://localhost:8080"
echo "   • Pressbooks: http://localhost:8081"
echo
echo "📚 Next Steps:"
echo "   1. Test Moodle login: http://localhost:8080"
echo "   2. Test Pressbooks login: http://localhost:8081"
echo "   3. Test LTI launch from Moodle course"
echo "   4. Verify Deep Linking works"
echo "   5. Test AGS grade sync"
echo
echo "📖 For troubleshooting, see: MIGRATION_GUIDE.md"
echo

log_info "To view logs: docker-compose logs -f"
log_info "To verify LTI config: docker exec mysql mysql -uroot -proot pressbooks -e 'SELECT * FROM wp_lti_platforms\\G'"
echo

log_success "Migration script completed successfully!"
