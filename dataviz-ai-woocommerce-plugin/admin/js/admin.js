( function( $ ) {
	'use strict';

	$( document ).on( 'submit', '.dataviz-ai-admin form[data-action="analyze"]', function( event ) {
		event.preventDefault();

		const $form = $( this );
		const $button = $form.find( 'button[type="submit"]' );
		const $output = $form.find( '.dataviz-ai-analysis-output' );
		const question = $form.find( 'textarea[name="question"]' ).val();

		$button.prop( 'disabled', true );
		$output.text( '' );

		$.post(
			DatavizAIAdmin.ajaxUrl,
			{
				action: 'dataviz_ai_analyze',
				nonce: DatavizAIAdmin.nonce,
				question,
			}
		)
			.done( function( response ) {
				if ( response.success ) {
					$output.text( JSON.stringify( response.data, null, 2 ) );
				} else if ( response.data && response.data.message ) {
					$output.text( response.data.message );
				}
			} )
			.fail( function( jqXHR ) {
				const message = jqXHR.responseJSON?.data?.message || jqXHR.responseJSON?.message;
				$output.text( message || 'An unexpected error occurred.' );
			} )
			.always( function() {
				$button.prop( 'disabled', false );
			} );
	} );
}( window.jQuery ) );

