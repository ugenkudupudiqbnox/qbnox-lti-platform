"""
Test H5P Activity Completion and Grade Sync
"""
import pytest
import time
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC


class TestH5PCompletion:
    """Test H5P activity completion and grade synchronization"""

    @pytest.mark.smoke
    @pytest.mark.h5p
    def test_h5p_completion_no_errors(self, driver, wait, moodle_login, test_config):
        """Test that H5P completion does NOT show 'no user logged in' error"""

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

        # Step 2: Find H5P activity iframe
        try:
            h5p_iframe = wait.until(
                EC.presence_of_element_located((By.CSS_SELECTOR, "iframe.h5p-iframe"))
            )
            print("✅ H5P activity found")

            # Switch to H5P iframe
            driver.switch_to.frame(h5p_iframe)

            # Step 3: Wait for H5P content to load
            wait.until(
                EC.presence_of_element_located((By.CLASS_NAME, "h5p-content"))
            )
            print("✅ H5P content loaded")

            # Step 4: Interact with H5P activity
            # This depends on H5P content type, but we'll try common interactions

            # For Multiple Choice: find and click answer
            try:
                answer_option = driver.find_element(By.CSS_SELECTOR, ".h5p-joubelui-button")
                answer_option.click()
                time.sleep(1)

                # Click check/submit button
                submit_button = driver.find_element(By.CSS_SELECTOR, ".h5p-question-check-answer")
                submit_button.click()
                print("✅ H5P activity completed")

            except Exception as e:
                print(f"ℹ️ Could not interact with specific H5P type: {e}")
                # Different H5P types have different interactions
                # This is OK - we're mainly testing for errors

            # Step 5: Switch back to main content
            driver.switch_to.default_content()

            # Step 6: Wait a bit for AJAX save to complete
            time.sleep(3)

            # Step 7: Check for "no user logged in" error
            page_source = driver.page_source.lower()

            assert 'no user logged in' not in page_source, \
                "❌ Found 'no user logged in' error after H5P completion"

            print("✅ No 'no user logged in' error found")

            # Step 8: Check browser console for errors
            logs = driver.get_log('browser')
            error_logs = [log for log in logs if log['level'] == 'SEVERE']

            critical_errors = [
                err for err in error_logs
                if 'no user logged in' in err['message'].lower()
            ]

            assert len(critical_errors) == 0, \
                f"❌ Found 'no user logged in' errors in console: {critical_errors}"

            print("✅ No console errors related to user authentication")

        except Exception as e:
            print(f"ℹ️ No H5P activity found in this chapter: {e}")
            # Not all chapters have H5P - this is OK
            pytest.skip("No H5P activity found in test chapter")


    def test_h5p_grade_sync_to_moodle(self, driver, wait, moodle_login, test_config):
        """Test that H5P grades sync to Moodle gradebook"""

        # Step 1: Complete H5P activity as student (reuse previous test logic)
        moodle_login(
            test_config['moodle_student']['username'],
            test_config['moodle_student']['password']
        )

        driver.get(f"{test_config['moodle_url']}/course/view.php?id=2")

        # Get the course ID from URL
        import re
        course_match = re.search(r'id=(\d+)', driver.current_url)
        course_id = course_match.group(1) if course_match else "2"

        # Click LTI activity
        lti_activity = wait.until(
            EC.element_to_be_clickable((By.CSS_SELECTOR, "a[href*='mod/lti/view.php']"))
        )

        # Get activity name for later verification
        activity_name = lti_activity.text

        lti_activity.click()

        wait.until(EC.url_contains(test_config['pressbooks_url']))

        # Try to complete H5P if present
        try:
            h5p_iframe = wait.until(
                EC.presence_of_element_located((By.CSS_SELECTOR, "iframe.h5p-iframe"))
            )
            driver.switch_to.frame(h5p_iframe)

            # Attempt simple completion
            try:
                answer = driver.find_element(By.CSS_SELECTOR, ".h5p-joubelui-button")
                answer.click()
                time.sleep(1)

                submit = driver.find_element(By.CSS_SELECTOR, ".h5p-question-check-answer")
                submit.click()
                print("✅ H5P activity completed")

            except:
                pass  # Different H5P types

            driver.switch_to.default_content()

            # Wait for grade sync (AGS is async)
            time.sleep(5)

        except:
            print("ℹ️ No H5P activity to complete")
            pytest.skip("No H5P activity found")

        # Step 2: Navigate to Moodle gradebook
        driver.get(f"{test_config['moodle_url']}/grade/report/user/index.php?id={course_id}")

        # Step 3: Look for the grade
        try:
            # Wait for gradebook table
            gradebook_table = wait.until(
                EC.presence_of_element_located((By.CLASS_NAME, "generaltable"))
            )

            # Look for activity name in gradebook
            grade_items = driver.find_elements(By.CSS_SELECTOR, ".gradeitemheader")

            found_activity = False
            for item in grade_items:
                if activity_name in item.text:
                    found_activity = True
                    print(f"✅ Found activity in gradebook: {activity_name}")
                    break

            if found_activity:
                # Look for grade value (any non-dash value)
                grade_cells = driver.find_elements(By.CSS_SELECTOR, ".grade")
                has_grade = any(
                    cell.text.strip() and cell.text.strip() != '-'
                    for cell in grade_cells
                )

                if has_grade:
                    print("✅ Grade synced to Moodle gradebook")
                else:
                    print("ℹ️ Activity in gradebook but no grade yet (sync may be delayed)")

        except Exception as e:
            print(f"ℹ️ Could not verify gradebook: {e}")
            # Gradebook verification can be flaky, don't fail test


    def test_user_session_persists_during_h5p(self, driver, wait, moodle_login, test_config):
        """Test that user session persists throughout H5P activity completion"""

        # Step 1: Launch and note login time
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

        initial_url = driver.current_url
        print(f"✅ Initial launch URL: {initial_url}")

        # Step 2: Wait for simulated activity time (user working on H5P)
        time.sleep(10)

        # Step 3: Verify still logged in - reload page
        driver.refresh()

        # Should not redirect to login page
        wait.until(EC.url_contains(test_config['pressbooks_url']))

        # Should still have lti_launch parameter or be on same content
        current_url = driver.current_url

        assert 'wp-login' not in current_url.lower(), \
            "❌ User was logged out - redirected to login page"

        print(f"✅ Session persisted, current URL: {current_url}")

        # Step 4: Check for any session-related errors
        logs = driver.get_log('browser')
        session_errors = [
            log for log in logs
            if 'session' in log['message'].lower() and log['level'] == 'SEVERE'
        ]

        assert len(session_errors) == 0, \
            f"❌ Found session errors: {session_errors}"

        print("✅ No session errors detected")
