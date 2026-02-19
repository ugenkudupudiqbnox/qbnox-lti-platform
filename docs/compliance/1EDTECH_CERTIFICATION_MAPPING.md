
# 1EdTech LTI Certification Mapping
## Pressbooks LTI Platform

This document maps automated CI evidence to official **1EdTech LTI 1.3 & LTI Advantage certification requirements**.

---

## LTI 1.3 Core

### Security Framework
| Requirement | Spec Reference | Evidence |
|------------|--------------|----------|
| OIDC login | LTI 1.3 Core §5 | CI: OIDC login endpoint |
| JWT signing (RS256) | LTI 1.3 Core §5.1 | CI: JWT cryptographic verification |
| JWKS support | LTI 1.3 Core §5.2 | CI: JWKS fetch + kid match |
| Nonce protection | LTI 1.3 Core §5.4 | CI: replay rejection |
| Audience validation | LTI 1.3 Core §5.3 | CI: aud claim assertion |

---

## Deep Linking 2.0

| Requirement | Spec Reference | Evidence |
|------------|--------------|----------|
| Deep Linking launch | DL §3 | CI: Deep Linking endpoint test |
| Return URL usage | DL §4 | CI: return URL verification |
| Multiple content items | DL §5 | CI: multi-item Deep Linking test |
| content_items structure | DL §5.1 | CI: JWT payload assertions |

---

## Assignment & Grade Services (AGS)

| Requirement | Spec Reference | Evidence |
|------------|--------------|----------|
| OAuth2 client credentials | AGS §4 | CI: token exchange test |
| LineItem creation | AGS §5 | CI: LineItem API assertion |
| Score POST | AGS §6 | CI: grade written to Moodle |
| Role enforcement | AGS §6.2 | CI: student vs instructor checks |
| Per-course grades | AGS §7 | CI: per-course grade validation |

---

## Platform & Tool Compatibility

| Requirement | Evidence |
|------------|----------|
| Moodle 4.x | CI Matrix |
| Moodle 5.x | CI Matrix |
| Pressbooks Bedrock | Docker lab |

---

## Audit & Evidence Retention

| Requirement | Evidence |
|------------|----------|
| Execution logs | CI artifacts |
| DB persistence | Moodle DB dump |
| Test reproducibility | Docker + Makefile |
| Evidence export | CI artifacts (GitHub Actions uploads) |

---

## Certification Readiness

Status: **READY FOR 1EDTECH CERTIFICATION SUBMISSION**

All mandatory LTI 1.3 + Advantage requirements are covered by automated or manual evidence.

---

Maintained by: Pressbooks LTI Platform
Last updated: 2026-02-19
