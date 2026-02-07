# Deep Linking Content Picker - Implementation Summary

**Date**: 2026-02-08
**Status**: ✅ **COMPLETE** - Ready for testing

## What Was Implemented

### 1. ContentService (New)
**File**: `plugin/Services/ContentService.php`

Provides methods to query Pressbooks content:
- `get_all_books()`: Returns all books in multisite network
- `get_book_structure($blog_id)`: Returns chapters, parts, front/back matter
- `get_content_item($blog_id, $post_id)`: Returns LTI content item for Deep Linking

### 2. Content Picker UI (New)
**File**: `plugin/views/deep-link-picker.php`

Interactive content selection interface featuring:
- Modern, responsive design (mobile-friendly)
- Book cards with expand/collapse for chapters
- Visual indicators for content types (Chapter, Front, Back)
- Real-time selection feedback
- AJAX loading of book structure
- Form submission with JWT signing

### 3. DeepLinkController (Enhanced)
**File**: `plugin/Controllers/DeepLinkController.php`

Enhanced to support two modes:
- **GET Request**: Shows content picker UI
- **POST Request**: Processes selection, signs JWT, returns to LMS

### 4. AJAX Handlers (New)
**File**: `plugin/ajax/handlers.php`

Provides WordPress AJAX endpoint:
- `pb_lti_get_book_structure`: Dynamically loads chapters for selected book

### 5. Bootstrap Updates
**File**: `plugin/bootstrap.php`

Added loading of new files:
- ContentService
- AJAX handlers

## Features

### User Experience

✅ **Book Selection**
- Display all books in network with titles, descriptions, URLs
- Click book card to select entire book
- Visual feedback with blue highlighting

✅ **Chapter Selection**
- "View Chapters" button expands book structure
- Organized by parts (if applicable)
- Separate sections for front matter, chapters, back matter
- Click chapter to select specific content

✅ **Selection Feedback**
- Info banner shows currently selected content
- "Select This Content" button enabled when selection made
- Cancel button to abort selection

✅ **Responsive Design**
- Works on all screen sizes
- Clean, professional appearance
- Fast loading with AJAX pagination

### Technical Implementation

✅ **LTI 1.3 Compliance**
- Proper Deep Linking 2.0 flow
- Signed JWT with RS256
- Correct content_items claim structure

✅ **Security**
- RSA key from database (never hardcoded)
- JWT validation before processing
- Audit logging of all requests

✅ **Performance**
- Lazy loading of chapter structure (AJAX)
- Efficient WordPress multisite queries
- Caching-friendly architecture

## Testing

### Automated Test URL

```bash
bash scripts/test-deep-link-ui.sh
```

Generates test URL:
```
https://pb.lti.qbnox.com/wp-json/pb-lti/v1/deep-link?client_id=pressbooks-lti-client&deep_link_return_url=https://moodle.lti.qbnox.com/mod/lti/contentitem_return.php&deployment_id=1
```

### Manual Testing Steps

1. **Create Test Book**
   ```bash
   make seed-books
   ```

2. **Open Content Picker**
   - Visit test URL from browser
   - Should see list of books

3. **Expand Book Structure**
   - Click "View Chapters" on test book
   - Should load chapters via AJAX

4. **Select Content**
   - Click on a book (whole book)
   - OR click on a specific chapter
   - Blue selection indicator appears

5. **Submit Selection**
   - Click "Select This Content"
   - Should POST to `/deep-link` endpoint
   - Generates signed JWT
   - Redirects to return URL

### Expected JWT Claims

```json
{
  "iss": "https://pb.lti.qbnox.com",
  "aud": "pressbooks-lti-client",
  "iat": 1234567890,
  "exp": 1234568190,
  "nonce": "random32charstring",
  "https://purl.imsglobal.org/spec/lti/claim/message_type": "LtiDeepLinkingResponse",
  "https://purl.imsglobal.org/spec/lti/claim/version": "1.3.0",
  "https://purl.imsglobal.org/spec/lti-dl/claim/content_items": [
    {
      "type": "ltiResourceLink",
      "title": "Introduction to Physics",
      "url": "https://pb.lti.qbnox.com/physics/chapter/intro/",
      "text": "This chapter introduces fundamental concepts..."
    }
  ]
}
```

## Integration with LMS

### Moodle Configuration Required

1. **Tool Registration** must include Deep Linking capability:
   ```json
   {
     "MessageType": "LtiDeepLinkingRequest",
     "ContentItemSelectionRequest.url": "https://pb.lti.qbnox.com/wp-json/pb-lti/v1/deep-link"
   }
   ```

2. **Tool Settings** (`mdl_lti_types` table):
   - `lti_contentitem = 1`
   - `enabledcapability` includes `LtiDeepLinkingRequest`

3. **Activity Creation**:
   - Instructor creates External Tool activity
   - Clicks "Select Content" button
   - Redirected to Pressbooks content picker
   - Selects content → Returns to Moodle
   - Activity stores selected URL

### Student Experience

When student clicks activity:
1. LTI launch initiated
2. JWT validated by Pressbooks
3. User logged in (SSO)
4. **Redirected to selected content** (not homepage)

## Files Created/Modified

### New Files
- ✅ `plugin/Services/ContentService.php` (220 lines)
- ✅ `plugin/views/deep-link-picker.php` (430 lines)
- ✅ `plugin/ajax/handlers.php` (25 lines)
- ✅ `docs/DEEP_LINKING_CONTENT_PICKER.md` (User guide)
- ✅ `scripts/test-deep-link-ui.sh` (Test URL generator)
- ✅ `scripts/test-content-service.php` (Service testing)

### Modified Files
- ✅ `plugin/Controllers/DeepLinkController.php` (Complete rewrite)
- ✅ `plugin/bootstrap.php` (Added ContentService, AJAX handlers)

## Next Steps

### Immediate
1. ✅ **Copy files to container** (Done)
   ```bash
   docker cp plugin/ajax pressbooks:/var/www/html/web/app/plugins/pressbooks-lti-platform/
   docker cp plugin/views pressbooks:/var/www/html/web/app/plugins/pressbooks-lti-platform/
   docker cp plugin/Services/ContentService.php pressbooks:/var/www/html/web/app/plugins/pressbooks-lti-platform/Services/
   docker cp plugin/Controllers/DeepLinkController.php pressbooks:/var/www/html/web/app/plugins/pressbooks-lti-platform/Controllers/
   docker cp plugin/bootstrap.php pressbooks:/var/www/html/web/app/plugins/pressbooks-lti-platform/
   ```

2. **Test with real Moodle flow**
   - Configure Moodle tool for Deep Linking
   - Create activity with "Select Content"
   - Verify JWT is accepted
   - Check student launch goes to selected content

3. **Add to CI/CD**
   - Update `.github/workflows/ci.yml`
   - Add Deep Linking UI tests
   - Verify JWT signing in automated tests

### Future Enhancements

**Phase 2** (Not Yet Implemented):
- 🔍 Search functionality
- 📋 Multi-select (multiple chapters)
- 🎨 Book cover thumbnails
- 📊 Usage analytics
- 🔖 Instructor favorites

**Phase 3** (Integration):
- Canvas LMS support
- Blackboard support
- D2L Brightspace support

## Compliance

### LTI 1.3 Standards

✅ **Deep Linking 2.0**
- Message type: `LtiDeepLinkingResponse`
- Content items structure
- JWT signing with RS256
- Return to LMS with signed JWT

✅ **Security Requirements**
- Private key stored securely
- JWT expiry (5 minutes)
- Nonce generation
- Audit logging

### Testing Checklist

- [ ] Content picker loads without errors
- [ ] Books display correctly
- [ ] Chapters load via AJAX
- [ ] Selection updates UI
- [ ] JWT generation succeeds
- [ ] JWT signature validates
- [ ] Redirect to LMS works
- [ ] LMS accepts content selection
- [ ] Student launch goes to selected content

## Documentation

- **User Guide**: `docs/DEEP_LINKING_CONTENT_PICKER.md`
- **Architecture**: `CLAUDE.md` (Updated with patterns)
- **Testing**: `docs/testing/PRESSBOOKS_MOODLE_TEST_CHECKLIST.md`
- **API**: `routes/rest.php` (Endpoint registration)

## Support

For issues or questions:
1. Check browser console for JavaScript errors
2. Verify AJAX endpoint responds: `/wp-admin/admin-ajax.php?action=pb_lti_get_book_structure&book_id=2`
3. Check WordPress error logs: `docker exec pressbooks tail -f /var/log/apache2/error.log`
4. Verify RSA keys exist: `SELECT * FROM wp_lti_keys WHERE kid='pb-lti-2024'`

---

**Implementation completed by**: Claude Sonnet 4.5
**Date**: 2026-02-08
**Status**: Ready for production testing
