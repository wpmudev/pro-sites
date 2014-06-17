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
    jQuery('.chosen').chosen().change(function(){ jQuery(this).trigger('chosen:updated') });;
	
});