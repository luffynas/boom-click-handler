jQuery(document).ready(function($) {
    $('#bch-block-ip-form').on('submit', function(e) {
        e.preventDefault();
        var ipAddress = $('input[name="bch_ip_address"]').val();
        var kind = $('select[name="bch_kind"]').val();

        $.post(bch_ajax.ajax_url, {
            action: 'bch_block_ip',
            bch_ip_address: ipAddress,
            bch_kind: kind
        }, function(response) {
            if (response.success) {
                alert(response.data);
                loadBlockedIPs();
            } else {
                alert(response.data);
            }
        });
    });

    $('body').on('click', '.bch-unblock-ip', function(e) {
        e.preventDefault();
        var ipId = $(this).data('ip-id');

        $.post(bch_ajax.ajax_url, {
            action: 'bch_unblock_ip',
            ip_id: ipId
        }, function(response) {
            if (response.success) {
                alert(response.data);
                loadBlockedIPs();
            } else {
                alert(response.data);
            }
        });
    });

    function loadBlockedIPs() {
        $.post(bch_ajax.ajax_url, {
            action: 'bch_get_blocked_ips'
        }, function(response) {
            if (response.success) {
                var tableBody = $('#bch-blocked-ips-table tbody');
                tableBody.empty();
                $.each(response.data, function(index, ip) {
                    var row = '<tr>' +
                        '<td>' + ip.ip_address + '</td>' +
                        '<td>' + ip.kind + '</td>' +
                        '<td><a href="#" class="button bch-unblock-ip" data-ip-id="' + ip.id + '">Unblock</a></td>' +
                        '</tr>';
                    tableBody.append(row);
                });
            }
        });
    }

    loadBlockedIPs();

    $('#bch-clear-access-log').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to clear all logs?')) {
            $.post(bch_ajax.ajax_url, {
                action: 'bch_clear_access_log'
            }, function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert(response.data);
                }
            });
        }
    });

    $('#bch-add-whitelist-form').on('submit', function(e) {
        e.preventDefault();
        var userLogin = $('input[name="bch_user_login"]').val();

        $.post(bch_ajax.ajax_url, {
            action: 'bch_add_whitelist',
            bch_user_login: userLogin
        }, function(response) {
            if (response.success) {
                alert(response.data);
                location.reload();
            } else {
                alert(response.data);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.log('AJAX error:', textStatus, errorThrown);
        });
    });

    $('body').on('click', '.bch-remove-whitelist', function(e) {
        e.preventDefault();
        var userId = $(this).data('user-id');

        $.post(bch_ajax.ajax_url, {
            action: 'bch_remove_whitelist',
            user_id: userId
        }, function(response) {
            if (response.success) {
                alert(response.data);
                location.reload();
            } else {
                alert(response.data);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.log('AJAX error:', textStatus, errorThrown);
        });
    });
});

