( function( $ ) {
	'use strict';

	let conversationHistory = [];
	let userScrolledUp = false;
	let shouldAutoScroll = true;
	let currentStreamController = null;
	let currentStreamReader = null;
	let streamStopped = false;

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
	function addMessage( text, type, forceScroll = true, questionForChart = null ) {
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
		
		// Render charts if this is an AI response and question mentions charts
		if ( type === 'ai' && questionForChart && mentionsChart( questionForChart ) ) {
			setTimeout( function() {
				renderChartForQuestion( questionForChart, $message );
				scrollToBottom( forceScroll );
			}, 100 );
		}
		
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

	// Check if question mentions charts
	function mentionsChart( text ) {
		const chartKeywords = [ 'chart', 'pie', 'bar', 'graph', 'visualize', 'visualization', 'plot', 'show me', 'display' ];
		const lowerText = text.toLowerCase();
		return chartKeywords.some( keyword => lowerText.includes( keyword ) );
	}

	// Detect chart type from question
	function detectChartType( question ) {
		const lowerQuestion = question.toLowerCase();
		if ( lowerQuestion.includes( 'pie' ) ) {
			return 'pie';
		} else if ( lowerQuestion.includes( 'bar' ) ) {
			return 'bar';
		}
		// Default based on context
		return 'pie'; // Default to pie chart
	}

	// Detect what data to chart from question
	function detectChartData( question ) {
		const lowerQuestion = question.toLowerCase();
		if ( lowerQuestion.includes( 'order' ) || lowerQuestion.includes( 'sale' ) || lowerQuestion.includes( 'revenue' ) ) {
			return 'orders';
		} else if ( lowerQuestion.includes( 'product' ) || lowerQuestion.includes( 'item' ) ) {
			return 'products';
		}
		return 'orders'; // Default to orders
	}

	// Render pie chart
	function renderPieChart( $container, data, labels, title ) {
		if ( typeof Chart === 'undefined' ) {
			return;
		}

		const canvas = document.createElement( 'canvas' );
		$container.append( canvas );

		new Chart( canvas, {
			type: 'pie',
			data: {
				labels: labels,
				datasets: [ {
					data: data,
					backgroundColor: [
						'#2271b1',
						'#135e96',
						'#0a4b78',
						'#10b981',
						'#059669',
						'#047857',
						'#f59e0b',
						'#d97706',
						'#b45309',
						'#ef4444',
						'#dc2626',
						'#b91c1c',
						'#8b5cf6',
						'#7c3aed',
						'#6d28d9',
					],
				} ],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					title: {
						display: !!title,
						text: title || '',
					},
					legend: {
						position: 'bottom',
					},
				},
			},
		} );
	}

	// Render bar chart
	function renderBarChart( $container, data, labels, title, yAxisLabel = 'Value' ) {
		if ( typeof Chart === 'undefined' ) {
			return;
		}

		const canvas = document.createElement( 'canvas' );
		$container.append( canvas );

		new Chart( canvas, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [ {
					label: yAxisLabel,
					data: data,
					backgroundColor: '#2271b1',
					borderColor: '#135e96',
					borderWidth: 1,
				} ],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					title: {
						display: !!title,
						text: title || '',
					},
					legend: {
						display: false,
					},
				},
				scales: {
					y: {
						beginAtZero: true,
					},
				},
			},
		} );
	}

	// Render chart based on question and data
	function renderChartForQuestion( question, $messageContainer ) {
		if ( ! mentionsChart( question ) || typeof DatavizAIAdmin === 'undefined' ) {
			return;
		}

		const chartType = detectChartType( question );
		const dataType = detectChartData( question );

		// Create chart container
		const $chartContainer = $( '<div class="dataviz-ai-chart-wrapper"></div>' );
		$messageContainer.append( $chartContainer );

		if ( dataType === 'orders' && DatavizAIAdmin.orderChartData && DatavizAIAdmin.orderChartData.length > 0 ) {
			const orders = DatavizAIAdmin.orderChartData;
			
			if ( chartType === 'pie' ) {
				// Order status pie chart
				const statusCounts = {};
				orders.forEach( function( order ) {
					const status = order.status || 'unknown';
					statusCounts[ status ] = ( statusCounts[ status ] || 0 ) + 1;
				} );

				const labels = Object.keys( statusCounts );
				const data = Object.values( statusCounts );

				if ( labels.length > 0 ) {
					renderPieChart( $chartContainer, data, labels, 'Order Status Distribution' );
				}
			} else if ( chartType === 'bar' ) {
				// Order totals bar chart (top 10)
				const sortedOrders = orders
					.filter( o => o.total > 0 )
					.sort( ( a, b ) => b.total - a.total )
					.slice( 0, 10 );

				const labels = sortedOrders.map( o => 'Order #' + o.id );
				const data = sortedOrders.map( o => o.total );

				if ( labels.length > 0 ) {
					renderBarChart( $chartContainer, data, labels, 'Top Orders by Value', 'Total ($)' );
				}
			}
		} else if ( dataType === 'products' && DatavizAIAdmin.productChartData && DatavizAIAdmin.productChartData.length > 0 ) {
			const products = DatavizAIAdmin.productChartData;
			
			if ( chartType === 'pie' ) {
				// Product sales pie chart (top 8)
				const topProducts = products
					.filter( p => p.sales > 0 )
					.sort( ( a, b ) => b.sales - a.sales )
					.slice( 0, 8 );

				const labels = topProducts.map( p => p.name.length > 20 ? p.name.substring( 0, 20 ) + '...' : p.name );
				const data = topProducts.map( p => p.sales );

				if ( labels.length > 0 ) {
					renderPieChart( $chartContainer, data, labels, 'Top Products by Sales' );
				}
			} else if ( chartType === 'bar' ) {
				// Product sales bar chart
				const topProducts = products
					.filter( p => p.sales > 0 )
					.sort( ( a, b ) => b.sales - a.sales )
					.slice( 0, 10 );

				const labels = topProducts.map( p => p.name.length > 15 ? p.name.substring( 0, 15 ) + '...' : p.name );
				const data = topProducts.map( p => p.sales );

				if ( labels.length > 0 ) {
					renderBarChart( $chartContainer, data, labels, 'Top Products by Sales', 'Units Sold' );
				}
			}
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

		// Remove loading indicator and create streaming message
		removeLoadingMessage( showLoadingMessage() );
		
		// Reset stream stopped flag
		streamStopped = false;
		
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
							
							if ( ! streamStopped && fullResponse.trim() ) {
								conversationHistory.push( { role: 'assistant', content: fullResponse } );
								
								// Render charts if question mentions charts
								if ( mentionsChart( question ) ) {
									setTimeout( function() {
										renderChartForQuestion( question, $aiMessage );
									}, 300 );
								}
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
								const dataStr = line.substring( 6 );
								
								if ( dataStr === '[DONE]' || streamStopped ) {
									currentStreamReader = null;
									currentStreamController = null;
									
									if ( ! streamStopped && fullResponse.trim() ) {
										conversationHistory.push( { role: 'assistant', content: fullResponse } );
									}
									
									// Hide stop button, show send button
									$stopButton.removeClass( 'show' ).hide();
									$sendButton.show();
									$input.prop( 'disabled', false );
									$sendButton.prop( 'disabled', false );
									$input.focus();
									return;
								}

								try {
									const data = JSON.parse( dataStr );
									
									if ( data.error ) {
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

									if ( data.chunk && ! streamStopped ) {
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
				// Ignore abort errors (user stopped the stream)
				if ( error.name === 'AbortError' || streamStopped ) {
					streamStopped = true;
					if ( fullResponse.trim() ) {
						// Add "(stopped)" indicator
						const formattedText = fullResponse.replace( /\n/g, '<br>' ) + '<br><br><em style="color: #646970; font-size: 0.9em;">Response stopped by user.</em>';
						$aiContent.html( formattedText );
					} else {
						$aiContent.html( '<em style="color: #646970;">Response stopped.</em>' );
					}
				} else {
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
}( window.jQuery ) );
