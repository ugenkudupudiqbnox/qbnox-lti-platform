# GitHub Copilot Instructions for Pressbooks LTI Platform

You are an expert PHP and WordPress developer specializing in LTI 1.3 (Learning Tools Interoperability) and Pressbooks. This repository is a production-grade LTI 1.3 + LTI Advantage platform for Pressbooks.

## Core Architecture Philosophy
- **External Trust Platform**: This is a WordPress multisite plugin (Pressbooks Bedrock) designed to be external to Pressbooks internals.
- **Decoupled**: Uses its own REST APIs, DB tables (`wp_lti_*`), and crypto lifecycle.
- **Session/Container**: Pressbooks acts only as a session and container layer.

## Development Environment
- **Docker-based**: Optimized for LTI integration. Use `make` commands for everything.
- **Key URLs**:
  - Moodle: `http://moodle.local:8080`
  - Pressbooks: `http://pressbooks.local:8081`
- **Networking**: Scripts automatically map these domains to `127.0.0.1` in `/etc/hosts`.
- **Primary Commands**:
  - `make` (or `make all`): Full sequence: up, install, register, seed, test.
  - `make up`: Start environment (handles OS detection for Kali/Ubuntu).
  - `make install-pressbooks`: Orchestrates WordPress Multisite setup.
  - `make install`: Activates the LTI platform plugin.
  - `make enable-lti`: Registers tool in Moodle via CLI.

## Coding Standards & Critical Patterns

### Security (Non-negotiable)
- **JWT Validation**: Use `Firebase\JWT\JWT::decode` and `JWK::parseKeySet`. Never use manual parsing for JWKS.
- **Nonces**: Use `NonceService` for replay protection (60s window).
- **Secrets**: Use `SecretVault` (AES-256-GCM) for client secrets.
- **Logging**: All security events MUST be logged via `AuditLogger`.
- **LTI Context**: When in LTI embedded contexts, use `SameSite=None; Secure` for cookies (see `plugin/lti-cookie-override.php`).

### Database
- Use standard table prefixes: `wp_lti_platforms`, `wp_lti_deployments`, `wp_lti_audit_log`.
- Field Names:
  - `key_set_url` (NOT `jwks_url`)
  - `token_url` (NOT `access_token_url`)

### LTI 1.3 Implementation Details
- **Deep Linking 2.0**: Requires a form POST with a signed JWT, NOT a URL redirect.
- **Deep Linking Claims**:
  - `iss`: MUST be the Tool's Client ID.
  - `aud`: MUST be the Platform's Issuer (look up in `wp_lti_platforms`).
- **Query Params**: Always use RFC3986 encoding (`http_build_query($params, '', '&', PHP_QUERY_RFC3986)`) for parameters containing JSON to avoid WordPress `add_query_arg` corruption.

### Controllers & Services
- Controllers are in `plugin/Controllers/` (OIDC, Launch, DeepLink, AGS).
- Services are in `plugin/Services/` (JWT, Nonce, SecretVault, PlatformRegistry, etc.).
- REST routes are registered in `plugin/routes/rest.php`.

## Manual Setup Requirements
- Moodle sometimes requires manual public key verification. If Deep Linking fails with "Consumer key is incorrect", verify the public key is correctly stored in Moodle's `lti_types_config`.

## Troubleshooting Guidelines
- **Launch Fails**: Check HTTPS, hostnames, and Issuer/Client ID matches.
- **Replay Errors**: Nonce is consumed on first use; browser refresh will always fail LTI launch.
- **Grade Posting Fails**: Check AGS scopes in Moodle and `SecretVault` for client secrets.
