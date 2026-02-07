# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **production-grade LTI 1.3 + LTI Advantage platform** for Pressbooks that integrates with LMS platforms (Moodle, Canvas, Blackboard, Brightspace). It's designed as critical infrastructure for universities and OER initiatives, not a demo plugin.

**Architecture Philosophy**: This is an external trust platform plugin for WordPress multisite (Pressbooks Bedrock) with:
- Zero coupling to Pressbooks internals
- Own REST APIs, DB tables, crypto, lifecycle
- Pressbooks acts only as a session/container layer

## Development Commands

### Primary Interface
All development uses `make` commands (never interact with Docker manually):

```bash
make up                  # Start Moodle + Pressbooks via Docker
make install-pressbooks  # Install & configure Pressbooks
make install            # Install & activate LTI plugin in Pressbooks
make enable-lti         # Auto-register LTI 1.3 tool in Moodle
make seed               # Create Moodle test users & course
make seed-books         # Create Pressbooks test content
make test               # Run smoke tests
make test-deep-linking  # Test Deep Linking 2.0 flow
make test-ags           # Test Assignment & Grade Services
make collect-artifacts  # Collect CI evidence artifacts
```

### Full Setup (One Command)
```bash
make up install-pressbooks install enable-lti seed seed-books test
```

### Test Environment URLs
- Moodle: `https://moodle.local`
- Pressbooks: `https://pressbooks.local`

## Code Architecture

### Plugin Structure (`plugin/`)
```
plugin/
├── Controllers/        # LTI endpoint handlers
│   ├── LoginController.php       # OIDC login initiation
│   ├── LaunchController.php      # LTI launch handler
│   ├── DeepLinkController.php    # Deep Linking 2.0
│   └── AGSController.php         # Assignment & Grade Services
├── Services/          # Core business logic
│   ├── JwtValidator.php          # JWT signature & claim validation
│   ├── NonceService.php          # Replay attack prevention
│   ├── SecretVault.php           # AES-256-GCM secret encryption
│   ├── PlatformRegistry.php      # LMS platform registration
│   ├── DeploymentRegistry.php    # Deployment ID tracking
│   ├── RoleMapper.php            # LTI → WordPress role mapping
│   ├── AGSClient.php             # OAuth2 + grade posting
│   ├── LineItemService.php       # AGS line item management
│   ├── TokenCache.php            # OAuth2 token caching
│   └── AuditLogger.php           # Security audit trail
├── routes/rest.php    # WordPress REST API route registration
├── admin/             # Network Admin UI
├── db/                # Database schema & migrations
└── bootstrap.php      # Plugin initialization
```

### LTI 1.3 Flow
1. **OIDC Login** (`LoginController`): Receives `iss`, `login_hint`, `target_link_uri` → redirects to platform auth endpoint with state/nonce
2. **Launch** (`LaunchController`): Validates JWT (via `JwtValidator`), checks nonce (via `NonceService`), validates deployment, maps roles, creates/logs in user
3. **Deep Linking** (`DeepLinkController`): Presents content picker, returns signed Deep Link JWT with selected content
4. **AGS Grade Return** (`AGSController`): Uses OAuth2 client credentials (via `AGSClient`) to POST scores to LMS

### Security Architecture
- **JWT Validation**: Full signature verification against JWKS, audience/issuer checks, expiry validation
- **Nonce Service**: Time-based replay protection (60s window)
- **Secret Vault**: AES-256-GCM encryption for client secrets, key derived from WordPress `AUTH_KEY` + `SECURE_AUTH_KEY`
- **Audit Logging**: All LTI launches, grade posts, and errors logged to custom DB table

### Key Standards Compliance
- **LTI 1.3 Core**: OIDC, JWT, deployment validation, JWKS key rotation
- **Deep Linking 2.0**: Content item selection with signed JWT response
- **AGS (Assignment & Grade Services)**: OAuth2 client credentials, scope enforcement, score POST, line item creation
- **Security**: Nonce replay protection, encrypted secrets, audit logging (aligned with ISO 27001 / SOC 2)

## Testing Strategy

### Automated Tests
- **Smoke tests**: `scripts/lti-smoke-test.sh` - Basic connectivity & launch validation
- **Deep Linking tests**: `scripts/ci-test-deep-linking.sh` - Content selection flow
- **AGS tests**: `scripts/ci-test-ags-grade.sh` - Grade posting verification
- **JWT crypto tests**: `scripts/ci-verify-jwt-crypto.php` - Signature validation

### CI Pipeline (`.github/workflows/ci.yml`)
Runs full end-to-end LTI compliance tests on every push/PR:
1. Brings up Moodle + Pressbooks in Docker
2. Installs & configures LTI plugin
3. Runs smoke tests, Deep Linking tests, AGS tests
4. Generates compliance evidence (1EdTech certification artifacts, CI evidence PDFs)
5. Uploads artifacts (logs, screenshots, compliance evidence)

### Manual Testing Requirements
LTI requires human-in-the-loop testing for:
- Instructor vs Student launch flows
- Deep Linking content picker UX
- Grade return (AGS) behavior in LMS gradebook
- Failure cases: invalid `aud`, replay attacks, scope violations

See: `docs/testing/PRESSBOOKS_MOODLE_TEST_CHECKLIST.md`

## Security Requirements

**CRITICAL - Never:**
- Log secrets or tokens
- Hardcode client secrets
- Bypass JWT validation
- Disable nonce/replay protection
- Skip HTTPS in production
- Use `--no-verify` hooks without explicit authorization

**Always:**
- Validate JWT signatures against JWKS
- Check `iss`, `aud`, `exp`, `nonce` claims
- Use `SecretVault` for storing client secrets
- Log security events via `AuditLogger`
- Follow nonce replay protection (60s transient window)

Security regressions block merges. For vulnerabilities, follow `SECURITY.md` (never open public issues).

## Development Workflow

1. Make code changes in `plugin/`
2. Run `make test` (PHP changes hot-reload, no rebuild needed)
3. Test manually via Moodle UI (`https://moodle.local`)
4. Check audit logs in Pressbooks Network Admin
5. Verify role mapping, AGS behavior, Deep Linking flow

## LTI Troubleshooting

### Launch Fails
- Verify HTTPS is working (required for LTI 1.3)
- Check hostnames match exactly (`moodle.local`, `pressbooks.local`)
- Confirm Issuer / Client ID match between Moodle and Pressbooks
- Check JWT claims in audit logs

### Replay Errors
- Browser refresh = expected failure (nonce consumed)
- Always use fresh launch from Moodle

### AGS Grade Posting Fails
- Verify AGS scopes are granted in Moodle
- Check client secret is stored in `SecretVault`
- Confirm token cache isn't stale (60min expiry)
- Check OAuth2 token response in logs

## Compliance & Certification

This project maintains 1EdTech LTI Advantage certification evidence:
- `docs/compliance/1EDTECH_CERTIFICATION_MAPPING.md` - Feature checklist
- `docs/compliance/ISO27001_2022_CONTROL_MAPPING.md` - Security controls
- `docs/compliance/SOC2_CONTROL_MATRIX.md` - Audit framework
- CI generates certification evidence on every run

## Repository Philosophy

Treat this as **critical infrastructure** for universities:
- Long-term institutional ownership
- Audit-ready (ISO 27001 / SOC 2 aligned)
- No vendor lock-in
- Upgrade-safe with Pressbooks Bedrock
- Open-source (MIT license)

Changes should prioritize **security, auditability, and standards compliance** over convenience.
