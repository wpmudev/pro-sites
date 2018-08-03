<?php

/*
Pro Sites (Module: Premium Plugins Manager)
*/

class ProSites_Module_Plugins_Manager {

	static $user_label;
	static $user_description;

	var $checkbox_rows = array();

	// Module name for registering

	function __construct() {

		add_action( 'psts_page_after_modules', array( &$this, 'plug_network_page' ) );

		add_action( 'admin_notices', array( &$this, 'message_output' ) );
		add_action( 'psts_withdraw', array( &$this, 'deactivate_all' ) );
		add_action( 'psts_upgrade', array( &$this, 'deactivate' ), 10, 3 );
		add_action( 'psts_downgrade', array( &$this, 'deactivate' ), 10, 3 );

		add_filter( 'all_plugins', array( &$this, 'remove_plugins' ) );

		add_action( 'wpmueditblogaction', array( &$this, 'blog_options_form' ) );
		add_action( 'wpmu_update_blog_options', array( &$this, 'blog_options_form_process' ) );

		self::$user_label       = __( 'Premium Plugins Manager', 'psts' );
		self::$user_description = __( 'Include premium plugins', 'psts' );
	}

	// Module description for registering

	public static function get_name() {
		return __('Premium Plugins Manager', 'psts');
	}

	public static function get_description() {
		return __('Easily create plugin packages only available to selected Pro Site levels. (Can\'t be used with "Premium Plugins")', 'psts');
	}

	static function run_critical_tasks() {
//		if ( ! defined( 'PSTS_DISABLE_PLUGINS_PAGE_OVERRIDE' ) ) {
//			add_filter( 'site_option_menu_items', array( get_class(), 'enable_plugins_page' ) );
//		}
	}

	public static function enable_plugins_page($menu_items) {
		$menu_items['plugins'] = 1;
		return $menu_items;
	}

	function plug_network_page() {
		$module_page = add_submenu_page( 'psts', __( 'Pro Sites Premium Plugins Manager', 'psts' ), __( 'Premium Plugins Manager', 'psts' ), 'manage_network_options', 'psts-plugins-manager', array(
			&$this,
			'admin_page'
		) );

		add_action( 'admin_print_styles-' . $module_page, array( &$this, 'load_settings_style' ) );
	}

	function load_settings_style() {
		ProSites_Helper_UI::load_psts_style();
		ProSites_Helper_UI::load_chosen();
	}

	function admin_page() {

		global $psts;
		$levels = (array) get_site_option( 'psts_levels' );

		array_unshift( $levels, 0 );

		$levels[0] = array( 'name' => 'Free' );

		$plugins    = get_plugins();
		$updated    = false;

		//Process Settings
		if( isset( $_POST['supporter_plugins_manager'] ) ){
			foreach( $levels as $level => $value ){
				$checked = isset( $_POST['psts_ppm'] ) && isset( $_POST['psts_ppm']['level_' . $level] ) ? $_POST['psts_ppm']['level_' . $level] : false;
				$psts->update_setting( 'psts_ppm_' . $level, $checked );
				$updated = true;
			}
			if ( ! defined( 'PSTS_DISABLE_PLUGINS_PAGE_OVERRIDE' ) ) {
				//Enable Plugin Administration menu
				$menu_items = get_site_option('menu_items', array() );
				$menu_items['plugins'] = 1;

				update_site_option( 'menu_items', $menu_items );
			}
		}

		foreach( $levels as $level => $value ){
			$fn_name = 'active_plugins_' . $level;
			$$fn_name = (array)$psts->get_setting( 'psts_ppm_' . $level );
		}

		?>
		<div class="wrap">
			<h1><?php _e( 'Premium Plugins Manager', 'psts' ); ?></h1>
			<?php if( $updated ) { ?>
			<div class="updated">
				<p><?php _e( 'Settings saved!', 'psts' ); ?></p>
			</div>
			<?php } ?>
			<form method="post" action="">
				<div class="psts_level_plugins">
					<p><?php _e( 'Select Plugins that you want to make available for this level.', 'psts' ) ?></p>
					<table class="widefat prosites-premium-plugins">
						<thead>
							<tr>
								<?php foreach( $levels as $level => $value ) { ?>
								<th class="psts_plugin_manager_level"><?php echo esc_html( $value['name'] ); ?></th>
								<?php } ?>
								<th class="psts_plugin_manager_name"><?php _e( 'Plugin', 'psts' ) ?></th>
								<th class="psts_plugin_manager_version"><?php _e( 'Version', 'psts' ) ?></th>
								<th class="psts_plugin_manager_description"><?php _e( 'Description', 'psts' ) ?></th>
							</tr>
						</thead>
						<tbody id="plugins">
							<?php foreach ( $plugins as $file => $p ) { ?>
							<?php
								//skip network only plugins
								if ( is_network_only_plugin( $file ) || is_plugin_active_for_network( $file ) ) {
									continue;
								}
							?>
							<tr>
								<?php foreach( $levels as $level => $value ) { ?>
								<?php
									$array = 'active_plugins_' . $level;
								?>
								<th class="psts_plugin_manager_level_checkbox">
									<input <?php echo in_array( $file, ( array ) $$array ) ? 'checked' : '' ?> style="position: relative; top: 5px" type="checkbox" name="psts_ppm[level_<?php echo $level ?>][]" value="<?php echo $file; ?>">
								</th>
								<?php } ?>
								<th scope="row"><p><?php echo esc_html($p['Name']); ?></p></th>
								<th scope="row"><p><?php echo esc_html($p['Version']); ?></p></th>
								<td><?php echo $p['Description']; ?></td>
							</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
				<p class="submit">
					<input type="submit" name="supporter_plugins_manager" class="button-primary" value="<?php _e( 'Save Changes', 'psts' ) ?>"/>
				</p>
			</form>
		</div>

		<div class="current_setup">
			<h3>Your current Setup</h3>
			<table class="widefat prosites-premium-plugins">
				<thead>
					<tr>
						<th>Level Name</th>
						<?php foreach( $levels as $level => $value ) { ?>
						<th><?php echo esc_html($value['name']) ?></th>
						<?php } ?>
					</tr>
				</thead>
				<tbody>
					<tr>
						<th valign="top">Plugins</th>
						<?php foreach( $levels as $level => $value ) { ?>
						<td>
						<?php
							$active_plugins = ( array ) $psts->get_setting( 'psts_ppm_' . $level );
							foreach( $active_plugins as $active_plugin ){
								if( empty( $active_plugin ) ) {
									continue;
								}
								echo esc_html($plugins[$active_plugin]['Name']) . '<br>';
							}
						?>
						</td>
						<?php } ?>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	function message_output() {
		global $pagenow;

		//advertises premium plugins on the main plugins page.
		if ( $pagenow == 'plugins.php' && is_super_admin() ) {
			echo '<div class="updated"><p>' . __( 'As a Super Admin you can activate any plugins for this site.', 'psts' ) . '</p></div>';
		}

		//Warns of Multisite Plugin Manager conflict
		if ( class_exists( 'PluginManager' ) && is_super_admin() ) {
			echo '<div class="error"><p>' . __( 'WARNING: Multisite Plugin Manager and the Premium Plugins module are incompatible. Please remove Multisite Plugin Manager.', 'psts' ) . '</p></div>';
		}
	}

	//process options from site-settings.php edit page. Overrides sitewide control settings for an individual blog.

	function blog_options_form( $blog_id ) {
		$plugins          = get_plugins();
		$override_plugins = (array) get_blog_option( $blog_id, 'psts_plugins' );
		?>
		</table>
		<h3><?php _e( 'Plugin Override Options', 'psts' ) ?></h3>
		<p style="padding:5px 10px 0 10px;margin:0;">
			<?php _e( 'Checked plugins here will be accessible to this site, overriding the <a href="admin.php?page=psts-plugins">Premium Plugins</a> settings. Uncheck to return to those permissions.', 'psts' ) ?>
		</p>
		<table class="widefat" style="margin:10px;width:95%;">
		<thead>
		<tr>
			<th title="<?php _e( 'Site users may activate/deactivate', 'psts' ) ?>"><?php _e( 'User Control', 'psts' ) ?></th>
			<th><?php _e( 'Name', 'psts' ); ?></th>
			<th><?php _e( 'Version', 'psts' ); ?></th>
			<th><?php _e( 'Author', 'psts' ); ?></th>
		</tr>
		</thead>
		<?php
		foreach ( $plugins as $file => $p ) {

			//skip network plugins or network activated plugins
			if ( is_network_only_plugin( $file ) || is_plugin_active_for_network( $file ) ) {
				continue;
			}
			?>
			<tr>
				<td>
					<?php
					$checked = ( in_array( $file, $override_plugins ) ) ? 'checked="checked"' : '';
					echo '<label><input name="plugins[' . $file . ']" type="checkbox" value="1" ' . $checked . '/> ' . __( 'Enable', 'psts' ) . '</label>';
					?>
				</td>
				<td><?php echo esc_html( $p['Name'] ); ?></td>
				<td><?php echo esc_html( $p['Version'] ); ?></td>
				<td><?php echo esc_html( $p['Author'] ); ?></td>
			</tr>
		<?php
		}
		echo '</table>';
	}

	function blog_options_form_process() {
		global $psts;

		$override_plugins = array();
		if ( isset( $_POST['plugins'] ) && is_array( $_POST['plugins'] ) ) {
			foreach ( (array) $_POST['plugins'] as $plugin => $value ) {
				$override_plugins[] = $plugin;
			}
			update_option( "psts_plugins", $override_plugins );
		} else {
			update_option( "psts_plugins", array() );
		}

	}

	function deactivate_all( $blog_id ) {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		global $psts;

		if ( is_pro_site( $blog_id ) ) {
			return;
		}

		$pro_plugins = $this->get_all_pro_plugins();

		// Withdrawal level will be 0. So get the free level plugins.
		$new_level_plugins = (array) $psts->get_setting( 'psts_ppm_0' );

		// Get the overridden plugins if any.
		$override_plugins = (array) get_blog_option( $blog_id, 'psts_plugins' );

		// Merge new level and overridden plugins.
		$override_plugins = array_merge( $new_level_plugins, $override_plugins );

		// Get the plugins to deactivate.
		$pro_plugins = array_diff( $pro_plugins, $override_plugins );

		if( count( $pro_plugins ) ){
			switch_to_blog( $blog_id );
			deactivate_plugins( $pro_plugins, true );
			restore_current_blog();
		}
	}

	function get_all_pro_plugins( $exclude = 0 ) {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		global $psts;

		$levels = (array) get_site_option( 'psts_levels' );
		$pro_plugins = array();
		foreach( $levels as $level => $value ){
			if( $exclude > 0 && $exclude == $level ) {continue;}

			$pro_level_plugins = $psts->get_setting( 'psts_ppm_' . $level, array() );
			if( empty( $pro_level_plugins ) ) {
			    return $pro_plugins;
			}
			foreach( $pro_level_plugins as $pro_level_plugin ){
				$pro_plugins[] = $pro_level_plugin;
			}
		}

		return $pro_plugins;
	}

	function deactivate( $blog_id, $new_level, $old_level ) {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		global $psts;

		$old_level_plugins = (array) $psts->get_setting( 'psts_ppm_' . $old_level, array() );

		// Get the plugins for existing level
		$new_level_plugins = (array) $psts->get_setting( 'psts_ppm_' . $new_level, array() );

		// Get the overridden plugins if any.
		$override_plugins = (array) get_blog_option( $blog_id, 'psts_plugins' );

		// Merge new level and overridden plugins.
		$override_plugins = array_merge( $new_level_plugins, $override_plugins );

		// Get the plugins to deactivate.
		$old_level_plugins = array_diff( $old_level_plugins, $override_plugins );

		if ( count( $old_level_plugins ) ) {
			switch_to_blog( $blog_id );
			deactivate_plugins( $old_level_plugins, true ); //silently remove any plugins so that uninstall hooks aren't fired
			restore_current_blog();
		}
	}

	/**
    * Activate the plugins on blog setup
	* @param $blog_id
	* @param $new_level
	* @param $old_level
    */
	function activate( $blog_id, $new_level, $old_level ) {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		global $psts;

		$psts_plugins  = (array) $psts->get_setting( 'psts_ppm_' . $new_level );

		$level_plugins = array();
		foreach ( $psts_plugins as $plugin_file => $data ) {
			if( empty( $data ) ) {
				continue;
			}
			if ( $data['auto'] && is_numeric( $data['level'] ) && $data['level'] > $old_level && $data['level'] <= $new_level ) {
				$level_plugins[] = $plugin_file;
			}
		}

		if ( count( $level_plugins ) && is_pro_site( $blog_id, $new_level ) ) {
			switch_to_blog( $blog_id );
			foreach ($level_plugins as $plugin ) {
				//Check If plugin file exists
				$valid_plugin = validate_plugin( $plugin );
				if ( !is_wp_error( $valid_plugin ) && ! is_plugin_active( $plugin ) ) {
					activate_plugin( $plugin, false, false, true );
				}
			}
			restore_current_blog();
		}
	}

	// Static hooks

	function remove_plugins( $all_plugins ) {
		global $psts, $blog_id;

		if ( is_super_admin() ) {
			return $all_plugins;
		}

		$level = $psts->get_level( $blog_id );
		$pro_plugins = $psts->get_setting( 'psts_ppm_' . $level );
		if( ! is_array( $pro_plugins ) ) {$pro_plugins = array();}

		$override_plugins = (array) get_blog_option( $blog_id, 'psts_plugins' );
		$pro_plugins = array_merge( $pro_plugins, $override_plugins );

		foreach( $all_plugins as $file => $plugin ){
			if( ! in_array( $file, $pro_plugins ) ){
				//skip network only plugins
				if ( is_network_only_plugin( $file ) || is_plugin_active_for_network( $file ) ) {

				}else{
					deactivate_plugins( $file, true );
				}
				unset( $all_plugins[$file] );
			}
		}

		return $all_plugins;
	}


}