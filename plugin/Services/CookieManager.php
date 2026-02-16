<?php
namespace PB_LTI\Services;

/**
 * CookieManager
 *
 * Manages WordPress authentication cookies for LTI embedded contexts
 * Ensures cookies work in third-party/embedded scenarios by setting SameSite=None
 *
 * CRITICAL: This MUST run before WordPress sets any cookies
 */
class CookieManager {

    /**
     * Initialize cookie management - MUST run very early (priority 1)
     */
    public static function init() {
        // Configure PHP session cookies for SameSite=None
        // This must run before any output/headers
        add_action('plugins_loaded', [__CLASS__, 'configure_session_cookies'], 1);

        // Intercept and rewrite Set-Cookie headers
        add_action('send_headers', [__CLASS__, 'rewrite_cookie_headers'], 1);
    }

    /**
     * Configure PHP session cookie parameters for LTI contexts
     * This affects WordPress auth cookies which use the same mechanism
     */
    public static function configure_session_cookies() {
        // Only in LTI context
        if (!self::is_lti_context()) {
            return;
        }

        error_log('[PB-LTI CookieManager] Configuring session cookies for LTI context');

        // Set session cookie parameters for SameSite=None
        if (PHP_VERSION_ID >= 70300) {
            // PHP 7.3+ supports options array
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'] ?? '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None'
            ]);
        } else {
            // PHP < 7.3: use legacy function
            // Note: Can't set SameSite directly, will handle in header rewrite
            session_set_cookie_params(0, '/', $_SERVER['HTTP_HOST'] ?? '', true, true);
        }

        error_log('[PB-LTI CookieManager] Session cookie params configured');
    }

    /**
     * Rewrite Set-Cookie headers to add SameSite=None
     * This is the most reliable method to ensure cookies work in embedded contexts
     */
    public static function rewrite_cookie_headers() {
        // Only in LTI context
        if (!self::is_lti_context()) {
            return;
        }

        error_log('[PB-LTI CookieManager] Intercepting cookie headers for LTI context');

        // Use output buffering to rewrite headers
        if (!headers_sent()) {
            ob_start([__CLASS__, 'modify_cookie_headers_callback']);
        }
    }

    /**
     * Output buffer callback to modify Set-Cookie headers
     *
     * @param string $buffer
     * @return string
     */
    public static function modify_cookie_headers_callback($buffer) {
        // Get all headers that will be sent
        $headers = headers_list();

        foreach ($headers as $header) {
            // Find Set-Cookie headers
            if (stripos($header, 'Set-Cookie:') === 0) {
                // Check if it's a WordPress cookie
                if (preg_match('/wordpress_[^=]+/', $header)) {
                    // Remove the old header
                    header_remove('Set-Cookie');

                    // Parse the cookie
                    $cookie_string = substr($header, strlen('Set-Cookie: '));

                    // Add SameSite=None if not already present
                    if (stripos($cookie_string, 'samesite') === false) {
                        $cookie_string .= '; SameSite=None';
                    }

                    // Ensure Secure flag is present (required for SameSite=None)
                    if (stripos($cookie_string, 'secure') === false) {
                        $cookie_string .= '; Secure';
                    }

                    // Set the modified cookie header
                    header('Set-Cookie: ' . $cookie_string, false);

                    error_log('[PB-LTI CookieManager] Modified cookie: ' . substr($cookie_string, 0, 50) . '...');
                }
            }
        }

        return $buffer;
    }

    /**
     * Alternative approach: Hook into wp_set_auth_cookie to override cookies
     * This runs DURING cookie setting, allowing us to modify before headers are sent
     */
    public static function override_auth_cookies() {
        // Only in LTI context
        if (!self::is_lti_context()) {
            return;
        }

        error_log('[PB-LTI CookieManager] Overriding WordPress auth cookie behavior');

        // Remove WordPress's default cookie setting
        remove_action('set_auth_cookie', 'wp_set_auth_cookie');
        remove_action('set_logged_in_cookie', 'wp_set_logged_in_cookie');

        // Add our custom cookie setting
        add_action('set_auth_cookie', [__CLASS__, 'set_custom_auth_cookie'], 10, 5);
        add_action('set_logged_in_cookie', [__CLASS__, 'set_custom_logged_in_cookie'], 10, 5);
    }

    /**
     * Set auth cookie with SameSite=None
     */
    public static function set_custom_auth_cookie($auth_cookie, $expire, $expiration, $user_id, $scheme) {
        $auth_cookie_name = $scheme === 'secure_auth' ? SECURE_AUTH_COOKIE : AUTH_COOKIE;

        setcookie($auth_cookie_name, $auth_cookie, [
            'expires' => $expire,
            'path' => ADMIN_COOKIE_PATH,
            'domain' => COOKIE_DOMAIN,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None'
        ]);

        error_log('[PB-LTI CookieManager] Set custom auth cookie: ' . $auth_cookie_name);
    }

    /**
     * Set logged-in cookie with SameSite=None
     */
    public static function set_custom_logged_in_cookie($logged_in_cookie, $expire, $expiration, $user_id, $scheme) {
        setcookie(LOGGED_IN_COOKIE, $logged_in_cookie, [
            'expires' => $expire,
            'path' => COOKIEPATH,
            'domain' => COOKIE_DOMAIN,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None'
        ]);

        error_log('[PB-LTI CookieManager] Set custom logged-in cookie');
    }

    /**
     * Check if current request is an LTI context
     *
     * @return bool
     */
    public static function is_lti_context() {
        return isset($_GET['lti_launch']) ||
               isset($_POST['id_token']) ||
               (isset($_SERVER['HTTP_REFERER']) &&
                (strpos($_SERVER['HTTP_REFERER'], 'moodle') !== false ||
                 strpos($_SERVER['HTTP_REFERER'], 'lms') !== false));
    }
}
