# Architecture Overview

## Design Philosophy

This plugin is an **external trust platform** for Pressbooks — not a Pressbooks extension. It has zero coupling to Pressbooks internals. Pressbooks acts only as a session container and content host.

- Own REST API endpoints, database tables, crypto, and lifecycle
- Network-activated WordPress plugin (works across all Pressbooks book blogs)
- Deployable independently of Pressbooks core version

---

## Plugin Structure

```
plugin/
├── Controllers/               # LTI endpoint handlers (thin — delegate to Services)
│   ├── LoginController.php    # OIDC login initiation
│   ├── LaunchController.php   # LTI launch: JWT validation, user login, AGS storage
│   ├── DeepLinkController.php # Deep Linking 2.0: content picker + signed response
│   └── AGSController.php      # Assignment & Grade Services REST endpoint
├── Services/                  # Core business logic
│   ├── JwtValidator.php        # RSA signature + iss/aud/exp/nonce validation
│   ├── NonceService.php        # Replay protection (60-second transient window)
│   ├── SecretVault.php         # AES-256-GCM encryption (key from WP AUTH_KEY)
│   ├── PlatformRegistry.php    # LMS platform registration + lookup
│   ├── DeploymentRegistry.php  # Deployment ID validation
│   ├── RoleMapper.php          # LTI roles → WordPress roles + user provisioning
│   ├── AGSClient.php           # OAuth2 client credentials + grade POST
│   ├── LineItemService.php     # AGS line item management
│   ├── TokenCache.php          # OAuth2 token caching (60-minute TTL)
│   ├── H5PGradeSyncEnhanced.php # h5p_alter_user_result → AGS grade sync
│   ├── H5PResultsManager.php   # Chapter-level H5P grading configuration
│   ├── H5PActivityDetector.php # Finds [h5p id="X"] shortcodes in chapter content
│   └── AuditLogger.php         # Security audit trail
├── admin/                     # Network Admin UI and chapter meta boxes
├── db/                        # Schema definitions and migration scripts
├── routes/rest.php            # WordPress REST API route registration
└── bootstrap.php              # Plugin initialization and hook registration
```

---

## LTI 1.3 Request Flows

### Standard Launch (student clicks activity)

```
1. OIDC Login  [LoginController]
   Moodle → POST iss, login_hint, target_link_uri
   Pressbooks → validates platform, generates state + nonce
   Pressbooks → redirects browser to Moodle auth endpoint

2. LTI Launch  [LaunchController]
   Moodle → POST signed id_token JWT
   JwtValidator → RSA signature against live JWKS, checks iss/aud/exp/nonce
   NonceService → consumes nonce (prevents replay within 60s)
   DeploymentRegistry → validates deployment_id
   RoleMapper → maps LTI roles to WP roles, creates/logs in user
   LaunchController → stores AGS lineitem URL in book blog post meta
   → wp_redirect to target_link_uri

3. H5P Grade Sync  [H5PGradeSyncEnhanced]
   H5P plugin → fires h5p_alter_user_result action
   H5PGradeSyncEnhanced → reads lineitem from chapter post meta
   AGSClient → fetches OAuth2 token from Moodle (cached 60 min)
   AGSClient → POSTs score to lineitem URL via LTI AGS
```

### Deep Linking (instructor adds content)

```
1. Moodle → sends LtiDeepLinkingRequest JWT to launch endpoint
2. DeepLinkController → renders Pressbooks content picker UI
3. Instructor selects chapter → ContentService builds response:
     - Includes lineItem{scoreMaximum:100} if H5P grading is enabled
     - Signs response JWT with Pressbooks RSA private key
4. Response JWT → POSTed back to Moodle
5. Moodle → creates activity + grade column (when lineItem present)
```

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `wp_lti_platforms` | Registered LMS platforms (issuer, client_id, auth/token/keyset URLs) |
| `wp_lti_deployments` | Deployment IDs per platform |
| `wp_lti_nonces` | Consumed nonces (replay protection) |
| `wp_lti_keys` | RSA key pairs for JWT signing |
| `wp_lti_audit_log` | Security event log (launches, grade posts, errors) |
| `wp_{n}_lti_h5p_grading_config` | Per-chapter H5P grading configuration (per book blog) |
| `wp_lti_h5p_grade_sync_log` | Grade sync history |

---

## Multisite Context Handling

The REST API endpoints run in the **main site context** (blog 1). Pressbooks book chapters live in **sub-blogs** (blog 2+). The plugin handles this explicitly:

- `LaunchController` calls `switch_to_blog($blog_id)` before writing post meta and `restore_current_blog()` after
- `H5PGradeSyncEnhanced` runs in the book blog's request context (H5P AJAX fires from the book's URL path)
- AGS lineitem URLs are stored as `_lti_ags_lineitem_user_{user_id}` in `wp_{blog_id}_postmeta` on the chapter post

---

## Security Architecture

| Control | Implementation |
|---------|---------------|
| JWT validation | RSA signature verified against live JWKS; `iss`, `aud`, `exp`, `nonce` all checked |
| Replay protection | Nonces stored as WP transients with 60-second TTL |
| Secret storage | AES-256-GCM via `SecretVault`; key derived from `AUTH_KEY` + `SECURE_AUTH_KEY` |
| Audit trail | All launches, grade posts, and errors written to `wp_lti_audit_log` |
| HTTPS | Required; LTI 1.3 spec mandates secure transport |

Aligned with ISO 27001:2022 and SOC 2 Type II control frameworks. See `docs/compliance/` for control mappings.
