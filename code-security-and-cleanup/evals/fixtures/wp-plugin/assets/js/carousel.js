( function () {
	var track = document.querySelector( '.shelfmark-carousel-track' );
	if ( ! track ) {
		return;
	}

	var index = 0;
	var slides = track.children;

	function show( next ) {
		index = ( next + slides.length ) % slides.length;
		track.style.transform = 'translateX(-' + index * 100 + '%)';
	}

	document.querySelector( '.shelfmark-carousel-prev' ).addEventListener( 'click', function () {
		show( index - 1 );
	} );
	document.querySelector( '.shelfmark-carousel-next' ).addEventListener( 'click', function () {
		show( index + 1 );
	} );
} )();
