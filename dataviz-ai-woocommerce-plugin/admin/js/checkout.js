/**
 * Secure Checkout Handler for Dataviz AI WooCommerce Plugin
 *
 * Handles Stripe and PayPal payment processing.
 */

(function($) {
	'use strict';

	let stripe = null;
	let cardElement = null;
	let paymentIntentClientSecret = null;

	/**
	 * Initialize checkout page.
	 */
	function initCheckout() {
		if (typeof DatavizAICheckout === 'undefined') {
			return;
		}

		const { paymentMethod, stripePublishableKey, isStripeConfigured, isPayPalConfigured } = DatavizAICheckout;

		if (paymentMethod === 'stripe' && isStripeConfigured && typeof Stripe !== 'undefined') {
			initStripeCheckout(stripePublishableKey);
		} else if (paymentMethod === 'paypal' && isPayPalConfigured) {
			initPayPalCheckout();
		}
	}

	/**
	 * Initialize Stripe checkout.
	 */
	function initStripeCheckout(publishableKey) {
		stripe = Stripe(publishableKey);

		// Create card element
		const elements = stripe.elements();
		cardElement = elements.create('card', {
			style: {
				base: {
					fontSize: '16px',
					color: '#32325d',
					fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
					'::placeholder': {
						color: '#aab7c4'
					}
				},
				invalid: {
					color: '#fa755a',
					iconColor: '#fa755a'
				}
			}
		});

		cardElement.mount('#stripe-card-element');

		// Handle real-time validation errors
		cardElement.on('change', function(event) {
			const displayError = document.getElementById('stripe-card-errors');
			if (event.error) {
				displayError.textContent = event.error.message;
			} else {
				displayError.textContent = '';
			}
		});

		// Handle form submission
		$('#dataviz-ai-stripe-checkout-form').on('submit', handleStripeSubmit);
	}

	/**
	 * Handle Stripe form submission.
	 */
	function handleStripeSubmit(e) {
		e.preventDefault();

		const submitButton = $('#stripe-submit-button');
		submitButton.prop('disabled', true).text('Processing...');

		// Create payment intent
		$.ajax({
			url: DatavizAICheckout.ajaxUrl,
			type: 'POST',
			data: {
				action: 'dataviz_ai_create_payment_intent',
				nonce: DatavizAICheckout.nonce,
				plan: DatavizAICheckout.plan,
				amount: DatavizAICheckout.amount,
				currency: DatavizAICheckout.currency
			},
			success: function(response) {
				if (response.success && response.data.client_secret) {
					paymentIntentClientSecret = response.data.client_secret;
					confirmStripePayment();
				} else {
					showError(response.data?.message || 'Failed to create payment intent. Please try again.');
					submitButton.prop('disabled', false).text('Pay $' + DatavizAICheckout.amount + '/month');
				}
			},
			error: function() {
				showError('Network error. Please check your connection and try again.');
				submitButton.prop('disabled', false).text('Pay $' + DatavizAICheckout.amount + '/month');
			}
		});
	}

	/**
	 * Confirm Stripe payment.
	 */
	function confirmStripePayment() {
		stripe.confirmCardPayment(paymentIntentClientSecret, {
			payment_method: {
				card: cardElement,
				billing_details: {
					name: DatavizAICheckout.userName || 'Customer'
				}
			}
		}).then(function(result) {
			if (result.error) {
				showError(result.error.message);
				$('#stripe-submit-button').prop('disabled', false).text('Pay $' + DatavizAICheckout.amount + '/month');
			} else {
				// Payment succeeded
				handlePaymentSuccess(result.paymentIntent.id);
			}
		});
	}

	/**
	 * Initialize PayPal checkout.
	 */
	function initPayPalCheckout() {
		// PayPal SDK is loaded via script tag in PHP, wait for it to be available
		if (typeof paypal === 'undefined') {
			// Wait for PayPal SDK to load
			setTimeout(function() {
				if (typeof paypal !== 'undefined') {
					renderPayPalButton();
				} else {
					showError('PayPal SDK failed to load. Please refresh the page.');
				}
			}, 1000);
		} else {
			renderPayPalButton();
		}
	}

	/**
	 * Render PayPal button.
	 */
	function renderPayPalButton() {
		paypal.Buttons({
			createOrder: function(data, actions) {
				return actions.order.create({
					purchase_units: [{
						amount: {
							value: DatavizAICheckout.amount.toString(),
							currency_code: DatavizAICheckout.currency
						},
						description: 'Dataviz AI ' + DatavizAICheckout.plan.charAt(0).toUpperCase() + DatavizAICheckout.plan.slice(1) + ' Plan'
					}]
				});
			},
			onApprove: function(data, actions) {
				return actions.order.capture().then(function(details) {
					handlePaymentSuccess(details.id, 'paypal');
				});
			},
			onError: function(err) {
				showError('PayPal error: ' + err.message);
			},
			onCancel: function() {
				showError('Payment cancelled.');
			}
		}).render('#paypal-button-container');
	}

	/**
	 * Handle successful payment.
	 */
	function handlePaymentSuccess(paymentId, method = 'stripe') {
		// Show loading state
		$('.dataviz-ai-checkout-form').html(
			'<div style="text-align: center; padding: 40px;">' +
			'<div class="spinner is-active" style="float: none; margin: 0 auto 20px;"></div>' +
			'<h2>Processing your payment...</h2>' +
			'<p>Please wait while we activate your license.</p>' +
			'</div>'
		);

		// Process payment and generate license
		$.ajax({
			url: DatavizAICheckout.ajaxUrl,
			type: 'POST',
			data: {
				action: 'dataviz_ai_process_payment',
				nonce: DatavizAICheckout.nonce,
				plan: DatavizAICheckout.plan,
				payment_id: paymentId,
				payment_method: method
			},
			success: function(response) {
				if (response.success) {
					// Redirect to license page with success message
					window.location.href = DatavizAICheckout.successUrl + '&license_key=' + encodeURIComponent(response.data.license_key);
				} else {
					showError(response.data?.message || 'Payment processed but license activation failed. Please contact support.');
				}
			},
			error: function() {
				showError('Network error. Your payment was successful, but we could not activate your license. Please contact support with payment ID: ' + paymentId);
			}
		});
	}

	/**
	 * Show error message.
	 */
	function showError(message) {
		const errorDiv = $('<div class="notice notice-error"><p>' + message + '</p></div>');
		$('.dataviz-ai-checkout-form').prepend(errorDiv);
		
		// Scroll to top
		$('html, body').animate({
			scrollTop: 0
		}, 300);
	}

	// Initialize on page load
	$(document).ready(function() {
		initCheckout();
	});

})(jQuery);

