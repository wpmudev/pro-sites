jQuery( document ).ready( function ( $ ) {

    Taxamo.subscribe( 'taxamo.prices.updated', function ( data ) {

        var use_taxamo = is_taxamo();

        integrate_taxamo();

        // Update primary display prices
        if( is_taxamo() ) {
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

                    if ( 0 < amount[ 0 ] ) {
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
        } else {

        }

        // Update monthly savings prices
        if( is_taxamo() ) {
            $.each( $( '.monthly-price-hidden, .savings-price-hidden' ), function ( index, value ) {
                var amount = $( value ).find( '.tax-total' ).html();
                if ( typeof amount !== 'undefined' ) {
                    if ( 0 < amount[ 0 ] ) {
                        $( $( value ).prev() ).html( amount );
                    }
                }
            } );
        } else {


        }

    } );

    /**
     * Better change things if the user changes country
     */
    Taxamo.subscribe( 'taxamo.country.detected', function ( data ) {
        integrate_taxamo();
    } );


    /**
     * Are we using Taxamo?
     *
     * If its an EU location (tax_supported) return true, else false.
     */
    function is_taxamo() {

        if( Taxamo.calculatedLocation !== undefined || typeof Taxamo.calculatedLocation !== 'undefined' ) {
            return Taxamo.calculatedLocation.tax_supported
        } else {
            return false;
        }

    }


    function integrate_taxamo() {
        var use_taxamo = is_taxamo();

        if( is_taxamo() ) {

            // Set Taxamo
            if( $( '[name="tax-type"]' ).val() != 'taxamo' ) {
                $( '[name="tax-type"]' ).attr( 'data-old', $( '[name="tax-type"]' ).val() );
            }
            $( '[name="tax-type"]' ).val( 'taxamo' );

        } else {

            // Reset tax type
            if( typeof ($( '[name="tax-type"]' ).attr( 'data-old' )) !== 'undefined' ) {
                $( '[name="tax-type"]' ).val( $( '[name="tax-type"]' ).attr( 'data-old' ) );
            }

            // Reset the prices

        }


    }

    function get_countries_array( dictionary ) {
        var countries = [];
        $.each( dictionary, function( key, value ) {
            countries.push( value['tax_number_country_code'] );
        } ) ;
        return countries;
    }

} );