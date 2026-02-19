# Project Roadmap

## Released

### v2.2.0 (February 2026)
- Fixed AGS grade sync: corrected Moodle tool config key (`ltiservice_gradesynchronization`)
- H5P grade sync with chapter-level grading configuration (Best/Average/First/Last attempt)
- Retroactive grade sync for historical H5P completions
- Per-chapter, per-user AGS lineitem storage in book blog post meta
- Moodle cron setup automated via `make setup-moodle-cron`
- PHP display_errors suppressed to prevent broken LTI redirects
- Consolidated documentation: `docs/SETUP_GUIDE.md`
- `lti_acceptgrades=DELEGATE` so only Deep-Linked chapters with H5P grading get grade columns

### v2.1.0
- Deep Linking 2.0 content picker (book/chapter/front-matter/back-matter)
- H5P Results grading feature (chapter-level grading configuration UI)
- ScaleMapper for letter-grade / pass-fail LMS grade schemes
- AGS OAuth2 token caching (60-minute TTL)
- Audit logging to `wp_lti_audit_log`
- Multisite blog context switching for correct post meta storage

### v2.0.0
- LTI 1.3 core: OIDC login, JWT launch, deployment validation
- Assignment & Grade Services (AGS) — score POST via OAuth2 client credentials
- SecretVault: AES-256-GCM encrypted client secret storage
- RSA key pair generation and JWKS endpoint
- Role mapping: LTI Instructor → WP Editor, LTI Learner → WP Subscriber
- Nonce replay protection (60-second window)
- Network Admin UI for platform and deployment registration

---

## Upcoming

### v2.3.0
- Canvas LMS validation and conformance testing
- Brightspace / D2L validation
- Automated Moodle Behat tests for LTI launch flows
- Admin UI: lineitem browser and manual grade sync trigger per student

### v2.4.0
- Names and Role Provisioning Services (NRPS) — course roster sync
- Localization support (i18n strings)
- Pressbooks Bedrock Composer package release

### v3.0.0
- Multi-tenant mode: single plugin instance serving multiple institutions
- Plugin-based assessment engine API (replace H5P-specific code with a generic interface)
- Analytics and grade reporting dashboard
- 1EdTech LTI Advantage formal certification submission

---

## Long-term Vision

Become a **reference open-source LTI Advantage platform** for Pressbooks — owned by universities and OER initiatives, not vendors. Audit-ready, upgrade-safe, and free from lock-in.
