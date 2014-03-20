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
  // Bind events for the pricing table
  if ( jQuery ( '#plans-table > .tab-menu' ).length > 0 ) {
  	jQuery ( '#plans-table > .tab-menu > li > a' )
  		.unbind ( 'click' )
  		.bind ( 'click', function ( event ) {
  			event.preventDefault ();
  			jQuery ( '#plans-table > .tab-menu > li' ).removeClass ( 'selected' );
  			jQuery ( this ).parent ().addClass ( 'selected' );
  			var selected_period = jQuery ( '.tab-menu > .selected.period > a' ).data ('period');
  			jQuery ( '#psts_period' ).val ( selected_period );
  			jQuery ( '.plan.description > li.column' ).hide();
  			var enabled_lists = ".plan.description > li.period_" + selected_period;
  			jQuery ( '.plan.description > li.column:first-child()' ).show();
  			jQuery ( enabled_lists ).show();
  		});
  }
  if ( jQuery ( '.button.choose-plan' ).length > 0 ) {
  	jQuery ( '.button.choose-plan' )
  		.unbind ( 'click' )
  		.bind ( 'click', function ( event ) {
  			event.preventDefault ();
  			var selected_period = jQuery ( '.tab-menu > .selected.period > a' ).data ('period');
  			var selected_level = jQuery ( this ).data ('level');
  			var selected_level_classname = jQuery ( this ).data ('level-name');
  			jQuery ( '.column' ).removeClass ( 'selected' );
  			var selector = '.column.' + selected_level_classname;
  			jQuery ( selector ).addClass ( 'selected' );
  			jQuery ( '#psts_period' ).val ( selected_period );
  			jQuery ( '#psts_level' ).val ( selected_level ); 
  		});
  	jQuery ( '.module.features .feature-name.column' )
  		.unbind ( 'mouseover' )
  		.bind ( 'mouseover', function ( event ) {
  			jQuery ( '.helper.wrapper' ).hide ();
  		 	jQuery ( this ).find ( '.helper.wrapper' ).show ();
  		})
  		.unbind ( 'mouseout' )
  		.bind ( 'mouseout', function ( event ) {
  		 	jQuery ( '.helper.wrapper' ).hide ();
  		});
  }
});