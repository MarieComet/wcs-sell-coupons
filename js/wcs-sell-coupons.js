(function($) {
	$(document).ready( function() {

		// listen on shipping method select to update fields displayed
	    $( '#wcs_send_method' ).on( 'change', function() {
	    	var shippingMethod = $( this ).val();
	    	var addressFriend = $( '.wcs-address-friend' );
	    	var addressFriendInput = $( '#wcs_address_friend' );
	    	var mailFriend = $( '.wcs-email-friend' );
	    	var mailFriendInput = $( '#wcs_email_friend' );

	    	switch( shippingMethod ) {
	    		case 'wcs_send_method_mail' :
	    			$( addressFriend ).css( 'display', 'block' );
	    			$( mailFriend ).css( 'display', 'none' );
	    			$( addressFriendInput ).attr( 'required', true );
	    			$( mailFriendInput ).attr( 'required', false );
	    			break;
	    		case 'wcs_send_method_pos' :
	    			$( addressFriend ).css( 'display', 'none' );
	    			$( mailFriend ).css( 'display', 'none' );
	    			$( mailFriendInput ).attr( 'required', false );
	    			$( addressFriendInput ).attr( 'required', false );
	    			break;
	    		default :
	    			$( addressFriend ).css( 'display', 'none' );
	    			$( mailFriend ).css( 'display', 'block' );
	    			$( addressFriendInput ).attr( 'required', false );
	    			$( mailFriendInput ).attr( 'required', true );
	    	}
	    } );
	});
})(jQuery);