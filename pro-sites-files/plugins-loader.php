<?php
/*
For handling modules and gateways
*/
class ProSites_PluginLoader {

	public static $modules = array(
		'ProSites_Module_Ads' => 'ads',
		'ProSites_Module_ProWidget' => 'badge-widget',
		'ProSites_Module_BP' => 'buddypress',
		'ProSites_Module_BulkUpgrades' => 'bulk-upgrades',
		'ProSites_Module_MarketPress_Global' => 'marketpress-filter',
		'ProSites_Module_PayToBlog' => 'pay-to-blog',
		'ProSites_Module_PostThrottling' => 'post-throttling',
		'ProSites_Module_PostingQuota' => 'posting-quota',
		'ProSites_Module_Plugins' => 'premium-plugins',
		'ProSites_Module_Plugins_Manager' => 'premium-plugins-manager',
		'ProSites_Module_Support' => 'premium-support',
		'ProSites_Module_PremiumThemes' => 'premium-themes',
		'ProSites_Module_Quota' => 'quota',
		'ProSites_Module_UnfilterHtml' => 'unfiltered-html',
		'ProSites_Module_UpgradeAdminLinks' => 'upgrade-admin-links',
		'ProSites_Module_Writing' => 'write',
		'ProSites_Module_XMLRPC' => 'xmlrpc',
	);

  function __construct() {

	  //load modules
		add_action( 'plugins_loaded', array(&$this, 'load_modules'), 11 );

		//load gateways
		add_action( 'plugins_loaded', array(&$this, 'load_gateways'), 11 );

		//load the logging class to debug payment gateway issues.
		require_once( 'logging.php' );
	}

	public static function require_module( $module ) {

		// Do not load if module does not exist in modules array.
		if ( ! isset( self::$modules[ $module ] ) ) {
			return;
		}

		// Get modules dir.
		$dir = plugin_dir_path( ProSites::$plugin_file ) . 'pro-sites-files/modules/';

		require_once( $dir . self::$modules[$module] . '.php' );
	}

	function load_modules() {
		global $psts;

		//get modules dir
		$dir = $psts->plugin_dir . 'modules/';

		// Avoiding file scan
		$modules = apply_filters( 'prosites_modules', self::$modules );

		ksort( $modules );

		//Save the settings
		if ( isset( $_POST['submit_module_settings'] ) ) {
			//check nonce
			check_admin_referer( 'psts_modules' );

			$psts->update_setting( 'modules_enabled', @$_POST['allowed_modules'] );

			do_action( 'psts_modules_save' );

			update_option( 'psts_module_settings_updated', 1 );

		}
		//include them suppressing errors
		foreach ( $modules as $file ) {
			require_once( $dir . $file . '.php');
		}

		//allow plugins from an external location to register themselves
		do_action('psts_load_modules');

		$modules_enabled = (array) $psts->get_setting( 'modules_enabled' );

		//load chosen plugin classes
		foreach ( array_keys( $modules ) as $class ) {
			$name = call_user_func( $class . '::get_name' );
			$description = call_user_func( $class . '::get_description' );
			$restriction = '';

			if( method_exists( $class, 'get_class_restriction' ) ) {
				$restriction = call_user_func( $class . '::get_class_restriction' );
			}

			if ( empty( $restriction ) || ( ! empty( $restriction) && class_exists( $restriction ) ) ) {
				psts_register_module( $class, $name, $description );
			}

			if ( class_exists( $class ) && in_array( $class, $modules_enabled ) ) {
				global $$class;
				$$class = new $class;
			}

		}

  }

	function load_gateways() {
		global $psts;

		//get gateways dir
		$dir = $psts->plugin_dir . 'gateways/';

		//search the dir for files
		$gateways = array();
		if ( ! is_dir( $dir ) ) {
			return;
		}
		if ( ! $dh = opendir( $dir ) ) {
			return;
		}
		while ( ( $gateway = readdir( $dh ) ) !== false ) {
			if ( substr( $gateway, - 4 ) == '.php' ) {
				$gateways[] = $dir . $gateway;
			}
		}
		closedir( $dh );
		sort( $gateways );

		//include them suppressing errors
		foreach ( $gateways as $file ) {
			include_once( $file );
		}

		//allow plugins from an external location to register themselves
		do_action( 'psts_load_gateways' );

		//load chosen plugin class
		global $psts_gateways, $psts_active_gateways;
		foreach ( (array) $psts_gateways as $class => $gateway ) {
			if ( class_exists( $class ) && in_array( $class, (array) $psts->get_setting( 'gateways_enabled' ) ) ) {
				$psts_active_gateways[] = new $class;
			}
		}
	}

}

//load the class
$psts_plugin_loader = new ProSites_PluginLoader();

/**
 * Use this function to register your gateway plugin class
 *
 * @param string $class_name - the case sensitive name of your plugin class
 * @param string $name - the nice name for your plugin
 * @param string $description - Short description of your gateway, for the admin side.
 */
function psts_register_gateway( $class_name, $name, $description, $demo = false ) {
	global $psts_gateways;

	if ( ! is_array( $psts_gateways ) ) {
		$psts_gateways = array();
	}

	if ( class_exists( $class_name ) ) {
		$psts_gateways[ $class_name ] = array( $name, $description, $demo );
	} else {
		return false;
	}
}

/**
 * Use this function to register your module class
 *
 * @param string $class_name - the case sensitive name of your plugin class
 * @param string $name - the nice name for your plugin
 * @param string $description - Short description of the module, for the admin side.
 */
function psts_register_module($class_name, $name, $description, $demo = false) {
  global $psts_modules;

  if (!is_array($psts_modules)) {
		$psts_modules = array();
	}

	if (class_exists($class_name)) {
		$psts_modules[$class_name] = array($name, $description, $demo);
	} else {
		return false;
	}
}