jQuery(document).ready(function($){
	
/* ---------------------------------------------------------------------------- */
/* Iris Colorpicker
/* ---------------------------------------------------------------------------- */

	function wpmudev_forums_iris_colorpicker() {
		
		if ($('.color-picker').length) {
			
			$('.color-picker').wpColorPicker();
		
		}
	}
	
	wpmudev_forums_iris_colorpicker();

    /** Show help text on help image hover **/
    jQuery('img.help_tip').mouseenter( function() {
        jQuery(this).parent().find('.psts-help-text-wrapper').fadeIn(50);
    }). mouseleave( function() {
        jQuery(this).parent().find('.psts-help-text-wrapper'). fadeOut(50);
    });
    //If chosen function exists and there is any select with class chosen
    if ( jQuery.isFunction(jQuery.fn.chosen) && jQuery('.chosen').length ) {
        jQuery('.chosen').chosen({disable_search_threshold: 10}).change(function () {
            jQuery(this).trigger('chosen:updated')
        });
    }

    /**
     * Make sure that settings wrapper go as far as it needs to go.
     */
    var height = $('.psts-tab-container .psts-tabs').height() + 10;
    $('.psts-wrap .psts-settings').css( 'min-height', height );







});