jQuery( document ).ready( function ( $ ) {

    Taxamo.subscribe( 'taxamo.prices.updated', function ( data ) {

        // Update primary display prices
        $.each( $( '.price-plain.hidden' ), function ( index, value ) {
            var amount = $( value ).find('.tax-total' ).html();
            var percentage = $( value ).find('.tax-rate' ).html();

            var notice_set = false;

            if( typeof amount !== 'undefined' ) {
                amount = amount.split('.');

                if( ! notice_set ) {
                    $( '.tax-checkout-notice .tax-percentage' ).html( percentage );
                    $( '.tax-checkout-notice' ).removeClass( 'hidden' );
                    notice_set = true;
                }

                if( 0 < amount[0] ) {
                    $( $( value ).prev() ).find( '.whole' ).html( amount[0] );
                }

                if( 0 < amount[0] ) {
                    $( $( value ).prev() ).find( '.decimal' ).html( amount[1] );
                    $( $( value ).prev() ).find( '.dot' ).removeClass( 'hidden' );
                    $( $( value ).prev() ).find( '.decimal' ).removeClass( 'hidden' );
                } else {
                    $( $( value ).prev() ).find( '.decimal' ).html( '' );
                    $( $( value ).prev() ).find( '.dot' ).addClass( 'hidden' );
                    $( $( value ).prev() ).find( '.decimal' ).addClass( 'hidden' );
                }
            }
        } );

        // Update monthly savings prices
        $.each( $( '.monthly-price-hidden, .savings-price-hidden' ), function ( index, value ) {
            var amount = $( value ).find('.tax-total' ).html();
            if( typeof amount !== 'undefined' ) {
                if( 0 < amount[0] ) {
                    $( $( value ).prev() ).html( amount );
                }
            }
        } );

    } );

} );