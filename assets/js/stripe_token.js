// JavaScript Document

// this identifies your website in the createToken call below
Stripe.setPublishableKey(stripe.publisher_key);

function stripeResponseHandler(status, response) {
	if (response.error) {
		// re-enable the submit button
		jQuery('#cc_stripe_checkout').removeAttr("disabled").show();
		jQuery('#stripe_processing').hide();
		// show the errors on the form
		jQuery("#psts-processcard-error").append('<div class="psts-error">' + response.error.message + '</div>');
	} else {
		var form = jQuery("#stripe-payment-form");
		// token contains id, last4, and card type
		var token = response['id'];
		// insert the token into the form so it gets submitted to the server
		form.append("<input type='hidden' name='stripeToken' value='" + token + "' />");
		// and submit
		form.get(0).submit();
	}
}

function stripePaymentFormSubmit( event ) {

    $ = jQuery;

    //Check if free option is selected, skip card details
    if (jQuery('#psts-radio-0-0').length > 0 && jQuery('#psts-radio-0-0').parent().hasClass('opt-selected')) {
        console.log('do not loop over');
        return true;
    }

    //skip checks for adding a coupon OR if using saved credit card info
    if ($('#coupon_code').val() || $('#wp_password').val()) {
        // disable the submit button to prevent repeated clicks
        $('#cc_stripe_checkout').attr("disabled", "disabled").hide();
        $('#stripe_processing').show();
        return true;
    }

    event.preventDefault();

    //clear errors
    $("#psts-processcard-error").empty();
    var is_error = false;

    //check form fields
    if ( $('#cc_name').val().length < 4 ) {
        $("#psts-processcard-error").append('<div class="psts-error">' + stripe.name + '</div>');
        is_error = true;
    }
    if ( !Stripe.card.validateCardNumber( $('#cc_number').val() )) {
        $("#psts-processcard-error").append('<div class="psts-error">' + stripe.number + '</div>');
        is_error = true;
    }
    if ( !Stripe.card.validateExpiry( $('#cc_month').val(), $('#cc_year').val() ) ) {
        $("#psts-processcard-error").append('<div class="psts-error">' + stripe.expiration + '</div>');
        is_error = true;
    }
    if ( !Stripe.card.validateCVC($('#cc_cvv2').val())) {
        $("#psts-processcard-error").append('<div class="psts-error">' + stripe.cvv2 + '</div>');
        is_error = true;
    }
    if (is_error) return false;

    // disable the submit button to prevent repeated clicks
    $('#cc_stripe_checkout').attr("disabled", "disabled").hide();
    $('#stripe_processing').show();


    // createToken returns immediately - the supplied callback submits the form if there are no errors
    Stripe.createToken({
        "name" : $('#cc_name').val(),
        "number" : $('#cc_number').val(),
        "cvc" : $('#cc_cvv2').val(),
        "exp_month" : $('#cc_month').val(),
        "exp_year" : $('#cc_year').val()
    }, stripeResponseHandler);

}

jQuery(document).ready(function($) {
    $("#stripe-payment-form").on( 'submit', stripePaymentFormSubmit );
});