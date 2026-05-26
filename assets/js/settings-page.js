/**
 * Settings admin page — outbound webhook "Send test ping" button.
 *
 * Configuration is injected from PHP via wp_add_inline_script() as a JSON
 * blob assigned to window.cmcpSettingsConfig:
 *   { ajaxUrl, nonce, i18n: { sending, delivered, failed } }
 *
 * @package WPCommander
 */
( function () {
    'use strict';

    var btn = document.getElementById( 'cmcp-webhook-test' );
    if ( ! btn ) { return; }

    var cfg     = window.cmcpSettingsConfig || {};
    var ajaxUrl = cfg.ajaxUrl || '';
    var nonce   = cfg.nonce   || '';
    var i18n    = cfg.i18n    || { sending: 'Sending…', delivered: 'Delivered:', failed: 'Failed:' };

    var out = document.getElementById( 'cmcp-webhook-test-result' );

    btn.addEventListener( 'click', function () {
        if ( ! out ) { return; }
        out.textContent = i18n.sending;
        out.style.color = '#646970';

        var data = new URLSearchParams();
        data.set( 'action', 'cmcp_test_webhook' );
        data.set( '_nonce', nonce );

        fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
            .then( function ( r ) { return r.json(); } )
            .then( function ( resp ) {
                var d  = ( resp && resp.data ) || {};
                var ok = resp && resp.success && d.ok;
                out.textContent = ok
                    ? i18n.delivered + ' ' + ( d.message || 'OK' )
                    : i18n.failed    + ' ' + ( d.message || 'error' );
                out.style.color = ok ? '#0a6041' : '#9b1c1c';
            } )
            .catch( function ( err ) {
                out.textContent = 'Error: ' + err;
                out.style.color = '#9b1c1c';
            } );
    } );
} )();
