Requests = {
    QueryString: function ( item ) {
        var svalue = location.search.match( new RegExp( "[\?\&]" + item + "=([^\&]*)(\&?)", "i" ) );
        return svalue ? svalue[ 1 ] : false;
    }
}

jQuery( document ).ready( function ( $ ) {

    $.unserialize = function ( serializedString ) {
        var str = decodeURI( serializedString );
        var pairs = str.split( '&' );
        var obj = {}, p, idx;
        for ( var i = 0, n = pairs.length; i < n; i++ ) {
            p = pairs[ i ].split( '=' );
            idx = p[ 0 ];
            if ( obj[ idx ] === undefined ) {
                obj[ idx ] = decodeURIComponent( p[ 1 ] );
            } else {
                if ( typeof obj[ idx ] == "string" ) {
                    obj[ idx ] = [ obj[ idx ] ];
                }
                obj[ idx ].push( decodeURIComponent( p[ 1 ] ) );
            }
        }
        return obj;
    };
    /**
     * Checks for the length of element and scroll the division up
     * @param the_element
     */
    function scroll_top( the_element ) {
        if ( typeof the_element != 'undefined' && the_element.length != 0 ) {
            $( 'html, body' ).animate( {
                scrollTop: the_element.offset().top - 100
            }, 1000 );
        }
    }

    $( 'div.pblg-checkout-opt' ).click( function ( e ) {
        var target = e.currentTarget;
        //var values = $('input', this).val().split(':');
        var level_parent = $( target ).parents( 'tr' )[ 0 ];
        var pattern = /level-\d+/i;
        var level = parseInt( pattern.exec( $( level_parent ).attr( 'class' ) )[ 0 ].replace( 'level-', '' ) );
        var price_child = $( target ).find( '.price' );
        pattern = /price_\d+/i;
        var period = parseInt( pattern.exec( $( price_child ).attr( 'class' ) )[ 0 ].replace( 'price_', '' ) );

        $( 'div.pblg-checkout-opt' ).removeClass( 'opt-selected' );

        $( target ).addClass( 'opt-selected' );

        var free_link = $( target ).is( 'a' );
        var site_registered = "yes" == $( '#prosites-checkout-table' ).attr( 'data-site-registered' );

        var blog_id = Requests.QueryString( "bid" );
        var action = Requests.QueryString( "action" );
        var new_blog = false;
        if ( false != action && 'new_blog' == action ) {
            new_blog = true;
        }
        new_blog = prosites_checkout.new_blog != 'false' ? new_blog : false;

        // Hide login link if its visible
        $( '.login-existing' ).hide();

        if ( prosites_checkout.logged_in && !new_blog ) {
            $( '.checkout-gateways.hidden' ).removeClass( 'hidden' );
            scroll_top( $( '.checkout-gateways') );
        } else {
            $( '#prosites-signup-form-checkout' ).removeClass( 'hidden' );
            scroll_top( $( '#prosites-signup-form-checkout' ) );
        }

        if ( free_link ) {
            level = 'free';
            if ( site_registered ) {
                $( '.gateways.checkout-gateways' ).addClass( 'hidden' );
            }
        } else {
            if ( site_registered ) {
                $( '.gateways.checkout-gateways' ).removeClass( 'hidden' );
            }
        }

        // Set the level required for gateways... but also set it on the checkout table
        $( '.gateways [name=level]' ).val( level );
	    $( '.gateways [name=period]' ).val( period );
        $( '#prosites-checkout-table' ).attr( 'data-level', level );

    } );

    jQuery( '#psts-coupon-link' ).click( function () {
        $( '#psts-coupon-link' ).hide();
        $( '#psts-coupon-code' ).show();
        return false;
    } );

    jQuery( '#psts-receipt-change a' ).click( function () {
        $( '#psts-receipt-change' ).hide();
        $( '#psts-receipt-input' ).show();
        return false;
    } );
    // Bind events for the pricing table
    if ( jQuery( '#plans-table > .tab-menu' ).length > 0 ) {
        jQuery( '#plans-table > .tab-menu > li > a' )
            .unbind( 'click' )
            .bind( 'click', function ( event ) {
                event.preventDefault();
                jQuery( '#plans-table > .tab-menu > li' ).removeClass( 'selected' );
                jQuery( this ).parent().addClass( 'selected' );
                var selected_period = jQuery( '.tab-menu > .selected.period > a' ).data( 'period' );
                jQuery( '#psts_period' ).val( selected_period );
                jQuery( '.plan.description > li.column' ).hide();
                var enabled_lists = ".plan.description > li.period_" + selected_period;
                jQuery( '.plan.description > li.column:first-child()' ).show();
                jQuery( enabled_lists ).show();
            } );
    }
    if ( jQuery( '.button.choose-plan' ).length > 0 ) {
        jQuery( '.button.choose-plan' )
            .unbind( 'click' )
            .bind( 'click', function ( event ) {
                event.preventDefault();
                var selected_period = jQuery( '.tab-menu > .selected.period > a' ).data( 'period' );
                var selected_level = jQuery( this ).data( 'level' );
                var selected_level_classname = jQuery( this ).data( 'level-name' );
                jQuery( '.column' ).removeClass( 'selected' );
                var selector = '.column.' + selected_level_classname;
                jQuery( selector ).addClass( 'selected' );
                jQuery( '#psts_period' ).val( selected_period );
                jQuery( '#psts_level' ).val( selected_level );
            } );
        jQuery( '.module.features .feature-name.column' )
            .unbind( 'mouseover' )
            .bind( 'mouseover', function ( event ) {
                jQuery( '.helper.wrapper' ).hide();
                jQuery( this ).find( '.helper.wrapper' ).show();
            } )
            .unbind( 'mouseout' )
            .bind( 'mouseout', function ( event ) {
                jQuery( '.helper.wrapper' ).hide();
            } );
    }

    /* New checkout form */
    $( '.pricing-column .period-selector select, .period-selector-container input' ).change( function ( e ) {
        var element = e.currentTarget;
        var period_class = $( element ).val();
        var period = parseInt( period_class.replace( 'price_', '' ) );

        // Set the period required for gateways... also set it on the pricing table
        $( '.gateways [name=period]' ).val( period );
        $( '#prosites-checkout-table' ).attr( 'data-period', period );

        $( '.pricing-column [class*="price_"]' ).removeClass( 'hide' );
        $( '.pricing-column [class*="price_"]' ).hide();
        $( '.pricing-column [class$="' + period_class + '"]' ).show();
        set_same_height( $( '.pricing-column .title' ) );
        set_same_height( $( '.pricing-column .summary' ), false );
        set_same_height( $( '.pricing-column .sub-title' ), false );
    } );

	function get_offset_diff( element ) {
		//Get Parent
		var parent = element.parent();
		//If period selector is not in featured column, we'll need to adjust the height
		if ( parent.find('li.period-selector').length == 0 ) {
			//Calculate the difference in offset of feature section
			var level1_offset = jQuery('.psts-level-1 .feature-section').offset();
			var level0_offset = jQuery('.psts-level-0 .feature-section').offset();

			if (level0_offset) {
				level0_offset = level0_offset.top;
			}

			if (level1_offset) {
				level1_offset = level1_offset.top;
			}
			if (level1_offset > level0_offset) {
				var pos_diff = level1_offset - level0_offset;
				return pos_diff;
			}
		}
		return 0;
	}

    function set_same_height( elements, use_featured ) {
        var max_height = 0;

        if ( typeof( use_featured ) == 'undefined' ) {
            use_featured = true;
        }

        // reset heights
        $( elements ).css( 'height', 'auto' );

        $.each( elements, function ( index, item ) {
            var item_height = $( item ).height();
            if ( $( item ).parents( '.pricing-column.featured' )[ 0 ] && use_featured ) {
            } else {
                if ( max_height < item_height ) {
                    max_height = item_height;
                }
            }
        } );
	    $.each(elements, function (index, item) {
		    var curr_element = jQuery(item);
		    var li_height = 0;
		    var is_featured_column_notitle = false;
		    is_featured_column_notitle = curr_element.hasClass('title') && curr_element.hasClass('no-title') && curr_element.hasClass('no-summary') && curr_element.parent().hasClass('featured');
		    //Adjust height if period selector is on top
		    if ( is_featured_column_notitle ) {
			    li_height = '225';
		    }else{
			    li_height = max_height;
		    }
            //For Single Period, Single Level, Set height auto of title
            if( jQuery(elements).hasClass('title') ) {
                var period_selector = jQuery('.period-selector').length;
                var pricing_column = jQuery('.pricing-column').length;
                if( period_selector == 0 && pricing_column == 1 ) {
                    $(item).css( { 'height' : 'auto' } );
                    return;
                }
            }
		    if ($(item).parents('.pricing-column.featured')[0] && use_featured && li_height > 0 ) {
			    //if( $( item).height < max_height ) {
			    if( !is_featured_column_notitle ) {
				    $(item).css( { 'height' : li_height + 15 } );
			    }else{
				    $(item).height(li_height + 15);
			    }
			    //}
		    } else {
			    $(item).height( li_height );
		    }
	    });
    }

    function set_feature_heights() {
        var feature_sections = $( 'ul.feature-section' );
        var total = feature_sections.length;

        var rows = $( feature_sections[ 0 ] ).find( 'li' );

        $.each( rows, function ( index, item ) {

            var max_height = 0;
            var col_item = [];

            for ( var i = 0; i < total; i++ ) {
                var cell = $( feature_sections[ i ] ).find( 'li' )[ index ];
                col_item[ col_item.length ] = cell;
                if ( max_height < $( cell ).height() ) {
                    max_height = $( cell ).height();
                }
            }

            for ( i = 0; i < total; i++ ) {
                $( col_item[ i ] ).height( max_height );
            }

        } );

    }

    function check_pricing_font_sizes() {
        $( ".pricing-column *" ).each( function () {
            var $this = $( this );
            if ( parseInt( $this.css( "fontSize" ) ) < 12 ) {
                //$this.css({ "font-size": "12px" });
            }
        } );

    }

    check_pricing_font_sizes();
    set_feature_heights();
    set_same_height( $( '.pricing-column .title' ) );
    set_same_height( $( '.pricing-column .summary' ), false );
    set_same_height( $( '.pricing-column .sub-title' ), false );

    // =========== APPLY COUPONS =========== //
    $( '#prosites-checkout-table [name=apply-coupon-link]' ).unbind( 'click' );
    $( '#prosites-checkout-table [name=apply-coupon-link]' ).click( function ( e ) {
        var input_box = $( '#prosites-checkout-table .coupon-box input' );
        var icon = $( '#prosites-checkout-table .coupon .coupon-status' );
        var pos = input_box.position();

        $( '#prosites-checkout-table .coupon-box' ).removeClass( 'coupon-valid' );
        $( '#prosites-checkout-table .coupon-box' ).removeClass( 'coupon-invalid' );

        var code = $( input_box ).val();

        /* Reset */
        $( '.original-amount' ).removeClass( 'scratch' );
        $( '.coupon-amount' ).remove();
        $( '.original-period' ).removeClass( 'hidden' );
        $( '.coupon-period' ).remove();

        /* Check Coupon AJAX */
        $.post(
            prosites_checkout.ajax_url, {
                action: 'apply_coupon_to_checkout',
                'coupon_code': code
            }
        ).done( function ( data, status ) {

                var response = $.parseJSON( $( data ).find( 'response_data' ).text() );

                if ( response.valid ) {
                    $( '#prosites-checkout-table .coupon-box' ).addClass( 'coupon-valid' );
                } else {
                    $( '#prosites-checkout-table .coupon-box' ).addClass( 'coupon-invalid' );
                }

                // Handle empty returns
                var levels = response.levels;
                if ( typeof levels != 'undefined' ) {

                    $.each( levels, function ( level_id, level ) {

                        if ( level.price_1_adjust ) {
                            var plan_original = $( 'ul.psts-level-' + level_id + ' .price.price_1 plan-price.original-amount' );

                            var original = $( 'ul.psts-level-' + level_id + ' .price.price_1 .original-amount' );

                            if ( original.length == 0 ) {
                                original = $( 'tr.level-' + level_id + ' .price.price_1 .original-amount' );
                            }

                            $( original ).after( level.price_1 );
                            $( original ).addClass( 'scratch' );

                            // Period display needs adjusting
                            if ( level.price_1_period != '' ) {
                                var period_original = $( 'ul.psts-level-' + level_id + ' .price.price_1 .period.original-period' );
                                if ( period_original.length == 0 ) {
                                    period_original = $( 'tr.level-' + level_id + ' .price.price_1 .original-period' );
                                }
                                $( period_original ).addClass( 'hidden' );
                                $( period_original ).after( level.price_1_period );
                            }

                        }
                        if ( level.price_3_adjust ) {
                            var original = $( 'ul.psts-level-' + level_id + ' .price.price_3 .original-amount' );
                            if ( original.length == 0 ) {
                                original = $( 'tr.level-' + level_id + ' .price.price_3 .original-amount' );
                            }

                            var monthly_original = $( 'ul.psts-level-' + level_id + ' .price_3 .monthly-price.original-amount' );
                            var savings_original = $( 'ul.psts-level-' + level_id + ' .price_3 .savings-price.original-amount' );
                            if ( monthly_original.length == 0 ) {
                                monthly_original = $( 'tr.level-' + level_id + ' .level-summary.price_3 .monthly-price.original-amount' );
                            }
                            if ( savings_original.length == 0 ) {
                                savings_original = $( 'tr.level-' + level_id + ' .level-summary.price_3 .savings-price.original-amount' );
                            }

                            $( original ).after( level.price_3 );
                            $( monthly_original ).after( level.price_3_monthly );
                            $( savings_original ).after( level.price_3_savings );
                            $( original ).addClass( 'scratch' );
                            $( monthly_original ).addClass( 'scratch' );
                            $( savings_original ).addClass( 'scratch' );

                            // Period display needs adjusting
                            if ( level.price_3_period != '' ) {
                                var period_original = $( 'ul.psts-level-' + level_id + ' .price.price_3 .period.original-period' );
                                if ( period_original.length == 0 ) {
                                    period_original = $( 'tr.level-' + level_id + ' .price.price_3 .period.original-period' );
                                }
                                $( period_original ).addClass( 'hidden' );
                                $( period_original ).after( level.price_3_period );
                            }

                        }
                        if ( level.price_12_adjust ) {
                            var original = $( 'ul.psts-level-' + level_id + ' .price.price_12 .original-amount' );
                            if ( original.length == 0 ) {
                                original = $( 'tr.level-' + level_id + ' .price.price_12 .original-amount' );
                            }

                            var monthly_original = $( 'ul.psts-level-' + level_id + ' .price_12 .monthly-price.original-amount' );
                            var savings_original = $( 'ul.psts-level-' + level_id + ' .price_12 .savings-price.original-amount' );
                            if ( monthly_original.length == 0 ) {
                                monthly_original = $( 'tr.level-' + level_id + ' .level-summary.price_12 .monthly-price.original-amount' );
                            }
                            if ( savings_original.length == 0 ) {
                                savings_original = $( 'tr.level-' + level_id + ' .level-summary.price_12 .savings-price.original-amount' );
                            }

                            $( original ).after( level.price_12 );
                            $( monthly_original ).after( level.price_12_monthly );
                            $( savings_original ).after( level.price_12_savings );
                            $( original ).addClass( 'scratch' );
                            $( monthly_original ).addClass( 'scratch' );
                            $( savings_original ).addClass( 'scratch' );

                            // Period display needs adjusting
                            if ( level.price_12_period != '' ) {
                                var period_original = $( 'ul.psts-level-' + level_id + ' .price.price_12 .period.original-period' );
                                if ( period_original.length == 0 ) {
                                    period_original = $( 'tr.level-' + level_id + ' .price.price_12 .period.original-period' );
                                }
                                $( period_original ).addClass( 'hidden' );
                                $( period_original ).after( level.price_12_period );
                            }
                        }

                    } );
                }

                /* Clear after AJAX return as bottom execution was synchronous */
                check_pricing_font_sizes();
                set_feature_heights();
                set_same_height( $( '.pricing-column .title' ) );
                set_same_height( $( '.pricing-column .summary' ), false );
                set_same_height( $( '.pricing-column .sub-title' ), false );

            } );

        /* Need to be run inside AJAX return as well */
        check_pricing_font_sizes();
        set_feature_heights();
        set_same_height( $( '.pricing-column .title' ) );
        set_same_height( $( '.pricing-column .summary' ), false );
        set_same_height( $( '.pricing-column .sub-title' ), false );

    } );


    // ====== CHOOSE BUTTON ======= //
    $( '.choose-plan-button, .free-plan-link a' ).unbind( 'click' );
    $( '.choose-plan-button, .free-plan-link a' ).click( function ( e ) {

        var target = e.currentTarget;
        var free_link = $( target ).is( 'a' );
        var site_registered = "yes" == $( '#prosites-checkout-table' ).attr( 'data-site-registered' );
        var button_text = '';

        var blog_id = Requests.QueryString( "bid" );
        var action = Requests.QueryString( "action" );
	    var new_blog = false;
	    if ( false != action && 'new_blog' == action ) {
		    new_blog = true;
	    }
	    new_blog = prosites_checkout.new_blog != 'false' ? new_blog : false;

        // Hide login link if its visible
        $( '.login-existing form' ).hide();
        $( '.login-existing' ).hide();

        if ( prosites_checkout.logged_in ) {
            button_text = prosites_checkout.button_choose;
        } else {
            button_text = prosites_checkout.button_signup;
        }

        // Reset button text
        $( '.choose-plan-button' ).html( button_text );

        if ( prosites_checkout.logged_in && !new_blog ) {
            var gateways =  $( '.checkout-gateways.hidden' );
            gateways.removeClass( 'hidden' );
            scroll_top(gateways);
        } else {
            var checkout_form = $( '#prosites-signup-form-checkout' );
            checkout_form.removeClass( 'hidden' );
            scroll_top(checkout_form);
        }

        $( '.chosen-plan' ).removeClass( 'chosen-plan' );
        $( '.not-chosen-plan' ).removeClass( 'not-chosen-plan' );

        var parent = $( target ).parents( 'ul' )[ 0 ];
        var level = 0;

        if ( free_link ) {
            level = 'free';
            //if( site_registered ) {
            $( '.gateways.checkout-gateways' ).addClass( 'hidden' );
            //}
        } else {
            if ( site_registered ) {
                $( '.gateways.checkout-gateways' ).removeClass( 'hidden' );
            }
            var classes = $( parent ).attr( 'class' );
            classes = classes.split( ' ' );

            // Extract the level number
            $.each( classes, function ( idx, val ) {
                var num = parseInt( val.replace( 'psts-level-', '' ) );
                if ( !isNaN( num ) ) {
                    level = num;
                }
            } );

            $( parent ).addClass( 'chosen-plan' );
            $( parent ).siblings( 'ul' ).addClass( 'not-chosen-plan' );
        }

        // Set the level required for gateways... but also set it on the checkout table
        $( '.gateways [name=level]' ).val( level );
        $( '#prosites-checkout-table' ).attr( 'data-level', level );

        //Update Period as well
        var period_selector = $( '.period-selector select').length > 0 ? $('.period-selector select') : ( $('input[name="period-selector-top"]').length > 0 ? $('input[name="period-selector-top"]:checked') : '' );
	if( typeof( period_selector ) !== 'undefined' && '' != period_selector ) {
	    var period_class = period_selector.val();
	    var period = 0;
	    if (typeof period_class !== 'undefined') {
	        period = parseInt(period_class.replace('price_', ''));
	    } else {
	        period = parseInt($('[name=single_period]').html());
	    }
	    $('.gateways [name=period]').val(period);
	    $('#prosites-checkout-table').attr('data-period', period);
	}

	// NBT support: Update templates or plans if required.
	if ( prosites_checkout.nbt_update_required ) {
	    nbt_template_update( level );
	}
    } );

    var period_value = $( '#stripe-payment-form [name=period]' ).val();
    // For first period selector layout.
    var period_option = $( '.period-option.period' + period_value );
    // For second period selector layout.
    var period_selector = $( '.period-selector option[value="price_' + period_value + '"]' );
    // If period value is set, make it selected.
    if( period_option.length ) {
        period_option.trigger( 'click' );
    } else if ( period_selector.length ) {
        period_selector.prop( 'selected', 'selected' ).change();
    }

    //More than 1 gateway?, Tabs
    if( jQuery('#gateways>div').length > 1 ) {
        $('#gateways').tabs();
    }

    // Cancellation confirmation
    $( 'a.cancel-prosites-plan' ).click( function ( e ) {

        if ( confirm( prosites_checkout.confirm_cancel ) ) {

        } else {
            e.preventDefault();
        }

    } );

    // Check user/blog availability
    $( '#check-prosite-blog' ).unbind( "click" );
    $( '#check-prosite-blog' ).on( "click", bind_availability_check );

    function bind_availability_check( e ) {
        e.preventDefault();
        e.stopPropagation();

        var form_data = $( '#prosites-user-register' ).serialize();
        var form_fields = getFormData( $( '#prosites-user-register' ) );
        //console.log( form_data );
        $( '#prosites-user-register p.error' ).remove();
        $( '.trial_msg' ).remove();
        $( '.reserved_msg' ).remove();

        $( '#check-prosite-blog' ).addClass( "hidden" );
        $( '#registration_processing' ).removeClass( "hidden" );

        $( '.input_available' ).remove();

        var level = $( '#prosites-checkout-table' ).attr( 'data-level' );
        var period = $( '#prosites-checkout-table' ).attr( 'data-period' );
        var coupon = $('input[name="apply-coupon"]').val();
        form_data += '&' + $.param( { 'coupon' : coupon } );

        $.post(
            prosites_checkout.ajax_url, {
                action: 'check_prosite_blog',
                data: form_data,
                level: level,
                period: period
            }
        ).done( function ( data, status ) {
                $( '#check-prosite-blog' ).removeClass( "hidden" );
                $( '#registration_processing' ).addClass( "hidden" );
                post_registration_process( data, status, form_fields );
            } );

    }

    function getFormData( $form ) {
        var unindexed_array = $form.serializeArray();
        var indexed_array = {};

        $.map( unindexed_array, function ( n, i ) {
            indexed_array[ n[ 'name' ] ] = n[ 'value' ];
        } );

        return indexed_array;
    }

    function position_field_available_tick( field ) {
        var pos = $( field ).position();
        var width = $( field ).innerWidth();
        var height = $( field ).innerHeight();
        var item_h = $( field + ' + .input_available' ).innerHeight();
        var item_w = $( field + ' + .input_available' ).innerWidth();
        $( field + ' + .input_available' ).css( 'top', Math.ceil( pos.top + (height - item_h) / 2 ) + 'px' );
        $( field + ' + .input_available' ).css( 'left', Math.ceil( pos.left + width - ( item_w * 2 ) ) + 'px' );

    }

    // NBT support: Update plans on template selection.
    $( 'select[name=blog_template]' ).on( "change", nbt_level_update );

    /**
     * NBT Support: Update the pro sites levels.
     *
     * To support New Blog Templates, update the available plans
     * when a template is selected from the NBT select box.
     */
    function nbt_level_update() {

        // NBT temmplate selector.
        var template_selector = $( 'select[name=blog_template]' );
        var plan_selector = $( '.pricing-column' );
        // Selected template.
        var template = template_selector.val();

        // Send ajax request if a template is selected.
        if ( plan_selector.length && 'none' != template ) {
            // Show the processing message.
            $( '#nbt_processing' ).removeClass( 'hidden' );
            // Send ajax request to get unavailable plans.
            $.post(
                prosites_checkout.ajax_url, {
                    action: 'update_nbt_levels',
                    'template': template_selector.val()
                }
            ).done(function ( response ) {
                // If nothing found, show all plans.
                if ( '' != response ) {
                    // If any of the plans are unavailable, hide them.
                    $.each( response, function ( key, value ) {
                        var level_ul = $( '.pricing-column.psts-level-' + value );
                        if ( level_ul.length ) {
                            $('.pricing-column.psts-level-' + value).hide();
                        }
                    });
                } else {
                    // If nothing found, show all.
                    $( '.pricing-column' ).show();
                }
                // After processing, hide the processing message.
                $( '#nbt_processing' ).addClass( 'hidden' );
            });
        } else if ( 'none' == template || '' == template ) {
            // If no option or none is selected, show all levels.
            $( '.pricing-column' ).show();
        }
    }

    /**
     * NBT Support: Update the nbt templates.
     *
     * To support New Blog Templates, update the available templates
     * when a plan is selected from the pricing table.
     *
     * @param level
     */
    function nbt_template_update( level ) {

        // NBT temmplate selector.
        var template_selector = $( 'select[name=blog_template]' );

        if ( template_selector.length ) {

            // Show the processing message.
            $('#nbt_processing').removeClass("hidden");
            // Send ajax request to update templates.
            $.post(
                prosites_checkout.ajax_url, {
                    action: 'update_nbt_templates',
                    'level': level
                }
            ).done(function (response) {
                // Clear templates dropdown.
                template_selector.html('');
                // If response is not empty, append new option for each templates.
                if ('' != response) {
                    template_selector.append(response);
                }
                // After processing, hide the processing message.
                $('#nbt_processing').addClass('hidden');
            });
        }
    }

    function post_registration_process( data, status, form_data ) {

        var response = $.parseJSON( $( data ).find( 'response_data' ).text() );

        if ( response === null || typeof response == 'undefined' ) {
            return false;
        }

        // Trial setup form for non-recurring settings
        if ( typeof response.show_finish != 'undefined' && response.show_finish === true ) {
            $( '#prosites-checkout-table' ).replaceWith( response.finish_content );
            $( '#prosites-signup-form-checkout' ).remove();
            $( '#gateways' ).remove();
            return false;
        }

        // Get fresh signup form
        if ( typeof response.form != 'undefined' ) {

            $( '#prosites-signup-form-checkout' ).replaceWith( response.form );
            $( '#check-prosite-blog' ).unbind( "click" );
            $( '#check-prosite-blog' ).on( "click", bind_availability_check );
            $( '#prosites-signup-form-checkout' ).removeClass( 'hidden' );
            $( '#prosites-checkout-table' ).attr( 'data-site-registered', 'yes' );

            // Reset values...
            $.each( form_data, function ( key, val ) {
                // Restore values
                if ( key != "signup_form_id" && key != "_signup_form" ) {
                    $( "[name='" + key + "']" ).val( val );
                }
            } );

        }

        // Get fresh gateways form
        if ( typeof response.gateways_form != 'undefined' ) {
            $( '.gateways.checkout-gateways' ).replaceWith( response.gateways_form );

            var is_free = "free" == $( '#prosites-checkout-table' ).attr( 'data-level' );

            // Reset the levels
            $( '.gateways [name=level]' ).val( $( '#prosites-checkout-table' ).attr( 'data-level' ) );
            $( '.gateways [name=period]' ).val( $( '#prosites-checkout-table' ).attr( 'data-period' ) );

            // Rebind Stripe -- find a generic way to make it easier for custom gateways
            if ( typeof stripePaymentFormSubmit !== 'undefined' ) {
                $( "#stripe-payment-form" ).unbind( "submit" );
                $( "#stripe-payment-form" ).on( 'submit', stripePaymentFormSubmit );
            }

            $( '#gateways' ).tabs();
            // if ( !is_free ) {
                $( '.gateways.checkout-gateways' ).removeClass( 'hidden' );
            // }
        }

	    var new_blog = false;
	    if ( typeof(action) != 'undefined' && false != action && 'new_blog' == action ) {
		    new_blog = true;
	    }
	    new_blog = prosites_checkout.new_blog != 'false' ? new_blog : false;

        if ( typeof response.username_available != 'undefined' && true === response.username_available ) {
            if ( new_blog ) {
                $( '[name=user_name]' ).after( '<i class="input_available"></i>' );
                position_field_available_tick( '[name=user_name]' );
            }
        }
        if ( typeof response.email_available != 'undefined' && true === response.email_available ) {
            if ( new_blog ) {
                $( '[name=user_email]' ).after( '<i class="input_available"></i>' );
                position_field_available_tick( '[name=user_email]' );
            }
        }
        if ( typeof response.blogname_available != 'undefined' && true === response.blogname_available ) {
            $( '[name=blogname]' ).after( '<i class="input_available"></i>' );
            position_field_available_tick( '[name=blogname]' );
        }
        if ( typeof response.blog_title_available != 'undefined' && true === response.blog_title_available ) {
            $( '[name=blog_title]' ).after( '<i class="input_available"></i>' );
            position_field_available_tick( '[name=blog_title]' );
        }

        if ( typeof response.trial_message != 'undefined' ) {
            $( '#prosites-signup-form-checkout' ).replaceWith( response.trial_message );
            $( '.input_available' ).remove();
        }
        if ( typeof response.reserved_message != 'undefined' ) {
            $( '#prosites-signup-form-checkout' ).replaceWith( response.reserved_message );
            $( '.input_available' ).remove();
        }

        $.event.trigger( 'psts:gateways_refreshed' );

    }

    $( '.login-existing .login-toggle' ).on( 'click', function ( e ) {
        e.preventDefault();
        e.stopPropagation();
        $( '.login-existing form' ).toggle();
    } );

    // Add additional class to custom feature that is text only
    $( '.pricing-column' ).each( function ( i, item ) {

        if ( !$( item ).hasClass( 'psts-level-0' ) && 0 == $(item ).find('.feature-text').siblings('.feature-indicator').size() ) {
            $(item ).find('.feature-text' ).addClass( 'text-only' );
        }

    } );

    $('.coupon-wrapper').css('width', $('.coupon-wrapper .coupon-box input').width() + $('.coupon-wrapper .coupon-box button').width() + 70 );

    // Adjust period selector width
    //$('.period-selector-container').css('width', $('.coupon-wrapper .coupon-box input').width() + $('.coupon-wrapper .coupon-box button').width() + 70 );
    var width = 0;
    $('.period-selector-container label' ).each( function( i, item ) {
        //width += $( item );
        width += parseInt( $( item ).find( 'div' ).css('width' ).replace('px', '') );
    } );
    $('.period-selector-container').css('width', width + 2 );

    // Confirm before cancelling Stripe subscriptions.
    if ( typeof stripe_checkout !== 'undefined' ) {
        $( 'a#stripe_cancel' ).click( function () {
            return confirm( prosites_checkout.confirm_cancel );
        });
    }

    // Confirm before cancelling PayPal subscription.
    if ( typeof paypal_checkout !== 'undefined' ) {
        $( 'form' ).submit( function () {
            $( '#cc_paypal_checkout' ).hide();
            $( '#paypal_processing' ).show();
        } );
        $( 'a#pypl_cancel' ).click( function ( e ) {
            return confirm( prosites_checkout.confirm_cancel );
        } );
    }

} );
