// Get card update button.
var updateCardButton = document.getElementById( 'psts-stripe-card-update' );
// Get existing card button.
var existingCardButton = document.getElementById( 'psts-existing-submit' );

// Process payment with existing card.
if ( existingCardButton ) {
// On button click, submit the form.
	existingCardButton.onclick = function () {
		document.getElementById( 'psts-stripe-checkout' ).submit();
	};
}

// We have update form and required vars.
if ( updateCardButton && typeof window.psts_stripe != 'undefined' ) {
	// Configure Stripe checkout.
	var handler = StripeCheckout.configure( {
		key: window.psts_stripe.publisher_key,
		image: window.psts_stripe.image,
		locale: window.psts_stripe.locale,

		// Update the card data in backend.
		token: function ( token, args ) {
			// Get the update form.
			var updateForm = document.getElementById( 'psts-stripe-update' );
			// Create new input.
			var tokenInput = document.createElement( 'INPUT' );
			// Set required data.
			tokenInput.setAttribute( 'type', 'hidden' );
			tokenInput.setAttribute( 'name', 'stripe_token' );
			tokenInput.setAttribute( 'value', token.id );
			updateForm.appendChild( tokenInput );
			// Submit the form now.
			updateForm.submit();
		}
	} );

	// Call update form on button click.
	updateCardButton.addEventListener( 'click', function ( e ) {
		// Open Checkout with further options:
		handler.open( {
			name: window.psts_stripe.name,
			description: window.psts_stripe.description,
			email: window.psts_stripe.email
		} );
		e.preventDefault();
	} );

	// Close Checkout on page navigation:
	window.addEventListener( 'popstate', function () {
		handler.close();
	} );
}