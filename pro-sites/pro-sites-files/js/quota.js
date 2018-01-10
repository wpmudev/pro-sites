jQuery(document).ready(function($) {

    if( $( '.upload-php #posts-filter').length ) {
        var element = $( '#prosites-media-quota-display').detach();
        element.insertBefore( $( '.upload-php #posts-filter .tablenav.top') );
        element.css('display','block');
    }

    if( $( '.upload-php .media-toolbar').length ) {
        var element = $( '#prosites-media-quota-display').detach();
        element.insertAfter( $( '.upload-php .media-toolbar') );
        element.css('display','block');
    }

} );