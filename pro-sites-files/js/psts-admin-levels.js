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

        if ( confirm( prosites_levels.confirm_level_delete ) ) {
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
        prosites_levels_mark_dirty();
    });
    $('#enable_3').change(function () {
        if (this.checked) {
            $('.price-3').removeAttr('disabled');
        } else {
            $('.price-3').attr('disabled', true);
        }
        prosites_levels_mark_dirty();
    });
    $('#enable_12').change(function () {
        if (this.checked) {
            $('.price-12').removeAttr('disabled');
        } else {
            $('.price-12').attr('disabled', true);
        }
        prosites_levels_mark_dirty();
    });

    $('#prosites-level-list tbody input').change( function() {
        prosites_levels_mark_dirty();
    });


    /* ---- ---- ---- LEVEL SETTINGS PAGE ---- ---- ---- */

    // Make the levels sortable
    $('#prosites-level-list.level-settings tbody').sortable({
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
            prosites_levels_mark_dirty();
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

    function prosites_levels_mark_dirty() {
        $( '.save_levels_dirty' ).css( 'display', 'inline-block' );
    }

    /* ---- ---- ---- PRICING SETTINGS PAGE ---- ---- ---- */
    // Make the levels sortable
    $('#prosites-level-list.pricing-table tbody').sortable({
        opacity: 0.5,
        cursor: 'pointer',
        axis: 'y',
        placeholder: "prosite-level-placeholder",
        update: function() {

            var rows = $('#prosites-level-list tbody tr');
            var level_order = new Array();

            $.each( rows, function( index, row ) {
                level_order[ level_order.length ] = $( row ).attr('data-level');

                $(row).removeClass('alternate');
                if ( index % 2 == 0) {
                    $(row).addClass('alternate');
                }

            } );

            level_order = level_order.join( ',' );
            console.log( level_order );
            $('input[name="psts[pricing_levels_order]"]').val( level_order );
        }
    });

    /* ---- ---- ---- COMPARISON/ FEATURE TABLE PAGE ---- ---- ---- */
   // Make the features sortable
    function make_features_sortable() {
        $('#prosites-level-list.feature-table tbody').sortable({
            opacity: 0.5,
            cursor: 'pointer',
            axis: 'y',
            placeholder: "prosite-level-placeholder",
            update: function () {
                rearrange_feature_rows();
            }
        });
    }

    function rearrange_feature_rows() {
        var rows = $('#prosites-level-list tbody tr');
        var module_order = new Array();
        //
        $.each(rows, function (index, row) {
            var first_cell = $(row).find('td:first-child');
            var pos_label = $(first_cell).find('.position');
            var mod_key = $($(row).find('td:first-child [name*=module_key], td:first-child [name*=custom]')).val();
            module_order[index] = mod_key;

            $(pos_label).text(index + 1);

            $(row).removeClass('alternate');
            if (index % 2 == 0) {
                $(row).addClass('alternate');
            }

        });

        module_order = module_order.join(',');

        $('input[name="psts[feature_table][feature_order]"]').val(module_order);
    }

    make_features_sortable();
    switch_level( 1 );

    $( '.level-select-bar a').click( function (e) {

        var element = e.currentTarget;

        $('.level-select-bar a').removeClass('selected');

        var current_level = $(element).attr('data-id');
        switch_level( current_level );
        set_active_level( current_level );
        $(element).addClass('selected');

    });

    function switch_level( level ) {
        $( '[name*="[levels]"]').next('.chosen-container').hide();
        $( 'textarea[name*="[levels]"]').hide();
        $( '[name*="[levels][' + level + ']"]').next('.chosen-container').show();
        $( 'textarea[name*="[levels][' + level + ']"]').show();
    }

    function get_active_level() {
        return $( '.level-select-bar [name=current_level]').val();
    }

    function set_active_level( level ) {
        $( '.level-select-bar [name=current_level]').val( level );
    }

    /* ---- ---- ---- ADD FEATURES BUTTON ---- ---- ---- */
    $( '#add-feature-button').click( function( e ) {
        var no_features = $('.no-features').hide().detach();
        var name = $('[name=new-feature-name]').val();
        var description = $('[name=new-feature-description]').val();
        var text = $('[name=new-feature-text]').val();
        var levels = $('[name=new-feature-levels]').val();

        // Get the following with script translation
        var save_action = 'save';
        var reset_action = 'reset';
        var none_label = 'None';

        var all_item_count = $( '#prosites-level-list.feature-table tbody tr').length;

        var custom_features = $( '#prosites-level-list.feature-table tbody tr.custom .order-col [type=hidden]');
        var number_custom = custom_features.length;

        // Set our custom name
        if( 0 == number_custom ) {
            var custom_name = 'custom-1';
        } else {
            var custom_name = 'custom-2';
            var counter = 1;
            // Make sure we get a valid custom name
            while( ! check_valid_custom_name( custom_name, custom_features ) ) {
                counter += 1;
                custom_name = 'custom-' + counter;
            }
        }

        var row_class = ( all_item_count + 1 ) % 2 == 0 ? '' : 'alternate';


        var feature_row = '<tr class="' + row_class + ' custom new-feature" blog-row">';
        var key = custom_name;
        var feature_order = '<td scope="row" style="padding-left: 10px" class="order-col">';
        feature_order += '<div class="position">' + ( all_item_count + 1 ) + '</div>';
        feature_order += '<input type="hidden" name="psts[feature_table][' + key + '][custom]" value="' + custom_name + '" />';
        feature_order += '<a class="delete"><span class="dashicons dashicons-trash"></span></a>';
        feature_order += '</td>';

        var feature_visible = '<td scope="row" style="padding-left: 20px;">';
        feature_visible += '<input type="checkbox" checked="checked" name="psts[feature_table][' + key + '][visible]" value="1">';
        feature_visible += '</td>'

        var feature_name = '<td scope="row">';
        feature_name += '<div class="text-item">' + name + '</div>';
        feature_name += '<div class="edit-box" style="display:none">';
        feature_name += '<input class="editor" type="text" name="psts[feature_table][' + key + '][name]" value="' + name + '" /><br />';
        feature_name += '<span><a class="save-link">' + save_action + '</a> <a style="margin-left: 10px;" class="reset-link">' + reset_action + '</a></span></div>';
        feature_name += '<input type="hidden" value="' + name + '" />'

        var feature_description = '<td scope="row">';
        feature_description += '<div class="text-item">' + description + '</div>';
        feature_description += '<div class="edit-box" style="display:none">';
        feature_description += '<textarea class="editor" name="psts[feature_table][' + key + '][description]">' + description + '</textarea><br />';
        feature_description += '<span><a class="save-link">' + save_action + '</a> <a style="margin-left: 10px;" class="reset-link">' + reset_action + '</a></span></div>';
        feature_description += '<input type="hidden" value="' + description + '" />'

        var feature_indicator = '<td scope="row" class="level-settings">'
        for( var i = 1; i <= levels ; i++ ) {
            feature_indicator += '<select class="chosen" name="psts[feature_table][' + key + '][levels][' + i + '][status]">';
            feature_indicator += '<option value="tick">&#x2713;</option>';
            feature_indicator += '<option value="cross">&#x2718;</option>';
            feature_indicator += '<option value="none">' + none_label + '</option>';
            feature_indicator += '</select>';
        }
        feature_indicator += '</td>';

        var feature_custom = '<td scope="row">';
        for( var i = 1; i <= levels ; i++ ) {
            feature_custom += '<textarea name="psts[feature_table][' + key + '][levels][' + i + '][text]">' + text + '</textarea>';
        }
        feature_custom += '</td>';

        feature_row += feature_order + feature_visible + feature_name + feature_description + feature_indicator + feature_custom;
        feature_row += '</tr>';

        $( '#prosites-level-list.feature-table tbody').append( feature_row );
        $( '#prosites-level-list.feature-table tbody').append( no_features );

        // Activate chosen
        if ( jQuery.isFunction(jQuery.fn.chosen) && jQuery('.chosen').length ) {
            jQuery('.chosen').chosen({disable_search_threshold: 10}).change(function () {
                jQuery(this).trigger('chosen:updated')
            });
        }

        set_inline_editing();
        switch_level( get_active_level() );
        rearrange_feature_rows();
        make_features_sortable();

    } );

    function check_valid_custom_name( custom_name, custom_items ) {
        var valid = true;

        $.each( custom_items, function( index, item ) {
            if( $( item ).val() == custom_name ) {
                valid = false;
            }
        } );

        // check items marked for delete
        var marked = $('[name=mark_for_delete]').val();
        marked = marked.split( ',' );

        $.each( marked, function( index, item ) {
            if( item == custom_name ) {
                valid = false;
            }
        } );

        return valid;
    }

    function set_inline_editing() {
        // Inline editing
        $('#prosites-level-list.feature-table .text-item').unbind( 'dblclick' );
        $('#prosites-level-list.feature-table .text-item').dblclick(function (e) {
            var element = e.currentTarget;
            $(element).next().show();
            $(element).hide();
        });

        $('#prosites-level-list.feature-table .save-link').unbind( 'click' );
        $('#prosites-level-list.feature-table .save-link').click(function (e) {
            var element = e.currentTarget;
            var text = $($(element).parents('td')[0]).find('.text-item');
            var parent = $($(element).parents('div')[0]);
            var editor = $(parent).find('.editor');

            $(text).html($(editor).val());
            $(text).show();
            $(parent).hide();
        });

        $('#prosites-level-list.feature-table .reset-link').unbind( 'click' );
        $('#prosites-level-list.feature-table .reset-link').click(function (e) {
            var element = e.currentTarget;
            var text = $($(element).parents('td')[0]).find('.text-item');
            var parent = $($(element).parents('div')[0]);
            var table_cell = $(element).parents('td')[0];
            var original = $(table_cell).find('[type=hidden]');
            var editor = $(parent).find('.editor');

            $(text).html($(original).val());
            $(text).show();
            $(editor).val($(original).val());
            $(parent).hide();
        });

        $('#prosites-level-list.feature-table .order-col .delete').unbind( 'click' );
        $('#prosites-level-list.feature-table .order-col .delete').click( function (e) {

            if ( confirm( prosites_levels.confirm_feature_delete ) ) {
                var element = e.currentTarget;
                var mark = $(element).prev().val();
                var row = $(element).parents('tr')[0];

                $(row).remove();
                rearrange_feature_rows();

                var marked = $('[name=mark_for_delete]').val();
                marked = '' == marked ? mark : marked + ',' + mark;
                $('[name=mark_for_delete]').val(marked);
            }

        } );
    }

    set_inline_editing();
});