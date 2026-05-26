/**
 * Tokens admin page — copy buttons, user picker, snippet tabs, test-connection.
 *
 * Configuration is injected from PHP via wp_add_inline_script() as a JSON
 * blob assigned to window.cmcpTokensConfig:
 *   { ajaxUrl, nonce, i18n: { copied, testing } }
 *
 * @package WPCommander
 */
( function () {
    'use strict';

    var cfg     = window.cmcpTokensConfig || {};
    var ajaxUrl = cfg.ajaxUrl || '';
    var nonce   = cfg.nonce   || '';
    var i18n    = cfg.i18n    || { copied: 'Copied', testing: 'Testing…' };

    // Generic copy-to-clipboard for any [data-target] button.
    document.querySelectorAll( '.cmcp-copy' ).forEach( function ( btn ) {
        btn.addEventListener( 'click', function () {
            var sel  = btn.getAttribute( 'data-target' );
            var el   = sel ? document.querySelector( sel ) : null;
            var text = el ? el.textContent : '';
            if ( ! text ) { return; }
            if ( navigator.clipboard && navigator.clipboard.writeText ) {
                navigator.clipboard.writeText( text ).then( function () {
                    var original    = btn.textContent;
                    btn.textContent = '✓ ' + i18n.copied;
                    setTimeout( function () { btn.textContent = original; }, 1500 );
                } );
            } else {
                // Fallback: select text.
                var range = document.createRange();
                range.selectNode( el );
                window.getSelection().removeAllRanges();
                window.getSelection().addRange( range );
            }
        } );
    } );

    // "Bind to WP user" picker — sync dropdown with the number input, and show
    // a big warning if user_id ends up as 0.
    var sel  = document.getElementById( 'cmcp-user-select' );
    var inp  = document.getElementById( 'user_id' );
    var warn = document.getElementById( 'cmcp-user-warning' );
    function syncWarn() {
        if ( ! warn ) { return; }
        warn.style.display = ( parseInt( inp.value, 10 ) === 0 ) ? 'block' : 'none';
    }
    if ( sel && inp ) {
        // Hide manual input by default; show only when "Other" is picked.
        inp.style.display = 'none';
        sel.addEventListener( 'change', function () {
            var v = sel.value;
            if ( v === '-1' ) {
                inp.style.display = '';
                inp.value         = '';
                inp.focus();
            } else {
                inp.style.display = 'none';
                inp.value         = v;
            }
            syncWarn();
        } );
        inp.addEventListener( 'input', syncWarn );
        syncWarn();
    }

    // Tab switching for snippet panels.
    document.querySelectorAll( '.cmcp-tabs' ).forEach( function ( bar ) {
        var panels = bar.parentElement.querySelectorAll( '.cmcp-tab-panel' );
        bar.querySelectorAll( '.cmcp-tab' ).forEach( function ( btn ) {
            btn.addEventListener( 'click', function () {
                bar.querySelectorAll( '.cmcp-tab' ).forEach( function ( b ) { b.classList.remove( 'active' ); } );
                btn.classList.add( 'active' );
                panels.forEach( function ( p ) {
                    p.classList.toggle( 'active', p.getAttribute( 'data-tab' ) === btn.getAttribute( 'data-tab' ) );
                } );
            } );
        } );
    } );

    // Test-connection on a freshly-shown plaintext token.
    document.querySelectorAll( '.cmcp-test-new' ).forEach( function ( btn ) {
        btn.addEventListener( 'click', function () {
            var token  = btn.getAttribute( 'data-token' );
            var result = btn.closest( '.notice' ).querySelector( '.cmcp-test-result' );
            if ( ! token || ! result ) { return; }
            result.textContent = i18n.testing;
            result.style.color = '#646970';
            var data = new URLSearchParams();
            data.set( 'action', 'cmcp_test_token' );
            data.set( '_nonce', nonce );
            data.set( 'token',  token );
            fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( resp ) {
                    var d  = ( resp && resp.data ) || {};
                    var ok = resp && resp.success && d.ok;
                    result.textContent = d.message || ( ok ? 'OK' : 'Failed' );
                    result.style.color = ok ? '#0a6041' : '#9b1c1c';
                } )
                .catch( function ( err ) {
                    result.textContent = 'Error: ' + err;
                    result.style.color = '#9b1c1c';
                } );
        } );
    } );
} )();
