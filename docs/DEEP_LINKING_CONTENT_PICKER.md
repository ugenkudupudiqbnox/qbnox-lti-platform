# Deep Linking Content Picker - User Guide

## Overview

The Deep Linking Content Picker allows instructors to select specific Pressbooks content (books, chapters, or pages) when creating External Tool activities in their LMS (Moodle, Canvas, etc.).

## How It Works

### For Instructors

1. **Create External Tool Activity**
   - In your LMS course, add a new "External Tool" activity
   - Select "Pressbooks LTI Platform" as the tool

2. **Select Content**
   - Click the "Select Content" button during activity setup
   - You'll be redirected to the Pressbooks Content Picker interface

3. **Choose Your Content**
   - **Whole Book**: Click on a book card to select the entire book
   - **Specific Chapter**: Click "View Chapters" to expand the book, then select a specific chapter
   - **Front/Back Matter**: Access introduction, preface, appendices, etc.

4. **Confirm Selection**
   - Review your selection in the blue info box at the top
   - Click "Select This Content" to confirm
   - You'll be redirected back to your LMS with the selected content

### For Students

When students click the activity link:
- They are automatically logged into Pressbooks (via LTI SSO)
- They are taken directly to the content selected by the instructor
- No manual navigation required

## Features

### Content Organization

The picker displays content in a hierarchical structure:

- **📚 Books**: All published books in the Pressbooks network
- **📖 Chapters**: Main content organized by parts (if applicable)
- **📄 Front Matter**: Introduction, preface, foreword, etc.
- **📄 Back Matter**: Appendices, bibliography, glossary, etc.

### Search & Navigation

- Books are listed with titles, descriptions, and URLs
- Click "View Chapters" to expand a book's structure
- Visual indicators show content type (Chapter, Front, Back)
- Selected content is highlighted in blue

### Responsive Design

- Works on desktop, tablet, and mobile devices
- Clean, modern interface
- Accessible keyboard navigation

## Technical Details

### LTI Deep Linking 2.0 Flow

```
1. Instructor clicks "Select Content" in LMS
   ↓
2. LMS sends Deep Linking Request to Pressbooks
   ↓
3. Pressbooks shows Content Picker UI
   ↓
4. Instructor selects book/chapter
   ↓
5. Pressbooks signs JWT with selected content
   ↓
6. Redirects back to LMS with JWT
   ↓
7. LMS stores selected content URL in activity
   ↓
8. Students launch → go directly to selected content
```

### Security

- All Deep Linking responses are signed with RS256 JWT
- Content selection is validated before JWT generation
- Only published content is displayed in the picker
- Full audit logging of all Deep Linking requests

### API Endpoints

**GET** `/wp-json/pb-lti/v1/deep-link`
Shows the content picker UI

**POST** `/wp-json/pb-lti/v1/deep-link`
Processes selection and returns signed JWT

**AJAX** `wp_ajax_pb_lti_get_book_structure`
Dynamically loads chapter structure for books

## Testing

### Manual Testing

```bash
# Generate test URL
bash scripts/test-deep-link-ui.sh

# Open URL in browser to test content picker
```

### Automated Testing

```bash
# Run Deep Linking compliance tests
make test-deep-linking
```

### Expected Test Results

✅ Content picker loads with book list
✅ "View Chapters" expands book structure
✅ Selection updates visual indicators
✅ Form submission generates valid JWT
✅ JWT includes correct content_items claim
✅ Redirect back to LMS with JWT parameter

## Architecture

### Files

- **Controller**: `plugin/Controllers/DeepLinkController.php`
- **Service**: `plugin/Services/ContentService.php`
- **View**: `plugin/views/deep-link-picker.php`
- **AJAX**: `plugin/ajax/handlers.php`

### Database Tables

Content is queried from standard WordPress tables:
- `wp_blogs`: Multisite books
- `wp_posts`: Chapters, front-matter, back-matter
- `wp_postmeta`: Chapter-to-part relationships

### Content Types

Pressbooks uses custom post types:
- `chapter`: Main book chapters
- `part`: Organizational structure (optional)
- `front-matter`: Introductory content
- `back-matter`: Supplementary content

## Troubleshooting

### No Books Displayed

**Cause**: No published books in Pressbooks network

**Solution**:
```bash
# Create test books
make seed-books

# Or manually create books via Network Admin
```

### Chapters Not Loading

**Cause**: AJAX handler not registered or JS error

**Solution**:
- Check browser console for errors
- Verify `ajax/handlers.php` is loaded in `bootstrap.php`
- Test AJAX endpoint directly:
  ```bash
  curl -X POST https://pb.lti.qbnox.com/wp-admin/admin-ajax.php \
    -d "action=pb_lti_get_book_structure&book_id=2"
  ```

### JWT Signature Failure

**Cause**: RSA keys not configured

**Solution**:
```bash
# Generate RSA keys
docker exec pressbooks php /path/to/generate-rsa-keys.php
```

### Selection Not Saved in LMS

**Cause**: LMS not configured to accept Deep Linking responses

**Solution**:
- Verify tool registration includes `LtiDeepLinkingRequest` capability
- Check `lti_contentitem` field is set to `1` in tool configuration
- Verify `deep_link_return_url` in LMS tool settings

## Future Enhancements

- 🔍 Search functionality across all books
- 📋 Bulk selection (multiple chapters at once)
- 🎨 Thumbnail previews of book covers
- 📊 Usage analytics (most-selected content)
- 🔖 Instructor favorites / recent selections
- 🌐 Multi-language support

## Related Documentation

- `WHAT_IS_DEEP_LINKING.md`: Non-technical explanation
- `docs/testing/PRESSBOOKS_MOODLE_TEST_CHECKLIST.md`: Manual testing guide
- `CLAUDE.md`: Development patterns and architecture
