jQuery(document).ready(function ($) {
    $('div.pblg-checkout-opt').click(function () {
        var values = $('input', this).val().split(':');

        $level = parseInt(values[0]);
        $period = parseInt(values[1]);

        $('#psts_level').val( $level );
        $('#psts_period').val( $period );
        $('div.pblg-checkout-opt').removeClass('opt-selected');
        $('tr.psts_level td').removeClass('opt-selected');
        $(this).addClass('opt-selected');
        $(this).parent().addClass('opt-selected');

        /** Hide Credit Card Options if Free Level is selected at checkout **/
        if ( $level == 0 && $period == 0 ) {
            //Stripe
            if ( jQuery('#psts-stripe-checkout').length > 0 ) {
                jQuery('#psts-stripe-checkout h2').hide();
            }
            //Paypal Pro heading
            if( jQuery('#psts-cc-checkout').length > 0 ){
                jQuery('#psts-cc-checkout h2').hide();
            }
            //Paypal
            if ( jQuery('#psts-paypal-checkout').length > 0 ) {
                jQuery('#psts-paypal-checkout').hide();
            }
            jQuery ('#psts-cc-table').fadeOut();
        }else {
            if ( jQuery('#psts-stripe-checkout').length > 0 ) {
                jQuery('#psts-stripe-checkout h2').show();
            }
            if( jQuery('#psts-cc-checkout').length > 0 ){
                jQuery('#psts-cc-checkout h2').show();
            }
            //Paypal
            if ( jQuery('#psts-paypal-checkout').length > 0 ) {
                jQuery('#psts-paypal-checkout').show();
            }
            jQuery ('#psts-cc-table').fadeIn();
        }

    });

    jQuery('#psts-coupon-link').click(function () {
        $('#psts-coupon-link').hide();
        $('#psts-coupon-code').show();
        return false;
    });

    jQuery('#psts-receipt-change a').click(function () {
        $('#psts-receipt-change').hide();
        $('#psts-receipt-input').show();
        return false;
    });
    // Bind events for the pricing table
    if (jQuery('#plans-table > .tab-menu').length > 0) {
        jQuery('#plans-table > .tab-menu > li > a')
            .unbind('click')
            .bind('click', function (event) {
            event.preventDefault();
            jQuery('#plans-table > .tab-menu > li').removeClass('selected');
            jQuery(this).parent().addClass('selected');
            var selected_period = jQuery('.tab-menu > .selected.period > a').data('period');
            jQuery('#psts_period').val(selected_period);
            jQuery('.plan.description > li.column').hide();
            var enabled_lists = ".plan.description > li.period_" + selected_period;
            jQuery('.plan.description > li.column:first-child()').show();
            jQuery(enabled_lists).show();
        });
    }
    if (jQuery('.button.choose-plan').length > 0) {
        jQuery('.button.choose-plan')
            .unbind('click')
            .bind('click', function (event) {
            event.preventDefault();
            var selected_period = jQuery('.tab-menu > .selected.period > a').data('period');
            var selected_level = jQuery(this).data('level');
            var selected_level_classname = jQuery(this).data('level-name');
            jQuery('.column').removeClass('selected');
            var selector = '.column.' + selected_level_classname;
            jQuery(selector).addClass('selected');
            jQuery('#psts_period').val(selected_period);
            jQuery('#psts_level').val(selected_level);
        });
        jQuery('.module.features .feature-name.column')
            .unbind('mouseover')
            .bind('mouseover', function (event) {
            jQuery('.helper.wrapper').hide();
            jQuery(this).find('.helper.wrapper').show();
        })
            .unbind('mouseout')
            .bind('mouseout', function (event) {
            jQuery('.helper.wrapper').hide();
        });
    }

    /* New checkout form */
    $('.pricing-column .period-selector select').change( function( e ) {
        var element = e.currentTarget;
        var period_class = $( element).val();

        $('.pricing-column [class*="price_"]').removeClass('hide');
        $('.pricing-column [class*="price_"]').hide();
        $('.pricing-column [class$="' + period_class + '"]').show();
        set_same_height( $('.pricing-column .title') );
        set_same_height( $('.pricing-column .summary'), false );
        set_same_height( $('.pricing-column .sub-title'), false );
    } );

    function set_same_height( elements, use_featured ) {
        var max_height = 0;

        if( typeof( use_featured ) == 'undefined' ) {
            use_featured = true;
        }

        // reset heights
        $( elements).css('height','auto');

        $.each( elements, function( index, item ) {
            var item_height = $(item).height();
            if( $( item).parents('.pricing-column.featured')[0] && use_featured ) {
            } else {
                if( max_height < item_height ) {
                    max_height = item_height;
                }
            }
        } );
        $.each( elements, function( index, item ) {
            if( $( item).parents('.pricing-column.featured')[0] && use_featured ) {
                //if( $( item).height < max_height ) {
                    $(item).height(max_height + 15);
                //}
                console.log(item);
            } else {
                $( item).height( max_height );
            }
        } );
    }

    function set_feature_heights() {
        var feature_sections = $( 'ul.feature-section' );
        var total = feature_sections.length;

        var rows = $( feature_sections[0] ).find( 'li');

        $.each( rows, function( index, item ) {

            var max_height = 0;
            var col_item = [];

            for( var i = 0; i < total; i++ ) {
                var cell = $( feature_sections[i]).find('li')[index];
                col_item[ col_item.length ] = cell;
                if( max_height < $( cell).height() ) {
                    max_height = $( cell).height();
                }
            }

            for( i = 0; i < total; i++ ) {
                $(col_item[i]).height(max_height);
            }

        } );

    }

    function check_pricing_font_sizes() {
        $(".pricing-column *").each( function () {
            var $this = $(this);
            if (parseInt($this.css("fontSize")) < 12) {
                $this.css({ "font-size": "12px" });
            }
        });
    }

    check_pricing_font_sizes();
    set_feature_heights();
    set_same_height( $('.pricing-column .title') );
    set_same_height( $('.pricing-column .summary'), false );
    set_same_height( $('.pricing-column .sub-title'), false );


});