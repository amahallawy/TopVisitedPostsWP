/**
 * Top Visited Posts — Scroll to Post
 *
 * 1. On the target page, scans post cards from any source (Spectra Post Grid,
 *    Spectra Loop Builder, WP Core Query Loop, standard theme templates) and
 *    injects id="tvp-post-{id}" anchors so the hash-based scroll can find them.
 * 2. When a URL contains a #tvp-post-{id} hash, scrolls to the matching
 *    element and centers it on screen with a highlight pulse.
 */
(function () {
	'use strict';

	/**
	 * Inject tvp-post-{id} anchors onto post cards.
	 *
	 * Uses the tvpScroll.postMap object (permalink → post_id) passed from PHP.
	 * Supports multiple rendering engines by scanning a broad set of container
	 * selectors and matching the first link inside each against the permalink map.
	 */
	function injectAnchors() {
		if ( typeof tvpScroll === 'undefined' || ! tvpScroll.postMap ) {
			return;
		}

		var map = tvpScroll.postMap; // { permalink: postId, ... }

		// Normalise a URL so trailing-slash differences don't break matching.
		function normalise( url ) {
			try {
				var u = new URL( url, window.location.origin );
				return u.pathname.replace( /\/+$/, '' );
			} catch ( e ) {
				return url.replace( /\/+$/, '' );
			}
		}

		// Build a lookup keyed by normalised pathname.
		var lookup = {};
		for ( var permalink in map ) {
			if ( Object.prototype.hasOwnProperty.call( map, permalink ) ) {
				lookup[ normalise( permalink ) ] = map[ permalink ];
			}
		}

		// --- Strategy 1: Permalink-based matching ---
		// Broad selector covering all known post-card containers:
		//   - Spectra Post Grid:   article.uagb-post__inner-wrap
		//   - Spectra Loop Builder: .uagb-loop-post
		//   - WP Core Query Loop:  li.wp-block-post
		//   - Generic WP themes:   article[class*="post-"]  (handled in strategy 2)
		var cards = document.querySelectorAll( [
			'article.uagb-post__inner-wrap',
			'.uagb-post__inner-wrap',
			'.uagb-loop-post',
			'li.wp-block-post'
		].join( ', ' ) );

		for ( var i = 0; i < cards.length; i++ ) {
			var card = cards[ i ];
			if ( card.id && card.id.indexOf( 'tvp-post-' ) === 0 ) {
				continue; // Already tagged.
			}
			var link = card.querySelector( 'a[href]' );
			if ( ! link ) {
				continue;
			}
			var key = normalise( link.href );
			if ( lookup[ key ] ) {
				var postId = parseInt( lookup[ key ], 10 );
				if ( postId > 0 ) {
					card.id = 'tvp-post-' + postId;
				}
				delete lookup[ key ];
			}
		}

		// --- Strategy 2: Class-based matching for standard WP themes ---
		// WP themes use post_class() which outputs <article class="post-{id} ...">.
		// Match by extracting the ID from the class name directly.
		if ( Object.keys( lookup ).length > 0 ) {
			var articles = document.querySelectorAll( 'article[class*="post-"]' );
			for ( var j = 0; j < articles.length; j++ ) {
				var article = articles[ j ];
				if ( article.id && article.id.indexOf( 'tvp-post-' ) === 0 ) {
					continue;
				}
				var match = article.className.match( /(?:^|\s)post-(\d+)(?:\s|$)/ );
				if ( match ) {
					var pid = match[1];
					for ( var href in lookup ) {
						if ( Object.prototype.hasOwnProperty.call( lookup, href ) && String( lookup[ href ] ) === pid ) {
							article.id = 'tvp-post-' + pid;
							delete lookup[ href ];
							break;
						}
					}
				}
			}
		}

		// --- Strategy 3: Fallback permalink scan ---
		// For any remaining unmatched posts, scan ALL links on the page
		// and tag the nearest block-level ancestor.
		if ( Object.keys( lookup ).length > 0 ) {
			var allLinks = document.querySelectorAll( '#content a[href], #main a[href], .site-main a[href], .entry-content a[href]' );
			for ( var k = 0; k < allLinks.length; k++ ) {
				var aEl = allLinks[ k ];
				var aKey = normalise( aEl.href );
				if ( ! lookup[ aKey ] ) {
					continue;
				}
				// Walk up to find a suitable container (div, li, article, section)
				// that isn't the entire content area.
				var container = aEl.parentElement;
				while ( container ) {
					var tag = container.tagName;
					if (
						( tag === 'ARTICLE' || tag === 'LI' || tag === 'DIV' || tag === 'SECTION' ) &&
						! container.id &&
						container.id !== 'content' &&
						container.id !== 'main' &&
						! container.classList.contains( 'site-main' ) &&
						! container.classList.contains( 'entry-content' )
					) {
						var fpid = parseInt( lookup[ aKey ], 10 );
						if ( fpid > 0 ) {
							container.id = 'tvp-post-' + fpid;
						}
						delete lookup[ aKey ];
						break;
					}
					container = container.parentElement;
				}
				if ( Object.keys( lookup ).length === 0 ) {
					break;
				}
			}
		}
	}

	/**
	 * Scroll to the target element and apply a highlight effect.
	 */
	function scrollToTarget() {
		var hash = window.location.hash;

		if ( ! hash || hash.indexOf( '#tvp-post-' ) !== 0 ) {
			return;
		}

		var targetId = hash.substring( 1 ); // Remove the '#'.
		var target   = document.getElementById( targetId );

		if ( ! target ) {
			return;
		}

		// Small delay to ensure DOM is fully rendered.
		setTimeout( function () {
			target.scrollIntoView({
				behavior: 'smooth',
				block: 'center',
			});

			// Apply highlight animation.
			target.classList.add( 'tvp-scroll-highlight' );

			// Remove class after animation completes.
			target.addEventListener( 'animationend', function handler() {
				target.classList.remove( 'tvp-scroll-highlight' );
				target.removeEventListener( 'animationend', handler );
			});
		}, 300 );
	}

	/**
	 * Main initialisation: inject anchors first, then scroll.
	 */
	function init() {
		injectAnchors();
		scrollToTarget();
	}

	// Run on page load.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Also handle in-page hash changes.
	window.addEventListener( 'hashchange', scrollToTarget );
})();
