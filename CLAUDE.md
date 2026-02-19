# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **production-grade LTI 1.3 + LTI Advantage platform** for Pressbooks that integrates with LMS platforms (Moodle, Canvas, Blackboard, Brightspace). It's designed as critical infrastructure for universities and OER initiatives, not a demo plugin.

**Architecture Philosophy**: External trust platform plugin for WordPress multisite (Pressbooks Bedrock):
- Zero coupling to Pressbooks internals
- Own REST APIs, DB tables, crypto, lifecycle
- Pressbooks acts only as a session/container layer

**Development Setup**: Docker-based lab optimized for LTI integration testing (not full production Pressbooks deployment). Uses WordPress Docker image + Pressbooks plugin. PHP changes hot-reload — no container rebuild needed.

## Development Commands

All development uses `make` commands (never interact with Docker manually):

```bash
make                      # Full setup: start containers + install + configure + seed + test
make install              # Install & activate LTI plugin in Pressbooks
make enable-lti           # Auto-register LTI 1.3 tool in Moodle
make seed                 # Create Moodle test users & course
make seed-books           # Create Pressbooks test content
make install-h5p          # Install H5P libraries in Pressbooks
make setup-moodle-cron    # Install host crontab entry to run Moodle cron every minute
make test                 # Run smoke tests
make test-deep-linking    # Test Deep Linking 2.0 flow
make test-ags             # Test Assignment & Grade Services
make collect-artifacts    # Collect CI evidence artifacts
```

### Environment Configuration
- **Logs**: WordPress debug log at `web/app/debug.log` inside the pressbooks container. Filter with `grep "PB-LTI"`.
- **PHP display_errors**: Suppressed via `lti-local-lab/moodle-php.ini` mounted into the Moodle container — prevents PHP warnings from breaking LTI redirects.
- **H5P**: WordPress H5P plugin configured with Hub enabled. Metadata refresh via `wp eval`.
- **Production domains**: Create `lti-local-lab/.env` from `.env.production` and set `PRESSBOOKS_DOMAIN`, `MOODLE_DOMAIN`.

## Code Architecture

### Plugin Structure (`plugin/`)
```
plugin/
├── Controllers/
│   ├── LoginController.php        # OIDC login initiation
│   ├── LaunchController.php       # JWT validation, user login, AGS lineitem storage
│   ├── DeepLinkController.php     # Deep Linking 2.0 content picker + signed response
│   └── AGSController.php          # AGS REST endpoint
├── Services/
│   ├── JwtValidator.php           # RSA signature + iss/aud/exp/nonce validation
│   ├── NonceService.php           # Replay protection (60s transient window)
│   ├── SecretVault.php            # AES-256-GCM encryption (key from AUTH_KEY)
│   ├── PlatformRegistry.php       # LMS platform registration
│   ├── DeploymentRegistry.php     # Deployment ID validation
│   ├── RoleMapper.php             # LTI roles → WordPress roles + user provisioning
│   ├── AGSClient.php              # OAuth2 client credentials + grade POST
│   ├── LineItemService.php        # AGS line item management
│   ├── TokenCache.php             # OAuth2 token caching (60-minute TTL)
│   ├── H5PGradeSyncEnhanced.php   # h5p_alter_user_result hook → AGS grade sync
│   ├── H5PResultsManager.php      # Chapter-level H5P grading configuration
│   ├── H5PActivityDetector.php    # Finds [h5p id="X"] shortcodes in chapter content
│   ├── ScaleMapper.php            # Maps percentage scores to LMS grade scales
│   └── AuditLogger.php            # Security audit trail
├── admin/                         # Network Admin UI + chapter meta boxes
├── db/                            # Schema definitions + migration scripts
├── routes/rest.php                # WordPress REST API route registration
└── bootstrap.php                  # Plugin initialization and hook registration
```

### LTI 1.3 Flow

1. **OIDC Login** (`LoginController`): Receives `iss`, `login_hint`, `target_link_uri` → generates state/nonce → redirects to platform auth endpoint
2. **Launch** (`LaunchController`): Validates JWT (`JwtValidator`), consumes nonce (`NonceService`), validates deployment, maps roles, creates/logs in user. If AGS claim has `lineitem`, stores it as `_lti_ags_lineitem_user_{user_id}` in `wp_{blog_id}_postmeta` on the chapter post.
3. **Deep Linking** (`DeepLinkController`): Renders content picker, returns signed response JWT. Includes `lineItem{scoreMaximum:100}` only when `_lti_h5p_grading_enabled=1` on the selected chapter — this is what tells Moodle to create a grade column.
4. **H5P Grade Sync** (`H5PGradeSyncEnhanced`): Hooked to `h5p_alter_user_result`. Reads lineitem URL from post meta → fetches OAuth2 token via `AGSClient` → POSTs score to Moodle lineitem URL.

### Multisite Context
The REST API runs in the **main site context** (blog 1). Book chapters live in **sub-blogs** (blog 2+). `LaunchController` calls `switch_to_blog($blog_id)` before post meta writes and `restore_current_blog()` after. `H5PGradeSyncEnhanced` runs in the book blog's request context (H5P AJAX fires from the book's URL path, not admin root).

## Security Requirements

**CRITICAL - Never:**
- Log secrets or tokens
- Hardcode client secrets
- Bypass JWT validation
- Disable nonce/replay protection
- Skip HTTPS in production
- Use `--no-verify` hooks without explicit authorization

**Always:**
- Validate JWT signatures against live JWKS
- Check `iss`, `aud`, `exp`, `nonce` claims
- Use `SecretVault` for storing client secrets
- Log security events via `AuditLogger`

Security regressions block merges. For vulnerabilities, follow `SECURITY.md` (never open public issues).

## Testing Strategy

- **Smoke tests**: `scripts/lti-smoke-test.sh`
- **Deep Linking tests**: `scripts/ci-test-deep-linking.sh`
- **AGS tests**: `scripts/ci-test-ags-grade.sh`
- **JWT crypto tests**: `scripts/ci-verify-jwt-crypto.php`
- **CI** (`.github/workflows/ci-matrix.yml`): Full end-to-end on every push/PR
- **Manual required**: Instructor/student launch flows, Deep Linking UX, AGS gradebook behavior, failure cases

See: `docs/testing/PRESSBOOKS_MOODLE_TEST_CHECKLIST.md`

## Development Workflow

1. Make code changes in `plugin/`
2. Run `make test` (PHP hot-reloads, no rebuild needed)
3. Test manually via Moodle UI
4. Check `web/app/debug.log` for `[PB-LTI]` entries
5. Check audit logs in Pressbooks Network Admin

## LTI Troubleshooting

### Launch Fails
- Verify HTTPS is working (required for LTI 1.3)
- Check hostnames match exactly in Issuer / Client ID
- Check JWT claims in audit logs

### Replay Errors
- Browser refresh = expected failure (nonce consumed)
- Always use fresh launch from Moodle

### AGS Grade Posting Fails

**JWT has `has_ags=no`**: Moodle is not including the AGS endpoint in the JWT. Check that `mdl_lti_types_config` has `ltiservice_gradesynchronization=2` for the tool type. This is the key Moodle's gradebookservices checks. The wrong key `ags_grades_service` is silently ignored. Run `make enable-lti` to re-register with the correct config.

**`No lineitem URL found` in debug log**: The lineitem is stored per-user per-chapter as `_lti_ags_lineitem_user_{user_id}` in `wp_{blog_id}_postmeta`. Check:
1. Chapter was added via **Deep Linking** (not manual URL) — Deep Linking creates the Moodle grade column
2. H5P grading is **enabled on the chapter** in Pressbooks ("LMS Grade Reporting" meta box)
3. Student did a **fresh LTI launch** after grading was configured — completing H5P without a prior LTI launch stores no lineitem

**Debug commands:**
```bash
# Check lineitem stored (replace 2 with book blog ID)
docker exec pressbooks wp --allow-root --path=/var/www/pressbooks/web/wp \
  db query "SELECT meta_key, meta_value FROM wp_2_postmeta WHERE meta_key LIKE '_lti_ags_lineitem_user_%'"

# Check Moodle AGS config key
docker exec mysql sh -c "mysql -uroot -proot moodle -e \
  'SELECT name,value FROM mdl_lti_types_config WHERE name=\"ltiservice_gradesynchronization\";'" 2>/dev/null | grep -v Warning
```

## Compliance & Certification

- `docs/compliance/1EDTECH_CERTIFICATION_MAPPING.md` — Feature checklist
- `docs/compliance/ISO27001_2022_CONTROL_MAPPING.md` — Security controls
- `docs/compliance/SOC2_CONTROL_MATRIX.md` — Audit framework
- CI generates certification evidence on every run

## Repository Philosophy

Treat this as **critical infrastructure** for universities:
- Long-term institutional ownership
- Audit-ready (ISO 27001 / SOC 2 aligned)
- No vendor lock-in
- Open-source (MIT license)

Changes should prioritize **security, auditability, and standards compliance** over convenience.
