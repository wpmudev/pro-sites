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
                //$this.css({ "font-size": "12px" });
            }
        });

    }

    check_pricing_font_sizes();
    set_feature_heights();
    set_same_height( $('.pricing-column .title') );
    set_same_height( $('.pricing-column .summary'), false );
    set_same_height( $('.pricing-column .sub-title'), false );

    $( '.pricing-column [name=apply-coupon-link]').unbind( 'click' );
    $( '.pricing-column [name=apply-coupon-link]').click( function( e ) {
        var input_box = $( '.pricing-column .coupon input' );
        var icon = $('.pricing-column .coupon .coupon-status');
        var pos = input_box.position();

        $('.pricing-column .coupon-box').removeClass('coupon-valid');
        $('.pricing-column .coupon-box').removeClass('coupon-invalid');

            var code = $(input_box).val();

            /* Reset */
            $('.original-amount').removeClass('scratch');
            $('.coupon-amount').remove();
            $('.original-period').removeClass('hidden');
            $('.coupon-period').remove();

            /* Check Coupon AJAX */
            $.post(
                prosites_checkout.admin_ajax_url, {
                    action: 'apply_coupon_to_checkout',
                    'coupon_code': code
                }
            ).done( function( data, status ) {

                    var response = $.parseJSON( $( data ).find( 'response_data' ).text() );

                    if( response.valid ) {
                        $('.pricing-column .coupon-box').addClass('coupon-valid');
                    } else {
                        $('.pricing-column .coupon-box').addClass('coupon-invalid');
                    }

                    // Handle empty returns
                    var levels = response.levels;
                    if( typeof levels != 'undefined' ) {

                        $.each(levels, function (level_id, level) {

                            if (level.price_1_adjust) {
                                var plan_original = $('ul.psts-level-' + level_id + ' .price.price_1 plan-price.original-amount');

                                var original = $('ul.psts-level-' + level_id + ' .price.price_1 .original-amount');
                                $(original).after(level.price_1);
                                $(original).addClass('scratch');

                                // Period display needs adjusting
                                if (level.price_1_period != '') {
                                    var period_original = $('ul.psts-level-' + level_id + ' .price.price_1 .period.original-period');
                                    $(period_original).addClass('hidden');
                                    $(period_original).after(level.price_1_period);
                                }

                            }
                            if (level.price_3_adjust) {
                                var original = $('ul.psts-level-' + level_id + ' .price.price_3 .original-amount');

                                var monthly_original = $('ul.psts-level-' + level_id + ' .price_3 .monthly-price.original-amount');
                                var savings_original = $('ul.psts-level-' + level_id + ' .price_3 .savings-price.original-amount');

                                $(original).after(level.price_3);
                                $(monthly_original).after(level.price_3_monthly);
                                $(savings_original).after(level.price_3_savings);
                                $(original).addClass('scratch');
                                $(monthly_original).addClass('scratch');
                                $(savings_original).addClass('scratch');

                                // Period display needs adjusting
                                if (level.price_3_period != '') {
                                    var period_original = $('ul.psts-level-' + level_id + ' .price.price_3 .period.original-period');
                                    $(period_original).addClass('hidden');
                                    $(period_original).after(level.price_3_period);
                                }

                            }
                            if (level.price_12_adjust) {
                                var original = $('ul.psts-level-' + level_id + ' .price.price_12 .original-amount');

                                var monthly_original = $('ul.psts-level-' + level_id + ' .price_12 .monthly-price.original-amount');
                                var savings_original = $('ul.psts-level-' + level_id + ' .price_12 .savings-price.original-amount');

                                $(original).after(level.price_12);
                                $(monthly_original).after(level.price_12_monthly);
                                $(savings_original).after(level.price_12_savings);
                                $(original).addClass('scratch');
                                $(monthly_original).addClass('scratch');
                                $(savings_original).addClass('scratch');

                                // Period display needs adjusting
                                if (level.price_12_period != '') {
                                    var period_original = $('ul.psts-level-' + level_id + ' .price.price_12 .period.original-period');
                                    $(period_original).addClass('hidden');
                                    $(period_original).after(level.price_12_period);
                                }
                            }

                        });
                    }

                    /* Clear after AJAX return as bottom execution was synchronous */
                    check_pricing_font_sizes();
                    set_feature_heights();
                    set_same_height( $('.pricing-column .title') );
                    set_same_height( $('.pricing-column .summary'), false );
                    set_same_height( $('.pricing-column .sub-title'), false );

            } );

            /* Need to be run inside AJAX return as well */
            check_pricing_font_sizes();
            set_feature_heights();
            set_same_height( $('.pricing-column .title') );
            set_same_height( $('.pricing-column .summary'), false );
            set_same_height( $('.pricing-column .sub-title'), false );

    });

});