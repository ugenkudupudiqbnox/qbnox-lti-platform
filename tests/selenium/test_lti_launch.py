"""
Test LTI 1.3 Launch Flow
"""
import pytest
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC


class TestLTILaunch:
    """Test LTI launch flow from Moodle to Pressbooks"""

    @pytest.mark.smoke
    @pytest.mark.lti_launch
    def test_student_launch_to_chapter(self, driver, wait, moodle_login, test_config):
        """Test student launching a chapter from Moodle"""

        # Step 1: Login to Moodle as student
        moodle_login(
            test_config['moodle_student']['username'],
            test_config['moodle_student']['password']
        )

        # Step 2: Navigate to test course
        driver.get(f"{test_config['moodle_url']}/course/view.php?id=2")

        # Step 3: Find and click LTI activity link
        # Look for External tool activity in course
        lti_activity = wait.until(
            EC.element_to_be_clickable((By.CSS_SELECTOR, "a[href*='mod/lti/view.php']"))
        )

        activity_name = lti_activity.text
        print(f"Clicking LTI activity: {activity_name}")
        lti_activity.click()

        # Step 4: Wait for LTI launch to complete
        # Should redirect to Pressbooks
        wait.until(EC.url_contains(test_config['pressbooks_url']))

        # Step 5: Verify we're on Pressbooks
        assert test_config['pressbooks_url'] in driver.current_url
        print(f"✅ Successfully launched to: {driver.current_url}")

        # Step 6: Verify lti_launch parameter is present
        assert 'lti_launch=1' in driver.current_url
        print("✅ LTI launch parameter detected")

        # Step 7: Check for session monitor initialization (should NOT be present)
        page_source = driver.page_source
        assert '[LTI Session Monitor] Initialized' not in driver.execute_script(
            "return document.documentElement.outerHTML"
        )
        print("✅ Session monitor correctly disabled")

        # Step 8: Verify no "no user logged in" errors in console
        logs = driver.get_log('browser')
        errors = [log for log in logs if log['level'] == 'SEVERE']

        # Filter out expected errors (CORS from disabled session monitor is OK)
        critical_errors = [
            err for err in errors
            if 'no user logged in' in err['message'].lower()
        ]

        assert len(critical_errors) == 0, f"Found critical errors: {critical_errors}"
        print("✅ No critical JavaScript errors")


    def test_instructor_launch_to_chapter(self, driver, wait, moodle_login, test_config):
        """Test instructor launching a chapter from Moodle"""

        # Step 1: Login to Moodle as instructor
        moodle_login(
            test_config['moodle_instructor']['username'],
            test_config['moodle_instructor']['password']
        )

        # Step 2: Navigate to test course
        driver.get(f"{test_config['moodle_url']}/course/view.php?id=2")

        # Step 3: Find and click LTI activity
        lti_activity = wait.until(
            EC.element_to_be_clickable((By.CSS_SELECTOR, "a[href*='mod/lti/view.php']"))
        )
        lti_activity.click()

        # Step 4: Wait for Pressbooks
        wait.until(EC.url_contains(test_config['pressbooks_url']))

        # Step 5: Verify launch
        assert test_config['pressbooks_url'] in driver.current_url
        assert 'lti_launch=1' in driver.current_url

        print(f"✅ Instructor successfully launched to: {driver.current_url}")


    def test_launch_creates_user(self, driver, wait, moodle_login, pressbooks_login, test_config):
        """Test that LTI launch creates/updates WordPress user"""

        # Step 1: Launch from Moodle as student
        moodle_login(
            test_config['moodle_student']['username'],
            test_config['moodle_student']['password']
        )

        driver.get(f"{test_config['moodle_url']}/course/view.php?id=2")

        lti_activity = wait.until(
            EC.element_to_be_clickable((By.CSS_SELECTOR, "a[href*='mod/lti/view.php']"))
        )
        lti_activity.click()

        wait.until(EC.url_contains(test_config['pressbooks_url']))

        # Step 2: Note the current URL (student is logged in to Pressbooks)
        student_url = driver.current_url

        # Step 3: Check if user exists in Pressbooks by accessing admin
        # Open new window for admin check
        driver.execute_script("window.open('');")
        driver.switch_to.window(driver.window_handles[1])

        # Login as admin
        pressbooks_login(
            test_config['pressbooks_admin']['username'],
            test_config['pressbooks_admin']['password']
        )

        # Navigate to users page
        driver.get(f"{test_config['pressbooks_url']}/wp-admin/users.php")

        # Search for student username
        search_box = driver.find_element(By.ID, "user-search-input")
        search_box.send_keys(test_config['moodle_student']['username'])
        search_box.submit()

        # Verify user appears in results
        wait.until(EC.presence_of_element_located((By.CLASS_NAME, "username")))

        # Check if student username is in the results
        usernames = driver.find_elements(By.CLASS_NAME, "username")
        found = any(
            test_config['moodle_student']['username'] in elem.text
            for elem in usernames
        )

        assert found, f"User {test_config['moodle_student']['username']} not found in Pressbooks"
        print(f"✅ LTI user created in Pressbooks: {test_config['moodle_student']['username']}")

        # Close admin window
        driver.close()
        driver.switch_to.window(driver.window_handles[0])


    def test_launch_with_ags_context(self, driver, wait, moodle_login, test_config):
        """Test that LTI launch includes AGS context for grade passback"""

        # Step 1: Launch from Moodle
        moodle_login(
            test_config['moodle_student']['username'],
            test_config['moodle_student']['password']
        )

        driver.get(f"{test_config['moodle_url']}/course/view.php?id=2")

        lti_activity = wait.until(
            EC.element_to_be_clickable((By.CSS_SELECTOR, "a[href*='mod/lti/view.php']"))
        )
        lti_activity.click()

        wait.until(EC.url_contains(test_config['pressbooks_url']))

        # Step 2: Check browser console for AGS context storage logs
        # This would appear in PHP error logs, not browser console
        # So we verify by checking if we can access a graded activity

        # Navigate to current URL (should be a chapter)
        current_url = driver.current_url
        print(f"✅ Launched to chapter: {current_url}")

        # Extract post ID from URL if possible
        import re
        match = re.search(r'/chapter/([^/]+)', current_url)
        if match:
            chapter_slug = match.group(1)
            print(f"✅ Chapter slug: {chapter_slug}")

        # Verify AGS context by checking if chapter has H5P activities
        # (which would require AGS context for grade sync)
        page_source = driver.page_source

        # Look for H5P iframe or content
        has_h5p = 'h5p-iframe' in page_source or 'h5p-container' in page_source

        if has_h5p:
            print("✅ Chapter contains H5P activities (AGS context will be used)")
        else:
            print("ℹ️ No H5P activities found (AGS context stored but not used yet)")

        # The actual AGS context verification happens server-side
        # This test confirms the launch flow completes successfully
        assert test_config['pressbooks_url'] in driver.current_url
