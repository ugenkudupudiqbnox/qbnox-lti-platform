# LTI 1.3 Integration - Complete Setup Guide

## Production Environment
- **Moodle**: https://moodle.lti.qbnox.com
- **Pressbooks**: https://pb.lti.qbnox.com

## ✅ Step 1: Tool Registration - COMPLETE

### Moodle Configuration
- **Tool ID**: 1
- **Tool Name**: Pressbooks LTI Platform
- **Client ID**: `pb-lti-ce86a36fa1e79212536130fe7b6e8292`
- **LTI Version**: 1.3
- **Status**: Active

### Pressbooks Configuration
- **Platform Issuer**: https://moodle.lti.qbnox.com
- **Client ID**: pb-lti-ce86a36fa1e79212536130fe7b6e8292
- **Deployment ID**: 1
- **Database Table**: `wp_lti_platforms` created and populated

### Endpoints Configuration
| Type | URL |
|------|-----|
| Moodle Auth | https://moodle.lti.qbnox.com/mod/lti/auth.php |
| Moodle Token | https://moodle.lti.qbnox.com/mod/lti/token.php |
| Moodle JWKS | https://moodle.lti.qbnox.com/mod/lti/certs.php |
| Pressbooks Login | https://pb.lti.qbnox.com/wp-json/pb-lti/v1/login |
| Pressbooks Launch | https://pb.lti.qbnox.com/wp-json/pb-lti/v1/launch |
| Pressbooks Keyset | https://pb.lti.qbnox.com/wp-json/pb-lti/v1/keyset |
| Pressbooks Deep Link | https://pb.lti.qbnox.com/wp-json/pb-lti/v1/deep-link |
| Pressbooks AGS | https://pb.lti.qbnox.com/wp-json/pb-lti/v1/ags/post-score |

---

## 🧪 Step 2: Test LTI Launch

### Manual Testing Steps
1. Log into Moodle as **instructor** (instructor / Instructor123!)
2. Navigate to "LTI Test Course"
3. Turn editing on
4. Add activity → External tool
5. Configure:
   - Name: "Pressbooks Content"
   - Preconfigured tool: Pressbooks LTI Platform
   - Accept grades: Yes
   - Maximum grade: 100
   - Launch container: New window
6. Save and click the activity link
7. Verify redirect to Pressbooks with auto-login

### Expected Behavior
✅ OIDC login redirect to Pressbooks
✅ JWT validation succeeds
✅ User auto-created/logged in to Pressbooks
✅ Content displays
✅ Return link works

### Troubleshooting
```bash
# Check Pressbooks logs
docker logs pressbooks 2>&1 | grep PB-LTI | tail -20

# Check JWT validation
curl -k https://pb.lti.qbnox.com/wp-json/pb-lti/v1/keyset

# Verify platform registration
docker exec pressbooks wp db query "SELECT * FROM wp_lti_platforms" --allow-root
```

---

## 📚 Step 3: Deep Linking

### Configuration
1. In Moodle → Site admin → Plugins → External tool → Manage tools
2. Edit "Pressbooks LTI Platform"
3. Services → Enable:
   - ☑ Content-Item Message (LTI Deep Linking)
   - ☑ Tool Settings

### Create Deep Link Activity
1. Add activity → External tool
2. Check "Select content" option
3. Choose Pressbooks LTI Platform
4. Click to launch content picker
5. Select book/chapter in Pressbooks
6. Content embeds back in Moodle

### Deep Linking Flow
```
Moodle → DeepLinkRequest (with deep_linking_settings)
  ↓
Pressbooks Content Picker UI
  ↓
User selects content
  ↓
Pressbooks → DeepLinkResponse (signed JWT with content items)
  ↓
Moodle receives and embeds content
```

### Deep Link JWT Structure
```json
{
  "message_type": "LtiDeepLinkingResponse",
  "deployment_id": "1",
  "content_items": [
    {
      "type": "ltiResourceLink",
      "title": "Chapter 1: Introduction",
      "url": "https://pb.lti.qbnox.com/book/chapter-1",
      "custom": {
        "book_id": "123",
        "chapter_id": "456"
      }
    }
  ]
}
```

---

## 📊 Step 4: Assignment & Grade Services (AGS)

### Enable AGS in Moodle
1. External tool configuration
2. Services → Enable:
   - ☑ IMS LTI Assignment and Grade Services

### Create Gradable Activity
1. Create External tool activity
2. Settings:
   - Accept grades from tool: **Yes**
   - Maximum grade: 100
3. Moodle creates lineitem automatically

### AGS Grade Posting Flow
```
User completes activity in Pressbooks
  ↓
Pressbooks requests OAuth2 token from Moodle
  ↓
Pressbooks POSTs score to AGS endpoint
  ↓
Grade appears in Moodle gradebook
```

### Test Grade Posting
```bash
# Get lineitem from launch claim
# (Available in JWT claim: https://purl.imsglobal.org/spec/lti-ags/claim/endpoint)

# OAuth2 Token Request
curl -X POST https://moodle.lti.qbnox.com/mod/lti/token.php \
  -d "grant_type=client_credentials" \
  -d "client_assertion_type=urn:ietf:params:oauth:client-assertion-type:jwt-bearer" \
  -d "client_assertion=<JWT_SIGNED_BY_PRESSBOOKS>" \
  -d "scope=https://purl.imsglobal.org/spec/lti-ags/scope/score"

# POST Score
curl -X POST <LINEITEM_URL>/scores \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -H "Content-Type: application/vnd.ims.lis.v1.score+json" \
  -d '{
    "timestamp": "2026-02-07T17:00:00Z",
    "scoreGiven": 85,
    "scoreMaximum": 100,
    "activityProgress": "Completed",
    "gradingProgress": "FullyGraded",
    "userId": "<USER_ID>"
  }'
```

### Verify Grade in Moodle
1. Go to course → Gradebook
2. Check grade appears for the activity
3. Grade = (scoreGiven / scoreMaximum) × Maximum grade

---

## 🔐 Security Configuration

### JWT Signature Validation
- Pressbooks validates incoming JWTs using Moodle's JWKS
- Moodle validates Deep Link responses using Pressbooks JWKS
- RS256 algorithm required

### Nonce Replay Protection
- Each nonce valid for 60 seconds
- Stored in transients: `pb_lti_nonce_{nonce}`

### Client Secret Encryption
- Stored in `SecretVault` with AES-256-GCM
- Key derived from WordPress AUTH_KEY + SECURE_AUTH_KEY

---

## 📋 Test Accounts

| System | Username | Password | Role |
|--------|----------|----------|------|
| Moodle | admin | Admin123! | Administrator |
| Moodle | instructor | Instructor123! | Editing Teacher |
| Moodle | student | Student123! | Student |
| Pressbooks | admin | Admin@123 | Super Admin |

---

## 🚨 Common Issues & Fixes

### "Invalid request" on launch
- **Cause**: Missing required parameters
- **Fix**: Check iss, login_hint, target_link_uri in OIDC request

### "Unknown platform" error
- **Cause**: Platform not registered in Pressbooks
- **Fix**: Run platform configuration script or check wp_lti_platforms table

### JWT validation fails
- **Cause**: Signature mismatch or expired token
- **Fix**: Verify JWKS URLs are accessible, check system clocks

### Grades not appearing
- **Cause**: AGS scope not granted or invalid token
- **Fix**: Verify AGS is enabled in tool configuration, check OAuth2 token

### Deep Linking returns empty
- **Cause**: Content picker not implemented or JWT signing fails
- **Fix**: Implement content picker UI, verify RSA key pair

---

## 🛠️ Maintenance Commands

```bash
# Restart services
docker restart pressbooks lti-local-lab_moodle_1

# Check logs
docker logs pressbooks 2>&1 | grep LTI
docker exec lti-local-lab_moodle_1 tail -f /var/www/html/moodledata/temp/debuglogs/*.log

# Verify endpoints
curl -k https://pb.lti.qbnox.com/wp-json/pb-lti/v1/keyset
curl -k https://moodle.lti.qbnox.com/mod/lti/certs.php

# Database queries
docker exec pressbooks wp db query "SELECT * FROM wp_lti_platforms" --allow-root
docker exec lti-local-lab_moodle_1 mysql -uroot -proot moodle -e "SELECT * FROM mdl_lti_types;"

# Clear caches
docker exec pressbooks wp cache flush --allow-root
```

---

## 📚 References

- [IMS LTI 1.3 Core Specification](https://www.imsglobal.org/spec/lti/v1p3/)
- [IMS LTI Advantage Complete](https://www.imsglobal.org/spec/lti/v1p3/impl)
- [Deep Linking 2.0](https://www.imsglobal.org/spec/lti-dl/v2p0)
- [Assignment & Grade Services](https://www.imsglobal.org/spec/lti-ags/v2p0)
- [Moodle LTI Documentation](https://docs.moodle.org/en/LTI_and_Moodle)

---

## ✅ Integration Checklist

- [x] SSL certificates installed
- [x] Moodle fully configured
- [x] Pressbooks Bedrock installed
- [x] LTI plugin activated
- [x] REST endpoints verified
- [x] Tool registered in Moodle
- [x] Platform registered in Pressbooks
- [ ] LTI launch tested
- [ ] Deep Linking tested
- [ ] AGS grade passback tested
- [ ] User provisioning verified
- [ ] Role mapping validated

---

**Integration completed on:** 2026-02-07
**Platform versions:** Moodle 4.4.4 | Pressbooks 6.32.0 | LTI Plugin 0.8.0
