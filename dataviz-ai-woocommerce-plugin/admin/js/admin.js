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

		// Render charts immediately on page load
		const $chartsContainer = $( '.dataviz-ai-charts-container' );
		if ( $chartsContainer.length ) {
			// Wait for Chart.js to load
			if ( typeof window.Chart !== 'undefined' ) {
				renderCharts( $chartsContainer );
			} else {
				// Wait for Chart.js to load
				$( window ).on( 'load', function() {
					setTimeout( function() {
						if ( typeof window.Chart !== 'undefined' ) {
							renderCharts( $chartsContainer );
						}
					}, 500 );
				} );
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
		// Don't clear charts - keep them visible alongside AI response

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

					// Render charts from available data (always render, even after AI response)
					if ( typeof window.Chart !== 'undefined' ) {
						renderCharts( $chartsContainer );
					} else {
						// Wait for Chart.js if not loaded yet
						setTimeout( function() {
							if ( typeof window.Chart !== 'undefined' ) {
								renderCharts( $chartsContainer );
							}
						}, 500 );
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
	 * Render pie charts from available data
	 */
	function renderCharts( $container ) {
		if ( typeof window.Chart === 'undefined' ) {
			console.warn( 'Chart.js is not loaded. Charts cannot be rendered.' );
			return;
		}

		if ( ! $container || $container.length === 0 ) {
			console.warn( 'Chart container not found.' );
			return;
		}

		$container.empty();

		// Check if data is available
		if ( typeof DatavizAIAdmin === 'undefined' ) {
			console.warn( 'DatavizAIAdmin data is not available.' );
			return;
		}

		let chartsRendered = 0;

		// Order Status Pie Chart
		if ( typeof DatavizAIAdmin !== 'undefined' && Array.isArray( DatavizAIAdmin.orderChartData ) && DatavizAIAdmin.orderChartData.length > 0 ) {
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
				chartsRendered++;
			}
		}

		// Order Totals Pie Chart
		if ( typeof DatavizAIAdmin !== 'undefined' && Array.isArray( DatavizAIAdmin.orderChartData ) && DatavizAIAdmin.orderChartData.length > 0 ) {
			const ordersWithTotals = DatavizAIAdmin.orderChartData.filter( function( order ) {
				return order.total && order.total > 0;
			} );

			if ( ordersWithTotals.length > 0 ) {
				const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
				const $chartTitle = $( '<h3>Order Value Distribution</h3>' );
				const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-order-value"></canvas>' );
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
				chartsRendered++;
			}
		}

		// Product Sales Pie Chart
		if ( typeof DatavizAIAdmin !== 'undefined' && Array.isArray( DatavizAIAdmin.productChartData ) && DatavizAIAdmin.productChartData.length > 0 ) {
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
				chartsRendered++;
			}
		}

		// Order Value Bar Chart
		if ( typeof DatavizAIAdmin !== 'undefined' && Array.isArray( DatavizAIAdmin.orderChartData ) && DatavizAIAdmin.orderChartData.length > 0 ) {
			const ordersWithTotals = DatavizAIAdmin.orderChartData.filter( function( order ) {
				return order.total && order.total > 0;
			} );

			if ( ordersWithTotals.length > 0 ) {
				const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
				const $chartTitle = $( '<h3>Order Values (Bar Chart)</h3>' );
				const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-order-value-bar"></canvas>' );
				$chartCard.append( $chartTitle ).append( $canvas );
				$container.append( $chartCard );

				// Sort by order ID for better visualization
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
				chartsRendered++;
			}
		}

		// Product Sales Bar Chart
		if ( typeof DatavizAIAdmin !== 'undefined' && Array.isArray( DatavizAIAdmin.productChartData ) && DatavizAIAdmin.productChartData.length > 0 ) {
			const productsWithSales = DatavizAIAdmin.productChartData.filter( function( product ) {
				return product.sales && product.sales > 0;
			} );

			if ( productsWithSales.length > 0 ) {
				const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
				const $chartTitle = $( '<h3>Product Sales Comparison (Bar Chart)</h3>' );
				const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-product-sales-bar"></canvas>' );
				$chartCard.append( $chartTitle ).append( $canvas );
				$container.append( $chartCard );

				// Sort by sales descending
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
						indexAxis: 'y', // Horizontal bar chart
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
				chartsRendered++;
			}
		}

		// Order Status Doughnut Chart (alternative to pie)
		if ( typeof DatavizAIAdmin !== 'undefined' && Array.isArray( DatavizAIAdmin.orderChartData ) && DatavizAIAdmin.orderChartData.length > 0 ) {
			const statusCounts = {};
			DatavizAIAdmin.orderChartData.forEach( function( order ) {
				const status = order.status || 'unknown';
				statusCounts[ status ] = ( statusCounts[ status ] || 0 ) + 1;
			} );

			const statusLabels = Object.keys( statusCounts );
			const statusData = Object.values( statusCounts );

			if ( statusLabels.length > 0 ) {
				const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
				const $chartTitle = $( '<h3>Order Status (Doughnut Chart)</h3>' );
				const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-order-status-doughnut"></canvas>' );
				$chartCard.append( $chartTitle ).append( $canvas );
				$container.append( $chartCard );

				const ctx = $canvas[0].getContext( '2d' );
				new window.Chart( ctx, {
					type: 'doughnut',
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
				chartsRendered++;
			}
		}

		// Order Value Line Chart (trend visualization)
		if ( typeof DatavizAIAdmin !== 'undefined' && Array.isArray( DatavizAIAdmin.orderChartData ) && DatavizAIAdmin.orderChartData.length > 0 ) {
			const ordersWithTotals = DatavizAIAdmin.orderChartData.filter( function( order ) {
				return order.total && order.total > 0;
			} );

			if ( ordersWithTotals.length > 1 ) {
				const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
				const $chartTitle = $( '<h3>Order Value Trend (Line Chart)</h3>' );
				const $canvas = $( '<canvas class="dataviz-ai-chart" id="chart-order-value-line"></canvas>' );
				$chartCard.append( $chartTitle ).append( $canvas );
				$container.append( $chartCard );

				// Sort by order ID for trend
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
				chartsRendered++;
			}
		}

		// Product Price vs Sales Scatter Chart
		if ( typeof DatavizAIAdmin !== 'undefined' && Array.isArray( DatavizAIAdmin.productChartData ) && DatavizAIAdmin.productChartData.length > 0 ) {
			const productsWithData = DatavizAIAdmin.productChartData.filter( function( product ) {
				return product.price && product.price > 0 && product.sales !== undefined;
			} );

			if ( productsWithData.length > 0 ) {
				const $chartCard = $( '<div class="dataviz-ai-chart-card"></div>' );
				const $chartTitle = $( '<h3>Price vs Sales (Scatter Chart)</h3>' );
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
				chartsRendered++;
			}
		}

		// If no charts were rendered, show a message
		if ( chartsRendered === 0 ) {
			const $noDataMsg = $( '<p class="dataviz-ai-no-charts">No chart data available. Make sure you have orders and products in your store.</p>' );
			$container.append( $noDataMsg );
		}
	}
}( window.jQuery ) );

