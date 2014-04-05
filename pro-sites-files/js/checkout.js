jQuery(document).ready(function($) {
  $('div.pblg-checkout-opt').click(function() {
		var values = $('input', this).val().split(':');
		$('#psts_level').val(parseInt(values[0]));
		$('#psts_period').val(parseInt(values[1]));
		$('div.pblg-checkout-opt').removeClass('opt-selected');
		$('tr.psts_level td').removeClass('opt-selected');
    $(this).addClass('opt-selected');
    $(this).parent().addClass('opt-selected');
	});
	
  jQuery('#psts-coupon-link').click(function() {
    $('#psts-coupon-link').hide();
    $('#psts-coupon-code').show();
    return false;
  });
	
	jQuery('#psts-receipt-change a').click(function() {
    $('#psts-receipt-change').hide();
    $('#psts-receipt-input').show();
    return false;
  });
});