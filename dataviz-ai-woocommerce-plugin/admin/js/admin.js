( function( $ ) {
	'use strict';

	$( document ).ready( function() {
		// Enable/disable the Run Analysis button based on API key availability.
		if ( typeof DatavizAIAdmin !== 'undefined' ) {
			const $button = $( '.dataviz-ai-analysis-form button[type="submit"]' );
			if ( $button.length ) {
				if ( DatavizAIAdmin.hasApiKey ) {
					$button.prop( 'disabled', false ).removeAttr( 'disabled' );
				} else {
					$button.prop( 'disabled', true );
				}
			}
		}
		const $chartCanvas = $( '#dataviz-ai-orders-chart' );

		if ( $chartCanvas.length && typeof DatavizAIAdmin !== 'undefined' && Array.isArray( DatavizAIAdmin.recentOrders ) ) {
			const ctx = $chartCanvas[0].getContext( '2d' );
			const labels = DatavizAIAdmin.recentOrders.map( ( order ) => `#${ order.id }` );
			const data = DatavizAIAdmin.recentOrders.map( ( order ) => order.total );

			if ( ! labels.length || typeof window.Chart === 'undefined' ) {
				$chartCanvas.hide();
				return;
			}

			new window.Chart( ctx, {
				type: 'pie',
				data: {
					labels,
					datasets: [
						{
							label: 'Order Totals',
							data,
							backgroundColor: [ '#3b82f6', '#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#f97316' ],
						},
					],
				},
				options: {
					plugins: {
						legend: {
							position: 'bottom',
						},
					},
				},
			} );
		}
	} );

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
					const data = response.data;
					
					// Extract answer text if available, otherwise format the response nicely.
					if ( data.answer ) {
						$output.text( data.answer );
					} else if ( typeof data === 'string' ) {
						$output.text( data );
					} else if ( data.message ) {
						$output.text( data.message );
					} else {
						// Fallback: show formatted JSON for debugging.
						$output.text( JSON.stringify( data, null, 2 ) );
					}
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

