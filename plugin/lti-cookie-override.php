<?php
/**
 * LTI Cookie Override
 *
 * This file MUST be loaded BEFORE WordPress's pluggable.php
 * It overrides wp_set_auth_cookie() to set SameSite=None for LTI contexts
 *
 * Place in mu-plugins or load very early
 */

if (!function_exists('wp_set_auth_cookie')) {
    /**
     * Override wp_set_auth_cookie to add SameSite=None for LTI contexts
     *
     * This function is copied from WordPress core and modified to add SameSite=None
     * when in an LTI context (detected by lti_launch parameter or Moodle referer)
     */
    function wp_set_auth_cookie($user_id, $remember = false, $secure = '', $token = '') {
        // Detect LTI context
        $is_lti = isset($_GET['lti_launch']) ||
                  isset($_POST['id_token']) ||
                  (isset($_SERVER['HTTP_REFERER']) &&
                   (strpos($_SERVER['HTTP_REFERER'], 'moodle') !== false ||
                    strpos($_SERVER['HTTP_REFERER'], 'lms') !== false));

        if ($is_lti) {
            error_log('[PB-LTI Override] wp_set_auth_cookie called in LTI context - using SameSite=None');
        }

        if ( $remember ) {
            $expiration = time() + apply_filters( 'auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user_id, $remember );
            $expire = $expiration + ( 12 * HOUR_IN_SECONDS );
        } else {
            $expiration = time() + apply_filters( 'auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user_id, $remember );
            $expire = 0;
        }

        if ( '' === $secure ) {
            $secure = is_ssl();
        }

        // For LTI contexts, force secure
        if ($is_lti) {
            $secure = true;
        }

        $secure = apply_filters( 'secure_auth_cookie', $secure, $user_id );
        $secure_logged_in_cookie = apply_filters( 'secure_logged_in_cookie', false, $user_id, $secure );
        $auth_cookie = wp_generate_auth_cookie( $user_id, $expiration, 'auth', $token );
        $logged_in_cookie = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token );

        do_action( 'set_auth_cookie', $auth_cookie, $expire, $expiration, $user_id, 'auth', $token );

        if ( $secure ) {
            $auth_cookie_name = SECURE_AUTH_COOKIE;
            $scheme = 'secure_auth';
        } else {
            $auth_cookie_name = AUTH_COOKIE;
            $scheme = 'auth';
        }

        // Set cookies with SameSite=None for LTI contexts
        if ($is_lti && PHP_VERSION_ID >= 70300) {
            // PHP 7.3+ with SameSite support
            setcookie( $auth_cookie_name, $auth_cookie, [
                'expires' => $expire,
                'path' => PLUGINS_COOKIE_PATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'None'
            ] );

            setcookie( $auth_cookie_name, $auth_cookie, [
                'expires' => $expire,
                'path' => ADMIN_COOKIE_PATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'None'
            ] );

            setcookie( LOGGED_IN_COOKIE, $logged_in_cookie, [
                'expires' => $expire,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => $secure_logged_in_cookie,
                'httponly' => true,
                'samesite' => 'None'
            ] );

            if ( COOKIEPATH !== SITECOOKIEPATH ) {
                setcookie( LOGGED_IN_COOKIE, $logged_in_cookie, [
                    'expires' => $expire,
                    'path' => SITECOOKIEPATH,
                    'domain' => COOKIE_DOMAIN,
                    'secure' => $secure_logged_in_cookie,
                    'httponly' => true,
                    'samesite' => 'None'
                ] );
            }

            error_log('[PB-LTI Override] Set cookies with SameSite=None');
        } else {
            // Standard WordPress cookie setting (no SameSite) or old PHP
            setcookie( $auth_cookie_name, $auth_cookie, $expire, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, $secure, true );
            setcookie( $auth_cookie_name, $auth_cookie, $expire, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, $secure, true );
            setcookie( LOGGED_IN_COOKIE, $logged_in_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true );

            if ( COOKIEPATH !== SITECOOKIEPATH ) {
                setcookie( LOGGED_IN_COOKIE, $logged_in_cookie, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true );
            }
        }
    }
}
