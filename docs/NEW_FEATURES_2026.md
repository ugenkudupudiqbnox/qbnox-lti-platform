# New Features Guide (2026)

**Recent enhancements to Pressbooks LTI Platform**

---

## 🆕 Automatic Logout (Bidirectional Single Sign-Out)

**What it does:** When a user logs out of Moodle, they are automatically logged out of Pressbooks within 60 seconds.

### How It Works

- JavaScript monitors Moodle session status every 30 seconds
- When Moodle session expires (user logs out or times out), Pressbooks detects it
- User is automatically logged out and redirected back to Moodle

### Setup Required

**Administrators must enable CORS** on Moodle to allow session monitoring:

```bash
cd /root/pressbooks-lti-platform
bash scripts/enable-moodle-cors.sh
```

This configures Nginx to allow Pressbooks to check Moodle session status.

### User Experience

**For Students/Instructors:**
- Click "← Return to LMS" in the admin bar to manually logout
- Or close Moodle browser tab - Pressbooks auto-logs out within 60 seconds

**Testing:**
1. Launch Pressbooks from Moodle
2. Log out of Moodle in another tab
3. Return to Pressbooks - should auto-logout within 60 seconds

**Troubleshooting:** See `docs/SESSION_MONITOR_TESTING.md`

---

## 👤 Real Usernames from Moodle

**What it does:** Pressbooks uses Moodle's actual username instead of generating new ones.

### Before vs After

**Before:**
- Moodle username: `instructor`
- Pressbooks username: `test.instructor` (generated from first + last name)

**After:**
- Moodle username: `instructor`
- Pressbooks username: `instructor` (same as Moodle)

### Benefits

- ✅ Consistent usernames across systems
- ✅ Easier for users to remember
- ✅ Better for reporting and user management

### How It Works

Automatically uses Moodle's username if available:
1. First priority: Moodle username (e.g., `instructor`)
2. Fallback: firstname.lastname (e.g., `john.smith`)
3. Last resort: LTI user ID

No configuration needed - works automatically!

---

## 📊 Chapter-Specific Grade Routing

**What it does:** Each chapter's grades post to the correct gradebook column in Moodle.

### Problem Solved

**Before:** All grades went to a single "whole book" gradebook item, even when chapters were separate activities.

**After:** Each chapter maintains its own gradebook column with correct grades.

### How It Works

- When student launches Chapter 1, system stores that chapter's lineitem URL
- When student launches Chapter 2, stores Chapter 2's lineitem separately
- H5P grades post to the correct chapter's gradebook item

### User Experience

**For Instructors:**
1. Use Deep Linking to add multiple chapters as separate activities
2. Each activity appears as separate column in gradebook
3. Grades automatically post to correct column

**For Students:**
- Complete H5P activities in any chapter
- Grades appear in correct gradebook column
- Progress tracked per chapter

No setup required - works automatically!

---

## 🔄 Retroactive Grade Sync

**What it does:** Sync existing H5P grades to Moodle for students who completed work before grading was enabled.

### Use Case

Students completed H5P activities before you enabled H5P Results grading. Their historical grades don't appear in Moodle gradebook.

### How to Use

**For Instructors:**

1. Open any chapter in Pressbooks editor
2. Scroll to **H5P Results Grading** meta box
3. Configure which H5P activities to grade
4. Click **🔄 Sync Existing Grades to LMS**
5. Confirmation dialog appears → Click **OK**
6. Results show:
   - ✅ Successfully synced: X
   - ⏭️ Skipped (no LTI context): Y
   - ❌ Failed: Z

### Requirements

- Student must have previously accessed the chapter via LTI launch
- H5P Results grading must be configured for the chapter
- Student must have completed at least one H5P activity

### What Gets Synced

- All H5P activities configured for grading in that chapter
- Uses current grading configuration (not historical)
- Only syncs for students who launched via LTI (have LTI context)

**Documentation:** See `docs/RETROACTIVE_GRADE_SYNC.md` for details

---

## 📝 H5P Results Grading Configuration

**What it does:** Configure chapter-level grading from multiple H5P activities.

### Features

- **Select H5P activities** to include in grade
- **Assign weights** to each activity (percentage of total grade)
- **Average or sum** scores across activities
- **Set passing threshold** (optional)
- **Automatic grade sync** to Moodle gradebook

### How to Use

**For Instructors:**

1. Edit any chapter (post/page with H5P content)
2. Scroll to **H5P Results Grading** meta box on right sidebar
3. Click **Enable Grading for This Chapter**
4. Select H5P activities to include (checkboxes)
5. Set weight for each activity (e.g., Quiz 1: 40%, Quiz 2: 60%)
6. Choose aggregation method:
   - **Weighted Average**: Average of all scores
   - **Weighted Sum**: Total points earned
7. Save chapter

**Example Configuration:**
```
Chapter Grade = (Quiz 1 × 40%) + (Quiz 2 × 60%)
```

If student scores:
- Quiz 1: 80/100
- Quiz 2: 90/100

Grade = (80 × 0.4) + (90 × 0.6) = 32 + 54 = 86%

**Documentation:** See `docs/H5P_RESULTS_GRADING.md` for details

---

## 🔗 Deep Linking Chapter Selection

**What it does:** Select specific chapters when adding Pressbooks activities to Moodle.

### Features

- **Browse all books** in visual card layout
- **Expand chapters** to see structure (parts, chapters, front/back matter)
- **Select whole book** or specific chapters
- **Bulk actions**: Select All / Deselect All
- **Visual badges**: Front (blue), Chapter (green), Back (yellow)

### How to Use

**For Instructors:**

1. In Moodle course, click **Add an activity or resource**
2. Select **External tool** → Choose **Pressbooks**
3. Click **Select content**
4. Browse available books (cards show title and description)
5. Click on a book card to expand chapters
6. **Option A - Whole Book:**
   - Click **Select This Content** without selecting chapters
   - Modal appears with all chapters (all checked)
   - Uncheck chapters you don't want
   - Click **Add Selected Chapters**
7. **Option B - Specific Chapters:**
   - Click **Show Chapters** to expand
   - Click individual chapter to select
   - Click **Select This Content**

**Result:** Moodle creates one activity per selected chapter

### Use Cases

- **Full course:** Add entire book (20+ chapters) at once
- **Selected readings:** Add only relevant chapters (e.g., Ch 1-5, 10)
- **Skip content:** Exclude preface, appendices, optional readings

**Documentation:** See `docs/DEEP_LINKING_CONTENT_PICKER.md` for details

---

## 📧 Real User Information Sync

**What it does:** Pressbooks uses real email addresses, first names, and last names from Moodle.

### Before vs After

**Before:**
- Email: `abc123@lti.local` (placeholder)
- Display name: Not set
- First name: Empty
- Last name: Empty

**After:**
- Email: `john.smith@example.com` (real email from Moodle)
- Display name: John Smith
- First name: John
- Last name: Smith

### Benefits

- ✅ Instructors see real student names in Pressbooks
- ✅ Email notifications go to real addresses
- ✅ Better for reporting and analytics
- ✅ Consistent identity across systems

### Setup Required

**Moodle Configuration:**

Ensure name and email sharing is enabled (should be automatic):

```bash
cd /root/pressbooks-lti-platform
bash scripts/enable-email-sharing.sh
```

Verifies these settings are enabled:
- `sendname = 1` (send student/instructor names)
- `sendemailaddr = 1` (send email addresses)

### User Privacy

Students and instructors can control what information is shared via Moodle's privacy settings. The LTI integration respects these preferences.

---

## 🎯 Scale Grading Support

**What it does:** Supports Moodle's scale-based grading (not just numeric points).

### Supported Scales

1. **Default Competence Scale** (2 items):
   - Not yet competent (< 50%)
   - Competent (≥ 50%)

2. **Separate and Connected Ways of Knowing** (3 items):
   - Mostly separate knowing (< 40%)
   - Separate and connected (40-70%)
   - Mostly connected knowing (≥ 70%)

### How It Works

- System detects scale type from Moodle gradebook
- Automatically maps H5P percentage scores to scale values
- Posts correct scale value to Moodle

**Example:**
```
Student scores 85% on H5P quiz
→ Maps to "Competent" on Competence scale
→ Posts to Moodle gradebook as "Competent"
```

### Adding Custom Scales

Contact your administrator to add support for additional scales.

---

## 📚 Summary of All Features

| Feature | Status | Setup Required | Documentation |
|---------|--------|---------------|---------------|
| Bidirectional Logout | ✅ Active | Enable CORS | SESSION_MONITOR_TESTING.md |
| Moodle Usernames | ✅ Active | None | (This document) |
| Chapter Grade Routing | ✅ Active | None | (Automatic) |
| Retroactive Grade Sync | ✅ Active | None | RETROACTIVE_GRADE_SYNC.md |
| H5P Results Grading | ✅ Active | Configure per chapter | H5P_RESULTS_GRADING.md |
| Deep Linking Picker | ✅ Active | None | DEEP_LINKING_CONTENT_PICKER.md |
| Real User Info Sync | ✅ Active | Enable email sharing | (This document) |
| Scale Grading | ✅ Active | None | (Automatic) |

---

## 🆘 Need Help?

- **Installation Issues:** See `docs/INSTALLATION.md`
- **Testing Guide:** See `docs/TESTING_DEEP_LINKING_AND_AGS.md`
- **Developer Docs:** See `docs/DEVELOPER_ONBOARDING.md`
- **Troubleshooting:** See `docs/SESSION_MONITOR_TESTING.md`

**Report Issues:** https://github.com/ugenkudupudiqbnox/qbnox-lti-platform/issues

---

**Last Updated:** February 15, 2026
