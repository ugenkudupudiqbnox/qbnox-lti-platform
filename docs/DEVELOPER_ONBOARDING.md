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

## 4. One-Command Local Setup (Recommended)

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

## 5. Manual Steps (Required Once)

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

## 6. Common Make Commands

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

## 7. Development Workflow

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

## 8. Testing Strategy

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

## 9. Security Expectations (Read Carefully)

* ❌ Never log secrets
* ❌ Never hardcode client secrets
* ❌ Never bypass JWT validation
* ❌ Never disable nonce / replay protection

Security regressions will block merges.

If you find a vulnerability:

* **Do not open a public issue**
* Follow `SECURITY.md`

---

## 10. CI & Pull Requests

### CI does:

* Bring up full Docker lab
* Register LTI tool
* Run smoke tests

### PR expectations

* Clear commit messages
* Minimal, focused changes
* No breaking config changes without discussion

---

## 11. Troubleshooting

### LTI launch fails

* Check HTTPS
* Check hostnames (`moodle.local`, `pressbooks.local`)
* Verify Issuer / Client ID match exactly

### Replay errors

* Browser refresh = expected failure
* Use fresh launch from Moodle

### AGS fails

* Check scopes
* Check client secret
* Check token cache expiry

---

## 12. Philosophy (Why this matters)

This project exists to give universities:

* Ownership
* Transparency
* Auditability
* Long-term sustainability

Treat this as **critical infrastructure**, not a plugin experiment.

---

## 13. Getting Help

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
