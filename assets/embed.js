/**
 * FForms external embed.
 *
 * Runs on third-party sites, so it stays dependency-free and build-free, defines no
 * globals and tolerates several embeds of different forms on one page.
 *
 * Usage:
 *   <script src=".../assets/embed.js" data-fforms-form="123" data-fforms-origin="https://example.com"></script>
 */
( function () {
	'use strict';

	const MESSAGE_TYPE = 'fforms:height';

	function currentScript() {
		if ( document.currentScript ) {
			return document.currentScript;
		}
		// Older browsers: the running script is the last one parsed so far.
		const scripts = document.getElementsByTagName( 'script' );
		for ( let i = scripts.length - 1; i >= 0; i-- ) {
			if (
				scripts[ i ].getAttribute( 'data-fforms-form' ) &&
				! scripts[ i ].getAttribute( 'data-fforms-done' )
			) {
				return scripts[ i ];
			}
		}
		return null;
	}

	function originFrom( script ) {
		const explicit = script.getAttribute( 'data-fforms-origin' );
		if ( explicit ) {
			return explicit.replace( /\/+$/, '' );
		}
		const src = script.getAttribute( 'src' ) || '';
		const match = src.match( /^(https?:\/\/[^/]+)/ );
		return match ? match[ 1 ] : '';
	}

	const script = currentScript();
	if ( ! script ) {
		return;
	}

	const formId = parseInt( script.getAttribute( 'data-fforms-form' ), 10 );
	const origin = originFrom( script );
	if ( ! formId || ! origin ) {
		return;
	}
	script.setAttribute( 'data-fforms-done', '1' );

	const iframe = document.createElement( 'iframe' );
	// The snippet carries the exact embed URL; the fallback only matters for a
	// hand-written tag. fforms_embed=1 asks for the bare form document, without
	// the site's header and footer.
	iframe.src =
		script.getAttribute( 'data-fforms-src' ) ||
		origin + '/forms/' + formId + '?fforms_embed=1';
	iframe.title = script.getAttribute( 'data-fforms-title' ) || 'Form';
	iframe.loading = 'lazy';
	iframe.style.width = '100%';
	iframe.style.border = '0';
	iframe.style.display = 'block';
	iframe.height = script.getAttribute( 'data-fforms-height' ) || '600';
	iframe.setAttribute( 'scrolling', 'no' );

	if ( script.parentNode ) {
		script.parentNode.insertBefore( iframe, script );
	}

	window.addEventListener( 'message', function ( event ) {
		if ( ! iframe.contentWindow || event.source !== iframe.contentWindow ) {
			return;
		}
		const data = event.data;
		if (
			! data ||
			data.type !== MESSAGE_TYPE ||
			parseInt( data.formId, 10 ) !== formId
		) {
			return;
		}
		const height = parseInt( data.height, 10 );
		if ( height > 0 ) {
			iframe.height = String( height );
		}
	} );
} )();
