(function ($) {
    $(document).ready(function () {
        $('.mw-sync-btn').on('click', function () {
            var $btn = $(this);
            if ($btn.prop('disabled')) return;
            var id = $btn.data('id');
            var service = $btn.data('service');
            var original = $btn.text();

            $btn.prop('disabled', true).text('Wird gesendet…');

            $.post(MW_ADMIN.ajaxurl, {
                action: 'mw_sync_' + service,
                nonce: MW_ADMIN.nonce,
                id: id
            }, function (res) {
                if (res.success) {
                    $btn.text('✓ in Playlist').addClass('button-primary');
                } else {
                    alert('Fehler: ' + (res.data ? res.data.message : 'Unbekannt'));
                    $btn.prop('disabled', false).text(original);
                }
            }).fail(function () {
                alert('Verbindungsfehler');
                $btn.prop('disabled', false).text(original);
            });
        });

        // Tooltips
        $('.mw-help').each(function () {
            $(this).attr('title', $(this).data('tooltip'));
        });
    });
}(jQuery));
