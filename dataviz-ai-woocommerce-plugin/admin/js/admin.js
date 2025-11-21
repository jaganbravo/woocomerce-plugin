( function( $ ) {
	'use strict';

	$( document ).ready( function() {
		// Enable/disable the Ask AI button based on API key availability.
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
	} );

	$( document ).on( 'submit', '.dataviz-ai-admin form[data-action="analyze"]', function( event ) {
		event.preventDefault();

		const $form = $( this );
		const $button = $form.find( 'button[type="submit"]' );
		const $output = $form.find( '.dataviz-ai-analysis-output' );
		const $chartsContainer = $form.find( '.dataviz-ai-charts-container' );
		const question = $form.find( 'textarea[name="question"]' ).val();

		$button.prop( 'disabled', true );
		$output.text( '' );
		// Hide charts container on new question
		$chartsContainer.slideUp( 300 );
		$chartsContainer.empty();

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
					let answerText = '';
					if ( data.answer ) {
						answerText = data.answer;
					} else if ( typeof data === 'string' ) {
						answerText = data;
					} else if ( data.message ) {
						answerText = data.message;
					} else {
						// Fallback: show formatted JSON for debugging.
						answerText = JSON.stringify( data, null, 2 );
					}
					$output.text( answerText );

					// Try to extract structured data from AI response for interactive charts
					const extractedData = extractDataFromResponse( answerText, data );
					
					// Determine which chart to show based on question content and available data
					const chartType = determineRelevantChart( question );
					
					if ( chartType ) {
						// Show only the relevant chart
						$chartsContainer.slideDown( 300 );
						
						// Render interactive chart with extracted data if available
						if ( typeof window.Chart !== 'undefined' ) {
							if ( extractedData && extractedData.hasData ) {
								renderInteractiveChartFromData( $chartsContainer, extractedData, chartType );
							} else {
								renderSingleChart( $chartsContainer, chartType );
							}
						} else {
							// Wait for Chart.js to load
							setTimeout( function() {
								if ( typeof window.Chart !== 'undefined' ) {
									if ( extractedData && extractedData.hasData ) {
										renderInteractiveChartFromData( $chartsContainer, extractedData, chartType );
									} else {
										renderSingleChart( $chartsContainer, chartType );
									}
								}
							}, 500 );
						}
					} else {
						// No chart requested, but check if we should show one based on data
						// This handles cases where user asks for data but might benefit from visualization
						const autoChartType = determineChartFromData( question );
						if ( autoChartType ) {
							$chartsContainer.slideDown( 300 );
							if ( typeof window.Chart !== 'undefined' ) {
								if ( extractedData && extractedData.hasData ) {
									renderInteractiveChartFromData( $chartsContainer, extractedData, autoChartType );
								} else {
									renderSingleChart( $chartsContainer, autoChartType );
								}
							}
						}
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

	/**
	 * Determine chart type based on available data when no explicit chart request
	 */
	function determineChartFromData( question ) {
		const questionLower = question.toLowerCase().trim();
		
		// Check what data type is being asked about
		const wantsCustomer = questionLower.match( /customer|customers|client|clients|buyer|buyers/i );
		const wantsOrder = questionLower.match( /order|orders/i );
		const wantsProduct = questionLower.match( /product|products/i );
		
		// Check available data
		const hasCustomerData = Array.isArray( DatavizAIAdmin.customerChartData ) && DatavizAIAdmin.customerChartData.length > 0;
		const hasOrderData = Array.isArray( DatavizAIAdmin.orderChartData ) && DatavizAIAdmin.orderChartData.length > 0;
		const hasProductData = Array.isArray( DatavizAIAdmin.productChartData ) && DatavizAIAdmin.productChartData.length > 0;
		
		// If user asks about specific data type, show appropriate chart if data exists
		if ( wantsCustomer && hasCustomerData ) {
			return 'customer-spending-bar';
		}
		
		if ( wantsOrder && hasOrderData ) {
			// Check if asking about status
			if ( questionLower.match( /status|state/i ) ) {
				return 'order-status-pie';
			}
			// Check if asking about value/revenue
			if ( questionLower.match( /value|total|revenue|amount|worth/i ) ) {
				return 'order-value-bar';
			}
			// Default order chart
			return 'order-status-pie';
		}
		
		if ( wantsProduct && hasProductData ) {
			return 'product-sales-bar';
		}
		
		// If no specific request but data exists, don't auto-show chart
		// User explicitly didn't ask for chart, so respect that
		return null;
	}

	/**
	 * Extract structured data from AI response for chart generation
	 */
	function extractDataFromResponse( answerText, responseData ) {
		let extracted = {
			hasData: false,
			labels: [],
			values: [],
			title: '',
			type: 'bar'
		};

		// Try to find JSON data in the response
		const jsonMatch = answerText.match( /```json\s*([\s\S]*?)\s*```/ ) || answerText.match( /\{[\s\S]*\}/ );
		if ( jsonMatch ) {
			try {
				const jsonData = JSON.parse( jsonMatch[1] || jsonMatch[0] );
				if ( jsonData.labels && jsonData.values ) {
					extracted.labels = jsonData.labels;
					extracted.values = jsonData.values;
					extracted.title = jsonData.title || '';
					extracted.type = jsonData.type || 'bar';
					extracted.hasData = true;
					return extracted;
				}
			} catch ( e ) {
				// Not valid JSON, continue
			}
		}

		// Try to extract data from responseData if it has structured format
		if ( responseData && typeof responseData === 'object' ) {
			if ( responseData.chartData ) {
				extracted = Object.assign( extracted, responseData.chartData );
				extracted.hasData = true;
				return extracted;
			}
			
			// Check for common data patterns
			if ( responseData.data && Array.isArray( responseData.data ) ) {
				extracted.labels = responseData.data.map( function( item ) {
					return item.label || item.name || item.key || '';
				} );
				extracted.values = responseData.data.map( function( item ) {
					return parseFloat( item.value || item.count || item.total || 0 );
				} );
				extracted.title = responseData.title || '';
				extracted.hasData = extracted.labels.length > 0 && extracted.values.length > 0;
				return extracted;
			}
		}

		// Try to parse table-like data from text
		const tableMatch = answerText.match( /(\w+)\s*:\s*(\d+\.?\d*)/gi );
		if ( tableMatch && tableMatch.length > 0 ) {
			extracted.labels = [];
			extracted.values = [];
			tableMatch.forEach( function( match ) {
				const parts = match.split( ':' );
				if ( parts.length === 2 ) {
					extracted.labels.push( parts[0].trim() );
					extracted.values.push( parseFloat( parts[1].trim() ) );
				}
			} );
			extracted.hasData = extracted.labels.length > 0;
		}

		return extracted;
	}

	/**
	 * Render interactive chart from extracted data
	 */
	function renderInteractiveChartFromData( $container, data, preferredType ) {
		if ( typeof window.Chart === 'undefined' ) {
			return;
		}

		$container.empty();

		if ( ! data.hasData || data.labels.length === 0 ) {
			return;
		}

		// Determine chart type
		let chartType = preferredType || data.type || 'bar';
		if ( typeof chartType === 'string' && chartType.includes( '-' ) ) {
			// Extract base type from chart type string (e.g., 'order-status-pie' -> 'pie')
			chartType = chartType.split( '-' ).pop();
		}

		// Normalize chart type
		if ( chartType === 'pie' || chartType === 'doughnut' ) {
			chartType = 'pie';
		} else if ( chartType === 'line' ) {
			chartType = 'line';
		} else {
			chartType = 'bar';
		}

		const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
		const $chartTitle = $( '<h3>' + ( data.title || 'Data Visualization' ) + '</h3>' );
		const $canvas = $( '<canvas class="dataviz-ai-chart dataviz-ai-interactive-chart" id="chart-interactive-' + Date.now() + '"></canvas>' );
		$chartCard.append( $chartTitle ).append( $canvas );
		$container.append( $chartCard );

		const ctx = $canvas[0].getContext( '2d' );
		
		// Color palette for interactive charts
		const colors = [
			'#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
			'#14b8a6', '#f97316', '#ec4899', '#06b6d4', '#6366f1'
		];

		const chartConfig = {
			type: chartType,
			data: {
				labels: data.labels,
				datasets: [ {
					label: data.title || 'Value',
					data: data.values,
					backgroundColor: colors.slice( 0, data.labels.length ),
					borderColor: colors.slice( 0, data.labels.length ).map( function( c ) {
						// Darken color for border
						return c.replace( /#(\w{2})(\w{2})(\w{2})/, function( m, r, g, b ) {
							return '#' + [ r, g, b ].map( function( x ) {
								return Math.max( 0, parseInt( x, 16 ) - 20 ).toString( 16 ).padStart( 2, '0' );
							} ).join( '' );
						} );
					} ),
					borderWidth: 2,
				} ],
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				animation: {
					duration: 1000,
					easing: 'easeInOutQuart',
				},
				interaction: {
					intersect: false,
					mode: 'index',
				},
				plugins: {
					legend: {
						display: chartType === 'pie' || chartType === 'doughnut',
						position: 'bottom',
						onClick: function( e, legendItem ) {
							// Toggle data visibility on legend click
							const index = legendItem.datasetIndex;
							const chart = this.chart;
							const meta = chart.getDatasetMeta( index );
							meta.hidden = meta.hidden === null ? !chart.data.datasets[ index ].hidden : null;
							chart.update();
						},
					},
					tooltip: {
						enabled: true,
						backgroundColor: 'rgba(0, 0, 0, 0.8)',
						titleColor: '#fff',
						bodyColor: '#fff',
						borderColor: '#666',
						borderWidth: 1,
						padding: 12,
						displayColors: true,
						callbacks: {
							label: function( context ) {
								let label = context.dataset.label || '';
								if ( label ) {
									label += ': ';
								}
								if ( context.parsed.y !== null ) {
									label += typeof context.parsed.y === 'number' ? context.parsed.y.toFixed( 2 ) : context.parsed.y;
								} else if ( context.parsed !== null ) {
									label += typeof context.parsed === 'number' ? context.parsed.toFixed( 2 ) : context.parsed;
								}
								return label;
							},
						},
					},
				},
				onHover: function( event, activeElements ) {
					event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default';
				},
			},
		};

		// Add scales for bar and line charts
		if ( chartType === 'bar' || chartType === 'line' ) {
			chartConfig.options.scales = {
				y: {
					beginAtZero: true,
					grid: {
						color: 'rgba(0, 0, 0, 0.1)',
					},
					ticks: {
						callback: function( value ) {
							return typeof value === 'number' ? value.toFixed( 2 ) : value;
						},
					},
				},
				x: {
					grid: {
						display: false,
					},
				},
			};
		}

		const chart = new window.Chart( ctx, chartConfig );

		// Add click handler for interactivity
		$canvas.on( 'click', function( event ) {
			const points = chart.getElementsAtEventForMode( event, 'nearest', { intersect: true }, true );
			if ( points.length ) {
				const firstPoint = points[0];
				const label = chart.data.labels[ firstPoint.index ];
				const value = chart.data.datasets[ firstPoint.datasetIndex ].data[ firstPoint.index ];
				console.log( 'Clicked:', label, value );
				// You can add more interactive features here, like showing details in a modal
			}
		} );

		// Store chart instance for potential future interactions
		$canvas.data( 'chart', chart );
	}

	/**
	 * Determine which chart is most relevant based on the question
	 */
	function determineRelevantChart( question ) {
		const questionLower = question.toLowerCase().trim();
		
		// Check for explicit chart requests - if user asks for chart, show one
		const chartKeywords = [
			'chart', 'charts', 'graph', 'graphs', 'visualization', 'visualizations',
			'visualize', 'plot', 'plots', 'diagram', 'diagrams',
			'visual', 'graphic', 'picture', 'illustration'
		];
		
		const hasChartKeyword = chartKeywords.some( function( keyword ) {
			return questionLower.includes( keyword );
		} );
		
		if ( ! hasChartKeyword ) {
			return null; // No chart requested - show only data/text
		}
		
		// First, check for explicit chart type requests (pie, bar, line)
		const wantsPie = questionLower.match( /pie\s+chart|pie\s+graph|doughnut|pie/i );
		const wantsBar = questionLower.match( /bar\s+chart|bar\s+graph|bars|bar/i );
		const wantsLine = questionLower.match( /line\s+chart|line\s+graph|line\s+plot|line/i );
		const wantsScatter = questionLower.match( /scatter\s+chart|scatter\s+plot|scatter\s+graph|scatter/i );
		
		// Determine data type requested - prioritize exact matches
		const wantsCustomer = questionLower.match( /customer|customers|client|clients|buyer|buyers|user\s+information|customer\s+info|customer\s+data/i );
		const wantsOrderStatus = questionLower.match( /order\s+status|status\s+of\s+orders|order\s+state|order\s+statuses/i );
		const wantsProductSales = questionLower.match( /product\s+sales|sales\s+by\s+product|top\s+products|best\s+selling|product\s+performance/i );
		const wantsOrderValue = questionLower.match( /order\s+value|order\s+total|revenue|order\s+amount|sales\s+value|order\s+worth/i );
		const wantsOrders = questionLower.match( /\borders\b/i ) && ! wantsOrderStatus && ! wantsOrderValue; // Only if not already matched
		const wantsProducts = questionLower.match( /\bproducts\b/i ) && ! wantsProductSales; // Only if not already matched
		const wantsPriceVsSales = questionLower.match( /price\s+vs|price\s+and\s+sales|correlation|relationship\s+between\s+price|price\s+impact/i );
		const wantsTrend = questionLower.match( /trend|over\s+time|history|timeline|progression|growth|change\s+over/i );
		
		// Combine chart type with data type
		// Priority: Explicit chart type > Data type > Default
		// IMPORTANT: Customer requests should NEVER show order/product charts
		
		// Customer requests - highest priority to avoid showing wrong data
		if ( wantsCustomer ) {
			if ( wantsPie ) {
				return 'customer-spending-pie';
			}
			if ( wantsBar ) {
				return 'customer-spending-bar';
			}
			if ( wantsLine ) {
				return 'customer-spending-line';
			}
			// Default customer chart: spending bar
			return 'customer-spending-bar';
		}
		
		// Pie chart requests
		if ( wantsPie ) {
			if ( wantsOrderStatus ) {
				return 'order-status-pie';
			}
			if ( wantsProductSales ) {
				return 'product-sales-pie';
			}
			if ( wantsOrderValue ) {
				return 'order-value-pie';
			}
			if ( wantsOrders ) {
				return 'order-status-pie';
			}
			if ( wantsProducts ) {
				return 'product-sales-pie';
			}
			// Default pie: order status
			return 'order-status-pie';
		}
		
		// Bar chart requests
		if ( wantsBar ) {
			if ( wantsOrderStatus ) {
				return 'order-status-bar';
			}
			if ( wantsProductSales ) {
				return 'product-sales-bar';
			}
			if ( wantsOrderValue ) {
				return 'order-value-bar';
			}
			if ( wantsOrders ) {
				return 'order-value-bar';
			}
			if ( wantsProducts ) {
				return 'product-sales-bar';
			}
			// Default bar: product sales
			return 'product-sales-bar';
		}
		
		// Line chart requests
		if ( wantsLine ) {
			if ( wantsOrderStatus ) {
				return 'order-status-line';
			}
			if ( wantsProductSales ) {
				return 'product-sales-line';
			}
			if ( wantsOrderValue || wantsTrend ) {
				return 'order-value-line';
			}
			if ( wantsOrders ) {
				return 'order-value-line';
			}
			if ( wantsProducts ) {
				return 'product-sales-line';
			}
			// Default line: order value trend
			return 'order-value-line';
		}
		
		// Scatter chart requests
		if ( wantsScatter || wantsPriceVsSales ) {
			return 'price-sales-scatter';
		}
		
		// No explicit chart type, determine by data type with smart defaults
		// Check for customer FIRST to avoid showing wrong charts
		if ( wantsCustomer ) {
			return 'customer-spending-bar';
		}
		
		if ( wantsOrderStatus ) {
			return 'order-status-pie'; // Default to pie for status
		}
		
		if ( wantsProductSales ) {
			return 'product-sales-bar'; // Default to bar for sales
		}
		
		if ( wantsOrderValue ) {
			if ( wantsTrend ) {
				return 'order-value-line';
			}
			return 'order-value-bar'; // Default to bar for values
		}
		
		if ( wantsOrders ) {
			return 'order-status-pie';
		}
		
		if ( wantsProducts ) {
			return 'product-sales-bar';
		}
		
		if ( wantsTrend ) {
			return 'order-value-line';
		}
		
		// User asked for chart but didn't specify data type - show a useful default
		// Prioritize order status as it's most commonly requested
		if ( Array.isArray( DatavizAIAdmin.orderChartData ) && DatavizAIAdmin.orderChartData.length > 0 ) {
			return 'order-status-pie';
		}
		// Fallback to product sales if no orders
		if ( Array.isArray( DatavizAIAdmin.productChartData ) && DatavizAIAdmin.productChartData.length > 0 ) {
			return 'product-sales-bar';
		}
		
		// Default: order status pie chart
		return 'order-status-pie';
	}

	/**
	 * Render a single relevant chart based on type
	 */
	function renderSingleChart( $container, chartType ) {
		if ( typeof window.Chart === 'undefined' ) {
			console.warn( 'Chart.js is not loaded. Charts cannot be rendered.' );
			return;
		}

		if ( ! $container || $container.length === 0 ) {
			console.warn( 'Chart container not found.' );
			return;
		}

		if ( typeof DatavizAIAdmin === 'undefined' ) {
			console.warn( 'DatavizAIAdmin data is not available.' );
			return;
		}

		$container.empty();
		
		// If chartType is null, don't render anything
		if ( ! chartType ) {
			return;
		}

		switch ( chartType ) {
			case 'order-status-pie':
				renderOrderStatusPieChart( $container );
				break;
			case 'order-status-bar':
				renderOrderStatusBarChart( $container );
				break;
			case 'order-status-line':
				renderOrderStatusLineChart( $container );
				break;
			case 'order-value-bar':
				renderOrderValueBarChart( $container );
				break;
			case 'order-value-line':
				renderOrderValueLineChart( $container );
				break;
			case 'order-value-pie':
				renderOrderValuePieChart( $container );
				break;
			case 'product-sales-bar':
				renderProductSalesBarChart( $container );
				break;
			case 'product-sales-pie':
				renderProductSalesPieChart( $container );
				break;
			case 'product-sales-line':
				renderProductSalesLineChart( $container );
				break;
			case 'customer-spending-bar':
				renderCustomerSpendingBarChart( $container );
				break;
			case 'customer-spending-pie':
				renderCustomerSpendingPieChart( $container );
				break;
			case 'customer-spending-line':
				renderCustomerSpendingLineChart( $container );
				break;
			case 'price-sales-scatter':
				renderPriceSalesScatterChart( $container );
				break;
			default:
				renderOrderStatusPieChart( $container );
		}
	}

	/**
	 * Render Order Status Pie Chart
	 */
	function renderOrderStatusPieChart( $container ) {
		if ( ! Array.isArray( DatavizAIAdmin.orderChartData ) || DatavizAIAdmin.orderChartData.length === 0 ) {
			return;
		}

		const statusCounts = {};
		DatavizAIAdmin.orderChartData.forEach( function( order ) {
			const status = order.status || 'unknown';
			statusCounts[ status ] = ( statusCounts[ status ] || 0 ) + 1;
		} );

		const statusLabels = Object.keys( statusCounts );
		const statusData = Object.values( statusCounts );

		if ( statusLabels.length > 0 ) {
			const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
			const $chartTitle = $( '<h3>Order Status Distribution</h3>' );
			const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-order-status"></canvas>' );
			$chartCard.append( $chartTitle ).append( $canvas );
			$container.append( $chartCard );

			const ctx = $canvas[0].getContext( '2d' );
			const chart = new window.Chart( ctx, {
				type: 'pie',
				data: {
					labels: statusLabels.map( function( s ) { return s.replace( 'wc-', '' ).charAt(0).toUpperCase() + s.replace( 'wc-', '' ).slice(1); } ),
					datasets: [ {
						label: 'Orders',
						data: statusData,
						backgroundColor: [
							'#3b82f6',
							'#10b981',
							'#f59e0b',
							'#ef4444',
							'#8b5cf6',
							'#14b8a6',
							'#f97316',
						],
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					animation: {
						duration: 1000,
						easing: 'easeInOutQuart',
					},
					interaction: {
						intersect: false,
						mode: 'index',
					},
					plugins: {
						legend: {
							position: 'bottom',
							onClick: function( e, legendItem ) {
								const index = legendItem.datasetIndex;
								const chart = this.chart;
								const meta = chart.getDatasetMeta( index );
								meta.hidden = meta.hidden === null ? !chart.data.datasets[ index ].hidden : null;
								chart.update();
							},
						},
						tooltip: {
							enabled: true,
							backgroundColor: 'rgba(0, 0, 0, 0.8)',
							titleColor: '#fff',
							bodyColor: '#fff',
							borderColor: '#666',
							borderWidth: 1,
							padding: 12,
							displayColors: true,
						},
					},
					onHover: function( event, activeElements ) {
						event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default';
					},
				},
			} );
			
			// Add click handler for interactivity
			$canvas.on( 'click', function( event ) {
				const points = chart.getElementsAtEventForMode( event, 'nearest', { intersect: true }, true );
				if ( points.length ) {
					const firstPoint = points[0];
					const label = chart.data.labels[ firstPoint.index ];
					const value = chart.data.datasets[ firstPoint.datasetIndex ].data[ firstPoint.index ];
					// Show interactive details
					showChartDetails( label, value, 'Order Status' );
				}
			} );
			
			$canvas.addClass( 'dataviz-ai-interactive-chart' );
		}
	}

	/**
	 * Render Order Value Bar Chart
	 */
	function renderOrderValueBarChart( $container ) {
		if ( ! Array.isArray( DatavizAIAdmin.orderChartData ) || DatavizAIAdmin.orderChartData.length === 0 ) {
			return;
		}

		const ordersWithTotals = DatavizAIAdmin.orderChartData.filter( function( order ) {
			return order.total && order.total > 0;
		} );

		if ( ordersWithTotals.length > 0 ) {
			const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
			const $chartTitle = $( '<h3>Order Values</h3>' );
			const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-order-value-bar"></canvas>' );
			$chartCard.append( $chartTitle ).append( $canvas );
			$container.append( $chartCard );

			ordersWithTotals.sort( function( a, b ) { return a.id - b.id; } );

			const labels = ordersWithTotals.map( function( order ) {
				return 'Order #' + order.id;
			} );
			const data = ordersWithTotals.map( function( order ) {
				return order.total;
			} );

			const ctx = $canvas[0].getContext( '2d' );
			new window.Chart( ctx, {
				type: 'bar',
				data: {
					labels: labels,
					datasets: [ {
						label: 'Order Total ($)',
						data: data,
						backgroundColor: '#3b82f6',
						borderColor: '#2563eb',
						borderWidth: 1,
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
						legend: {
							display: false,
						},
						tooltip: {
							callbacks: {
								label: function( context ) {
									return '$' + context.parsed.y.toFixed( 2 );
								},
							},
						},
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								callback: function( value ) {
									return '$' + value.toFixed( 2 );
								},
							},
						},
					},
				},
			} );
		}
	}

	/**
	 * Render Order Value Line Chart
	 */
	function renderOrderValueLineChart( $container ) {
		if ( ! Array.isArray( DatavizAIAdmin.orderChartData ) || DatavizAIAdmin.orderChartData.length === 0 ) {
			return;
		}

		const ordersWithTotals = DatavizAIAdmin.orderChartData.filter( function( order ) {
			return order.total && order.total > 0;
		} );

		if ( ordersWithTotals.length > 1 ) {
			const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
			const $chartTitle = $( '<h3>Order Value Trend</h3>' );
			const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-order-value-line"></canvas>' );
			$chartCard.append( $chartTitle ).append( $canvas );
			$container.append( $chartCard );

			ordersWithTotals.sort( function( a, b ) { return a.id - b.id; } );

			const labels = ordersWithTotals.map( function( order ) {
				return 'Order #' + order.id;
			} );
			const data = ordersWithTotals.map( function( order ) {
				return order.total;
			} );

			const ctx = $canvas[0].getContext( '2d' );
			new window.Chart( ctx, {
				type: 'line',
				data: {
					labels: labels,
					datasets: [ {
						label: 'Order Total ($)',
						data: data,
						borderColor: '#8b5cf6',
						backgroundColor: 'rgba(139, 92, 246, 0.1)',
						borderWidth: 2,
						fill: true,
						tension: 0.4,
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
						legend: {
							display: true,
							position: 'top',
						},
						tooltip: {
							callbacks: {
								label: function( context ) {
									return '$' + context.parsed.y.toFixed( 2 );
								},
							},
						},
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								callback: function( value ) {
									return '$' + value.toFixed( 2 );
								},
							},
						},
					},
				},
			} );
		}
	}

	/**
	 * Render Product Sales Bar Chart
	 */
	function renderProductSalesBarChart( $container ) {
		if ( ! Array.isArray( DatavizAIAdmin.productChartData ) || DatavizAIAdmin.productChartData.length === 0 ) {
			return;
		}

		const productsWithSales = DatavizAIAdmin.productChartData.filter( function( product ) {
			return product.sales && product.sales > 0;
		} );

		if ( productsWithSales.length > 0 ) {
			const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
			const $chartTitle = $( '<h3>Product Sales</h3>' );
			const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-product-sales-bar"></canvas>' );
			$chartCard.append( $chartTitle ).append( $canvas );
			$container.append( $chartCard );

			productsWithSales.sort( function( a, b ) { return b.sales - a.sales; } );

			const labels = productsWithSales.map( function( product ) {
				return product.name.length > 15 ? product.name.substring( 0, 15 ) + '...' : product.name;
			} );
			const data = productsWithSales.map( function( product ) {
				return product.sales;
			} );

			const ctx = $canvas[0].getContext( '2d' );
			new window.Chart( ctx, {
				type: 'bar',
				data: {
					labels: labels,
					datasets: [ {
						label: 'Units Sold',
						data: data,
						backgroundColor: '#10b981',
						borderColor: '#059669',
						borderWidth: 1,
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					indexAxis: 'y',
					plugins: {
						legend: {
							display: false,
						},
						tooltip: {
							callbacks: {
								label: function( context ) {
									return context.parsed.x + ' units sold';
								},
							},
						},
					},
					scales: {
						x: {
							beginAtZero: true,
						},
					},
				},
			} );
		}
	}

	/**
	 * Render Product Sales Pie Chart
	 */
	function renderProductSalesPieChart( $container ) {
		if ( ! Array.isArray( DatavizAIAdmin.productChartData ) || DatavizAIAdmin.productChartData.length === 0 ) {
			return;
		}

		const productsWithSales = DatavizAIAdmin.productChartData.filter( function( product ) {
			return product.sales && product.sales > 0;
		} );

		if ( productsWithSales.length > 0 ) {
			const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
			const $chartTitle = $( '<h3>Product Sales Distribution</h3>' );
			const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-product-sales"></canvas>' );
			$chartCard.append( $chartTitle ).append( $canvas );
			$container.append( $chartCard );

			const labels = productsWithSales.map( function( product ) {
				return product.name.length > 20 ? product.name.substring( 0, 20 ) + '...' : product.name;
			} );
			const data = productsWithSales.map( function( product ) {
				return product.sales;
			} );

			const ctx = $canvas[0].getContext( '2d' );
			new window.Chart( ctx, {
				type: 'pie',
				data: {
					labels: labels,
					datasets: [ {
						label: 'Total Sales',
						data: data,
						backgroundColor: [
							'#3b82f6',
							'#6366f1',
							'#10b981',
							'#f59e0b',
							'#ef4444',
							'#8b5cf6',
							'#14b8a6',
							'#f97316',
							'#ec4899',
							'#06b6d4',
						],
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
						legend: {
							position: 'bottom',
						},
						tooltip: {
							callbacks: {
								label: function( context ) {
									return context.label + ': ' + context.parsed + ' sold';
								},
							},
						},
					},
				},
			} );
		}
	}

	/**
	 * Render Price vs Sales Scatter Chart
	 */
	function renderPriceSalesScatterChart( $container ) {
		if ( ! Array.isArray( DatavizAIAdmin.productChartData ) || DatavizAIAdmin.productChartData.length === 0 ) {
			return;
		}

		const productsWithData = DatavizAIAdmin.productChartData.filter( function( product ) {
			return product.price && product.price > 0 && product.sales !== undefined;
		} );

		if ( productsWithData.length > 0 ) {
			const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
			const $chartTitle = $( '<h3>Price vs Sales</h3>' );
			const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-price-sales-scatter"></canvas>' );
			$chartCard.append( $chartTitle ).append( $canvas );
			$container.append( $chartCard );

			const scatterData = productsWithData.map( function( product ) {
				return {
					x: parseFloat( product.price ),
					y: product.sales,
				};
			} );

			const ctx = $canvas[0].getContext( '2d' );
			new window.Chart( ctx, {
				type: 'scatter',
				data: {
					datasets: [ {
						label: 'Products',
						data: scatterData,
						backgroundColor: '#ec4899',
						borderColor: '#db2777',
						borderWidth: 2,
						pointRadius: 6,
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
						legend: {
							display: false,
						},
						tooltip: {
							callbacks: {
								label: function( context ) {
									return 'Price: $' + context.parsed.x.toFixed( 2 ) + ', Sales: ' + context.parsed.y;
								},
							},
						},
					},
					scales: {
						x: {
							title: {
								display: true,
								text: 'Price ($)',
							},
							beginAtZero: true,
						},
						y: {
							title: {
								display: true,
								text: 'Units Sold',
							},
							beginAtZero: true,
						},
					},
				},
			} );
		}
	}

	/**
	 * Render Order Status Bar Chart
	 */
	function renderOrderStatusBarChart( $container ) {
		if ( ! Array.isArray( DatavizAIAdmin.orderChartData ) || DatavizAIAdmin.orderChartData.length === 0 ) {
			return;
		}

		const statusCounts = {};
		DatavizAIAdmin.orderChartData.forEach( function( order ) {
			const status = order.status || 'unknown';
			statusCounts[ status ] = ( statusCounts[ status ] || 0 ) + 1;
		} );

		const statusLabels = Object.keys( statusCounts );
		const statusData = Object.values( statusCounts );

		if ( statusLabels.length > 0 ) {
			const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
			const $chartTitle = $( '<h3>Order Status Distribution</h3>' );
			const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-order-status-bar"></canvas>' );
			$chartCard.append( $chartTitle ).append( $canvas );
			$container.append( $chartCard );

			const ctx = $canvas[0].getContext( '2d' );
			new window.Chart( ctx, {
				type: 'bar',
				data: {
					labels: statusLabels.map( function( s ) { return s.replace( 'wc-', '' ).charAt(0).toUpperCase() + s.replace( 'wc-', '' ).slice(1); } ),
					datasets: [ {
						label: 'Orders',
						data: statusData,
						backgroundColor: '#3b82f6',
						borderColor: '#2563eb',
						borderWidth: 1,
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
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
	}

	/**
	 * Render Order Status Line Chart
	 */
	function renderOrderStatusLineChart( $container ) {
		if ( ! Array.isArray( DatavizAIAdmin.orderChartData ) || DatavizAIAdmin.orderChartData.length === 0 ) {
			return;
		}

		const statusCounts = {};
		DatavizAIAdmin.orderChartData.forEach( function( order ) {
			const status = order.status || 'unknown';
			statusCounts[ status ] = ( statusCounts[ status ] || 0 ) + 1;
		} );

		const statusLabels = Object.keys( statusCounts );
		const statusData = Object.values( statusCounts );

		if ( statusLabels.length > 0 ) {
			const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
			const $chartTitle = $( '<h3>Order Status Distribution</h3>' );
			const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-order-status-line"></canvas>' );
			$chartCard.append( $chartTitle ).append( $canvas );
			$container.append( $chartCard );

			const ctx = $canvas[0].getContext( '2d' );
			new window.Chart( ctx, {
				type: 'line',
				data: {
					labels: statusLabels.map( function( s ) { return s.replace( 'wc-', '' ).charAt(0).toUpperCase() + s.replace( 'wc-', '' ).slice(1); } ),
					datasets: [ {
						label: 'Orders',
						data: statusData,
						borderColor: '#8b5cf6',
						backgroundColor: 'rgba(139, 92, 246, 0.1)',
						borderWidth: 2,
						fill: true,
						tension: 0.4,
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
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
	}

	/**
	 * Render Order Value Pie Chart
	 */
	function renderOrderValuePieChart( $container ) {
		if ( ! Array.isArray( DatavizAIAdmin.orderChartData ) || DatavizAIAdmin.orderChartData.length === 0 ) {
			return;
		}

		const ordersWithTotals = DatavizAIAdmin.orderChartData.filter( function( order ) {
			return order.total && order.total > 0;
		} );

		if ( ordersWithTotals.length > 0 ) {
			const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
			const $chartTitle = $( '<h3>Order Value Distribution</h3>' );
			const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-order-value-pie"></canvas>' );
			$chartCard.append( $chartTitle ).append( $canvas );
			$container.append( $chartCard );

			const labels = ordersWithTotals.map( function( order ) {
				return 'Order #' + order.id;
			} );
			const data = ordersWithTotals.map( function( order ) {
				return order.total;
			} );

			const ctx = $canvas[0].getContext( '2d' );
			new window.Chart( ctx, {
				type: 'pie',
				data: {
					labels: labels,
					datasets: [ {
						label: 'Order Total ($)',
						data: data,
						backgroundColor: [
							'#3b82f6',
							'#6366f1',
							'#10b981',
							'#f59e0b',
							'#ef4444',
							'#8b5cf6',
							'#14b8a6',
							'#f97316',
							'#ec4899',
							'#06b6d4',
						],
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
						legend: {
							position: 'bottom',
						},
						tooltip: {
							callbacks: {
								label: function( context ) {
									return context.label + ': $' + context.parsed.toFixed( 2 );
								},
							},
						},
					},
				},
			} );
		}
	}

	/**
	 * Render Product Sales Line Chart
	 */
	function renderProductSalesLineChart( $container ) {
		if ( ! Array.isArray( DatavizAIAdmin.productChartData ) || DatavizAIAdmin.productChartData.length === 0 ) {
			return;
		}

		const productsWithSales = DatavizAIAdmin.productChartData.filter( function( product ) {
			return product.sales && product.sales > 0;
		} );

		if ( productsWithSales.length > 0 ) {
			const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
			const $chartTitle = $( '<h3>Product Sales Trend</h3>' );
			const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-product-sales-line"></canvas>' );
			$chartCard.append( $chartTitle ).append( $canvas );
			$container.append( $chartCard );

			productsWithSales.sort( function( a, b ) { return b.sales - a.sales; } );

			const labels = productsWithSales.map( function( product ) {
				return product.name.length > 15 ? product.name.substring( 0, 15 ) + '...' : product.name;
			} );
			const data = productsWithSales.map( function( product ) {
				return product.sales;
			} );

			const ctx = $canvas[0].getContext( '2d' );
			new window.Chart( ctx, {
				type: 'line',
				data: {
					labels: labels,
					datasets: [ {
						label: 'Units Sold',
						data: data,
						borderColor: '#10b981',
						backgroundColor: 'rgba(16, 185, 129, 0.1)',
						borderWidth: 2,
						fill: true,
						tension: 0.4,
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
						legend: {
							display: true,
							position: 'top',
						},
						tooltip: {
							callbacks: {
								label: function( context ) {
									return context.parsed.y + ' units sold';
								},
							},
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
	}

	/**
	 * Render Customer Spending Bar Chart
	 */
	function renderCustomerSpendingBarChart( $container ) {
		if ( ! Array.isArray( DatavizAIAdmin.customerChartData ) || DatavizAIAdmin.customerChartData.length === 0 ) {
			return;
		}

		const customersWithSpending = DatavizAIAdmin.customerChartData.filter( function( customer ) {
			return customer.total_spent && customer.total_spent > 0;
		} );

		if ( customersWithSpending.length > 0 ) {
			const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
			const $chartTitle = $( '<h3>Customer Spending</h3>' );
			const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-customer-spending-bar"></canvas>' );
			$chartCard.append( $chartTitle ).append( $canvas );
			$container.append( $chartCard );

			// Sort by spending descending
			customersWithSpending.sort( function( a, b ) { return b.total_spent - a.total_spent; } );

			const labels = customersWithSpending.map( function( customer ) {
				return customer.name.length > 15 ? customer.name.substring( 0, 15 ) + '...' : customer.name;
			} );
			const data = customersWithSpending.map( function( customer ) {
				return customer.total_spent;
			} );

			const ctx = $canvas[0].getContext( '2d' );
			new window.Chart( ctx, {
				type: 'bar',
				data: {
					labels: labels,
					datasets: [ {
						label: 'Total Spent ($)',
						data: data,
						backgroundColor: '#8b5cf6',
						borderColor: '#7c3aed',
						borderWidth: 1,
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					indexAxis: 'y',
					plugins: {
						legend: {
							display: false,
						},
						tooltip: {
							callbacks: {
								label: function( context ) {
									return '$' + context.parsed.x.toFixed( 2 );
								},
							},
						},
					},
					scales: {
						x: {
							beginAtZero: true,
							ticks: {
								callback: function( value ) {
									return '$' + value.toFixed( 2 );
								},
							},
						},
					},
				},
			} );
		}
	}

	/**
	 * Render Customer Spending Pie Chart
	 */
	function renderCustomerSpendingPieChart( $container ) {
		if ( ! Array.isArray( DatavizAIAdmin.customerChartData ) || DatavizAIAdmin.customerChartData.length === 0 ) {
			return;
		}

		const customersWithSpending = DatavizAIAdmin.customerChartData.filter( function( customer ) {
			return customer.total_spent && customer.total_spent > 0;
		} );

		if ( customersWithSpending.length > 0 ) {
			const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
			const $chartTitle = $( '<h3>Customer Spending Distribution</h3>' );
			const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-customer-spending-pie"></canvas>' );
			$chartCard.append( $chartTitle ).append( $canvas );
			$container.append( $chartCard );

			const labels = customersWithSpending.map( function( customer ) {
				return customer.name.length > 20 ? customer.name.substring( 0, 20 ) + '...' : customer.name;
			} );
			const data = customersWithSpending.map( function( customer ) {
				return customer.total_spent;
			} );

			const ctx = $canvas[0].getContext( '2d' );
			new window.Chart( ctx, {
				type: 'pie',
				data: {
					labels: labels,
					datasets: [ {
						label: 'Total Spent ($)',
						data: data,
						backgroundColor: [
							'#8b5cf6',
							'#6366f1',
							'#3b82f6',
							'#10b981',
							'#f59e0b',
							'#ef4444',
							'#14b8a6',
							'#f97316',
							'#ec4899',
							'#06b6d4',
						],
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
						legend: {
							position: 'bottom',
						},
						tooltip: {
							callbacks: {
								label: function( context ) {
									return context.label + ': $' + context.parsed.toFixed( 2 );
								},
							},
						},
					},
				},
			} );
		}
	}

	/**
	 * Render Customer Spending Line Chart
	 */
	function renderCustomerSpendingLineChart( $container ) {
		if ( ! Array.isArray( DatavizAIAdmin.customerChartData ) || DatavizAIAdmin.customerChartData.length === 0 ) {
			return;
		}

		const customersWithSpending = DatavizAIAdmin.customerChartData.filter( function( customer ) {
			return customer.total_spent && customer.total_spent > 0;
		} );

		if ( customersWithSpending.length > 1 ) {
			const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
			const $chartTitle = $( '<h3>Customer Spending Trend</h3>' );
			const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-customer-spending-line"></canvas>' );
			$chartCard.append( $chartTitle ).append( $canvas );
			$container.append( $chartCard );

			// Sort by customer ID for trend
			customersWithSpending.sort( function( a, b ) { return a.id - b.id; } );

			const labels = customersWithSpending.map( function( customer ) {
				return customer.name.length > 15 ? customer.name.substring( 0, 15 ) + '...' : customer.name;
			} );
			const data = customersWithSpending.map( function( customer ) {
				return customer.total_spent;
			} );

			const ctx = $canvas[0].getContext( '2d' );
			new window.Chart( ctx, {
				type: 'line',
				data: {
					labels: labels,
					datasets: [ {
						label: 'Total Spent ($)',
						data: data,
						borderColor: '#8b5cf6',
						backgroundColor: 'rgba(139, 92, 246, 0.1)',
						borderWidth: 2,
						fill: true,
						tension: 0.4,
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
						legend: {
							display: true,
							position: 'top',
						},
						tooltip: {
							callbacks: {
								label: function( context ) {
									return '$' + context.parsed.y.toFixed( 2 );
								},
							},
						},
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								callback: function( value ) {
									return '$' + value.toFixed( 2 );
								},
							},
						},
					},
				},
			} );
		}
	}

	/**
	 * Show interactive chart details (can be extended for modal/popup)
	 */
	function showChartDetails( label, value, category ) {
		// You can extend this to show a modal, tooltip, or update UI
		console.log( 'Chart Interaction:', category, label, value );
		// Example: Could show a notification or update a details panel
		if ( typeof window.jQuery !== 'undefined' ) {
			const $details = $( '<div class="dataviz-ai-chart-details"></div>' );
			$details.html( '<strong>' + category + ':</strong> ' + label + ' - ' + value );
			$( '.dataviz-ai-charts-container' ).after( $details );
			setTimeout( function() {
				$details.fadeOut( function() {
					$details.remove();
				} );
			}, 3000 );
		}
	}

}( window.jQuery ) );

