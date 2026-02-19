# Go-Live SOP – Pressbooks LTI Platform v2.2.0

**Last Updated:** February 19, 2026
**Plugin Version:** v2.2.0+
**Target Audience:** DevOps, System Administrators, IT Directors

---

## Overview

This Standard Operating Procedure (SOP) covers production deployment of the Pressbooks LTI Platform with all v2.2.0 features, including:
- LTI 1.3 Core + LTI Advantage (AGS)
- Deep Linking 2.0 with interactive content picker
- H5P Results grading with chapter-level configuration
- Chapter-specific grade routing
- Scale grading support
- Retroactive grade synchronization
- Real username and email synchronization

---

## 1. Pre-Go-Live Checklist

### System Requirements

#### Pressbooks Environment
- [ ] Pressbooks Bedrock 6.0+ installed and stable
- [ ] WordPress Multisite configured (subdirectory or subdomain)
- [ ] PHP 8.1+ (8.2+ recommended)
- [ ] MySQL 8.0+ or MariaDB 10.6+
- [ ] HTTPS enabled with valid SSL certificate (required for LTI 1.3)
- [ ] Apache DocumentRoot set to `/path/to/bedrock/web` (critical for Bedrock)
- [ ] WordPress REST API accessible and working
- [ ] H5P plugin installed and active (if using grading features)

**Verify Pressbooks:**
```bash
# Check PHP version
php -v  # Should be 8.1+

# Check required extensions
php -m | grep -E 'curl|json|mysqli|xml|mbstring|zip|gd|intl'

# Verify REST API
curl -I https://your-pressbooks-domain.com/wp-json/

# Verify WordPress core location (Bedrock)
ls /path/to/bedrock/web/wp/wp-load.php  # Should exist
```

#### Moodle Environment
- [ ] Moodle 4.1+ installed and stable
- [ ] External Tool activity module enabled
- [ ] HTTPS enabled with valid SSL certificate
- [ ] Session cookie settings configured for third-party cookies
- [ ] Admin access to configure LTI 1.3 external tools
- [ ] Test course available for pilot deployment

**Verify Moodle Configuration:**
```php
// Check config.php includes these settings (required for LTI cross-domain flow)
$CFG->session_cookie_samesite = 'None';
$CFG->cookiesecure = true;
$CFG->sslproxy = true;  // If behind reverse proxy (Nginx, Apache)
```

#### Plugin Installation
- [ ] Plugin version >= v2.1.0 installed network-wide in Pressbooks
- [ ] Plugin network activated (not just site activated)
- [ ] Composer dependencies installed in plugin directory
- [ ] Database tables created (`wp_lti_platforms`, `wp_lti_deployments`, `wp_lti_nonces`, `wp_lti_keys`)
- [ ] REST API endpoints accessible

**Verify Plugin Installation:**
```bash
# Check plugin status
wp plugin list --status=active-network | grep qbnox-lti-platform

# Verify Composer dependencies
ls /path/to/plugins/qbnox-lti-platform/vendor/firebase/php-jwt/

# Check database tables
wp db query "SHOW TABLES LIKE 'wp_lti_%'" --allow-root

# Test REST API endpoints
curl -I https://your-pressbooks-domain.com/wp-json/pb-lti/v1/keyset
curl -I https://your-pressbooks-domain.com/wp-json/pb-lti/v1/login
curl -I https://your-pressbooks-domain.com/wp-json/pb-lti/v1/launch
curl -I https://your-pressbooks-domain.com/wp-json/pb-lti/v1/deep-link
```

#### Backups
- [ ] Full database backup completed and tested
- [ ] Pressbooks files backup completed
- [ ] Moodle database backup completed (optional, but recommended)
- [ ] Backup restoration procedure documented and tested
- [ ] Rollback window defined (e.g., 24 hours)

#### Documentation
- [ ] User guides distributed to instructors
- [ ] Quick reference cards printed/shared
- [ ] Support escalation path defined
- [ ] Known issues document prepared
- [ ] FAQ document ready for common questions

**Recommended User Documentation:**
- `docs/NEW_FEATURES_2026.md` - Feature overview for instructors
- `docs/INSTRUCTOR_QUICK_REFERENCE.md` - Quick reference card
- `docs/SETUP_GUIDE.md` - Technical installation guide

---

## 2. Configuration Steps

### Step 2.1: Generate RSA Key Pair

The platform uses RS256 JWT signing. Generate a production key pair:

```bash
# Navigate to plugin directory
cd /path/to/pressbooks/web/app/plugins/qbnox-lti-platform/

# Generate RSA key pair
php scripts/generate-rsa-keys.php

# Verify key stored in database
wp db query "SELECT kid, created_at FROM wp_lti_keys" --allow-root
```

**Expected Output:**
```
RSA key pair generated successfully!
Kid: pb-lti-2024
Key stored in wp_lti_keys table
```

### Step 2.2: Register LMS Platform

Register Moodle as a trusted LTI platform:

**Via Database:**
```sql
INSERT INTO wp_lti_platforms (
    issuer,
    client_id,
    auth_url,
    token_url,
    key_set_url,
    created_at
) VALUES (
    'https://your-moodle-domain.com',                          -- Platform issuer URL
    'pressbooks-lti-client-id',                                -- Client ID (generate unique)
    'https://your-moodle-domain.com/mod/lti/auth.php',        -- OIDC auth endpoint
    'https://your-moodle-domain.com/mod/lti/token.php',       -- OAuth2 token endpoint
    'https://your-moodle-domain.com/mod/lti/certs.php',       -- Platform JWKS endpoint
    NOW()
);
```

**Or Via WP-CLI:**
```bash
wp eval '
global $wpdb;
$wpdb->insert($wpdb->prefix . "lti_platforms", [
    "issuer" => "https://your-moodle-domain.com",
    "client_id" => "pressbooks-lti-client-id",
    "auth_url" => "https://your-moodle-domain.com/mod/lti/auth.php",
    "token_url" => "https://your-moodle-domain.com/mod/lti/token.php",
    "key_set_url" => "https://your-moodle-domain.com/mod/lti/certs.php",
    "created_at" => current_time("mysql")
]);
echo "Platform registered!\n";
' --allow-root
```

### Step 2.3: Register Deployment ID

Register the deployment ID for your Moodle instance:

```sql
INSERT INTO wp_lti_deployments (
    platform_issuer,
    deployment_id,
    created_at
) VALUES (
    'https://your-moodle-domain.com',  -- Must match platform issuer
    '1',                                -- Moodle deployment ID (usually '1')
    NOW()
);
```

### Step 2.4: Configure Moodle LTI 1.3 External Tool

In Moodle: **Site Administration → Plugins → Activity modules → External tool → Manage tools**

Click **Configure a tool manually** and enter:

**Basic Settings:**
- Tool name: `Pressbooks LTI Platform`
- Tool URL: `https://your-pressbooks-domain.com`
- LTI version: `LTI 1.3`
- Public key type: `Keyset URL`
- Public keyset URL: `https://your-pressbooks-domain.com/wp-json/pb-lti/v1/keyset`
- Initiate login URL: `https://your-pressbooks-domain.com/wp-json/pb-lti/v1/login`
- Redirection URI(s):
  ```
  https://your-pressbooks-domain.com/wp-json/pb-lti/v1/launch
  https://your-pressbooks-domain.com/wp-json/pb-lti/v1/deep-link
  ```

**Services:**
- ✅ IMS LTI Assignment and Grade Services (score: POST)
- ✅ IMS LTI Assignment and Grade Services (lineitem: GET)
- ✅ IMS LTI Assignment and Grade Services (lineitem: POST)
- ✅ Deep Linking (content selection)

**Privacy Settings:**
- ✅ Share launcher's name with tool
- ✅ Share launcher's email with tool
- ✅ Accept grades from the tool

**Advanced Settings:**
- Tool configuration usage: `Show in activity chooser and as a preconfigured tool`
- Default launch container: `Embed, without blocks`

**Copy these values from Moodle after saving:**
- Platform ID (issuer URL)
- Client ID
- Deployment ID

### Step 2.5: Store Client Secret (If Required)

Some LMS platforms require a shared secret. Store via Network Admin UI or database:

```sql
-- Note: Actual implementation may vary based on plugin version
UPDATE wp_lti_platforms
SET client_secret = 'your-secret-here'
WHERE client_id = 'pressbooks-lti-client-id';
```

### Step 2.6: Enable CORS for Bidirectional Logout (Optional but Recommended)

To enable automatic logout when Moodle session expires:

```bash
cd /path/to/project/root/
bash scripts/enable-moodle-cors.sh
```

**Verify CORS:**
```bash
curl -i -H "Origin: https://your-pressbooks-domain.com" \
     -X OPTIONS \
     https://your-moodle-domain.com/lib/ajax/service.php

# Should include these headers:
# Access-Control-Allow-Origin: https://your-pressbooks-domain.com
# Access-Control-Allow-Credentials: true
```

### Step 2.7: Configure Moodle Email/Username Sharing

Ensure Moodle sends name and email information:

```bash
bash scripts/enable-email-sharing.sh
```

**Verify settings in Moodle database:**
```sql
SELECT name, value FROM mdl_lti_types_config
WHERE typeid = (SELECT id FROM mdl_lti_types WHERE name = 'Pressbooks LTI Platform')
AND name IN ('sendname', 'sendemailaddr');

-- Expected:
-- sendname = 1
-- sendemailaddr = 1
```

---

## 3. Validation & Testing

### Step 3.1: Test Basic LTI Launch

**Test as Instructor:**
1. Log into Moodle as instructor
2. Create new course or use test course
3. Add activity → External tool → Pressbooks LTI Platform
4. Click activity to launch
5. Verify:
   - [ ] Redirect to Pressbooks successful
   - [ ] Logged in automatically (SSO)
   - [ ] Correct username (Moodle username, not firstname.lastname)
   - [ ] Correct email address
   - [ ] Instructor role assigned in Pressbooks
   - [ ] No errors in browser console (F12)

**Test as Student:**
1. Log into Moodle as student
2. Enroll in test course
3. Click Pressbooks activity
4. Verify:
   - [ ] Redirect successful
   - [ ] Logged in automatically
   - [ ] Correct username and email
   - [ ] Student/Subscriber role assigned
   - [ ] No errors in browser console

**Check Logs:**
```bash
# Pressbooks logs
docker exec pressbooks tail -50 /var/www/html/web/app/debug.log | grep "PB-LTI"

# Expected log entries:
# [PB-LTI Launch] Received launch request from: https://your-moodle-domain.com
# [PB-LTI Launch] User created/updated: username
# [PB-LTI Launch] Redirecting to: https://...
```

### Step 3.2: Test Deep Linking Content Selection

**Test Flow:**
1. As instructor, add new activity → External tool → Pressbooks
2. Click **Select content** button
3. Verify content picker opens with:
   - [ ] All books displayed as cards
   - [ ] Book covers and descriptions visible
   - [ ] "Show Chapters" button on each book
4. Click on a book card → Click "Show Chapters"
5. Verify:
   - [ ] Chapters expand with color-coded badges (Front/Chapter/Back)
   - [ ] Checkboxes appear for each chapter
   - [ ] "Select All" / "Deselect All" buttons work
6. Select 2-3 chapters → Click "Select This Content"
7. Verify modal appears with selected chapters
8. Confirm selection
9. Verify:
   - [ ] Return to Moodle activity settings
   - [ ] Activity name populated with first chapter title
   - [ ] Save activity
   - [ ] Multiple activities created (one per selected chapter)

**Detailed Testing Guide:** See `docs/DEEP_LINKING_CONTENT_PICKER.md`

### Step 3.3: Test H5P Grade Sync

**Setup:**
1. Create/edit a chapter with H5P activities
2. In Pressbooks editor, find "H5P Results Grading" meta box
3. Enable grading for the chapter
4. Select H5P activities to include
5. Set weights (e.g., Quiz 1: 50%, Quiz 2: 50%)
6. Choose aggregation method (Weighted Average)
7. Save chapter

**Test Flow:**
1. As student, launch chapter from Moodle
2. Complete H5P activities
3. Verify in Pressbooks:
   - [ ] H5P completion recorded (check H5P results table)
   - [ ] Check debug logs for grade calculation
4. Verify in Moodle:
   - [ ] Grade appears in gradebook
   - [ ] Correct grade value (based on H5P scores and weights)
   - [ ] Grade appears in correct column (chapter-specific)

**Check Grade Sync Logs:**
```bash
docker exec pressbooks tail -100 /var/www/html/web/app/debug.log | grep -E "H5P|AGS"

# Expected entries:
# [PB-LTI H5P Enhanced] User X completed H5P Y with score Z
# [PB-LTI H5P Enhanced] Calculated chapter score: A/B
# [PB-LTI AGS] Posting grade to: https://moodle.../lineitem/...
# [PB-LTI AGS] Grade posted successfully
```

**Detailed Testing Guide:** See `docs/H5P_RESULTS_GRADING.md`

### Step 3.4: Test Chapter-Specific Grade Routing

**Critical Test** (addresses production bug fix from v2.1.0):

1. As student, launch **Chapter 1** from Moodle
2. Complete H5P activity in Chapter 1
3. Verify grade posts to **Chapter 1 gradebook column**
4. As same student, launch **Chapter 2** from Moodle
5. Complete H5P activity in Chapter 2
6. Verify:
   - [ ] Chapter 2 grade posts to **Chapter 2 gradebook column** (not Chapter 1)
   - [ ] Chapter 1 grade still visible in Chapter 1 column
   - [ ] No grade overwriting

**Check Lineitem Storage:**
```bash
docker exec pressbooks wp eval '
$post_id = 123;  // Chapter post ID
$user_id = 456;  // WordPress user ID
$lineitem_key = "_lti_ags_lineitem_user_" . $user_id;
$lineitem = get_post_meta($post_id, $lineitem_key, true);
echo "Chapter $post_id, User $user_id lineitem: $lineitem\n";
' --allow-root
```

### Step 3.5: Test Retroactive Grade Sync

**Setup:**
1. Have students complete H5P activities in a chapter
2. Configure grading for that chapter AFTER completion
3. Click "🔄 Sync Existing Grades to LMS" in meta box

**Verify:**
- [ ] Confirmation dialog appears
- [ ] Sync completes with success/skip/fail counts
- [ ] Historical grades appear in Moodle gradebook
- [ ] Only students with LTI context synced
- [ ] Direct-access students skipped appropriately

**Detailed Guide:** See `docs/RETROACTIVE_GRADE_SYNC.md`

### Step 3.6: Test Scale Grading

**Setup:**
1. In Moodle, create activity with scale grading (not points)
2. Use "Default competence scale" or "Separate and Connected ways of knowing"
3. Configure H5P grading in Pressbooks for that chapter

**Test:**
1. Student completes H5P with score < 50%
2. Verify Moodle shows "Not yet competent" (for competence scale)
3. Student completes H5P with score >= 50%
4. Verify Moodle shows "Competent"

**Supported Scales:**
- Default Competence Scale (2 items: Not yet competent / Competent)
- Separate and Connected Ways of Knowing (3 items)

### Step 3.7: Verify Audit Logs

Check that all LTI events are being logged:

```bash
# Check recent LTI launches
docker exec pressbooks wp db query "
SELECT * FROM wp_lti_nonces
ORDER BY used_at DESC LIMIT 10
" --allow-root

# Check grade postings (if AGS audit table exists)
# Note: Actual audit logging implementation may vary
docker exec pressbooks tail -100 /var/www/html/web/app/debug.log | grep "PB-LTI"
```

---

## 4. Pilot Deployment

### Step 4.1: Select Pilot Course

**Criteria for Pilot Course:**
- Small enrollment (10-30 students)
- Willing instructor (early adopter)
- Mix of content types (chapters with/without H5P)
- Active course (not archived)
- Instructor available for feedback

### Step 4.2: Instructor Training

**Training Checklist:**
- [ ] Provide instructor with quick reference guide (`docs/INSTRUCTOR_QUICK_REFERENCE.md`)
- [ ] Walk through Deep Linking content selection
- [ ] Demonstrate H5P grading configuration
- [ ] Show how to use retroactive grade sync
- [ ] Explain student experience (SSO, logout)
- [ ] Review gradebook in Moodle (where grades appear)
- [ ] Provide support contact information
- [ ] Schedule follow-up check-in (1 week)

### Step 4.3: Student Communication

**Email Template:**
```
Subject: New Pressbooks Integration in [Course Name]

Dear Students,

Starting [Date], you'll access course readings through a new Pressbooks integration in Moodle.

What's New:
- Click on reading activities to access content
- You'll be logged in automatically (single sign-on)
- Your quiz grades will sync automatically to the gradebook
- To log out, click "Return to LMS" in the top bar

What You Need to Do:
- Nothing! Just click on activities as usual
- Make sure your browser allows cookies from both Moodle and Pressbooks

Need Help?
Contact [Support Email/Phone]

Thank you,
[Instructor Name]
```

### Step 4.4: Monitor Pilot (First 72 Hours)

**Daily Monitoring:**
- [ ] Check Pressbooks debug logs for errors
- [ ] Review Moodle LTI launch logs
- [ ] Monitor grade sync success rate
- [ ] Check student feedback/questions
- [ ] Verify no authentication failures
- [ ] Confirm gradebook accuracy

**Log Monitoring Commands:**
```bash
# Real-time log monitoring
docker exec pressbooks tail -f /var/www/html/web/app/debug.log | grep "PB-LTI"

# Count LTI launches today
docker exec pressbooks wp db query "
SELECT COUNT(*) as launches_today
FROM wp_lti_nonces
WHERE DATE(used_at) = CURDATE()
" --allow-root

# Check for errors
docker exec pressbooks grep -i "error\|failed\|exception" /var/www/html/web/app/debug.log | tail -20
```

### Step 4.5: Collect Feedback

**Instructor Feedback Questions:**
1. How easy was Deep Linking content selection?
2. Did H5P grade sync work as expected?
3. Were there any student issues?
4. What documentation was most helpful?
5. What improvements would you suggest?

**Student Feedback (Optional Survey):**
1. Did you have any login issues?
2. Were grades updated in a timely manner?
3. Was the reading experience smooth?
4. Any technical problems?

---

## 5. Full Production Go-Live

### Step 5.1: Review Pilot Results

**Go/No-Go Criteria:**
- [ ] No critical bugs identified
- [ ] LTI launch success rate > 95%
- [ ] Grade sync success rate > 90%
- [ ] Instructor satisfaction (positive feedback)
- [ ] No data loss or corruption
- [ ] Support tickets < 5% of pilot users
- [ ] Performance acceptable (launch time < 3 seconds)

**If No-Go:**
- Document issues
- Create remediation plan
- Extend pilot period
- Do NOT proceed to full rollout

### Step 5.2: Gradual Rollout Plan

**Recommended Approach:**
1. **Week 1:** 1-2 pilot courses (completed)
2. **Week 2:** 5-10 early adopter courses
3. **Week 3:** 20-30 courses
4. **Week 4:** 50+ courses
5. **Week 5+:** All remaining courses

**Per-Week Checklist:**
- [ ] Instructor training sessions scheduled
- [ ] Student communication sent
- [ ] Support capacity increased
- [ ] Monitoring dashboard reviewed daily
- [ ] Feedback collected and addressed

### Step 5.3: Production Configuration Lock

**Freeze Configuration Changes:**
- [ ] No plugin updates during first 2 weeks
- [ ] No Moodle external tool config changes
- [ ] No Pressbooks core updates
- [ ] No server configuration changes
- [ ] Document all emergency-only change procedures

### Step 5.4: Support Escalation

**Define Support Tiers:**

**Tier 1 (Help Desk):**
- Handle: Login issues, basic questions, password resets
- Escalate: Grade sync failures, LTI launch errors, data issues

**Tier 2 (System Administrators):**
- Handle: Configuration issues, log analysis, grade sync debugging
- Escalate: Plugin bugs, security issues, data corruption

**Tier 3 (Developers):**
- Handle: Code-level bugs, urgent patches, emergency rollback

**Support Contact Card:**
```
PRESSBOOKS LTI SUPPORT

Tier 1 (Help Desk): helpdesk@example.com / x1234
Tier 2 (Sys Admin): sysadmin@example.com / x5678
Tier 3 (Emergency): devops@example.com / x9999

Normal Hours: Mon-Fri 8am-5pm
After Hours: Emergency only (x9999)

Known Issues: https://your-domain.com/lti-known-issues
Documentation: https://github.com/your-org/qbnox-lti-platform/docs
```

---

## 6. Rollback Plan

### When to Rollback

**Immediate Rollback Triggers:**
- Authentication failures > 10% of launches
- Data corruption or grade loss
- Security vulnerability discovered
- System performance degradation (launch time > 10 seconds)
- Critical plugin bug affecting all users

**Planned Rollback Triggers:**
- User satisfaction < 50%
- Support tickets > 20% of active users
- Grade sync failures > 20%
- Instructor adoption < 30% after 4 weeks

### Rollback Procedure

**Step 1: Disable Plugin (Immediate)**
```bash
# Network deactivate plugin
wp plugin deactivate qbnox-lti-platform --network --allow-root

# Verify disabled
wp plugin list | grep qbnox-lti-platform
# Should show: inactive
```

**Step 2: Disable Moodle External Tool**

In Moodle: **Site Administration → Plugins → External tool → Manage tools**
- Click on "Pressbooks LTI Platform"
- Set **Tool status** to: `Disabled`
- Click **Save changes**

**Step 3: Notify Users**
```
Subject: Pressbooks Integration Temporarily Disabled

The Pressbooks LTI integration has been temporarily disabled due to [REASON].

Impact:
- Pressbooks content is not accessible via Moodle activities
- Grades will not sync automatically
- Direct access to Pressbooks still available at [URL]

We are working to resolve the issue and will notify you when service is restored.

Estimated Resolution: [TIME]

For urgent access to grades, contact [SUPPORT EMAIL].
```

**Step 4: Restore Database (If Data Corruption)**
```bash
# Stop Pressbooks container (if using Docker)
docker-compose stop pressbooks

# Restore database from backup
mysql -u root -p pressbooks_db < backup-YYYY-MM-DD.sql

# Verify restoration
mysql -u root -p -e "SELECT COUNT(*) FROM pressbooks_db.wp_lti_platforms"

# Restart Pressbooks
docker-compose start pressbooks
```

**Step 5: Root Cause Analysis**
- [ ] Collect all error logs
- [ ] Review audit trail
- [ ] Identify failure point
- [ ] Document lessons learned
- [ ] Create remediation plan
- [ ] Update this SOP with preventative measures

### Data Preservation During Rollback

**What Gets Preserved:**
- User accounts created via LTI (remain in Pressbooks)
- Grades posted to Moodle (remain in gradebook)
- H5P completion records (remain in H5P tables)
- LTI configuration in database (remains for re-enable)

**What Gets Lost:**
- Active LTI sessions (users logged out)
- OAuth2 token cache (regenerated on re-enable)
- Nonce history (cleaned up automatically)

**User Data Handling:**
```bash
# Export user data before rollback (if needed)
wp user list --field=ID --meta_key=_lti_user_id | while read uid; do
    wp user meta get $uid _lti_user_id
    wp user meta get $uid _lti_ags_lineitem
done > lti-user-data-backup.txt
```

---

## 7. Post-Go-Live Operations

### Week 1: Intensive Monitoring

**Daily Tasks:**
- [ ] Review debug logs (morning and evening)
- [ ] Check grade sync success rate
- [ ] Monitor support ticket volume
- [ ] Respond to instructor feedback
- [ ] Document issues and workarounds
- [ ] Update known issues list

**Metrics to Track:**
```bash
# LTI launches per day
docker exec pressbooks wp db query "
SELECT DATE(used_at) as date, COUNT(*) as launches
FROM wp_lti_nonces
WHERE used_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(used_at)
" --allow-root

# Unique users per day
docker exec pressbooks wp db query "
SELECT DATE(used_at) as date, COUNT(DISTINCT user_id) as unique_users
FROM wp_usermeta
WHERE meta_key = '_lti_last_launch'
AND meta_value >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(used_at)
" --allow-root
```

### Weeks 2-4: Regular Monitoring

**Weekly Tasks:**
- [ ] Review aggregated metrics
- [ ] Conduct instructor check-ins
- [ ] Update documentation based on feedback
- [ ] Plan training for next wave of courses
- [ ] Review and close resolved issues

**Monthly Tasks:**
- [ ] Audit log review (security events, failures)
- [ ] Performance analysis (launch times, grade sync times)
- [ ] User satisfaction survey
- [ ] Documentation review and updates

### Ongoing Maintenance

**Weekly:**
- [ ] Review error logs
- [ ] Monitor grade sync failures
- [ ] Check OAuth2 token cache health

**Monthly:**
- [ ] Review and rotate client secrets (optional, but recommended)
- [ ] Update documentation with new use cases
- [ ] Plan instructor training sessions
- [ ] Review support tickets for patterns

**Quarterly:**
- [ ] Security audit (SQL injection, XSS, CSRF)
- [ ] Performance testing with production load
- [ ] Backup and restore testing
- [ ] Disaster recovery drill
- [ ] Review and update this SOP

**Annually:**
- [ ] Plugin version upgrade
- [ ] Review LTI 1.3 specification updates
- [ ] Evaluate new features (IMS Global standards)
- [ ] Comprehensive security audit
- [ ] User satisfaction survey

### Security Maintenance

**Secret Rotation (Monthly Recommended):**
```bash
# Generate new RSA key pair
php scripts/generate-rsa-keys.php

# Update Moodle with new public key from JWKS endpoint
# (Moodle should fetch automatically if using keyset URL)

# Verify both old and new keys work (grace period)

# After 24 hours, remove old key
wp db query "DELETE FROM wp_lti_keys WHERE kid = 'old-kid-here'" --allow-root
```

**Log Retention:**
- Keep debug logs for 90 days
- Archive audit logs for 1 year
- Retain compliance evidence for 7 years (if required)

---

## 8. Success Metrics

### Technical Metrics

**Availability:**
- Target: 99.5% uptime
- Measure: LTI launch success rate
- Alert: < 95% success rate in 1-hour window

**Performance:**
- Target: LTI launch < 3 seconds (95th percentile)
- Target: Grade sync < 2 seconds per grade
- Measure: Server response times, log timestamps

**Reliability:**
- Target: Grade sync success rate > 95%
- Target: Zero data loss incidents
- Measure: AGS POST success rate, audit logs

### User Metrics

**Adoption:**
- Target: 80% of courses using LTI within 6 months
- Target: 90% of instructors rate "satisfied" or higher
- Measure: Course enrollment counts, feedback surveys

**Support:**
- Target: < 5% of users submit support tickets
- Target: Mean time to resolution < 4 hours
- Measure: Ticket tracking system

**Satisfaction:**
- Target: Net Promoter Score > 40
- Target: 85% of students report "no issues"
- Measure: Quarterly surveys

---

## 9. Documentation & Resources

### User Documentation
- **Feature Overview:** `docs/NEW_FEATURES_2026.md`
- **Quick Reference:** `docs/INSTRUCTOR_QUICK_REFERENCE.md`
- **Installation Guide:** `docs/SETUP_GUIDE.md`
- **H5P Grading Guide:** `docs/H5P_RESULTS_GRADING.md`
- **Deep Linking Guide:** `docs/DEEP_LINKING_CONTENT_PICKER.md`
- **Retroactive Sync Guide:** `docs/RETROACTIVE_GRADE_SYNC.md`

### Technical Documentation
- **Test Checklist:** `docs/testing/PRESSBOOKS_MOODLE_TEST_CHECKLIST.md`
- **Architecture Overview:** `ARCHITECTURE.md`
- **Development Guide:** `SETUP_GUIDE.md` (Part 3) / `CLAUDE.md`

### Compliance Documentation
- **1EdTech Certification:** `docs/compliance/1EDTECH_CERTIFICATION_MAPPING.md`
- **ISO 27001 Controls:** `docs/compliance/ISO27001_2022_CONTROL_MAPPING.md`
- **SOC 2 Framework:** `docs/compliance/SOC2_CONTROL_MATRIX.md`

### Support Resources
- **GitHub Issues:** https://github.com/ugenkudupudiqbnox/qbnox-lti-platform/issues
- **Release Notes:** https://github.com/ugenkudupudiqbnox/qbnox-lti-platform/releases
- **Community Forum:** (if available)

---

## 10. Appendix

### A. Common Issues & Solutions

**Issue: LTI Launch Returns 401 Unauthorized**
- **Cause:** JWT signature verification failed
- **Solution:** Verify Moodle has correct public key from JWKS endpoint
- **Check:** `curl https://your-pressbooks-domain.com/wp-json/pb-lti/v1/keyset`

**Issue: Grades Not Appearing in Moodle**
- **Cause:** Chapter-specific lineitem not stored
- **Solution:** Have student launch chapter again (fresh launch stores lineitem)
- **Check Logs:** Look for `[PB-LTI] Extracted post_id X from URL`

**Issue: Username Shows as firstname.lastname**
- **Cause:** Moodle not sending preferred_username claim
- **Solution:** Enable username sharing in Moodle external tool settings
- **Check:** `bash scripts/enable-email-sharing.sh`

**Issue: Bidirectional Logout Not Working**
- **Cause:** CORS not enabled on Moodle
- **Solution:** Run `bash scripts/enable-moodle-cors.sh`
- **Verify:** Check browser console for CORS errors

**Issue: Deep Linking Returns "Consumer key is incorrect"**
- **Cause:** JWT iss/aud claims incorrect for tool→platform messages
- **Solution:** Verify plugin version >= v2.1.0 (fixed in this version)
- **Check:** JWT should have `iss = client_id`, `aud = platform_issuer`

### B. Emergency Contacts

**Production Issue (Business Hours):**
- Tier 1 Support: [EMAIL/PHONE]
- Tier 2 System Admin: [EMAIL/PHONE]

**Critical Security Issue (Anytime):**
- Security Team: [EMAIL/PHONE]
- Follow: `SECURITY.md` disclosure process

**Plugin Developer (Non-Emergency):**
- GitHub Issues: https://github.com/ugenkudupudiqbnox/qbnox-lti-platform/issues
- Email: [DEVELOPER EMAIL]

### C. Changelog

**v2.2.0 (2026-02-19):**
- Updated for v2.2.0 release
- Removed Session Monitor (bidirectional logout) — feature removed from codebase
- Updated documentation references

**v2.1.0 (2026-02-15):**
- Added comprehensive production deployment guide
- Added detailed testing procedures, success metrics and monitoring

---

**Document Version:** 2.2.0
**Last Updated:** February 19, 2026
**Next Review Date:** May 19, 2026
**Document Owner:** DevOps Team
