# Installation Guide

This guide covers installation for both **developers** (using Docker) and **end users** (with existing Moodle and Pressbooks installations).

---

## Table of Contents

1. [For Developers - Docker Installation](#for-developers---docker-installation)
2. [For End Users - Manual Installation](#for-end-users---manual-installation)
3. [Plugin Installation](#plugin-installation)
4. [System Requirements](#system-requirements)
5. [Troubleshooting](#troubleshooting)

---

## For Developers - Docker Installation

### Prerequisites
- Docker & Docker Compose installed
- Git
- 8GB RAM minimum
- Ports 80, 443, 3306 available

### Quick Start

```bash
# Clone repository
git clone https://github.com/ugenkudupudiqbnox/qbnox-lti-platform.git
cd qbnox-lti-platform

# Setup the lab with moodle and pressboosk in dockers
make 
```

### Docker Services

| Service | Port | Description |
|---------|------|-------------|
| MySQL | 3306 | Database server |
| Moodle | 8080 | Moodle LMS |
| Pressbooks | 8081 | Pressbooks platform |
| Nginx | 80, 443 | Reverse proxy (production) |

### Development Commands

```bash
# View logs
docker-compose logs -f pressbooks
docker-compose logs -f moodle

# Access containers
docker exec -it pressbooks bash
docker exec -it moodle bash

# Restart services
docker-compose restart

# Clean install
docker-compose down -v
docker-compose up -d
```

### Production Environment Configuration (.env)

When deploying to a production or staging environment (instead of `.local`), developers must configure a `.env` file in the project root. This ensures that the automated scripts for registration, grade sync testing, and CORS setup use the correct domains and protocol.

1. **Create the .env file**:
   ```bash
   cp lti-local-lab/.env.production .env
   ```

2. **Configure Variables**:
   Update the following variables in your `.env`:

   ```dotenv
   ### --- 🌍 Domain Configuration ---
   # Only set your domains; URLs are derived automatically by maintenance scripts.
   PRESSBOOKS_DOMAIN=pb.yourdomain.com
   MOODLE_DOMAIN=moodle.yourdomain.com

   ### --- ⚙️ Platform Settings ---
   # Matches your production Moodle version (4.1, 4.4, or 5.1 supported).
   # This ensures the correct LTI Advantage service registration logic is used.
   MOODLE_VERSION=4.4
   ```

### Nginx Reverse Proxy Configuration (Production Hardening)

For production environments, Nginx must be configured as a reverse proxy with specific headers to ensure that LTI 1.3 launches (which rely on secure signed cookies and JWTs) function correctly behind the proxy.

#### Crucial Proxy Headers
Add these to your Nginx `location /` block:

```nginx
# Ensure Pressbooks/Moodle detect the correct protocol (HTTPS)
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
proxy_set_header Host $http_host;

# Cookie Privacy (required for LTI frames)
proxy_cookie_path / "/; SameSite=None; Secure";

# Required for LTI embedding
# Prevent Moodle from blocking the Pressbooks iframe
proxy_hide_header X-Frame-Options; 
# Allow Moodle domains to embed this site
add_header Content-Security-Policy "frame-ancestors 'self' *.yourdomain.com moodle.yourdomain.com";
```

### Diagnostics & Pre-flight
To verify your environment is correctly configured before attempting a production LTI registration, run:
```bash
bash scripts/doctor.sh
```

---

## For End Users - Manual Installation

This section is for users with **existing Moodle and Pressbooks installations**.

### 1. Perform LTI 1.3 Handshake

To enable LTI communication, you must register the platforms in each other.

#### Part 1: Register Pressbooks in Moodle
1. Log in to Moodle as **Site Administrator**.
2. Go to **Site administration > Plugins > Activity modules > External tool > Manage tools**.
3. Click **configure a tool manually**.
4. Fill in these details (Replace `https://pb.example.com` with your Pressbooks URL):
   - **Tool name**: `Pressbooks LTI Platform`
   - **Tool URL**: `https://pb.example.com/`
   - **LTI version**: `LTI 1.3`
   - **Public key type**: `JWK Keyset`
   - **Public keyset**: `https://pb.example.com/wp-json/pb-lti/v1/keyset`
   - **Initiate login URL**: `https://pb.example.com/wp-json/pb-lti/v1/login`
   - **Redirection URI(s)**: `https://pb.example.com/wp-json/pb-lti/v1/launch`
   - **Content Selection URL**: `https://pb.example.com/wp-json/pb-lti/v1/deep-link` (Check "Supports Deep Linking")
5. **Services**:
   - **IMS LTI Assignment and Grade Services**: `Use this service for grade sync and column management`
   - **IMS LTI Names and Role Provisioning**: `Request user's name and email`
6. **Privacy**:
   - **Share launcher's name with tool**: `Always`
   - **Share launcher's email with tool**: `Always`
   - **Accept grades from the tool**: `Delegate to teacher` (This enables the "Allow adding grades" checkbox in activity settings).

#### Part 2: Register Moodle in Pressbooks
After saving the tool in Moodle, click the **Deployment icon** (the list icon) for the new tool to see the **Client ID** and **Deployment ID**. Register these in Pressbooks.

**Via CLI (Recommended):**
```bash
# Run this on your Pressbooks server
php scripts/pressbooks-register-platform.php "https://your-moodle.com" "YOUR_CLIENT_ID" "YOUR_DEPLOYMENT_ID"
```

For more detailed setup and troubleshooting, see the [User Guide](USER_GUIDE.md).

### System Requirements

#### Minimum Requirements
- **Moodle**: 4.1+ (LTI 1.3 support required)
- **Pressbooks**: 6.0+ with Bedrock structure
- **PHP**: 8.1+ (8.2+ recommended for Pressbooks)
- **MySQL/MariaDB**: 8.0+ / 10.6+
- **HTTPS**: SSL certificate required (LTI 1.3 requires secure connections)
- **Server**: Apache or Nginx with mod_rewrite enabled

#### PHP Extensions Required
```
- mysqli / pdo_mysql
- curl
- json
- xml
- mbstring
- zip
- gd
- intl
- opcache
```

### Pre-Installation Checklist

✅ **Moodle**
- [ ] Moodle 4.1 or higher installed
- [ ] Admin access to Moodle
- [ ] HTTPS enabled
- [ ] External tool activity module enabled

✅ **Pressbooks**
- [ ] Pressbooks installed (Bedrock or standard)
- [ ] WordPress Multisite configured
- [ ] Network Admin access
- [ ] HTTPS enabled
- [ ] REST API accessible

✅ **Server**
- [ ] PHP 8.1+ installed
- [ ] Required PHP extensions enabled
- [ ] Composer installed (for Bedrock)
- [ ] SSL certificate valid

---

## Plugin Installation


### Install via Composer (Bedrock Only)

Add to `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/ugenkudupudiqbnox/qbnox-lti-platform"
    }
  ],
  "require": {
    "ugenkudupudiqbnox/qbnox-lti-platform": "v2.1.0"
  }
}
```

Then run:
```bash
composer update
wp plugin activate pressbooks-lti-platform --network
```

---

## Post-Installation Configuration

### 1. Verify Plugin Activation

```bash
# Via WP-CLI
wp plugin list --status=active-network | grep pressbooks-lti-platform

# Via Browser
# Navigate to: Network Admin → Plugins
# Verify "Pressbooks LTI Platform" shows "Network Active"
```

### 2. Verify REST API Endpoints

Test that all LTI endpoints are accessible:

```bash
# Test keyset endpoint
curl https://your-pressbooks-domain.com/wp-json/pb-lti/v1/keyset

# Expected response:
# {"keys":[{"kty":"RSA","use":"sig",...}]}

# Test other endpoints
curl https://your-pressbooks-domain.com/wp-json/pb-lti/v1/login
curl https://your-pressbooks-domain.com/wp-json/pb-lti/v1/launch
```

**If endpoints return 404:**

#### For Apache:
Ensure `.htaccess` exists in document root with:
```apache
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
```

#### For Bedrock + Apache:
Check Apache DocumentRoot is set to `/path/to/bedrock/web`:
```apache
# /etc/apache2/sites-available/your-site.conf
DocumentRoot /var/www/pressbooks/web
```

#### For Nginx:
```nginx
location / {
    try_files $uri $uri/ /index.php?$args;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

### 3. Configure Database Tables

The plugin will auto-create required tables on activation. Verify:

```sql
-- Check if tables exist
SHOW TABLES LIKE 'wp_lti_%';

-- Expected tables:
-- wp_lti_platforms
-- wp_lti_deployments
-- wp_lti_nonces
-- wp_lti_keys
```

If tables don't exist, run manually:

```bash
wp eval-file /path/to/pressbooks-lti-platform/db/schema.sql --allow-root
```

---

## Verifying Installation

### Checklist

✅ **Plugin Status**
```bash
wp plugin list --status=active-network | grep pressbooks-lti-platform
# Should show: active-network
```

✅ **REST API Endpoints**
```bash
curl https://your-domain.com/wp-json/ | grep pb-lti
# Should include: "pb-lti/v1"
```

✅ **Database Tables**
```bash
wp db query "SHOW TABLES LIKE 'wp_lti_%'" --allow-root
# Should list: wp_lti_platforms, wp_lti_deployments, etc.
```

✅ **PHP Version**
```bash
php -v
# Should be: 8.1+ (8.2+ recommended)
```

✅ **Required Extensions**
```bash
php -m | grep -E 'curl|json|mysqli|xml|mbstring'
# All should be listed
```

---

## Common Installation Issues

### Issue: REST API Returns 404

**Cause**: Permalink/rewrite rules not working

**Fix**:
```bash
# Flush rewrite rules
wp rewrite flush --allow-root

# Regenerate .htaccess
wp rewrite structure '/%postname%/' --allow-root

# Verify mod_rewrite is enabled (Apache)
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Issue: Plugin Activation Fails

**Cause**: PHP version too old or missing extensions

**Fix**:
```bash
# Check PHP version
php -v

# Check required extensions
php -m | grep -E 'curl|json|mysqli|xml|mbstring|zip|gd|intl'

# Install missing extensions (Debian/Ubuntu)
sudo apt-get install php8.2-curl php8.2-mysql php8.2-xml php8.2-mbstring php8.2-zip php8.2-gd php8.2-intl

# Restart web server
sudo systemctl restart apache2  # or nginx
```

### Issue: Database Connection Errors

**Cause**: WordPress database credentials incorrect

**Fix**:
- Verify `wp-config.php` database settings
- Test database connection:
```bash
wp db check --allow-root
```

### Issue: Permission Denied Errors

**Cause**: Incorrect file ownership/permissions

**Fix**:
```bash
# Set correct ownership (replace www-data with your web server user)
chown -R www-data:www-data /path/to/pressbooks/

# Set correct permissions
find /path/to/pressbooks/ -type d -exec chmod 755 {} \;
find /path/to/pressbooks/ -type f -exec chmod 644 {} \;
```

### Issue: White Screen / Fatal Error

**Cause**: PHP memory limit too low or fatal PHP error

**Fix**:
```bash
# Increase PHP memory limit
# Edit php.ini or wp-config.php
memory_limit = 256M

# Enable WordPress debugging
# Add to wp-config.php:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

# Check debug log
tail -f /path/to/wordpress/wp-content/debug.log
```

---

## Security Considerations

### 1. HTTPS Required
LTI 1.3 **requires** HTTPS. Ensure valid SSL certificate:
```bash
# Test SSL
curl -I https://your-domain.com

# Verify certificate
openssl s_client -connect your-domain.com:443 -servername your-domain.com
```

### 2. File Permissions
```bash
# Plugins directory
chmod 755 /path/to/plugins/pressbooks-lti-platform
chmod 644 /path/to/plugins/pressbooks-lti-platform/*.php

# Never make files writable by web server (777)
```

### 3. WordPress Security Keys
Ensure unique salts/keys in `wp-config.php`:
```bash
# Generate new keys
curl -s https://api.wordpress.org/secret-key/1.1/salt/
```

### 4. Database Security
- Use strong database password
- Restrict database user to necessary privileges only
- Enable SSL for database connections if remote

---

## Upgrading

### Via WordPress Admin
1. Download new version
2. Deactivate current plugin
3. Delete old plugin files
4. Upload new version
5. Activate plugin
6. Clear all caches

### Via Command Line
```bash
# Backup database first
wp db export backup.sql --allow-root

# Deactivate plugin
wp plugin deactivate pressbooks-lti-platform --network --allow-root

# Update plugin files
cd /path/to/plugins/
rm -rf pressbooks-lti-platform
git clone https://github.com/ugenkudupudiqbnox/qbnox-lti-platform.git
# OR unzip new version

# Set permissions
chown -R www-data:www-data pressbooks-lti-platform

# Activate plugin
wp plugin activate pressbooks-lti-platform --network --allow-root

# Clear caches
wp cache flush --allow-root
```

---

## Uninstallation

### Complete Removal

```bash
# 1. Deactivate plugin
wp plugin deactivate pressbooks-lti-platform --network --allow-root

# 2. Delete plugin files
rm -rf /path/to/plugins/pressbooks-lti-platform

# 3. (Optional) Remove database tables
wp db query "DROP TABLE IF EXISTS wp_lti_platforms" --allow-root
wp db query "DROP TABLE IF EXISTS wp_lti_deployments" --allow-root
wp db query "DROP TABLE IF EXISTS wp_lti_nonces" --allow-root
wp db query "DROP TABLE IF EXISTS wp_lti_keys" --allow-root

# 4. Clear caches
wp cache flush --allow-root
```

**⚠️ Warning**: Removing database tables will delete all LTI configuration data.

---

## Getting Help

### Support Resources
- 📖 [Full Documentation](../README.md)
- 🐛 [Issue Tracker](https://github.com/ugenkudupudiqbnox/qbnox-lti-platform/issues)
- 💬 [Community Forum](https://community.pressbooks.org)
- 📧 [Email Support](mailto:support@example.com)

### Before Asking for Help

Please gather this information:
```bash
# System information
wp --info

# Plugin status
wp plugin list | grep pressbooks-lti-platform

# Check for errors
tail -50 /path/to/wordpress/wp-content/debug.log

# Test REST API
curl -I https://your-domain.com/wp-json/pb-lti/v1/keyset
```

---

## Next Steps

After successful installation:
1. 📋 [Manual Handshake & Configuration](USER_GUIDE.md)
2. 🔧 [Configure LTI Settings](./CONFIGURATION.md)
3. 🧪 [Test LTI Launch](./TESTING.md)
4. 📊 [Set Up Grade Passback](./GRADE_PASSBACK.md)

---

**Need help?** Check our [troubleshooting guide](./TROUBLESHOOTING.md) or [open an issue](https://github.com/ugenkudupudiqbnox/qbnox-lti-platform/issues).
