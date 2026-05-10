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

        // Apple token test button
        $('#mw-test-apple').on('click', function () {
            var $btn = $(this);
            var $result = $('#mw-test-result');
            $btn.prop('disabled', true).text('Wird geprüft…');
            $result.html('').css('color', '');

            $.post(MW_ADMIN.ajaxurl, {
                action: 'mw_test_apple',
                nonce: MW_ADMIN.nonce
            }, function (res) {
                if (res.success) {
                    $result.text(res.data.message).css('color', '#00612b');
                    setTimeout(function () { window.location.reload(); }, 1200);
                } else {
                    $result.text('✘ ' + (res.data ? res.data.message : 'Fehler')).css('color', '#8c1c1c');
                }
            }).fail(function () {
                $result.text('Verbindungsfehler').css('color', '#8c1c1c');
            }).always(function () {
                $btn.prop('disabled', false).text('🔍 Tokens jetzt testen');
            });
        });

        // Admin search on "Neuer Wunsch" page
        var $adminSearch = $('#mw-admin-search');
        var $adminResults = $('#mw-admin-search-results');
        var adminTimer = null;

        $adminSearch.on('input', function () {
            var q = $(this).val().trim();
            clearTimeout(adminTimer);
            if (q.length < 2) { $adminResults.empty(); return; }
            adminTimer = setTimeout(function () {
                $.post(MW_ADMIN.ajaxurl, {
                    action: 'mw_admin_search',
                    nonce: MW_ADMIN.nonce,
                    query: q
                }, function (res) {
                    var results = res.data || [];
                    $adminResults.empty();
                    if (!results.length) {
                        $adminResults.html('<p style="color:#888;padding:8px">Keine Treffer.</p>');
                        return;
                    }
                    results.forEach(function (r) {
                        var badges = '';
                        if (r.sources && r.sources.indexOf('spotify') !== -1) badges += '<span class="mw-badge-svc mw-badge-spotify">Spotify</span>';
                        if (r.sources && r.sources.indexOf('apple') !== -1)   badges += '<span class="mw-badge-svc mw-badge-apple">Apple</span>';
                        var html = $(
                            '<div class="mw-admin-result">' +
                                (r.cover ? '<img src="' + r.cover + '" alt="">' : '<div style="width:40px;height:40px;background:#ddd;border-radius:3px"></div>') +
                                '<div class="mw-admin-result-meta">' +
                                    '<strong></strong><span></span>' +
                                    '<div>' + badges + '</div>' +
                                '</div>' +
                                '<div class="mw-admin-result-duration">' + r.duration + '</div>' +
                            '</div>'
                        );
                        html.find('strong').text(r.titel);
                        html.find('.mw-admin-result-meta > span').text(r.interpret);
                        html.data('track', r);
                        $adminResults.append(html);
                    });
                });
            }, 300);
        });

        $adminResults.on('click', '.mw-admin-result', function () {
            var t = $(this).data('track');
            $('.mw-admin-result').removeClass('is-selected');
            $(this).addClass('is-selected');
            $('#titel').val(t.titel);
            $('#interpret').val(t.interpret);
            $('#spotify_url').val(t.spotify_url || '');
            $('#apple_url').val(t.apple_url || '');
            $adminSearch.val(t.titel + ' – ' + t.interpret);
            setTimeout(function () { $adminResults.empty(); }, 300);
        });
    });
}(jQuery));
