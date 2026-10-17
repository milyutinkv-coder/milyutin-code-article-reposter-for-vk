jQuery(document).ready(function($) {
    // ---- Settings page ----
    $('input[name="vkaire_settings[summary_source]"]').on('change', function() {
        if ($(this).val() === 'excerpt') {
            $('#yandex-gpt-settings').slideUp();
        } else {
            $('#yandex-gpt-settings').slideDown();
        }
    });

    // ---- Logs page ----
    $('#vkaire-refresh-logs').on('click', function() {
        var $btn = $(this);
        var $msg = $('#vkaire-log-message');
        $btn.prop('disabled', true);
        $msg.html(vkaireL10n.refreshing);
        $.post(ajaxurl, {
            action: 'vkaire_refresh_logs',
            nonce: vkaireL10n.refresh_nonce
        }, function(resp) {
            if (resp.success) {
                $('#vkaire-log-container').text(resp.data);
                $msg.html('✅ ' + vkaireL10n.refreshed).css('color', 'green');
            } else {
                $msg.html('❌ ' + resp.data).css('color', 'red');
            }
        }).fail(function() {
            $msg.html('❌ ' + vkaireL10n.connection_error).css('color', 'red');
        }).always(function() {
            $btn.prop('disabled', false);
            setTimeout(function(){ $msg.html(''); }, 3000);
        });
    });
    $('#vkaire-clear-logs').on('click', function() {
        if (!confirm(vkaireL10n.confirm_clear)) return;
        var $btn = $(this);
        var $msg = $('#vkaire-log-message');
        $btn.prop('disabled', true);
        $msg.html(vkaireL10n.clearing);
        $.post(ajaxurl, {
            action: 'vkaire_clear_logs',
            nonce: vkaireL10n.clear_nonce
        }, function(resp) {
            if (resp.success) {
                $('#vkaire-log-container').text(vkaireL10n.logs_cleared);
                $msg.html('✅ ' + resp.data).css('color', 'green');
            } else {
                $msg.html('❌ ' + resp.data).css('color', 'red');
            }
        }).fail(function() {
            $msg.html('❌ ' + vkaireL10n.connection_error).css('color', 'red');
        }).always(function() {
            $btn.prop('disabled', false);
            setTimeout(function(){ $msg.html(''); }, 3000);
        });
    });
    var autoRefresh = setInterval(function() { $('#vkaire-refresh-logs').click(); }, 30000);
    $('#vkaire-log-container').on('scroll', function() { clearInterval(autoRefresh); });

    // ---- Post edit meta box ----
    $('.vkaire-manual-btn').on('click', function() {
        var $btn = $(this);
        var $result = $('#vkaire-meta-result');
        var post_id = $btn.data('post-id');
        var nonce = $btn.data('nonce');
        $btn.prop('disabled', true).text(vkaireL10n.publishing);
        $result.html('');
        $.post(ajaxurl, {
            action: 'vkaire_manual_publish',
            post_id: post_id,
            nonce: nonce
        }, function(resp) {
            if (resp.success) {
                $result.html('<span style="color:green;">✅ ' + resp.data + '</span>');
                $btn.replaceWith('<p style="color:green;">✅ ' + vkaireL10n.published + '</p>');
            } else {
                $result.html('<span style="color:red;">❌ ' + resp.data + '</span>');
                $btn.prop('disabled', false).text(vkaireL10n.publish_now);
            }
        }).fail(function() {
            $result.html('<span style="color:red;">❌ ' + vkaireL10n.network_error + '</span>');
            $btn.prop('disabled', false).text(vkaireL10n.publish_now);
        });
    });
});