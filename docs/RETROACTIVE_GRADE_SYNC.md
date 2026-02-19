# Retroactive Grade Sync - User Guide

## Overview

The **Retroactive Grade Sync** feature allows instructors to synchronize historical H5P grades that were completed **before** the H5P Results grading configuration was enabled for a chapter.

## Use Case

**Scenario**: You have a chapter with H5P activities that students have already completed. You then enable the H5P Results grading feature and configure which activities should be included in the final grade. However, the grades for students who already completed the activities don't automatically appear in the LMS gradebook.

**Solution**: Use the "Sync Existing Grades" button to retroactively send all historical grades to the LMS.

## How to Use

### Step 1: Enable Grading Configuration

1. Edit a chapter that contains H5P activities
2. Scroll to the **"📊 LMS Grade Reporting (LTI AGS)"** meta box
3. Enable grading using the toggle
4. Select which H5P activities to include
5. Choose grading scheme for each activity
6. Save the chapter

### Step 2: Sync Existing Grades

1. In the same meta box, find the **"🔄 Sync Existing Grades"** section
2. Click the **"🔄 Sync Existing Grades to LMS"** button
3. Confirm the action when prompted
4. Wait for the sync to complete (a spinner will appear)
5. Review the results summary

### Step 3: Review Results

The sync results will show:

- **Successfully synced**: Number of students whose grades were posted to LMS
- **Skipped**: Number of students without LTI context (accessed directly, not via LMS)
- **Failed**: Number of students whose grades failed to sync (with error details)

## Important Notes

### Who Gets Synced?

✅ **Synced**: Students who:
- Completed H5P activities in the chapter
- Originally accessed the chapter via LTI launch from the LMS
- Have an active LTI context (lineitem URL stored)

❌ **Skipped**: Students who:
- Accessed the chapter directly (not through LMS)
- Don't have LTI context metadata
- Never launched via LTI

### What Gets Synced?

The sync calculates grades based on your **current grading configuration**:

- **Grading Scheme**: Uses the scheme you configured (Best, Average, First, Last)
- **Aggregation Method**: Combines multiple activity scores as configured (Sum, Average, Weighted)
- **Included Activities**: Only syncs activities you've checked in the configuration

**Example**:
```
Chapter Configuration:
- H5P Activity 1: ✓ Include, Best Attempt
- H5P Activity 2: ✓ Include, Average
- H5P Activity 3: ✗ Exclude

Sync Behavior:
- Calculates best attempt for Activity 1
- Calculates average of all attempts for Activity 2
- Ignores Activity 3 (not included)
- Sums Activity 1 + Activity 2 scores
- Posts final score to LMS
```

### Scale Grading Support

If your LMS activity uses scale grading (e.g., "Competent/Not yet competent"), the sync will:

1. Calculate the percentage score from H5P results
2. Detect the scale type from the LMS lineitem
3. Map the percentage to the appropriate scale value
4. Post the scale value to the gradebook

Supported scales:
- **Default Competence Scale** (2 items): Not yet competent / Competent
- **Separate and Connected Ways of Knowing** (3 items): Mostly separate / Separate and connected / Mostly connected

### Limitations

- **One-time sync**: This is not an automatic background process. You must click the button each time you want to sync historical grades.
- **LTI context required**: Students must have previously accessed the chapter via LTI launch. Direct access to Pressbooks doesn't create LTI context.
- **Current configuration**: Grades are calculated based on the **current** configuration, not the configuration that existed when students completed the activities.
- **No overwrite protection**: If grades were already synced, they will be synced again (which may overwrite manual adjustments in the LMS).

## Troubleshooting

### No Grades Were Synced

**Check:**
1. Grading is enabled for the chapter
2. At least one H5P activity is configured for grading
3. Students have completed the H5P activities
4. Students accessed the chapter via LTI launch (not directly)

**Verify LTI context:**
```bash
# Check if user has LTI metadata
docker exec pressbooks wp user meta list <USER_ID> --path=/var/www/html/web/wp --allow-root | grep lti

# Expected output:
# _lti_ags_lineitem: https://moodle.../mod/lti/services.php/...
# _lti_platform_issuer: https://moodle...
# _lti_user_id: abc123
```

### Partial Success

If some students synced successfully but others were skipped:

- **Skipped students**: Accessed chapter directly (no LTI context)
- **Failed students**: Check error details in the results (click "View Errors")

Common errors:
- `Platform not found for issuer`: LTI platform registration issue
- `OAuth2 token acquisition failed`: Check platform credentials
- `400 Incorrect score received`: Verify LTI user ID format

### Debugging

Enable WordPress debug logging (`wp-config.php`):

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs for sync details:

```bash
docker exec pressbooks tail -f /var/www/html/web/app/debug.log | grep "H5P Sync"
```

Expected log output:
```
[PB-LTI H5P Sync] Found 5 H5P results to potentially sync for post 123
[PB-LTI H5P Sync] User 139 has no LTI context - skipping
[PB-LTI H5P Sync] Using scale grading for user 125: Competent
[PB-LTI H5P Sync] ✅ Synced grade for user 125: 85.00/100.00 (85.0%)
```

## Technical Details

### Database Queries

The sync process:

1. Queries `wp_lti_h5p_grading_config` for chapter configuration
2. Queries `wp_h5p_results` for all historical results
3. Groups results by user
4. For each user:
   - Checks `wp_usermeta` for LTI context (`_lti_ags_lineitem`, `_lti_user_id`)
   - Calculates score using `H5PResultsManager::calculate_chapter_score()`
   - Fetches lineitem from LMS to detect scale type
   - Posts score via `AGSClient::post_score()`
   - Logs sync to `wp_lti_h5p_grade_sync_log`

### Performance

- **Small chapters** (<100 students): Sync completes in seconds
- **Large chapters** (>500 students): May take 30-60 seconds
- **Timeout risk**: If sync times out, reduce the number of students by syncing in batches (contact developer for batch implementation)

### AJAX Implementation

Button click triggers:
- AJAX endpoint: `wp_ajax_pb_lti_sync_existing_grades`
- Handler: `pb_lti_ajax_sync_existing_grades()` in `plugin/ajax/handlers.php`
- Service: `H5PGradeSyncEnhanced::sync_existing_grades()` in `plugin/Services/H5PGradeSyncEnhanced.php`

Security:
- Nonce verification: `check_ajax_referer('pb_lti_sync_grades')`
- Capability check: `current_user_can('edit_post', $post_id)`

## Best Practices

1. **Configure first, sync second**: Always set up your grading configuration and save the chapter before clicking "Sync Existing Grades"

2. **Test with one chapter**: Test the sync on a single chapter before running it on multiple chapters

3. **Communicate with students**: Let students know that grades are being synced retroactively to avoid confusion

4. **Verify in LMS**: After syncing, check the LMS gradebook to confirm grades appear correctly

5. **Document configuration**: Keep a record of your grading configuration (which activities, schemes, aggregation method) for audit purposes

6. **Re-sync if needed**: If you change the grading configuration, you can re-sync to update grades in the LMS

## FAQ

**Q: Will this overwrite grades I manually adjusted in the LMS?**
A: Yes. The sync will post grades based on H5P results, which may overwrite manual adjustments. Consider this before syncing.

**Q: Can I sync grades for a specific student?**
A: Currently, the button syncs all students. Per-student sync requires custom implementation.

**Q: What if a student has multiple attempts?**
A: The sync uses the grading scheme you configured (Best, Average, First, Last) to determine which attempt(s) to use.

**Q: Can I automate this sync to run daily?**
A: Not currently. This is a manual button click. Automated background sync could be implemented via WP-Cron if needed.

**Q: Will future H5P completions sync automatically?**
A: Yes! The normal H5P grade sync (via `h5p_alter_user_result` hook) continues to work automatically for new completions. This button is only for **historical** grades.

---

**Version:** 1.0.0
**Last Updated:** 2026-02-14
**Related Documentation:**
- [Setup Guide](SETUP_GUIDE.md)
- [H5P Results Grading](H5P_RESULTS_GRADING.md)
