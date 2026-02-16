"""
Pressbooks Page Objects
"""
from selenium.webdriver.common.by import By
from .base_page import BasePage


class PressbooksLoginPage(BasePage):
    """Pressbooks login page"""

    # Locators
    USERNAME_FIELD = (By.ID, "user_login")
    PASSWORD_FIELD = (By.ID, "user_pass")
    LOGIN_BUTTON = (By.ID, "wp-submit")

    def login(self, username, password):
        """Login to Pressbooks"""
        self.input_text(self.USERNAME_FIELD, username)
        self.input_text(self.PASSWORD_FIELD, password)
        self.click(self.LOGIN_BUTTON)


class PressbooksChapterPage(BasePage):
    """Pressbooks chapter page"""

    # Locators
    H5P_IFRAME = (By.CSS_SELECTOR, "iframe.h5p-iframe")
    H5P_CONTENT = (By.CLASS_NAME, "h5p-content")
    LTI_LAUNCH_PARAM = "lti_launch=1"

    def has_h5p_activity(self):
        """Check if page has H5P activity"""
        return self.is_element_visible(self.H5P_IFRAME, timeout=5)

    def is_lti_launch(self):
        """Check if this is an LTI launch"""
        return self.LTI_LAUNCH_PARAM in self.get_current_url()

    def switch_to_h5p_iframe(self):
        """Switch to H5P iframe"""
        iframe = self.find_element(self.H5P_IFRAME)
        self.driver.switch_to.frame(iframe)

    def switch_to_default_content(self):
        """Switch back to main content"""
        self.driver.switch_to.default_content()


class PressbooksContentPicker(BasePage):
    """Deep Linking content picker page"""

    # Locators
    BOOK_CARDS = (By.CLASS_NAME, "book-card")
    BOOK_TITLE = (By.CSS_SELECTOR, ".book-title")
    SELECT_CONTENT_BUTTON = (By.CSS_SELECTOR, ".select-content-btn")
    EXPAND_CHAPTERS_BUTTON = (By.CSS_SELECTOR, ".expand-chapters")
    CHAPTER_ITEMS = (By.CLASS_NAME, "chapter-item")
    CHAPTER_SELECTION_MODAL = (By.ID, "chapter-selection-modal")
    SELECT_ALL_BUTTON = (By.ID, "select-all-chapters")
    DESELECT_ALL_BUTTON = (By.ID, "deselect-all-chapters")
    CONFIRM_SELECTION_BUTTON = (By.ID, "confirm-chapter-selection")

    def get_book_count(self):
        """Get number of books"""
        books = self.find_elements(self.BOOK_CARDS)
        return len(books)

    def select_book(self, index=0):
        """Select a book by index"""
        books = self.find_elements(self.BOOK_CARDS)
        if index < len(books):
            select_btn = books[index].find_element(*self.SELECT_CONTENT_BUTTON)
            select_btn.click()

    def expand_book_chapters(self, index=0):
        """Expand chapters for a book"""
        books = self.find_elements(self.BOOK_CARDS)
        if index < len(books):
            expand_btn = books[index].find_element(*self.EXPAND_CHAPTERS_BUTTON)
            expand_btn.click()

    def is_modal_visible(self):
        """Check if chapter selection modal is visible"""
        return self.is_element_visible(self.CHAPTER_SELECTION_MODAL, timeout=5)

    def deselect_all_chapters(self):
        """Click deselect all button"""
        self.click(self.DESELECT_ALL_BUTTON)

    def select_all_chapters(self):
        """Click select all button"""
        self.click(self.SELECT_ALL_BUTTON)

    def confirm_selection(self):
        """Confirm chapter selection"""
        self.click(self.CONFIRM_SELECTION_BUTTON)
