jQuery(document).ready(function($){

    $('#prosites-level-list tbody').sortable({
        opacity: 0.5,
        cursor: 'pointer',
        axis: 'y',
        placeholder: "prosite-level-placeholder",
        update: function() {
            //var ordr = jQuery(this).sortable('serialize') + '&action=list_update_order';
            //jQuery.post(ajaxurl, ordr, function(response){
            //    //alert(response);
            //});
            var rows = $('#prosites-level-list tbody tr');

            $.each( rows, function( index, row ) {
                /**
                 * Get the columns
                 */
                var cols = $( row ).find( 'td' );

                /**
                 * Update row class
                 */
                $( row ).removeClass( 'alternate' );
                if( index % 2 == 0 ) {
                    $( row ).addClass( 'alternate' );
                }

                /**
                 * Update row number
                 */
                $( cols[0]).html('<strong>' + ( index + 1 ) + '</strong>' );

                /**
                 * Update input field names
                 */
                $.each( cols, function( c_idx, col ) {
                    if( 0 < c_idx && c_idx < ( $(cols).length - 1 ) ) {
                        var input_field = $(col).find('input');
                        var current_name = $( input_field ).attr( 'name' );
                        var new_name = current_name.substr( 0, current_name.indexOf('[') ) + '[' + (index + 1 ) + ']';
                        input_field.attr( 'name', new_name );
                    }
                } );

            });

        }
    });




});