<?php
/*
Plugin Name: Pro Sites (Feature: Pay To Blog)
*/
class ProSites_Module_PayToBlog {

	function ProSites_Module_PayToBlog() {
		$this->__construct();
	}

  function __construct() {
		add_action( 'psts_settings_page', array(&$this, 'settings') );
		add_filter( 'psts_settings_filter', array(&$this, 'settings_process') );
		add_action( 'template_redirect', array(&$this, 'disable_front') );
		add_filter( 'psts_prevent_dismiss', create_function(null, 'return true;') );
		add_filter( 'psts_force_redirect', array(&$this, 'force_redirect') );
		add_filter( 'pre_option_psts_signed_up', array(&$this, 'force_redirect') );
		
		//checkout message, show before gateways
		add_filter( 'psts_checkout_output', array(&$this, 'checkout_screen'), 9, 2 );
	}
	
	function checkout_screen($content, $blog_id) {
	  global $psts;

	  if (!$blog_id)
	    return $content;
    
    //show top part of content if its not a pro blog
		if ( !is_pro_site($blog_id) )
			$content .= $psts->get_setting('ptb_checkout_msg');

	  return $content;
	}
	
	function disable_front() {
    global $psts, $blog_id;
		
		if (is_admin())
			return;

		if ( $psts->get_setting('ptb_front_disable') && !is_pro_site($blog_id, 1) ) {
			
			//send temporary headers
			header('HTTP/1.1 503 Service Temporarily Unavailable');
			header('Status: 503 Service Temporarily Unavailable');
			header('Retry-After: 86400');
			
			//load template if exists
			if ( file_exists( WP_CONTENT_DIR . '/ptb-template.php') ) {
				require_once( WP_CONTENT_DIR . '/ptb-template.php' );
				exit;
			} else {
				$content = $psts->get_setting('ptb_front_msg');
				if (is_user_logged_in() && current_user_can('edit_pages'))
					$content .= '<p><a href="'.$psts->checkout_url($blog_id).'">' . __('Re-enable now &raquo;', 'psts') . '</a></p>';
				wp_die( $content );
			}
	  }
	}

	function force_redirect($value) {
    global $psts;
		
		if ( is_pro_site(false, 1) ) {
			return 0;
	  } else {
			return 1;
		}
	}

	function settings_process($settings) {
	  $settings['ptb_front_disable'] = isset($settings['ptb_front_disable']) ? $settings['ptb_front_disable'] : 0;
	  return $settings;
	}

	function settings() {
  	global $psts;
		?>
		<div class="postbox">
		  <h3 class='hndle'><span><?php _e('Pay To Blog', 'psts') ?></span> - <span class="description"><?php _e('Allows you to completely disable a site both front end and back until paid.', 'psts') ?></span></h3>
		  <div class="inside">
			  <table class="form-table">
					<tr valign="top">
				  <th scope="row"><?php _e('Checkout Message', 'psts') ?></th>
				  <td>
					<textarea name="psts[ptb_checkout_msg]" rows="5" wrap="soft" style="width: 95%"><?php echo esc_textarea($psts->get_setting('ptb_checkout_msg')); ?></textarea>
				  <br /><?php _e('Required - This message is displayed on the checkout page if the site is unpaid. HTML Allowed', 'psts') ?></td>
				  </tr>
					<tr valign="top">
				  <th scope="row"><?php _e('Disable Front End', 'psts'); ?></th>
				  <td><label><input type="checkbox" name="psts[ptb_front_disable]" value="1"<?php checked($psts->get_setting('ptb_front_disable')); ?> /> <?php _e('Disable', 'psts'); ?></label></td>
				  </tr>
	      	<tr valign="top">
				  <th scope="row"><?php _e('Front End Restricted Message', 'psts') ?></th>
				  <td>
					<textarea name="psts[ptb_front_msg]" rows="5" wrap="soft" style="width: 95%"><?php echo esc_textarea($psts->get_setting('ptb_front_msg')); ?></textarea>
				  <br /><?php _e('Required - This message is displayed on front end of the site if it is unpaid and disabling the front end is enabled. HTML Allowed', 'psts') ?></td>
				  </tr>
			  </table>
		  </div>
		</div>
	  <?php
	}
}

//register the module
psts_register_module( 'ProSites_Module_PayToBlog', __('Pay To Blog', 'psts'), __('Allows you to completely disable a site both front end and back until paid.', 'psts') );
?>