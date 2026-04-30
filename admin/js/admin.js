/**
 * Top Visited Posts — Admin sortable lists.
 *
 * Makes both the post elements list and the order-by criteria list
 * drag-to-reorder, and syncs hidden inputs so only checked items submit.
 */
(function ( $ ) {
	'use strict';

	$( function () {
		// Initialise each sortable list.
		$( '#tvp-elements-sortable, #tvp-orderby-sortable' ).each( function () {
			var $list = $( this );

			$list.sortable({
				handle: '.tvp-drag-handle',
				axis: 'y',
				containment: 'parent',
				tolerance: 'pointer',
			});

			// Toggle the hidden input when a checkbox is checked/unchecked.
			$list.on( 'change', '.tvp-element-checkbox', function () {
				var $hidden = $( this ).closest( '.tvp-element-item' ).find( 'input[type="hidden"]' );
				if ( this.checked ) {
					$hidden.prop( 'disabled', false );
				} else {
					$hidden.prop( 'disabled', true );
				}
			});
		});
	});
})( jQuery );
