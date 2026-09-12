/**
 * Reports the public form page height to the embedding parent window.
 *
 * Only runs inside a frame, and only ever sends the height — there is no
 * reverse channel from the parent. See assets/embed.js for the consumer.
 */
( function () {
	'use strict';

	if ( window.parent === window ) {
		return;
	}

	const settings = window.fformsPublicForm || {};
	const formId = parseInt( settings.formId, 10 );
	if ( ! formId ) {
		return;
	}

	let last = 0;

	/**
	 * Measure the content itself rather than documentElement, whose box can be
	 * pinned to the frame's viewport and would then report the wrong height.
	 *
	 * @return {number} Content height in pixels.
	 */
	function measure() {
		const content = document.querySelector( '.fforms-embed__content' );
		if ( ! content ) {
			return Math.ceil( document.body.scrollHeight );
		}
		const top = document.documentElement.getBoundingClientRect().top;
		return Math.ceil( content.getBoundingClientRect().bottom - top );
	}

	function report() {
		const height = measure();
		if ( ! height ) {
			return;
		}
		// Re-send an unchanged height while the frame still does not match it:
		// the parent may have sized us before the layout settled, and a repeat
		// message is the only way that gets corrected.
		if ( height === last && Math.abs( window.innerHeight - height ) <= 1 ) {
			return;
		}
		last = height;
		window.parent.postMessage(
			{ type: 'fforms:height', formId, height },
			'*'
		);
	}

	if ( window.ResizeObserver ) {
		const observer = new window.ResizeObserver( report );
		observer.observe( document.body );
		const content = document.querySelector( '.fforms-embed__content' );
		if ( content ) {
			observer.observe( content );
		}
	}
	window.addEventListener( 'load', report );
	window.addEventListener( 'resize', report );
	document.addEventListener( 'fforms:resize', report );

	// Late web fonts and block styles can shift the layout after load.
	[ 300, 1000, 2500 ].forEach( ( delay ) => setTimeout( report, delay ) );
	report();
} )();
