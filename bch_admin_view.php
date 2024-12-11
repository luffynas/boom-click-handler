<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Admin Page View
function bch_admin_page() {
    ?>
    <div class="wrap">
        <h1>Boom Click Handler - Block IP</h1>
        <form id="bch-block-ip-form" method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">IP Address to Block</th>
                    <td>
                    <textarea name="bch_ip_address" rows="10" cols="50" class="regular-text" required></textarea>
                    <p class="description">Masukkan daftar IP, satu IP per baris.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Kind</th>
                    <td>
                        <select name="bch_kind" required>
                            <option value="traffic">Traffic</option>
                            <option value="click">Click</option>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <?php submit_button('Block IP', 'primary', '', false); ?>
                <button type="button" id="export-csv" class="button-secondary">Export CSV</button>
            </p>
        </form>
        <h2>Blocked IPs</h2>
        <table id="bch-blocked-ips-table" class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th id="columnname" class="manage-column column-columnname" scope="col">IP Address</th>
                    <th id="columnname" class="manage-column column-columnname" scope="col">Kind</th>
                    <th id="columnname" class="manage-column column-columnname" scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="3">Loading...</td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}
?>
