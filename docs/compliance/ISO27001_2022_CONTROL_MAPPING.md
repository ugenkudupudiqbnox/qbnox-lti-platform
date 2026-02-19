# ISO/IEC 27001:2022 Control Mapping
## Pressbooks LTI Platform

This document maps implemented controls to ISO/IEC 27001:2022 Annex A.

| ISO 27001:2022 Control | Description | Implementation Evidence |
|------------------------|-------------|-------------------------|
| A.5.1 | Information security policies | Architecture.md, Security.md, Go-Live SOP |
| A.5.23 | Information security for use of cloud services | Bedrock deployment model, hosting SOP |
| A.6.1 | Roles and responsibilities | LTI trust boundary documentation |
| A.8.1 | Inventory of information and assets | Asset classification in SECURITY.md, ARCHITECTURE.md |
| A.8.10 | Information deletion | Token TTL, nonce expiry |
| A.9.1 | Access control policy | Role mapping, least privilege |
| A.9.2 | User access management | LMS-based identity, no local passwords |
| A.10.1 | Cryptographic controls | RS256, AES-256-GCM, JWKS |
| A.12.4 | Logging and monitoring | AuditLogger, audit UI |
| A.12.6 | Capacity management | Token caching, stateless design |
| A.14.2 | Secure development lifecycle | Git, code reviews, Composer isolation |
| A.16.1 | Incident management | Audit logs, response procedures |
| A.18.1 | Compliance with policies and standards | 1EdTech LTI compliance, SOPs |
