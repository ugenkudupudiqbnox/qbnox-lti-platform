"""
Pytest configuration and fixtures for Selenium tests
"""
import os
import pytest
from datetime import datetime
from pathlib import Path
from dotenv import load_dotenv
from selenium import webdriver
from selenium.webdriver.chrome.options import Options as ChromeOptions
from selenium.webdriver.firefox.options import Options as FirefoxOptions
from selenium.webdriver.support.ui import WebDriverWait

# Load environment variables
load_dotenv()

# Test configuration
MOODLE_URL = os.getenv('MOODLE_URL', 'https://moodle.lti.qbnox.com')
PRESSBOOKS_URL = os.getenv('PRESSBOOKS_URL', 'https://pb.lti.qbnox.com')
SELENIUM_TIMEOUT = int(os.getenv('SELENIUM_TIMEOUT', '30'))
SELENIUM_HEADLESS = os.getenv('SELENIUM_HEADLESS', 'true').lower() == 'true'
SELENIUM_BROWSER = os.getenv('SELENIUM_BROWSER', 'chrome')
SCREENSHOT_DIR = os.getenv('SCREENSHOT_DIR', './screenshots')

# Create screenshot directory
Path(SCREENSHOT_DIR).mkdir(parents=True, exist_ok=True)


@pytest.fixture(scope="session")
def test_config():
    """Test configuration fixture"""
    return {
        'moodle_url': MOODLE_URL,
        'pressbooks_url': PRESSBOOKS_URL,
        'timeout': SELENIUM_TIMEOUT,
        'moodle_admin': {
            'username': os.getenv('MOODLE_ADMIN_USER'),
            'password': os.getenv('MOODLE_ADMIN_PASSWORD')
        },
        'moodle_student': {
            'username': os.getenv('MOODLE_STUDENT_USER'),
            'password': os.getenv('MOODLE_STUDENT_PASSWORD')
        },
        'moodle_instructor': {
            'username': os.getenv('MOODLE_INSTRUCTOR_USER'),
            'password': os.getenv('MOODLE_INSTRUCTOR_PASSWORD')
        },
        'pressbooks_admin': {
            'username': os.getenv('PRESSBOOKS_ADMIN_USER'),
            'password': os.getenv('PRESSBOOKS_ADMIN_PASSWORD')
        }
    }


@pytest.fixture(scope="function")
def driver(request):
    """WebDriver fixture - creates and tears down browser instance"""

    # Configure browser options
    if SELENIUM_BROWSER == 'chrome':
        options = ChromeOptions()
        if SELENIUM_HEADLESS:
            options.add_argument('--headless')
        options.add_argument('--no-sandbox')
        options.add_argument('--disable-dev-shm-usage')
        options.add_argument('--disable-gpu')
        options.add_argument('--window-size=1920,1080')
        options.add_argument('--ignore-certificate-errors')
        options.add_argument('--allow-insecure-localhost')

        driver_instance = webdriver.Chrome(options=options)

    elif SELENIUM_BROWSER == 'firefox':
        options = FirefoxOptions()
        if SELENIUM_HEADLESS:
            options.add_argument('--headless')
        options.add_argument('--width=1920')
        options.add_argument('--height=1080')

        driver_instance = webdriver.Firefox(options=options)

    else:
        raise ValueError(f"Unsupported browser: {SELENIUM_BROWSER}")

    # Set timeouts
    driver_instance.implicitly_wait(int(os.getenv('SELENIUM_IMPLICIT_WAIT', '10')))
    driver_instance.set_page_load_timeout(SELENIUM_TIMEOUT)

    # Yield driver to test
    yield driver_instance

    # Teardown: Take screenshot on failure
    if request.node.rep_call.failed and os.getenv('SCREENSHOT_ON_FAILURE', 'true').lower() == 'true':
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        screenshot_name = f"{request.node.name}_{timestamp}.png"
        screenshot_path = os.path.join(SCREENSHOT_DIR, screenshot_name)
        driver_instance.save_screenshot(screenshot_path)
        print(f"Screenshot saved: {screenshot_path}")

    # Quit browser
    driver_instance.quit()


@pytest.hookimpl(hookwrapper=True)
def pytest_runtest_makereport(item, call):
    """Hook to capture test result for screenshot on failure"""
    outcome = yield
    rep = outcome.get_result()
    setattr(item, f"rep_{rep.when}", rep)


@pytest.fixture(scope="function")
def wait(driver):
    """WebDriverWait fixture"""
    return WebDriverWait(driver, SELENIUM_TIMEOUT)


@pytest.fixture(scope="function")
def moodle_login(driver, test_config):
    """Login to Moodle as specified user"""
    def _login(username, password):
        driver.get(f"{test_config['moodle_url']}/login/index.php")

        from selenium.webdriver.common.by import By
        from selenium.webdriver.support import expected_conditions as EC
        from selenium.webdriver.support.ui import WebDriverWait

        wait = WebDriverWait(driver, SELENIUM_TIMEOUT)

        # Wait for login form
        username_field = wait.until(
            EC.presence_of_element_located((By.ID, "username"))
        )

        # Enter credentials
        username_field.send_keys(username)
        driver.find_element(By.ID, "password").send_keys(password)
        driver.find_element(By.ID, "loginbtn").click()

        # Wait for dashboard or redirect
        wait.until(EC.url_contains(test_config['moodle_url']))

        return driver

    return _login


@pytest.fixture(scope="function")
def pressbooks_login(driver, test_config):
    """Login to Pressbooks as admin"""
    def _login(username, password):
        driver.get(f"{test_config['pressbooks_url']}/wp-login.php")

        from selenium.webdriver.common.by import By
        from selenium.webdriver.support import expected_conditions as EC
        from selenium.webdriver.support.ui import WebDriverWait

        wait = WebDriverWait(driver, SELENIUM_TIMEOUT)

        # Wait for login form
        username_field = wait.until(
            EC.presence_of_element_located((By.ID, "user_login"))
        )

        # Enter credentials
        username_field.send_keys(username)
        driver.find_element(By.ID, "user_pass").send_keys(password)
        driver.find_element(By.ID, "wp-submit").click()

        # Wait for dashboard
        wait.until(EC.url_contains("wp-admin"))

        return driver

    return _login
