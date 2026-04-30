/**
 * Top Visited Posts — View Tracker
 *
 * Fires an AJAX request on single post pages to increment the view count.
 * Uses sessionStorage to avoid counting multiple views per session.
 */
(function () {
	'use strict';

	if ( typeof tvpTracker === 'undefined' ) {
		return;
	}

	var storageKey = 'tvp_viewed_' + tvpTracker.postId;

	// Only count once per session per post.
	try {
		if ( sessionStorage.getItem( storageKey ) ) {
			return;
		}
	} catch ( e ) {
		// sessionStorage not available; proceed anyway.
	}

	var data = new FormData();
	data.append( 'action', 'tvp_track_view' );
	data.append( 'post_id', tvpTracker.postId );
	data.append( 'nonce', tvpTracker.nonce );

	fetch( tvpTracker.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		body: data,
	}).then( function () {
		try {
			sessionStorage.setItem( storageKey, '1' );
		} catch ( e ) {
			// Silently fail if storage is unavailable.
		}
	});
})();
