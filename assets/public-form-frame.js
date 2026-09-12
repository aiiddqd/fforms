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

	function report() {
		const height = Math.ceil(
			document.documentElement.getBoundingClientRect().height
		);
		if ( ! height || height === last ) {
			return;
		}
		last = height;
		window.parent.postMessage(
			{ type: 'fforms:height', formId, height },
			'*'
		);
	}

	if ( window.ResizeObserver ) {
		new window.ResizeObserver( report ).observe( document.documentElement );
	}
	window.addEventListener( 'load', report );
	window.addEventListener( 'resize', report );
	document.addEventListener( 'fforms:resize', report );
	report();
} )();
