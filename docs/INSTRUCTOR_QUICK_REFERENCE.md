# Instructor Quick Reference

**Pressbooks LTI Platform - Common Tasks**

---

## 🚀 Adding Pressbooks Content to Moodle

### Method 1: Deep Linking (Recommended)

1. **Add activity:** Click "Add an activity or resource"
2. **Select tool:** Choose "External tool" → "Pressbooks"
3. **Select content:** Click "Select content" button
4. **Choose chapters:**
   - Click book card to expand chapters
   - Select specific chapters or entire book
   - Click "Add Selected Chapters"
5. **Save:** Moodle creates one activity per chapter

### Method 2: Manual Link

1. **Add activity:** Choose "External tool"
2. **Preconfigured tool:** Select "Pressbooks"
3. **Activity name:** Enter chapter title
4. **Save and display**

---

## 📊 Setting Up Chapter Grading

### Enable H5P Results Grading

1. **Open chapter** in Pressbooks editor
2. **Find meta box:** "H5P Results Grading" (right sidebar)
3. **Enable:** Click "Enable Grading for This Chapter"
4. **Select activities:** Check H5P quizzes/exercises to include
5. **Set weights:** Assign percentage to each activity
   - Example: Quiz 1 (40%), Quiz 2 (60%)
6. **Choose method:**
   - Weighted Average: Average of scores
   - Weighted Sum: Total points
7. **Save chapter**

**Result:** Grades automatically sync to Moodle gradebook

---

## 🔄 Syncing Historical Grades

If students completed H5P activities before you enabled grading:

1. **Open chapter** in Pressbooks editor
2. **Scroll to:** "H5P Results Grading" meta box
3. **Click:** "🔄 Sync Existing Grades to LMS"
4. **Confirm:** Click OK in dialog
5. **Review results:**
   - Successfully synced: X students
   - Skipped: Y students (no LTI context)
   - Failed: Z students

**Note:** Only syncs for students who accessed via LTI launch

---

## 📈 Viewing Grades in Moodle

### Where to Check

1. **Gradebook:** Course → Grades → Grader report
2. **Activity:** Click activity → View submissions

### What You'll See

- Each chapter = separate gradebook column
- Grades update automatically when students complete H5P
- Scale-based activities show scale labels (e.g., "Competent")

---

## 👥 Student Access & Usernames

### How Students Access

1. Student clicks Pressbooks activity in Moodle
2. Automatically logged into Pressbooks (single sign-on)
3. Sees assigned content
4. Completes H5P activities
5. Grades sync automatically to Moodle

### Usernames

- **Format:** Uses Moodle username (e.g., "student01")
- **Display name:** Shows real name (e.g., "John Smith")
- **Email:** Real email from Moodle profile

---

## 🚪 Logout

When done, close the Pressbooks tab or log out of Moodle. 

---

## 🐛 Common Issues & Solutions

### Grades Not Showing

**Issue:** Student completed H5P but grade doesn't appear in Moodle

**Solutions:**
1. ✅ Check if student accessed via LTI launch (not direct URL)
2. ✅ Verify H5P Results grading is enabled for chapter
3. ✅ Check if activity is graded in Moodle settings
4. ✅ Use "Sync Existing Grades" if retroactive

### Wrong Gradebook Column

**Issue:** Grades going to wrong activity

**Solution:**
- Have student launch chapter again from Moodle
- System will update lineitem association
- Future grades will go to correct column

### Student Can't Access

**Issue:** Student gets error when launching

**Solutions:**
1. ✅ Verify activity is published in Moodle
2. ✅ Check student is enrolled in course
3. ✅ Confirm Pressbooks is accessible via HTTPS
4. ✅ Check browser console for errors (F12)

### Multiple Users with Same Name

**Issue:** Multiple "John Smith" accounts created

**Solution:**
- This shouldn't happen - system uses LTI user ID
- If it does, contact administrator
- Old accounts can be merged/deleted

---

## 📱 Best Practices

### Adding Content

✅ **DO:**
- Use Deep Linking to select specific chapters
- Add chapters as separate activities for granular grading
- Set clear activity names (e.g., "Chapter 1: Introduction")

❌ **DON'T:**
- Link to entire book if you only need some chapters
- Create multiple activities pointing to same chapter
- Forget to enable grading if you want grade tracking

### Grading Configuration

✅ **DO:**
- Configure grading before students start work
- Set weights that total 100% (or use Weighted Average)
- Use "Sync Existing Grades" for historical data
- Test with a test student account first

❌ **DON'T:**
- Change grading scheme after students complete work
- Use too many H5P activities (5-10 max per chapter)
- Forget to save chapter after configuring grading

### Grade Management

✅ **DO:**
- Check gradebook regularly to verify sync
- Use Moodle's grade history for audit trail
- Communicate grading policy to students
- Export grades before semester ends

❌ **DON'T:**
- Manually override synced grades (will be overwritten)
- Delete Moodle activities with existing student data
- Ignore sync errors (check logs if grades missing)

---

## 📞 Getting Help

### Documentation

- **Setup & Configuration:** `docs/SETUP_GUIDE.md`
- **Grading Details:** `docs/H5P_RESULTS_GRADING.md`
- **Deep Linking:** `docs/DEEP_LINKING_CONTENT_PICKER.md`
- **Retroactive Sync:** `docs/RETROACTIVE_GRADE_SYNC.md`

### Support

- **IT Help Desk:** Contact your institution's support
- **Technical Issues:** Check `docs/SETUP_GUIDE.md`
- **Bug Reports:** GitHub Issues (if applicable)

---

## 🎯 Quick Troubleshooting Checklist

Before contacting support, verify:

- [ ] Activity is published and visible in Moodle
- [ ] Student is enrolled in course
- [ ] Student accessed via LTI launch (clicked activity in Moodle)
- [ ] H5P Results grading is enabled for chapter
- [ ] Moodle activity is set to accept grades
- [ ] HTTPS is working on both systems
- [ ] No browser errors in console (F12)

---

**Last Updated:** February 15, 2026
**Version:** 1.0
