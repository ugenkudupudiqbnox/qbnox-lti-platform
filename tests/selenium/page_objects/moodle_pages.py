"""
Moodle Page Objects
"""
from selenium.webdriver.common.by import By
from .base_page import BasePage


class MoodleLoginPage(BasePage):
    """Moodle login page"""

    # Locators
    USERNAME_FIELD = (By.ID, "username")
    PASSWORD_FIELD = (By.ID, "password")
    LOGIN_BUTTON = (By.ID, "loginbtn")

    def login(self, username, password):
        """Login to Moodle"""
        self.input_text(self.USERNAME_FIELD, username)
        self.input_text(self.PASSWORD_FIELD, password)
        self.click(self.LOGIN_BUTTON)


class MoodleCoursePage(BasePage):
    """Moodle course page"""

    # Locators
    LTI_ACTIVITY_LINK = (By.CSS_SELECTOR, "a[href*='mod/lti/view.php']")
    EDIT_MODE_TOGGLE = (By.CSS_SELECTOR, "input[name='setmode']")
    ADD_ACTIVITY_BUTTON = (By.CSS_SELECTOR, "a[data-action='open-chooser']")

    def click_lti_activity(self):
        """Click on LTI activity to launch"""
        self.click(self.LTI_ACTIVITY_LINK)

    def get_lti_activity_name(self):
        """Get LTI activity name"""
        return self.get_text(self.LTI_ACTIVITY_LINK)

    def enable_editing(self):
        """Turn editing mode on"""
        edit_btn = self.find_element(self.EDIT_MODE_TOGGLE)
        if edit_btn.get_attribute('value') == '1':  # Edit mode off
            self.click(self.EDIT_MODE_TOGGLE)

    def add_activity(self):
        """Click add activity button"""
        self.click(self.ADD_ACTIVITY_BUTTON)


class MoodleGradebookPage(BasePage):
    """Moodle gradebook page"""

    # Locators
    GRADEBOOK_TABLE = (By.CLASS_NAME, "generaltable")
    GRADE_ITEMS = (By.CSS_SELECTOR, ".gradeitemheader")
    GRADE_CELLS = (By.CSS_SELECTOR, ".grade")

    def get_grade_for_activity(self, activity_name):
        """Get grade for specific activity"""
        items = self.find_elements(self.GRADE_ITEMS)

        for item in items:
            if activity_name in item.text:
                # Found the activity, now get the grade
                grade_cells = self.find_elements(self.GRADE_CELLS)
                for cell in grade_cells:
                    grade_text = cell.text.strip()
                    if grade_text and grade_text != '-':
                        return grade_text

        return None

    def has_grade_for_activity(self, activity_name):
        """Check if activity has a grade"""
        grade = self.get_grade_for_activity(activity_name)
        return grade is not None
