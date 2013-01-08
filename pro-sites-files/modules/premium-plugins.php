<?php
/*
Pro Sites (Module: Premium Plugins)
*/
class ProSites_Module_Plugins {

	var $checkbox_rows = array();
	
	function ProSites_Module_Plugins() {
		$this->__construct();
	}

  function __construct() {
  	add_action( 'psts_page_after_modules', array(&$this, 'plug_network_page') );
		
		if ( !defined('PSTS_HIDE_PLUGINS_MENU') ) {
			add_action( 'admin_menu', array(&$this, 'plug_page') );
			add_action( 'admin_init', array(&$this, 'redirect_plugins_page') );
		}
		
		add_action( 'psts_settings_page', array(&$this, 'settings') );
		add_action( 'admin_notices', array(&$this, 'message_output') );
		add_action( 'psts_withdraw', array(&$this, 'deactivate_all') );
		add_action( 'psts_upgrade', array(&$this, 'auto_activate'), 10, 3 );
		add_action( 'psts_downgrade', array(&$this, 'deactivate'), 10, 3 );
		add_action( 'wpmu_new_blog', array(&$this, 'new_blog'), 50 ); //auto activation hook
		
		add_filter( 'site_option_menu_items', array(&$this, 'enable_plugins_page') );
		
		add_filter( 'all_plugins', array(&$this, 'remove_plugins') );
		add_filter( 'plugin_action_links', array(&$this, 'action_links'), 10, 4);
		add_filter( 'pre_update_option_recently_activated', array(&$this, 'check_activated') );
		
		//individual blog options
		add_action( 'wpmueditblogaction', array( &$this, 'blog_options_form' ) );
		add_action( 'wpmu_update_blog_options', array( &$this, 'blog_options_form_process' ) );

		add_filter( 'plugin_row_meta' , array( &$this, 'remove_plugin_meta' ), 10, 2 );
		add_action( 'admin_init', array( &$this, 'remove_plugin_update_row' ) );
	}

  function plug_network_page() {
	  add_submenu_page( 'psts', __('Pro Sites Premium Plugins', 'psts'), __('Premium Plugins', 'psts'), 'manage_network_options', 'psts-plugins', array(&$this, 'admin_page') );
	}
	
	//adds Premium Plugins submenu under pro blogs
	function plug_page() {
		global $psts;
		
	  add_submenu_page('psts-checkout', $psts->get_setting('pp_name'), $psts->get_setting('pp_name'), 'activate_plugins', 'premium-plugins', array(&$this, 'plugins_page_redirect') );
	}
	
	function enable_plugins_page($menu_items) {
		$menu_items['plugins'] = 1;
		return $menu_items;
	}
	
	//remove plugins with no user control
	function remove_plugins($all_plugins) {
    global $psts;
    
	  if ( is_super_admin() )
	    return $all_plugins;

		$psts_plugins = (array)$psts->get_setting('pp_plugins');
    $override_plugins = (array)get_option('psts_plugins');
	  foreach ( (array)$all_plugins as $plugin_file => $plugin_data) {
	    if ( !in_array($plugin_file, $override_plugins) && !(isset($psts_plugins[$plugin_file]['level']) && is_numeric($psts_plugins[$plugin_file]['level'])) ) {
	      unset($all_plugins[$plugin_file]); //remove plugin
	    }
	  }
	  return $all_plugins;
	}

	//plugin activate links
	function action_links($action_links, $plugin_file, $plugin_data, $context) {
		global $psts, $blog_id;

		if ( is_super_admin() ){
			return $action_links;
		}

		$psts_plugins = (array)$psts->get_setting('pp_plugins');
		$override_plugins = (array)get_option('psts_plugins');

		if ( isset($psts_plugins[$plugin_file]['level']) && $psts_plugins[$plugin_file]['level'] != 0 ) {
			$rebrand = sprintf( __('%s Only', 'psts'), $psts->get_level_setting($psts_plugins[$plugin_file]['level'], 'name') );
			$color = 'green';

			if ( !is_pro_site(false, $psts_plugins[$plugin_file]['level']) && 
				 !in_array($plugin_file, $override_plugins) ){

				// plugin disabled, subscription not high enough
				$color = 'red';
				$this->checkbox_rows[] = $plugin_file;
				$action_links = array();
			}

			$action_links[] = '<a style="color:'.$color.';" href="'.$psts->checkout_url($blog_id).'">'.$rebrand.'</a>';
		}

		return $action_links;
	}

	//use jquery to remove associated checkboxes to prevent mass activation (usability, not security)
	function remove_checks($plugin_file) {
	  echo "<script type='text/javascript'>jQuery(\"input:checkbox[value='".esc_attr($plugin_file)."']\").remove();</script>\n";
	}

	/*
	Removes hactivated plugins that should not have been activated (multi). Single activations
	are additionaly protected by a nonce field. Dirty hack in case someone uses firebug or
	something to hack the post and simulate a bulk activation. I'd rather prevent
	them from being activated in the first place, but there are no hooks for that! The
	display will show the activated status, but really they are not. Only hacking attempts
	will see this though! */
	function check_activated($active_plugins) {
    global $psts;
    
		if ( is_super_admin() )
	    return $active_plugins;

	  //only perform check right after activation hack attempt
	  if ( (isset($_POST['action']) && $_POST['action'] != 'activate-selected') && (isset($_POST['action']) && $_POST['action2'] != 'activate-selected') )
	    return $active_plugins;

		$psts_plugins = (array)$psts->get_setting('pp_plugins');
    $override_plugins = (array)get_option('psts_plugins');

	  foreach ( (array)$active_plugins as $plugin_file => $plugin_data) {
     	if ( isset($psts_plugins[$plugin_file]['level']) ) {
	      if ( !in_array($plugin_file, $override_plugins) && !is_pro_site(false, $psts_plugins[$plugin_file]['level']) ) {
		      deactivate_plugins($plugin_file);
		      unset($active_plugins[$plugin_file]);
				}
	    }
	  }

	  return $active_plugins;
	}

	//auto-activate pro plugins
	function auto_activate($blog_id, $new_level, $old_level) {
	  require_once( ABSPATH.'wp-admin/includes/plugin.php' );
    global $psts;
    
  	$psts_plugins = (array)$psts->get_setting('pp_plugins');
  	$level_plugins = array();
		foreach ( $psts_plugins as $plugin_file => $data ) {
			if ( $data['auto'] && is_numeric($data['level']) && $data['level'] > $old_level && $data['level'] <= $new_level )
		    $level_plugins[] = $plugin_file;
		}

	  if ( count($level_plugins) && is_pro_site($blog_id, $new_level) ) {
	    switch_to_blog($blog_id);
	    activate_plugins($level_plugins); //activate any plugins
	    restore_current_blog();
	  }
	}
	
	function deactivate($blog_id, $new_level, $old_level) {
	  require_once( ABSPATH.'wp-admin/includes/plugin.php' );
    global $psts;

		$psts_plugins = (array)$psts->get_setting('pp_plugins');
		$override_plugins = (array)get_blog_option($blog_id, 'psts_plugins');
  	$level_plugins = array();
		foreach ( $psts_plugins as $plugin_file => $data ) {
		  if ( !in_array($plugin_file, $override_plugins) && is_numeric($data['level']) && $data['level'] > $new_level )
		    $level_plugins[] = $plugin_file;
		}

	  if ( count($level_plugins) ) {
	    switch_to_blog($blog_id);
	    deactivate_plugins($level_plugins, true); //silently remove any plugins so that uninstall hooks aren't fired 
	    restore_current_blog();
	  }
	}
	
	//deactivate all pro blog plugins when not a pro blog
	function deactivate_all($blog_id) {
	  require_once( ABSPATH.'wp-admin/includes/plugin.php' );
    global $psts;
    
    if ( is_pro_site($blog_id) )
      return;
    
		$psts_plugins = (array)$psts->get_setting('pp_plugins');
		$override_plugins = (array)get_blog_option($blog_id, 'psts_plugins');
  	$level_plugins = array();
		foreach ( $psts_plugins as $plugin_file => $data ) {
		  if ( !in_array($plugin_file, $override_plugins) && is_numeric($data['level']) && $data['level'] > 0 )
		    $level_plugins[] = $plugin_file;
		}

	  if ( count($level_plugins) ) {
	    switch_to_blog($blog_id);
	    deactivate_plugins($level_plugins, true); //silently remove any plugins so that uninstall hooks aren't fired 
	    restore_current_blog();
	  }
	}

	function message_output() {
	  global $pagenow;

	  //advertises premium plugins on the main plugins page.
	  if ( $pagenow == 'plugins.php' && is_super_admin() ) {
	   	echo '<div class="updated"><p>'.__('As a Super Admin you can activate any plugins for this site.', 'psts').'</p></div>';
		}

		//Warns of Multisite Plugin Manager conflict
	  if ( class_exists('PluginManager') && is_super_admin() ) {
	 		echo '<div class="error"><p>'.__('WARNING: Multisite Plugin Manager and the Premium Plugins module are incompatible. Please remove Multisite Plugin Manager.', 'psts').'</p></div>';
		}
	}

  //options added to site-settings.php edit page. Overrides sitewide control settings for an individual blog.
	function blog_options_form($blog_id) {
	  $plugins = get_plugins();
	  $override_plugins = (array)get_blog_option($blog_id, 'psts_plugins');
	  ?>
	  </table>
	  <h3><?php _e('Plugin Override Options', 'psts') ?></h3>
	  <p style="padding:5px 10px 0 10px;margin:0;">
	  <?php _e('Checked plugins here will be accessible to this site, overriding the <a href="admin.php?page=psts-plugins">Premium Plugins</a> settings. Uncheck to return to those permissions.', 'psts') ?>
	  </p>
	  <table class="widefat" style="margin:10px;width:95%;">
	  <thead>
		<tr>
			<th title="<?php _e('Site users may activate/deactivate', 'psts') ?>"><?php _e('User Control', 'psts') ?></th>
	    <th><?php _e('Name', 'psts'); ?></th>
			<th><?php _e('Version', 'psts'); ?></th>
			<th><?php _e('Author', 'psts'); ?></th>
		</tr>
		</thead>
	  <?php
	  foreach ( $plugins as $file => $p ) {

	  	//skip network plugins or network activated plugins
		  if ( is_network_only_plugin( $file ) || is_plugin_active_for_network( $file ) )
		    continue;
			?>
			<tr>
				<td>
				<?php
			  $checked = (in_array($file, $override_plugins)) ? 'checked="checked"' : '';
			  echo '<label><input name="plugins['.$file.']" type="checkbox" value="1" '.$checked.'/> ' . __('Enable', 'psts') . '</label>';
				?>
				</td>
		 		<td><?php echo $p['Name']?></td>
		 		<td><?php echo $p['Version']?></td>
		 		<td><?php echo $p['Author']?></td>
			</tr>
			<?php
	  }
	  echo '</table>';
	}

	//process options from site-settings.php edit page. Overrides sitewide control settings for an individual blog.
	function blog_options_form_process() {
	  $override_plugins = array();
	  if (is_array($_POST['plugins'])) {
	    foreach ((array)$_POST['plugins'] as $plugin => $value) {
	      $override_plugins[] = $plugin;
	    }
	    update_option( "psts_plugins", $override_plugins );
	  } else {
	    update_option( "psts_plugins", array() );
	  }
	}

  //removes the meta information for normal admins
	function remove_plugin_meta($plugin_meta, $plugin_file) {
		if ( is_super_admin() ) {
			return $plugin_meta;
		} else {
    	remove_all_actions("after_plugin_row_$plugin_file");
    	if ( in_array($plugin_file, $this->checkbox_rows) )
	    	add_action( "after_plugin_row_$plugin_file", array(&$this, 'remove_checks') ); //add action to disable row's checkbox
		  return array();
		}
	}

  function remove_plugin_update_row() {
	  if ( !is_super_admin() ) {
    	remove_all_actions('after_plugin_row');
		}
	}

	//activate on new blog
	function new_blog($blog_id) {
	  require_once( ABSPATH.'wp-admin/includes/plugin.php' );
    global $psts;

  	$psts_plugins = (array)$psts->get_setting('pp_plugins');
  	$auto_activate = array();
		switch_to_blog($blog_id);
		
	  //look for valid plugins with anyone access   
		foreach ( $psts_plugins as $plugin_file => $data ) {
		  if ( $data['auto'] && is_numeric($data['level']) && (is_pro_site($blog_id, $data['level']) || $data['level'] == 0) && !is_plugin_active($plugin_file) )
				$auto_activate[] = $plugin_file;
		}
		//if any activate them
		if ( count($auto_activate) ) {
	    activate_plugins($auto_activate, '', false); //silently activate any plugins
		}
		restore_current_blog();
	}
	
	function redirect_plugins_page() {
	  global $plugin_page;
	  if ( isset($plugin_page) && $plugin_page == 'premium-plugins' )
			wp_redirect( admin_url('plugins.php') );
	}
	
  //This page should never be shown
  function plugins_page_redirect() {

		if( !current_user_can('activate_plugins') ) {
			echo "<p>" . __('Nice Try...', 'psts') . "</p>";  //If accessed properly, this message doesn't appear.
			return;
		}

	  echo '<div class="wrap">';
  	echo "<SCRIPT LANGUAGE='JavaScript'>window.location='plugins.php';</script>";
	  echo '<a href="plugins.php">Go Here</a>';
	  echo '</div>'; //div wrap
	}

	function settings() {
  	global $psts;
		?>
		<div class="postbox">
		  <h3 class='hndle'><span><?php _e('Premium Plugins', 'psts') ?></span> - <span class="description"><?php _e('Allows you to create plugin packages only available to selected Pro Site levels.', 'psts') ?></span></h3>
		  <div class="inside">
			  <table class="form-table">
      		<tr valign="top">
				  <th scope="row"><?php _e('Rename Feature', 'psts') ?></th>
				  <td>
				  <input type="text" name="psts[pp_name]" value="<?php echo esc_attr($psts->get_setting('pp_name', __('Premium Plugins', 'psts'))); ?>" size="30" />
				  <br /><?php _e('Required - No HTML! - Make this short and sweet.', 'psts') ?></td>
				  </tr>
			  </table>
		  </div>
		</div>
	  <?php
	}

	function admin_page() {
		global $psts;
		
	  if ( isset($_POST['supporter_plugins']) ) {
      $psts_plugins = array();
      if ( is_array($_POST['plugins']) ) {
        foreach ( $_POST['plugins'] as $plugin => $value ) {
					$value['auto'] = ($value['level'] == 'none') ? 0 : intval(@$value['auto']);
          $psts_plugins[$plugin] = $value;
        }
        $psts->update_setting('pp_plugins', $psts_plugins);

      	echo '<div id="message" class="updated fade"><p>' . __('Settings Saved!', 'psts') . '</p></div>';
      }
    }
	  ?>
	  <div class="wrap">
    <div class="icon32" id="icon-plugins"></div>
    <h2><?php _e('Premium Plugins', 'psts'); ?></h2>
  	<p><?php _e('Select the minimum Pro Site level for premium plugins that you want to enable for sites of that level or above. Checking Auto Activate will activate the plugin when they upgrade to that level. Network only and network activated plugins will not show in this list. Note you can also override plugin permissions on a per-site basis on the <a href="sites.php">edit sites</a> page.', 'psts'); ?></p>

		<form method="post" action="">
		<table class="widefat">
  			<thead>
  				<tr>
  					<th style="width:25%;"><?php _e('Minimum Level', 'psts') ?></th>
            <th style="width:20%;"><?php _e('Plugin', 'psts') ?></th>
  					<th style="width:10%;"><?php _e('Version', 'psts') ?></th>
  					<th><?php _e('Description', 'psts') ?></th>
  				</tr>
  			</thead>
  			<tbody id="plugins">
  		  <?php
        $plugins = get_plugins();
        $psts_plugins = (array)$psts->get_setting('pp_plugins');
        $levels = (array)get_site_option('psts_levels');
        foreach($plugins as $file=>$p) {
          //skip network only plugins
          if ( is_network_only_plugin( $file ) || is_plugin_active_for_network( $file ) )
            continue;
        	?>
        	<tr>
        		<td>
        		<select name="plugins[<?php echo $file; ?>][level]">
             <option value="none"<?php selected(@$psts_plugins[$file]['level'], 'none'); ?>><?php _e('None', 'psts') ?></option>
             <option value="0"<?php selected(@$psts_plugins[$file]['level'], 0); ?>><?php _e('Anyone', 'psts') ?></option>
             <?php
						 foreach ($levels as $level => $value) {
							?><option value="<?php echo $level; ?>"<?php selected(@$psts_plugins[$file]['level'], $level); ?>><?php echo $level . ': ' . esc_attr($value['name']); ?></option><?php
						 }
						 ?>
            </select>
            <label><input type="checkbox" name="plugins[<?php echo $file; ?>][auto]" value="1"<?php checked(@$psts_plugins[$file]['auto']); ?> /> <?php _e('Auto Activate', 'psts') ?></label>
        		</td>
        		<th scope="row"><?php echo $p['Name']?></th>
        		<th scope="row"><?php echo $p['Version']?></th>
        		<td><?php echo $p['Description']?></td>
          </tr>
        <?php } ?>
  			</tbody>
  		</table>
  		
  		<p class="submit"><input type="submit" name="supporter_plugins" class="button-primary" value="<?php _e('Save Changes', 'psts') ?>" /></p>
  	</form>
		</div>
	  <?php
	}
}

//register the module
psts_register_module( 'ProSites_Module_Plugins', __('Premium Plugins', 'psts'), __('Allows you to create plugin packages only available to selected Pro Site levels.', 'psts') );
?>