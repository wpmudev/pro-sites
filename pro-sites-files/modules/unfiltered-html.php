<?php
/*
Plugin Name: Pro Sites (Feature: Unfilter HTML)
*/
class ProSites_Module_UnfilterHtml {

	function ProSites_Module_UnfilterHtml() {
		$this->__construct();
	}

  function __construct() {
		add_action( 'psts_settings_page', array(&$this, 'settings') );
		add_action( 'admin_notices', array(&$this, 'message') );
		add_filter( 'user_has_cap', array(&$this, 'unfilter_check'), 100, 3 );
		add_filter( 'map_meta_cap', array(&$this, 'unfilter_caps'), 10, 4 );
		
		define('DISALLOW_UNFILTERED_HTML', false);
	}
	
	//for ads module to allow unfiltered
	function ads_unfilter() {
    global $psts;
    
		if (function_exists('psts_hide_ads') && $psts->get_setting('ads_unfilter') && psts_hide_ads())
	    return true;
	  else
	    return false;
	}
	
	function unfilter_check($allcaps, $caps, $args) {
		global $psts;
		
		if ( is_super_admin() )
			return;
		
		if ( is_pro_site(false, $psts->get_setting('uh_level', 1)) || $this->ads_unfilter() ) {
			$allcaps['unfiltered_html'] = true;
			kses_remove_filters();
		} else {
			unset($allcaps['unfiltered_html']);
		}
		return $allcaps;
	}

	function unfilter_caps($caps, $cap, $user_id, $args) {
		if ( $cap == 'unfiltered_html' ) {
			unset( $caps );
			$caps[] = $cap;
		}
		return $caps;
	}

	function settings() {
  	global $psts;
	  $levels = (array)get_site_option( 'psts_levels' );
		?>
		<div class="postbox">
		  <h3 class='hndle'><span><?php _e('Unfilter HTML', 'psts') ?></span> - <span class="description"><?php _e('Allows you provide the "unfiltered_html" permission to specific user types for selected Pro Site levels.', 'psts') ?></span></h3>
		  <div class="inside">
			  <table class="form-table">
          <tr valign="top">
				  <th scope="row"><?php _e('Pro Site Level', 'psts') ?></th>
				  <td>
				  <select name="psts[uh_level]">
						<?php
						foreach ($levels as $level => $value) {
						?><option value="<?php echo $level; ?>"<?php selected($psts->get_setting('uh_level', 1), $level) ?>><?php echo $level . ': ' . esc_attr($value['name']); ?></option><?php
						}
						?>
	        </select><br />
	        <?php _e('Select the minimum level required to enable unfiltered html.', 'psts') ?>
					</td>
				  </tr>
	      	<tr valign="top">
				  <th scope="row"><?php _e('Filtered Message', 'psts') ?></th>
				  <td><input type="text" name="psts[uh_message]" id="uh_message" value="<?php echo esc_attr($psts->get_setting('uh_message')); ?>" style="width: 95%" />
				  <br /><?php _e('Required - This message is displayed on the post/page screen for sites that don\'t have unfiltered html permissions upon the saving of a post. "LEVEL" will be replaced with the needed level name.', 'psts') ?></td>
				  </tr>
			  </table>
		  </div>
		</div>
	  <?php
	}

	function message() {
		global $psts, $current_screen, $blog_id;

    if ( is_pro_site(false, $psts->get_setting('uh_level', 1)) || $this->ads_unfilter() )
      return;

	  if ( in_array( $current_screen->id, array('edit-page', 'page', 'edit-post', 'post') ) && isset( $_GET['message'] ) ) {
	    $notice = str_replace( 'LEVEL', $psts->get_level_setting($psts->get_setting('uh_level', 1), 'name'), $psts->get_setting('uh_message') );
	   	echo '<div class="error"><p><a href="'.$psts->checkout_url($blog_id).'">' . $notice . '</a></p></div>';
		}
	}
}

//register the module
psts_register_module( 'ProSites_Module_UnfilterHtml', __('Unfilter HTML', 'psts'), __('Allows you provide the "unfiltered_html" permission to specific user types for selected Pro Site levels.', 'psts') );
?>