<?php

/*
Pro Sites (Module: Premium Plugins Manager)
*/

class ProSites_Module_Plugins_Manager {

	static $user_label;
	static $user_description;

	var $checkbox_rows = array();

	// Module name for registering
	public static function get_name() {
		return __('Premium Plugins Manager', 'psts');
	}

	// Module description for registering
	public static function get_description() {
		return __('Allows you to create plugin packages only available to selected Pro Site levels.', 'psts');
	}

	static function run_critical_tasks() {
		if ( ! defined( 'PSTS_DISABLE_PLUGINS_PAGE_OVERRIDE' ) ) {
			add_filter( 'site_option_menu_items', array( get_class(), 'enable_plugins_page' ) );
		}
	}

	function __construct() {
		add_action( 'psts_page_after_modules', array( &$this, 'plug_network_page' ) );

		add_action( 'admin_notices', array( &$this, 'message_output' ) );
		add_action( 'psts_withdraw', array( &$this, 'deactivate_all' ) );
		add_action( 'psts_upgrade', array( &$this, 'activate' ), 10, 3 );
		add_action( 'psts_downgrade', array( &$this, 'deactivate' ), 10, 3 );

		add_filter( 'all_plugins', array( &$this, 'remove_plugins' ) );
		
		add_action( 'wpmueditblogaction', array( &$this, 'blog_options_form' ) );
		add_action( 'wpmu_update_blog_options', array( &$this, 'blog_options_form_process' ) );
		
		self::$user_label       = __( 'Premium Plugins Manager', 'psts' );
		self::$user_description = __( 'Include premium plugins', 'psts' );
	}
	
	function plug_network_page() {
		$module_page = add_submenu_page( 'psts', __( 'Pro Sites Premium Plugins Manager', 'psts' ), __( 'Premium Plugins Manager', 'psts' ), 'manage_network_options', 'psts-plugins-manager', array(
			&$this,
			'admin_page'
		) );

		add_action( 'admin_print_styles-' . $module_page, array( &$this, 'load_settings_style' ) );
		add_action( 'admin_footer', array( &$this, 'load_settings_script' ) );
	}
	
	function load_settings_style() {
		ProSites_Helper_UI::load_psts_style();
		ProSites_Helper_UI::load_chosen();
	}
	
	function load_settings_script() {
		?>
		<script type="text/javascript">
		jQuery(function($) {
			$('.ppm_level').change(function(){
				window.location.href = '<?php echo network_admin_url( 'admin.php?page=psts-plugins-manager&level=' ) ?>' + $(this).val();
			});
		});
		</script>
		<?php
	}
	
	function admin_page() {
		
		global $psts;
		$levels 	= (array) get_site_option( 'psts_levels' );
		$level_ids 	= array_keys( $levels );
		$plugins      	= get_plugins();
		$updated	= false;
		
		if( isset( $_POST['supporter_plugins_manager'] ) ){
			$psts->update_setting( 'psts_ppm_' . $_POST['psts_ppm']['level'], $_POST['psts_ppm']['level_' . $_POST['psts_ppm']['level']]  );
			$updated = true;
		}
		
		$active_plugins = isset( $_REQUEST['level'] ) ? $psts->get_setting( 'psts_ppm_' . $_REQUEST['level'] ) : array();
		if( ! is_array( $active_plugins ) ) $active_plugins = array();
		
		?>
		<div class="wrap">
			<div class="icon32" id="icon-plugins"></div>
			<h2><?php _e( 'Premium Plugins Manager', 'psts' ); ?></h2>
			<?php if( $updated ) { ?>
			<div class="updated">
				<p><?php _e( 'Setings saved!', 'psts' ); ?></p>
			</div>
			<?php } ?>
			<form method="post" action="">
				<p>
					<label><?php _e( 'Select a Level', 'psts' ); ?></label>
					<select name="psts_ppm[level]" class="ppm_level">
						<option value=""><label><?php _e( 'Select a pro Level', 'psts' ); ?></label></option>
						<option <?php echo isset( $_REQUEST['level'] ) && $_REQUEST['level'] == 0 ? 'selected' : '' ?> value="0"><label><?php _e( 'Free Level', 'psts' ); ?></label></option>
						<?php foreach( $levels as $level => $value ) { ?>
						<option <?php echo isset( $_REQUEST['level'] ) && $_REQUEST['level'] == $level ? 'selected' : '' ?> value="<?php echo $level ?>"><?php echo $level . ' - ' . $value['name'] ?></option>
						<?php } ?>
					</select>
				</p>
				<?php if( isset( $_REQUEST['level'] ) && ( in_array( $_REQUEST['level'], $level_ids ) || $_REQUEST['level'] == 0 ) ) { ?>
				<div class="psts_level_plugins">
					<p><?php _e( 'Select Plugins that you want to make available for this level.', 'psts' ) ?></p>
					<table class="widefat prosites-premium-plugins">
						<thead>
							<tr>
								<th style="width:5%;">&nbsp;</th>
								<th style="width:20%;"><?php _e( 'Plugin', 'psts' ) ?></th>
								<th style="width:10%;"><?php _e( 'Version', 'psts' ) ?></th>
								<th><?php _e( 'Description', 'psts' ) ?></th>
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
								<td>
									<input <?php echo in_array( $file, $active_plugins ) ? 'checked' : '' ?> style="position: relative; top: 5px" type="checkbox" name="psts_ppm[level_<?php echo $_REQUEST['level'] ?>][]" value="<?php echo $file; ?>">
								</td>
								<th scope="row"><p><?php echo $p['Name'] ?></p></th>
								<th scope="row"><p><?php echo $p['Version'] ?></p></th>
								<td><?php echo $p['Description'] ?></td>
							</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
				<p class="submit">
					<input type="submit" name="supporter_plugins_manager" class="button-primary" value="<?php _e( 'Save Changes', 'psts' ) ?>"/>
				</p>
				<?php } ?>
			</form>
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
				<td><?php echo $p['Name'] ?></td>
				<td><?php echo $p['Version'] ?></td>
				<td><?php echo $p['Author'] ?></td>
			</tr>
		<?php
		}
		echo '</table>';
	}

	//process options from site-settings.php edit page. Overrides sitewide control settings for an individual blog.
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
		/**$blog_id = $_POST['id'];
		
		
		$level = $psts->get_level( $blog_id );
		$pro_plugins = $psts->get_setting( 'psts_ppm_' . $level );
		if( ! is_array( $pro_plugins ) ) $pro_plugins = array();
		
		$override_plugins = (array) get_blog_option( $blog_id, 'psts_plugins' );
		$pro_plugins = array_merge( $pro_plugins, $override_plugins );
		
		$plugins = array_keys( get_plugins() );
		$false_plugins = array_diff( $plugins, $pro_plugins );
		
		switch_to_blog()
		
		echo "<pre>";
print_r($false_plugins);
echo "</pre>";


die();*/
	}
	
	function get_all_pro_plugins( $exclude = 0 ) {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		global $psts;
		
		$levels = (array) get_site_option( 'psts_levels' );
		$pro_plugins = array();
		foreach( $levels as $level => $value ){
			if( $exclude > 0 && $exclude == $level ) continue;
			
			$pro_level_plugins = $psts->get_setting( 'psts_ppm_' . $level );
			foreach( $pro_level_plugins as $pro_level_plugin ){
				$pro_plugins[] = $pro_level_plugin;
			}
		}
		
		return $pro_plugins;
	}
	
	function deactivate_all( $blog_id ) {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		global $psts;

		if ( is_pro_site( $blog_id ) ) {
			return;
		}
		
		$pro_plugins = $this->get_all_pro_plugins();
		$override_plugins = (array) get_blog_option( $blog_id, 'psts_plugins' );
		$pro_plugins = array_diff( $pro_plugins, $override_plugins );
		
		if( count( $pro_plugins ) ){
			switch_to_blog( $blog_id );
			deactivate_plugins( $pro_plugins, true );
			restore_current_blog();
		}
	}
	
	function deactivate( $blog_id, $new_level, $old_level ) {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		global $psts;
		
		$old_level_plugins = $psts->get_setting( 'psts_ppm_' . $old_level );
		$override_plugins = (array) get_blog_option( $blog_id, 'psts_plugins' );
		$old_level_plugins = array_diff( $old_level_plugins, $override_plugins );

		if ( count( $old_level_plugins ) ) {
			switch_to_blog( $blog_id );
			deactivate_plugins( $old_level_plugins, true ); //silently remove any plugins so that uninstall hooks aren't fired
			restore_current_blog();
		}
	}
	
	function activate( $blog_id, $new_level, $old_level ) {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		global $psts;
		
		$old_level_plugins = $psts->get_setting( 'psts_ppm_' . $old_level );
		$override_plugins = (array) get_blog_option( $blog_id, 'psts_plugins' );
		$old_level_plugins = array_diff( $old_level_plugins, $override_plugins );

		if ( count( $old_level_plugins ) ) {
			switch_to_blog( $blog_id );
			deactivate_plugins( $old_level_plugins, true ); //silently remove any plugins so that uninstall hooks aren't fired
			restore_current_blog();
		}
	}
	
	function remove_plugins( $all_plugins ) {
		global $psts, $blog_id;

		if ( is_super_admin() ) {
			return $all_plugins;
		}
		
		$level = $psts->get_level( $blog_id );
		$pro_plugins = $psts->get_setting( 'psts_ppm_' . $level );
		if( ! is_array( $pro_plugins ) ) $pro_plugins = array();
		
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
	
	// Static hooks
	public static function enable_plugins_page($menu_items) {
		$menu_items['plugins'] = 1;
		return $menu_items;
	}


}