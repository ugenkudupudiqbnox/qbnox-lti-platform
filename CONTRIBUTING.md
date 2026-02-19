# Contributing Guide

Thank you for contributing to the Pressbooks LTI Platform. This is production-grade infrastructure used by universities — contributions are held to a high standard.

## Getting Started

1. Fork the repository
2. Set up a local environment: `make` (see `docs/SETUP_GUIDE.md` Part 3)
3. Create a feature branch: `git checkout -b feature/your-change`
4. Make changes in `plugin/` — PHP hot-reloads, no container rebuild needed
5. Run `make test` before submitting
6. Open a pull request with a clear description of what changed and why

## Pull Request Expectations

- **One concern per PR** — keep changes minimal and focused
- **Explain the why** in your commit messages, not just the what
- **No breaking configuration changes** without prior discussion in an issue
- **Security regressions block merge** — do not weaken JWT validation, nonce protection, or secret handling
- Tests must pass (CI runs automatically on every PR)

## Security Requirements

These are non-negotiable:

- Never log secrets or tokens
- Never hardcode client secrets — use `SecretVault`
- Never bypass JWT validation (`JwtValidator`) or disable nonce replay protection
- Never skip HTTPS in production

**Reporting vulnerabilities:** Do NOT open a public issue. Follow `SECURITY.md` or email ugen@qbnox.com.

## Code Style

- Follow existing patterns in `plugin/` — Controllers stay thin, logic lives in Services
- No premature abstractions — solve the current problem, not hypothetical future ones
- Do not add docstrings, comments, or error handling for code you did not change
- Security and auditability take priority over convenience

## Governance

This project follows a **benevolent maintainer** model:

- **Minor changes** (bug fixes, docs): maintainer review and merge
- **Major changes** (architecture, new services, breaking config): open an issue for design discussion first
- **Security issues**: private disclosure only (see above)

Decisions prioritize long-term institutional ownership, standards compliance, and auditability over short-term convenience.
