( function( $ ) {
	'use strict';

	let conversationHistory = [];
	let userScrolledUp = false;
	let shouldAutoScroll = true;

	// Auto-resize textarea
	function autoResizeTextarea( $textarea ) {
		$textarea.css( 'height', 'auto' );
		$textarea.css( 'height', Math.min( $textarea[0].scrollHeight, 150 ) + 'px' );
	}

	// Check if user is near bottom of messages
	function isNearBottom( $messages, threshold = 100 ) {
		const element = $messages[0];
		const scrollTop = element.scrollTop;
		const scrollHeight = element.scrollHeight;
		const clientHeight = element.clientHeight;
		const distanceFromBottom = scrollHeight - scrollTop - clientHeight;
		return distanceFromBottom < threshold;
	}

	// Scroll messages to bottom (only if user hasn't scrolled up)
	function scrollToBottom( force = false ) {
		const $messages = $( '#dataviz-ai-chat-messages' );
		
		// If user has scrolled up and we're not forcing, don't auto-scroll
		if ( ! force && userScrolledUp && ! isNearBottom( $messages ) ) {
			return;
		}

		// Reset user scroll flag if we're near bottom
		if ( isNearBottom( $messages, 50 ) ) {
			userScrolledUp = false;
			shouldAutoScroll = true;
		}

		// Stop any existing scroll animations
		$messages.stop( true, false );

		// For streaming updates (not forced), use instant scroll to avoid animation queue
		if ( ! force ) {
			$messages.scrollTop( $messages[0].scrollHeight );
		} else {
			// For new messages, use smooth animation
			$messages.animate( { scrollTop: $messages[0].scrollHeight }, 300 );
		}
	}

	// Monitor user scroll behavior
	function setupScrollMonitoring() {
		const $messages = $( '#dataviz-ai-chat-messages' );
		
		$messages.on( 'scroll', function() {
			if ( ! isNearBottom( $( this ), 50 ) ) {
				userScrolledUp = true;
				shouldAutoScroll = false;
			} else {
				userScrolledUp = false;
				shouldAutoScroll = true;
			}
		} );
	}

	// Add message to chat
	function addMessage( text, type, forceScroll = true ) {
		const $messages = $( '#dataviz-ai-chat-messages' );
		const $welcome = $messages.find( '.dataviz-ai-chat-welcome' );
		
		// Hide welcome message after first message
		if ( $welcome.length && conversationHistory.length === 0 ) {
			$welcome.fadeOut( 300, function() {
				$( this ).remove();
			} );
		}

		const messageClass = type === 'user' ? 'dataviz-ai-message--user' : 'dataviz-ai-message--ai';
		const $message = $( '<div class="dataviz-ai-message ' + messageClass + '"></div>' );
		const $content = $( '<div class="dataviz-ai-message-content"></div>' );
		
		// Format text (preserve line breaks)
		const formattedText = text.replace( /\n/g, '<br>' );
		$content.html( formattedText );

		$message.append( $content );
		$messages.append( $message );
		
		// Only scroll if forced (new messages) or if user is near bottom
		scrollToBottom( forceScroll );
		
		return $message;
	}

	// Show loading indicator
	function showLoadingMessage() {
		const $messages = $( '#dataviz-ai-chat-messages' );
		const $welcome = $messages.find( '.dataviz-ai-chat-welcome' );
		
		if ( $welcome.length && conversationHistory.length === 0 ) {
			$welcome.fadeOut( 300, function() {
				$( this ).remove();
			} );
		}

		const $loading = $( '<div class="dataviz-ai-message dataviz-ai-message--ai dataviz-ai-message--loading"></div>' );
		const $content = $( '<div class="dataviz-ai-message-content"></div>' );
		const $dots = $( '<div class="dataviz-ai-message-loading-dots"></div>' );
		
		$dots.append( '<span></span><span></span><span></span>' );
		$content.append( $dots );
		$loading.append( $content );
		$messages.append( $loading );
		
		scrollToBottom();
		
		return $loading;
	}

	// Remove loading indicator
	function removeLoadingMessage( $loading ) {
		$loading.fadeOut( 300, function() {
			$( this ).remove();
		} );
	}

	$( document ).ready( function() {
		const $form = $( '.dataviz-ai-chat-form' );
		const $input = $( '#dataviz-ai-question' );
		const $sendButton = $( '.dataviz-ai-chat-send' );

		// Setup scroll monitoring to detect user scroll
		setupScrollMonitoring();

		// Enable/disable send button based on API key availability
		if ( typeof DatavizAIAdmin !== 'undefined' ) {
			if ( ! DatavizAIAdmin.hasApiKey ) {
				$sendButton.prop( 'disabled', true );
				$input.prop( 'disabled', true );
			}
		}

		// Auto-resize textarea on input
		$input.on( 'input', function() {
			autoResizeTextarea( $( this ) );
		} );

		// Handle Enter key (Shift+Enter for new line, Enter to send)
		$input.on( 'keydown', function( e ) {
			if ( e.key === 'Enter' && ! e.shiftKey ) {
				e.preventDefault();
				if ( ! $sendButton.prop( 'disabled' ) && $( this ).val().trim() ) {
					$form.submit();
				}
			}
		} );

		// Focus input on load
		if ( $sendButton.prop( 'disabled' ) === false ) {
			$input.focus();
		}
	} );

	// Handle chat form submission
	$( document ).on( 'submit', '.dataviz-ai-chat-form', function( event ) {
		event.preventDefault();

		const $form = $( this );
		const $input = $( '#dataviz-ai-question' );
		const $sendButton = $( '.dataviz-ai-chat-send' );
		const question = $input.val().trim();

		if ( ! question ) {
			return;
		}

		// Reset scroll flags when user sends a new message
		userScrolledUp = false;
		shouldAutoScroll = true;

		// Add user message (force scroll for new message)
		addMessage( question, 'user' );
		conversationHistory.push( { role: 'user', content: question } );

		// Clear input and reset height
		$input.val( '' );
		$input.css( 'height', 'auto' );

		// Disable input and button
		$input.prop( 'disabled', true );
		$sendButton.prop( 'disabled', true );

		// Remove loading indicator and create streaming message
		removeLoadingMessage( showLoadingMessage() );
		
		// Create AI message container for streaming
		const $aiMessage = addMessage( '', 'ai' );
		const $aiContent = $aiMessage.find( '.dataviz-ai-message-content' );
		let fullResponse = '';

		// Prepare form data
		const formData = new FormData();
		formData.append( 'action', 'dataviz_ai_analyze' );
		formData.append( 'nonce', DatavizAIAdmin.nonce );
		formData.append( 'question', question );
		formData.append( 'stream', 'true' );

		// Use fetch with streaming
		fetch( DatavizAIAdmin.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		} )
			.then( function( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Network response was not ok' );
				}

				const reader = response.body.getReader();
				const decoder = new TextDecoder();
				let buffer = '';

				function readChunk() {
					return reader.read().then( function( result ) {
						if ( result.done ) {
							// Stream complete
							conversationHistory.push( { role: 'assistant', content: fullResponse } );
							$input.prop( 'disabled', false );
							$sendButton.prop( 'disabled', false );
							$input.focus();
							return;
						}

						buffer += decoder.decode( result.value, { stream: true } );
						const lines = buffer.split( '\n\n' );
						buffer = lines.pop() || ''; // Keep incomplete line in buffer

						lines.forEach( function( line ) {
							if ( line.startsWith( 'data: ' ) ) {
								const dataStr = line.substring( 6 );
								
								if ( dataStr === '[DONE]' ) {
									conversationHistory.push( { role: 'assistant', content: fullResponse } );
									$input.prop( 'disabled', false );
									$sendButton.prop( 'disabled', false );
									$input.focus();
									return;
								}

								try {
									const data = JSON.parse( dataStr );
									
									if ( data.error ) {
										$aiContent.html( '<span style="color: #d63638;">' + data.error + '</span>' );
										$input.prop( 'disabled', false );
										$sendButton.prop( 'disabled', false );
										$input.focus();
										return;
									}

									if ( data.chunk ) {
										fullResponse += data.chunk;
										// Update message content with accumulated text
										const formattedText = fullResponse.replace( /\n/g, '<br>' );
										$aiContent.html( formattedText );
										scrollToBottom();
									}
								} catch ( e ) {
									// Ignore JSON parse errors for incomplete chunks
								}
							}
						} );

						return readChunk();
					} );
				}

				return readChunk();
			} )
			.catch( function( error ) {
				const errorMessage = 'An unexpected error occurred. Please try again.';
				$aiContent.html( '<span style="color: #d63638;">' + errorMessage + '</span>' );
				$input.prop( 'disabled', false );
				$sendButton.prop( 'disabled', false );
				$input.focus();
			} );
	} );
}( window.jQuery ) );
