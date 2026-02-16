"""
Base Page Object - Common functionality for all pages
"""
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException


class BasePage:
    """Base page object with common methods"""

    def __init__(self, driver, timeout=30):
        self.driver = driver
        self.timeout = timeout
        self.wait = WebDriverWait(driver, timeout)

    def find_element(self, locator):
        """Find element with wait"""
        return self.wait.until(EC.presence_of_element_located(locator))

    def find_elements(self, locator):
        """Find multiple elements"""
        return self.wait.until(EC.presence_of_all_elements_located(locator))

    def click(self, locator):
        """Click element with wait"""
        element = self.wait.until(EC.element_to_be_clickable(locator))
        element.click()

    def input_text(self, locator, text):
        """Input text into field"""
        element = self.find_element(locator)
        element.clear()
        element.send_keys(text)

    def get_text(self, locator):
        """Get element text"""
        element = self.find_element(locator)
        return element.text

    def is_element_visible(self, locator, timeout=None):
        """Check if element is visible"""
        try:
            wait_time = timeout if timeout else self.timeout
            WebDriverWait(self.driver, wait_time).until(
                EC.visibility_of_element_located(locator)
            )
            return True
        except TimeoutException:
            return False

    def wait_for_url_contains(self, url_fragment):
        """Wait for URL to contain fragment"""
        self.wait.until(EC.url_contains(url_fragment))

    def get_current_url(self):
        """Get current URL"""
        return self.driver.current_url

    def navigate_to(self, url):
        """Navigate to URL"""
        self.driver.get(url)

    def refresh(self):
        """Refresh page"""
        self.driver.refresh()

    def take_screenshot(self, filename):
        """Take screenshot"""
        self.driver.save_screenshot(filename)

    def execute_script(self, script):
        """Execute JavaScript"""
        return self.driver.execute_script(script)

    def get_console_logs(self):
        """Get browser console logs"""
        return self.driver.get_log('browser')

    def get_severe_errors(self):
        """Get severe console errors"""
        logs = self.get_console_logs()
        return [log for log in logs if log['level'] == 'SEVERE']
