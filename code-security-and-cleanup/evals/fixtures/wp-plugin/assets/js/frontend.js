import './polyfills.js';

const ajaxUrl = `${ window.location.origin }/wp-admin/admin-ajax.php`;

const results = document.querySelector( '.shelfmark-search-results' );
const term = new URLSearchParams( window.location.search ).get( 'q' );

if ( results && term ) {
	fetch( `${ ajaxUrl }?action=shelfmark_search&q=${ encodeURIComponent( term ) }` )
		.then( ( response ) => response.json() )
		.then( ( payload ) => {
			for ( const row of payload.data ?? [] ) {
				const item = document.createElement( 'li' );
				item.textContent = row.post_title;
				results.append( item );
			}
		} );
}

const books = document.querySelectorAll( '.shelfmark-book' );

if ( books.length > 0 ) {
	fetch( `${ ajaxUrl }?action=shelfmark_featured` )
		.then( ( response ) => response.json() )
		.then( ( payload ) => {
			const featured = new Set( ( payload.data ?? [] ).map( String ) );
			for ( const book of books ) {
				if ( featured.has( book.dataset.bookId ) ) {
					book.classList.add( 'is-featured' );
				}
			}
		} );
}
