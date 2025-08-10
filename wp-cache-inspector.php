<?php
/**
 * Plugin Name: WP Cache Inspector
 * Description: Diagnose server/page/object/browser caching. Run HIT/MISS tests, see headers, toggle a dev no-cache mode, and purge common caches.
 * Version: 1.0.0
 * Author: ghouliaras
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('WPCI_VER', '1.0.0');
define('WPCI_FILE', __FILE__);
define('WPCI_DIR', plugin_dir_path(__FILE__));
define('WPCI_URL', plugin_dir_url(__FILE__));
define('WPCI_DEV_COOKIE', 'wpci_dev');

require_once WPCI_DIR . 'includes/class-wpci-tester.php';
require_once WPCI_DIR . 'includes/class-wpci-admin.php';

register_activation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function () {
    // clear dev cookie if set
    if (isset($_COOKIE[WPCI_DEV_COOKIE])) {
        setcookie(WPCI_DEV_COOKIE, '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '');
    }
    flush_rewrite_rules();
});

// Add REST ping endpoint that must never be cached.
add_action('rest_api_init', function () {
    register_rest_route('wpci/v1', '/ping', array(
        'methods'  => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function () {
            nocache_headers();
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            return array(
                'now' => microtime(true),
                'rand' => wp_generate_password(20, false, false),
            );
        },
    ));
});

// If dev cookie set, force no-store for all front-end requests in this browser.
add_action('send_headers', function () {
    if (!is_admin() && isset($_COOKIE[WPCI_DEV_COOKIE]) && $_COOKIE[WPCI_DEV_COOKIE] === '1') {
        header_remove('Cache-Control');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}, 1);

// Admin page
add_action('admin_menu', function () {
    add_management_page('Cache Inspector', 'Cache Inspector', 'manage_options', 'wpci', ['WPCI_Admin', 'render']);
});

// Simple helpers
function wpci_is_litespeed_server() {
    $sv = $_SERVER['SERVER_SOFTWARE'] ?? '';
    return stripos($sv, 'litespeed') !== false;
}

/**
 * Attempt to purge caches via popular plugins if available.
 */
function wpci_purge_known_caches() {
    // LiteSpeed Cache plugin
    if (class_exists('LiteSpeed_Cache_API')) {
        try { LiteSpeed_Cache_API::purge_all(); } catch (\Throwable $e) {}
    }
    // WP Rocket
    if (function_exists('rocket_clean_domain')) {
        try { rocket_clean_domain(); } catch (\Throwable $e) {}
    }
    // W3 Total Cache
    if (function_exists('w3tc_flush_all')) {
        try { w3tc_flush_all(); } catch (\Throwable $e) {}
    }
    // WP Super Cache
    if (function_exists('wp_cache_clear_cache')) {
        try { wp_cache_clear_cache(); } catch (\Throwable $e) {}
    }
    // SiteGround Optimizer
    if (class_exists('SiteGround_Optimizer\Supercacher\Supercacher')) {
        try { \SiteGround_Optimizer\Supercacher\Supercacher::purge_cache(); } catch (\Throwable $e) {}
    }
    // Redis/Memcached via WP object cache flush
    if (function_exists('wp_cache_flush')) {
        @wp_cache_flush();
    }
}
