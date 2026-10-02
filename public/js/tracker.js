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

	fetch( tvpTracker.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		body: data,
	}).then( function ( response ) {
		return response.ok ? response.json() : null;
	}).then( function ( result ) {
		// Only remember the view once the server accepted it, so a failed
		// request is retried on the next page load in this session.
		if ( ! result || ! result.success ) {
			return;
		}
		try {
			sessionStorage.setItem( storageKey, '1' );
		} catch ( e ) {
			// Silently fail if storage is unavailable.
		}
	}).catch( function () {
		// Network error or non-JSON reply: leave the view unrecorded so it is retried.
	});
})();
