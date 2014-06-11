// JavaScript Document

// this identifies your website in the createToken call below
Stripe.setPublishableKey(stripe.publisher_key);

function stripeResponseHandler(status, response) {
	if (response.error) {
		// re-enable the submit button
		jQuery('#cc_checkout').removeAttr("disabled").show();
		jQuery('#stripe_processing').hide();
		// show the errors on the form
		jQuery("#psts-processcard-error").append('<div class="psts-error">' + response.error.message + '</div>');
	} else {
		var form = jQuery("#payment-form");
		// token contains id, last4, and card type
		var token = response['id'];
		// insert the token into the form so it gets submitted to the server
		form.append("<input type='hidden' name='stripeToken' value='" + token + "' />");
		// and submit
		form.get(0).submit();
	}
}

jQuery(document).ready(function($) {
	$("#payment-form").submit(function(event) {
		
		//skip checks for adding a coupon
		if ( $('#coupon_code').val() ) {
			return true;
		}
		
		//clear errors
		$("#psts-processcard-error").empty();
		var is_error = false;
		
		//check form fields
		if ( $('#cc_name').val().length < 4 ) {
			$("#psts-processcard-error").append('<div class="psts-error">' + stripe.name + '</div>');
			is_error = true;
		}
		if ( !Stripe.validateCardNumber( $('#cc_number').val() )) {
			$("#psts-processcard-error").append('<div class="psts-error">' + stripe.number + '</div>');
			is_error = true;
		}
		if ( !Stripe.validateExpiry( $('#cc_month').val(), $('#cc_year').val() ) ) {
			$("#psts-processcard-error").append('<div class="psts-error">' + stripe.expiration + '</div>');
			is_error = true;
		}
		if ( !Stripe.validateCVC($('#cc_cvv2').val())) {
			$("#psts-processcard-error").append('<div class="psts-error">' + stripe.cvv2 + '</div>');
			is_error = true;
		}
		if (is_error) return false;
		
		// disable the submit button to prevent repeated clicks
		$('#cc_checkout').attr("disabled", "disabled").hide();
		$('#stripe_processing').show();
		
	
		// createToken returns immediately - the supplied callback submits the form if there are no errors
		Stripe.createToken({
			name: $('#cc_name').val(),
			number: $('#cc_number').val(),
			cvc: $('#cc_cvv2').val(),
			exp_month: $('#cc_month').val(),
			exp_year: $('#cc_year').val()
			}, stripeResponseHandler);
			return false; // submit from callback
	});
});