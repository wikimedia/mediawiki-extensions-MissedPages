$( function () {
	// Infuse all the widgets for selecting redirect targets.
	$( '.mw-widget-titleInputWidget' ).each( function () {
		OO.ui.infuse( $( this ) );
	} );
} );
