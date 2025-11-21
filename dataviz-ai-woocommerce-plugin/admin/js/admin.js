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

					// Determine which chart to show based on question content
					const chartType = determineRelevantChart( question );
					
					if ( chartType ) {
						// Show only the relevant chart
						$chartsContainer.slideDown( 300 );
						
						// Render only the specific chart
						if ( typeof window.Chart !== 'undefined' ) {
							renderSingleChart( $chartsContainer, chartType );
						} else {
							// Wait for Chart.js to load
							setTimeout( function() {
								if ( typeof window.Chart !== 'undefined' ) {
									renderSingleChart( $chartsContainer, chartType );
								}
							}, 500 );
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
	 * Determine which chart is most relevant based on the question
	 */
	function determineRelevantChart( question ) {
		const questionLower = question.toLowerCase().trim();
		
		// Check for chart-related keywords first
		const chartKeywords = [
			'chart', 'charts', 'graph', 'graphs', 'visualization', 'visualizations',
			'visualize', 'plot', 'plots', 'diagram', 'diagrams',
			'show me', 'display', 'show', 'see', 'view',
			'pie chart', 'bar chart', 'line chart', 'scatter chart',
			'visual', 'graphic', 'picture', 'illustration'
		];
		
		const hasChartKeyword = chartKeywords.some( function( keyword ) {
			return questionLower.includes( keyword );
		} );
		
		if ( ! hasChartKeyword ) {
			return null; // No chart requested
		}
		
		// First, check for explicit chart type requests (pie, bar, line)
		const wantsPie = questionLower.match( /pie\s+chart|pie\s+graph|doughnut|pie/i );
		const wantsBar = questionLower.match( /bar\s+chart|bar\s+graph|bars|bar/i );
		const wantsLine = questionLower.match( /line\s+chart|line\s+graph|line\s+plot|line/i );
		const wantsScatter = questionLower.match( /scatter\s+chart|scatter\s+plot|scatter\s+graph|scatter/i );
		
		// Determine data type requested
		const wantsOrderStatus = questionLower.match( /order\s+status|status\s+of\s+orders|order\s+state|order\s+statuses/i );
		const wantsProductSales = questionLower.match( /product\s+sales|sales\s+by\s+product|top\s+products|best\s+selling|product\s+performance|products/i );
		const wantsOrderValue = questionLower.match( /order\s+value|order\s+total|revenue|order\s+amount|sales\s+value|order\s+worth|orders/i );
		const wantsPriceVsSales = questionLower.match( /price\s+vs|price\s+and\s+sales|correlation|relationship\s+between\s+price|price\s+impact/i );
		const wantsTrend = questionLower.match( /trend|over\s+time|history|timeline|progression|growth|change\s+over/i );
		
		// Combine chart type with data type
		// Priority: Explicit chart type > Data type > Default
		
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
			// Default line: order value trend
			return 'order-value-line';
		}
		
		// Scatter chart requests
		if ( wantsScatter || wantsPriceVsSales ) {
			return 'price-sales-scatter';
		}
		
		// No explicit chart type, determine by data type
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
		
		if ( wantsTrend ) {
			return 'order-value-line';
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
			new window.Chart( ctx, {
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
					plugins: {
						legend: {
							position: 'bottom',
						},
					},
				},
			} );
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

}( window.jQuery ) );

