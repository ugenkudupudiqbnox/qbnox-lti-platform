# Pressbooks LTI Platform — Setup & Developer Guide

**Audience:** IT/Moodle administrators, Pressbooks network administrators, plugin developers and contributors.

---

## Table of Contents

1. [Overview](#overview)
2. [System Requirements](#system-requirements)
3. [Part 1 — IT Administrator Setup](#part-1--it-administrator-setup)
   - [Pre-Installation Checklist](#pre-installation-checklist)
   - [Step 1: Install the Plugin on Pressbooks](#step-1-install-the-plugin-on-pressbooks)
   - [Step 2: Register Pressbooks in Moodle](#step-2-register-pressbooks-in-moodle)
   - [Step 3: Register Moodle in Pressbooks](#step-3-register-moodle-in-pressbooks)
   - [Step 4: Verify the Integration](#step-4-verify-the-integration)
   - [Step 5: Production Hardening](#step-5-production-hardening)
4. [Part 2 — Instructor Configuration](#part-2--instructor-configuration)
   - [Adding Chapters via Deep Linking](#adding-chapters-via-deep-linking)
   - [Enabling H5P Grade Sync on a Chapter](#enabling-h5p-grade-sync-on-a-chapter)
   - [Syncing Historical Grades](#syncing-historical-grades)
5. [Part 3 — Developer & Contributor Guide](#part-3--developer--contributor-guide)
   - [Local Development Setup](#local-development-setup)
   - [Repository Structure](#repository-structure)
   - [Architecture: LTI 1.3 Request Flow](#architecture-lti-13-request-flow)
   - [Key Design Decisions](#key-design-decisions)
   - [Security Requirements](#security-requirements)
   - [Testing](#testing)
   - [Contributing](#contributing)
6. [Troubleshooting](#troubleshooting)
7. [Upgrading & Uninstalling](#upgrading--uninstalling)

---

## Overview

This plugin provides **LTI 1.3 + LTI Advantage** connectivity between Pressbooks and LMS platforms (Moodle, Canvas, Blackboard, Brightspace). It is production-grade infrastructure — not a demo — aligned with 1EdTech certification, ISO 27001, and SOC 2.

**What it enables:**

| Feature | Description |
|---------|-------------|
| Single Sign-On | Students launch Pressbooks content from Moodle without a separate login |
| Deep Linking 2.0 | Instructors browse and select specific chapters via a content picker |
| Grade Sync (AGS) | H5P activity scores post automatically to the Moodle gradebook |
| Security | RSA JWT validation, nonce replay protection, AES-256-GCM secret vault, audit log |

---

## System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| Moodle | 4.1 | 4.4+ |
| Pressbooks | 6.0 | Latest |
| PHP | 8.1 | 8.2 |
| MySQL / MariaDB | 8.0 / 10.6 | Latest |
| HTTPS | Required | Required |

**Required PHP extensions:** `curl`, `json`, `mysqli`, `xml`, `mbstring`, `zip`, `gd`, `intl`, `opcache`

---

## Part 1 — IT Administrator Setup

### Pre-Installation Checklist

- [ ] HTTPS enabled on both Moodle and Pressbooks (LTI 1.3 requires it)
- [ ] Server clocks synchronized via NTP — JWT signatures fail if clocks differ by more than 60 seconds
- [ ] Pressbooks REST API is accessible: `curl https://your-pb-domain/wp-json/`
- [ ] Site Administrator access to Moodle
- [ ] Network Administrator access to Pressbooks

---

### Step 1: Install the Plugin on Pressbooks

#### Option A: Git (recommended)

```bash
cd /path/to/pressbooks/web/app/plugins/
git clone https://github.com/ugenkudupudiqbnox/qbnox-lti-platform.git qbnox-lti-platform
chown -R www-data:www-data qbnox-lti-platform
wp plugin activate qbnox-lti-platform --network --allow-root
```

#### Option B: Composer (Bedrock)

Add to your root `composer.json`:

```json
{
  "repositories": [
    {"type": "vcs", "url": "https://github.com/ugenkudupudiqbnox/qbnox-lti-platform"}
  ],
  "require": {
    "ugenkudupudiqbnox/qbnox-lti-platform": "^2.1"
  }
}
```

```bash
composer update
wp plugin activate qbnox-lti-platform --network --allow-root
```

#### Verify Plugin Installation

```bash
# Plugin is network-active
wp plugin list --status=active-network --allow-root | grep qbnox-lti-platform

# JWKS endpoint responds with RSA key
curl https://your-pb-domain/wp-json/pb-lti/v1/keyset
# Expected: {"keys":[{"kty":"RSA","use":"sig",...}]}

# Database tables were auto-created
wp db query "SHOW TABLES LIKE 'wp_lti_%'" --allow-root
# Expected: wp_lti_platforms, wp_lti_deployments, wp_lti_nonces, wp_lti_keys
```

> If the keyset endpoint returns 404, flush rewrite rules: `wp rewrite flush --allow-root`

---

### Step 2: Register Pressbooks in Moodle

1. Log in to Moodle as **Site Administrator**
2. Go to **Site administration → Plugins → Activity modules → External tool → Manage tools**
3. Click **Configure a tool manually**

Fill in (replace `https://pb.example.com` with your Pressbooks domain):

| Field | Value |
|-------|-------|
| Tool name | `Pressbooks` |
| Tool URL | `https://pb.example.com` |
| LTI version | `LTI 1.3` |
| Public key type | `Keyset URL` |
| Public keyset URL | `https://pb.example.com/wp-json/pb-lti/v1/keyset` |
| Initiate login URL | `https://pb.example.com/wp-json/pb-lti/v1/login` |
| Redirection URI(s) | `https://pb.example.com/wp-json/pb-lti/v1/launch` |
| Content Selection URL | `https://pb.example.com/wp-json/pb-lti/v1/deep-link` |

**Services tab:**

| Service | Setting |
|---------|---------|
| IMS LTI Assignment and Grade Services | **Use this service for grade sync and column management** |
| IMS LTI Names and Role Provisioning | Use this service to retrieve members' information |

Also check: **Supports Deep Linking (Content-Item Message)**

**Privacy tab:**

| Setting | Value |
|---------|-------|
| Share launcher's name with tool | Always |
| Share launcher's email with tool | Always |
| Accept grades from the tool | **Delegate to teacher** |

> **Critical:** "Accept grades from the tool" must be **Delegate to teacher**. Any other setting prevents grade columns from being created when chapters are added via Deep Linking.

4. Click **Save changes**
5. On the tools list, click the **deployment icon** (list/chain icon) next to your new tool — note down the **Client ID** and **Deployment ID** for the next step.

---

### Step 3: Register Moodle in Pressbooks

This registers Moodle as a trusted platform so Pressbooks can validate JWT signatures.

#### Option A: CLI script (recommended)

The script is at `scripts/pressbooks-register-platform.php`. Before running, open it and update line 3 to match your WordPress root path:

```php
require_once('/your/actual/path/to/wp-load.php');
```

Then run on your Pressbooks server:

```bash
php /path/to/qbnox-lti-platform/scripts/pressbooks-register-platform.php \
  "https://your-moodle-domain.com" \
  "YOUR_CLIENT_ID" \
  "YOUR_DEPLOYMENT_ID"
```

#### Option B: Pressbooks Network Admin UI

1. Log in as Network Administrator
2. Navigate to **Network Admin → Settings → LTI Platforms → Add New Platform**
3. Fill in:

| Field | Value |
|-------|-------|
| Issuer | `https://your-moodle-domain.com` |
| Client ID | From Step 2 |
| Deployment ID | From Step 2 |
| Auth Login URL | `https://your-moodle-domain.com/mod/lti/auth.php` |
| Auth Token URL | `https://your-moodle-domain.com/mod/lti/token.php` |
| Public Keyset URL | `https://your-moodle-domain.com/mod/lti/certs.php` |

---

### Step 4: Verify the Integration

1. In Moodle as an instructor, add **External Tool → Pressbooks → Select content**
2. The Pressbooks content picker should open (Deep Linking)
3. Select a chapter and save — Moodle creates the activity
4. As a student, click the activity — should auto-login to Pressbooks and open the chapter

Check logs for errors:

```bash
grep "PB-LTI" /path/to/wordpress/wp-content/debug.log | tail -30
```

---

### Step 5: Production Hardening

#### Cookie configuration (required for iframe embedding)

LTI 1.3 runs Pressbooks inside a Moodle iframe. Modern browsers block third-party cookies by default, which breaks session handling. Configure your web server:

**Nginx (recommended):**

```nginx
# In the Pressbooks server block
proxy_cookie_path / "/; SameSite=None; Secure";
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
proxy_set_header Host $http_host;
proxy_hide_header X-Frame-Options;
add_header Content-Security-Policy "frame-ancestors 'self' *.yourdomain.com moodle.yourdomain.com";
```

**Apache (`wp-config.php`):**

```php
// Force SameSite=None on WordPress session cookies
@ini_set('session.cookie_samesite', 'None');
@ini_set('session.cookie_secure', '1');
```

#### Suppress PHP display errors

PHP warnings printed before headers can break LTI redirects (Moodle logs "Error output, so disabling automatic redirect"). Add a PHP ini file:

```ini
; /usr/local/etc/php/conf.d/zz-suppress-warnings.ini
display_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING
```

#### Moodle cron

Grade processing and LTI service tasks require Moodle's cron to run every minute:

```bash
# Add to crontab (replace /usr/bin/php with your PHP path)
* * * * * /usr/bin/php /var/www/moodle/admin/cli/cron.php >> /var/log/moodle-cron.log 2>&1
```

---

## Part 2 — Instructor Configuration

> **Quick Reference:** For a one-page summary of all instructor tasks, see [INSTRUCTOR_QUICK_REFERENCE.md](INSTRUCTOR_QUICK_REFERENCE.md). This section covers the same topics in full detail.

### Adding Chapters via Deep Linking

**Always use Deep Linking** when adding chapters that require grade sync. Deep Linking is what tells Moodle to create a grade column — manually entering a URL will not create one.

1. In your Moodle course, click **+ Add an activity or resource → External tool**
2. Select **Pressbooks** as the preconfigured tool
3. Click **Select content**
4. In the picker: expand a book → select a chapter → click **Add Selected Chapters**
5. Moodle creates one activity per chapter, with a grade column if H5P grading is enabled on that chapter

> In the activity settings, check **"Allow Pressbooks LTI Platform to add grades in the gradebook"** to enable the grading options. This checkbox only appears if the tool was configured with "Accept grades from the tool: Delegate to teacher".

---

### Enabling H5P Grade Sync on a Chapter

Configured in Pressbooks, not in Moodle:

1. Open the chapter in the Pressbooks editor
2. Find the **"📊 LMS Grade Reporting (LTI AGS)"** meta box in the right sidebar
3. Click **Enable Grading for This Chapter**
4. Check each H5P activity to include in the grade
5. Set a weight for each activity
6. Choose the aggregation method:
   - **Sum** — total raw points across all activities
   - **Weighted Average** — weighted mean percentage
7. Click **Save chapter**

**Grading schemes (per activity):**

| Scheme | Use case |
|--------|----------|
| Best Attempt | Mastery-based learning — students retry until they pass |
| Average | Practice-focused — mean of all attempts |
| First Attempt | Diagnostic assessment |
| Last Attempt | Iterative improvement |

> **Important:** After configuring grading, the student must do a **fresh LTI launch** (click the Moodle activity) before completing the H5P quiz. The launch stores the grade column association. Completing H5P without a prior LTI launch will not sync grades.

---

### Syncing Historical Grades

If students completed H5P activities before you enabled grading:

1. Edit the chapter → find the **"LMS Grade Reporting"** meta box
2. Click **"🔄 Sync Existing Grades to LMS"**
3. Confirm → review the summary (synced / skipped / failed)

Students are skipped if they accessed the chapter directly (not via LTI launch) — they have no lineitem URL stored, so no grade can be posted.

---

## Part 3 — Developer & Contributor Guide

### Local Development Setup

**Prerequisites:** Docker ≥ 24, Docker Compose, Git, Make, 8 GB+ RAM

```bash
git clone https://github.com/ugenkudupudiqbnox/qbnox-lti-platform.git
cd qbnox-lti-platform
make
```

`make` (no target) runs the full setup: starts containers, installs Pressbooks + plugin, registers the LTI tool in Moodle, seeds test data, and runs smoke tests.

**Access after setup:**
- Moodle: `https://moodle.local` — admin / admin
- Pressbooks: `https://pressbooks.local` — admin / admin

#### Make commands

| Command | Description |
|---------|-------------|
| `make` | Full setup / rebuild |
| `make up` | Start containers |
| `make install` | Install & activate plugin |
| `make enable-lti` | Register LTI tool in Moodle |
| `make seed` | Create Moodle test users & course |
| `make seed-books` | Create Pressbooks test content |
| `make install-h5p` | Install H5P libraries |
| `make test` | Smoke tests |
| `make test-deep-linking` | Deep Linking flow test |
| `make test-ags` | Grade sync test |
| `make logs` | Follow container logs |
| `make down` | Stop containers |
| `make reset` | Destroy all data and rebuild |

#### Environment configuration

For production/staging domains, create a `.env` file before starting containers:

```bash
cp lti-local-lab/.env.production lti-local-lab/.env
```

```dotenv
PRESSBOOKS_DOMAIN=pb.yourdomain.com
MOODLE_DOMAIN=moodle.yourdomain.com
MOODLE_VERSION=4.4
```

No `.env` file = localhost defaults (correct for CI and local dev). PHP changes hot-reload — no container rebuild required.

---

### Repository Structure

```
qbnox-lti-platform/
├── plugin/                         # WordPress plugin — deploy this directory
│   ├── Controllers/
│   │   ├── LoginController.php     # OIDC login initiation
│   │   ├── LaunchController.php    # LTI launch → user login + AGS context storage
│   │   ├── DeepLinkController.php  # Deep Linking 2.0 content picker
│   │   └── AGSController.php       # Grade Services REST endpoint
│   ├── Services/
│   │   ├── JwtValidator.php        # RSA signature + iss/aud/exp/nonce validation
│   │   ├── NonceService.php        # Replay protection (60-second window)
│   │   ├── SecretVault.php         # AES-256-GCM encryption (key from AUTH_KEY)
│   │   ├── PlatformRegistry.php    # LMS platform registration
│   │   ├── DeploymentRegistry.php  # Deployment ID validation
│   │   ├── RoleMapper.php          # LTI roles → WordPress roles
│   │   ├── AGSClient.php           # OAuth2 client credentials + grade POST
│   │   ├── LineItemService.php     # AGS line item management
│   │   ├── TokenCache.php          # OAuth2 token caching (60-minute TTL)
│   │   ├── H5PGradeSyncEnhanced.php # h5p_alter_user_result → AGS grade sync
│   │   ├── H5PResultsManager.php   # Chapter grading configuration
│   │   ├── H5PActivityDetector.php # Finds [h5p id="X"] in chapter content
│   │   └── AuditLogger.php         # Security audit trail
│   ├── admin/                      # Network Admin UI and chapter meta boxes
│   ├── db/                         # Schema and migration scripts
│   ├── routes/rest.php             # WordPress REST API route registration
│   └── bootstrap.php               # Plugin initialization and hook registration
├── lti-local-lab/                  # Docker lab (Moodle + Pressbooks)
├── scripts/                        # Registration and automation scripts
├── docs/                           # Documentation
├── Makefile                        # Primary developer interface
└── .github/workflows/              # CI pipeline
```

---

### Architecture: LTI 1.3 Request Flow

#### Standard launch (student clicks activity)

```
1. OIDC Login  [LoginController]
   Moodle POSTs: iss, login_hint, target_link_uri
   → Pressbooks validates platform, generates state + nonce
   → Redirects browser to Moodle auth endpoint

2. LTI Launch  [LaunchController]
   Moodle POSTs signed id_token JWT
   → JwtValidator: RSA signature against JWKS, iss/aud/exp/nonce checks
   → NonceService: consumes nonce (60-second replay window)
   → DeploymentRegistry: validates deployment_id
   → RoleMapper: maps LTI roles → WP roles, creates or logs in user
   → Stores AGS lineitem URL in wp_{blog_id}_postmeta for grade sync
   → wp_redirect to target_link_uri

3. Student completes H5P
   → H5P plugin fires h5p_alter_user_result action
   → H5PGradeSyncEnhanced reads lineitem from chapter post meta
   → AGSClient fetches OAuth2 token from Moodle (cached 60 min)
   → POSTs score to Moodle lineitem URL via LTI AGS
```

#### Deep Linking (instructor adds content)

```
1. Moodle sends LtiDeepLinkingRequest JWT to launch endpoint
2. DeepLinkController renders Pressbooks content picker UI
3. Instructor selects chapter → ContentService builds response:
   - Includes lineItem{scoreMaximum:100} if H5P grading is enabled
   - Signs response JWT with Pressbooks RSA private key
4. Response JWT POSTed back to Moodle
5. Moodle creates activity + grade column (if lineItem was present)
```

---

### Key Design Decisions

**Per-user, per-chapter lineitem** — stored as `_lti_ags_lineitem_user_{user_id}` in post meta on the chapter post (in the book's blog table, e.g. `wp_2_postmeta`). This allows different students to have independent grade column associations, supporting retakes and multi-section courses.

**Blog context switching** — The LTI launch endpoint runs in the main site context (blog 1), but Pressbooks book chapters live in sub-blogs (blog 2+). `LaunchController` calls `switch_to_blog($blog_id)` before any post meta write and `restore_current_blog()` after. `H5PGradeSyncEnhanced` runs in the book blog's request context because H5P AJAX fires from the book's URL path.

**Moodle AGS config key** — `ltiservice_gradesynchronization=2` must be in `mdl_lti_types_config`. Moodle's `get_launch_parameters()` checks exactly this key name. `lti_add_type()` stores `ltiservice_*` prefixed keys as-is, but strips the `lti_` prefix from `lti_*` keys — so `$config->ltiservice_gradesynchronization = 2` is correct; `$config->lti_ags_grades_service` stores under the wrong key and AGS is never injected into the JWT.

**Deep Linking creates grade columns** — Moodle only creates a `mdl_ltiservice_gradebookservices` row (and therefore includes `lineitem` in future JWTs) when a Deep Linking response includes a `lineItem` field. Manually entering a chapter URL bypasses this. `ContentService::get_content_item()` includes `lineItem` only when `_lti_h5p_grading_enabled=1` on the chapter.

---

### Security Requirements

These are non-negotiable. PRs that violate any of these will not be merged:

- **Never log secrets or tokens** — not even temporarily for debugging
- **Never hardcode client secrets** — always use `SecretVault` (AES-256-GCM, key derived from WordPress `AUTH_KEY` + `SECURE_AUTH_KEY`)
- **Never bypass JWT validation** — always verify RSA signature against live JWKS, and check `iss`, `aud`, `exp`, `nonce`
- **Never disable nonce replay protection** — the 60-second window is intentional and required by LTI 1.3
- **Never skip HTTPS in production**
- **Never use `--no-verify`** on git hooks without explicit authorization

Report vulnerabilities via `SECURITY.md`. Do not open public issues for security findings.

---

### Testing

#### Automated

```bash
make test                  # Smoke tests — basic launch, JWT, connectivity
make test-deep-linking     # Deep Linking 2.0 end-to-end
make test-ags              # Grade sync via AGS
```

CI runs the full suite on every push and PR (`.github/workflows/ci-matrix.yml`). Security regressions block merges.

#### Manual (required for LTI)

Some behaviors require a human in the loop and cannot be fully automated:

| Scenario | What to check |
|----------|--------------|
| Instructor launch | Gets editor role in Pressbooks |
| Student launch | Gets subscriber role, auto-logged in, no login prompt |
| Deep Linking | Content picker opens, chapter added, grade column created in Moodle |
| H5P grade sync | After fresh launch + H5P completion, grade appears in Moodle gradebook |
| Replay protection | Refreshing the launch page gives a nonce error (expected) |
| Invalid issuer | Launch is rejected, audit log entry created |
| Browser in incognito | Cookie warnings expected — document for end users |

Full checklist: `docs/testing/PRESSBOOKS_MOODLE_TEST_CHECKLIST.md`

---

### Contributing

1. Fork the repository and create a branch: `git checkout -b feature/your-change`
2. Make changes in `plugin/` — PHP hot-reloads, no container rebuild needed
3. Run `make test` before submitting
4. Open a PR with a clear description of what changed and why

**PR expectations:**
- One concern per PR — minimal and focused
- No breaking configuration changes without prior discussion
- Clear commit messages explaining the *why*, not just the *what*
- Security regressions block merge

---

## Troubleshooting

### LTI Launch Fails

| Symptom | Likely cause | Fix |
|---------|-------------|-----|
| "Invalid issuer" | Issuer URL mismatch | Verify the `issuer` in `wp_lti_platforms` matches Moodle's Platform ID exactly (including protocol and trailing slash) |
| "Invalid nonce" / nonce error | Browser refresh or replay | Use a fresh launch from Moodle — refresh is expected to fail |
| JWT signature error | Server clock drift | Sync both servers via NTP; clocks must be within 60 seconds |
| "Deployment not found" | Deployment ID not registered | Re-run `pressbooks-register-platform.php` with the correct deployment ID |
| Blank page / content not loading | Third-party cookies blocked | Configure `SameSite=None; Secure` — see [Production Hardening](#step-5-production-hardening) |
| "Error output, so disabling automatic redirect" | PHP warnings printed before headers | Set `display_errors = Off` in PHP config |

### Grades Not Syncing

Check in this order — each step must pass before the next matters:

**1. Was the chapter added via Deep Linking?**
Manually entering a URL does not create a Moodle grade column. Delete the activity and re-add it using "Select content" (Deep Linking).

**2. Is H5P grading enabled on the Pressbooks chapter?**
Open the chapter editor → find the "LMS Grade Reporting" meta box → confirm it is enabled with at least one H5P activity selected.

**3. Did the student do a fresh LTI launch after setup?**
The lineitem URL is stored on launch. If the student completed H5P before clicking the Moodle activity, there is no lineitem. Have them click the Moodle activity first, then complete H5P.

**4. Is `ltiservice_gradesynchronization=2` in the Moodle tool config?**

```sql
-- Run on Moodle DB:
SELECT name, value FROM mdl_lti_types_config
WHERE name = 'ltiservice_gradesynchronization';
```

If missing or value is not `2`, re-register the tool. For the Docker lab, run `make enable-lti`. For production, repeat [Step 2](#step-2-register-pressbooks-in-moodle) and delete/recreate the old tool.

**5. Read the Pressbooks debug log:**

```bash
grep "PB-LTI H5P Enhanced" /path/to/wordpress/wp-content/debug.log | tail -20
```

| Log message | Meaning |
|-------------|---------|
| `has_ags=no` | AGS endpoint not in JWT — tool config issue (item 4) |
| `No lineitem URL found` | Student hasn't launched yet (item 3) |
| `No chapter-specific lineitem` | Post meta not written — check blog context or lineitem storage |
| `✅ Chapter grade posted successfully` | Sync worked — check Moodle gradebook and refresh |

**6. Verify lineitem was stored after launch:**

```bash
# Check wp_2_postmeta (replace 2 with your book's blog ID)
wp db query "SELECT meta_key, meta_value FROM wp_2_postmeta WHERE meta_key LIKE '_lti_ags_lineitem_user_%'" --allow-root
```

### REST API Returns 404

```bash
wp rewrite flush --allow-root
# Apache only:
sudo a2enmod rewrite && sudo systemctl restart apache2
```

### Plugin Activation Fails

```bash
php -v                                              # Must be 8.1+
php -m | grep -E 'curl|json|mysqli|xml|mbstring'   # All must be listed
```

---

## Upgrading & Uninstalling

### Upgrading

```bash
# 1. Back up the database
wp db export backup-$(date +%Y%m%d).sql --allow-root

# 2. Pull latest plugin code
cd /path/to/plugins/qbnox-lti-platform
git pull origin main

# 3. Flush caches — DB migrations run automatically on next page load
wp cache flush --allow-root
```

### Uninstalling

```bash
# Deactivate
wp plugin deactivate qbnox-lti-platform --network --allow-root

# Remove files
rm -rf /path/to/plugins/qbnox-lti-platform

# Optional: remove all plugin data
wp db query "DROP TABLE IF EXISTS
  wp_lti_platforms, wp_lti_deployments, wp_lti_nonces, wp_lti_keys,
  wp_lti_audit_log, wp_lti_h5p_grading_config, wp_lti_h5p_grade_sync_log" --allow-root
wp db query "DELETE FROM wp_options WHERE option_name LIKE 'pb_lti_%'" --allow-root
```

> **Warning:** Dropping tables removes all registered platforms, deployments, and audit history permanently.

---

**Need help?**
- Debug log: `wp-content/debug.log` (filter for `[PB-LTI]`)
- Audit log: Pressbooks Network Admin → LTI Audit Log
- Issues: https://github.com/ugenkudupudiqbnox/qbnox-lti-platform/issues
