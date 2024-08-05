<?php

// Admin Page View for Whitelist
function bch_whitelist_page() {
    global $wpdb;
    $whitelist_table = $wpdb->prefix . 'bch_whitelist';
    $whitelist = $wpdb->get_results("SELECT * FROM $whitelist_table ORDER BY user_login ASC");

    ?>
    <div class="wrap">
        <h1>Boom Click Handler - Whitelist</h1>
        <form id="bch-add-whitelist-form" method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">User Login to Whitelist</th>
                    <td><input type="text" name="bch_user_login" value="" class="regular-text" required /></td>
                </tr>
            </table>
            <?php submit_button('Add to Whitelist'); ?>
        </form>
        <h2>Whitelist</h2>
        <table id="bch-whitelist-table" class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th id="columnname" class="manage-column column-columnname" scope="col">User Login</th>
                    <th id="columnname" class="manage-column column-columnname" scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($whitelist) : ?>
                    <?php foreach ($whitelist as $user) : ?>
                        <tr>
                            <td><?php echo esc_html($user->user_login); ?></td>
                            <td><a href="#" class="button bch-remove-whitelist" data-user-id="<?php echo esc_attr($user->id); ?>">Remove</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="2">No users in whitelist.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>