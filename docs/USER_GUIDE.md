# Pressbooks LTI Platform - User Guide

**For Moodle Administrators and Instructors**

This guide explains how to integrate Pressbooks with your existing Moodle installation using LTI 1.3.

---

## What is LTI?

**Learning Tools Interoperability (LTI)** is a standard that allows external learning tools (like Pressbooks) to integrate seamlessly with Learning Management Systems (like Moodle).

### Benefits
- ✅ Single sign-on (students don't need separate accounts)
- ✅ Automatic grade synchronization
- ✅ Embed Pressbooks content directly in Moodle courses
- ✅ Track student progress
- ✅ Seamless user experience

---

## Prerequisites

### Required
- Moodle 4.1 or higher
- Pressbooks installation with LTI plugin activated
- Admin access to both systems
- HTTPS enabled on both systems

### Access Needed
- **Moodle**: Site Administrator role
- **Pressbooks**: Network Administrator role

---

## 🛑 Critical: Browser & Privacy Settings

Since LTI 1.3 launches occur within an iframe (Pressbooks nested inside Moodle), modern browser privacy protections can block the integration.

### cookie Requirements
- **SameSite=None; Secure**: Your servers MUST be running over **HTTPS**.
- **Third-Party Cookies**: Instructors and students using Chrome or Safari may need to allow "Third-party cookies" for the Moodle domain, or use a browser that hasn't yet fully deprecated third-party cookies for LTI purposes.
- **Incognito Mode**: Launches frequently fail in Incognito/Private mode as browsers aggressively block cross-site cookies in this state.

---

## Step 1: Register Pressbooks in Moodle

### 1.1 Access External Tool Configuration

1. Log in to Moodle as **Site Administrator**
2. Navigate to: **Site administration** → **Plugins** → **Activity modules** → **External tool**
3. Click **Manage tools**

### 1.2 Add New Tool

Click **Configure a tool manually** or **Add LTI Advantage tool**

### 1.3 Enter Tool Details

Fill in the following information:

| Field | Value |
|-------|-------|
| **Tool name** | `Pressbooks` |
| **Tool URL** | `https://your-pressbooks-domain.com` |
| **LTI version** | `LTI 1.3` |
| **Public key type** | `Keyset URL` |
| **Public keyset URL** | `https://your-pressbooks-domain.com/wp-json/pb-lti/v1/keyset` |
| **Initiate login URL** | `https://your-pressbooks-domain.com/wp-json/pb-lti/v1/login` |
| **Redirection URI(s)** | `https://your-pressbooks-domain.com/wp-json/pb-lti/v1/launch` |

**Replace** `your-pressbooks-domain.com` with your actual Pressbooks domain.

### 1.4 Configure Services & Privacy

To enable features like grading and student identification, configure these settings:

**Services (Checkboxes):**
- ☑ **IMS LTI Assignment and Grade Services**: Use this service for grade sync and column management.
- ☑ **IMS LTI Deep Linking**: Enable "Supports Deep Linking" to use the Pressbooks Content Picker.
- ☑ **IMS LTI Names and Role Provisioning**: Request user's name and email.

**Privacy (Critical for Grading):**
- **Share launcher's name**: `Always`
- **Share launcher's email**: `Always`
- **Accept grades from the tool**: `Delegate to teacher` (Required to see the grading checkbox in individual activities).

### 1.5 Save Configuration

1. Click **Save changes**
2. **IMPORTANT**: Note down the following values shown after saving:
   - **Platform ID** (Issuer)
   - **Client ID**
   - **Deployment ID**

You'll need these for Step 2.

---

## Step 2: Register Moodle in Pressbooks

### 2.1 Access Pressbooks Network Admin

1. Log in to Pressbooks as **Network Administrator**
2. Navigate to: **Network Admin** → **Settings** → **LTI Platforms**

*(If you don't see this menu, ensure the LTI plugin is network-activated)*

### 2.2 Add New Platform

Click **Add New Platform**

### 2.3 Enter Platform Details

Fill in the information from Step 1.5:

| Field | Value | Example |
|-------|-------|---------|
| **Platform Name** | `Moodle` | Your institution name |
| **Issuer (Platform ID)** | From Moodle | `https://your-moodle-domain.com` |
| **Client ID** | From Moodle | `abc123xyz...` |
| **Deployment ID** | From Moodle | `1` or `deployment-123` |
| **Auth Login URL** | Moodle's auth endpoint | `https://your-moodle-domain.com/mod/lti/auth.php` |
| **Auth Token URL** | Moodle's token endpoint | `https://your-moodle-domain.com/mod/lti/token.php` |
| **Public Keyset URL** | Moodle's JWKS endpoint | `https://your-moodle-domain.com/mod/lti/certs.php` |

### 2.4 Save Platform

Click **Save Platform**

---

## Step 3: Add Pressbooks Content to Moodle Course

### 3.1 As an Instructor

1. Log in to Moodle as **Teacher/Instructor**
2. Navigate to your course
3. Click **Turn editing on** (top right)

### 3.2 Add External Tool Activity

1. In any section, click **+ Add an activity or resource**
2. Select **External tool** from the list
3. Click **Add**

### 3.3 Configure Activity

| Setting | Value | Notes |
|---------|-------|-------|
| **Activity name** | `Pressbooks: Chapter 1` | Choose a descriptive name |
| **Preconfigured tool** | Select **Pressbooks** | The tool you registered in Step 1 |
| **Select content** | Click the button | Opens the Pressbooks Picker (if Deep Linking is enabled) |
| **Launch container** | `Embed` | Recommended for seamless experience |
| **Grade -> [checkbox]** | **Allow Pressbooks...** | **Check this box** to enable grading options |
| **Grade Type** | `Point` or `Scale` | Appears only after checking the box above |

**Note on Grading:** Moodle hides the Grade Type and Maximum Grade fields until you check **"Allow Pressbooks LTI Platform to add grades in the gradebook"**. If you do not see this checkbox, ensure your administrator set "Accept grades from the tool" to `Delegate to teacher`.

### 3.4 Save Activity

Click **Save and return to course** or **Save and display**

---

## Step 4: Test the Integration

### 4.1 Test as Student

1. Log in as a student (or switch role to student)
2. Click on the Pressbooks activity you created
3. **Expected behavior**:
   - Redirect to Pressbooks
   - Automatic login (no credentials needed)
   - Content displays
   - Student can interact with content

### 4.2 Verify Single Sign-On

Students should **not** be prompted for login credentials. If they are:
- ❌ Check platform registration in Pressbooks
- ❌ Verify HTTPS is enabled on both systems
- ❌ Review browser console for errors

### 4.3 Test Grade Passback (Optional)

If you configured grade passback:
1. Student completes activity in Pressbooks
2. Grade automatically appears in Moodle gradebook
3. Check: **Course → Grades**

---

## Step 4b: Enable H5P Grading on Pressbooks Chapters

> **Required if you want H5P activity scores synced to Moodle's gradebook.**

1. In Pressbooks, open the chapter editor for the chapter you added to Moodle
2. In the right sidebar, find the **"📊 LMS Grade Reporting (LTI AGS)"** meta box
3. Click **"Enable Grading for This Chapter"**
4. Check each H5P activity to include in the grade
5. Set a weight for each (e.g., 100% for a single quiz)
6. Choose aggregation method (Weighted Sum or Weighted Average)
7. Click **Save**

Students who complete the H5P activity via LTI launch will have their grades automatically posted to Moodle.

---

## Using Deep Linking (Content Selection)

Deep Linking allows instructors to browse and select specific Pressbooks content. **For graded chapters, Deep Linking is required** — it creates the Moodle grade column automatically. Manually entering a URL will not create a grade column.

### Enable Deep Linking

1. When adding External Tool activity
2. Check ☑ **Select content** (or **Content Selection**)
3. Click **Select content** button
4. Browse Pressbooks catalog
5. Select book/chapter — if H5P grading is enabled for that chapter, Moodle will automatically create a grade column
6. Content is automatically configured

### Benefits
- Browse available content before adding
- Direct link to specific chapters
- Grade column created automatically for chapters with H5P grading enabled
- Better student experience

---

## Managing Students and Grades

### Automatic User Provisioning

When a student launches a Pressbooks activity:
1. Moodle sends student information to Pressbooks
2. Pressbooks automatically creates user account (if needed)
3. Student is logged in automatically
4. Role is assigned based on Moodle role

### Grade Synchronization

If grade passback is enabled:
1. Student completes activity in Pressbooks
2. Pressbooks sends grade to Moodle via LTI AGS
3. Grade appears in Moodle gradebook
4. Instructor can view/override grade in Moodle

### Privacy

- Student data is transmitted securely (HTTPS + JWT)
- Only necessary information is shared
- Complies with FERPA/GDPR requirements
- Students can request data deletion

---

## Troubleshooting

### Problem: "Invalid request" error when launching

**Possible causes:**
- Client ID mismatch
- Platform not registered correctly

**Solutions:**
1. Verify Client ID matches in both systems
2. Check platform registration in Pressbooks
3. Ensure HTTPS is enabled

### Problem: Students see login page instead of auto-login

**Cause:** Single sign-on not working

**Solutions:**
1. Verify Issuer URL matches exactly
2. Check JWT validation is working
3. Review browser console for errors
4. Test JWKS endpoint accessibility:
   ```
   curl https://your-pressbooks-domain.com/wp-json/pb-lti/v1/keyset
   ```

### Problem: Grades not appearing in Moodle

**Cause:** One or more configuration steps were skipped

**Solutions (check in order):**
1. **Chapter must be added via Deep Linking** — manually entering a URL does NOT create a Moodle grade column. Delete the activity and re-add it using "Select content" (Deep Linking).
2. **H5P grading must be enabled on the Pressbooks chapter** — open the chapter editor, find the "LMS Grade Reporting" meta box, and enable grading with the H5P activities selected.
3. **Student must launch fresh from Moodle** — after grading is configured, the student must click the activity in Moodle again to store the grade column association. Completing H5P without a fresh LTI launch will not sync.
4. Check "Accept grades from the tool" is set to `Delegate to teacher` in the Moodle tool config (Site administration level).
5. Review Pressbooks debug log (`wp-content/debug.log`) for `[PB-LTI H5P Enhanced]` lines to see the sync result.

### Problem: Content not loading / blank page

**Possible causes:**
- CORS issues
- Mixed content (HTTP/HTTPS)
- Firewall blocking

**Solutions:**
1. Check browser console for errors
2. Verify both systems use HTTPS
3. Check server firewall settings
4. Test direct access to Pressbooks

### Problem: Signature Validation Fails / JWT Errors

**Cause:** Server time drift or incorrect keys.

**Solutions:**
1. **Sync Server Time**: Ensure both Moodle and Pressbooks servers have synchronized time via **NTP**. LTI 1.3 JWT signatures will fail if the server clocks are off by more than 60 seconds (prevents replay attacks).
2. **Verify Public Keys**: Use `curl` to verify that the Pressbooks `keyset` endpoint is reachable from the Moodle server.
3. **Check Client ID**: Ensure the Client ID in Pressbooks matches exactly what was generated by Moodle.

### Getting Debug Information

1. **Check Moodle logs:**
   - Site administration → Reports → Logs
   - Filter by: External tool activities

2. **Check Pressbooks logs:**
   - Contact Pressbooks administrator
   - Request LTI launch logs

3. **Browser Console:**
   - Press F12 in browser
   - Check Console tab for errors
   - Check Network tab for failed requests

---

## Best Practices

### For Administrators

✅ **Do:**
- Test integration thoroughly before rollout
- Document configuration for future reference
- Keep both systems updated
- Monitor error logs regularly
- Provide training for instructors

❌ **Don't:**
- Share Client IDs publicly
- Disable HTTPS
- Skip testing phase
- Ignore security updates

### For Instructors

✅ **Do:**
- Test activities before students access them
- Provide clear instructions to students
- Use descriptive activity names
- Enable grading if needed
- Use Deep Linking for specific content

❌ **Don't:**
- Create duplicate activities (use copy feature)
- Leave activities hidden indefinitely
- Forget to check gradebook for grade passback

### For Students

Students typically just click the activity link. No special steps needed!

---

## FAQ

### Q: Do students need separate Pressbooks accounts?
**A:** No. Accounts are created automatically via LTI.

### Q: Can I link to specific chapters?
**A:** Yes, using Deep Linking or by specifying the URL in Tool URL field.

### Q: Are grades automatically synced?
**A:** Yes, if "Accept grades" is enabled and AGS is configured.

### Q: What happens if a student's name changes in Moodle?
**A:** The updated information is sent on next launch and Pressbooks updates the user.

### Q: Can I use this with Moodle mobile app?
**A:** Yes, if the activity opens in a browser. Some limitations may apply.

### Q: Is this GDPR/FERPA compliant?
**A:** Yes, when properly configured. Consult your privacy officer.

### Q: Can I restrict which Pressbooks books are accessible?
**A:** Yes, configure permissions in Pressbooks or use Deep Linking.

### Q: What if Pressbooks is unavailable?
**A:** Students will see an error. Have a backup plan for critical content.

---

## Support

### Need Help?

1. **Check troubleshooting section** above
2. **Review logs** for specific error messages
3. **Contact support**:
   - Moodle admin for Moodle issues
   - Pressbooks admin for Pressbooks issues
   - System admin for network/SSL issues

### Reporting Issues

When reporting issues, include:
- Moodle version
- Pressbooks version
- LTI plugin version
- Error messages (exact text)
- Steps to reproduce
- Browser console errors
- What you've tried already

---

## Additional Resources

- 📖 [LTI 1.3 Specification](https://www.imsglobal.org/spec/lti/v1p3/)
- 📚 [Moodle LTI Documentation](https://docs.moodle.org/en/External_tool)
- 🔧 [Pressbooks Documentation](https://pressbooks.org/docs/)
- 🎓 [Video Tutorials](https://example.com/tutorials)

---

**Questions?** Contact your system administrator or refer to the [technical documentation](./INSTALLATION.md).
