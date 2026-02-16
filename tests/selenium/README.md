# Selenium UI Testing for Qbnox LTI Platform

Comprehensive Selenium-based UI testing framework for automated testing of LTI 1.3 flows, H5P completion, Deep Linking, and grade synchronization.

---

## 📋 Table of Contents

- [Features](#features)
- [Architecture](#architecture)
- [Setup](#setup)
- [Running Tests](#running-tests)
- [Test Structure](#test-structure)
- [CI/CD Integration](#cicd-integration)
- [Troubleshooting](#troubleshooting)

---

## ✨ Features

### Test Coverage

✅ **LTI 1.3 Launch Flow**
- Student launch to chapter
- Instructor launch to chapter
- User creation/update in Pressbooks
- AGS context storage

✅ **H5P Activity Completion**
- Activity interaction and completion
- No "no user logged in" errors
- Grade sync to Moodle gradebook
- Session persistence during activities

✅ **Deep Linking 2.0**
- Content picker UI validation
- Chapter selection
- Whole book modal confirmation
- JWT response generation

✅ **Grade Synchronization**
- AGS grade posting
- Gradebook verification
- Scale grading support
- Retroactive sync

### Framework Features

🔧 **Robust Framework**
- Page Object Model pattern
- Cross-browser support (Chrome, Firefox)
- Headless and visible modes
- Screenshot on failure
- Detailed HTML reports
- Parallel test execution
- Docker support

---

## 🏗️ Architecture

### Directory Structure

```
tests/selenium/
├── page_objects/           # Page Object Model classes
│   ├── base_page.py       # Base page with common methods
│   ├── moodle_pages.py    # Moodle page objects
│   └── pressbooks_pages.py# Pressbooks page objects
│
├── test_lti_launch.py     # LTI launch flow tests
├── test_h5p_completion.py # H5P activity tests
├── test_deep_linking.py   # Deep Linking tests
│
├── conftest.py            # Pytest fixtures and configuration
├── pytest.ini             # Pytest settings
├── requirements.txt       # Python dependencies
├── .env.example           # Environment variables template
│
├── run_tests.sh           # Test runner script
├── docker-compose.yml     # Selenium Grid setup
├── Dockerfile.tests       # Test container image
│
├── screenshots/           # Failure screenshots (generated)
└── reports/               # Test reports (generated)
```

### Page Object Model

Tests use the Page Object pattern to separate test logic from page interactions:

```python
# page_objects/moodle_pages.py
class MoodleCoursePage(BasePage):
    def click_lti_activity(self):
        self.click(self.LTI_ACTIVITY_LINK)

# test_lti_launch.py
def test_student_launch(driver):
    course_page = MoodleCoursePage(driver)
    course_page.click_lti_activity()
```

---

## 🚀 Setup

### Prerequisites

- Python 3.11+
- Docker (optional, for Selenium Grid)
- Chrome or Firefox browser
- Access to Moodle and Pressbooks test environments

### Installation

1. **Clone Repository**
   ```bash
   cd /root/qbnox-lti-platform/tests/selenium
   ```

2. **Create Virtual Environment**
   ```bash
   python3 -m venv venv
   source venv/bin/activate
   ```

3. **Install Dependencies**
   ```bash
   pip install -r requirements.txt
   ```

4. **Configure Environment**
   ```bash
   cp .env.example .env
   nano .env  # Edit with your credentials
   ```

### Environment Variables

Edit `.env` with your test environment details:

```bash
# Base URLs
MOODLE_URL=https://moodle.lti.qbnox.com
PRESSBOOKS_URL=https://pb.lti.qbnox.com

# Moodle Credentials
MOODLE_ADMIN_USER=admin
MOODLE_ADMIN_PASSWORD=your_admin_password
MOODLE_STUDENT_USER=student
MOODLE_STUDENT_PASSWORD=your_student_password
MOODLE_INSTRUCTOR_USER=instructor
MOODLE_INSTRUCTOR_PASSWORD=your_instructor_password

# Pressbooks Credentials
PRESSBOOKS_ADMIN_USER=admin
PRESSBOOKS_ADMIN_PASSWORD=your_admin_password

# Test Configuration
SELENIUM_HEADLESS=true
SELENIUM_BROWSER=chrome
SCREENSHOT_ON_FAILURE=true
```

---

## 🧪 Running Tests

### Using Test Runner Script (Recommended)

```bash
# Run all tests
./run_tests.sh

# Run specific test file
./run_tests.sh -t test_lti_launch.py

# Run in visible mode (watch browser)
./run_tests.sh -v

# Run smoke tests only
./run_tests.sh -s

# Run tests in parallel (4 workers)
./run_tests.sh -p 4

# Run tests with specific marker
./run_tests.sh -m h5p
```

### Using Pytest Directly

```bash
# Run all tests
pytest -v

# Run specific test file
pytest test_lti_launch.py -v

# Run specific test
pytest test_lti_launch.py::TestLTILaunch::test_student_launch_to_chapter -v

# Run tests with marker
pytest -m smoke -v

# Run in parallel
pytest -n auto

# Generate HTML report
pytest --html=reports/report.html --self-contained-html
```

### Using Docker (Selenium Grid)

```bash
# Start Selenium Grid
docker-compose up -d

# Run tests in container
docker-compose run test-runner

# View Selenium Grid dashboard
open http://localhost:4444

# Stop grid
docker-compose down
```

---

## 📊 Test Reports

### HTML Report

After test execution, open the HTML report:

```bash
open reports/report.html
```

**Report includes:**
- Test summary (passed/failed/skipped)
- Detailed test results
- Execution time
- Failure screenshots (embedded)
- Console logs

### Screenshots

Failure screenshots are automatically saved to `screenshots/` directory:

```
screenshots/
├── test_student_launch_20260216_143022.png
├── test_h5p_completion_20260216_143045.png
└── ...
```

---

## 🎯 Test Markers

Tests are organized with pytest markers:

```python
@pytest.mark.smoke
def test_critical_flow():
    """Quick smoke test"""
    pass

@pytest.mark.lti_launch
def test_launch():
    """LTI launch test"""
    pass
```

**Available Markers:**
- `smoke` - Critical smoke tests (fast)
- `lti_launch` - LTI launch flow tests
- `h5p` - H5P activity tests
- `deep_linking` - Deep Linking tests
- `grade_sync` - Grade synchronization tests
- `slow` - Slow-running tests
- `integration` - Full integration tests

**Run tests by marker:**
```bash
pytest -m smoke         # Quick smoke tests
pytest -m "lti_launch and not slow"  # LTI tests, exclude slow
pytest -m "h5p or deep_linking"      # H5P or Deep Linking tests
```

---

## 🔧 CI/CD Integration

### GitHub Actions

Tests run automatically on:
- **Push** to `main` or `develop` branches
- **Pull Requests**
- **Schedule** (daily at 2 AM UTC)
- **Manual trigger** via workflow_dispatch

**Workflow file:** `.github/workflows/selenium-tests.yml`

**Required Secrets:**
Configure in GitHub repository settings:

```
MOODLE_URL
PRESSBOOKS_URL
MOODLE_ADMIN_USER
MOODLE_ADMIN_PASSWORD
MOODLE_STUDENT_USER
MOODLE_STUDENT_PASSWORD
MOODLE_INSTRUCTOR_USER
MOODLE_INSTRUCTOR_PASSWORD
PRESSBOOKS_ADMIN_USER
PRESSBOOKS_ADMIN_PASSWORD
```

### Manual Trigger

```bash
# Via GitHub UI:
Actions → Selenium UI Tests → Run workflow

# Via GitHub CLI:
gh workflow run selenium-tests.yml
```

---

## 🐛 Troubleshooting

### Common Issues

#### 1. **"Connection refused" errors**

**Problem:** Can't connect to Moodle/Pressbooks

**Solution:**
- Verify URLs in `.env` are correct
- Check if services are running
- Verify SSL certificates are valid
- Test URLs manually in browser

```bash
curl -I https://moodle.lti.qbnox.com
curl -I https://pb.lti.qbnox.com
```

#### 2. **"Element not found" errors**

**Problem:** Selenium can't find page elements

**Solution:**
- Increase timeout in `.env`: `SELENIUM_TIMEOUT=60`
- Check if page structure changed
- Run in visible mode to debug: `./run_tests.sh -v`
- Check screenshots in `screenshots/` directory

#### 3. **"Session not created" errors**

**Problem:** WebDriver can't start browser

**Solution:**
- Update chromedriver: `pip install --upgrade selenium`
- Or use webdriver-manager: `pip install webdriver-manager`
- Check Chrome/Firefox version compatibility

#### 4. **Headless mode issues**

**Problem:** Tests pass in visible mode but fail in headless

**Solution:**
- Some JavaScript doesn't run in headless
- Increase waits/timeouts
- Check for `window.innerWidth` issues
- Run in visible mode for debugging

#### 5. **Parallel execution failures**

**Problem:** Tests fail when running in parallel

**Solution:**
- Reduce parallel workers: `pytest -n 2` instead of `-n auto`
- Check for shared state/resources
- Ensure tests are independent
- Use unique test data per test

### Debug Mode

**Run single test in visible mode with verbose output:**

```bash
pytest test_lti_launch.py::TestLTILaunch::test_student_launch_to_chapter \
    -v -s \
    --capture=no \
    --log-cli-level=DEBUG \
    --env SELENIUM_HEADLESS=false
```

### Viewing Logs

```bash
# Test execution logs
cat reports/test.log

# Pytest output
pytest -v -s  # -s shows print statements

# Browser console logs (in test)
logs = driver.get_log('browser')
print(logs)
```

---

## 📚 Writing New Tests

### Example Test

```python
import pytest
from selenium.webdriver.common.by import By
from page_objects.moodle_pages import MoodleCoursePage

@pytest.mark.smoke
@pytest.mark.lti_launch
def test_my_new_feature(driver, wait, moodle_login, test_config):
    """Test description"""

    # Step 1: Login
    moodle_login(
        test_config['moodle_student']['username'],
        test_config['moodle_student']['password']
    )

    # Step 2: Navigate
    course_page = MoodleCoursePage(driver)
    course_page.navigate_to(f"{test_config['moodle_url']}/course/view.php?id=2")

    # Step 3: Interact
    course_page.click_lti_activity()

    # Step 4: Assert
    wait.until(lambda d: test_config['pressbooks_url'] in d.current_url)
    assert 'expected_text' in driver.page_source

    print("✅ Test passed")
```

### Best Practices

1. **Use Page Objects** - Don't use raw Selenium calls in tests
2. **Add Markers** - Tag tests with appropriate markers
3. **Use Fixtures** - Leverage pytest fixtures for setup
4. **Wait Explicitly** - Use WebDriverWait, not sleep()
5. **Assert Clearly** - Use descriptive assertion messages
6. **Log Progress** - Print checkpoints for debugging
7. **Handle Failures** - Use try/except for optional elements

---

## 📞 Support

**Issues:** https://github.com/ugenkudupudiqbnox/qbnox-lti-platform/issues

**Documentation:** https://github.com/ugenkudupudiqbnox/qbnox-lti-platform

---

**Last Updated:** 2026-02-16
**Framework Version:** 1.0.0
**Selenium Version:** 4.18.1
