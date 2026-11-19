function shelfmarkToggleSummary( button ) {
	var summary = button.nextElementSibling;
	if ( ! summary ) {
		return;
	}

	summary.classList.toggle( 'is-open' );
	button.setAttribute( 'aria-expanded', summary.classList.contains( 'is-open' ) ? 'true' : 'false' );
}
