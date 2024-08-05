<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Admin Page View for Access Log
function bch_access_log_page() {
    global $wpdb;
    $access_log_table = $wpdb->prefix . 'bch_access_log';
    $logs = $wpdb->get_results("SELECT * FROM $access_log_table ORDER BY access_time DESC");

    ?>
    <div class="wrap">
        <h1>Boom Click Handler - Access Log</h1>
        <button id="bch-clear-access-log" class="button button-secondary" style="margin-bottom: 20px;">Clear All Logs</button>
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th id="columnname" class="manage-column column-columnname" scope="col">IP Address</th>
                    <th id="columnname" class="manage-column column-columnname" scope="col">User Agent</th>
                    <th id="columnname" class="manage-column column-columnname" scope="col">URL</th>
                    <th id="columnname" class="manage-column column-columnname" scope="col">Access Time</th>
                    <th id="columnname" class="manage-column column-columnname" scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs) : ?>
                    <?php foreach ($logs as $log) : ?>
                        <tr>
                            <td><?php echo esc_html($log->ip_address); ?></td>
                            <td><?php echo esc_html($log->user_agent); ?></td>
                            <td><?php echo esc_html($log->url); ?></td>
                            <td><?php echo esc_html($log->access_time); ?></td>
                            <td><?php echo esc_html($log->status); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">No logs found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

?>
