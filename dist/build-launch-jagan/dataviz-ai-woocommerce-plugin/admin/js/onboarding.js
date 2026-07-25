( function( $ ) {
	'use strict';

	const Onboarding = {
		currentStep: 1,
		totalSteps: 5,
		isCompleted: false,

		init: function() {
			if ( typeof DatavizAIOnboarding === 'undefined' ) {
				return;
			}

			this.currentStep = DatavizAIOnboarding.currentStep || 1;
			this.isCompleted = DatavizAIOnboarding.isCompleted || false;

			// Don't show onboarding if already completed
			if ( this.isCompleted ) {
				return;
			}

			// Show onboarding overlay
			this.showOverlay();

			// Bind events
			this.bindEvents();
		},

		showOverlay: function() {
			// Create overlay if it doesn't exist
			if ( $( '#dataviz-ai-onboarding-overlay' ).length === 0 ) {
				// Overlay will be rendered by PHP
				return;
			}

			$( '#dataviz-ai-onboarding-overlay' ).fadeIn( 300 );
		},

		hideOverlay: function() {
			$( '#dataviz-ai-onboarding-overlay' ).fadeOut( 300, function() {
				$( this ).remove();
			} );
		},

		bindEvents: function() {
			const self = this;

			// Next button
			$( document ).on( 'click', '.dataviz-ai-onboarding-next', function() {
				self.nextStep();
			} );

			// Previous button
			$( document ).on( 'click', '.dataviz-ai-onboarding-prev', function() {
				self.prevStep();
			} );

			// Skip button
			$( document ).on( 'click', '.dataviz-ai-onboarding-skip', function() {
				self.skipOnboarding();
			} );

			// Complete button
			$( document ).on( 'click', '.dataviz-ai-onboarding-complete', function() {
				self.completeOnboarding();
			} );

			// Close button (if exists)
			$( document ).on( 'click', '.dataviz-ai-onboarding-close', function() {
				self.skipOnboarding();
			} );

			// Try example question
			$( document ).on( 'click', '.dataviz-ai-onboarding-try-example', function() {
				const example = $( this ).data( 'example' );
				if ( example ) {
					$( '#dataviz-ai-question' ).val( example ).focus();
					self.hideOverlay();
				}
			} );
		},

		nextStep: function() {
			if ( this.currentStep < this.totalSteps ) {
				this.currentStep++;
				this.updateStep();
				this.saveStep();
			} else {
				this.completeOnboarding();
			}
		},

		prevStep: function() {
			if ( this.currentStep > 1 ) {
				this.currentStep--;
				this.updateStep();
				this.saveStep();
			}
		},

		updateStep: function() {
			// Hide all steps
			$( '.dataviz-ai-onboarding-step' ).removeClass( 'active' );

			// Show current step
			$( '.dataviz-ai-onboarding-step[data-step="' + this.currentStep + '"]' ).addClass( 'active' );

			// Update progress indicator
			this.updateProgress();

			// Update button states
			this.updateButtons();
		},

		updateProgress: function() {
			const progress = ( this.currentStep / this.totalSteps ) * 100;
			$( '.dataviz-ai-onboarding-progress' ).css( 'width', progress + '%' );

			// Update step indicator
			$( '.dataviz-ai-onboarding-step-indicator' ).text(
				DatavizAIOnboarding.strings.step + ' ' + this.currentStep + ' ' + DatavizAIOnboarding.strings.of + ' ' + this.totalSteps
			);
		},

		updateButtons: function() {
			// Hide/show previous button
			if ( this.currentStep === 1 ) {
				$( '.dataviz-ai-onboarding-prev' ).hide();
			} else {
				$( '.dataviz-ai-onboarding-prev' ).show();
			}

			// Update next button text
			if ( this.currentStep === this.totalSteps ) {
				$( '.dataviz-ai-onboarding-next' ).text( DatavizAIOnboarding.strings.complete );
			} else {
				$( '.dataviz-ai-onboarding-next' ).text( DatavizAIOnboarding.strings.next );
			}
		},

		saveStep: function() {
			$.ajax( {
				url: DatavizAIOnboarding.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dataviz_ai_save_onboarding_step',
					nonce: DatavizAIOnboarding.nonce,
					step: this.currentStep,
				},
			} );
		},

		completeOnboarding: function() {
			const self = this;

			$.ajax( {
				url: DatavizAIOnboarding.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dataviz_ai_complete_onboarding',
					nonce: DatavizAIOnboarding.nonce,
				},
				success: function( response ) {
					if ( response.success ) {
						self.isCompleted = true;
						self.hideOverlay();
					}
				},
			} );
		},

		skipOnboarding: function() {
			const self = this;

			$.ajax( {
				url: DatavizAIOnboarding.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dataviz_ai_skip_onboarding',
					nonce: DatavizAIOnboarding.nonce,
				},
				success: function( response ) {
					if ( response.success ) {
						self.isCompleted = true;
						self.hideOverlay();
					}
				},
			} );
		},
	};

	// Initialize on document ready
	$( document ).ready( function() {
		Onboarding.init();
	} );

}( window.jQuery ) );
