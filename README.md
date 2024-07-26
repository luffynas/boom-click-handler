Berikut adalah file `README.md` untuk plugin "Boom Click Handler":

```markdown
# Boom Click Handler

Boom Click Handler adalah plugin WordPress yang mendeteksi dan mencegah klik iklan berlebihan dari pengguna dengan perangkat dan IP yang sama. Plugin ini membantu melindungi akun Google AdSense Anda dari aktivitas klik yang mencurigakan.

## Fitur

- Mendeteksi klik iklan lebih dari 2 kali dalam waktu kurang dari 1 menit.
- Mendeteksi klik iklan lebih dari 5 kali dalam waktu 30 menit.
- Mengkarantina pengguna yang melanggar batas klik selama 1 jam.
- Mencegah pengguna yang dikarantina untuk mengklik iklan.

## Instalasi

1. **Download** dan **Ekstrak** plugin ini.
2. **Upload** folder `boom-click-handler` ke direktori `/wp-content/plugins/`.
3. **Aktifkan** plugin melalui menu 'Plugins' di WordPress.

## Penggunaan

1. Tambahkan kode iklan AdSense Anda dalam konten seperti berikut:
    ```html
    <div class="adsense-wrapper">
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="ca-pub-1234567890123456"
             data-ad-slot="1234567890"
             data-ad-format="auto"></ins>
        <script>
             (adsbygoogle = window.adsbygoogle || []).push({});
        </script>
    </div>
    ```

2. Plugin akan secara otomatis mendeteksi klik dan menerapkan logika karantina jika pengguna melanggar batas klik.

## Kode

### File: `boom-click-handler.php`

```php
<?php
/*
Plugin Name: Boom Click Handler
Description: Mendeteksi double click iklan adsense dari pengguna dengan device dan IP yang sama, dan memasukkan mereka ke dalam karantina jika mereka mengklik lebih dari 2 kali dalam waktu kurang dari 1 menit atau lebih dari 5 kali dalam waktu 30 menit.
Version: 1.3
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
}

// Enqueue Script
add_action('wp_enqueue_scripts', 'bch_enqueue_scripts');
function bch_enqueue_scripts() {
    wp_enqueue_script('bch-script', plugin_dir_url(__FILE__) . 'js/bch-script.js', array('jquery'), '1.3', true);
    wp_localize_script('bch-script', 'bch_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}

// Handle Clicks
add_action('wp_ajax_nopriv_bch_handle_click', 'bch_handle_click');
add_action('wp_ajax_bch_handle_click', 'bch_handle_click');
function bch_handle_click() {
    global $wpdb;
    $quarantine_table = $wpdb->prefix . 'bch_quarantine';
    $click_log_table = $wpdb->prefix . 'bch_click_log';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $device_id = sanitize_text_field($_POST['device_id']);
    $current_time = current_time('mysql');

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
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $device_id = sanitize_text_field($_POST['device_id']);
    $current_time = current_time('mysql');

    // Check quarantine
    $quarantine = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE ip_address = %s AND device_id = %s AND quarantine_until > %s", $ip_address, $device_id, $current_time));
    if ($quarantine) {
        wp_send_json_error('You are in quarantine.');
    } else {
        wp_send_json_success('You are not in quarantine.');
    }

    wp_die();
}
?>
```

### File: `js/bch-script.js`

```javascript
jQuery(document).ready(function($) {
    let device_id = localStorage.getItem('bch_device_id');
    if (!device_id) {
        device_id = 'device_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('bch_device_id', device_id);
    }

    function wrapAds() {
        $('ins.adsbygoogle').each(function() {
            if (!$(this).parent().hasClass('adsense-wrapper')) {
                $(this).wrap('<div class="adsense-wrapper"></div>');
            }
        });
    }

    // Initial wrapping
    wrapAds();

    // Re-wrap ads after new ads are loaded
    (function() {
        const originalPush = window.adsbygoogle.push;
        window.adsbygoogle.push = function(...args) {
            originalPush.apply(this, args);
            wrapAds();
        };
    })();

    // Check quarantine status
    function checkQuarantine(callback) {
        $.ajax({
            type: 'POST',
            url: bch_ajax.ajax_url,
            data: {
                action: 'bch_check_quarantine',
                device_id: device_id
        },
        success: function(response) {
            if (response.success) {
                callback(false);
            } else {
                callback(true);
            }
        }
    });
}

$('body').on('click', '.adsense-wrapper', function(e) {
    checkQuarantine(function(isQuarantined) {
        if (isQuarantined) {
            e.preventDefault();
            alert('You are in quarantine and cannot click ads.');
        } else {
            $.ajax({
                type: 'POST',
                url: bch_ajax.ajax_url,
                data: {
                    action: 'bch_handle_click',
                    device_id: device_id
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Click registered');
                    } else {
                        console.log(response.data);
                    }
                }
            });
        }
    });
});
});
```

## Lisensi

Plugin ini dilisensikan di bawah [Nama Lisensi Anda].