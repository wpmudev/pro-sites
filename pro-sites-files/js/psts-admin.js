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
	
});