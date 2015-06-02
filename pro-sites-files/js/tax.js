// Taxamo Module: Additional
var Taxamo = (function(my) {
    "use strict";
    var $ = my.$ || jQuery || null;

    var imsi_evidence = false;
    var the_imsi = false;
    var valid_imsi = false;

    my.detectIMSI = function( imsi, invalid_callback ) {

        var the_evidence = my.getEvidenceData();

        if( the_evidence && the_evidence.valid_imsi ) {

            // Already verified
            valid_imsi = the_evidence.valid_imsi;
            the_imsi = the_evidence.imsi;
            imsi_evidence = the_evidence.imsi_evidence;
            my.updateEvidence();

        } else {

            the_imsi = imsi;
            $.post(
                prosites_checkout.ajax_url, {
                    action: 'validate_imsi',
                    'imsi': imsi
                }
            ).done( function( data, status ) {

                var response = $.parseJSON( $( data ).find( 'response_data' ).text() );
                var imsi_data = $.parseJSON( response.imsi_data );

                var evidence = null;

                if( imsi_data && imsi_data.is_EU !== false ) {
                    var network = imsi_data.operator.network;
                    if( network.length == 0 ) {
                        network = imsi_data.operator.brand;
                    }
                    evidence = {
                        'resolved_country_code': imsi_data.country_code,
                        'used': true,
                        'evidence_value': 'MCC:' + imsi_data.mcc + ', MNC:' + imsi_data.operator_code + ', Network:' + network,
                        'evidence_type': 'other_commercially_relevant_info'
                    }
                    valid_imsi = true;
                } else {
                    invalid_callback();
                }

                the_evidence.valid_imsi = valid_imsi;
                the_evidence.imsi = the_imsi;
                the_evidence.imsi_evidence = evidence;
                Taxamo.$.cookie( 'taxamo_evidence', JSON.stringify( the_evidence ) );

                imsi_evidence = evidence;
                my.updateEvidence();

            } );

        }
    };

    my.updateEvidence = function() {
        var evidence_count = 0;
        var matching_reference = [];
        var matching_key = [];
        var is_valid_imsi = false;

        if ( my.calculatedLocation && imsi_evidence && my.calculatedLocation.billing_country_code == imsi_evidence.resolved_country_code ) {
            evidence_count = 1;
            is_valid_imsi = true;
            $.each( my.calculatedLocation.countries, function( key, item ) {
                if( item && item.cca2 == imsi_evidence.resolved_country_code ) {
                    evidence_count += 1;
                    matching_key.push( key );
                    matching_reference.push( item );
                }
            } );

            if( evidence_count > 1 ) {
                my.calculatedLocation.countries.other_commercially_relevant_info = matching_reference[0];
            } else {
                my.calculatedLocation.countries.other_commercially_relevant_info = null;
            }

            my.calculatedLocation.evidence.other_commercially_relevant_info = imsi_evidence;

            if( evidence_count > 1 ) {
                $.each( matching_key, function( key, item ) {
                    my.calculatedLocation.evidence[item].used = true;
                } );

                if( my.calculatedLocation.countries.other_commercially_relevant_info.tax_supported ) {
                    my.calculatedLocation.tax_country_code = my.calculatedLocation.countries.other_commercially_relevant_info.cca2;
                    my.calculatedLocation.tax_supported = true;
                }
            }

        } else {

            if( ( my.calculatedLocation.evidence.by_billing && my.calculatedLocation.evidence.by_ip ) &&
                ( my.calculatedLocation.evidence.by_ip.resolved_country_code != my.calculatedLocation.evidence.by_billing.resolved_country_code ) ) {
                my.calculatedLocation.evidence.other_commercially_relevant_info = null;
                my.calculatedLocation.tax_country_code = '00';
                my.calculatedLocation.tax_supported = null;
            }
        }

        var the_evidence = my.getEvidenceData();
        if( my.calculatedLocation.tax_country_code != the_evidence.country && ! my.firstLoad ) {
            the_evidence.imsi_evidence = null;
            the_evidence.valid_imsi = false;
            the_evidence.imsi = '';
        }
        the_evidence.country = my.calculatedLocation.billing_country_code;
        the_evidence.imsi = the_imsi;

        Taxamo.$.cookie( 'taxamo_evidence', JSON.stringify( the_evidence ) );

        my.setFirstLoad( false );

        my.showCountryOverlay();
        my.publishEvent('taxamo.country.detected_post', my.calculatedLocation );
    }


    // Pulled from Taxamo API
    my.showSelectCountryModal = function() {
        var $ = my.$;
        var src = "/checkout/index.html?";
        src += "country_code=" + escape($.cookie('taxamo.country_code'));
        src += "&public_token=" + escape(my.options.publicToken);
        src += "#country";
        my.showXdmModal(src,
            function(message, origin) {
                var res = $.parseJSON(message);
                if (res.country_code) {
                    my.setBillingCountry(res.country_code);
                }
            },
            {data:         my.calculatedLocation,
                country_code: $.cookie('taxamo.country_code')});
    }

    // Pulled from Taxamo API .... for localization
    my.showCountryOverlay = function() {

        var data = my.calculatedLocation;

        $("#taxamo-confirm-country-overlay").remove();
        //create div
        var newDiv = document.createElement('div');
        document.body.appendChild(newDiv);
        $(newDiv).addClass('taxamo');
        $(newDiv).attr({id: "taxamo-confirm-country-overlay",
            style: "color: #ffffff !important; position: fixed !important; bottom: 0px !important; right: 0px !important; background-color: black !important; opacity: 0.8 !important; padding: 8px !important; border-top-left-radius: 5px !important; z-index:9998 !important; font-size: 14px !important;"});

        var content = "";
        var button = document.createElement('a');
        $(button).attr('style', "color: #428bca !important; text-decoration: none !important; font-weight: bold;")
        $(button).attr('href', '');
        var button1 = document.createElement('a');
        $(button1).attr('href', '');
        $(button1).attr('style', "color: #428bca !important; text-decoration: none !important; font-weight: bold;")

        if (data.tax_country_code != null && ( data.countries.by_billing && ! data.countries.by_billing.tax_supported )) {
            if (!data.tax_supported) {
                content += psts_tax.taxamo_overlay_non_eu;
            } else {
                content += "&nbsp;<span>" +   + "<b>";
                content += data.country_name;
                content += "</b>.</span>&nbsp;";
            }
            $(button).click(function (e) {
                try {
                    my.showSelectCountryModal(data);
                } finally {
                    return false;
                }
            });
            $(button).append(' ' + psts_tax.taxamo_overlay_learn_more );
            button1 = null;
        } else {
            if (!data.countries.by_billing.tax_supported) {
                content += psts_tax.taxamo_overlay_non_eu;
            } else {
                content += "&nbsp;<span>" + psts_tax.taxamo_overlay_country_set + " <b>";
                content += data.countries.by_billing.name;
            }
            content += "</b></span>&nbsp;";
            $(button).click(function (e) {
                try {
                    my.showSelectCountryModal(data);
                } finally {
                    return false;
                }
            });
            $(button).append(' ' + psts_tax.taxamo_overlay_learn_more );
            button1 = null;

        }

        $(newDiv).append(content);
        $(newDiv).append(button);
        if (button1 != null) {
            $(newDiv).append("&nbsp;or&nbsp;");
            $(newDiv).append(button1);
        }
    }

    my.is_tax_supported = function() {
        if ( my.calculatedLocation !== undefined || typeof my.calculatedLocation !== 'undefined' ) {
            return my.calculatedLocation.tax_supported
        } else {
            return false;
        }
    }

    my.firstLoad = false;
    my.setFirstLoad = function( value ) {
        my.firstLoad = value;
    }

    my.getEvidenceData = function() {
        if( Taxamo.$ !== undefined ) {
            var the_evidence = Taxamo.$.cookie( 'taxamo_evidence' ) || false;
            if( the_evidence ) {
                the_evidence = JSON.parse( the_evidence );
            } else {
                the_evidence = {};
            }
            return the_evidence;
        } else {
            return {};
        }
    }

    my.saveEvidenceData = function( the_evidence ) {
        Taxamo.$.cookie( 'taxamo_evidence', JSON.stringify( the_evidence ) );
    }

    return my;
})(Taxamo || {});


function invalid_imsi() {
    alert( psts_tax.taxamo_imsi_invalid );
}


jQuery( document ).ready( function ( $ ) {

    var the_evidence = false;

    if( Taxamo && Taxamo.$ ) {
        the_evidence = Taxamo.getEvidenceData();
        if ( the_evidence && the_evidence.country ) {
            Taxamo.setBillingCountry( the_evidence.country );
        }
    }

    $(this).bind( 'psts:gateways_refreshed', function( e ) {

        the_evidence = Taxamo.getEvidenceData();

        var tax_type = the_evidence.tax_type || 'none';
        var tax_country = the_evidence.tax_country || '';
        var tax_evidence = the_evidence.tax_evidence || '';

        $('[name="tax-type"]' ).val( tax_type );
        $('[name="tax-country"]' ).val( tax_country );
        $('[name="tax-evidence"]' ).val( tax_evidence );

    } );


    $('[name="tax-evidence-update"]' ).on( 'click', function(target){
        var imsi = $('[name="tax-evidence-imsi"]' ).val();
        if( imsi.length == 15 ) {
            // Lets validate
            var the_evidence = Taxamo.getEvidenceData();
            the_evidence.valid_imsi = false;
            the_evidence.imsi_evidence = null;
            Taxamo.$.cookie( 'taxamo_evidence', JSON.stringify( the_evidence ) );

            Taxamo.detectIMSI( imsi, invalid_imsi );
        } else {
            alert( psts_tax.taxamo_imsi_short );
        }

    });


    //if ( typeof Taxamo !== "undefined" && taxamo_token_ok() ) {
    if ( Taxamo.subscribe ) {
        Taxamo.subscribe( 'taxamo.prices.updated', function ( data ) {
            integrate_taxamo( data );
        } );
    }

    /**
     * Better change things if the user changes country
     */
    //if ( typeof Taxamo !== "undefined" && taxamo_token_ok() ) {
    if ( Taxamo.subscribe ) {
        Taxamo.subscribe( 'taxamo.country.detected', function ( data ) {
            // Use additional Taxamo module
            var imsi = $('[name="tax-evidence-imsi"]' ).val();

            Taxamo.detectIMSI( imsi, function(){} );
        } );
        // Use the additional module's detection
        Taxamo.subscribe( 'taxamo.country.detected_post', function ( data ) {

            $( '.tax-checkout-warning' ).remove();

            var the_evidence = Taxamo.getEvidenceData();

            if ( !data.tax_country_code ) {
                $( '[name="tax-country"]' ).val( data.evidence.by_ip.resolved_country_code );
                the_evidence.tax_country = data.evidence.by_ip.resolved_country_code;
            } else {
                $( '[name="tax-country"]' ).val( data.tax_country_code );
                the_evidence.tax_country = data.tax_country_code;
            }

            Taxamo.saveEvidenceData( the_evidence );

            integrate_taxamo( data );
            taxamo_scan_prices();

            // @todo Check this
            // Incompatible evidence....
            if ( data.billing_country_code != '00' && ( data.evidence.by_ip.resolved_country_code != data.evidence.by_billing.resolved_country_code ) &&
                    (
                        (
                            data.evidence.other_commercially_relevant_info && data.evidence.other_commercially_relevant_info.resolved_country_code != data.evidence.by_billing.resolved_country_code
                        ) || (
                            data.evidence.other_commercially_relevant_info === null
                        )
                    )
            ) {
                $( '.tax-checkout-evidence' ).removeClass( 'hidden' );
            } else {
                $( '.tax-checkout-evidence' ).addClass( 'hidden' );
            }

        } );
    }

    function taxamo_update_evidence() {

        if( ! Taxamo.calculatedLocation ) {
            return false;
        }

        var data = Taxamo.calculatedLocation;
        var evidence_data = {};
        evidence_data.billing_country_code = data.billing_country_code;
        evidence_data.buyer_ip = data.buyer_ip;
        evidence_data.evidence = data.evidence;
        evidence_data.country_name = data.country_name;
        evidence_data.tax_country_code = data.tax_country_code;
        evidence_data.tax_supported = data.tax_supported;
        evidence_data.tax_percentage = $( '.price-plain .tax-rate' ).html();
        $( '[name="tax-evidence"]' ).val( JSON.stringify( evidence_data ) );
        var the_evidence = Taxamo.getEvidenceData();
        the_evidence.tax_evidence = JSON.stringify( evidence_data );
        Taxamo.saveEvidenceData( the_evidence );

    }
    function taxamo_token_ok() {
        tokenOK = false;
        Taxamo.verifyToken( function ( data ) {
            tokenOK = data.tokenOK;
        } );
        return tokenOK;
    }

    function taxamo_scan_prices() {
        Taxamo.scanPrices( '.price-plain, .monthly-price-hidden, .savings-price-hidden', {
            "priceTemplate": "<div class=\"tax-total\">${totalAmount}</div><div class=\"tax-amount\">${taxAmount}</div><div class=\"tax-rate\">${taxRate}</div><div class=\"tax-base\">${amount}</div>",
            "noTaxTitle": "", //set titles to false to disable title attribute update
            "taxTitle": ""
        } );
    }

    /**
     * Are we using Taxamo?
     *
     * If its an EU location (tax_supported) return true, else false.
     */
    function is_taxamo() {
        return Taxamo.is_tax_supported();
        //if ( Taxamo.calculatedLocation !== undefined || typeof Taxamo.calculatedLocation !== 'undefined' ) {
        //    return Taxamo.calculatedLocation.tax_supported
        //} else {
        //    return false;
        //}
    }


    function integrate_taxamo( data ) {
        var use_taxamo = is_taxamo();

        var the_evidence = Taxamo.getEvidenceData();

        if ( use_taxamo ) {

            // Set Taxamo
            if ( $( '[name="tax-type"]' ).val() != 'taxamo' ) {
                $( '[name="tax-type"]' ).attr( 'data-old', $( '[name="tax-type"]' ).val() );
            }
            $( '[name="tax-type"]' ).val( 'taxamo' );
            the_evidence.tax_type = 'taxamo';

            // Update Primary Display Prices
            $.each( $( '.price-plain.hidden' ), function ( index, value ) {
                var amount = $( value ).find( '.tax-total' ).html();
                var percentage = $( value ).find( '.tax-rate' ).html();

                var run_once = false;

                if ( typeof amount !== 'undefined' ) {
                    amount = amount.split( '.' );

                    if ( !run_once && use_taxamo ) {
                        $( '.tax-checkout-notice .tax-percentage' ).html( percentage );
                        $( '.tax-checkout-notice' ).removeClass( 'hidden' );
                    } else if ( !run_once ) {
                        $( '.tax-checkout-notice' ).addClass( 'hidden' );
                    }

                    if ( 0 < amount[ 0 ] ) {
                        $( $( value ).prev() ).find( '.whole' ).html( amount[ 0 ] );
                    }

                    if ( 0 < amount[ 1 ] ) {
                        $( $( value ).prev() ).find( '.decimal' ).html( amount[ 1 ] );
                        $( $( value ).prev() ).find( '.dot' ).removeClass( 'hidden' );
                        $( $( value ).prev() ).find( '.decimal' ).removeClass( 'hidden' );
                    } else {
                        $( $( value ).prev() ).find( '.decimal' ).html( '' );
                        $( $( value ).prev() ).find( '.dot' ).addClass( 'hidden' );
                        $( $( value ).prev() ).find( '.decimal' ).addClass( 'hidden' );
                    }

                    run_once = true;
                }
            } );

            // Update monthly savings prices
            $.each( $( '.monthly-price-hidden, .savings-price-hidden' ), function ( index, value ) {
                var amount = $( value ).find( '.tax-total' ).html();
                if ( typeof amount !== 'undefined' ) {
                    if ( 0 < amount[ 0 ] ) {
                        var amount_string = $( $( value ).prev() ).html();
                        //var tax_base = $( value ).find( '.tax-base' ).html();
                        var replace_value = $( value ).attr( 'taxamo-amount-str' );
                        amount_string = amount_string.replace( replace_value, amount );
                        if ( 'yes' != $( $( value ).prev() ).attr( 'data-updated' ) ) {
                            $( $( value ).prev() ).html( amount_string );
                        }
                        $( $( value ).prev() ).attr( 'data-updated', 'yes' );
                    }
                }
            } );

        } else {

            // Reset tax type
            if ( typeof ($( '[name="tax-type"]' ).attr( 'data-old' )) !== 'undefined' ) {
                $( '[name="tax-type"]' ).val( $( '[name="tax-type"]' ).attr( 'data-old' ) );
                the_evidence.tax_type = $( '[name="tax-type"]' ).attr( 'data-old' );
            }

            // Update Primary Display Prices
            $( '.tax-checkout-notice' ).addClass( 'hidden' );
            $.each( $( '.price-plain.hidden' ), function ( index, value ) {
                var amount = $( value ).attr( 'taxamo-amount-str' );
                if ( typeof amount !== 'undefined' ) {
                    amount = amount.split( '.' );

                    if ( 0 < amount[ 0 ] ) {
                        $( $( value ).prev() ).find( '.whole' ).html( amount[ 0 ] );
                    }

                    if ( 0 < amount[ 1 ] ) {
                        $( $( value ).prev() ).find( '.decimal' ).html( amount[ 1 ] );
                        $( $( value ).prev() ).find( '.dot' ).removeClass( 'hidden' );
                        $( $( value ).prev() ).find( '.decimal' ).removeClass( 'hidden' );
                    } else {
                        $( $( value ).prev() ).find( '.decimal' ).html( '' );
                        $( $( value ).prev() ).find( '.dot' ).addClass( 'hidden' );
                        $( $( value ).prev() ).find( '.decimal' ).addClass( 'hidden' );
                    }
                }
            } );

            // Reset monthly savings prices
            $.each( $( '.monthly-price-hidden, .savings-price-hidden' ), function ( index, value ) {
                var original = $( value ).attr( 'taxamo-original-content' );
                if ( typeof original !== 'undefined' ) {
                    $( $( value ).prev() ).html( original );
                    $( $( value ).prev() ).attr( 'data-updated', '' );
                }
            } );

        }

        Taxamo.saveEvidenceData( the_evidence );

        taxamo_update_evidence();
    }


    function get_countries_array( dictionary ) {
        var countries = [];
        $.each( dictionary, function ( key, value ) {
            countries.push( value[ 'tax_number_country_code' ] );
        } );
        return countries;
    }

} );