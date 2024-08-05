<?php
/*
Plugin Name: Boom Click Handler
Description: Mendeteksi dan mencegah klik iklan berlebihan dari pengguna dengan perangkat dan IP yang sama menggunakan metode deteksi canggih. Termasuk memblokir IP yang dicurigai sebagai bot.
Version: 1.6
Author: Developer
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Activation Hook
register_activation_hook(__FILE__, 'bch_activate');
function bch_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Create quarantine table
    $quarantine_table = $wpdb->prefix . 'bch_quarantine';
    $sql = "CREATE TABLE $quarantine_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        ip_address varchar(100) NOT NULL,
        device_id varchar(100) NOT NULL,
        quarantine_until datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Create click log table
    $click_log_table = $wpdb->prefix . 'bch_click_log';
    $sql = "CREATE TABLE $click_log_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        ip_address varchar(100) NOT NULL,
        device_id varchar(100) NOT NULL,
        click_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta($sql);

    // Create traffic log table
    $traffic_log_table = $wpdb->prefix . 'bch_traffic_log';
    $sql = "CREATE TABLE $traffic_log_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        ip_address varchar(100) NOT NULL,
        device_id varchar(100) NOT NULL,
        location varchar(255) NOT NULL,
        access_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta($sql);

    // Create or update blacklist table
    $blacklist_table = $wpdb->prefix . 'bch_blacklist';
    $sql = "CREATE TABLE $blacklist_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        ip_address varchar(100) NOT NULL,
        device_id varchar(100) DEFAULT NULL,
        kind varchar(50) NOT NULL,
        reason varchar(255) DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta($sql);

    // Create access log table
    $access_log_table = $wpdb->prefix . 'bch_access_log';
    $sql = "CREATE TABLE $access_log_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        ip_address varchar(100) NOT NULL,
        user_agent text NOT NULL,
        url text NOT NULL,
        access_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        status varchar(10) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta($sql);
}

// Get IP Address
function bch_get_ip_address() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Log setiap akses ke situs
function bch_log_access() {
    global $wpdb;
    $access_log_table = $wpdb->prefix . 'bch_access_log';
    $ip_address = bch_get_ip_address();
    $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT']);
    $url = sanitize_text_field($_SERVER['REQUEST_URI']);
    $access_time = current_time('mysql');
    $status = http_response_code();

    $wpdb->insert($access_log_table, array(
        'ip_address' => $ip_address,
        'user_agent' => $user_agent,
        'url' => $url,
        'access_time' => $access_time,
        'status' => $status
    ));
}
add_action('wp', 'bch_log_access');

// Check if IP is blocked
function bch_check_ip_block() {
    global $wpdb;
    $blacklist_table = $wpdb->prefix . 'bch_blacklist';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    // error_log('ERROR LOG ::: '.$ip_address);

    $blocked_ip = $wpdb->get_row($wpdb->prepare("SELECT * FROM $blacklist_table WHERE ip_address = %s AND kind = 'traffic'", $ip_address));
    if ($blocked_ip) {
        wp_die('Your IP has been blocked.');
    }
}
add_action('init', 'bch_check_ip_block');

// Include admin view
include plugin_dir_path(__FILE__) . 'bch_admin_view.php';

// Include admin view
include plugin_dir_path(__FILE__) . 'bch_admin_access_log_view.php';

// Admin Menu for Blocking IPs
add_action('admin_menu', 'bch_admin_menu');
function bch_admin_menu() {
    add_menu_page('Boom Click Handler', 'Boom Click Handler', 'manage_options', 'boom-click-handler', 'bch_admin_page');
    add_submenu_page('boom-click-handler', 'Access Log', 'Access Log', 'manage_options', 'bch-access-log', 'bch_access_log_page');
}

// Enqueue scripts
add_action('admin_enqueue_scripts', 'bch_enqueue_admin_scripts');
function bch_enqueue_admin_scripts($hook) {
    if ($hook != 'toplevel_page_boom-click-handler' && $hook != 'boom-click-handler_page_bch-access-log') {
        return;
    }
    wp_enqueue_script('bch-admin-script', plugin_dir_url(__FILE__) . 'js/bch-admin.js', array('jquery'), '1.0', true);
    wp_localize_script('bch-admin-script', 'bch_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}

// Handle IP Blocking via AJAX
add_action('wp_ajax_bch_block_ip', 'bch_ajax_block_ip');
function bch_ajax_block_ip() {
    global $wpdb;
    $blacklist_table = $wpdb->prefix . 'bch_blacklist';
    $ip_address = sanitize_text_field($_POST['bch_ip_address']);
    $kind = sanitize_text_field($_POST['bch_kind']);
    if (filter_var($ip_address, FILTER_VALIDATE_IP)) {
        $result = $wpdb->insert($blacklist_table, array(
            'ip_address' => $ip_address,
            'device_id' => null,
            'kind' => $kind
        ));
        if ($result !== false) {
            wp_send_json_success('IP ' . esc_html($ip_address) . ' has been blocked as ' . esc_html($kind));
        } else {
            wp_send_json_error('Failed to block IP ' . esc_html($ip_address));
        }
    } else {
        wp_send_json_error('Invalid IP address');
    }
}

// Handle IP Unblocking via AJAX
add_action('wp_ajax_bch_unblock_ip', 'bch_ajax_unblock_ip');
function bch_ajax_unblock_ip() {
    global $wpdb;
    $blacklist_table = $wpdb->prefix . 'bch_blacklist';
    $ip_id = intval($_POST['ip_id']);
    $result = $wpdb->delete($blacklist_table, array('id' => $ip_id));
    if ($result !== false) {
        wp_send_json_success('IP has been unblocked');
    } else {
        wp_send_json_error('Failed to unblock IP');
    }
}

// Function to retrieve blocked IPs for AJAX
function bch_get_blocked_ips() {
    global $wpdb;
    $blacklist_table = $wpdb->prefix . 'bch_blacklist';
    $blocked_ips = $wpdb->get_results("SELECT * FROM $blacklist_table");
    wp_send_json_success($blocked_ips);
}
add_action('wp_ajax_bch_get_blocked_ips', 'bch_get_blocked_ips');

// Function to clear all entries in access log via AJAX
add_action('wp_ajax_bch_clear_access_log', 'bch_clear_access_log');
function bch_clear_access_log() {
    global $wpdb;
    $access_log_table = $wpdb->prefix . 'bch_access_log';
    $result = $wpdb->query("TRUNCATE TABLE $access_log_table");
    if ($result !== false) {
        wp_send_json_success('Access log cleared.');
    } else {
        wp_send_json_error('Failed to clear access log.');
    }
}

// Detect and Block Suspicious IPs
function bch_block_suspicious_ips() {
    global $wpdb;
    $access_log_table = $wpdb->prefix . 'bch_access_log';
    $blacklist_table = $wpdb->prefix . 'bch_blacklist';

    // Daftar user-agent yang mencurigakan
    $suspicious_agents = ["scrapy", "python", "aiohttp"];

    // Mendapatkan semua entri dengan user-agent yang mencurigakan
    $suspicious_entries = $wpdb->get_results("
        SELECT DISTINCT ip_address, user_agent
        FROM {$access_log_table}
        WHERE user_agent REGEXP '" . implode("|", $suspicious_agents) . "'
    ");

    foreach ($suspicious_entries as $entry) {
        $ip_address = $entry->ip_address;
        $user_agent = $entry->user_agent;

        // Memeriksa apakah IP sudah ada di blacklist
        $is_blocked = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $blacklist_table WHERE ip_address = %s AND kind = 'traffic'", $ip_address));
        if (!$is_blocked) {
            // Memasukkan IP yang mencurigakan ke dalam tabel blacklist
            $wpdb->insert($blacklist_table, array(
                'ip_address' => $ip_address,
                'device_id' => null,
                'kind' => 'traffic',
                'reason' => 'Suspicious User-Agent: ' . $user_agent
            ));
        }
    }

    // Memblokir IP yang mengakses lebih dari 4 konten dalam satu menit
    // $time_limit = '1 minute';
    // $access_threshold = 4;

    // $frequent_accesses = $wpdb->get_results("
    //     SELECT ip_address, COUNT(*) as access_count
    //     FROM {$access_log_table}
    //     WHERE access_time > DATE_SUB(NOW(), INTERVAL $time_limit)
    //     GROUP BY ip_address
    //     HAVING access_count > $access_threshold
    // ");

    // foreach ($frequent_accesses as $entry) {
    //     $ip_address = $entry->ip_address;
    //     $access_count = $entry->access_count;

    //     // Memeriksa apakah IP sudah ada di blacklist
    //     $is_blocked = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $blacklist_table WHERE ip_address = %s AND kind = 'traffic'", $ip_address));
    //     if (!$is_blocked) {
    //         // Memasukkan IP yang mencurigakan ke dalam tabel blacklist
    //         $wpdb->insert($blacklist_table, array(
    //             'ip_address' => $ip_address,
    //             'device_id' => null,
    //             'kind' => 'traffic',
    //             'reason' => 'Frequent access: ' . $access_count . ' accesses in ' . $time_limit
    //         ));
    //     }
    // }
}
add_action('init', 'bch_block_suspicious_ips');

// Validate reCAPTCHA
function validate_recaptcha($response) {
    $secret_key = 'YOUR_SECRET_KEY';
    $remote_ip = $_SERVER['REMOTE_ADDR'];
    $response = wp_remote_get("https://www.google.com/recaptcha/api/siteverify?secret=$secret_key&response=$response&remoteip=$remote_ip");
    $response_body = wp_remote_retrieve_body($response);
    $result = json_decode($response_body, true);
    return $result['success'];
}

// Handle Clicks
add_action('wp_ajax_nopriv_bch_handle_click', 'bch_handle_click');
add_action('wp_ajax_bch_handle_click', 'bch_handle_click');
function bch_handle_click() {
    global $wpdb;
    $quarantine_table = $wpdb->prefix . 'bch_quarantine';
    $click_log_table = $wpdb->prefix . 'bch_click_log';
    $blacklist_table = $wpdb->prefix . 'bch_blacklist';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $device_id = sanitize_text_field($_POST['device_id']);
    $recaptcha_response = sanitize_text_field($_POST['g-recaptcha-response']);
    $current_time = current_time('mysql');

    if (!validate_recaptcha($recaptcha_response)) {
        wp_send_json_error('reCAPTCHA validation failed.');
    }

    // Check blacklist
    $blacklist = $wpdb->get_row($wpdb->prepare("SELECT * FROM $blacklist_table WHERE ip_address = %s AND (device_id IS NULL OR device_id = %s) AND kind = 'click'", $ip_address, $device_id));
    if ($blacklist) {
        wp_send_json_error('You are blacklisted.');
    }

    // Detect VPN usage
    if (detect_vpn($ip_address, $device_id)) {
        wp_send_json_error('You are using a VPN.');
    }

    // Check quarantine
    $quarantine = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quarantine_table WHERE ip_address = %s AND device_id = %s AND quarantine_until > %s", $ip_address, $device_id, $current_time));
    if ($quarantine) {
        wp_send_json_error('You are in quarantine.');
    }

    // Log click
    $wpdb->insert($click_log_table, array(
        'ip_address' => $ip_address,
        'device_id' => $device_id,
        'click_time' => $current_time
    ));

    // Check click limit for 1 minute
    $click_limit_1_minute = 2; // Maximum allowed clicks in 1 minute
    $time_limit_1_minute = '1 minute'; // Time period to check clicks
    $click_count_1_minute = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $click_log_table WHERE ip_address = %s AND device_id = %s AND click_time > DATE_SUB(%s, INTERVAL $time_limit_1_minute)", $ip_address, $device_id, $current_time));

    if ($click_count_1_minute > $click_limit_1_minute) {
        $wpdb->insert($quarantine_table, array(
            'ip_address' => $ip_address,
            'device_id' => $device_id,
            'quarantine_until' => date('Y-m-d H:i:s', strtotime('+1 hour', current_time('timestamp')))
        ));
        wp_send_json_error('You have been quarantined.');
    }

    // Check click limit for 30 minutes
    $click_limit_30_minutes = 5; // Maximum allowed clicks in 30 minutes
    $time_limit_30_minutes = '30 minutes'; // Time period to check clicks
    $click_count_30_minutes = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $click_log_table WHERE ip_address = %s AND device_id = %s AND click_time > DATE_SUB(%s, INTERVAL $time_limit_30_minutes)", $ip_address, $device_id, $current_time));

    if ($click_count_30_minutes > $click_limit_30_minutes) {
        $wpdb->insert($quarantine_table, array(
            'ip_address' => $ip_address,
            'device_id' => $device_id,
            'quarantine_until' => date('Y-m-d H:i:s', strtotime('+1 hour', current_time('timestamp')))
        ));
        wp_send_json_error('You have been quarantined.');
    }

    wp_send_json_success('Click registered.');
    wp_die();
}

// Check Quarantine Status
add_action('wp_ajax_nopriv_bch_check_quarantine', 'bch_check_quarantine');
add_action('wp_ajax_bch_check_quarantine', 'bch_check_quarantine');
function bch_check_quarantine() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bch_quarantine';
    $blacklist_table = $wpdb->prefix . 'bch_blacklist';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $device_id = sanitize_text_field($_POST['device_id']);
    $current_time = current_time('mysql');

    // Check blacklist
    $blacklist = $wpdb->get_row($wpdb->prepare("SELECT * FROM $blacklist_table WHERE ip_address = %s AND (device_id IS NULL OR device_id = %s) AND kind = 'click'", $ip_address, $device_id));
    if ($blacklist) {
        wp_send_json_error('You are blacklisted.');
    }

    // Check quarantine
    $quarantine = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE ip_address = %s AND device_id = %s AND quarantine_until > %s", $ip_address, $device_id, $current_time));
    if ($quarantine) {
        wp_send_json_error('You are in quarantine.');
    } else {
        wp_send_json_success('You are not in quarantine.');
    }

    wp_die();
}

// Detect VPN based on traffic pattern
function detect_vpn($ip_address, $device_id) {
    global $wpdb;
    $traffic_log_table = $wpdb->prefix . 'bch_traffic_log';
    $blacklist_table = $wpdb->prefix . 'bch_blacklist';
    $current_time = current_time('mysql');
    $location = get_user_location($ip_address);

    // Insert current access log
    $wpdb->insert($traffic_log_table, array(
        'ip_address' => $ip_address,
        'device_id' => $device_id,
        'location' => $location,
        'access_time' => $current_time
    ));

    // Check for rapid IP changes
    $time_limit = '10 minutes';
    $recent_logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $traffic_log_table WHERE device_id = %s AND access_time > DATE_SUB(%s, INTERVAL $time_limit)", $device_id, $current_time));

    $ip_changes = 0;
    $locations = [];
    foreach ($recent_logs as $log) {
        if ($log->ip_address !== $ip_address) {
            $ip_changes++;
        }
        if (!in_array($log->location, $locations)) {
            $locations[] = $log->location;
        }
    }

    // Detect rapid IP changes or multiple locations
    if ($ip_changes > 3 || count($locations) > 2) {
        $wpdb->insert($blacklist_table, array(
            'ip_address' => $ip_address,
            'device_id' => $device_id,
            'kind' => 'traffic'
        ));
        return true;
    }
    return false;
}

// Get User Location using multiple providers
function get_user_location($ip_address) {
    // Try ipinfo.io
    $api_token_ipinfo = 'YOUR_IPINFO_API_TOKEN';
    $response = wp_remote_get("https://ipinfo.io/$ip_address/json?token=$api_token_ipinfo");
    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (isset($data['country']) && isset($data['city'])) {
            return $data['country'] . ', ' . $data['city'];
        }
    }

    // Fallback to ip-api.com
    $response = wp_remote_get("http://ip-api.com/json/$ip_address");
    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($data['status'] === 'success') {
            return $data['country'] . ', ' . $data['city'];
        }
    }

    return 'Unknown';
}
?>
