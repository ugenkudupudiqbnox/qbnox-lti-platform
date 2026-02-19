# H5P Results Installation Guide

## Quick Start

### 1. Install Database Tables

The tables are automatically created when the plugin loads. To verify:

```sql
SHOW TABLES LIKE 'wp_lti_h5p_%';
```

Expected output:
```
wp_lti_h5p_grading_config
wp_lti_h5p_grade_sync_log
```

### 2. Copy Files to Container (If Using Docker)

```bash
# From repository root
cd /root/pressbooks-lti-platform

# Copy all new files to Docker container
docker cp plugin/Services/H5PActivityDetector.php \
  pressbooks:/var/www/html/web/app/plugins/pressbooks-lti-platform/Services/

docker cp plugin/Services/H5PResultsManager.php \
  pressbooks:/var/www/html/web/app/plugins/pressbooks-lti-platform/Services/

docker cp plugin/Services/H5PGradeSyncEnhanced.php \
  pressbooks:/var/www/html/web/app/plugins/pressbooks-lti-platform/Services/

docker cp plugin/admin/h5p-results-metabox.php \
  pressbooks:/var/www/html/web/app/plugins/pressbooks-lti-platform/admin/

docker cp plugin/db/install-h5p-results.php \
  pressbooks:/var/www/html/web/app/plugins/pressbooks-lti-platform/db/

docker cp plugin/bootstrap.php \
  pressbooks:/var/www/html/web/app/plugins/pressbooks-lti-platform/
```

### 3. Verify Installation

```bash
# Check if tables were created
docker exec pressbooks wp db query \
  "SHOW TABLES LIKE 'wp_lti_h5p_%'" --path=/var/www/html/web/wp --allow-root

# Check if meta box is registered
docker exec pressbooks wp eval \
  "global \$wp_meta_boxes; var_dump(isset(\$wp_meta_boxes['chapter']['normal']['high']['pb_lti_h5p_results']));" \
  --path=/var/www/html/web/wp --allow-root
```

### 4. Test the Feature

1. **Edit a chapter** with H5P content
2. Look for **"📊 LMS Grade Reporting (LTI AGS)"** meta box
3. **Enable grading** and configure activities
4. **Save** the chapter
5. **Launch from LMS** as a student
6. **Complete H5P** activity
7. **Check LMS gradebook** for synced grade

## Manual Installation (Production)

### Step 1: Add Files to Plugin

Create these files in your `pressbooks-lti-platform` plugin directory:

```
plugin/
├── Services/
│   ├── H5PActivityDetector.php
│   ├── H5PResultsManager.php
│   └── H5PGradeSyncEnhanced.php
├── admin/
│   └── h5p-results-metabox.php
└── db/
    ├── h5p-results-schema.sql
    └── install-h5p-results.php
```

### Step 2: Update bootstrap.php

Add these lines to `plugin/bootstrap.php`:

```php
// After existing Service requires
require_once PB_LTI_PATH.'Services/H5PActivityDetector.php';
require_once PB_LTI_PATH.'Services/H5PResultsManager.php';
require_once PB_LTI_PATH.'Services/H5PGradeSyncEnhanced.php';

// After admin/menu.php
require_once PB_LTI_PATH.'admin/h5p-results-metabox.php';

// After ajax/handlers.php
require_once PB_LTI_PATH.'db/install-h5p-results.php';

// Replace H5PGradeSync::init with:
add_action('init', ['PB_LTI\Services\H5PGradeSyncEnhanced', 'init']);

// Add meta box initialization
add_action('admin_init', ['PB_LTI\Admin\H5PResultsMetaBox', 'init']);
```

### Step 3: Create Database Tables

**Option A: Automatic (Recommended)**

Visit any WordPress admin page. Tables are created automatically via `plugins_loaded` hook.

**Option B: Manual via WP-CLI**

```bash
wp eval "pb_lti_install_h5p_results_tables();" --path=/path/to/wordpress
```

**Option C: Manual via MySQL**

```bash
mysql -u USER -p DATABASE

# Replace {prefix} with your table prefix (usually 'wp_')
# Then run the SQL from plugin/db/h5p-results-schema.sql
```

### Step 4: Verify Installation

```bash
# Check version option
wp option get pb_lti_h5p_results_db_version

# Expected output: 1.0.0
```

## Post-Installation Configuration

### Enable Debug Logging (Recommended for Testing)

Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Configure LMS (Moodle Example)

Ensure your LTI tool registration includes:

```json
{
  "scopes": [
    "https://purl.imsglobal.org/spec/lti-ags/scope/lineitem",
    "https://purl.imsglobal.org/spec/lti-ags/scope/lineitem.readonly",
    "https://purl.imsglobal.org/spec/lti-ags/scope/score",
    "https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly"
  ]
}
```

### Test Grade Sync

1. Create a test chapter with H5P content:
```
[h5p id="1"]
```

2. Configure grading:
   - Edit chapter
   - Enable "LMS Grade Reporting"
   - Check the H5P activity
   - Select "Best Attempt"
   - Save

3. Launch from LMS and complete activity

4. Check logs:
```bash
tail -f /path/to/wordpress/wp-content/debug.log | grep "H5P Enhanced"
```

5. Verify in LMS gradebook

## Troubleshooting

### Tables Not Created?

```bash
# Manually trigger installation
wp eval "
require_once(WP_PLUGIN_DIR . '/pressbooks-lti-platform/plugin/db/install-h5p-results.php');
pb_lti_install_h5p_results_tables();
"
```

### Meta Box Not Showing?

Check:
1. Post type is 'chapter', 'front-matter', or 'back-matter'
2. User has 'edit_post' capability
3. H5P content exists in chapter (save first)
4. `h5p-results-metabox.php` is loaded in bootstrap

### Grades Not Syncing?

Debug checklist:
```bash
# 1. Check lineitem is stored (per-chapter, per-user post meta on the BOOK blog, e.g. blog 2)
wp db query "SELECT meta_key, meta_value FROM wp_2_postmeta WHERE meta_key LIKE '_lti_ags_lineitem_user_%'" --allow-root
# If empty → student hasn't done a fresh LTI launch yet, OR tool config is missing ltiservice_gradesynchronization=2

# 2. Check global LTI context for user
wp user meta get <USER_ID> _lti_platform_issuer --allow-root
# If empty → student never launched via LTI

# 3. Check grading config
wp db query "SELECT * FROM wp_2_lti_h5p_grading_config WHERE post_id = <CHAPTER_ID>" --allow-root

# 4. Check H5P results
wp db query "SELECT * FROM wp_2_h5p_results WHERE user_id = <USER_ID> LIMIT 5" --allow-root

# 5. Check sync log
wp db query "SELECT * FROM wp_lti_h5p_grade_sync_log ORDER BY synced_at DESC LIMIT 10" --allow-root

# 6. Check Moodle has ltiservice_gradesynchronization=2
# Run on Moodle DB: SELECT name,value FROM mdl_lti_types_config WHERE name='ltiservice_gradesynchronization';
# If missing or 0 → re-run tool registration
```

### Performance Issues?

Add indexes if needed:

```sql
-- If queries are slow
CREATE INDEX idx_h5p_results_user_content ON wp_h5p_results(user_id, content_id);
CREATE INDEX idx_grading_config_post ON wp_lti_h5p_grading_config(post_id, include_in_scoring);
```

## Uninstallation

### Remove Database Tables

```sql
DROP TABLE IF EXISTS wp_lti_h5p_grading_config;
DROP TABLE IF EXISTS wp_lti_h5p_grade_sync_log;
DELETE FROM wp_options WHERE option_name = 'pb_lti_h5p_results_db_version';
```

### Remove Post Meta

```sql
DELETE FROM wp_postmeta WHERE meta_key LIKE '_lti_h5p_grading%';
```

### Remove Files

Delete:
- `plugin/Services/H5PActivityDetector.php`
- `plugin/Services/H5PResultsManager.php`
- `plugin/Services/H5PGradeSyncEnhanced.php`
- `plugin/admin/h5p-results-metabox.php`
- `plugin/db/install-h5p-results.php`
- `plugin/db/h5p-results-schema.sql`

Revert `bootstrap.php` changes.

---

**Questions?** See [H5P_RESULTS_GRADING.md](H5P_RESULTS_GRADING.md) for full documentation.
