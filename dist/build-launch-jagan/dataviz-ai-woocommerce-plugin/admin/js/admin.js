( function( $ ) {
	'use strict';

	let conversationHistory = [];
	let userScrolledUp = false;
	let shouldAutoScroll = true;
	let currentStreamController = null;
	let currentStreamReader = null;
	let streamStopped = false;
	let sessionId = '';

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

	/**
	 * Vetted starter questions: show chips when the textarea is focused/clicked (empty + enabled).
	 *
	 * @param {JQuery} $form Chat form.
	 * @param {JQuery} $input Question textarea.
	 * @return {void}
	 */
	function setupSuggestedPrompts( $form, $input ) {
		if ( typeof DatavizAIAdmin === 'undefined' || ! DatavizAIAdmin.hasApiKey || ! Array.isArray( DatavizAIAdmin.suggestedQuestions ) || DatavizAIAdmin.suggestedQuestions.length === 0 ) {
			return;
		}
		const $panel = $( '#dataviz-ai-suggested-prompts' );
		if ( ! $panel.length ) {
			return;
		}
		DatavizAIAdmin.suggestedQuestions.forEach( function( text ) {
			if ( typeof text !== 'string' || ! $.trim( text ) ) {
				return;
			}
			const $btn = $( '<button type="button" class="dataviz-ai-suggested-chip"></button>' );
			$btn.text( text ).attr( 'aria-label', text ).attr( 'data-prompt', text );
			$panel.append( $btn );
		} );
		function hideSuggested() {
			$panel.prop( 'hidden', true );
		}
		function showSuggestedIfEligible() {
			if ( $input.prop( 'disabled' ) ) {
				hideSuggested();
				return;
			}
			const v = $input.val();
			if ( typeof v === 'string' && $.trim( v ) !== '' ) {
				hideSuggested();
				return;
			}
			$panel.prop( 'hidden', false );
		}
		$input.on( 'focusin', showSuggestedIfEligible );
		$input.on( 'click', showSuggestedIfEligible );
		$input.on( 'input', function() {
			if ( $.trim( $input.val() ) !== '' ) {
				hideSuggested();
			} else {
				showSuggestedIfEligible();
			}
		} );
		$input.on( 'blur', function() {
			window.setTimeout( function() {
				const active = document.activeElement;
				if ( $panel[0] && active && ( $panel[0] === active || $.contains( $panel[0], active ) ) ) {
					return;
				}
				hideSuggested();
			}, 180 );
		} );
		$panel.on( 'mousedown', 'button', function( e ) {
			e.preventDefault();
		} );
		$panel.on( 'click', 'button', function() {
			const q = $( this ).attr( 'data-prompt' ) || $( this ).text();
			$input.val( q );
			autoResizeTextarea( $input );
			hideSuggested();
			$form.trigger( 'submit' );
		} );
	}

	// Add message to chat
	function addMessage( text, type, forceScroll = true, toolData = null ) {
		const $messages = $( '#dataviz-ai-chat-messages' );
		const $welcome = $messages.find( '.dataviz-ai-chat-welcome' );
		
		if ( $welcome.length && conversationHistory.length === 0 ) {
			$welcome.fadeOut( 300, function() {
				$( this ).remove();
			} );
		}

		const messageClass = type === 'user' ? 'dataviz-ai-message--user' : 'dataviz-ai-message--ai';
		const $message = $( '<div class="dataviz-ai-message ' + messageClass + '"></div>' );
		const $content = $( '<div class="dataviz-ai-message-content"></div>' );
		
		const formattedText = text.replace( /\n/g, '<br>' );
		$content.html( formattedText );

		$message.append( $content );
		$messages.append( $message );

		if ( type === 'ai' && toolData && toolData.chart_descriptor ) {
			maybeRenderChart( toolData, $message );
		}

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
		const $status = $( '<div class="dataviz-ai-message-loading-status" aria-live="polite">Starting your query...</div>' );
		
		$dots.append( '<span></span><span></span><span></span>' );
		$content.append( $dots );
		$content.append( $status );
		$loading.append( $content );
		$messages.append( $loading );
		
		scrollToBottom();
		
		return $loading;
	}

	function createLongWaitStatusUpdater( $loadingMessage ) {
		if ( ! $loadingMessage || ! $loadingMessage.length ) {
			return { stop: function() {} };
		}

		const startedAt = Date.now();
		const $status = $loadingMessage.find( '.dataviz-ai-message-loading-status' );
		const tick = function() {
			const elapsed = Math.floor( ( Date.now() - startedAt ) / 1000 );
			if ( elapsed >= 20 ) {
				$status.text( 'Still working... This can take up to a minute for larger stores.' );
			} else if ( elapsed >= 12 ) {
				$status.text( 'Still loading your answer... querying WooCommerce data.' );
			} else if ( elapsed >= 6 ) {
				$status.text( 'Working on it... this is taking a bit longer than usual.' );
			} else {
				$status.text( 'Starting your query...' );
			}
		};

		tick();
		const intervalId = window.setInterval( tick, 1000 );

		return {
			stop: function() {
				window.clearInterval( intervalId );
			},
		};
	}

	// Remove loading indicator
	function removeLoadingMessage( $loading ) {
		$loading.fadeOut( 300, function() {
			$( this ).remove();
		} );
	}

	// ------------------------------------------------------------------
	// Chart rendering from backend descriptor (no question parsing)
	// ------------------------------------------------------------------

	var chartPalette = [
		'#6366f1', '#8b5cf6', '#a78bfa',
		'#10b981', '#059669', '#047857',
		'#f59e0b', '#d97706', '#b45309',
		'#ef4444', '#dc2626', '#b91c1c',
		'#3b82f6', '#2563eb', '#1d4ed8',
	];

	function renderChartFromDescriptor( descriptor, $container ) {
		if ( ! descriptor || typeof Chart === 'undefined' ) {
			return;
		}

		if ( $container.find( '.dataviz-ai-chart-wrapper' ).length > 0 ) {
			return;
		}

		var labels   = descriptor.labels || [];
		var datasets = descriptor.datasets || [];
		if ( ! labels.length || ! datasets.length || ! datasets[0].data || ! datasets[0].data.length ) {
			return;
		}

		var $wrapper = $( '<div class="dataviz-ai-chart-wrapper"></div>' );
		if ( descriptor.title ) {
			$wrapper.append( '<div class="dataviz-ai-chart-title">' + $( '<span>' ).text( descriptor.title ).html() + '</div>' );
		}
		var canvas = document.createElement( 'canvas' );
		$wrapper.append( canvas );
		$container.append( $wrapper );

		var chartType = descriptor.chart_type || 'bar';
		var isCurrency = descriptor.format === 'currency';
		var isHorizontal = chartType === 'horizontalBar';
		var cjsType = isHorizontal ? 'bar' : chartType;

		var cjsDatasets = datasets.map( function( ds, idx ) {
			var base = {
				label: ds.label || '',
				data: ds.data,
			};
			if ( cjsType === 'pie' ) {
				base.backgroundColor = chartPalette.slice( 0, labels.length );
			} else if ( cjsType === 'line' ) {
				base.borderColor = chartPalette[ idx ] || '#6366f1';
				base.backgroundColor = ( chartPalette[ idx ] || '#6366f1' ) + '22';
				base.fill = true;
				base.tension = 0.3;
				base.pointRadius = 3;
			} else {
				base.backgroundColor = chartPalette[ idx ] || '#6366f1';
				base.borderColor = ( chartPalette[ idx ] || '#6366f1' );
				base.borderWidth = 1;
				base.borderRadius = 4;
			}
			return base;
		} );

		var tooltipCallback = {};
		if ( isCurrency ) {
			tooltipCallback = {
				label: function( ctx ) {
					var val = ctx.parsed !== undefined ? ( typeof ctx.parsed === 'object' ? ( isHorizontal ? ctx.parsed.x : ctx.parsed.y ) : ctx.parsed ) : ctx.raw;
					return ( ctx.dataset.label || '' ) + ': $' + parseFloat( val ).toLocaleString( undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
				}
			};
		}

		var scales = {};
		if ( cjsType !== 'pie' ) {
			var xCfg = { display: true };
			var yCfg = { display: true, beginAtZero: true };
			if ( descriptor.x_axis_label ) xCfg.title = { display: true, text: descriptor.x_axis_label };
			if ( descriptor.y_axis_label ) yCfg.title = { display: true, text: descriptor.y_axis_label };
			if ( isCurrency ) {
				var currencyAxis = isHorizontal ? xCfg : yCfg;
				currencyAxis.ticks = { callback: function( v ) { return '$' + v.toLocaleString(); } };
			}
			if ( isHorizontal ) {
				xCfg.beginAtZero = true;
			}
			scales = { x: xCfg, y: yCfg };
		}

		var options = {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				title: { display: false },
				legend: { display: cjsType === 'pie', position: 'bottom' },
				tooltip: { callbacks: tooltipCallback },
			},
			scales: scales,
		};

		if ( isHorizontal ) {
			options.indexAxis = 'y';
		}

		new Chart( canvas, {
			type: cjsType,
			data: { labels: labels, datasets: cjsDatasets },
			options: options,
		} );
	}

	function maybeRenderChart( streamToolData, $messageContainer ) {
		if ( streamToolData && streamToolData.chart_descriptor ) {
			setTimeout( function() {
				renderChartFromDescriptor( streamToolData.chart_descriptor, $messageContainer );
				scrollToBottom( true );
			}, 200 );
		}
	}

	// Stop the current stream
	function stopStreaming() {
		streamStopped = true;
		
		if ( currentStreamController ) {
			currentStreamController.abort();
			currentStreamController = null;
		}
		if ( currentStreamReader ) {
			currentStreamReader.cancel();
			currentStreamReader = null;
		}
		
		// Hide stop button, show send button
		const $stopButton = $( '.dataviz-ai-chat-stop' );
		const $sendButton = $( '.dataviz-ai-chat-send' );
		const $input = $( '#dataviz-ai-question' );
		
		$stopButton.removeClass( 'show' ).hide();
		$sendButton.show();
		$input.prop( 'disabled', false );
		$sendButton.prop( 'disabled', false );
		$input.focus();
	}

	$( document ).ready( function() {
		const $form = $( '.dataviz-ai-chat-form' );
		const $input = $( '#dataviz-ai-question' );
		const $sendButton = $( '.dataviz-ai-chat-send' );
		const $stopButton = $( '.dataviz-ai-chat-stop' );

		// Setup scroll monitoring to detect user scroll
		setupScrollMonitoring();

		setupSuggestedPrompts( $form, $input );

		// Enable/disable send button based on API key availability
		if ( typeof DatavizAIAdmin !== 'undefined' ) {
			if ( ! DatavizAIAdmin.hasApiKey ) {
				$sendButton.prop( 'disabled', true );
				$input.prop( 'disabled', true );
			}
		}

		// Handle stop button click (use event delegation in case button is dynamically shown/hidden)
		$( document ).on( 'click', '.dataviz-ai-chat-stop', function() {
			stopStreaming();
		} );

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

		// Disable input and send button, show stop button
		$input.prop( 'disabled', true );
		$sendButton.prop( 'disabled', true );
		$sendButton.hide();
		
		const $stopButton = $( '.dataviz-ai-chat-stop' );
		if ( $stopButton.length ) {
			$stopButton.addClass( 'show' ).show();
		}

		// Keep a visible loading state while waiting for stream chunks.
		const $loadingMessage = showLoadingMessage();
		const loadingStatusUpdater = createLongWaitStatusUpdater( $loadingMessage );
		let loadingRemoved = false;
		function finalizeLoadingState() {
			if ( loadingRemoved ) {
				return;
			}
			loadingRemoved = true;
			loadingStatusUpdater.stop();
			removeLoadingMessage( $loadingMessage );
		}
		
		// Reset stream stopped flag
		streamStopped = false;
		
		// Create AI message container for streaming
		const $aiMessage = addMessage( '', 'ai' );
		const $aiContent = $aiMessage.find( '.dataviz-ai-message-content' );
		let fullResponse = '';

		// Ensure session ID is set (should already be set from page load, but double-check)
		if ( ! sessionId ) {
			initializeSessionId();
		}
		
		console.log( 'Sending message with session ID:', sessionId );

		// Prepare form data
		const formData = new FormData();
		formData.append( 'action', 'dataviz_ai_analyze' );
		formData.append( 'nonce', DatavizAIAdmin.nonce );
		formData.append( 'question', question );
		formData.append( 'stream', 'true' );
		formData.append( 'session_id', sessionId );

		// Create AbortController for cancellation
		currentStreamController = new AbortController();

		// Use fetch with streaming
		fetch( DatavizAIAdmin.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
			signal: currentStreamController.signal,
		} )
			.then( function( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Network response was not ok' );
				}

				const reader = response.body.getReader();
				currentStreamReader = reader;
				const decoder = new TextDecoder();
				let buffer = '';
				let streamToolData = null; // Store tool data from stream for chart rendering

				function readChunk() {
					return reader.read().then( function( result ) {
						// Check if stream was stopped
						if ( streamStopped ) {
							reader.cancel();
							currentStreamReader = null;
							currentStreamController = null;
							return;
						}

						if ( result.done ) {
							// Stream complete
							currentStreamReader = null;
							currentStreamController = null;
							finalizeLoadingState();
							
							if ( ! streamStopped && fullResponse.trim() ) {
								conversationHistory.push( { role: 'assistant', content: fullResponse } );
								// Note: Chart rendering is handled when processing [DONE] line below
							}
							
							// Hide stop button, show send button
							$stopButton.removeClass( 'show' ).hide();
							$sendButton.show();
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
								const dataStr = line.substring( 6 ).trim();

								try {
									const data = JSON.parse( dataStr );
									if ( data.done === true ) {
										finalizeLoadingState();
										if ( data.tool_data ) {
											streamToolData = data.tool_data;
										}
										currentStreamReader = null;
										currentStreamController = null;
										if ( ! streamStopped && fullResponse.trim() ) {
											conversationHistory.push( { role: 'assistant', content: fullResponse } );
											maybeRenderChart( streamToolData, $aiMessage );
										}
										$stopButton.removeClass( 'show' ).hide();
										$sendButton.show();
										$input.prop( 'disabled', false );
										$sendButton.prop( 'disabled', false );
										$input.focus();
										return;
									}

									if ( data.error ) {
										finalizeLoadingState();
										$aiContent.html( '<span style="color: #d63638;">' + data.error + '</span>' );
										$stopButton.removeClass( 'show' ).hide();
										$sendButton.show();
										$input.prop( 'disabled', false );
										$sendButton.prop( 'disabled', false );
										$input.focus();
										currentStreamReader = null;
										currentStreamController = null;
										return;
									}

									if ( data.tool_data ) {
										streamToolData = data.tool_data;
									}

									if ( data.chunk && ! streamStopped ) {
										finalizeLoadingState();
										fullResponse += data.chunk;
										const formattedText = fullResponse.replace( /\n/g, '<br>' );
										$aiContent.html( formattedText );
										scrollToBottom();
									}
								} catch ( e ) {
									if ( dataStr === '[DONE]' || streamStopped ) {
										currentStreamReader = null;
										currentStreamController = null;
										finalizeLoadingState();
										if ( ! streamStopped && fullResponse.trim() ) {
											conversationHistory.push( { role: 'assistant', content: fullResponse } );
											maybeRenderChart( streamToolData, $aiMessage );
										}
										$stopButton.removeClass( 'show' ).hide();
										$sendButton.show();
										$input.prop( 'disabled', false );
										$sendButton.prop( 'disabled', false );
										$input.focus();
										return;
									}
								}
							}
						} );

						return readChunk();
					} );
				}

				return readChunk();
			} )
			.catch( function( error ) {
				// Ignore abort errors (user stopped the stream)
				if ( error.name === 'AbortError' || streamStopped ) {
					streamStopped = true;
					finalizeLoadingState();
					if ( fullResponse.trim() ) {
						// Add "(stopped)" indicator
						const formattedText = fullResponse.replace( /\n/g, '<br>' ) + '<br><br><em style="color: #646970; font-size: 0.9em;">Response stopped by user.</em>';
						$aiContent.html( formattedText );
					} else {
						$aiContent.html( '<em style="color: #646970;">Response stopped.</em>' );
					}
				} else {
					finalizeLoadingState();
					const errorMessage = 'An unexpected error occurred. Please try again.';
					$aiContent.html( '<span style="color: #d63638;">' + errorMessage + '</span>' );
				}
				
				currentStreamReader = null;
				currentStreamController = null;
				
				// Hide stop button, show send button
				const $stopButton = $( '.dataviz-ai-chat-stop' );
				const $sendButton = $( '.dataviz-ai-chat-send' );
				const $input = $( '#dataviz-ai-question' );
				$stopButton.removeClass( 'show' ).hide();
				$sendButton.show();
				$input.prop( 'disabled', false );
				$sendButton.prop( 'disabled', false );
				$input.focus();
			} );
	} );

	// Initialize session ID on page load
	function initializeSessionId() {
		// Get session ID - prefer server-side (user meta) which persists across logins
		// Fall back to localStorage, then generate new one
		if ( DatavizAIAdmin.userSessionId ) {
			sessionId = DatavizAIAdmin.userSessionId;
			// Sync to localStorage for consistency
			localStorage.setItem( 'dataviz_ai_session_id', sessionId );
		} else {
			sessionId = localStorage.getItem( 'dataviz_ai_session_id' ) || '';
			if ( ! sessionId ) {
				// Generate a simple session ID (UUID-like)
				sessionId = 'session_' + Date.now() + '_' + Math.random().toString( 36 ).substr( 2, 9 );
				localStorage.setItem( 'dataviz_ai_session_id', sessionId );
			}
		}
		console.log( 'Session ID initialized:', sessionId );
	}

	// Load chat history on page load
	function loadChatHistory() {
		// Ensure session ID is initialized
		if ( ! sessionId ) {
			initializeSessionId();
		}

		// Fetch ALL chat history for the user (across all sessions) from last 5 days
		// This ensures history persists across logout/login
		console.log( 'Loading chat history...' );
		console.log( 'AJAX URL:', DatavizAIAdmin.ajaxUrl );
		console.log( 'Session ID:', sessionId );
		
		$.ajax( {
			url: DatavizAIAdmin.ajaxUrl,
			method: 'GET',
			data: {
				action: 'dataviz_ai_get_history',
				nonce: DatavizAIAdmin.nonce,
				all_sessions: true, // Get all sessions for this user
				limit: 200, // Get up to 200 messages
				days: 5, // Only get messages from last 5 days
			},
			success: function( response ) {
				console.log( 'Chat history response:', response );
				
				if ( response.success && response.data && response.data.history ) {
					const history = response.data.history;
					console.log( 'Found', history.length, 'messages in history' );
					
					const $messages = $( '#dataviz-ai-chat-messages' );
					
					if ( ! $messages.length ) {
						console.error( 'Chat messages container #dataviz-ai-chat-messages not found' );
						return;
					}

					console.log( 'Chat messages container found' );
					const $welcome = $messages.find( '.dataviz-ai-chat-welcome' );

					if ( history.length > 0 ) {
						console.log( 'Displaying', history.length, 'history messages' );
						// Hide welcome message immediately
						if ( $welcome.length ) {
							$welcome.fadeOut( 200, function() {
								$( this ).remove();
							} );
						}

						// Don't clear existing messages - just ensure welcome is hidden
						// This allows history to load properly

						// Add messages to conversation history first (so welcome message logic works)
						history.forEach( function( msg ) {
							const messageType = msg.message_type === 'user' ? 'user' : 'ai';
							const messageContent = msg.message_content || '';
							
							if ( messageContent.trim() ) {
								if ( messageType === 'user' ) {
									conversationHistory.push( { role: 'user', content: messageContent } );
								} else {
									conversationHistory.push( { role: 'assistant', content: messageContent } );
								}
							}
						} );

						// Now display history messages in chronological order
						history.forEach( function( msg ) {
							const messageType = msg.message_type === 'user' ? 'user' : 'ai';
							const messageContent = msg.message_content || '';
							
							if ( messageContent.trim() ) {
								// Add message without forcing scroll (we'll scroll at the end)
								addMessage( messageContent, messageType, false );
							}
						} );

						// Scroll to bottom after all messages are loaded
						setTimeout( function() {
							scrollToBottom( true );
						}, 300 );
					} else {
						// No history found - keep welcome message visible
						console.log( 'No chat history found for the last 5 days' );
					}
				} else {
					console.log( 'No chat history in response:', response );
				}
			},
			error: function( xhr, status, error ) {
				// Log error for debugging
				console.error( 'Error loading chat history:', status, error );
				console.error( 'Response:', xhr.responseText );
			}
		} );
	}

	// Initialize on document ready
	$( document ).ready( function() {
		// Setup scroll monitoring first
		setupScrollMonitoring();
		
		// Initialize session ID immediately
		if ( typeof DatavizAIAdmin !== 'undefined' ) {
			initializeSessionId();
		}
		
		// Load chat history after DOM is fully ready
		// Use a longer delay to ensure all scripts are loaded
		setTimeout( function() {
			if ( typeof DatavizAIAdmin !== 'undefined' ) {
				loadChatHistory();
			} else {
				console.error( 'DatavizAIAdmin not defined - chat history will not load' );
			}
		}, 500 );
	} );
}( window.jQuery ) );
