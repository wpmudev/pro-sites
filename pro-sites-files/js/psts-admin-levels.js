jQuery(document).ready(function($){


    // Confirm deleting level
    $('[name^="delete_level"]').click(function ( item ) {

        /**
         * Get the position from the text input because altering button breaks it
         */
        var item_name = $( item.currentTarget).attr( 'name' );
        item_name = item_name.replace( '[', '\[');
        item_name = item_name.replace( ']', '\]');
        //console.log( item_name );

        var row_index = $( $( $( $(item.currentTarget).parents('tr') ).find('td')[1]).find('input')).attr('data-position');

        if ( confirm("<?php _e('Are you sure you really want to remove this level? This will also delete all feature settings for the level.', 'psts'); ?>") ) {
            prosite_update_level_rows( { deleteRow: row_index } );
            return true;
        }

        return false;
    });

    // When the page loads, disable/enable price inputs accordingly
    if (!$('#enable_1').is(':checked')) {
        $('.price-1').attr('disabled', true);
    }
    if (!$('#enable_3').is(':checked')) {
        $('.price-3').attr('disabled', true);
    }
    if (!$('#enable_12').is(':checked')) {
        $('.price-12').attr('disabled', true);
    }

    // And remember to update it when the user checks the enabled boxes
    $('#enable_1').change(function () {
        if (this.checked) {
            $('.price-1').removeAttr('disabled');
        } else {
            $('.price-1').attr('disabled', true);
        }
    });
    $('#enable_3').change(function () {
        if (this.checked) {
            $('.price-3').removeAttr('disabled');
        } else {
            $('.price-3').attr('disabled', true);
        }
    });
    $('#enable_12').change(function () {
        if (this.checked) {
            $('.price-12').removeAttr('disabled');
        } else {
            $('.price-12').attr('disabled', true);
        }
    });


    // Make the levels sortable
    $('#prosites-level-list tbody').sortable({
        opacity: 0.5,
        cursor: 'pointer',
        axis: 'y',
        placeholder: "prosite-level-placeholder",
        update: function() {

            // Leave this here for now... just in case we want to make it update via AJAX
            //var ordr = jQuery(this).sortable('serialize') + '&action=list_update_order';
            //jQuery.post(ajaxurl, ordr, function(response){
            //    //alert(response);
            //});

            prosite_update_level_rows();

            $( '.save_levels_dirty' ).css( 'display', 'inline-block' );

        }
    });

    function prosite_update_level_rows( args ) {
        var rows = $('#prosites-level-list tbody tr');
        var deleted_row = -1;

        if ( args !== undefined ) {
            if( args.deleteRow !== undefined ) {
                deleted_row = args.deleteRow;
            }
        }

        var t_index = 0;
        $.each( rows, function( index, row ) {

            /**
             * Get the columns
             */
            var cols = $( row ).find( 'td' );
            var row_position = $( $( $(cols)[1]).find('input') ).attr('data-position');

            if( row_position == deleted_row ) {
                $( row ).hide();
                prosite_update_level_cols( cols, -99 );
                return true;
            }

            /**
             * True index count in case row got deleted.
             */
            t_index += 1;

            console.log( 'Row: ' + row_position + ' T-Index: ' + t_index );
            /**
             * Update row class
             */
            $(row).removeClass('alternate');
            if (t_index % 2 != 0) {
                $(row).addClass('alternate');
            }

            prosite_update_level_cols( cols, t_index );

        });

    }

    function prosite_update_level_cols( cols, t_index ) {
        /**
         * Update row number
         */
        $(cols[0]).html('<strong>' + ( t_index ) + '</strong>');

        /**
         * Update input field names.
         *
         */
        $.each(cols, function (c_idx, col) {
            if (0 < c_idx && c_idx < ( $(cols).length - 1 )) {
                var input_field = $(col).find('input');
                var current_name = $(input_field).attr('name');
                var new_name = current_name.substr(0, current_name.indexOf('[')) + '[' + ( t_index ) + ']';
                input_field.attr('name', new_name);
            }
            if (c_idx == 1 ) {
                var input_field = $(col).find('input');
                input_field.attr('data-position', t_index);
            }
        });

    }


});