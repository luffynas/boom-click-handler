<?php
/*
Plugin Name: Boom Click Handler
Description: Mendeteksi dan mencegah klik iklan berlebihan dari pengguna dengan perangkat dan IP yang sama menggunakan metode deteksi canggih.
Version: 1.0
Author: @luffynas
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

    // Create blacklist table
    $blacklist_table = $wpdb->prefix . 'bch_blacklist';
    $sql = "CREATE TABLE $blacklist_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        ip_address varchar(100) NOT NULL,
        device_id varchar(100) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta($sql);
}

// Deactivation Hook
register_deactivation_hook(__FILE__, 'bch_deactivate');
function bch_deactivate() {
    global $wpdb;
    // Drop quarantine table
    $quarantine_table = $wpdb->prefix . 'bch_quarantine';
    $wpdb->query("DROP TABLE IF EXISTS $quarantine_table;");

    // Drop click log table
    $click_log_table = $wpdb->prefix . 'bch_click_log';
    $wpdb->query("DROP TABLE IF EXISTS $click_log_table;");

    // Drop traffic log table
    $traffic_log_table = $wpdb->prefix . 'bch_traffic_log';
    $wpdb->query("DROP TABLE IF EXISTS $traffic_log_table;");

    // Drop blacklist table
    $blacklist_table = $wpdb->prefix . 'bch_blacklist';
    $wpdb->query("DROP TABLE IF EXISTS $blacklist_table;");
}

// Enqueue Script
add_action('wp_enqueue_scripts', 'bch_enqueue_scripts');
function bch_enqueue_scripts() {
    wp_enqueue_script('bch-script', plugin_dir_url(__FILE__) . 'js/bch-script.js', array('jquery'), '1.5', true);
    wp_localize_script('bch-script', 'bch_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
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
    $current_time = current_time('mysql');

    // Check blacklist
    $blacklist = $wpdb->get_row($wpdb->prepare("SELECT * FROM $blacklist_table WHERE ip_address = %s AND device_id = %s", $ip_address, $device_id));
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
    $blacklist = $wpdb->get_row($wpdb->prepare("SELECT * FROM $blacklist_table WHERE ip_address = %s AND device_id = %s", $ip_address, $device_id));
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
            'device_id' => $device_id
        ));
        return true;
    }
    return false;
}

// Get User Location using multiple providers
function get_user_location($ip_address) {
    // Try ipinfo.io
    $api_token_ipinfo = 'bd550fe55da285';
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