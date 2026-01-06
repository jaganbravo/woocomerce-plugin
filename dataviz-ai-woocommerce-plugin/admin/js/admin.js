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
		// Only trigger on explicit chart/visualization requests, not generic "show me" or "display"
		const chartKeywords = [ 'chart', 'pie', 'bar', 'graph', 'visualize', 'visualization', 'plot', 'diagram' ];
		const lowerText = text.toLowerCase();
		// Check if it's an explicit chart request (not just "show me" or "display")
		const hasChartKeyword = chartKeywords.some( keyword => lowerText.includes( keyword ) );
		// Also check for "show me" + "chart" combination, but not just "show me" alone
		const hasShowMeWithChart = lowerText.includes( 'show me' ) && ( lowerText.includes( 'chart' ) || lowerText.includes( 'graph' ) || lowerText.includes( 'visual' ) );
		return hasChartKeyword || hasShowMeWithChart;
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
		if ( lowerQuestion.includes( 'order' ) || lowerQuestion.includes( 'sale' ) || lowerQuestion.includes( 'revenue' ) || lowerQuestion.includes( 'transaction' ) ) {
			return 'orders';
		} else if ( lowerQuestion.includes( 'product' ) || lowerQuestion.includes( 'item' ) ) {
			return 'products';
		} else if ( lowerQuestion.includes( 'inventory' ) || lowerQuestion.includes( 'stock' ) ) {
			return 'inventory';
		} else if ( lowerQuestion.includes( 'coupon' ) || lowerQuestion.includes( 'discount' ) ) {
			return 'coupons';
		} else if ( lowerQuestion.includes( 'customer' ) || lowerQuestion.includes( 'buyer' ) ) {
			return 'customers';
		} else if ( lowerQuestion.includes( 'category' ) || lowerQuestion.includes( 'tag' ) ) {
			return 'categories';
		}
		// Return null if we can't determine the data type - don't default to orders
		return null;
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
	function renderChartForQuestion( question, $messageContainer, streamToolData = null ) {
		if ( ! mentionsChart( question ) || typeof DatavizAIAdmin === 'undefined' ) {
			return;
		}

		// Check if chart already exists in this container to prevent duplicates
		if ( $messageContainer.find( '.dataviz-ai-chart-wrapper' ).length > 0 ) {
			return; // Chart already rendered
		}

		const chartType = detectChartType( question );
		const dataType = detectChartData( question );

		// Don't render chart if we can't determine the data type or it's not a supported type
		if ( ! dataType || ( dataType !== 'orders' && dataType !== 'products' && dataType !== 'inventory' ) ) {
			return;
		}

		// Create chart container
		const $chartContainer = $( '<div class="dataviz-ai-chart-wrapper"></div>' );
		$messageContainer.append( $chartContainer );

		// Use streamToolData if available (from actual query), otherwise fall back to static data
		let orders = null;
		if ( dataType === 'orders' ) {
			if ( streamToolData && streamToolData.orders && streamToolData.orders.length > 0 ) {
				// Use data from actual tool response (most accurate)
				orders = streamToolData.orders;
			} else if ( DatavizAIAdmin.orderChartData && DatavizAIAdmin.orderChartData.length > 0 ) {
				// Fallback to static pre-loaded data
				orders = DatavizAIAdmin.orderChartData;
			}
		}

		if ( dataType === 'orders' && orders && orders.length > 0 ) {
			
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
		} else if ( dataType === 'inventory' ) {
			// Inventory chart - use data from stream if available, otherwise fetch via AJAX
			// streamToolData is passed as third parameter when called from stream completion
			if ( streamToolData && streamToolData.inventory && streamToolData.inventory.products ) {
				// Use data already fetched by LLM (no redundant AJAX call)
				renderInventoryChart( $chartContainer, chartType, streamToolData.inventory.products );
			} else {
				// Fallback: fetch data via AJAX if not in stream
				fetchInventoryForChart( $chartContainer, chartType, question );
			}
		}
	}

	// Render inventory chart from products data (shared by both stream and AJAX)
	function renderInventoryChart( $chartContainer, chartType, products ) {
		if ( chartType === 'pie' ) {
			// Inventory pie chart - group by stock status or stock ranges
			const stockGroups = {};
			
			products.forEach( function( product ) {
				const stockQty = product.stock_quantity;
				let group;
				
				if ( stockQty === null || stockQty === undefined ) {
					group = 'No Stock Management';
				} else if ( stockQty === 0 ) {
					group = 'Out of Stock';
				} else if ( stockQty < 10 ) {
					group = 'Low Stock (1-9)';
				} else if ( stockQty < 50 ) {
					group = 'Medium Stock (10-49)';
				} else {
					group = 'High Stock (50+)';
				}
				
				stockGroups[ group ] = ( stockGroups[ group ] || 0 ) + 1;
			} );
			
			const labels = Object.keys( stockGroups );
			const data = Object.values( stockGroups );
			
			if ( labels.length > 0 ) {
				renderPieChart( $chartContainer, data, labels, 'Inventory Distribution by Stock Level' );
			}
		} else if ( chartType === 'bar' ) {
			// Top products by stock quantity (bar chart)
			const productsWithStock = products
				.filter( p => p.stock_quantity !== null && p.stock_quantity !== undefined )
				.sort( ( a, b ) => b.stock_quantity - a.stock_quantity )
				.slice( 0, 10 );
			
			const labels = productsWithStock.map( p => p.name.length > 15 ? p.name.substring( 0, 15 ) + '...' : p.name );
			const data = productsWithStock.map( p => p.stock_quantity );
			
			if ( labels.length > 0 ) {
				renderBarChart( $chartContainer, data, labels, 'Top Products by Stock Quantity', 'Stock Qty' );
			}
		}
	}

	// Fetch inventory data for chart rendering (fallback if not in stream)
	function fetchInventoryForChart( $chartContainer, chartType, question ) {
		// Make AJAX call to get inventory data
		jQuery.ajax( {
			url: DatavizAIAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'dataviz_ai_get_inventory_chart',
				nonce: DatavizAIAdmin.nonce,
			},
			success: function( response ) {
				if ( response.success && response.data && response.data.products ) {
					renderInventoryChart( $chartContainer, chartType, response.data.products );
				}
			},
			error: function() {
				console.error( 'Failed to fetch inventory data for chart' );
			}
		} );
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
								const dataStr = line.substring( 6 );
								
								if ( dataStr === '[DONE]' || streamStopped ) {
									currentStreamReader = null;
									currentStreamController = null;
									
									if ( ! streamStopped && fullResponse.trim() ) {
										conversationHistory.push( { role: 'assistant', content: fullResponse } );
										
										// Render charts if question mentions charts, passing tool data
										if ( mentionsChart( question ) ) {
											setTimeout( function() {
												renderChartForQuestion( question, $aiMessage, streamToolData );
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

									// Store tool data from stream for chart rendering (if present)
									if ( data.tool_data ) {
										streamToolData = data.tool_data;
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
