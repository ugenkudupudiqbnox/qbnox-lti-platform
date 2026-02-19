# Pressbooks + Moodle LTI Advantage Test Checklist

This checklist validates **LTI 1.3 + LTI Advantage** integration between
**Moodle** and **Pressbooks** on a staging or production environment.

---

## 0. Environment Sanity Checks

- [ ] HTTPS enabled on both Moodle and Pressbooks
- [ ] Stable hostnames (not localhost)
- [ ] Time synchronized (NTP) on both servers
- [ ] WordPress REST API accessible (`/wp-json/pb-lti/v1/keyset` returns JSON)

---

## 1. LTI Tool Registration

### Moodle
- [ ] Tool type: LTI 1.3
- [ ] Login URL, Redirect URI(s), JWKS URL configured
- [ ] Deployment ID generated
- [ ] AGS services enabled (score POST, lineitem GET/POST)
- [ ] Name and email sharing enabled (`sendname=1`, `sendemailaddr=1`)
- [ ] `ltiservice_gradesynchronization=2` in tool config (required for AGS JWT claim)

### Pressbooks
- [ ] Platform registered (issuer, client_id, auth/token/keyset URLs)
- [ ] Deployment ID registered
- [ ] Client secret stored in SecretVault (not plaintext)
- [ ] RSA key pair present in `wp_lti_keys`

---

## 2. Core LTI Launch

- [ ] Instructor launch → WordPress Editor role assigned
- [ ] Student launch → WordPress Subscriber role assigned
- [ ] Automatic login (SSO) works — no manual login prompt
- [ ] Username matches Moodle username (not firstname.lastname fallback)
- [ ] Real email address synced from Moodle
- [ ] Real first/last name synced from Moodle
- [ ] `wp_lti_audit_log` entry created for launch

---

## 3. Security Validation

- [ ] Invalid issuer rejected (401)
- [ ] Invalid client_id rejected
- [ ] Invalid deployment_id rejected
- [ ] Invalid `aud` claim rejected
- [ ] Expired JWT rejected

---

## 4. Replay Protection

- [ ] Browser refresh of launch page blocked (nonce already consumed)
- [ ] Reuse of same `id_token` blocked within 60-second window

---

## 5. Deep Linking

- [ ] Instructor clicks "Select content" → content picker loads with book list
- [ ] Books expand to show chapters (Front/Chapter/Back matter badges)
- [ ] Selecting chapters and confirming returns signed JWT to Moodle
- [ ] Moodle creates one activity per selected chapter
- [ ] Chapters with H5P grading enabled: Moodle creates a gradebook column
- [ ] Student launch from Deep-Linked activity lands on correct chapter

---

## 6. H5P Grade Sync (AGS)

- [ ] Student launches chapter from Moodle → `_lti_ags_lineitem_user_{id}` stored in chapter post meta
- [ ] H5P Results grading enabled for chapter (meta box in Pressbooks editor)
- [ ] Student completes H5P activity → grade syncs automatically to Moodle gradebook
- [ ] OAuth2 token fetched and cached (second completion within 60 min reuses token)
- [ ] Grade appears in correct Moodle gradebook column for that chapter

---

## 7. Chapter-Specific Grade Routing

- [ ] Student launches Chapter 1 → completes H5P → grade posts to Chapter 1 column
- [ ] Same student launches Chapter 2 → completes H5P → grade posts to Chapter 2 column
- [ ] No cross-chapter grade overwriting

---

## 8. Retroactive Grade Sync

- [ ] Configure H5P grading on a chapter where students already have H5P results
- [ ] "Sync Existing Grades to LMS" button triggers sync
- [ ] Students with LTI context → grades synced
- [ ] Students without LTI context (direct access) → skipped (not failed)
- [ ] Results summary shows synced / skipped / failed counts

---

## 9. Scope Enforcement

- [ ] Missing AGS scope rejected by Pressbooks
- [ ] Correct scope allows grade POST

---

## 10. Audit Logging

- [ ] Successful launch logged in `wp_lti_audit_log`
- [ ] Failed launch (invalid JWT) logged
- [ ] AGS grade post events logged

---

## 11. Upgrade Safety

- [ ] Restart Pressbooks and Moodle services
- [ ] LTI launch still works after restart
- [ ] Grade sync still works after restart

---

## 12. Acceptance

System is production-ready when all items above are checked.
See `docs/ops/GO_LIVE_SOP.md` for full go-live procedure.
