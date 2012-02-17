<?php
/*
Plugin Name: Pro Sites (Feature: Limit BuddyPress)
*/
class ProSites_Module_BP {

	function ProSites_Module_BP() {
		$this->__construct();
	}

  function __construct() {
		add_action( 'psts_settings_page', array(&$this, 'settings') );
		add_filter( 'psts_settings_filter', array(&$this, 'settings_process') );
		add_filter( 'bp_user_can_create_groups', array(&$this, 'create_groups') );
		add_filter( 'messages_template_compose', array(&$this, 'messages_template') );
		add_action( 'wp_head', array(&$this, 'css_output') );
	}

	function settings_process($settings) {
	  $settings['bp_group'] = isset($settings['bp_group']) ? 1 : 0;
	  $settings['bp_compose'] = isset($settings['bp_compose']) ? 1 : 0;
	  return $settings;
	}
	
	function settings() {
    global $psts;
		?>
		<div class="postbox">
		  <h3 class='hndle'><span><?php _e('Limit BuddyPress Features', 'psts') ?></span> - <span class="description"><?php _e('Allows you to limit BuddyPress group creation and messaging to users of a Pro Site.', 'psts') ?></span></h3>
		  <div class="inside">
			  <table class="form-table">
				  <tr valign="top">
				  <th scope="row"><?php _e('Limit Group Creation', 'psts'); ?></th>
				  <td><label><input type="checkbox" name="psts[bp_group]" value="1"<?php checked($psts->get_setting('bp_group')); ?> /> <?php _e('Pro Site user only', 'psts'); ?></label></td>
				  </tr>
				  <tr valign="top">
				  <th scope="row"><?php _e('Limit Composing Messages', 'psts'); ?></th>
				  <td><label><input type="checkbox" name="psts[bp_compose]" value="1"<?php checked($psts->get_setting('bp_compose')); ?> /> <?php _e('Pro Site user only', 'psts'); ?></label></td>
				  </tr>
				  <tr valign="top">
					<th scope="row"><?php _e('Restricted Message', 'psts'); ?></th>
					<td><input type="text" name="psts[bp_notice]" id="bp_notice" value="<?php echo esc_attr($psts->get_setting('bp_notice')); ?>" style="width: 95%" />
					<br /><?php _e('Required - HTML allowed - This message is displayed when a Pro Site user only feature is accessed in BuddyPress. "LEVEL" will be replaced with the first level name.', 'psts') ?></td>
					</tr>
			  </table>
		  </div>
		</div>
	  <?php
	}

	function create_groups($can_create) {
	  global $bp, $psts;

	  if ( !$psts->get_setting('bp_group') )
	    return $can_create;

	  //don't mess with pro_sites
	  if ( is_pro_user() )
	    return $can_create;

		$can_create = false;
	  add_action( 'template_notices', array(&$this, 'message') );

	  return $can_create;
	}

	function messages_template($template) {
		global $psts;
		
	  if ( !$psts->get_setting('bp_compose') )
	    return $template;

	  //don't mess with pro_sites
	  if ( is_pro_user() )
	    return $template;

	  add_action( 'bp_template_content', array(&$this, 'message') );

	  return 'members/single/plugins';
	}

	function message() {
	  global $psts;
		
		//link to the primary blog
		$blog_id = get_user_meta(get_current_user_id(), 'primary_blog', true);
		if (!$blog_id)
			$blog_id = false;
		
		$notice = str_replace( 'LEVEL', $psts->get_level_setting(1, 'name'), $psts->get_setting('bp_notice') );
	  echo '<div id="message" class="error"><p><a href="'.$psts->checkout_url($blog_id).'">' . $notice . '</a></p></div>';
	}

	function css_output() {
	  //display css for error messages
	  ?>
	  <style type="text/css">#message.error p a {color:#FFFFFF;}</style>
	  <?php

	}
}

//register the module
psts_register_module( 'ProSites_Module_BP', __('Limit BuddyPress Features', 'psts'), __('Allows you to limit BuddyPress group creation and messaging to users of a Pro Site.', 'psts') );
?>