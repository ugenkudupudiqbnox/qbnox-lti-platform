# Developer Onboarding

## Pressbooks LTI Platform

Welcome 👋
This guide helps developers set up a **fully working local environment** for the Pressbooks LTI Platform, including **Pressbooks, Moodle, and LTI Advantage**, using Docker.

This project is **production-grade infrastructure**, not a demo plugin. Please read carefully.

---

## 1. What You’re Working On

### This repository provides:

* An **LTI 1.3 + LTI Advantage tool platform**
* Integration between **Pressbooks (Bedrock)** and LMSs like **Moodle**
* Support for:

  * Deep Linking 2.0
  * Assignment & Grade Services (AGS)
  * Audit logging
  * Enterprise security controls

### You will typically work on:

* LTI launch & security logic
* Admin UX
* LMS interoperability
* Test automation

---

## 2. Prerequisites

### Required

* Linux or macOS
* Docker ≥ 24.x
* Docker Compose
* Git
* Make

### Recommended

* 16 GB RAM (8 GB minimum)
* Chrome / Firefox (normal mode, not incognito)

---

## 3. Repository Structure (Important)

```
pressbooks-lti-platform/
├── plugin/                 # WordPress multisite plugin (core logic)
├── lti-local-lab/          # Docker-based Pressbooks + Moodle lab
├── scripts/                # Automation scripts
├── docs/                   # Documentation
├── Makefile                # Primary developer interface
└── .github/workflows/      # CI
```

> 🔑 **Rule**:
> Developers interact with the system via `make`, not by running Docker commands manually.

---

## 4. Environment Configuration (CI vs Production)

### Overview

The Docker setup supports **different URL configurations** for different environments:

| Environment | URLs | Configuration |
|------------|------|---------------|
| **CI** (GitHub Actions) | `http://localhost:8081` | Default (no .env file) |
| **Local Development** | `http://localhost:8081` | Default (no .env file) |
| **Production** | `https://pb.lti.qbnox.com` | `.env` file required |

### How It Works

**docker-compose.yml uses environment variables with defaults:**

```yaml
environment:
  WP_HOME: ${PRESSBOOKS_URL:-http://localhost:8081}
  DOMAIN_CURRENT_SITE: ${PRESSBOOKS_DOMAIN:-localhost}
```

**Behavior:**
- ✅ No `.env` file → Uses `localhost` (perfect for CI and local dev)
- ✅ With `.env` file → Uses production URLs

### Production Setup

**1. Create .env file:**

```bash
cd lti-local-lab
cp .env.production .env
```

**2. Edit .env with your domains:**

```dotenv
# Production environment variables
PRESSBOOKS_DOMAIN=pb.yourdomain.com
MOODLE_DOMAIN=moodle.yourdomain.com

# Critical: Set your production Moodle version
MOODLE_VERSION=4.4
```

**Note:** The maintenance scripts use `scripts/load-env.sh` to derive `PRESSBOOKS_URL` and `MOODLE_URL` automatically from these domains.

**3. Restart containers:**

```bash
docker-compose down
docker-compose up -d
```

### Local Development

**No configuration needed!** Just run:

```bash
make up
```

The system will automatically use `http://localhost:8081` for Pressbooks.

### CI (GitHub Actions)

**No configuration needed!** CI automatically uses localhost defaults because there's no `.env` file in the repository (it's gitignored).

### Verifying Configuration

**Check what URLs your containers are using:**

```bash
docker exec pressbooks env | grep -E "WP_HOME|DOMAIN_CURRENT_SITE"
```

**Expected output (production):**
```
WP_HOME=https://pb.lti.qbnox.com
DOMAIN_CURRENT_SITE=pb.lti.qbnox.com
```

**Expected output (local/CI):**
```
WP_HOME=http://localhost:8081
DOMAIN_CURRENT_SITE=localhost
```

---

## 5. One-Command Local Setup (Recommended)

From the repository root:

```bash
make up install enable-lti seed test
```

This will:

1. Start Moodle + Pressbooks via Docker
2. Install & activate the LTI plugin in Pressbooks
3. Auto-register the LTI 1.3 tool in Moodle
4. Seed Moodle with test users & a course
5. Run smoke tests

### Expected URLs

* Moodle: `https://moodle.local`
* Pressbooks: `https://pressbooks.local`

---

## 6. Manual Steps (Required Once)

Some steps **must remain manual** to reflect real production behavior.

### Moodle

* Complete the Moodle admin setup wizard
* Log in as admin at least once

### Pressbooks

* Log in as Network Admin
* Configure:

  * LTI Platform (Issuer, Client ID, URLs)
  * Deployment ID
  * Client Secret (Network Admin → LTI Client Secrets)

These steps mirror real institutional deployment.

---

## 7. Common Make Commands

| Command           | What it does                |
| ----------------- | --------------------------- |
| `make up`         | Start Moodle + Pressbooks   |
| `make install`    | Install & activate plugin   |
| `make enable-lti` | Register LTI tool in Moodle |
| `make seed`       | Create users & course       |
| `make test`       | Run smoke tests             |
| `make logs`       | Follow container logs       |
| `make down`       | Stop containers             |
| `make reset`      | Destroy all data            |

---

## 8. Development Workflow

### Typical flow

1. Make code changes in `plugin/`
2. Run:

   ```bash
   make test
   ```
3. Test manually via Moodle UI
4. Check:

   * Audit logs
   * Role mapping
   * AGS behavior

### Hot reload

* PHP changes are picked up immediately
* No container rebuild needed unless dependencies change

---

## 9. Testing Strategy

### Automated

* Smoke tests (`scripts/lti-smoke-test.sh`)
* CI runs on every PR

### Manual (Required for LTI)

* Instructor launch
* Student launch
* Deep Linking
* Grade return (AGS)
* Failure cases (invalid aud, replay, scope violation)

See:

```
docs/testing/PRESSBOOKS_MOODLE_TEST_CHECKLIST.md
```

---

## 10. Security Expectations (Read Carefully)

* ❌ Never log secrets
* ❌ Never hardcode client secrets
* ❌ Never bypass JWT validation
* ❌ Never disable nonce / replay protection

Security regressions will block merges.

If you find a vulnerability:

* **Do not open a public issue**
* Follow `SECURITY.md`

---

## 11. CI & Pull Requests

### CI does:

* Bring up full Docker lab
* Register LTI tool
* Run smoke tests

### PR expectations

* Clear commit messages
* Minimal, focused changes
* No breaking config changes without discussion

---

## 12. Troubleshooting

### LTI launch fails

* Check HTTPS
* Check hostnames (`moodle.local`, `pressbooks.local`)
* Verify Issuer / Client ID match exactly

### Replay errors

* Browser refresh = expected failure
* Use fresh launch from Moodle

### AGS fails

* **JWT has no AGS endpoint claim (`has_ags=no`)**: Check that `mdl_lti_types_config` has `ltiservice_gradesynchronization=2` for the tool type. Moodle's `get_launch_parameters` only injects AGS claims when this key is present. Using `ags_grades_service` (wrong key) silently does nothing because `lti_add_type()` saves `ltiservice_*` keys as-is but strips `lti_*` prefixes. Run `make enable-lti` to re-register with the correct config.
* **lineitem not stored after launch**: If `has_ags=yes` but `_lti_ags_lineitem_user_{id}` is missing from `wp_2_postmeta`, check that `mdl_ltiservice_gradebookservices` has a row for this LTI activity's `ltilinkid`. This is created by Moodle when Deep Linking returns a `lineItem` field.
* Check OAuth2 scopes granted in Moodle tool config
* Check client secret stored in `SecretVault`
* Check token cache expiry (60min)

---

## 13. Philosophy (Why this matters)

This project exists to give universities:

* Ownership
* Transparency
* Auditability
* Long-term sustainability

Treat this as **critical infrastructure**, not a plugin experiment.

---

## 14. Getting Help

* Read docs first
* Check audit logs
* Review CI output
* Ask maintainers with context

---

## ✅ You’re Ready

If you reached this point:

* You can run the full stack
* You understand the architecture
* You can safely contribute

Welcome aboard 🚀
