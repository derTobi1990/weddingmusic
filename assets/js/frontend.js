(function ($) {
    'use strict';

    $(document).ready(function () {
        var $form = $('#mw-form');
        var $search = $('#mw-search');
        var $results = $('#mw-search-results');
        var $titel = $('#mw-titel');
        var $interpret = $('#mw-interpret');
        var $spotifyId = $('#mw-spotify-id');
        var $spotifyUrl = $('#mw-spotify-url');
        var $error = $('#mw-error');
        var $submit = $('#mw-submit');
        var searchTimer = null;

        // Live search
        $search.on('input', function () {
            var query = $(this).val().trim();
            clearTimeout(searchTimer);
            if (query.length < 2) {
                $results.empty();
                return;
            }
            searchTimer = setTimeout(function () {
                $.post(MW.ajaxurl, {
                    action: 'mw_search',
                    nonce: MW.nonce,
                    query: query
                }, function (res) {
                    renderResults(res.data || []);
                });
            }, 300);
        });

        function renderResults(results) {
            $results.empty();
            if (!results.length) return;
            results.forEach(function (r) {
                var html = $(
                    '<div class="mw-search-result" data-id="' + r.id + '" data-url="' + r.url + '">' +
                        (r.cover ? '<img src="' + r.cover + '" alt="">' : '<div class="mw-cover-placeholder">♪</div>') +
                        '<div class="mw-result-meta">' +
                            '<strong>' + escapeHtml(r.titel) + '</strong>' +
                            '<span>' + escapeHtml(r.interpret) + '</span>' +
                        '</div>' +
                        '<div class="mw-result-duration">' + r.duration + '</div>' +
                    '</div>'
                );
                html.data('track', r);
                $results.append(html);
            });
        }

        // Click search result
        $results.on('click', '.mw-search-result', function () {
            var track = $(this).data('track');
            $('.mw-search-result').removeClass('is-selected');
            $(this).addClass('is-selected');

            $titel.val(track.titel);
            $interpret.val(track.interpret);
            $spotifyId.val(track.id);
            $spotifyUrl.val(track.url);
            $search.val(track.titel + ' – ' + track.interpret);

            // Hide other results, keep only selected
            setTimeout(function () { $results.empty(); }, 300);
        });

        // Submit
        $form.on('submit', function (e) {
            e.preventDefault();
            var data = {
                action: 'mw_submit',
                nonce: MW.nonce,
                name: $('#mw-name').val(),
                titel: $titel.val(),
                interpret: $interpret.val(),
                link: $('#mw-link').val(),
                spotify_id: $spotifyId.val(),
                spotify_url: $spotifyUrl.val(),
                apple_id: $('#mw-apple-id').val(),
                apple_url: $('#mw-apple-url').val()
            };

            if (!data.name) { showError('Bitte gib deinen Namen ein.'); return; }
            if (!data.titel && !data.link) { showError('Bitte einen Song auswählen oder Titel/Interpret eingeben.'); return; }

            setLoading(true);
            hideError();

            $.post(MW.ajaxurl, data, function (res) {
                if (res.success) {
                    $form.fadeOut(300, function () {
                        if (res.data.duplicate && res.data.count > 1) {
                            $('#mw-success-msg').text('Dieser Song wurde bereits ' + res.data.count + '× gewünscht – wir merken uns dich auch dafür!');
                        }
                        $('#mw-success').fadeIn(400);
                    });
                } else {
                    showError(res.data && res.data.message ? res.data.message : 'Fehler beim Senden.');
                }
            }).fail(function () {
                showError('Verbindungsfehler.');
            }).always(function () {
                setLoading(false);
            });
        });

        $('#mw-add-another').on('click', function () {
            $('#mw-success').hide();
            $form[0].reset();
            $titel.val(''); $interpret.val(''); $spotifyId.val(''); $spotifyUrl.val('');
            $results.empty();
            $form.fadeIn(300);
        });

        function setLoading(on) {
            $submit.prop('disabled', on);
            $submit.find('.mw-submit-text').toggle(!on);
            $submit.find('.mw-submit-loading').toggle(on);
        }
        function showError(msg) { $error.text(msg).fadeIn(200); }
        function hideError() { $error.hide(); }

        function escapeHtml(s) {
            return $('<div>').text(s).html();
        }
    });
}(jQuery));
