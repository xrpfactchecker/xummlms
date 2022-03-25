(function( $ ) {
	'use strict';

	$(function() {
		$( 'a.xlms-payout-retry' ).click(function(e) {
			return confirm('Are you sure?');
		});	
	});

})( jQuery );
