//*****************************************************************************************************************************************
// reusable buttons
//*****************************************************************************************************************************************
function pmmButtonOn( pclass ) {
	jQuery( 'p.' + pclass + ' input.pmm-button' ).removeAttr( 'disabled' );
}

function pmmButtonOff( pclass ) {
	jQuery( 'p.' + pclass + ' input.pmm-button' ).attr( 'disabled', 'disabled' );
}

//*****************************************************************************************************************************************
// display a message
//*****************************************************************************************************************************************
function pmmDisplayMessages( msgAnchor, msgClass, msgText ) {

	// show the message
	jQuery( msgAnchor ).after( '<div id="message" class="' + msgClass + '"><p>' + msgText + '</p></div>' );

	// delay then hide up
	jQuery( 'div.pmm-message' ).delay( 3000 ).slideUp( 'slow' );
}

//*****************************************************************************************************************************************
// clear any existing settings
//*****************************************************************************************************************************************
function pmmClearMessages() {
	jQuery( 'div#wpbody div#message' ).remove();
	jQuery( 'div#wpbody div#setting-error-settings_updated' ).remove();
}

//*****************************************************************************************************************************************
// start the engine
//*****************************************************************************************************************************************
jQuery(document).ready( function($) {

//*****************************************************************************************************************************************
// set some vars
//*****************************************************************************************************************************************
	var keyOld  = '';
	var keyNew  = '';
	var keyKill = '';
	var mTable  = '';
	var nonce   = '';

//*****************************************************************************************************************************************
// change meta key
//*****************************************************************************************************************************************
	$( 'p.process' ).on('click', 'input#pmm-change', function () {

		// remove any existing messages
		pmmClearMessages();

		// disable the button
		pmmButtonOff( 'change-process' );

		// get ajax nonce
		nonce = $( this ).data( 'nonce' );

		// get our table name
		mTable  = $( this ).data( 'tablename' );

		// get the values
		keyOld  = $( '#meta-key-old' ).prop( 'value' );
		keyNew  = $( '#meta-key-new' ).prop( 'value' );

		var data = {
			action: 'key_change',
			keyold: keyOld,
			keynew: keyNew,
			table:  mTable,
			nonce: nonce
		};

		jQuery.post(ajaxurl, data, function( response ) {

			pmmButtonOn( 'change-process' );

			var obj;

			try {
				obj = jQuery.parseJSON(response);
			}
			catch(e) {
				pmmDisplayMessages( 'div#wpbody h2:first', 'error below-h2 pmm-message', pmmAjaxData.genError );
			}

			// it worked. clear the field and show the message
			if( obj.success === true ) {
				// clear the field
				$( 'input.meta-key-field' ).val( '' );
				// handle the message
				pmmDisplayMessages( 'div#wpbody h2:first', 'updated below-h2 pmm-message', obj.message );
			}

			else if( obj.success === false && obj.message !== '' ) {
				pmmDisplayMessages( 'div#wpbody h2:first', 'error below-h2 pmm-message', obj.message );
			}

			else {
				pmmDisplayMessages( 'div#wpbody h2:first', 'error below-h2 pmm-message', pmmAjaxData.genError );
			}

		});
	});

//*****************************************************************************************************************************************
// delete meta key
//*****************************************************************************************************************************************
	$( 'p.process' ).on('click', 'input#pmm-remove', function (event) {

		// remove any existing messages
		pmmClearMessages();

		// disable the button
		pmmButtonOff( 'remove-process' );

		// get ajax nonce
		nonce = $( this ).data( 'nonce' );

		// get our table name
		mTable  = $( this ).data( 'tablename' );

		// get the values
		keyKill = $( '#meta-key-kill' ).prop( 'value' );

		var data = {
			action:     'key_delete',
			keykill:    keyKill,
			table:      mTable,
			nonce:      nonce
		};

		jQuery.post(ajaxurl, data, function(response) {

			pmmButtonOn('remove-process');

			var obj;

			try {
				obj = jQuery.parseJSON(response);
			}
			catch(e) {
				pmmDisplayMessages( 'div#wpbody h2:first', 'error below-h2 pmm-message', pmmAjaxData.genError );
			}

			if( obj.success === true ) {
				// clear the field
				$( 'input.meta-key-field' ).val( '' );
				// handle the message
				pmmDisplayMessages( 'div#wpbody h2:first', 'updated below-h2 pmm-message', obj.message );
			}

			else if( obj.success === false && obj.message !== '' ) {
				pmmDisplayMessages( 'div#wpbody h2:first', 'error below-h2 pmm-message', obj.message );
			}

			else {
				pmmDisplayMessages( 'div#wpbody h2:first', 'error below-h2 pmm-message', pmmAjaxData.genError );
			}
		});
	});

//*****************************************************************************************************************************************
// what, you're still here? it's over. go home.
//*****************************************************************************************************************************************
});