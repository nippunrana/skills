( function () {
	var settingsForm = document.getElementById( 'shelfmark-settings-form' );
	var importForm = document.getElementById( 'shelfmark-import-form' );

	function post( action, nonce, form ) {
		var body = new FormData( form );
		body.append( 'action', action );
		body.append( 'nonce', nonce );
		return fetch( shelfmarkAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( response ) {
			return response.json();
		} );
	}

	if ( settingsForm ) {
		settingsForm.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			post( 'shelfmark_save_settings', shelfmarkAdmin.settingsNonce, settingsForm );
		} );
	}

	if ( importForm ) {
		importForm.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			post( 'shelfmark_import_cover', shelfmarkAdmin.importNonce, importForm ).then( function ( payload ) {
				var result = document.getElementById( 'shelfmark-import-result' );
				result.textContent = payload.success ? payload.data.image : payload.data.message;
			} );
		} );
	}
} )();
