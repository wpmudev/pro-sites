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
    $('.psts-wrap .psts-settings').css('min-height', height);
    $('#psts_ProSites_Module_Plugins, #psts_ProSites_Module_Plugins_Manager').change(function () {
        if ($(this).is(':checked')) {
            var id = $(this).attr('id');
            if (id == 'psts_ProSites_Module_Plugins') {
                if ($('#psts_ProSites_Module_Plugins_Manager').is(':checked')) {
                    alert(prosites_admin.disable_premium_plugin_manager);
                    $('#psts_ProSites_Module_Plugins_Manager').prop('checked', false);
                }
            } else if (id == 'psts_ProSites_Module_Plugins_Manager') {
                if ($('#psts_ProSites_Module_Plugins').is(':checked')) {
                    alert(prosites_admin.disable_premium_plugin);
                    $('#psts_ProSites_Module_Plugins').prop('checked', false);
                }
            }
        }
    });
	
	/**
	* On posting quota settings, if the level is selected
	* reload the page if per level posting quotas are enabled
	*/
	$(document).ready(function(e) {
		$('#pq_level').on('change', function(e){
			if ( $(".per_level:checked").val() == 1 ){
			   self.location=self.location+'&level='+this.options[this.selectedIndex].value;
			} 
		});
	});
});