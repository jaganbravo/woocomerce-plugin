( function( $ ) {
	'use strict';

	function appendMessage( $container, text, type ) {
		const $message = $( '<div />', {
			class: 'message message--' + type,
			text,
		} );

		$container.append( $message );
		$container.scrollTop( $container[0].scrollHeight );
	}

	$( document ).on( 'submit', '.dataviz-ai-chat-form', function( event ) {
		event.preventDefault();

		const $form = $( this );
		const $widget = $form.closest( '.dataviz-ai-chat-widget' );
		const $messages = $widget.find( '.dataviz-ai-chat-messages' );
		const $textarea = $form.find( 'textarea[name="message"]' );
		const message = $textarea.val().trim();

		if ( ! DatavizAIChat.connected ) {
			appendMessage( $messages, DatavizAIChat.strings.disconnected, 'assistant' );
			return;
		}

		if ( ! message ) {
			return;
		}

		appendMessage( $messages, message, 'user' );
		$textarea.val( '' );

		appendMessage( $messages, '…', 'assistant' );
		const $loading = $messages.children().last();

		$.post(
			DatavizAIChat.ajaxUrl,
			{
				action: 'dataviz_ai_chat',
				nonce: DatavizAIChat.nonce,
				message,
			}
		)
			.done( function( response ) {
				$loading.remove();
				if ( response.success && response.data ) {
					const text = response.data.message || JSON.stringify( response.data, null, 2 );
					appendMessage( $messages, text, 'assistant' );
				} else {
					appendMessage( $messages, response.data?.message || DatavizAIChat.strings.error_generic, 'assistant' );
				}
			} )
			.fail( function( jqXHR ) {
				$loading.remove();
				const message = jqXHR.responseJSON?.data?.message || jqXHR.responseJSON?.message;
				appendMessage( $messages, message || DatavizAIChat.strings.error_generic, 'assistant' );
			} );
	} );
}( window.jQuery ) );

