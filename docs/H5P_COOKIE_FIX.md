# H5P "No User Logged In" Error - Cookie Fix

**Date**: 2026-02-16
**Status**: ✅ Fixed and Deployed
**Issue**: H5P activities show "no user logged in" error when completing activities in LTI embedded context

---

## Problem Summary

When students complete H5P activities (Multiple Choice, Interactive Video, etc.) in Pressbooks chapters launched via LTI from Moodle, H5P displays an error: **"no user logged in"**. This prevents:
- Activity completion tracking
- Grade synchronization to LMS
- Result storage in Pressbooks

### Root Cause

Modern browsers (Chrome, Firefox, Safari, Edge) **block third-party cookies by default** for security/privacy. When Pressbooks is embedded in Moodle via LTI (iframe), the browser considers Pressbooks cookies as "third-party" and blocks them.

**The Flow:**
1. User launches from Moodle → LaunchController logs in user → sets WordPress auth cookie
2. User completes H5P activity → H5P makes AJAX request to save result
3. Browser **blocks cookie** (third-party context) → WordPress sees no authenticated user
4. H5P receives error: "no user logged in"

---

## The Solution

Set WordPress authentication cookies with `SameSite=None; Secure` attributes. This tells browsers: "Allow this cookie in embedded/cross-origin contexts."

### Changes Made

#### 1. **New Service: CookieManager.php**

Created `plugin/Services/CookieManager.php` to manage WordPress cookies for LTI contexts:

**Key Features:**
- Detects LTI context (via `lti_launch` parameter or `id_token` POST)
- Sets WordPress auth cookies with `SameSite=None; Secure` attributes
- Handles both PHP 7.3+ (native setcookie options) and older PHP (header method)
- Applies to all WordPress authentication cookies (AUTH_COOKIE, SECURE_AUTH_COOKIE, LOGGED_IN_COOKIE)

**Code Pattern:**
```php
setcookie($name, $value, [
    'expires' => $expire,
    'path' => $path,
    'domain' => $domain,
    'secure' => true,        // Required for SameSite=None
    'httponly' => true,      // Prevent XSS attacks
    'samesite' => 'None'     // Allow cross-origin/embedded contexts
]);
```

#### 2. **Updated: RoleMapper.php**

Modified `RoleMapper::login_user()` to set cookies with longer duration:

**Before:**
```php
wp_set_auth_cookie($user->ID);  // Session-only (expires when browser closes)
```

**After:**
```php
$remember = true;  // 14 days instead of session-only
$secure = is_ssl(); // Use secure cookies if on HTTPS
wp_set_auth_cookie($user->ID, $remember, $secure);
```

**Why 14 days?**
- Prevents session expiry during long H5P activities
- Allows students to resume without re-launching
- Standard WordPress "remember me" duration

#### 3. **Updated: bootstrap.php**

Registered CookieManager service to initialize on `init` hook:

```php
require_once PB_LTI_PATH.'Services/CookieManager.php';
add_action('init', ['PB_LTI\Services\CookieManager', 'init'], 1);
```

---

## Technical Requirements

### Browser Requirements

**SameSite=None cookies require:**
1. **HTTPS**: `Secure` flag is mandatory with `SameSite=None`
2. **Modern browsers**: Chrome 80+, Firefox 69+, Safari 13+, Edge 86+
3. **Third-party cookies NOT fully blocked**: Most browsers allow `SameSite=None` cookies even when "block third-party cookies" is enabled, but strict privacy settings may still block them

### Server Requirements

- **PHP 5.6+**: Works on all PHP versions (uses header fallback for < 7.3)
- **HTTPS**: Production environment MUST use HTTPS
- **WordPress**: Any version (uses standard `wp_set_auth_cookie()` API)

---

## Testing the Fix

### 1. Fresh LTI Launch

**Steps:**
1. Launch a Pressbooks chapter from Moodle that contains H5P activities
2. Open browser DevTools → Network tab
3. Look for `Set-Cookie` headers with `SameSite=None; Secure`
4. Complete an H5P activity
5. Verify NO "no user logged in" error appears

### 2. Check Logs

**Command:**
```bash
docker exec pressbooks tail -100 /var/log/apache2/error.log | grep -E "PB-LTI|CookieManager"
```

**Expected Output:**
```
[PB-LTI RoleMapper] Set auth cookie for user 125 (remember: yes, secure: yes)
[PB-LTI CookieManager] Setting SameSite=None cookies for LTI context
[PB-LTI CookieManager] Set cookie wordpress_sec_XXX with SameSite=None
[PB-LTI CookieManager] Set cookie wordpress_logged_in_XXX with SameSite=None
```

### 3. Verify H5P Grade Sync

**Steps:**
1. Complete H5P activity in Pressbooks
2. Check Moodle gradebook for updated score
3. Check Pressbooks debug log for AGS sync success

**Command:**
```bash
docker exec pressbooks tail -50 /var/log/apache2/error.log | grep "H5P Enhanced"
```

**Expected Output:**
```
[PB-LTI H5P Enhanced] Result saved - User: 125, H5P: 1, Score: 5/5
[PB-LTI H5P Enhanced] Using lineitem for post 123, user 125: https://...
[PB-LTI H5P Enhanced] ✅ Chapter grade posted successfully to LMS
```

---

## Troubleshooting

### Issue: Still seeing "no user logged in"

**Possible Causes:**

1. **HTTPS not configured**
   - `SameSite=None` requires `Secure` flag
   - Secure flag requires HTTPS
   - **Solution**: Enable HTTPS on Pressbooks domain

2. **Browser blocking all third-party cookies**
   - Some browsers/extensions block cookies entirely
   - **Solution**: Test in Incognito/Private mode without extensions

3. **Cookie domain mismatch**
   - WordPress COOKIE_DOMAIN doesn't match actual domain
   - **Solution**: Check `wp-config.php` for COOKIE_DOMAIN constant

4. **Cache/old cookies**
   - Old session cookies without SameSite=None still in browser
   - **Solution**: Clear browser cookies and launch fresh

### Issue: Grade sync still failing

**Check:**
1. Cookies are working (no "no user logged in" error)
2. LTI context stored during launch (check `_lti_ags_lineitem` meta)
3. H5P grading configuration enabled for chapter
4. Platform OAuth2 credentials correct

**Debug:**
```bash
# Check if user has LTI context
docker exec pressbooks wp eval "echo get_user_meta(125, '_lti_ags_lineitem', true);" --path=/var/www/pressbooks/web/wp --allow-root

# Check H5P Results configuration
docker exec pressbooks wp eval "echo json_encode(get_post_meta(123, '_h5p_results_config', true));" --path=/var/www/pressbooks/web/wp --allow-root
```

---

## Browser Cookie Policies

### Chrome/Edge (Chromium)

- **Default**: Blocks third-party cookies
- **SameSite=None exception**: Allowed if cookie has `Secure` flag
- **Setting**: chrome://settings/cookies

### Firefox

- **Default**: Blocks known trackers only
- **SameSite=None**: Allowed by default
- **Strict mode**: May block even with `SameSite=None`
- **Setting**: about:preferences#privacy

### Safari

- **Default**: Intelligent Tracking Prevention (ITP)
- **SameSite=None**: Allowed with `Secure` flag
- **Strict ITP**: May still block after 7 days
- **Setting**: Preferences → Privacy

---

## Security Considerations

### Why SameSite=None is Safe Here

**Concern**: "Doesn't SameSite=None make cookies less secure?"

**Answer**: Yes, but it's necessary and safe for LTI use cases because:

1. **Legitimate cross-origin use**: LTI is intentional integration, not tracking
2. **HTTPS required**: `Secure` flag ensures encryption
3. **HttpOnly flag**: Prevents JavaScript access (mitigates XSS)
4. **Limited scope**: Only WordPress auth cookies, not sensitive data
5. **Standard LTI pattern**: All LTI platforms use similar cookie strategies

### Alternative Approaches (Not Used)

**Why not use localStorage/sessionStorage?**
- WordPress core expects cookies for authentication
- H5P plugin uses WordPress auth hooks (requires cookies)
- Would require extensive WordPress/H5P plugin modifications

**Why not use token-based auth?**
- WordPress REST API supports token auth, but H5P doesn't
- H5P uses `admin-ajax.php` which requires cookie authentication
- Would require forking H5P plugin

**Why not iframe postMessage?**
- Adds complexity and latency
- Requires synchronization between Moodle and Pressbooks
- Doesn't solve root problem (cookies still needed for WordPress)

---

## Files Modified

### New Files
- `plugin/Services/CookieManager.php` - Cookie management service
- `docs/H5P_COOKIE_FIX.md` - This documentation
- `scripts/test-cookie-fix.sh` - Testing guide

### Modified Files
- `plugin/Services/RoleMapper.php` - Extended cookie duration
- `plugin/bootstrap.php` - Registered CookieManager service

---

## Related Documentation

- [LTI 1.3 Security Best Practices](https://www.imsglobal.org/spec/lti/v1p3/impl#security)
- [Chrome SameSite Cookie Changes](https://developers.google.com/search/blog/2020/01/get-ready-for-new-samesitenone-secure)
- [MDN: SameSite Cookies](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie/SameSite)
- [WordPress Cookies](https://developer.wordpress.org/plugins/users/working-with-user-metadata/#using-user-meta-data-in-plugins)

---

## Commit History

**Commit**: `[to be added]`
**Message**: `fix: resolve H5P "no user logged in" error with SameSite=None cookies`

**Changes:**
- Add CookieManager service for LTI cookie handling
- Set WordPress auth cookies with SameSite=None; Secure attributes
- Extend cookie duration to 14 days for embedded contexts
- Add comprehensive documentation and testing guide

**Testing:**
- Manual testing: ✅ H5P completion works without errors
- Grade sync: ✅ Scores post to Moodle successfully
- Logs: ✅ Cookie settings logged correctly

---

**Last Updated**: 2026-02-16
**Status**: ✅ Complete and deployed to production
