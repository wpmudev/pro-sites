<?php
/*
Plugin Name: Pro Sites
Plugin URI: https://premium.wpmudev.org/project/pro-sites/
Description: The ultimate multisite site upgrade plugin, turn regular sites into multiple pro site subscription levels selling access to storage space, premium themes, premium plugins and much more!
Author: WPMU DEV
Version: 3.5.5
Author URI: https://premium.wpmudev.org/
Text Domain: psts
Domain Path: /pro-sites-files/languages/
Network: true
WDP ID: 49
*/

/*
Copyright 2007-2017 Incsub (http://incsub.com)
Author - Aaron Edwards
Contributors - Rheinard Korf, Jonathan Cowher, Carlos Vences, Andrew Billits, Umesh Kumar

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class ProSites {

	var $version = '3.5.5';
	var $location;
	var $language;
	var $plugin_dir = '';
	var $plugin_url = '';
	var $pro_sites = array();
	var $level = array();
	var $checkout_processed = false;
	var $tcpdf = array(); //Array for PDF settings

	//setup error var
	var $errors = '';

	public static $plugin_file = __FILE__;

	function __construct() {

		// Creates the class autoloader.
		spl_autoload_register( array( $this, 'class_loader' ) );

		//setup our variables
		$this->init_vars();

		//install plugin
		register_activation_hook( __FILE__, array( $this, 'install' ) );

		//load dashboard notice
		global $wpmudev_notices;
		$wpmudev_notices[] = array(
			'id'      => 49,
			'name'    => 'Pro Sites',
			'screens' => array(
				'toplevel_page_psts-network',
				'pro-sites_page_psts-stats-network',
				'pro-sites_page_psts-coupons-network',
				'pro-sites_page_psts-levels-network',
				'pro-sites_page_psts-modules-network',
				'pro-sites_page_psts-plugins-network',
				'pro-sites_page_psts-themes-network',
				'pro-sites_page_psts-settings-network',
				'pro-sites_page_psts-gateways-network',
				'pro-sites_page_psts-pricing-settings-network',
			)
		);
		include_once( $this->plugin_dir . 'dash-notice/wpmudev-dash-notification.php' );

		// Force sessions to activate
		add_action( 'init', array( 'ProSites_Helper_Session', 'attempt_force_sessions' ) );

		//load plugins
		require_once( $this->plugin_dir . 'plugins-loader.php' );

		// TAX integration
		ProSites_Helper_Tax::init_tax();

		// Other integrations
		ProSites_Helper_Integration::init();

		/**
		 * Temporary loading for modules
		 *
		 * @todo Improve this
		 */
		//add important filters
		$modules = get_site_option( 'psts_settings' );
		$modules = isset( $modules['modules_enabled'] ) ? $modules['modules_enabled'] : array();

		foreach ( $modules as $module ) {
				ProSites_PluginLoader::require_module( $module );
				// Making sure that important filters are in place rather than loading too late
				if( method_exists( $module, 'run_critical_tasks' ) && ( is_admin() || ! is_main_site( get_current_blog_id() ) ) ) {
					call_user_func( array(  $module, 'run_critical_tasks' ) );
				}
		}

		/**
		 * @todo make Taxamo load as module above (above needs changing first)
		 */
		ProSites_Module_Taxamo::init();


		//localize
		add_action( 'plugins_loaded', array( &$this, 'localization' ) );

		//admin page stuff
		add_action( 'network_admin_menu', array( &$this, 'plug_network_pages' ) );
		add_action( 'admin_menu', array( &$this, 'plug_pages' ) );
		add_action( 'admin_bar_menu', array( &$this, 'add_menu_admin_bar' ), 100 );
		add_action( 'wp_head', array( &$this, 'add_menu_admin_bar_css' ) );
		add_action( 'admin_head', array( &$this, 'add_menu_admin_bar_css' ) );
		add_filter( 'wpmu_blogs_columns', array( &$this, 'add_column' ) );
		add_action( 'manage_sites_custom_column', array( &$this, 'add_column_field' ), 1, 3 );

		add_action( 'init', array( &$this, 'check' ) );
		add_action( 'load-toplevel_page_psts-checkout', array( &$this, 'redirect_checkout' ) );
		add_action( 'admin_init', array(
			&$this,
			'signup_redirect'
		), 100 ); //delay to make sure it is last hook to admin_init

		//trials
		add_action( 'wpmu_new_blog', array( &$this, 'trial_extend' ) );
		add_action( 'admin_notices', array( &$this, 'trial_notice' ), 2 );

		add_action( 'pre_get_posts', array( &$this, 'checkout_page_load' ) );

		// Change signup...
		add_filter( 'register', array( &$this, 'prosites_signup_url' ) );

		add_filter( 'psts_primary_checkout_table', array( 'ProSites_View_Front_Checkout', 'render_checkout_page' ), 10, 3 );
		// Add Registration AJAX handler
		ProSites_Model_Registration::add_ajax_hook();
		add_filter( 'prosite_register_blog_pre_validation', array( 'ProSites_Model_Registration', 'cleanup_unused_user' ), 10, 3 );

		add_action( 'wp_enqueue_scripts', array( &$this, 'registration_page_styles' ) );
		add_filter( 'update_welcome_email', array( 'ProSites_Helper_Registration', 'alter_welcome_for_existing_users' ), 10, 6 );

		//handle signup pages
		add_action('init','ProSites_Helper_ProSite::redirect_signup_page' );
		add_filter( 'prosites_render_checkout_page_period', 'ProSites_View_Front_Gateway::select_current_period', 10, 2 );
		add_filter( 'prosites_render_checkout_page_level', 'ProSites_View_Front_Gateway::select_current_level', 10, 2 );
		// Dismissed signup prompt
		if ( isset( $_GET['psts_dismiss'] ) ) {
			update_option( 'psts_signed_up', 0 );
		}

		//Force Used Space Check in network if quota is enabled
		add_action( 'psts_modules_save', array( $this, 'enable_network_used_space_check' ) );

		add_action( 'psts_process_stats', array( &$this, 'process_stats' ) ); //cronjob hook
		add_filter( 'blog_template_exclude_settings', array(
			&$this,
			'blog_template_settings'
		) ); // exclude pro site setting from blog template copies

		//Disable Blog Activation Email, as of Pay before blog creation
		add_filter( 'wpmu_signup_blog_notification', array( $this, 'disable_user_activation_mail' ), 10 );

		//Register styles
		add_action( 'admin_enqueue_scripts', array( $this, 'register_psts_style' ) );

		//Display the asterisk detail in sites screen
		add_action( 'in_admin_footer', array( $this, 'psts_note' ) );

		//update install script if necessary
		if ( ( ! defined( 'PSTS_DISABLE_UPGRADE' ) || ( defined( 'PSTS_DISABLE_UPGRADE' ) && ! PSTS_DISABLE_UPGRADE ) ) && $this->get_setting( 'version' ) != $this->version ) {
			$this->install();
		}

		// Hooking here until the models get reworked.
		add_action( 'psts_extend', array( $this, 'send_extension_email' ), 10, 7 );

		// New receipt
		add_action( 'prosites_transaction_record', array( get_class(), 'send_receipt' ) );

		//Check for manual signup, on blog activation
		add_action('wpmu_activate_blog', array( $this, 'process_manual_signup'), 10, 5 );

		//Checks if Allow multiple blog signup is disabled, hides the create new site link from dashboard
		add_filter('default_site_option_registration', array($this, 'hide_create_new_site_link') );
		add_filter('site_option_registration', array($this, 'hide_create_new_site_link') );

		//Show message before checkout table
		add_filter('prosites_inner_pricing_table_pre', array($this, 'signup_output') );

		// Take action when a gateway changes
		add_action( 'psts_extend', array( $this, 'cancel_on_gateway_change' ), 10, 6 );

		// Delete blog
		add_action( 'delete_blog', array( &$this, 'delete_blog' ) );

		$this->setup_ajax_hooks();

		$this->errors = new WP_Error();

	}

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

	private function class_loader( $class ) {

		do_action( 'prosites_class_loader_pre_processing', $this );

		$basedir = dirname( __FILE__ );
		$class   = trim( $class );

		$included_classes = array(
			'^ProSites_Helper',
			'^ProSites_View',
			'^ProSites_Model',
			'^ProSites_Gateway',
			'^ProSites_Module',
		);

		/**
		 * @todo: Temporary until gateways are adopted into new structure
		 */
		$class_overrides = array(
			'ProSites_Gateway_2Checkout' => 'gateways/gateway-2checkout.php',
			'ProSites_Gateway_Manual' => 'gateways/gateway-manual.php',
			'ProSites_Gateway_PayPalExpressPro' => 'gateways/gateway-paypal-express-pro.php',
			'ProSites_Gateway_Stripe' => 'gateways/gateway-stripe.php',
		);
		$override_keys = array_keys( $class_overrides );

		$pattern = '/' . implode( '|', $included_classes ) . '/';

		if ( preg_match( $pattern, $class ) ) {

			if( ! in_array( $class, $override_keys ) ) {
				$filename = $basedir . '/pro-sites-files/lib/' . str_replace( '_', DIRECTORY_SEPARATOR, $class ) . '.php';
			} else {
				$filename = $basedir . '/pro-sites-files/' . $class_overrides[ $class ];
			}

			$filename = apply_filters( 'prosites_class_file_override', $filename );

			if ( is_readable( $filename ) ) {
				include_once $filename;

				return true;
			}
		}

		return false;
	}

	function localization() {
		// Load up the localization file if we're using WordPress in a different language
		// Place it in this plugin's "languages" folder and name it "psts-[value in wp-config].mo"
		if ( $this->location == 'plugins' ) {
			load_plugin_textdomain( 'psts', false, plugin_dir_path( __FILE__ ) . 'pro-sites-files/languages/' );
		} else if ( $this->location == 'mu-plugins' ) {
			load_muplugin_textdomain( 'psts', '/pro-sites-files/languages/' );
		}

		//setup language code for jquery datepicker translation
		$temp_locales   = explode( '_', get_locale() );
		$this->language = ( $temp_locales[0] ) ? $temp_locales[0] : 'en';
	}

	function init_vars() {
		//setup proper directories
		if ( defined( 'WP_PLUGIN_URL' ) && defined( 'WP_PLUGIN_DIR' ) && file_exists( plugin_dir_path( __FILE__ ) . basename( __FILE__ ) ) ) {
			$this->location   = 'plugins';
			$this->plugin_dir = plugin_dir_path( __FILE__ ) . 'pro-sites-files/';
			$this->plugin_url = plugins_url( '/pro-sites-files/', __FILE__ );
		} else if ( defined( 'WPMU_PLUGIN_URL' ) && defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/' . basename( __FILE__ ) ) ) {
			$this->location   = 'mu-plugins';
			$this->plugin_dir = WPMU_PLUGIN_DIR . '/pro-sites-files/';
			$this->plugin_url = WPMU_PLUGIN_URL . '/pro-sites-files/';
		} else {
			wp_die( __( 'There was an issue determining where Pro Sites is installed. Please reinstall.', 'psts' ) );
		}
		//Text Domain
		define('PSTS_TEXT_DOMAIN', 'psts');
		define('PSTS_PREFIX', 'psts');

		//load data structures
		require_once( $this->plugin_dir . 'data.php' );
	}

	private function setup_ajax_hooks() {

		add_action( 'wp_ajax_apply_coupon_to_checkout', array( 'ProSites_Helper_Coupons', 'apply_coupon_to_checkout' ) );
		// Adding _nopriv_ for future buy on register
		add_action( 'wp_ajax_nopriv_apply_coupon_to_checkout', array( 'ProSites_Helper_Coupons', 'apply_coupon_to_checkout' ) );

		add_action( 'wp_ajax_nopriv_create_prosite_blog', array( 'ProSites_Model_Registration', 'ajax_create_prosite_blog' ) );
		add_action( 'wp_ajax_nopriv_check_prosite_blog', array( 'ProSites_Model_Registration', 'ajax_check_prosite_blog' ) );
	}

	public static function get_default_settings_array() {
		return array(
			'base_country'             => 'US',
			'currency'                 => 'USD',
			'curr_symbol_position'     => 1,
			'curr_decimal'             => 1,
			'rebrand'                  => __( 'Pro Site', 'psts' ),
			'lbl_signup'               => __( 'Pro Upgrade', 'psts' ),
			'lbl_curr'                 => __( 'Your Account', 'psts' ),
			'gateways_enabled'         => array(),
			'modules_enabled'          => array(),
			'enabled_periods'          => array( 1, 3, 12 ),
			'send_receipts'             => 1,
			'hide_adminmenu'           => 0,
			'hide_adminbar'            => 0,
			'hide_adminbar_super'      => 0,
			'show_signup'              => 1,
			'show_signup_message'      => 0,
			'free_signup'              => 0,
			'multiple_signup'          => 1,
			'free_name'                => __( 'Free', 'psts' ),
			'free_msg'                 => __( 'No thank you, I will continue with a basic site for now', 'psts' ),
			'trial_days'               => get_site_option( "supporter_free_days" ),
			'trial_message'            => __( 'You have DAYS days left in your LEVEL free trial. Checkout now to prevent losing LEVEL features &raquo;', 'psts' ),
			'cancel_message'           => __( 'Your DAYS day trial begins once you click "Subscribe" below. We perform a $1 pre-authorization to ensure your credit card is valid, but we won\'t actually charge your card until the end of your trial. If you don\'t cancel by day DAYS, your card will be charged for the subscription amount shown above. You can cancel your subscription at any time.', 'psts' ),
			'recurring_subscriptions'  => 1,
			'ga_ecommerce'             => 'none',
			'signup_message'           => __( 'Signup for a Pro site', 'psts' ),
			'feature_message'          => __( 'Upgrade to LEVEL to access this feature &raquo;', 'psts' ),
			'active_message'           => __( 'Your Pro Site privileges will expire on: DATE<br />Unless you have canceled your subscription or your site was upgraded via the Bulk Upgrades tool, your Pro Site privileges will automatically be renewed.', 'psts' ),
			'success_subject'          => __( 'Thank you for becoming a Pro Site member!', 'psts' ),
			'success_msg'              => __( "Thank you for becoming a Pro Site member!

We have received your first subscription payment and you can now access all LEVEL features!

Subscription payments should show on your credit card or bank statement as \"THIS COMPANY\". If you ever need to view, modify, upgrade, or cancel your Pro Site subscription you can do so here:
CHECKOUTURL

If you ever have any billing questions please contact us:
http://mysite.com/contact/

Thanks again for joining!", 'psts' ),
			'canceled_subject'         => __( 'Your Pro Site subscription has been canceled', 'psts' ),
			'canceled_msg'             => __( "Your Pro Site subscription has been canceled.

You should continue to have access until ENDDATE.

We are very sorry to see you go, but we are looking forward to you subscribing to our services again.

You can resubscribe at any time here:
CHECKOUTURL

Thanks!", 'psts' ),
			'receipt_subject'          => __( 'Your Pro Site payment receipt', 'psts' ),
			'receipt_msg'              => __( "Your Pro Site subscription payment was successful!

PAYMENTINFO

Subscription payments should show on your credit card or bank statement as \"YOUR COMPANY\". If you ever need to view, modify, upgrade, or cancel your Pro Site subscription you can do so here:
CHECKOUTURL

If you ever have any billing questions please contact us:
http://mysite.com/contact/

Thanks again for being a valued member!", 'psts' ),
			'expired_subject'          => __( 'Your Pro Site status has expired', 'psts' ),
			'expired_msg'              => __( "Unfortunately the Pro status for your site SITENAME (SITEURL) has lapsed.

You can renew your Pro Site status here:
CHECKOUTURL

If you're having billing problems please contact us for help:
http://mysite.com/contact/

Looking forward to having you back as a valued member!", 'psts' ),
			'failed_subject'           => __( 'Your Pro Site subscription payment failed', 'psts' ),
			'failed_msg'               => __( "It seems like there is a problem with your latest Pro Site subscription payment, sorry about that.

Please update your payment information or change your payment method as soon as possible to avoid a lapse in Pro Site features. If you're still having billing problems please contact us for help:
http://mysite.com/contact/

Many thanks again for being a member!", 'psts' ),
			'extension_subject'           => __( 'You have been given free Pro Site membership.', 'psts' ),
			'extension_msg'               => __( "We have given you Pro Site access. You will now be able to enjoy all the benefits of being a Pro Site member.

These benefits will be available to you until: ENDDATE.

After this date your site will revert back to a standard site.

You can subscribe at any time from the link below:
CHECKOUTURL

Thanks!", 'psts' ),
			'revoked_subject'           => __( 'Your permanent Pro Site status has changed.', 'psts' ),
			'revoked_msg'               => __( "Your permanent Pro Site status has been removed. You will continue to have all the benefits of your Pro Site membership until ENDDATE.

After this date your site will revert back to a standard site.

You can subscribe at any time from the link below:
CHECKOUTURL

Thanks!", 'psts' ),
			'pypl_site'                => 'US',
			'pypl_currency'            => 'USD',
			'pypl_status'              => 'test',
			'pypl_enable_pro'          => 0,
			'stripe_ssl'               => 0,
			'mp_name'                  => __( 'Manual Payment', 'psts' ),
			'mp_show_form'             => 0,
			'mp_email'                 => get_site_option( "admin_email" ),
			'pt_name'                  => __( 'Premium Themes', 'psts' ),
			'pt_text'                  => __( 'Upgrade to LEVEL to activate this premium theme &raquo;', 'psts' ),
			'ps_level'                 => 1,
			'ps_email'                 => get_site_option( "admin_email" ),
			'ps_name'                  => __( 'Premium Support', 'psts' ),
			'ps_message'               => __( 'You can send us a priority direct email support request here if you need help with your site.', 'psts' ),
			'ps_notice'                => __( 'To enable premium support, please upgrade to LEVEL &raquo;', 'psts' ),
			'publishing_level'         => 1,
			'publishing_message_posts' => __( 'To enable publishing posts, please upgrade to LEVEL &raquo;', 'psts' ),
			'publishing_message_pages' => __( 'To enable publishing pages, please upgrade to LEVEL &raquo;', 'psts' ),
			'quota_message'            => __( 'For SPACE of upload space, upgrade to LEVEL!', 'psts' ),
			'quota_out_message'        => __( 'You are out of upload space! Please upgrade to LEVEL to enable SPACE of storage space.', 'psts' ),
			'xmlrpc_level'             => 1,
			'xmlrpc_message'           => __( 'To enable XML-RPC remote publishing please upgrade to LEVEL &raquo;', 'psts' ),
			'bp_notice'                => __( 'Upgrade to LEVEL to access this feature &raquo;', 'psts' ),
			'pp_name'                  => __( 'Premium Plugins', 'psts' ),
			'ads_name'                 => __( 'Disable Ads', 'psts' ),
			'ads_level'                => 1,
			'ads_enable_blogs'         => 0,
			'ads_count'                => 3,
			'ads_before_page'          => 0,
			'ads_after_page'           => 0,
			'ads_before_post'          => 0,
			'ads_after_post'           => 0,
			'ads_themes'               => 0,
			'bu_email'                 => get_site_option( "supporter_paypal_email" ),
			'bu_status'                => 'test',
			'bu_payment_type'          => 'recurring',
			'bu_level'                 => 1,
			'bu_credits_1'             => 10,
			'bu_option_msg'            => __( 'Upgrade CREDITS sites to LEVEL for one year for only PRICE:', 'psts' ),
			'bu_checkout_msg'          => __( 'You can upgrade multiple sites at a lower cost by purchasing Pro Site credits below. After purchasing your credits just come back to this page, search for your sites via the tool at the bottom of the page, and upgrade them to Pro Site status. Each site is upgraded for one year.', 'psts' ),
			'bu_payment_msg'           => __( 'Depending on your payment method it may take just a few minutes (Credit Card or PayPal funds) or it may take several days (eCheck) for your Pro Site credits to become available.', 'psts' ),
			'bu_name'                  => __( 'Bulk Upgrades', 'psts' ),
			'bu_link_msg'              => __( 'Purchase credits to upgrade multiple sites for one discounted price!', 'psts' ),
			'ptb_front_disable'        => 1,
			'ptb_front_msg'            => __( 'This site is temporarily disabled until payment is received. Please check back later.', 'psts' ),
			'ptb_checkout_msg'         => __( 'You must pay to enable your site.', 'psts' ),
			'pq_level'                 => 1,
			'pq_quotas'                => array(
				'post' => array( 'quota' => 'unlimited' ),
				'page' => array( 'quota' => 'unlimited' )
			),
			'uh_level'                 => 1,
			'uh_message'               => __( 'To enable the embedding html, please upgrade to LEVEL &raquo;', 'psts' ),
			'co_pricing'               => 'disabled',
			'plans_table_enabled'      => 'enabled',
			'subsites_ssl'             => is_ssl() ? 1 : 0,
		);
	}

	function install() {
		global $wpdb, $current_site;

		//check if multisite is installed
		if ( ! is_multisite() ) {
			$this->trigger_install_error( __( 'WordPress multisite is required to run this plugin. <a target="_blank" href="http://codex.wordpress.org/Create_A_Network">Create a network</a>.', 'psts' ), E_USER_ERROR );
		}

		//rename tables if upgrading from old supporter
		if ( get_site_option( "supporter_installed" ) == "yes" ) {
			$wpdb->query( "RENAME TABLE `{$wpdb->base_prefix}supporters` TO `{$wpdb->base_prefix}pro_sites`" );
			$wpdb->query( "RENAME TABLE `{$wpdb->base_prefix}supporter_signup_stats` TO `{$wpdb->base_prefix}pro_sites_signup_stats`" );
			$wpdb->query( "RENAME TABLE `{$wpdb->base_prefix}supporter_daily_stats` TO `{$wpdb->base_prefix}pro_sites_daily_stats`" );
			delete_site_option( "supporter_installed" );
		}

		if( ! defined( 'DO_NOT_UPGRADE_GLOBAL_TABLES' ) ) {
			define( 'DO_NOT_UPGRADE_GLOBAL_TABLES', false );
		}

		$table1 = "CREATE TABLE {$wpdb->base_prefix}pro_sites (
		  blog_ID bigint(20) NOT NULL,
		  level int(3) NOT NULL DEFAULT 1,
		  expire bigint(20) NOT NULL,
		  gateway varchar(25) NULL DEFAULT '',
		  term varchar(25) NULL DEFAULT NULL,
		  amount varchar(10) NULL DEFAULT NULL,
		  is_recurring tinyint(1) NULL DEFAULT 1,
		  meta longtext NOT NULL,
		  identifier varchar(50) NULL,
		  PRIMARY KEY  (blog_ID),
		  KEY  (blog_ID,level,expire)
		);";

		$table2 = "CREATE TABLE {$wpdb->base_prefix}pro_sites_signup_stats (
		  action_ID bigint(20) unsigned NOT NULL auto_increment,
		  blog_ID bigint(20) NOT NULL,
		  action varchar(20) NOT NULL,
		  time_stamp DATE NOT NULL,
		  PRIMARY KEY  (action_ID)
		);";

		$table3 = "CREATE TABLE {$wpdb->base_prefix}pro_sites_daily_stats (
		  id bigint(20) unsigned NOT NULL auto_increment,
		  date DATE NOT NULL,
		  supporter_count int(10) NOT NULL DEFAULT 0,
		  expired_count int(10) NOT NULL DEFAULT 0,
		  term_count_1 int(10) NOT NULL DEFAULT 0,
		  term_count_3 int(10) NOT NULL DEFAULT 0,
		  term_count_12 int(10) NOT NULL DEFAULT 0,
		  term_count_manual int(10) NOT NULL DEFAULT 0,
		  level_count_1 int(10) NOT NULL DEFAULT 0,
		  level_count_2 int(10) NOT NULL DEFAULT 0,
		  level_count_3 int(10) NOT NULL DEFAULT 0,
		  level_count_4 int(10) NOT NULL DEFAULT 0,
		  level_count_5 int(10) NOT NULL DEFAULT 0,
		  level_count_6 int(10) NOT NULL DEFAULT 0,
		  level_count_7 int(10) NOT NULL DEFAULT 0,
		  level_count_8 int(10) NOT NULL DEFAULT 0,
		  level_count_9 int(10) NOT NULL DEFAULT 0,
		  level_count_10 int(10) NOT NULL DEFAULT 0,
		  PRIMARY KEY  (id)
		);";

		$table4 = "CREATE TABLE {$wpdb->base_prefix}pro_sites_transactions (
		  id bigint(20) unsigned NOT NULL auto_increment,
		  transaction_id varchar(255) NOT NULL,
		  transaction_date DATE NOT NULL,
		  items longtext NOT NULL,
		  total decimal(13,4) NOT NULL DEFAULT 0,
		  sub_total decimal(13,4) NOT NULL DEFAULT 0,
		  tax_amount decimal(13,4) NOT NULL DEFAULT 0,
		  tax_percentage decimal(4,2) NOT NULL DEFAULT 0,
		  country varchar(3) NULL,
		  currency varchar(3) NULL,
		  meta longtext NULL,
		  PRIMARY KEY  (id),
		  KEY  (id, transaction_id)
		);";

		//@todo: A Check needs to be in place to see if all the table exists or not
		// If we get an error while creating table, plugin user should be aware of it.
		if ( ! defined( 'DO_NOT_UPGRADE_GLOBAL_TABLES' ) || ( defined( 'DO_NOT_UPGRADE_GLOBAL_TABLES' ) && ! DO_NOT_UPGRADE_GLOBAL_TABLES ) ) {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $table1 );
			dbDelta( $table2 );
			dbDelta( $table3 );
			dbDelta( $table4 );
		}

		// add stats cron job action only to main site (or it may be running all the time!)
		switch_to_blog( $current_site->blog_id );
		if ( ! wp_next_scheduled( 'psts_process_stats' ) ) {
			//get end of day
			$time = strtotime( date( "Y-m-d 23:50:00" ) );
			wp_schedule_event( $time, 'daily', 'psts_process_stats' );
		}
		restore_current_blog();

		//our default settings
		$default_settings = ProSites::get_default_settings_array();

		$settings         = wp_parse_args( ( array ) get_site_option( 'psts_settings' ), $default_settings );
		update_site_option( 'psts_settings', $settings );

		//default level
		$default_levels = array(
			1 => array(
				'name'     => __( 'Pro', 'psts' ),
				'price_1'  => get_site_option( "supporter_1_whole_cost" ) . '.' . get_site_option( "supporter_1_partial_cost" ),
				'price_3'  => get_site_option( "supporter_3_whole_cost" ) . '.' . get_site_option( "supporter_3_partial_cost" ),
				'price_12' => get_site_option( "supporter_12_whole_cost" ) . '.' . get_site_option( "supporter_12_partial_cost" )
			)
		);
		if ( ! get_site_option( 'psts_levels' ) ) {
			add_site_option( 'psts_levels', $default_levels );
		}

		//create a checkout page if not existing
		add_action( 'init', array( &$this, 'create_checkout_page' ) );

		//3.4.3.8 upgrade - fixes permanent upgrades that got truncated on 32 bit systems due to (int) casting
		if ( version_compare( $this->get_setting( 'version' ), '3.4.3.7', '<=' ) ) {
			$wpdb->query( "UPDATE {$wpdb->base_prefix}pro_sites SET expire = '9999999999' WHERE expire = '1410065407'" );
		}

		//3.5 upgrade - modify pro_sites table
		if ( version_compare( $this->get_setting( 'version' ), '3.5', '<=' ) ) {
			// Using dbDelta above, but add other code here.
			//$wpdb->query( "ALTER TABLE {$wpdb->base_prefix}pro_sites ADD meta longtext NOT NULL" );
		}

		// If upgrading from a version lesser than or equal to 3.5.4 display options for Paypal pro, otherwise hide them
		if ( $this->get_setting( 'version' ) && version_compare( $this->get_setting( 'version' ), '3.5.4', '<=' ) ) {
			$this->update_setting( 'display_paypal_pro_option', true );
		}

		$this->update_setting( 'version', $this->version );
	}

	//an easy way to get to our settings array without undefined indexes
	function get_setting( $key, $default = null ) {
		$settings = get_site_option( 'psts_settings' );
		$setting  = isset( $settings[ $key ] ) ? $settings[ $key ] : $default;

		$setting = !is_array( $setting ) ? trim( $setting ) : $setting;
		/**
		 * Filter the specific setting, $key parameter value
		 *
		 * @param array $setting
		 * @param mixed $default , null The default value for $key setting if there is no value returned
		 */

		return apply_filters( "psts_setting_$key", $setting, $default );
	}

	//determine if a given level has a setup fee for a given blog id
	function has_setup_fee( $blog_id, $level ) {
		$setup_fee_amt = ( float ) $this->get_setting( 'setup_fee', 0 );

		if( empty( $blog_id ) && 0 < $setup_fee_amt ) {
			return true;
		}

		if ( 0 == $setup_fee_amt ) {
			return false;
		} //setup fee not set or is 0

		if ( $this->get_level( $blog_id ) == 0 ) {
			return true;
		} //this is a free site. always apply setup fee.

		if ( $this->get_level( $blog_id ) > $level ) {
			return false;
		} //customer is downgrading. don't apply setup fee


		if ( ! self::is_trial( $blog_id ) && is_pro_site( $blog_id ) && ! $this->get_setting( 'apply_setup_fee_upgrade', false ) ) {
			return false;
		} //this is a pro site, not in trial, and admin doesn't want setup fees applied to upgrades

		return true;
	}

	function update_setting( $key, $value ) {
		$settings         = get_site_option( 'psts_settings' );
		$settings[ $key ] = $value;

		return update_site_option( 'psts_settings', $settings );
	}

	function get_level_setting( $level, $key, $default = null ) {
		$levels = ( array ) get_site_option( 'psts_levels' );

		return isset( $levels[ $level ][ $key ] ) ? $levels[ $level ][ $key ] : $default;
	}

	function update_level_setting( $level, $key, $value ) {
		$levels                   = ( array ) get_site_option( 'psts_levels' );
		$levels[ $level ][ $key ] = $value;

		return update_site_option( 'psts_levels', $levels );
	}

	/**
	* Add trial days
	*
	* @param $blog_id
	*/
	function trial_extend( $blog_id ) {
		$trial_days = $this->get_setting( 'trial_days' );
		$free_signup = $this->get_setting( 'free_signup' );

		$level = !empty( $_POST['level'] ) ? intval( $_POST['level'] ) : 1;
		if( $free_signup ) {
		    return;
		}elseif ( $trial_days > 0 ) {
			$extend = $trial_days * 86400;
			$this->extend( $blog_id, $extend, 'trial', $level );
		}
	}

	function trial_notice() {
		global $wpdb, $blog_id;
		//get allowed roles for checkout
		$checkout_roles = $this->get_setting( 'checkout_roles', array( 'administrator', 'editor' ) );

		//check If user is allowed
		$current_user_id = get_current_user_id();
		$permission      = $this->check_user_role( $current_user_id, $checkout_roles );

		if ( ! is_main_site() && $permission && $this->get_setting( 'trial_days' ) ) {
			$expire = $wpdb->get_var( $wpdb->prepare( "
				SELECT expire
				FROM {$wpdb->base_prefix}pro_sites
				WHERE blog_ID = %d
					AND ( gateway = 'Trial' OR gateway = 'trial' )
					AND expire >= %s
				LIMIT 1", $blog_id, time()
			) );

			if ( $expire ) {
				$days   = round( ( $expire - time() ) / 86400 ); //calculate days left rounded
				$notice = str_replace( 'LEVEL', $this->get_level_setting( $this->get_level($blog_id), 'name' ), $this->get_setting( 'trial_message' ) );
				$notice = str_replace( 'DAYS', $days, $notice );
				echo '
					<div class="update-nag">
						<a href="' . $this->checkout_url( $blog_id ) . '">' . $notice . '</a>
					</div>';
			}
		}
	}

	function is_trial( $blog_id ) {
		global $wpdb;

		$trialing = ProSites_Helper_Registration::is_trial( $blog_id );
		if( ! $trialing ) {
			$trialing = empty( $blog_id ) ? false : $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d AND ( gateway = 'Trial' OR gateway = 'trial' ) AND expire >= %s LIMIT 1", $blog_id, time() ) );
		}

		return $trialing;
	}

	//run daily via wp_cron
	function process_stats() {
		global $wpdb;

		$date = date( "Y-m-d", time() );

		//don't process if already completed today (in case wp_cron goes nutzy)
		$existing = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_daily_stats WHERE date = '" . $date . "'" );
		if ( $existing ) {
			return;
		}

		$active_pro_sites      = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE expire > '" . time() . "'" );
		$expired_pro_sites     = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE expire <= '" . time() . "'" );
		$term_1_pro_sites      = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term = 1 AND expire > '" . time() . "'" );
		$term_3_pro_sites      = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term = 3 AND expire > '" . time() . "'" );
		$term_12_pro_sites     = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term = 12 AND expire > '" . time() . "'" );
		$term_manual_pro_sites = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term NOT IN (1,3,12) AND expire > '" . time() . "'" );

		//get level counts
		$levels = get_site_option( 'psts_levels' );
		$level_count = array();
		for ( $i = 1; $i <= 10; $i ++ ) {
			$level_count[ $i ] = 0;
		} //prefill the array
		if ( is_array( $levels ) && count( $levels ) > 1 ) {
			foreach ( $levels as $level => $data ) {
				//if last level include all previous ones greater than that level, in case a level was deleted
				if ( count( $levels ) == $level ) {
					$level_count[ $level ] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE level >= %d AND expire > %s", $level, time() ) );
				} else {
					$level_count[ $level ] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE level = %d AND expire > %s", $level, time() ) );
				}
			}
		} else {
			$level_count[1] = $active_pro_sites;
		}

		$wpdb->query( "INSERT INTO {$wpdb->base_prefix}pro_sites_daily_stats ( date, supporter_count, expired_count, term_count_1, term_count_3, term_count_12, term_count_manual, level_count_1, level_count_2, level_count_3, level_count_4, level_count_5, level_count_6, level_count_7, level_count_8, level_count_9, level_count_10 )
									 VALUES ( '$date', $active_pro_sites, $expired_pro_sites, $term_1_pro_sites, $term_3_pro_sites, $term_12_pro_sites, $term_manual_pro_sites, {$level_count[1]}, {$level_count[2]}, {$level_count[3]}, {$level_count[4]}, {$level_count[5]}, {$level_count[6]}, {$level_count[7]}, {$level_count[8]}, {$level_count[9]}, {$level_count[10]} )" );
	}

	/*
	Used for stats, must be called by payment gateways
	--------------------------------------------------
	Parameters:
	$blog_id = blog's id
	$action = "signup", "cancel", "modify", "upgrade"
	*/
	function record_stat( $blog_id, $action ) {
		global $wpdb;
		//only record one stat action per blog per day
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE blog_ID = %s AND action = %s AND time_stamp = %s", $blog_id, $action, date( 'Y-m-d' ) ) );
		if ( ! $exists ) {
			$wpdb->insert( "{$wpdb->base_prefix}pro_sites_signup_stats", array(
				'blog_ID'    => $blog_id,
				'action'     => $action,
				'time_stamp' => date( 'Y-m-d' )
			), array( '%d', '%s', '%s' ) );
		}
	}

	//returns html of a weekly summary
	function weekly_summary() {
		global $wpdb;

		$img_base = $this->plugin_url . 'images/';

		//count total
		$current_total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE expire > '" . time() . "'" );

		$date       = date( "Y-m-d", strtotime( "-1 week" ) );
		$last_total = $wpdb->get_var( "SELECT supporter_count FROM {$wpdb->base_prefix}pro_sites_daily_stats WHERE date >= '$date' ORDER BY date ASC LIMIT 1" );

		if ( $current_total > $last_total ) {
			$active_diff = "<img src='{$img_base}green-up.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n( $current_total - $last_total ) . "</span>";
		} else if ( $current_total < $last_total ) {
			$active_diff = "<img src='{$img_base}red-down.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n( - ( $current_total - $last_total ) ) . "</span>";
		} else {
			$active_diff = "<span style='font-size: 18px; font-family: arial;'>" . __( 'no change', 'psts' ) . "</span>";
		}

		$text = sprintf( __( '%s active Pro Sites %s since last week', 'psts' ), "<p><span style='font-size: 24px; font-family: arial;'>" . number_format_i18n( $current_total ) . "</span><span style='color: rgb(85, 85, 85);'>", "</span>$active_diff<span style='color: rgb(85, 85, 85);'>" ) . "</span></p>";

		//activity stats
		$week_start                 = strtotime( "-1 week" );
		$week_start_date            = date( 'Y-m-d', $week_start );
		$this_week['total_signups'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'signup' AND time_stamp >= '$week_start_date'" );
		$this_week['upgrades']      = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'upgrade' AND time_stamp >= '$week_start_date'" );
		$this_week['cancels']       = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'cancel' AND time_stamp >= '$week_start_date'" );
		$number_trial               = count( ProSites_Helper_Registration::get_all_trial_blogs() );

		$week_end                   = $week_start;
		$week_start                 = strtotime( "-1 week", $week_start );
		$week_start_date            = date( 'Y-m-d', $week_start );
		$week_end_date              = date( 'Y-m-d', $week_end );
		$last_week['total_signups'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'signup' AND time_stamp >= '$week_start_date' AND time_stamp < '$week_end_date'" );
		$last_week['upgrades']      = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'upgrade' AND time_stamp >= '$week_start_date' AND time_stamp < '$week_end_date'" );
		$last_week['cancels']       = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'cancel' AND time_stamp >= '$week_start_date' AND time_stamp < '$week_end_date'" );

		if ( $this_week['total_signups'] > $last_week['total_signups'] ) {
			$diff = "<img src='{$img_base}green-up.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n( $this_week['total_signups'] - $last_week['total_signups'] ) . "</span>";
		} else if ( $this_week['total_signups'] < $last_week['total_signups'] ) {
			$diff = "<img src='{$img_base}red-down.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n( - ( $this_week['total_signups'] - $last_week['total_signups'] ) ) . "</span>";
		} else {
			$diff = "<span style='font-size: 18px; font-family: arial;'>" . __( 'no change', 'psts' ) . "</span>";
		}

		$text .= sprintf( __( '%s new signups this week %s compared to last week', 'psts' ), "\n<p><span style='font-size: 24px; font-family: arial;'>" . number_format_i18n( $this_week['total_signups'] ) . "</span><span style='color: rgb(85, 85, 85);'>", "</span>$diff<span style='color: rgb(85, 85, 85);'>" ) . "</span></p>";

		if ( $this_week['upgrades'] > $last_week['upgrades'] ) {
			$diff = "<img src='{$img_base}green-up.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n( $this_week['upgrades'] - $last_week['upgrades'] ) . "</span>";
		} else if ( $this_week['upgrades'] < $last_week['upgrades'] ) {
			$diff = "<img src='{$img_base}red-down.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n( - ( $this_week['upgrades'] - $last_week['upgrades'] ) ) . "</span>";
		} else {
			$diff = "<span style='font-size: 18px; font-family: arial;'>" . __( 'no change', 'psts' ) . "</span>";
		}

		$text .= sprintf( __( '%s upgrades this week %s compared to last week', 'psts' ), "\n<p><span style='font-size: 24px; font-family: arial;'>" . number_format_i18n( $this_week['upgrades'] ) . "</span><span style='color: rgb(85, 85, 85);'>", "</span>$diff<span style='color: rgb(85, 85, 85);'>" ) . "</span></p>";

		if ( $this_week['cancels'] > $last_week['cancels'] ) {
			$diff = "<img src='{$img_base}red-up.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n( $this_week['cancels'] - $last_week['cancels'] ) . "</span>";
		} else if ( $this_week['cancels'] < $last_week['cancels'] ) {
			$diff = "<img src='{$img_base}green-down.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n( - ( $this_week['cancels'] - $last_week['cancels'] ) ) . "</span>";
		} else {
			$diff = "<span style='font-size: 18px; font-family: arial;'>" . __( 'no change', 'psts' ) . "</span>";
		}

		$text .= sprintf( __( '%s cancelations this week %s compared to last week', 'psts' ), "\n<p><span style='font-size: 24px; font-family: arial;'>" . number_format_i18n( $this_week['cancels'] ) . "</span><span style='color: rgb(85, 85, 85);'>", "</span>$diff<span style='color: rgb(85, 85, 85);'>" ) . "</span></p>";

		// Current active trials
		$text .= sprintf( '<p>' . __( '%s active trials.', 'psts' ) . '</p>', "<span style='font-size: 24px; font-family: arial;'>" . number_format_i18n( $number_trial ) . "</span>" );

		return $text;
	}

	function plug_network_pages() {
		global $psts_plugin_loader;

		//main page
		$psts_main_page         = add_menu_page( __( 'Pro Sites', 'psts' ), __( 'Pro Sites', 'psts' ), 'manage_network_options', 'psts', array(
			&$this,
			'admin_modify'
		), 'dashicons-plus' );
		$psts_manage_sites_page = add_submenu_page( 'psts', __( 'Manage Sites', 'psts' ), __( 'Manage Sites', 'psts' ), 'manage_network_options', 'psts', array(
			&$this,
			'admin_modify'
		) );

		do_action( 'psts_page_after_main' );

		//stats page
		$psts_stats_page = add_submenu_page( 'psts', __( 'Pro Sites Statistics', 'psts' ), __( 'Statistics', 'psts' ), 'manage_network_options', 'psts-stats', array(
			&$this,
			'admin_stats'
		) );


		do_action( 'psts_page_after_stats' );

		//coupons page
//		$psts_coupons_page_old = add_submenu_page( 'psts', __( 'Pro Sites Coupons', 'psts' ), __( 'Coupons', 'psts' ), 'manage_network_options', 'psts-coupons-old', array(
//			&$this,
//			'admin_coupons'
//		) );

		//ProSites_View_Coupons
		$psts_coupons_page = add_submenu_page( 'psts', ProSites_View_Coupons::get_page_name(), ProSites_View_Coupons::get_menu_name(), 'manage_network_options', ProSites_View_Coupons::get_page_slug(), array(
			'ProSites_View_Coupons',
			'render_page'
		) );
		do_action( 'psts_page_after_coupons' );

		//levels page
		$psts_levels_page = add_submenu_page( 'psts', __( 'Pro Sites Levels', 'psts' ), __( 'Levels', 'psts' ), 'manage_network_options', 'psts-levels', array(
			&$this,
			'admin_levels'
		) );

		do_action( 'psts_page_after_levels' );

		//modules page
		$psts_modules_page = add_submenu_page( 'psts', __( 'Pro Sites Modules', 'psts' ), __( 'Modules', 'psts' ), 'manage_network_options', 'psts-modules', array(
			&$this,
			'admin_modules'
		) );

		do_action( 'psts_page_after_modules' );

		$psts_gateways_page = add_submenu_page( 'psts', __( 'Pro Sites Gateways', 'psts' ), __( 'Payment Gateways', 'psts' ), 'manage_network_options', 'psts-gateways', array(
			'ProSites_View_Gateways',
			'render_page'
		) );
		do_action( 'psts_page_after_gateways' );

		//ProSites_View_Settings
		$psts_settings_page = add_submenu_page( 'psts', __( 'Pro Sites Settings', 'psts' ), __( 'Settings', 'psts' ), 'manage_network_options', 'psts-settings', array(
			'ProSites_View_Settings',
			'render_page'
		) );
		do_action( 'psts_page_after_settings' );

		//ProSites_View_Settings
		$psts_pricing_page = add_submenu_page( 'psts', ProSites_View_Pricing::get_page_name(), ProSites_View_Pricing::get_menu_name(), 'manage_network_options', ProSites_View_Pricing::get_page_slug(), array(
			'ProSites_View_Pricing',
			'render_page'
		) );
		do_action( 'psts_page_after_pricing_settings' );

		//checkout page settings
//		$psts_pricing_page_old = add_submenu_page( 'psts', __( 'Pro Sites Pricing Table', 'psts' ), __( 'Pricing Table', 'psts' ), 'manage_network_options', 'psts-pricing-table', array(
//			&$this,
//			'pricing_table_settings'
//		) );

		//register plugin style
		add_action( 'admin_print_styles-' . $psts_main_page, array( &$this, 'load_psts_style' ) );

		//Load style and js for cooupons only
		add_action( 'admin_print_scripts-' . $psts_coupons_page, array( &$this, 'scripts_coupons' ) );
		add_action( 'admin_print_styles-' . $psts_coupons_page, array( &$this, 'css_coupons' ) );

		//Load Stats page js
		add_action( 'admin_print_scripts-' . $psts_stats_page, array( &$this, 'scripts_stats' ) );

		//Load pricing table style and scripts
		add_action( 'admin_print_styles-' . $psts_pricing_page, array( &$this, 'css_pricing' ) );

		//Add PSTS Style to settings page
		add_action( 'admin_print_styles-' . $psts_settings_page, array( &$this, 'load_settings_style' ) );
//		add_action( 'admin_print_styles-' . $psts_settings_page_old, array( &$this, 'load_settings_style' ) );

		//Add PSTS Style to gateways page
		add_action( 'admin_print_styles-' . $psts_gateways_page, array( &$this, 'load_settings_style' ) );

		//Add PSTS Style to pricing page
		add_action( 'admin_print_styles-' . $psts_pricing_page, array( &$this, 'load_levels_style' ) );

		// Add Scripts for Levels page
		add_action( 'admin_print_styles-' . $psts_levels_page, array( &$this, 'load_levels_style' ) );

		do_action( 'psts_after_checkout_page_settings' );

	}

	function plug_pages() {

		//get allowed roles for checkout
		$checkout_roles = $this->get_setting( 'checkout_roles', array( 'administrator', 'editor' ) );

		//check If user is allowed
		$current_user_id = get_current_user_id();
		$permission      = $this->check_user_role( $current_user_id, $checkout_roles );

		if ( ! is_main_site() && ! $this->get_setting( 'hide_adminmenu', 0 ) && $permission ) {
			$label = is_pro_site( get_current_blog_id() ) ? $this->get_setting( 'lbl_curr' ) : $this->get_setting( 'lbl_signup' );
			add_menu_page( $label, $label, 'edit_pages', 'psts-checkout', array(
				&$this,
				'checkout_redirect_page'
			), 'dashicons-plus', 3.12 );
		}
	}

	function add_menu_admin_bar_css() {

		if ( is_main_site() || ! is_admin_bar_showing() || ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) || $this->get_setting( 'hide_adminbar' ) ) {
			return;
		}

		//styles the upgrade button
		?>
		<style type="text/css">#wpadminbar li#wp-admin-bar-pro-site {
				float: right;
			}

			#wpadminbar li#wp-admin-bar-pro-site a {
				padding-top: 3px !important;
				height: 25px !important;
				border-right: 1px solid #333 !important;
			}

			#wpadminbar li#wp-admin-bar-pro-site a span {
				display: block;
				color: #fff;
				font-weight: bold;
				font-size: 11px;
				margin: 0px 1px 0px 1px;
				padding: 0 30px !important;
				border: 1px solid #409ed0 !important;
				height: 18px !important;
				line-height: 18px !important;
				border-radius: 4px;
				-moz-border-radius: 4px;
				-webkit-border-radius: 4px;
				background-image: -moz-linear-gradient(bottom, #3b85ad, #419ece) !important;
				background-image: -ms-linear-gradient(bottom, #3b85ad, #419ece) !important;
				background-image: -webkit-gradient(linear, left bottom, left top, from(#3b85ad), to(#419ece)) !important;
				background-image: -webkit-linear-gradient(bottom, #419ece, #3b85ad) !important;
				background-image: linear-gradient(bottom, #3b85ad, #419ece) !important;
			}

			#wpadminbar li#wp-admin-bar-pro-site a:hover span {
				background-image: -moz-linear-gradient(bottom, #0B93C5, #3b85ad) !important;
				background-image: -ms-linear-gradient(bottom, #0B93C5, #3b85ad) !important;
				background-image: -webkit-gradient(linear, left bottom, left top, from(#0B93C5), to(#3b85ad)) !important;
				background-image: -webkit-linear-gradient(bottom, #3b85ad, #0B93C5) !important;
				background-image: linear-gradient(bottom, #0B93C5, #3b85ad) !important;
				border: 1px solid #3b85ad !important;
				color: #E8F3F8;
			}</style>
	<?php
	}

	function add_menu_admin_bar() {
		global $wp_admin_bar, $blog_id, $wp_version;

		if ( is_main_site() || ! is_admin_bar_showing() || ! is_user_logged_in() ) {
			return;
		}
		//get allowed roles for checkout
		$checkout_roles = $this->get_setting( 'checkout_roles', array( 'administrator', 'editor' ) );

		//check If user is allowed
		$current_user_id = get_current_user_id();
		$permission      = $this->check_user_role( $current_user_id, $checkout_roles );

		//add user admin bar upgrade button
		if ( $permission && ! $this->get_setting( 'hide_adminbar' ) ) {
			$checkout = $this->checkout_url( $blog_id );

			$label = is_pro_site() ? $this->get_setting( 'lbl_curr' ) : $this->get_setting( 'lbl_signup' );
			$label = '<span>' . esc_attr( $label ) . '</span>';

			$wp_admin_bar->add_menu( array(
				'id'     => 'pro-site',
				'parent' => ( version_compare( $wp_version, '3.3', '>=' ) ? 'top-secondary' : false ),
				'title'  => $label,
				'href'   => $checkout
			) );
		}

		//add superadmin status menu
		if ( is_super_admin() && ! $this->get_setting( 'hide_adminbar_super' ) ) {
			$sup_title = is_pro_site() ? $this->get_level_setting( $this->get_level( $blog_id ), 'name' ) : false;
			if ( ! $sup_title ) {
				$sup_title = ( function_exists( 'psts_hide_ads' ) && psts_hide_ads( $blog_id ) )
					? __( 'Upgraded', 'psts' )
					: __( 'Free', 'psts' );
			}
			$expire = $this->get_expire( $blog_id );
			if ( $expire > 2147483647 ) {
				$expire = __( "Permanent", "psts" );
			} else {
				$expire = $expire ? date( "Y-m-d", intval( $expire ) ) : __( "N/A", "psts" );
			}
			$sup_title .= " [{$expire}]";
			$wp_admin_bar->add_menu( array(
				'title'  => $sup_title,
				'href'   => network_admin_url( 'admin.php?page=psts&bid=' . $blog_id ),
				'parent' => false,
				'id'     => 'psts-status'
			) );
		}
	}

	function checkout_url( $blog_id = false, $domain = false ) {
		global $psts;

		$url = $this->get_setting( 'checkout_url' );

		$page = get_post( $this->get_setting( 'checkout_page' ) );
		if ( ! $page || $page->post_status == 'trashed' ) {
			$url = $this->create_checkout_page();
		}
		/*
          //just in case the checkout page was not created do it now
          if (!$url) {
          $this->create_checkout_page();
          $url = $this->get_setting('checkout_url');
          }
         */
		/**
		 * Filter the force SSl option
		 *
		 * @param bool , default is set to wether current page is ssl
		 */
		if ( apply_filters( 'psts_force_ssl', is_ssl() ) ) {
			$url = str_replace( 'http://', 'https://', $url );
		}

		if ( $blog_id ) {
			$url = add_query_arg( array( 'bid' => $blog_id ), $url );
		} elseif ( $domain ) {
			$url = add_query_arg( array( 'domain' => $domain ), $url );
		}

		return $url;
	}

	function redirect_checkout() {
		global $blog_id;
//		wp_redirect( $this->checkout_url( $blog_id ) );
	}

	//creates the checkout page on install and updates
	function create_checkout_page() {
		global $current_site;

		//allow overriding and changing the root site to put the checkout page on
		$checkout_site = defined( 'PSTS_CHECKOUT_SITE' ) ? constant( 'PSTS_CHECKOUT_SITE' ) : $current_site->blog_id;

		//default brand title
		$default_title = $this->get_default_settings_array();
		$default_title = $default_title['rebrand'];
		$rebranded_title = $this->get_setting( 'rebrand', $default_title );

		//insert new page if not existing
		switch_to_blog( $checkout_site );
		$page = get_post( $this->get_setting( 'checkout_page' ) );

		if ( ! $page || $page->post_status == 'trashed' || $page->post_title != $rebranded_title ) {
			$id = wp_insert_post( array(
				'post_title'     => $rebranded_title,
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'post_content'   => stripslashes( get_site_option( 'supporter_message' ) )
			) );
			$this->update_setting( 'checkout_page', $id );
			$url = get_permalink( $id );
			$this->update_setting( 'checkout_url', $url );

			//Delete the existing page
			if( !empty( $page ) ) {
			    wp_delete_post( $page->ID, true );
			}
		} else {
			$url = get_permalink( $this->get_setting( 'checkout_page' ) );
			$this->update_setting( 'checkout_url', $url );
		}
		restore_current_blog();

		return $url;
	}

	function checkout_page_load( $query ) {

		$x = '';

		//allow overriding and changing the root site to put the checkout page on
		$checkout_site = defined( 'PSTS_CHECKOUT_SITE' ) ? constant( 'PSTS_CHECKOUT_SITE' ) : '';

		//don't check on other blogs, unless checkout site is set
		if ( ! is_main_site() && empty( $checkout_site ) ) {
			return;
		}
		//If checkout site is not empty, and current blog is not checkout blog
		if( !empty( $checkout_site ) && $checkout_site != get_current_blog_id() ) {
			return;
		}

		//prevent weird redo when theme has multiple query loops
		if ( $this->checkout_processed ) {
			return;
		}

		//Get the id of the current item
		$queried_object_id = 0;
		if( !empty( $query->queried_object_id ) ) {
		    $queried_object_id = intval( $query->queried_object_id );
		}elseif( $page_id = $query->get('page_id') ) {
		    //Check if page id is set
		    $queried_object_id = intval( $page_id );
		}

		//check if on checkout page or exit
		if ( ! $this->get_setting( 'checkout_page' ) || $queried_object_id != $this->get_setting( 'checkout_page' ) ) {

			return;
		}

		//force ssl on the checkout page if required by gateway force if admin is forced (because user will be logged in)
		if ( ( apply_filters( 'psts_force_ssl', false ) && ! is_ssl() ) || ( force_ssl_admin() && ! is_ssl() ) ) {
			wp_redirect( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
			exit();
		}

		//passed all checks, flip one time flag
		$this->checkout_processed = true;

		//remove all filters except shortcodes and checkout form
		remove_all_filters( 'the_content' );
		add_filter( 'the_content', 'do_shortcode' );

		/**
		 * Responsible for checkout page
		 */
		add_filter( 'the_content', array( &$this, 'checkout_output' ), 15 );
		/**
		 * @todo: come back to this one
		 */
		do_action( 'psts_checkout_page_load'); //for gateway plugins to hook into

		wp_enqueue_script( 'psts-checkout', $this->plugin_url . 'js/checkout.js', array( 'jquery' ), $this->version );
		wp_enqueue_script( 'jquery-ui-tabs' );

		wp_localize_script( 'psts-checkout', 'prosites_checkout', array(
			'ajax_url' => ProSites_Helper_ProSite::admin_ajax_url(),
			'confirm_cancel' => __( "Please note that if you cancel your subscription you will not be immune to future price increases. The price of un-canceled subscriptions will never go up!\n\nAre you sure you really want to cancel your subscription?\nThis action cannot be undone!", 'psts'),
			'button_signup' => __( "Sign Up", 'psts' ),
			'button_choose' => __( "Choose Plan", 'psts' ),
			'button_chosen' => __( "Chosen Plan", 'psts' ),
			'logged_in' => is_user_logged_in(),
			'new_blog'  => ProSites_Helper_ProSite::allow_new_blog() ? 'true' : 'false'
		) );

		if ( ! current_theme_supports( 'psts_style' ) ) {
			wp_enqueue_style( 'psts-checkout', $this->plugin_url . 'css/checkout.css', false, $this->version );
			wp_enqueue_style( 'dashicons' ); // in case it hasn't been loaded yet

			/* Checkout layout */
			$layout_option = $this->get_setting( 'pricing_table_layout', 'option1' );
			$checkout_layout = apply_filters( 'prosites_checkout_css', $this->plugin_url . 'css/pricing-tables/' . $layout_option . '.css' );
			wp_enqueue_style( 'psts-checkout-layout', $checkout_layout, false, $this->version );

			/* Apply styles from options */
			$checkout_style = ProSites_View_Pricing_Styling::get_styles_from_options();
			if( ! empty( $checkout_style ) ) {
				wp_add_inline_style( 'psts-checkout-layout', $checkout_style );
			};

		}
		if ( $this->get_setting( 'plans_table_enabled' ) || $this->get_setting( 'comparison_table_enabled' ) ) {
			wp_enqueue_style( 'psts-plans-pricing', $this->plugin_url . 'css/plans-pricing.css', false, $this->version );
		}

		//setup error var
		$this->errors = new WP_Error();

		//set blog_id
		$blog_id = false;
		$domain  = false;

		if ( isset( $_POST['bid'] ) ) {
			$blog_id = intval( $_POST['bid'] );
		} else if ( isset( $_GET['bid'] ) ) {
			$blog_id = intval( $_GET['bid'] );
		}

		// Set domain if in session
		$domain = ProSites_Helper_Session::session( 'domain' );

		if ( $blog_id || $domain ) {

			add_filter( 'the_title', array( &$this, 'page_title_output' ), 99, 2 );
			add_filter( 'bp_page_title', array( &$this, 'page_title_output' ), 99, 2 );

			$use_pricing_table = $this->get_setting( 'comparison_table_enabled' ) ? $this->get_setting( 'comparison_table_enabled' ) : $this->get_setting( 'co_pricing' );
			if ( $use_pricing_table === "enabled" ) {
				add_filter( 'psts_checkout_screen_before_grid', array( &$this, 'checkout_trial_msg' ), 10, 2 );
			}

			//clear coupon if link clicked
			if ( isset( $_GET['remove_coupon'] ) ) {
				ProSites_Helper_Session::unset_session( 'COUPON_CODE' );
			}

			//check for coupon session variable
			if ( $session_coupon = ProSites_Helper_Session::session( 'COUPON_CODE' ) ) {
				if ( !empty( $_POST ) && $this->check_coupon( $session_coupon, $blog_id, intval( @$_POST['level'] ), $_POST['period'], '' ) ) {
					$coupon = true;
				} else {
					if ( isset( $_POST['level'] ) && is_numeric( $_POST['level'] ) ) {
						$this->errors->add( 'coupon', __( 'Sorry, the coupon code you entered is not valid for your chosen level.', 'psts' ) );
					} else {
						$this->errors->add( 'coupon', __( 'Whoops! The coupon code you entered is not valid.', 'psts' ) );
						ProSites_Helper_Session::unset_session( 'COUPON_CODE' );
					}
				}
			}

			if ( isset( $_POST['coupon-submit'] ) ) {
				$code   = preg_replace( '/[^A-Z0-9_-]/', '', strtoupper( $_POST['coupon_code'] ) );
				$coupon = $this->check_coupon( $code, $blog_id );
				if ( $coupon ) {
					ProSites_Helper_Session::session( 'COUPON_CODE', $code );
					$this->log_action( $blog_id, __( "User added a valid coupon to their order on the checkout page:", 'psts' ) . ' ' . $code, $domain );
				} else {
					$this->errors->add( 'coupon', __( 'Whoops! The coupon code you entered is not valid.', 'psts' ) );
					$this->log_action( $blog_id, __( "User attempted to add an invalid coupon to their order on the checkout page:", 'psts' ) . ' ' . $code, $domain );
				}
			}
		} else {
			//code for unique coupon links
			if ( isset( $_GET['coupon'] ) ) {
				$code = preg_replace( '/[^A-Z0-9_-]/', '', strtoupper( $_GET['coupon'] ) );
				if ( $this->check_coupon( $code ) ) {
					ProSites_Helper_Session::session( 'COUPON_CODE', $code );
				}
			}
		}
	}

	/**
    * Check if a blog is expired
    *
	* @return bool
    */
	function check() {

		global $blog_id, $wpdb;

		if ( is_pro_site( $blog_id ) ) {
			do_action( 'psts_active' );
		} else if ( $wpdb->result ) { //only trigger withdrawls if it wasn't a db error
			do_action( 'psts_inactive' );

			$current_expire = $this->get_expire( $blog_id );

			/*
			 * Add 2 hour buffer before expiring allowing webhooks to process ( 1hr = 3600 )
			 * Can override in wp-config by setting PSTS_EXPIRATION_BUFFER
			 * 1 hr = 3600 seconds
			 * 1 day = 86400 seconds
			 */
			$expiration_buffer = defined( 'PSTS_EXPIRATION_BUFFER' ) ? (int) PSTS_EXPIRATION_BUFFER : 86400;

			//Confirm the expiry from subscription
			if( $current_expire <= time() ) {
				//Try to fetch subscription details from Gateway first
				$expiry = $this->get_subscription_details( $blog_id );

				//If the blog is not expired, return
				if( !empty( $expiry ) && $expiry >= time() ) {
					//Update the Expiry of the blog
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->base_prefix}pro_sites SET expire = %s WHERE blog_ID = %d", $expiry, $blog_id ) );
					return true;
				}
			}

			// Check current expiration, if its '9999999999' then its indefinite, else calculate
			if( '9999999999' == $current_expire || ( ( (int) $current_expire + $expiration_buffer ) < time() ) ) {

				//fire hooks on first encounter
				if ( get_blog_option( $blog_id, 'psts_withdrawn' ) === '0' ) {
					$this->withdraw( $blog_id );

					//send email
					if ( ! defined( 'PSTS_NO_EXPIRE_EMAIL' ) && '9999999999' != $this->get_expire( $blog_id ) ) {
						$this->email_notification( $blog_id, 'expired' );
					}
				}
			}
		}
	}

	/**
	 * Check if given blog has been canceled
	 *
	 * @since 3.4.3.7
	 *
	 * @param int $blog_id
	 *
	 * @return bool
	 */
	function is_blog_canceled( $blog_id ) {
		global $wpdb;

		//If we don't have a blog id, bailout
		if( empty( $blog_id ) ) {
			return;
		}

		//Check the option table for cancellation
		if ( get_blog_option( $blog_id, 'psts_is_canceled' ) || get_blog_option( $blog_id, 'psts_stripe_canceled' ) ) {
			return true;
		}

		//check if blog has been canceled in the stat log (other gateways, manual cancel, etc)
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(action_ID) FROM {$wpdb->prefix}pro_sites_signup_stats WHERE blog_ID = %d AND action = 'cancel'", $blog_id ) );

		if ( $count > 0 ) {
			update_blog_option( $blog_id, 'psts_is_canceled', 1 );

			return true;
		}

		return false;
	}


	//sends email notification to the user
	function email_notification( $blog_id, $action, $email = false, $args = array() ) {
		global $wpdb;

		if ( ! $email ) {
			$email = get_blog_option( $blog_id, 'admin_email' );
		}

		// used in all emails
		$search_replace = array(
			'LEVEL'       => $this->get_level_setting( $this->get_level( $blog_id ), 'name' ),
			'SITEURL'     => get_home_url( $blog_id ),
			'SITENAME'    => get_blog_option( $blog_id, 'blogname' ),
			'CHECKOUTURL' => $this->checkout_url( $blog_id )
		);
		// send emails as html (fixes some formatting issues with currencies)
		$mail_headers = array( 'Content-Type: text/html' );
		
		add_action('phpmailer_init', 'psts_text_body' );
		
		switch ( $action ) {
			case 'success':
				$e = apply_filters( 'psts_email_success_fields', array(
					'msg'     => $this->get_setting( 'success_msg' ),
					'subject' => $this->get_setting( 'success_subject' )
				) );

				$e = str_replace( array_keys( $search_replace ), $search_replace, $e );
				wp_mail( $email, $e['subject'], nl2br( $e['msg'] ), implode( "\r\n", $mail_headers ) );

				$this->log_action( $blog_id, sprintf( __( 'Signup success email sent to %s', 'psts' ), $email ) );
				break;

			case 'receipt':

				// NOTE: Stripe no longer uses this

				//grab default payment info
				$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id ) );
				if ( $result->term == 1 || $result->term == 3 || $result->term == 12 ) {
					$term = sprintf( __( 'Every %s Month(s)', 'psts' ), $result->term );
				} else {
					$term = $result->term;
				}
				$level = $result->level;

				$payment_info = '';

				// Get current plan
				$level_list = get_site_option( 'psts_levels' );
				$level_name = $level_list[ $level ]['name'];
				$payment_info .= sprintf( __( 'Current Plan: %s', 'psts' ), $level_name ) . "\n\n";

				if ( $result->gateway ) {
					$nicename = ProSites_Helper_Gateway::get_nice_name( $result->gateway );
					$payment_info .= sprintf( __( 'Payment Method: %s', 'psts' ), $nicename ) . "\n";
				}

				if ( $term ) {
					$payment_info .= sprintf( __( 'Payment Term: %s', 'psts' ), $term ) . "\n";
				}

				$trialing = ProSites_Helper_Registration::is_trial( $blog_id );
				$amount = $trialing ? 0.0 : $result->amount;
				$payment_info .= sprintf( __( 'Payment Amount: %s', 'psts' ), $this->format_currency( false, $amount ) . ' ' . $this->get_setting( 'currency' ) ) . "\n";

				if( ! empty( $args ) ) {

					if( isset( $args['items'] ) ) {
						$items = $args['items'];
						$items_total = ProSites_Model_Receipt::get_items_total( $items );
						foreach( $items as $item ) {
							$symbol = $item['amount'] > 0 ? '' : '-';
							$payment_info .= sprintf( '%s: %s%s', $item['description'], $symbol, $this->format_currency( false, abs( $item['amount'] ) ) . ' ' . $this->get_setting( 'currency' ) ) . "\n";
						}
						$payment_info .= '<hr />';
						$items_total = $items_total > 0 ? $items_total : 0;
						$symbol = $items_total >= 0 ? '' : '-';
						$payment_info .= sprintf( __( 'Total Paid: %s%s', 'psts' ), $symbol, $this->format_currency( false, $items_total ) . ' ' . $this->get_setting( 'currency' ) ) . "\n";
					} else {
						/**
						 * @todo Remove prior to release
						 */
						if( isset( $args['setup_amount'] ) ) {
							$payment_info .= sprintf( __( 'One-Time Setup Fee: %s', 'psts' ), $this->format_currency( false, $args['setup_amount'] ) . ' ' . $this->get_setting( 'currency' ) ) . "\n";
						} else {
							$args['setup_amount'] = 0;
						}
						if( isset( $args['discount_amount'] ) ) {
							$payment_info .= sprintf( __( 'Discount: -%s', 'psts' ), $this->format_currency( false, abs( $args['discount_amount'] ) ) . ' ' . $this->get_setting( 'currency' ) ) . "\n";
						} else {
							$args['discount_amount'] = 0;
						}

						$zero_cost_change = false;
						if( isset( $args['plan_change_amount'] ) ) {
							switch( $args['plan_change_mode'] ) {
								case 'upgrade':
									$payment_info .= sprintf( __( 'Plan Modified: %s', 'psts' ), $this->format_currency( false, abs( $args['plan_change_amount'] ) ) . ' ' . $this->get_setting( 'currency' ) ) . "\n";
									break;
								case 'downgrade':
									$payment_info .= sprintf( __( 'Plan Modified: -%s', 'psts' ), $this->format_currency( false, abs( $args['plan_change_amount'] ) ) . ' ' . $this->get_setting( 'currency' ) ) . "\n";
									break;
							}
							$zero_cost_change = 0 > ( $amount + $args['setup_amount'] + $args['plan_change_amount'] - $args['discount_amount'] );
						} else {
							$args['plan_change_amount'] = 0;
						}
						$payment_info .= sprintf( '<hr />' );
						if( $zero_cost_change ) {
							// No cost to upgrade
							$payment_info .= sprintf( __( 'Total Paid: %s', 'psts' ), $this->format_currency( false,  0 ) . ' ' . $this->get_setting( 'currency' ) ) . "\n";
						} else {
							$payment_info .= sprintf( __( 'Total Paid: %s', 'psts' ), $this->format_currency( false, ( $amount + $args['setup_amount'] + $args['plan_change_amount'] - $args['discount_amount'] ) ) . ' ' . $this->get_setting( 'currency' ) ) . "\n";
						}

					}
				}

				if ( $result->gateway == 'trial' || ! empty( $trialing ) ) {
					$trial_info = "\n" . __( '*** PLEASE NOTE ***', 'psts' ) . "\n";
					$trial_info .= sprintf( __( 'You will not be charged for your subscription until your trial ends on %s. If applicable, this does not apply to setup fees and other upfront costs.', 'psts' ), date_i18n( get_option( 'date_format' ), $result->expire ) );
					$payment_info .= apply_filters( 'psts_trial_info', $trial_info, $blog_id );
				}

				$search_replace['PAYMENTINFO'] = apply_filters( 'psts_payment_info', $payment_info, $blog_id );

				$e = apply_filters( 'psts_email_receipt_fields', array(
					'msg'     => $this->get_setting( 'receipt_msg' ),
					'subject' => $this->get_setting( 'receipt_subject' )
				) );
				$e = str_replace( array_keys( $search_replace ), $search_replace, $e );

				ob_start();
				wp_mail( $email, $e['subject'], nl2br( $e['msg'] ), implode( "\r\n", $mail_headers ), $this->pdf_receipt( $e['msg'] ) );
				ob_end_clean();

				$this->log_action( $blog_id, sprintf( __( 'Payment receipt email sent to %s', 'psts' ), $email ) );
				break;

			case 'canceled':
				//get end date from expiration
				$end_date = date_i18n( get_blog_option( $blog_id, 'date_format' ), $this->get_expire( $blog_id ) );

				$search_replace['ENDDATE'] = $end_date;
				$e                         = apply_filters( 'psts_email_cancelled_fields', array(
					'msg'     => $this->get_setting( 'canceled_msg' ),
					'subject' => $this->get_setting( 'canceled_subject' )
				) );

				$e = str_replace( array_keys( $search_replace ), $search_replace, $e );
				wp_mail( $email, $e['subject'], nl2br( $e['msg'] ), implode( "\r\n", $mail_headers ) );

				$this->log_action( $blog_id, sprintf( __( 'Subscription canceled email sent to %s', 'psts' ), $email ) );
				break;

			case 'expired':
				$e = apply_filters( 'psts_email_expired_fields', array(
					'msg'     => $this->get_setting( 'expired_msg' ),
					'subject' => $this->get_setting( 'expired_subject' )
				) );

				$e = str_replace( array_keys( $search_replace ), $search_replace, $e );
				wp_mail( $email, $e['subject'], nl2br( $e['msg'] ), implode( "\r\n", $mail_headers ) );

				$this->log_action( $blog_id, sprintf( __( 'Expired email sent to %s', 'psts' ), $email ) );
				break;

			case 'failed':
				$e = apply_filters( 'psts_email_payment_failed_fields',array(
					'msg'     => $this->get_setting( 'failed_msg' ),
					'subject' => $this->get_setting( 'failed_subject' )
				) );

				$e = str_replace( array_keys( $search_replace ), $search_replace, $e );
				wp_mail( $email, $e['subject'], nl2br( $e['msg'] ), implode( "\r\n", $mail_headers ) );

				$this->log_action( $blog_id, sprintf( __( 'Payment failed email sent to %s', 'psts' ), $email ) );
				break;
			case 'extension':
				//get end date from expiration
				$end_date = date_i18n( get_blog_option( $blog_id, 'date_format' ), $this->get_expire( $blog_id ) );

				if( ! empty( $args ) && isset( $args['indefinite'] ) ) {
					$end_date = __( 'Indefinitely', 'psts' );
				}

				$search_replace['ENDDATE'] = $end_date;
				$e                         = apply_filters( 'psts_email_extension_fields', array(
					'msg'     => $this->get_setting( 'extension_msg' ),
					'subject' => $this->get_setting( 'extension_subject' )
				) );

				$e = str_replace( array_keys( $search_replace ), $search_replace, $e );
				wp_mail( $email, $e['subject'], nl2br( $e['msg'] ), implode( "\r\n", $mail_headers ) );

				$this->log_action( $blog_id, sprintf( __( 'Manual extension email sent to %s', 'psts' ), $email ) );
				break;
			case 'permanent_revoked':
				//get end date from expiration
				$end_date = date_i18n( get_blog_option( $blog_id, 'date_format' ), $this->get_expire( $blog_id ) );

				$search_replace['ENDDATE'] = $end_date;
				$defaults = ProSites::get_default_settings_array();
				$e                         = apply_filters( 'psts_email_revoked_fields', array(
					'msg'     => $this->get_setting( 'revoked_msg', $defaults['revoked_msg'] ),
					'subject' => $this->get_setting( 'revoked_subject', $defaults['revoked_subject'] )
				) );

				$e = str_replace( array_keys( $search_replace ), $search_replace, $e );
				wp_mail( $email, $e['subject'], nl2br( $e['msg'] ), implode( "\r\n", $mail_headers ) );

				$this->log_action( $blog_id, sprintf( __( 'Permanent status revoked email sent to %s', 'psts' ), $email ) );
				break;
		}
		remove_action('phpmailer_init', 'psts_text_body');
	}

	/**
    * Send a Receipt for the transaction
    *
	* @param $transaction Transaction Object
    *
	* @return bool True/False If the email was sent or not
    *
    */
	public static function send_receipt( $transaction ) {
		global $psts, $wpdb;

		//Don't send receipt if there is no blog id or level set for the transaction
		if( empty( $transaction->blog_id ) || empty( $transaction->level ) ) {
			return false;
		}

		// used in all emails
		$search_replace = array(
			'LEVEL'       => $psts->get_level_setting( $psts->get_level( $transaction->blog_id ), 'name' ),
			'SITEURL'     => get_home_url( $transaction->blog_id ),
			'SITENAME'    => get_blog_option( $transaction->blog_id, 'blogname' ),
			'CHECKOUTURL' => $psts->checkout_url( $transaction->blog_id )
		);
		// send emails as html (fixes some formatting issues with currencies)
		$mail_headers = array( 'Content-Type: text/html' );

		// Get the user
		if ( !empty( $transaction->username ) ) {
			$user = get_user_by( 'login', $transaction->username );
			$email = !empty( $user ) ? $user->user_email : '';
		} elseif ( !empty( $transaction->email ) ) {
			$user = get_user_by( 'email', $transaction->email );
			$email = $transaction->email;
		}

		//Get admin email for the blog id
		if ( ! $user || empty( $email ) ) {
			$email = get_blog_option( $transaction->blog_id, 'admin_email' );
		}

		// Get current plan
		$level_list = get_site_option( 'psts_levels' );
		$level_name = !empty($transaction->level ) && !empty( $level_list[ $transaction->level ] ) ? $level_list[ $transaction->level ]['name'] : '';
		if( empty( $level_name ) ) {
			$level = $psts->get_level( $transaction->blog_id );
		}

		//If we have level and level name is empty
		if( !empty( $level ) && empty( $level_name ) ) {
			$level_name = $level_list[$level]['name'];
		}

		$gateway    = ProSites_Helper_Gateway::get_nice_name_from_class( $transaction->gateway );
		$result     = $wpdb->get_row( $wpdb->prepare( "SELECT term FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $transaction->blog_id ) );

		$term       = !empty( $result->term ) ? $result->term : false;

		if ( $term == 1 || $term == 3 || $term == 12 ) {
			$term = sprintf( __( 'Every %s Month(s)', 'psts' ), $result->term );
		} else {
			$term = false;
		}

		$payment_info = sprintf( __( 'Current Plan: %s', 'psts' ), $level_name ) . "\n";
		$payment_info .= sprintf( __( 'Payment Method: %s', 'psts' ), $gateway ) . "\n";
		if ( $term ) {
			$payment_info .= sprintf( __( 'Payment Term: %s', 'psts' ), $term ) . "\n<hr />";
		}
		$payment_info .= sprintf( __( 'Transaction/Invoice #: %s', 'psts' ), $transaction->invoice_number) . "\n";
		$payment_info .= sprintf( __( 'Transaction/Invoice Date: %s', 'psts' ), $transaction->invoice_date) . "\n\n";
		$payment_info .= '<strong>' . __( 'Transaction Details:', 'psts' ) . "</strong>\n\n";

		foreach( $transaction->transaction_lines as $line ) {
			$payment_info .= esc_html( $line->description  ) . '&nbsp;&nbsp;';
			$payment_info .= esc_html( $psts->format_currency( $transaction->currency_code, $line->amount )  ) . "&nbsp;&nbsp;\n";
		}

		$tax_rate = isset( $transaction->tax_percent ) ? $transaction->tax_percent : 0;
		$total = isset( $transaction->total ) ? $transaction->total : false;
		$subtotal = isset( $transaction->subtotal ) ? $transaction->subtotal : false;
		$tax = isset( $transaction->tax ) ? $transaction->tax : false;

		if( false === $total && $subtotal ) {
			$total = ( $subtotal * $tax_rate ) + $subtotal;
		}
		if( false === $subtotal && $total ) {
			$subtotal = $total / ( $tax_rate + 1 );
		}
		if( false === $tax && $total && $subtotal ) {
			$tax = $total - $subtotal;
		}

		if ( empty( $tax_rate ) ) {
			$payment_info .= "\n<strong>" . sprintf( __( 'Total: %s', 'psts' ), $psts->format_currency( $transaction->currency_code, $total ) ) . "</strong>\n\n";
		} else {
			$payment_info .= "\n" . sprintf( __( 'Sub-Total: %s', 'psts' ), $psts->format_currency( $transaction->currency_code, $subtotal ) ) . "\n";
			$payment_info .= sprintf( __( 'Tax Rate: %s%%', 'psts' ), ($tax_rate * 100) ) . "\n";
			$payment_info .= sprintf( __( 'Tax Amount: %s', 'psts' ), $psts->format_currency( $transaction->currency_code, $tax ) ) . "\n";
			$payment_info .= '<strong>' . sprintf( __( 'Total: %s', 'psts' ), $psts->format_currency( $transaction->currency_code, $total ) ) . "</strong>\n\n";
		}

		$payment_info .= '<hr />';

		$search_replace['PAYMENTINFO'] = apply_filters( 'psts_payment_info', $payment_info, $transaction->blog_id );

		$e = array(
			'msg'     => $psts->get_setting( 'receipt_msg' ),
			'subject' => $psts->get_setting( 'receipt_subject' )
		);
		$e = str_replace( array_keys( $search_replace ), $search_replace, $e );
		$pdf_receipt = '';
		if( $psts->get_setting( 'send_receipts', 1 ) ) {
			$pdf_receipt = $psts->pdf_receipt( $e['msg'] );
		}
		//It is converting Euro symbol to emoji, need to submit a trac ticket, until then remove emoji in email
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		wp_mail( $email, $e['subject'], nl2br( $e['msg'] ), implode( "\r\n", $mail_headers ), $pdf_receipt );
		add_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		$psts->log_action( $transaction->blog_id, sprintf( __( 'Payment receipt email sent to %s', 'psts' ), $email ) );

		return true;
	}


	/**
	 * @todo: Rework this into a model
	 */
	public function send_extension_email( $blog_id, $new_expire, $level, $manual_notify, $gateway, $last_gateway, $extra ) {

		if( $manual_notify ) {
			$args = array();
			if ( '9999999999' == $new_expire ) {
				$args['indefinite'] = true;
			}

			if ( ! defined( 'PSTS_NO_EXTENSION_EMAIL' ) ) {

				if( isset( $extra['permanent_revoked'] ) && true === $extra['permanent_revoked'] ) {
					$this->email_notification( $blog_id, 'permanent_revoked', false, $args );
					unset( $args['indefinite'] );
				} else {
					$this->email_notification( $blog_id, 'extension', false, $args );
				}

			}
		}
	}

	/**
	 * Log all the actions for blog or domain
	 *
	 * @param $blog_id
	 * @param $note
	 * @param string $domain , Optional
	 */
	function log_action( $blog_id, $note, $domain = '' ) {

		if ( empty( $blog_id ) && empty ( $domain ) ) {
			return false;
		}
		//append
		$timestamp = microtime( true );

		//make sure timestamp is unique by padding seconds, or they will be overwritten
		while ( isset( $log[ $timestamp ] ) ) {
			$timestamp += 0.0001;
		}

		if ( ! empty( $blog_id ) ) {
			//grab data
			$log = get_blog_option( $blog_id, 'psts_action_log' );

			if ( ! is_array( $log ) ) {
				$log = array();
			}

			$log[ $timestamp ] = $note;

			//save
			update_blog_option( $blog_id, 'psts_action_log', $log );
		} else {

			$signup_meta                                  = $this->get_signup_meta( $domain );
			$signup_meta['psts_action_log'][ $timestamp ] = $note;

			//Update signup meta
			$this->update_signup_meta( $signup_meta, $domain );
		}
	}

	//record last payment
	function record_transaction( $blog_id, $txn_id, $amt ) {
		$trans_meta = get_blog_option( $blog_id, 'psts_payments_log' );

		$trans_meta[ $txn_id ]['txn_id']    = $txn_id;
		$trans_meta[ $txn_id ]['timestamp'] = time();
		$trans_meta[ $txn_id ]['amount']    = $amt;
		$trans_meta[ $txn_id ]['refunded']  = false;
		update_blog_option( $blog_id, 'psts_payments_log', $trans_meta );
	}

	//record payment refund
	function record_refund_transaction( $blog_id, $txn_id, $refunded, $domain = false ) {
		$trans_meta = get_blog_option( $blog_id, 'psts_payments_log' );

		if ( isset( $trans_meta[ $txn_id ] ) ) {
			//add to previous refund if there was one
			if ( $trans_meta[ $txn_id ]['refunded'] ) {
				$refunded = $refunded + $trans_meta[ $txn_id ]['refunded'];
			}

			$trans_meta[ $txn_id ]['refunded'] = $refunded;
			update_blog_option( $blog_id, 'psts_payments_log', $trans_meta );
		}
	}

	//get last transaction details
	function last_transaction( $blog_id, $domain = false ) {
		if ( ! $blog_id && ! $domain ) {
			return false;
		}
		$trans_meta = '';
		if ( $blog_id ) {
			$trans_meta = get_blog_option( $blog_id, 'psts_payments_log' );
		} else {
			$signup_meta = $this->get_signup_meta( $domain );
			$trans_meta  = isset( $signup_meta['psts_payment_log'] ) ? $signup_meta['psts_payment_log'] : '';
		}

		if ( is_array( $trans_meta ) ) {
			return array_pop( $trans_meta );
		} else {
			return false;
		}
	}

	function is_pro_site( $blog_id = false, $level = false ) {
		global $wpdb, $current_site;

		if ( empty( $blog_id ) && is_user_logged_in() ) {
			$blog_id = $wpdb->blogid;
		}

		$blog_id = (int) $blog_id;

		if( empty( $blog_id ) ) {
			return false;
		}
		// Allow plugins to short-circuit
		$pro = apply_filters( 'is_pro_site', null, $blog_id );
		if ( ! is_null( $pro ) ) {
			return $pro;
		}

		//check cache first
		if ( $level ) { //level is passed, check level
			if ( $level == 0 ) {
				return true;
			} else if ( isset( $this->pro_sites[ $blog_id ][ $level ] ) && is_bool( $this->pro_sites[ $blog_id ][ $level ] ) ) { //check local cache
				return $this->pro_sites[ $blog_id ][ $level ];
			} else if ( $pro_site = wp_cache_get( 'is_pro_site_' . $blog_id, 'psts' ) ) { //check object cache
				if ( isset( $pro_site[ $level ] ) && is_bool( $pro_site[ $level ] ) ) {
					return $pro_site[ $level ];
				}
			}
		} else { //any level will do
			if ( isset( $this->pro_sites[ $blog_id ] ) && is_array( $this->pro_sites[ $blog_id ] ) ) { //check local cache for any level
				foreach ( $this->pro_sites[ $blog_id ] as $key => $value ) {
					if ( $value ) {
						return true;
					}
				}
			} else if ( $pro_site = wp_cache_get( 'is_pro_site_' . $blog_id, 'psts' ) ) { //check object cache
				if ( is_array( $pro_site ) ) {
					foreach ( $pro_site as $key => $value ) {
						if ( $value ) {
							return true;
						}
					}
				}
			}
		}

		//check if main site
		if ( is_main_site( $blog_id ) ) {
			return true;
		} else { //finally go to DB
			$now  = time();
			$data = $wpdb->get_row( $wpdb->prepare( "SELECT expire, level FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id ) );
			if ( is_object( $data ) ) {
				if ( $level ) {
					if ( $data->expire && $data->expire > $now && $level <= $data->level ) {
						for ( $i = 1; $i <= $data->level; $i ++ ) {
							$this->pro_sites[ $blog_id ][ $i ] = true;
						} //update local cache

						wp_cache_set( 'is_pro_site_' . $blog_id, $this->pro_sites[ $blog_id ], 'psts' ); //set object cache
						return true;
					} else {
						$levels = ( array ) get_site_option( 'psts_levels' );
						for ( $i = $level; $i <= count( $levels ); $i ++ ) {
							$this->pro_sites[ $blog_id ][ $i ] = false;
						} //update local cache

						wp_cache_set( 'is_pro_site_' . $blog_id, $this->pro_sites[ $blog_id ], 'psts' ); //set object cache
						return false;
					}
				} else { //any level will do
					if ( $data->expire && $data->expire > $now ) {
						for ( $i = 1; $i <= $data->level; $i ++ ) {
							$this->pro_sites[ $blog_id ][ $i ] = true;
						} //update local cache

						wp_cache_set( 'is_pro_site_' . $blog_id, $this->pro_sites[ $blog_id ], 'psts' ); //set object cache
						return true;
					} else {
						for ( $i = 1; $i <= $data->level; $i ++ ) {
							$this->pro_sites[ $blog_id ][ $i ] = false;
						} //update local cache

						wp_cache_set( 'is_pro_site_' . $blog_id, $this->pro_sites[ $blog_id ], 'psts' ); //set object cache
						return false;
					}
				}
			} else {
				if ( $wpdb->result ) { //only cache if there was not a db error
					if ( ! $level ) {
						$level = 5;
					} //if level false give an arbitrary level count
					for ( $i = 1; $i <= $level; $i ++ ) {
						$this->pro_sites[ $blog_id ][ $i ] = false;
					} //update local cache

					wp_cache_set( 'is_pro_site_' . $blog_id, $this->pro_sites[ $blog_id ], 'psts' ); //set object cache
				}

				return false;
			}
		}
	}

	function is_blog_recurring( $blog_id ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "
			SELECT is_recurring
			FROM {$wpdb->base_prefix}pro_sites
			WHERE blog_ID = %d", $blog_id
		) );
	}

	/*
	Useful in plugins to test users. Checks if any of the blogs they are a member of
	are supporter blogs, which works but is resource intensive and a bit wacky at best,
	because a supporter blog may have a thousand users, and they would all be "pro_sites".
	*/
	function is_pro_user( $user_id = false ) {
		global $wpdb, $current_user, $current_site;

		if ( ! $user_id ) {
			$user_id = $current_user->ID;
		}
		$user_id = intval( $user_id );

		if ( is_super_admin( $user_id ) ) {
			return true;
		}

		//very db intensive, so we cache (1 hour)
		$expire_time = time() - 3600;
		@list( $expire, $is_pro ) = get_user_meta( $user_id, 'psts_user', true );
		if ( $expire && $expire >= $expire_time ) {
			return $is_pro;
		}

		//TODO - add option to select which user levels from supporter blog will be supporter user. Right now it's all (>= Subscriber)
		//$results = $wpdb->get_results("SELECT * FROM `$wpdb->usermeta` WHERE `user_id` = $user_id AND `meta_key` LIKE 'wp_%_capabilities' AND `meta_value` LIKE '%administrator%'");
		$results = $wpdb->get_results( "SELECT * FROM `$wpdb->usermeta` WHERE `user_id` = $user_id AND `meta_key` LIKE '{$wpdb->base_prefix}%_capabilities'" );
		if ( ! $results ) {
			//update cache
			update_user_meta( $user_id, 'psts_user', array( time(), 0 ) );

			return false;
		}

		foreach ( $results as $row ) {
			$tmp = explode( '_', $row->meta_key );
			//skip main blog
			if ( $tmp[1] != $current_site->blogid ) {
				$blog_ids[] = intval( $tmp[1] );
			}
		}
		$blog_ids = implode( ',', $blog_ids );

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE expire > '" . time() . "' AND blog_ID IN ($blog_ids)" );
		if ( $count ) {
			update_user_meta( $user_id, 'psts_user', array( time(), 1 ) ); //update cache
			return true;
		} else {
			//update cache
			update_user_meta( $user_id, 'psts_user', array( time(), 0 ) ); //update cache
			return false;
		}
	}

	//returns the level if blog is paid up
	function get_level( $blog_id = '' ) {
		global $wpdb;

		if ( empty( $blog_id ) ) {
			$blog_id = $wpdb->blogid;
		}

		//check cache
		if ( isset( $this->level ) && isset( $this->level[ $blog_id ] ) ) {
			return $this->level[ $blog_id ];
		} else if ( false !== ( $level = wp_cache_get( 'level_' . $blog_id, 'psts' ) ) ) //try local cache (could be 0)
		{
			return $level;
		}

		if ( ! is_pro_site( $blog_id ) ) {
			return 0;
		}

		$sql = $wpdb->prepare( "SELECT level FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id );

		$level = $wpdb->get_var( $sql );
		if ( $level ) {
			$this->level[ $blog_id ] = $level; //update local cache
			wp_cache_set( 'level_' . $blog_id, $level, 'psts' ); //update object cache
			return $level;
		} else {
			unset( $this->level[ $blog_id ] ); //clear local cache
			wp_cache_delete( 'level_' . $blog_id, 'psts' ); //clear object cache
			return 0;
		}
	}

	function get_expire( $blog_id = '' ) {
		global $wpdb;

		if ( empty( $blog_id ) ) {
			$blog_id = $wpdb->blogid;
		}

		$expire = $wpdb->get_var( $wpdb->prepare( "SELECT expire FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id ) );
		if ( $expire ) {
			return $expire;
		} else {
			return false;
		}
	}

	//gateways hook into this and return false with no sub, or a timestamp
	function get_next_payment_date( $blog_id ) {
		return apply_filters( 'psts_next_payment', false );
	}

	/**
	* @param $blog_id
	* @param $extend Period of Subscription
	* @param bool|string $gateway (Manual, Trial, Stripe, Paypal)
	* @param int $level
	* @param bool|false $amount
	* @param bool|false $expires
	* @param bool|true $is_recurring
	* @param bool|false $manual_notify
	* @param string $extend_type
    */
	function extend( $blog_id, $extend, $gateway = false, $level = 1, $amount = false, $expires = false, $is_recurring = true, $manual_notify = false, $extend_type = '' ) {
		global $wpdb, $current_site;
		
		$gateway = ! empty( $gateway ) ? strtolower( $gateway ) : false;
		
		$last_gateway = '';

		$now    = time();
		//	$exists = $this->get_expire( $blog_id ); // not reliable
		$exists = false;
		if ( ! empty( $blog_id ) ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT expire FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id ) );
		}
		$term   = $extend;

		if ( $expires !== false ) {
			// expiration is set (e.g. for trials)
			$new_expire = $expires;
		} else {
			if ( $exists ) {
				$old_expire = $exists;
				if ( $now > $old_expire ) {
					$old_expire = $now;
				}
			} else {
				$old_expire = $now;
			}

			if ( $extend == '1' ) {
				//$extend = 2629744;
				$extend = strtotime( "+1 month" );
				$extend = $extend - time();
			} else if ( $extend == '3' ) {
				//$extend = 7889231;
				$extend = strtotime( "+3 months" );
				$extend = $extend - time();
			} else if ( $extend == '12' ) {
				//$extend = 31556926;
				$extend = strtotime( "+1 year" );
				$extend = $extend - time();
			} else {
				$term = false;
			}

			$new_expire = $old_expire + $extend;
			if ( $extend >= 9999999999 ) {
				$new_expire = 9999999999;
				$term       = __( 'Permanent', 'psts' );
			}
		}
		//Add 1.5 hour extra to handle the delays in subscription renewal by stripe
		$new_expire = $new_expire >= 9999999999 ? $new_expire : $new_expire + 5400;

		// Are we changing a permanent extension back to a normal site?
		$permanent_revoked = false;
		if( $exists && (int) $exists >= 9999999999 && (int) $new_expire < 9999999999 && 'manual' == strtolower( $gateway ) ) {
			$new_expire = $expires;
			$permanent_revoked = true;
		}

		$old_level = $this->get_level( $blog_id );

		$extra_sql = $wpdb->prepare( "expire = %s", $new_expire );
		$extra_sql .= ( $level ) ? $wpdb->prepare( ", level = %d", $level ) : '';
		if ( 'manual' === $gateway && $exists ) {
			$last_gateway = ProSites_Helper_ProSite::last_gateway( $blog_id );
			$last_gateway = ! empty( $last_gateway ) ? strtolower( $last_gateway ) : '';
			//control whether we are upgrading the user or extending trial period
			if ( 'manual' === $extend_type && $last_gateway != 'trial' ){
				$new_gateway = ( $last_gateway == $gateway ) ? 'manual': $last_gateway;			
			} elseif ( 'manual' === $extend_type && 'trial' === $last_gateway ){
				$new_gateway = 'manual';
			} else {
				$new_gateway = 'trial';
			}
			
			$extra_sql .= ", gateway = '" . $new_gateway . "'";
		} else {
			$extra_sql .= ( $gateway ) ? $wpdb->prepare( ", gateway = %s", $gateway ) : '';
		}
		
		$extra_sql .= ( $amount ) ? $wpdb->prepare( ", amount = %s", $amount ) : '';
		$extra_sql .= ( $term ) ? $wpdb->prepare( ", term = %d", $term ) : '';
		$extra_sql .= $wpdb->prepare( ", is_recurring = %d", $is_recurring );

		if ( $exists ) {

			// Get last gateway if exists
			$last_gateway = ProSites_Helper_ProSite::last_gateway( $blog_id );
			$last_gateway = ! empty( $last_gateway ) ? strtolower( $last_gateway ) : '';

			$wpdb->query( $wpdb->prepare( "
	  		UPDATE {$wpdb->base_prefix}pro_sites
	  		SET $extra_sql
	  		WHERE blog_ID = %d",
				$blog_id
			) );
		} else {
			$wpdb->query( $wpdb->prepare( "
		  	INSERT INTO {$wpdb->base_prefix}pro_sites (blog_ID, expire, level, gateway, term, amount, is_recurring)
		  	VALUES (%d, %s, %d, %s, %s, %s, %d)",
				$blog_id, $new_expire, $level, $gateway, $term, $amount, $is_recurring
			) );
		}

		unset( $this->pro_sites[ $blog_id ] ); //clear local cache
		wp_cache_delete( 'is_pro_site_' . $blog_id, 'psts' ); //clear object cache
		unset( $this->level[ $blog_id ] ); //clear cache
		wp_cache_delete( 'level_' . $blog_id, 'psts' ); //clear object cache

		if ( $exists != $new_expire ) { //only log if blog expiration date is not changing
			if ( $new_expire >= 9999999999 ) {
				$this->log_action( $blog_id, __( 'Pro Site status expiration permanently extended.', 'psts' ) );
			} else {
				$this->log_action( $blog_id, sprintf( __( 'Pro Site status expiration extended until %s.', 'psts' ), date_i18n( get_blog_option( $current_site->blog_id, 'date_format' ), $new_expire ) ) );
			}
		}

		$extra = array(
			'permanent_revoked' => $permanent_revoked
		);
		do_action( 'psts_extend', $blog_id, $new_expire, $level, $manual_notify, $gateway, $last_gateway, $extra );

		//fire level change
		if ( intval( $exists ) <= time() ) { //count reactivating account as upgrade
			do_action( 'psts_upgrade', $blog_id, $level, 0 );
		} else {
			if ( $old_level < $level ) {
				$this->log_action( $blog_id, sprintf( __( 'Pro Site level upgraded from "%s" to "%s".', 'psts' ), $this->get_level_setting( $old_level, 'name' ), $this->get_level_setting( $level, 'name' ) ) );
				do_action( 'psts_upgrade', $blog_id, $level, $old_level );
			} else if ( $old_level > $level ) {
				$this->log_action( $blog_id, sprintf( __( 'Pro Site level downgraded from "%s" to "%s".', 'psts' ), $this->get_level_setting( $old_level, 'name' ), $this->get_level_setting( $level, 'name' ) ) );
				do_action( 'psts_downgrade', $blog_id, $level, $old_level );
			}
		}

		// Change trial status
		$trialing = ProSites_Helper_Registration::is_trial( $blog_id );

		if( $trialing && 'trial' != $gateway ) {
			ProSites_Helper_Registration::set_trial( $blog_id, 0 );
		}
		if( 'trial' == $gateway ) {
			ProSites_Helper_Registration::set_trial( $blog_id, 1 );
		}

		if( ! empty( $gateway ) && class_exists( 'ProSites_Gateway_Stripe' ) ) {
			do_action( 'psts_' . $gateway . '_extension', $blog_id );
			// Stripe Fix
			ProSites_Gateway_Stripe::attempt_manual_reactivation( $blog_id );
		}

		//flip flag after action fired
		update_blog_option( $blog_id, 'psts_withdrawn', 0 );

		//force to checkout screen next login
		update_blog_option( $blog_id, 'psts_signed_up', 0 );
	}

	function withdraw( $blog_id, $withdraw = false, $domain = false ) {
		global $wpdb;

		$blog_expire = $this->get_expire( $blog_id );

		if ( $withdraw ) {
			if ( $withdraw == '1' ) {
				$withdraw = 2629744;
			} else if ( $withdraw == '3' ) {
				$withdraw = 7889231;
			} else if ( $withdraw == '12' ) {
				$withdraw = 31556926;
			}
			$new_expire = $blog_expire - $withdraw;
		} else {
			$new_expire = strtotime('-1 day', time() );
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->base_prefix}pro_sites SET expire = %s WHERE blog_ID = %d", $new_expire, $blog_id ) );

		unset( $this->pro_sites[ $blog_id ] ); //clear cache
		wp_cache_delete( 'is_pro_site_' . $blog_id, 'psts' ); //clear object cache

		$this->log_action( $blog_id, __( 'Pro Site status has been withdrawn.', 'psts' ) );

		do_action( 'psts_withdraw', $blog_id, $new_expire );

		//flip flag after action fired
		update_blog_option( $blog_id, 'psts_withdrawn', 1 );
		ProSites_Helper_Registration::set_trial( $blog_id, 0 );

		//force to checkout screen next login
		if ( $new_expire <= time() ) {
			update_blog_option( $blog_id, 'psts_signed_up', 1 );
		}
	}

	function cancel_on_gateway_change( $blog_id, $new_expire, $level, $manual_notify, $gateway, $last_gateway ) {

		if( defined( 'PSTS_CANCEL_ON_GATEWAY_CHANGE' ) && PSTS_CANCEL_ON_GATEWAY_CHANGE === false ) {
			return;
		}

		// Avoid trials and manual extensions from cancelling subscriptions
		$exempted_gateways = array( 'trial', 'manual', 'bulk upgrade' );

		// If previous gateway is not the same, we need to cancel the old subscription if we can.
		if( ! empty( $last_gateway ) && $last_gateway != $gateway && ! in_array( $last_gateway, $exempted_gateways ) && ! in_array( $gateway, $exempted_gateways ) ) {
			$gateways = ProSites_Helper_Gateway::get_gateways();
			if( ! empty( $gateways ) && isset( $gateways[ $last_gateway ] ) && method_exists( $gateways[ $last_gateway ]['class'], 'cancel_subscription' ) ) {
				call_user_func( $gateways[ $last_gateway ]['class'] . '::cancel_subscription', $blog_id );
			}
		}

	}


	/**
	 * checks a coupon code for validity. Return boolean
	 * @todo: remove this after cleaning up
	 */
	function check_coupon( $code, $blog_id = false, $level = false, $period = '', $domain = '' ) {
		return ProSites_Helper_Coupons::check_coupon( $code, $blog_id, $level, $period, $domain );
	}

	/**
	* get coupon value. Returns array(discount, new_total) or false for invalid code
	* @todo: remove this after cleaning up
	*/
	function coupon_value( $code, $total ) {
		return ProSites_Helper_Coupons::coupon_value( $code, $total );
	}

	/**
	 * record coupon use. Returns boolean successful
	 * @todo: remove this after cleaning up
	 */
	function use_coupon( $code, $blog_id, $domain = false ) {
		return ProSites_Helper_Coupons::use_coupon( $code, $blog_id, $domain );
	}

	//display currency symbol
	function format_currency( $currency = '', $amount = false ) {

		if ( ! $currency ) {
			$currency = $this->get_setting( 'currency', 'USD' );
		}

		// get the currency symbol
		$currencies = @ProSites_Model_Data::$currencies;
		if( empty( $currencies) ) {
			$currencies = $this->currencies;
			$symbol = @$currencies[ $currency ][1];
		} else {
			$symbol = @ProSites_Model_Data::$currencies[ $currency ]['symbol'];
		}

		// if many symbols are found, rebuild the full symbol
		$symbols = explode( ',', $symbol );
		if ( is_array( $symbols ) ) {
			$symbol = "";
			foreach ( $symbols as $temp ) {
				$temp = trim( $temp );
				$symbol .= '&#x' . $temp . ';';
			}
		} else {
			$symbol = '&#x' . $symbol . ';';
		}
		$symbol = apply_filters( 'prosite_currency_symbol', $symbol, $currency );

		//check decimal option
		if ( $this->get_setting( 'curr_decimal' ) === '0' ) {
			$decimal_place = 0;
			$zero          = '0';
		} else {
			$decimal_place = 2;
			$zero          = '0.00';
		}

		$symbol_position = $this->get_setting( 'curr_symbol_position', 1 );
		/*
		 * 1 - Left Tight
		 * 2 - Left Space
		 * 3 - Right Tight
		 * 4 - Right Space
		 */
		$symbol_position = apply_filters( 'prosite_currency_symbol_position', $symbol_position, $currency );

		//format currency amount according to preference
		if ( $amount ) {

			if ( $symbol_position ) {
				return $symbol . @number_format_i18n( $amount, $decimal_place );
			} else if ( $symbol_position == 2 ) {
				return $symbol . ' ' . number_format_i18n( $amount, $decimal_place );
			} else if ( $symbol_position == 3 ) {
				return number_format_i18n( $amount, $decimal_place ) . $symbol;
			} else if ( $symbol_position == 4 ) {
				return number_format_i18n( $amount, $decimal_place ) . ' ' . $symbol;
			}

		} else if ( $amount === false ) {
			return $symbol;
		} else {
			if ( $symbol_position ) {
				return $symbol . $zero;
			} else if ( $symbol_position == 2 ) {
				return $symbol . ' ' . $zero;
			} else if ( $symbol_position == 3 ) {
				return $zero . $symbol;
			} else if ( $symbol_position == 4 ) {
				return $zero . ' ' . $symbol;
			}
		}
	}

	/*
	 * This is rather complicated, but essentialy it works like:
	 * Pass it a new amt and period, then it finds the old amt
	 * and period, to calculate how much money is unused from their last payment.
	 * Then it takes that money and applies it to the cost per day of the new plan,
	 * returning the timestamp of the day the first payment of the new plan should take place.
	 */
	function calc_upgrade( $blog_id, $new_amt, $new_level, $new_period ) {
		global $wpdb;

		$old = $wpdb->get_row( $wpdb->prepare( "SELECT expire, level, term, amount FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id ) );
		if ( ! $old ) {
			return false;
		}

		//if level is not being raised not an upgrade
		if ( $new_level <= $old->level ) {
			return false;
		}

		//some complicated math calculating the prorated amt left and applying it to the price of new plan
		$diff     = $old->expire - time();
		$duration = $old->term * 30.4166 * 24 * 60 * 60; //number of seconds in the period
		$left     = $duration - ( $duration - $diff );
		if ( $left <= 0 || empty( $old->amount ) || $old->amount <= 0 ) {
			return false;
		}
		if( $new_amt === 0 ) {
			error_log("Pro Sites: Amount can't be zero");
			return false;
		}
		$prorate_amt   = $duration > 0 ? $old->amount * ( $left / $duration ) : 0; //Avoid Divison by zero
		$new_duration  = $new_period * 30.4166 * 24 * 60 * 60; //number of seconds in the period
		$first_payment = ( $prorate_amt / ( $new_amt / $new_duration ) ) + time(); //return timestamp of first payment date
		$first_payment = intval( round( $first_payment ) );

		return ( $first_payment > time() ) ? $first_payment : false;
	}

	/**
	 * This function will calculate the cost to upgrade/modify to a
	 * different plan mid-billing period
	 */
	function calc_upgrade_cost( $blog_id, $new_level, $new_period, $new_amt ) {
		global $wpdb;

		//If no blog id is set, or value is 0, return the amount
		if ( empty( $blog_id ) || $blog_id === 0 ) {
			return $new_amt;
		}

		$old = $wpdb->get_row( $wpdb->prepare( "SELECT expire, level, term, amount FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id ) );

		if ( ! $old ) {
			return $new_amt;
		}
		if ( $old->expire < time() ) {
			return $new_amt;
		}

		if ( $new_level < $old->level && $new_period < $old->term ) // if customer is downgrading no need to charge them
		{
			return 0;
		}elseif( $new_level == $old->level && $new_period == $old->term ) {
			if( is_pro_trial( $blog_id ) ) {
				//Non Recurring, with trial (Level and Period is assigned)
				return $new_amt;
			}else{
				return 0;
			}
		}

		$diff     = $old->expire - time();
		$duration = $old->term * 30.4166 * 24 * 60 * 60; //number of seconds in the period
		$left     = $duration - ( $duration - $diff ); //number of seconds left in current period

		if ( $left <= 0 || empty( $old->amount ) || $old->amount <= 0 ) {
			return $new_amt;
		}
		$refund_amt = round( $old->amount * ( $left / $duration ), 2 ); //amount to refund
		$refund_amt = $refund_amt > $old->amount ? $old->amount : $refund_amt;

		$new_amt    = $new_amt - $refund_amt;

		return ( $new_amt < 0 ) ? 0 : $new_amt;
	}

	//filters the titles for our custom pages
	function page_title_output( $title, $id = null ) {

		//filter out nav titles
		if ( ! in_the_loop() || get_queried_object_id() != $id ) {
			return $title;
		}

		//set blog_id
		if ( isset( $_POST['bid'] ) ) {
			$blog_id = intval( $_POST['bid'] );
		} else if ( isset( $_GET['bid'] ) ) {
			$blog_id = intval( $_GET['bid'] );
		} else {
			return $title;
		}

		$url = str_replace( 'http://', '', get_home_url( $blog_id, '', 'http' ) );

		return sprintf( __( '%1$s: %2$s (%3$s)', 'psts' ), $title, get_blog_option( $blog_id, 'blogname' ), $url );
	}

	function signup_redirect() {
		global $blog_id;

		//If doing ajax, exit
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX === true ) {
			return;
		}
		//dismiss redirect if free site option is chosen or paid
		if ( isset( $_GET['psts_dismiss'] ) || is_pro_site() ) {
			update_option( 'psts_signed_up', 0 );
		}

		//skip redirect on bulk upgrades page
		if ( isset( $_GET['page'] ) && $_GET['page'] == 'psts-bulk-upgrades' ) {
			return true;
		}
		$psts_force_redirect = $this->get_setting('psts_force_redirect', 1 );
		$psts_force_redirect = apply_filters( 'psts_force_redirect', $psts_force_redirect );
		//force to checkout page
		if ( ! is_super_admin() && ( ( get_option( 'psts_signed_up' ) && current_user_can( 'edit_pages' ) ) && $psts_force_redirect ) ) {
			wp_redirect( $this->checkout_url( $blog_id ) );
			exit();
		}
	}

	function scripts_checkout() {
		wp_enqueue_script( 'psts-checkout', $this->plugin_url . 'js/checkout.js', array( 'jquery' ), $this->version );
		wp_enqueue_script( 'jquery-ui-tabs' );
	}

	function scripts_stats() {
		wp_enqueue_script( 'flot', $this->plugin_url . 'js/jquery.flot.min.js', array( 'jquery' ), $this->version );
		wp_enqueue_script( 'flot_pie', $this->plugin_url . 'js/jquery.flot.pie.min.js', array(
			'jquery',
			'flot'
		), $this->version );
		wp_enqueue_script( 'flot_xcanvas', $this->plugin_url . 'js/excanvas.pack.js', array(
			'jquery',
			'flot'
		), $this->version );
	}

	function scripts_coupons() {
		wp_enqueue_script( 'jquery-datepicker', $this->plugin_url . 'datepicker/js/datepicker.min.js', array(
			'jquery',
			'jquery-ui-core'
		), $this->version );

		//only load languages for datepicker if not english (or it will show Chinese!)
		if ( $this->language != 'en' ) {
			wp_enqueue_script( 'jquery-datepicker-i18n', $this->plugin_url . 'datepicker/js/datepicker-i18n.min.js', array(
				'jquery',
				'jquery-ui-core',
				'jquery-datepickexr'
			), $this->version );
		}
	}

	/**
	 * Enqeue datepicker css on coupons screen
	 *
	 */
	function css_coupons() {
		$this->load_psts_style();
		$this->load_chosen();
		wp_enqueue_style( 'jquery-datepicker-css', $this->plugin_url . 'datepicker/css/ui-lightness/datepicker.css', false, $this->version );
	}

	/**
	 * Register PSTS Style
	 */
	function register_psts_style() {
		wp_register_style( 'psts-style', $this->plugin_url . 'css/psts-admin.css' );

		//Check if chosen css is already registered
		if ( ! wp_style_is( 'chosen', 'registered' ) ) {
			wp_register_style( 'chosen', $this->plugin_url . 'css/chosen/chosen.min.css' );
		}

		wp_register_script( 'psts-js', $this->plugin_url . 'js/psts-admin.js', array(
			'wp-color-picker',
			'jquery'
		), $this->version );
		wp_localize_script( 'psts-js', 'prosites_admin', array(
			'currency_select_placeholder' => __( 'Enable gateways', 'psts' ),
			'disable_premium_plugin' => __( 'Enabling this module will disable Premium Plugin module.', 'psts' ),
			'disable_premium_plugin_manager' => __( 'Enabling this module will disable Premium Plugin Manager module.', 'psts' ),
		));

		wp_register_script( 'psts-js-levels', $this->plugin_url . 'js/psts-admin-levels.js', array(
			'jquery',
			'jquery-ui-sortable',
		), $this->version );

		wp_localize_script( 'psts-js-levels', 'prosites_levels', array(
			'confirm_level_delete' => __( 'Are you sure you really want to remove this level? This will also delete all feature settings for the level.', 'psts' ),
			'confirm_feature_delete' => __( 'Are you sure you really want to remove this custom feature?', 'psts' ),
		) );

		//Check if chosen js is already registered
		if ( ! wp_script_is( 'chosen', 'registered' ) ) {
			wp_register_script( 'chosen', $this->plugin_url . 'js/chosen/chosen.jquery.min.js' );
		}

	}

	/**
	 * Enqueue the main style and js
	 */
	function load_psts_style() {
		ProSites_Helper_UI::load_psts_style();
	}

	/**
	 * Loads the Chosen Style and script
	 */
	function load_chosen() {
		ProSites_Helper_UI::load_chosen();
	}

	function load_settings_style() {
		$this->load_psts_style();
		$this->load_chosen();
	}

	function load_levels_style() {
		$this->load_psts_style();
		$this->load_chosen();

		wp_enqueue_script( 'psts-js-levels' );
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	function css_pricing() {
		?>
		<style type='text/css'>
			.column-psts_co_visible {
				width: 6%;
			}

			.column-psts_co_has_thick {
				width: 10%;
			}

			.column-psts_co_included,
			.column-psts_co_name {
				width: 15%;
			}

			.submit.alignright {
				padding: 0;
				float: right;
			}

			.ui-sortable > tr {
				cursor: move;
			}
		</style> <?php
		wp_enqueue_style( 'wp-color-picker' );
		$this->load_psts_style( false );
	}

	function feature_notice( $level = 1 ) {
		global $blog_id;
		$feature_message = str_replace( 'LEVEL', $this->get_level_setting( $level, 'name', $this->get_setting( 'rebrand' ) ), $this->get_setting( 'feature_message' ) );
		echo '<div id="message" class="error"><p><a href="' . $this->checkout_url( $blog_id ) . '">' . $feature_message . '</a></p></div>';
	}

	function levels_select( $name, $selected, $echo = true ) {
		$html   = '<select name="' . esc_attr( $name ) . '" id="psts-level-select">';
		$levels = (array) get_site_option( 'psts_levels' );
		foreach ( $levels as $level => $value ) {
			$html .= '<option value="' . $level . '"' . selected( $selected, $level, false ) . '>' . $level . ': ' . esc_attr( $value['name'] ) . '</option>';
		}
		$html .= '</select>';

		if ( $echo ) {
			echo $html;
		} else {
			return $html;
		}
	}

	/**
    * Hooked at prosites_inner_pricing_table_pre, to display a message before pricing table
	*
	* @param $content
	*
	*@return mixed
	*/
	function signup_output( $content ) {

		if ( $this->get_setting( 'show_signup_message' ) ) {
			?>
			<div class="psts-signup-message">
				<?php echo $this->get_setting( 'signup_message' ); ?>
			</div>

		<?php
		}
		return $content;
	}

	function signup_override() {
		//carries the hidden signup field over from user to blog signup
		if ( isset( $_GET[ sanitize_title( $this->get_setting( 'rebrand' ) ) ] ) || isset( $_POST['psts_signed_up_override'] ) ) {
			echo '<input type="hidden" name="psts_signed_up_override" value="1" />';
		}
	}

	function signup_save( $meta ) {
		if ( isset( $_POST['psts_signed_up'] ) ) {
			$meta['psts_signed_up'] = ( $_POST['psts_signed_up'] == 'yes' ) ? 1 : 0;
		}

		return $meta;
	}

	function add_column( $columns ) {

		$first_array = array_splice( $columns, 0, 2 );
		$columns     = array_merge( $first_array, array( 'psts' => __( 'Pro Site', 'psts' ) ), $columns );

		return $columns;
	}

	function add_column_field( $column, $blog_id ) {
		$this->column_field_cache( $blog_id );

		if ( $column == 'psts' ) {
			if ( isset( $this->column_fields[ $blog_id ] ) ) {
				echo "<a title='" . __( 'Manage site &raquo;', 'psts' ) . "' href='" . network_admin_url( 'admin.php?page=psts&bid=' . $blog_id ) . "'>" . $this->column_fields[ $blog_id ] . "</a>";
			} else {
				echo "<a title='" . __( 'Manage site &raquo;', 'psts' ) . "' href='" . network_admin_url( 'admin.php?page=psts&bid=' . $blog_id ) . "'>" . __( 'Manage &raquo;', 'psts' ) . "</a>";
			}
		}
	}

	/**
	 * Get Blog Level
	 *
	 * @param $blog_id
	 */
	function column_field_cache( $blog_id ) {
		global $wpdb;

		if ( ! isset( $this->column_fields[ $blog_id ] ) ) {
			$psts = $wpdb->get_results( "SELECT blog_ID, level FROM {$wpdb->base_prefix}pro_sites WHERE expire > '" . time() . "'" );
			foreach ( $psts as $row ) {
				$level_name = $this->get_level_setting( $row->level, 'name' );
				// If level exits show level details
				if ( ! empty  ( $level_name ) ) {
					$level = $row->level . ': ' . $this->get_level_setting( $row->level, 'name' );
				} else {
					//Otherwise get the level below it and show it's name with some indication
					$levels    = ( array ) get_site_option( 'psts_levels' );
					$max_level = max( array_keys( $levels ) );
					$level     = $max_level . ': ' . $this->get_level_setting( $max_level, 'name' ) . '<sup>&#42;</sup>';
				}
				$this->column_fields[ $row->blog_ID ] = $level;
			}
		}
	}

	/**
	 * Returns the js needed to record ecommerce transactions.
	 *
	 * @param $blog_id
	 * @param $period
	 * @param $amount
	 * @param $level
	 * @param string $city
	 * @param string $state
	 * @param string $country
	 * @param string $site_name
	 * @param string $domain
	 */
	function create_ga_ecommerce( $blog_id, $period, $amount, $level, $city = '', $state = '', $country = '', $site_name = '', $domain = '' ) {
		global $current_site;

		$name     = $this->get_level_setting( $level, 'name' );
		$category = $period . ' Month';
		$sku      = 'level' . $level . '_' . $period . 'month';
		if ( ! empty( $blog_id ) ) {
			$order_id = $blog_id . '_' . time();
		} else {
			$order_id = $domain . '_' . time();
		}
		$store_name = $site_name . ' ' . $this->get_setting( 'rebrand' );

		if ( $this->get_setting( 'ga_ecommerce' ) == 'old' ) {

			$js = '<script type="text/javascript">
try{
	pageTracker._addTrans(
		"' . $order_id . '",          // order ID - required
		"' . esc_js( $store_name ) . '",// affiliation or store name
		"' . $amount . '",            // total - required
		"",                       // tax
		"",                       // shipping
		"' . esc_js( $city ) . '",      // city
		"' . esc_js( $state ) . '",     // state or province
		"' . esc_js( $country ) . '"    // country
	);
	pageTracker._addItem(
		"' . $order_id . '",    // order ID - necessary to associate item with transaction
		"' . $sku . '",         // SKU/code - required
		"' . esc_js( $name ) . '",// product name
		"' . $category . '",    // category or variation
		"' . $amount . '",      // unit price - required
		"1"                 // quantity - required
	);

	pageTracker._trackTrans(); //submits transaction to the Analytics servers
} catch(err) {}
</script>
';

		} else if ( $this->get_setting( 'ga_ecommerce' ) == 'new' ) {

			$js = '<script type="text/javascript">
_gaq.push(["_addTrans",
	"' . $order_id . '",          // order ID - required
	"' . esc_js( $store_name ) . '",// affiliation or store name
	"' . $amount . '",            // total - required
	"",                       // tax
	"",                       // shipping
	"' . esc_js( $city ) . '",      // city
	"' . esc_js( $state ) . '",     // state or province
	"' . esc_js( $country ) . '"    // country
]);
_gaq.push(["_addItem",
	"' . $order_id . '",    // order ID - necessary to associate item with transaction
	"' . $sku . '",         // SKU/code - required
	"' . esc_js( $name ) . '",// product name
	"' . $category . '",    // category
	"' . $amount . '",      // unit price - required
	"1"                 // quantity - required
]);
_gaq.push(["_trackTrans"]);
</script>
';

		}

		//add to footer
		if ( ! empty( $js ) ) {
			$function = "echo '$js';";
			add_action( 'wp_footer', create_function( '', $function ), 99999 );
		}
	}

	//------------------------------------------------------------------------//
	//---Page Output Functions------------------------------------------------//
	//------------------------------------------------------------------------//

	function admin_modify() {
		global $wpdb, $current_user;

		if ( ! is_super_admin() ) {
			echo "<p>" . __( 'Nice Try...', 'psts' ) . "</p>"; //If accessed properly, this message doesn't appear.
			return;
		}

		//add manual log entries
		if ( isset( $_POST['log_entry'] ) ) {
			$this->log_action( (int) $_GET['bid'], $current_user->display_name . ': "' . strip_tags( stripslashes( $_POST['log_entry'] ) ) . '"' );
			echo '<div id="message" class="updated fade"><p>' . __( 'Log entry added.', 'psts' ) . '</p></div>';
		}

		//extend blog
		if ( isset( $_POST['psts_extend'] ) ) {
			check_admin_referer( 'psts_extend' ); //check nonce

			if ( isset( $_POST['extend_permanent'] ) ) {
				$extend = 9999999999;
			} else {
				$months = $_POST['extend_months'];
				$days   = $_POST['extend_days'];
				$extend = strtotime( "+$months Months $days Days" ) - time();
			}
			// Get the extension type from post.
			$extend_type = empty( $_POST['extend_type'] ) ? 'manual' : esc_attr( $_POST['extend_type'] );
			$this->extend( (int) $_POST['bid'], $extend, 'manual', $_POST['extend_level'], false, false, true, true, $extend_type );
			echo '<div id="message" class="updated fade"><p>' . __( 'Site Extended.', 'psts' ) . '</p></div>';
		}

		if ( isset( $_POST['psts_transfer_pro'] ) ) {
			$new_bid     = (int) $_POST['new_bid'];
			$current_bid = (int) $_GET['bid'];
			if ( ! $new_bid ) {
				echo '<div id="message" class="error"><p>' . __( 'Please enter the Blog ID of a site to transfer to.', 'psts' ) . '</p></div>';
			} else if ( is_pro_site( $new_bid ) ) {
				echo '<div id="message" class="error"><p>' . __( 'Could not transfer Pro Status: The chosen site already is a Pro Site. You must remove Pro status and cancel any existing subscriptions tied to that site.', 'psts' ) . '</p></div>';
			} else {
				$current_level = $wpdb->get_row( "SELECT * FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$current_bid'" );
				$new_expire    = $current_level->expire - time();
				$this->extend( $new_bid, $new_expire, $current_level->gateway, $current_level->level, $current_level->amount );
				$wpdb->query( "UPDATE {$wpdb->base_prefix}pro_sites SET term = '{$current_level->term}' WHERE blog_ID = '$new_bid'" );
				$this->withdraw( $current_bid );
				$this->log_action( $current_bid, sprintf( __( 'Pro Status transferred by %s to BlogID: %d', 'psts' ), $current_user->display_name, $new_bid ) );
				$this->log_action( $new_bid, sprintf( __( 'Pro Status transferred by %s from BlogID: %d', 'psts' ), $current_user->display_name, $current_bid ) );
				do_action( 'psts_transfer_pro', $current_bid, $new_bid ); //for gateways to hook into for api calls, etc.
				echo '<div id="message" class="updated fade"><p>' . sprintf( __( 'Pro Status transferred to BlogID: %d', 'psts' ), (int) $_POST['new_bid'] ) . '</p></div>';
			}
		}

		//remove blog
		if ( isset( $_POST['psts_modify'] ) ) {
			check_admin_referer( 'psts_modify' ); //check nonce

			do_action( 'psts_modify_process', (int) $_POST['bid'] );

			if ( isset( $_POST['psts_remove'] ) ) {
				$this->withdraw( (int) $_POST['bid'] );
				echo '<div id="message" class="updated fade"><p>' . __( 'Pro Site Status Removed.', 'psts' ) . '</p></div>';
			}

			if ( isset( $_POST['psts_receipt'] ) ) {
				$this->email_notification( (int) $_POST['bid'], 'receipt', $_POST['receipt_email'] );
				echo '<div id="message" class="updated fade"><p>' . __( 'Email receipt sent.', 'psts' ) . '</p></div>';
			}

		}

		//check blog_id
		if ( isset( $_GET['bid'] ) ) {
			$blog_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}blogs WHERE blog_ID = %d", (int) $_GET['bid'] ) );
			if ( ! $blog_count ) {
				echo '<div id="message" class="updated fade"><p>' . __( 'Invalid blog ID. Please try again.', 'psts' ) . '</p></div>';
				$blog_id = false;
			} else {
				$blog_id = (int) $_GET['bid'];
			}
		} else {
			$blog_id = false;
		}

		$activation_key = false;
		if ( isset( $_GET['activation_key'] ) ) {
			$activation_key = $_GET['activation_key'];
		}

		?>
		<div class="wrap">
		<script type="text/javascript">
			jQuery(document).ready(function () {
				jQuery('input.psts_confirm').click(function () {
					var answer = confirm("<?php _e('Are you sure you really want to do this?', 'psts'); ?>")
					if (answer) {
						return true;
					} else {
						return false;
					}
					;
				});
			});
		</script>
		<div class="icon32"><img src="<?php echo $this->plugin_url . 'images/modify.png'; ?>"/></div>
		<h1><?php _e( 'Pro Sites Management', 'psts' ); ?></h1>

		<?php
		if( $activation_key ) {
			$result = ProSites_Helper_Registration::activate_blog( $activation_key );
			$blog_id = (int) $result['blog_id'];
		}

		if ( $blog_id ) {
		    //Get blog details
		    $blog = get_blog_details( $blog_id ); ?>
			<h3><?php _e( 'Manage Site', 'psts' );
			if ( $name = !empty( $blog->blogname ) ? $blog->blogname : get_blog_option( $blog_id, 'blogname' ) ) {
				echo ': ' . $name . ' (Blog ID: ' . $blog_id . ')';
			}

			echo '</h3>';

			if( !empty( $blog ) && !empty( $blog->siteurl ) ) {
			    echo esc_html__("Blog URL: ") . make_clickable( $blog->siteurl );
			}

			$levels        = (array) get_site_option( 'psts_levels' );
			$current_level = $this->get_level( $blog_id );
			$expire        = $this->get_expire( $blog_id );
			$result        = $wpdb->get_row( "SELECT * FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'" );
			if ( $result ) {
				if ( $result->term == 1 || $result->term == 3 || $result->term == 12 ) {
					$term = sprintf( _n( '%s Month','%s Months', $result->term, 'psts' ), $result->term );
				} else {
					$term = $result->term;
				}
			} else {
				$term = 0;
			}

			if ( $expire && $expire > time() ) {
				echo '<p><strong>' . __( 'Current Pro Site', 'psts' ) . '</strong></p>';

				echo '<ul>';
				if ( $expire > 2147483647 ) {
					echo '<li>' . __( 'Pro Site privileges will expire: <strong>Never</strong>', 'psts' ) . '</li>';
				} else {
					$trialing = ProSites_Helper_Registration::is_trial( $blog_id );
					$active_trial = $trialing ? __( '(Active trial)', 'psts') : '';

					echo '<li>' . sprintf( __( 'Pro Site privileges will expire on: <strong>%s</strong>', 'psts' ), date_i18n( get_option( 'date_format' ), $expire ) ) . ' ' . $active_trial . '</li>';
				}

				echo '<li>' . sprintf( __( 'Level: <strong>%s</strong>', 'psts' ), $current_level . ' - ' . @$levels[ $current_level ]['name'] ) . '</li>';
				if ( $result->gateway ) {
					$nicename = ProSites_Helper_Gateway::get_nice_name( $result->gateway );
					echo '<li>' . sprintf( __( 'Payment Gateway: <strong>%s</strong>', 'psts' ), $nicename ) . '</li>';
				}
				if ( $term ) {
					echo '<li>' . sprintf( __( 'Payment Term: <strong>%s</strong>', 'psts' ), $term ) . '</li>';
				}
				echo '</ul>';

			} else if ( $expire && $expire <= time() ) {
				echo '<p><strong>' . __( 'Expired Pro Site', 'psts' ) . '</strong></p>';

				echo '<ul>';
				echo '<li>' . sprintf( __( 'Pro Site privileges expired on: <strong>%s</strong>', 'psts' ), date_i18n( get_option( 'date_format' ), $expire ) ) . '</li>';

				echo '<li>' . sprintf( __( 'Previous Level: <strong>%s</strong>', 'psts' ), $current_level . ' - ' . @$levels[ $current_level ]['name'] ) . '</li>';
				if ( $result->gateway ) {
					$nicename = ProSites_Helper_Gateway::get_nice_name( $result->gateway );
					echo '<li>' . sprintf( __( 'Previous Payment Gateway: <strong>%s</strong>', 'psts' ), $nicename ) . '</li>';
				}
				if ( $term ) {
					echo '<li>' . sprintf( __( 'Previous Payment Term: <strong>%s</strong>', 'psts' ), $term ) . '</li>';
				}
				echo '</ul>';

			} else {
				echo '<p><strong>"' . get_blog_option( $blog_id, 'blogname' ) . '" ' . __( 'has never been a Pro Site.', 'psts' ) . '</strong></p>';
			}

			//meta boxes hooked by gateway plugins
			if ( has_action( 'psts_subscription_info' ) || has_action( 'psts_subscriber_info' ) ) {
				?>
				<div class="metabox-holder">
					<?php if ( has_action( 'psts_subscription_info' ) ) { ?>
						<div style="width: 49%;" class="postbox-container">
							<div class="postbox">
								<h3 class="hndle" style="cursor:auto;">
									<span><?php _e( 'Subscription Information', 'psts' ); ?></span></h3>

								<div class="inside">
									<?php do_action( 'psts_subscription_info', $blog_id ); ?>
								</div>
							</div>
						</div>
					<?php } ?>

					<?php if ( has_action( 'psts_subscriber_info' ) ) { ?>
						<div style="width: 49%;margin-left: 2%;" class="postbox-container">
							<div class="postbox">
								<h3 class="hndle" style="cursor:auto;">
									<span><?php _e( 'Subscriber Information', 'psts' ); ?></span></h3>

								<div class="inside">
									<?php do_action( 'psts_subscriber_info', $blog_id ); ?>
								</div>
							</div>
						</div>
					<?php } ?>

					<div class="clear"></div>
				</div>
			<?php } ?>

			<div id="poststuff" class="metabox-holder">
				<div class="postbox">
					<h3 class="hndle" style="cursor:auto;"><span><?php _e( 'Account History', 'psts' ) ?></span></h3>

					<div class="inside">
						<span class="description"><?php _e( 'This logs basically every action done in the system regarding the site for an audit trail.', 'psts' ); ?></span>

						<div style="height:150px;overflow:auto;margin-top:5px;margin-bottom:5px;">
							<table class="widefat">
								<?php
								$log = get_blog_option( $blog_id, 'psts_action_log' );
								$time_offset = ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
								if ( is_array( $log ) && count( $log ) ) {
									$log = array_reverse( $log, true );
									foreach ( $log as $timestamp => $memo ) {
										$class = ( isset( $class ) && $class == 'alternate' ) ? '' : 'alternate';
										$localtime = $timestamp + $time_offset;
										echo '<tr class="'.$class.'"><td><strong>' . date_i18n( __('Y-m-d g:i:s a', 'psts'), $localtime ) . '</strong></td><td>' . esc_html($memo) . '</td></tr>';
									}
								} else {
									echo '<tr><td colspan="2">' . __( 'No history recorded for this site yet.', 'psts' ) . '</td></tr>';
								}
								?>
							</table>
						</div>
						<form method="post" action="">
							<input type="text" placeholder="<?php _e( 'Add a custom log entry...', 'psts' ); ?>" name="log_entry" style="width:91%;"/>
							<input type="submit" class="button-secondary" name="add_log_entry" value="<?php _e( 'Add &raquo;', 'psts' ) ?>" style="width:8%;float:right;"/>
						</form>
					</div>
				</div>
			</div>


			<div id="poststuff" class="metabox-holder">

				<div style="width: 49%;" class="postbox-container">
					<div class="postbox">
						<h3 class="hndle" style="cursor:auto;">
							<span><?php _e( 'Manually Extend Pro Site Status', 'psts' ) ?></span></h3>

						<div class="inside">
							<span class="description"><?php _e( 'Please note that these changes will not adjust the payment dates or level for any existing subscription.', 'psts' ); ?></span>

							<form method="post" action="">
								<table class="form-table">
									<?php wp_nonce_field( 'psts_extend' ) ?>
									<input type="hidden" name="bid" value="<?php echo $blog_id; ?>"/>
									<tr valign="top">
										<th scope="row"><?php _e( 'Period', 'psts' ) ?></th>
										<td><select name="extend_months">
												<?php
												for ( $counter = 0; $counter <= 36; $counter += 1 ) {
													echo '<option value="' . $counter . '">' . $counter . '</option>' . "\n";
												}
												?>
											</select><?php _e( 'Months', 'psts' ); ?>
											<select name="extend_days">
												<?php
												for ( $counter = 0; $counter <= 30; $counter += 1 ) {
													echo '<option value="' . $counter . '">' . $counter . '</option>' . "\n";
												}
												?>
											</select><?php _e( 'Days', 'psts' ); ?>
											&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php _e( 'or', 'psts' ); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
											<label><input type="checkbox" name="extend_permanent" value="1"/> <?php _e( 'Permanent', 'psts' ); ?>
											</label>
											<br/><?php _e( 'Period you wish to extend the site. Leave at zero to only change the level.', 'psts' ); ?>
										</td>
									</tr>
									<tr valign="top">
										<th scope="row"><?php _e( 'Level', 'psts' ) ?></th>
										<td><select name="extend_level">
												<?php
												foreach ( $levels as $level => $value ) {
													?>
													<option value="<?php echo $level; ?>"<?php selected( $current_level, $level ) ?>><?php echo $level . ': ' . esc_attr( $value['name'] ); ?></option><?php
												}
												?>
											</select>
											<br/><?php _e( 'Choose what level the site should have access to.', 'psts' ); ?>
										</td>
									</tr>
									<tr valign="top">
										<th scope="row"><?php _e( 'Extend as', 'psts' ) ?></th>
										<td>
										<?php
											$gateway = $wpdb->get_var( $wpdb->prepare( "
												SELECT gateway
												FROM {$wpdb->base_prefix}pro_sites
												WHERE blog_ID = %d", $blog_id
											) );
											
											?>
											<select name="extend_type">
												<option value="trial" <?php selected( $gateway,'trial' ); ?>><?php echo ProSites_Helper_Gateway::get_nice_name( 'trial' ); ?></option>
												<option value="manual" <?php selected( $gateway != 'trial' ); ?>><?php _e( 'Manual', 'psts' ); ?></option>
											</select>
											<br/><?php _e( 'Choose whether to keep the user on trial or upgrade as paid member.', 'psts' ); ?>
										</td>
									</tr>
									<?php
									$active_gateways = (array) $this->get_setting('gateways_enabled');
									$stripe_active =  array_search('ProSites_Gateway_Stripe', $active_gateways);
									$stripe_active = ! empty( $stripe_active ) || $stripe_active === 0;
									if( ! empty( $stripe_active ) || $stripe_active === 0 ) {
										?>
									<tr valign="top">
										<th scope="row">
											<?php esc_html_e( 'Stripe Reactivate', 'psts' ); ?>
										</th>
										<td>
											<input type="checkbox" name="attempt_stripe_reactivation" value="1"/>
											<br/><?php esc_html_e( 'Attempt to reactivate former Stripe subscription.', 'psts' ); ?><br/><small><?php esc_html_e( 'Note: Only do this if the subscription was accidentally cancelled or you have explicit permission from the customer.', 'psts' ); ?></small>
										</td>
									</tr>
										<?php
									}
									?>
									<tr valign="top">
										<td colspan="2" style="text-align:right;">
											<input class="button-primary" type="submit" name="psts_extend" value="<?php _e( 'Extend &raquo;', 'psts' ) ?>"/>
										</td>
									</tr>
								</table>
								<hr/>
								<table class="form-table">
									<tr valign="top">
										<td><label>Transfer Pro status to Blog ID:
												<input type="text" name="new_bid" size="3"/></label></td>
										<td style="text-align:right;">
											<input class="button-primary psts_confirm" type="submit" name="psts_transfer_pro" value="<?php _e( 'Transfer &raquo;', 'psts' ) ?>"/>
										</td>
									</tr>
								</table>
							</form>
						</div>
					</div>
				</div>

				<?php if ( is_pro_site( $blog_id ) || has_action( 'psts_modify_form' ) ) { ?>
					<div style="width: 49%;margin-left: 2%;" class="postbox-container">
						<div class="postbox">
							<h3 class="hndle" style="cursor:auto;">
								<span><?php _e( 'Modify Pro Site Status', 'psts' ) ?></span></h3>

							<div class="inside">
								<form method="post" action="">
									<?php wp_nonce_field( 'psts_modify' ) ?>
									<input type="hidden" name="bid" value="<?php echo $blog_id; ?>"/>

									<?php do_action( 'psts_modify_form', $blog_id ); ?>

									<?php if ( is_pro_site( $blog_id ) ) { ?>
										<p>
											<label><input type="checkbox" name="psts_remove" value="1"/> <?php _e( 'Remove Pro status from this site.', 'psts' ); ?>
											</label></p>
									<?php } ?>

									<?php if ( $last_payment = $this->last_transaction( $blog_id ) ) { ?>
										<p>
											<label><input type="checkbox" name="psts_receipt" value="1"/> <?php _e( 'Email a receipt copy for last payment to:', 'psts' ); ?>
												<input type="text" name="receipt_email" value="<?php echo get_blog_option( $blog_id, 'admin_email' ); ?>"/></label>
										</p>
									<?php } ?>

									<p class="submit">
										<input type="submit" name="psts_modify" class="button-primary psts_confirm" value="<?php _e( 'Modify &raquo;', 'psts' ) ?>"/>
									</p>
								</form>
							</div>
						</div>
					</div>
				<?php } ?>
			</div>
			<?php

			//show blog_id form
		} else {
			?>
			<div class="metabox-holder">
				<div class="postbox">
					<h3 class="hndle" style="cursor:auto;"><span><?php _e( 'Manage a Site', 'psts' ) ?></span></h3>

					<div class="inside">
						<form method="get" action="">
							<table class="form-table">
								<input type="hidden" name="page" value="psts"/>
								<tr valign="top">
									<th scope="row"><?php _e( 'Blog ID:', 'psts' ) ?></th>
									<td><input type="text" size="17" name="bid" value=""/>
										<input class="button-secondary" type="submit" value="<?php _e( 'Continue &raquo;', 'psts' ) ?>"/>
									</td>
								</tr>
							</table>
						</form>
						<hr />
						<form method="get" action="">
							<table class="form-table">
								<input type="hidden" name="page" value="psts"/>
								<tr valign="top">
									<th scope="row"><?php _e( 'Activation Key:', 'psts' ) ?></th>
									<td><input type="text" size="17" name="activation_key" value=""/>
										<input class="button-secondary" type="submit" value="<?php _e( 'Activate Blog &raquo;', 'psts' ) ?>"/>
									</td>
								</tr>
							</table>
						</form>
						<hr/>
						<form method="get" action="sites.php" name="searchform">
							<table class="form-table">
								<tr valign="top">
									<th scope="row"><?php _e( 'Or search for a site:<br /><small>By Blog ID, IP address or Path/Domain</small>', 'psts' ) ?></th>
									<td><input type="text" size="17" value="" name="s"/>
										<input class="button-secondary" type="submit" value="<?php _e( 'Search Sites &raquo;', 'psts' ) ?>" id="submit_sites" name="submit"/>
									</td>
								</tr>
							</table>
						</form>
					</div>
				</div>
			</div>
		<?php
		}
		echo '</div>';
	}

function admin_stats() {
	global $wpdb;
	$pro_sites = $level_counts = $term_1 = $term_12 = $term_3 = '';
	$term_manual = $daily_stats_levels = '';
	if ( ! is_super_admin() ) {
		echo "<p>" . __( 'Nice Try...', 'psts' ) . "</p>"; //If accessed properly, this message doesn't appear.
		return;
	}

	$levels = get_site_option( 'psts_levels' );

	$active_pro_sites = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE expire > '" . time() . "'" );
	//$expired_pro_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE expire <= '" . time() . "'");
	$term_1_pro_sites      = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term = 1 AND expire > '" . time() . "'" );
	$term_3_pro_sites      = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term = 3 AND expire > '" . time() . "'" );
	$term_12_pro_sites     = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term = 12 AND expire > '" . time() . "'" );
	$term_manual_pro_sites = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term NOT IN (1,3,12) AND expire > '" . time() . "'" );
	$show_term             = $term_1_pro_sites + $term_3_pro_sites + $term_12_pro_sites + $term_manual_pro_sites;
	//ratio levels
	if ( is_array( $levels ) && count( $levels ) > 1 ) {
		foreach ( $levels as $level => $data ) {
			//if last level include all previous ones greater than that level, in case a level was deleted
			if ( count( $levels ) == $level ) {
				$level_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE level >= $level AND expire > '" . time() . "'" );
			} else {
				$level_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE level = $level AND expire > '" . time() . "'" );
			}

			$ratio_levels[] = '{ label: "' . esc_js( $level . ': ' . $this->get_level_setting( $level, 'name' ) ) . ' (' . $level_count . ')", data: ' . $level_count . '}';
		}
	} else {
		$ratio_levels[] = '{ label: "' . esc_js( '1: ' . $this->get_level_setting( 1, 'name' ) ) . ' (' . $active_pro_sites . ')", data: ' . $active_pro_sites . '}';
	}


if ( $active_pro_sites ) {

	//build gateway dataset
	$gateways = $wpdb->get_results( "SELECT DISTINCT(gateway) FROM {$wpdb->base_prefix}pro_sites WHERE expire > '" . time() . "'" );
	foreach ( $gateways as $gateway ) {
		$count   = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE gateway = '" . $gateway->gateway . "' AND expire > '" . time() . "'" );
		$gates[] = '{ label: "' . $gateway->gateway . ' (' . $count . ')", data: ' . $count . ' }';
	}
	$gates = implode( ', ', (array) $gates );

	//get monthly stats
	if ( ! defined( 'PSTS_STATS_MONTHS' ) ) {
		define( 'PSTS_STATS_MONTHS', 12 );
	}
	$month_data = array();
	for ( $i = 1; $i <= PSTS_STATS_MONTHS; $i ++ ) {
		$month_start = '';
		if ( $i == 1 ) {
			$month_start = date( 'Y-m-01' );
			$month_end   = date( 'Y-m-d', strtotime( '+1 month', strtotime( $month_start ) ) );
		} else {
			$month_start = date( 'Y-m-d', strtotime( '-1 month', strtotime( $month_start ) ) );
			$month_end   = date( 'Y-m-d', strtotime( '+1 month', strtotime( $month_start ) ) );
		}

		$month_stamp                            = strtotime( $month_start );
		$month_data[ $month_stamp ]['signups']  = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'signup' AND time_stamp >= '$month_start' AND time_stamp < '$month_end'" );
		$month_data[ $month_stamp ]['mods']     = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'modify' AND time_stamp >= '$month_start' AND time_stamp < '$month_end'" );
		$month_data[ $month_stamp ]['upgrades'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'upgrade' AND time_stamp >= '$month_start' AND time_stamp < '$month_end'" );
		$month_data[ $month_stamp ]['cancels']  = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'cancel' AND time_stamp >= '$month_start' AND time_stamp < '$month_end'" );

	}
	$month_data = array_reverse( $month_data, true );
	$fix = array_keys( $month_data );
	if( 0 > $fix[0] ) {
		$three_or_four_ago = $fix[1] - (60*60*24*30*4);
		unset( $month_data[$fix[0]] );
		$data = array();
		$data[ $three_or_four_ago ] = array(
			'signups' => 0,
			'mods' => 0,
			'upgrades' => 0,
			'cancels' => 0,
		);
		$month_data = $data + $month_data;
	}

	foreach ( $month_data as $month => $nums ) {
		$month = $month * 1000;

		$m1[] = '[' . $month . ', ' . $nums['signups'] . ']';
		$m2[] = '[' . $month . ', ' . $nums['upgrades'] . ']';
		$m3[] = '[' . $month . ', ' . $nums['mods'] . ']';
		$m4[] = '[' . $month . ', ' . $nums['cancels'] . ']';
	}

	$m1 = implode( ', ', (array) $m1 );
	$m2 = implode( ', ', (array) $m2 );
	$m3 = implode( ', ', (array) $m3 );
	$m4 = implode( ', ', (array) $m4 );

	//get weekly stats
	$week_data = array();
	$start     = time();
	for ( $i = 1; $i <= 26; $i ++ ) { //Only show 6 months of weekly data
		$week_start = $start;
		if ( $i == 1 ) {
			$week_start                           = strtotime( "-$i week", $start );
			$week_start_date                      = date( 'Y-m-d', $week_start );
			$week_data[ $week_start ]['signups']  = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'signup' AND time_stamp >= '$week_start_date'" );
			$week_data[ $week_start ]['upgrades'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'upgrade' AND time_stamp >= '$week_start_date'" );
			$week_data[ $week_start ]['mods']     = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'modify' AND time_stamp >= '$week_start_date'" );
			$week_data[ $week_start ]['cancels']  = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'cancel' AND time_stamp >= '$week_start_date'" );
		} else {
			$week_end                             = $week_start;
			$week_start                           = strtotime( "-$i weeks", $start );
			$week_start_date                      = date( 'Y-m-d', $week_start );
			$week_end_date                        = date( 'Y-m-d', $week_end );
			$week_data[ $week_start ]['signups']  = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'signup' AND time_stamp >= '$week_start_date' AND time_stamp < '$week_end_date'" );
			$week_data[ $week_start ]['upgrades'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'upgrade' AND time_stamp >= '$week_start_date' AND time_stamp < '$week_end_date'" );
			$week_data[ $week_start ]['mods']     = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'modify' AND time_stamp >= '$week_start_date' AND time_stamp < '$week_end_date'" );
			$week_data[ $week_start ]['cancels']  = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'cancel' AND time_stamp >= '$week_start_date' AND time_stamp < '$week_end_date'" );
		}
	}
	$week_data = array_reverse( $week_data, true );

	foreach ( $week_data as $week => $nums ) {
		$week = $week * 1000;

		$w1[] = '[' . $week . ', ' . $nums['signups'] . ']';
		$w2[] = '[' . $week . ', ' . $nums['upgrades'] . ']';
		$w3[] = '[' . $week . ', ' . $nums['mods'] . ']';
		$w4[] = '[' . $week . ', ' . $nums['cancels'] . ']';
	}
	$w1 = implode( ', ', (array) $w1 );
	$w2 = implode( ', ', (array) $w2 );
	$w3 = implode( ', ', (array) $w3 );
	$w4 = implode( ', ', (array) $w4 );

	//get daily totals
	$date = date( 'Y-m-d', strtotime( '-' . PSTS_STATS_MONTHS . ' months', time() ) );
	$days = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}pro_sites_daily_stats WHERE date >= '$date' ORDER BY date", ARRAY_A );
	if ( $days ) {
		$level_count = $pro_sites = $term_1 = $term_3 = $term_12 = $term_manual = array();

		foreach ( $days as $day => $nums ) {
			$day_code = strtotime( $nums['date'] ) * 1000;

			$pro_sites[]   = '[' . $day_code . ', ' . $nums['supporter_count'] . ']';
			$term_1[]      = '[' . $day_code . ', ' . $nums['term_count_1'] . ']';
			$term_3[]      = '[' . $day_code . ', ' . $nums['term_count_3'] . ']';
			$term_12[]     = '[' . $day_code . ', ' . $nums['term_count_12'] . ']';
			$term_manual[] = '[' . $day_code . ', ' . $nums['term_count_manual'] . ']';

			//get level counts
			if ( is_array( $levels ) && count( $levels ) > 1 ) {
				foreach ( $levels as $level => $data ) {
					$level_count[ $level ][] = '[' . $day_code . ', ' . $nums[ 'level_count_' . $level ] . ']';
				}
			}
		}
		$pro_sites   = implode( ', ', $pro_sites );
		$term_1      = implode( ', ', $term_1 );
		$term_3      = implode( ', ', $term_3 );
		$term_12     = implode( ', ', $term_12 );
		$term_manual = implode( ', ', $term_manual );
		foreach ( $level_count as $level => $data ) {
			$level_counts[ $level ] = implode( ', ', (array) $data );
		}
	}

	?>
	<script type="text/javascript">
		jQuery(document).ready(function ($) {
			//set data
			var pro_sites = {
				label: "<?php echo esc_js(__('Total Pro Sites', 'psts')); ?>",
				color: 3,
				data: [<?php echo $pro_sites; ?>]
			};
			<?php
			$daily_stats_levels = '';
			if ( ! empty( $level_counts ) ) {
		        foreach ($level_counts as $level => $data) {
		            //daily stats
		            echo 'var level_'.$level.' = { label: "'.esc_js($level.': '.$this->get_level_setting($level, 'name')).'", data: ['.$data.'] };';
		            $daily_stats_levels .= ", level_$level";
				}
			}
			?>

			var term_1 = {label: "<?php echo esc_js(__('1 Month', 'psts')); ?>", data: [<?php echo $term_1; ?>]};
			var term_3 = {label: "<?php echo esc_js(__('3 Month', 'psts')); ?>", data: [<?php echo $term_3; ?>]};
			var term_12 = {label: "<?php echo esc_js(__('12 Month', 'psts')); ?>", data: [<?php echo $term_12; ?>]};
			var term_manual = {
				label: "<?php echo esc_js(__('Manual', 'psts')); ?>",
				data: [<?php echo $term_manual; ?>]
			};

			var m1 = {label: "<?php echo esc_js(__('Signups', 'psts')); ?>", color: 3, data: [<?php echo $m1; ?>]};
			var m2 = {label: "<?php echo esc_js(__('Upgrades', 'psts')); ?>", color: 5, data: [<?php echo $m2; ?>]};
			var m3 = {
				label: "<?php echo esc_js(__('Modifications', 'psts')); ?>",
				color: 10,
				data: [<?php echo $m3; ?>]
			};
			var m4 = {label: "<?php echo esc_js(__('Cancelations', 'psts')); ?>", color: 2, data: [<?php echo $m4; ?>]};

			var w1 = {label: "<?php echo esc_js(__('Signups', 'psts')); ?>", color: 3, data: [<?php echo $w1; ?>]};
			var w2 = {label: "<?php echo esc_js(__('Upgrades', 'psts')); ?>", color: 5, data: [<?php echo $w2; ?>]};
			var w3 = {
				label: "<?php echo esc_js(__('Modifications', 'psts')); ?>",
				color: 10,
				data: [<?php echo $w3; ?>]
			};
			var w4 = {label: "<?php echo esc_js(__('Cancelations', 'psts')); ?>", color: 2, data: [<?php echo $w4; ?>]};

			var pie_ratio = [
				<?php echo implode(', ', $ratio_levels); ?>
			];

			var pie_gateways = [<?php echo $gates; ?>];

			var pie_terms = [
				{
					label: "<?php echo esc_js(__('1 Month', 'psts')); ?> (<?php echo $term_1_pro_sites; ?>)",
					data: <?php echo $term_1_pro_sites; ?>
				},
				{
					label: "<?php echo esc_js(__('3 Month', 'psts')); ?> (<?php echo $term_3_pro_sites; ?>)",
					data: <?php echo $term_3_pro_sites; ?>
				},
				{
					label: "<?php echo esc_js(__('12 Month', 'psts')); ?> (<?php echo $term_12_pro_sites; ?>)",
					data: <?php echo $term_12_pro_sites; ?>
				},
				{
					label: "<?php echo esc_js(__('Manual', 'psts')); ?> (<?php echo $term_manual_pro_sites; ?>)",
					data: <?php echo $term_manual_pro_sites; ?>
				}
			];

			//set options
			var graph_options1 = {
				xaxis: {mode: "time", minTickSize: [1, "month"], timeformat: "%b %y"},
				yaxis: {min: 0, minTickSize: 1, tickDecimals: 0},
				lines: {show: true},
				points: {show: true},
				legend: {show: true, backgroundOpacity: 0.5, position: "nw"},
				grid: {hoverable: true, clickable: false}
			};

			var graph_options2 = {
				xaxis: {mode: "time", minTickSize: [1, "month"], timeformat: "%b %y"},
				yaxis: {min: 0, minTickSize: 1, tickDecimals: 0},
				lines: {show: true},
				points: {show: true},
				legend: {show: true, backgroundOpacity: 0.5, position: "nw"},
				grid: {hoverable: true, clickable: false}
			};

			var graph_options3 = {
				xaxis: {mode: "time", minTickSize: [1, "month"], timeformat: "%b %y"},
				yaxis: {minTickSize: 1, tickDecimals: 0},
				lines: {show: true},
				points: {show: false},
				legend: {show: true, backgroundOpacity: 0.5, position: "nw"}
			};

			var pie_options = {
				series: {
					pie: {
						show: true,
						radius: 1,
						label: {
							show: true,
							radius: 3 / 4,
							formatter: function (label, series) {
								return '<div style="font-size:8pt;font-weight:bold;text-align:center;padding:2px;color:white;">' + Math.round(series.percent) + '%</div>';
							},
							background: {opacity: 0.5}
						}
					}
				},
				legend: {show: true, backgroundOpacity: 0.5}
			};

			//plot graphs
			<?php if ($days) { ?>
			jQuery.plot(jQuery("#daily_stats"), [pro_sites<?php echo $daily_stats_levels; ?>], graph_options3);
			jQuery.plot(jQuery("#daily_term_stats"), [term_1, term_3, term_12, term_manual], graph_options3);
			<?php } ?>
			jQuery.plot(jQuery("#monthly_signup_stats"), [m1, m2, m3, m4], graph_options1);
			jQuery.plot(jQuery("#weekly_signup_stats"), [w1, w2, w3, w4], graph_options2);
			jQuery.plot(jQuery("#pie-ratio"), pie_ratio, pie_options);
			jQuery.plot(jQuery("#pie-gateway"), pie_gateways, pie_options);
			<?php if ($show_term) { ?>
			jQuery.plot(jQuery("#pie-terms"), pie_terms, pie_options);
			<?php } ?>

			//handle window resizing
			jQuery(window).resize(function ($) {
				<?php if ($days) { ?>
				jQuery.plot(jQuery("#daily_stats"), [pro_sites<?php echo $daily_stats_levels; ?>], graph_options3);
				jQuery.plot(jQuery("#daily_term_stats"), [term_1, term_3, term_12, term_manual], graph_options3);
				<?php } ?>
				jQuery.plot(jQuery("#monthly_signup_stats"), [m1, m2, m3, m4], graph_options1);
				jQuery.plot(jQuery("#weekly_signup_stats"), [w1, w2, w3, w4], graph_options2);
				jQuery.plot(jQuery("#pie-ratio"), pie_ratio, pie_options);
				jQuery.plot(jQuery("#pie-gateway"), pie_gateways, pie_options);
				<?php if ($show_term) { ?>
				jQuery.plot(jQuery("#pie-terms"), pie_terms, pie_options);
				<?php } ?>
			});

			//tooltips
			function showTooltip(x, y, contents) {
				$('<div id="tooltip">' + contents + '</div>').css({
					position: 'absolute',
					display: 'none',
					top: y + 5,
					left: x + 5,
					border: '1px solid #fdd',
					padding: '2px',
					'background-color': '#fee',
					opacity: 0.80
				}).appendTo("body").fadeIn(200);
			}

			var previousPoint = null;
			$("#monthly_signup_stats").bind("plothover", function (event, pos, item) {
				if (item) {
					if (previousPoint != item.datapoint) {
						previousPoint = item.datapoint;

						$("#tooltip").remove();
						var x = item.datapoint[0].toFixed(2),
							y = item.datapoint[1].toFixed(2);

						var dt = new Date(parseInt(x));
						var monthname = new Array("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");
						var date = monthname[dt.getMonth()] + " " + dt.getFullYear();
						showTooltip(item.pageX, item.pageY, item.series.label + " in<br>" + date + ": " + parseInt(y));
					}
				} else {
					$("#tooltip").remove();
					previousPoint = null;
				}
			});
			$("#weekly_signup_stats").bind("plothover", function (event, pos, item) {
				if (item) {
					if (previousPoint != item.datapoint) {
						previousPoint = item.datapoint;

						$("#tooltip").remove();
						var x = item.datapoint[0].toFixed(2),
							y = item.datapoint[1].toFixed(2);

						var dt = new Date(parseInt(x));
						var date = dt.getFullYear() + "/" + (dt.getMonth() + 1) + "/" + dt.getDate();
						showTooltip(item.pageX, item.pageY, item.series.label + " in the<br>week of " + date + ": " + parseInt(y));
					}
				} else {
					$("#tooltip").remove();
					previousPoint = null;
				}
			});
		});
	</script>
<div class="wrap">
	<div class="icon32"><img src="<?php echo $this->plugin_url . 'images/stats.png'; ?>"/></div>
	<h1><?php _e( 'Pro Sites Statistics', 'psts' ); ?></h1>

<?php echo $this->weekly_summary(); ?>

	<div class="metabox-holder">

		<div class="postbox">
			<h3 class="hndle" style="cursor:auto;"><span><?php _e( 'Monthly Activity Summary', 'psts' ) ?></span></h3>

			<div class="inside">
				<div id="monthly_signup_stats" style="margin:20px;height:300px"><?php _e( 'No data available yet', 'psts' ) ?></div>
			</div>
		</div>

		<div class="postbox">
			<h3 class="hndle" style="cursor:auto;"><span><?php _e( 'Weekly Activity Summary', 'psts' ) ?></span></h3>

			<div class="inside">
				<div id="weekly_signup_stats" style="margin:20px;height:300px"><?php _e( 'No data available yet', 'psts' ) ?></div>
			</div>
		</div>

		<div class="postbox">
			<h3 class="hndle" style="cursor:auto;"><span><?php _e( 'Ratios', 'psts' ) ?></span></h3>

			<div class="inside">

				<div style="width:40%;height:300px;float:left;margin-bottom:25px;">
					<h4 style="margin-left:20%;"><?php printf( __( 'Current Pro Site Levels (%s Total)', 'psts' ), number_format_i18n( $active_pro_sites ) ); ?></h4>

					<div id="pie-ratio" style="width:100%;height:100%;"><?php _e( 'No data available yet', 'psts' ) ?></div>
				</div>

				<div style="width:40%;height:300px;float:left;margin-left:10%;margin-bottom:25px;">
					<h4 style="margin-left:20%;"><?php _e( 'Current Gateway Use', 'psts' ) ?></h4>

					<div id="pie-gateway" style="width:100%;height:100%;"><?php _e( 'No data available yet', 'psts' ) ?></div>
				</div>
				<div class="clear" style="margin-bottom:50px;"></div>

			</div>
		</div>

		<div class="postbox">
			<h3 class="hndle" style="cursor:auto;"><span><?php _e( 'Pro Sites History', 'psts' ) ?></span></h3>

			<div class="inside">
				<div id="daily_stats" style="margin:20px;height:300px"><?php _e( 'No data available yet', 'psts' ) ?></div>
			</div>
		</div>

		<div class="postbox">
			<h3 class="hndle" style="cursor:auto;"><span><?php _e( 'Pro Sites Term History', 'psts' ) ?></span></h3>

			<div class="inside">
				<div id="daily_term_stats" style="margin:20px;height:300px;"><?php _e( 'No data available yet', 'psts' ) ?></div>
				<h4 style="margin-left:10%;"><?php _e( 'Current Terms', 'psts' ) ?></h4>

				<div id="pie-terms" style="width:40%;height:300px;margin-bottom:25px;"><?php _e( 'No data available yet', 'psts' ) ?></div>
			</div>
		</div>

	</div>
<?php
} else {
	echo '<h3>' . __( 'No data available yet', 'psts' ) . '</h3>';
}
	echo '</div>';
}

function admin_levels() {
	global $wpdb;

	if ( ! is_super_admin() ) {
		echo "<p>" . __( 'Nice Try...', 'psts' ) . "</p>"; //If accessed properly, this message doesn't appear.
		return;
	}
	?>
	<div class="wrap">
	<div class="icon32"><img src="<?php echo $this->plugin_url . 'images/levels.png'; ?>"/></div>
	<h1><?php _e( 'Pro Sites Levels', 'psts' ); ?></h1>
	<?php

	$levels = (array) get_site_option( 'psts_levels' );

	//delete checked levels
	if ( isset( $_POST['delete_level'] ) ) {

		//check nonce
		check_admin_referer( 'psts_levels' );

		// Get correct level
		$level_num = (array) $_POST['delete_level'];
		$level_num = array_keys( $level_num );
		$level_num = array_pop( $level_num );
		$level_num = (int) $level_num;

		if( in_array( $level_num, array_keys( $levels ) ) ) {
			unset( $levels[ $level_num] );

			// Re-Index
			$levels = array_merge( array('x'), array_values( $levels ) );
			unset( $levels[0]);

			update_site_option( 'psts_levels', $levels );
			//Update Pricing level order
			ProSites_Helper_ProSite::update_level_order( $levels );

			//display message confirmation
			echo '<div class="updated fade"><p>' . sprintf( __( 'Level %s successfully deleted.', 'psts' ), number_format_i18n( $level_num ) ) . '</p></div>';
		}

	}

	//add level
	if ( isset( $_POST['add_level'] ) ) {
		//check nonce
		check_admin_referer( 'psts_levels' );

		$error = false;

		if ( empty( $_POST['add_name'] ) ) {
			$error[] = __( 'Please enter a valid level name.', 'psts' );
		}

		if ( ! is_numeric( $_POST['add_price_1'] ) && ! is_numeric( $_POST['add_price_3'] ) && ! is_numeric( $_POST['add_price_12'] ) ) {
			$error[] = __( 'You must enter a price for at least one payment period.', 'psts' );
		}

		if ( ! $error ) {
			$level_data = array(
				'name'       => stripslashes( trim( wp_filter_nohtml_kses( $_POST['add_name'] ) ) ),
				'price_1'    => round( @$_POST['add_price_1'], 2 ),
				'price_3'    => round( @$_POST['add_price_3'], 2 ),
				'price_12'   => round( @$_POST['add_price_12'], 2 ),
				'is_visible' => intval( @$_POST['add_is_visible'] ),
				'setup_fee'  => round( @$_POST['add_setup_fee'], 2 )
			);

			// Just in case something went wrong, make sure we start at 1.
			if( 0 == count( $levels ) ){
				$levels[1] = $level_data;
			} else {
				$levels[] = $level_data;
			}

			update_site_option( 'psts_levels', $levels );
			echo '<div class="updated fade"><p>' . __( 'Level added.', 'psts' ) . '</p></div>';
		} else {
			echo '<div class="error"><p>' . implode( '<br />', $error ) . '</p></div>';
		}
	}

	//save levels
	if ( isset( $_POST['save_levels'] ) ) {
		//check nonce
		check_admin_referer( 'psts_levels' );

		$periods = array();
		if ( isset( $_POST['enable_1'] ) ) {
			$periods[] = 1;
		}
		if ( isset( $_POST['enable_3'] ) ) {
			$periods[] = 3;
		}
		if ( isset( $_POST['enable_12'] ) ) {
			$periods[] = 12;
		}

		$this->update_setting( 'enabled_periods', $periods );

		$old_levels = $levels;

		foreach ( $_POST['name'] as $level => $name ) {
			$stripped_name                  = stripslashes( trim( wp_filter_nohtml_kses( $name ) ) );
			$name                           = empty( $stripped_name ) ? $levels[ $level ]['name'] : $stripped_name;
			$levels[ $level ]['name']       = $name;
			$levels[ $level ]['price_1']    = isset($_POST['price_1'] ) ? round( @$_POST['price_1'][ $level ], 2 ) : $old_levels[$level]['price_1'];
			$levels[ $level ]['price_3']    = isset($_POST['price_3'] ) ? round( @$_POST['price_3'][ $level ], 2 ) : $old_levels[$level]['price_3'];
			$levels[ $level ]['price_12']   = isset($_POST['price_12'] ) ? round( @$_POST['price_12'][ $level ], 2 ) : $old_levels[$level]['price_12'];

			$levels[ $level ]['is_visible'] = isset( $_POST['is_visible'][ $level ] ) ? intval( $_POST['is_visible'][ $level ] ) : 0;
		}

		do_action( 'update_site_option_psts_levels', '', $levels, $old_levels );
		update_site_option( 'psts_levels', $levels );
		echo '<div class="updated fade"><p>' . __( 'Levels saved.', 'psts' ) . '</p></div>';
	}

	$level_list = get_site_option( 'psts_levels' );
	$last_level = ( is_array( $level_list ) ) ? count( $level_list ) : 0;
	$periods    = (array) $this->get_setting( 'enabled_periods' );
	?>

	<form id="form-level-list" action="" method="post">
		<?php wp_nonce_field( 'psts_levels' ) ?>

		<?php
		// define the columns to display, the syntax is 'internal name' => 'display name'
		$posts_columns = array(
			'level'      => __( 'Level', 'psts' ),
			'name'       => __( 'Name', 'psts' ),
			'is_visible' => __( 'Is Visible', 'psts' ),
			'price_1'    => __( '1 Month Price', 'psts' ),
			'price_3'    => __( '3 Month Price', 'psts' ),
			'price_12'   => __( '12 Month Price', 'psts' ),
			'edit'       => ''
		);
		?>
		<h3><?php _e( 'Edit Pro Site Levels', 'psts' ) ?></h3>
		<span class="description"><?php _e( 'Pro Sites will have the features assigned to all level numbers at or less than their own. You can disable a subscription period by unchecking it. Modifying the prices of a level will not change the current subsciption rate or plan for existing sites in that level. When you delete a level, existing sites in that level will retain the features of all levels below their current level number.', 'psts' ) ?></span>
		<table width="100%" cellpadding="3" cellspacing="3" class="widefat level-settings" id="prosites-level-list">
			<thead>
			<tr>
				<th scope="col"><?php _e( 'Level', 'psts' ); ?></th>
				<th scope="col"><?php _e( 'Name', 'psts' ); ?></th>
				<th scope="col"><?php _e( 'Is Visible', 'psts' ); ?></th>
				<th scope="col">
					<label><input name="enable_1" id="enable_1" value="1" title="<?php _e( 'Enable 1 Month Checkout', 'psts' ); ?>" type="checkbox"<?php checked( in_array( 1, $periods ) ); ?>> <?php _e( '1 Month Price', 'psts' ); ?>
					</label></th>
				<th scope="col">
					<label><input name="enable_3" id="enable_3" value="1" title="<?php _e( 'Enable 3 Month Checkout', 'psts' ); ?>" type="checkbox"<?php checked( in_array( 3, $periods ) ); ?>> <?php _e( '3 Month Price', 'psts' ); ?>
					</label></th>
				<th scope="col">
					<label><input name="enable_12" id="enable_12" value="1" title="<?php _e( 'Enable 12 Month Checkout', 'psts' ); ?>" type="checkbox"<?php checked( in_array( 12, $periods ) ); ?>> <?php _e( '12 Month Price', 'psts' ); ?>
					</label></th>
				<th scope="col"></th>
			</tr>
			</thead>
			<tbody id="the-list">
			<?php
			if ( is_array( $level_list ) && count( $level_list ) ) {
				$bgcolor = $class = '';
				foreach ( $level_list as $level_code => $level ) {
					$class = ( 'alternate' == $class ) ? '' : 'alternate';

					echo '<tr class="' . $class . ' blog-row">';

					foreach ( $posts_columns as $column_name => $column_display_name ) {
						switch ( $column_name ) {
							case 'level':
								?>
								<td scope="row" style="padding-left: 20px;">
									<strong><?php echo $level_code; ?></strong>
								</td>
								<?php
								break;

							case 'name':
								?>
								<td scope="row">
									<input data-position="<?php echo esc_attr( (int) $level_code ); ?>" value="<?php echo esc_attr( $level['name'] ) ?>" size="50" maxlength="100" name="name[<?php echo $level_code; ?>]" type="text"/>
								</td>
								<?php
								break;

							case 'is_visible':
								?>
								<td scope="row">
									<?php $is_visible = isset( $level['is_visible'] ) ? $level['is_visible'] : 1; ?>
									<input value="1" name="is_visible[<?php echo $level_code; ?>]" type="checkbox" <?php echo checked( $is_visible, 1 ); ?> />
								</td>
								<?php
								break;

							case 'price_1':
								?>
								<td scope="row">
									<label><?php echo $this->format_currency(); ?></label><input class="price-1" value="<?php echo ( isset( $level['price_1'] ) ) ? number_format( (float) $level['price_1'], 2, '.', '' ) : ''; ?>" size="4" name="price_1[<?php echo $level_code; ?>]" type="text"/>
								</td>
								<?php
								break;

							case 'price_3':
								?>
								<td scope="row">
									<label><?php echo $this->format_currency(); ?></label><input class="price-3" value="<?php echo ( isset( $level['price_3'] ) ) ? number_format( (float) $level['price_3'], 2, '.', '' ) : ''; ?>" size="4" name="price_3[<?php echo $level_code; ?>]" type="text"/>
								</td>
								<?php
								break;

							case 'price_12':
								?>
								<td scope="row">
									<label><?php echo $this->format_currency(); ?></label><input class="price-12" value="<?php echo ( isset( $level['price_12'] ) ) ? number_format( (float) $level['price_12'], 2, '.', '' ) : ''; ?>" size="4" name="price_12[<?php echo $level_code; ?>]" type="text"/>
								</td>
								<?php
								break;

							case 'edit':
								?>
								<td scope="row">
									<?php if ( count( $level_list ) > 1 ) {
										//Display delete option for last level only
										?>
										<input class="button" type="submit" name="delete_level[<?php echo $level_code; ?>]" value="<?php _e( 'Delete &raquo;', 'psts' ) ?>"/>
									<?php } ?>
								</td>
								<?php
								break;

						}
					}
					?>
					</tr>
				<?php
				}
			} else {
				$bgcolor = 'transparent';
				?>
				<tr style='background-color: <?php echo $bgcolor; ?>'>
					<td colspan="6"><?php _e( 'No levels yet.', 'psts' ) ?></td>
				</tr>
			<?php
			} // end if levels
			?>

			</tbody>
		</table>
		<p class="submit">
			<input type="submit" name="save_levels" class="button-primary" value="<?php _e( 'Save Levels', 'psts' ) ?>"/>
			<span class="save_levels_dirty" style="display:none;"><?php esc_html_e( 'Changes not saved.', 'psts' ); ?></span>
		</p>

		<h3><?php _e( 'Add New Level', 'psts' ) ?></h3>
		<span class="description"><?php _e( 'You can add a new Pro Site level here.', 'psts' ) ?></span>
		<table width="100%" cellpadding="3" cellspacing="3" class="widefat">
			<thead>
			<tr>
				<th scope="col"><?php _e( 'Level', 'psts' ); ?></th>
				<th scope="col"><?php _e( 'Name', 'psts' ); ?></th>
				<th scope="col"><?php _e( 'Is Visible', 'psts' ); ?></th>
				<th scope="col"><?php _e( '1 Month Price', 'psts' ); ?></th>
				<th scope="col"><?php _e( '3 Month Price', 'psts' ); ?></th>
				<th scope="col"><?php _e( '12 Month Price', 'psts' ); ?></th>
				<th scope="col"></th>
			</tr>
			</thead>
			<tbody id="the-list">
			<tr>
				<td scope="row" style="padding-left: 20px;">
					<strong><?php echo $last_level + 1; ?></strong>
				</td>
				<td>
					<input value="" size="50" maxlength="100" name="add_name" type="text"/>
				</td>
				<td>
					<input value="1" name="add_is_visible" type="checkbox" checked="checked"/>
				</td>
				<td>
					<label><?php echo $this->format_currency(); ?></label><input class="price-1" value="" size="4" name="add_price_1" type="text"/>
				</td>
				<td>
					<label><?php echo $this->format_currency(); ?></label><input class="price-3" value="" size="4" name="add_price_3" type="text"/>
				</td>
				<td>
					<label><?php echo $this->format_currency(); ?></label><input class="price-12" value="" size="4" name="add_price_12" type="text"/>
				</td>
				<td>
					<input class="button" type="submit" name="add_level" value="<?php _e( 'Add &raquo;', 'psts' ) ?>"/>
				</td>
			</tr>
			</tbody>
		</table>


	</div>
	</form>

</div>
<?php
}

function admin_modules() {
	global $psts_modules;
	ProSites_Helper_UI::load_psts_style();

		if ( ! is_super_admin() ) {
			echo "<p>" . __( 'Nice Try...', 'psts' ) . "</p>"; //If accessed properly, this message doesn't appear.
			return;
		}
		if ( get_option( 'psts_module_settings_updated' ) ) {
			delete_option( 'psts_module_settings_updated' );
			echo '<div class="updated notice-info is-dismissible"><p>' . __( 'Modules Saved. Please visit <a href="admin.php?page=psts-settings">Settings</a> to configure them.', 'psts' ) . '</p></div>';
		}
		?>
		<div class="wrap">
			<div class="icon32" id="icon-plugins"></div>
			<h1><?php _e( 'Pro Sites Modules', 'psts' ); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field( 'psts_modules' ) ?>

				<h3><?php _e( 'Enable Modules', 'psts' ) ?></h3>
				<span class="description"><?php _e( 'Select the modules you would like to use below. You can then configure their options on the settings page.', 'psts' ) ?></span>
				<table class="widefat">
					<thead>
						<tr>
							<th style="width: 15px;"><?php _e( 'Enable', 'psts' ) ?></th>
							<th><?php _e( 'Module Name', 'psts' ) ?></th>
							<th><?php _e( 'Description', 'psts' ) ?></th>
						</tr>
					</thead>
					<tbody id="plugins"><?php
					$css  = '';
					$css2 = '';
					uasort( $psts_modules, create_function( '$a,$b', 'if ($a[0] == $b[0]) return 0;return ($a[0] < $b[0])? -1 : 1;' ) ); //sort modules by name

					$modules_enabled = (array) $this->get_setting( 'modules_enabled' );

					foreach ( (array) $psts_modules as $class => $plugin ) {
						$css = ( 'alt' == $css ) ? '' : 'alt';
						if ( in_array( $class,  $modules_enabled ) ) {
							$css2   = ' active';
							$active = true;
						} else {
							$active = false;
						}

						?>
						<tr valign="top" class="<?php echo $css . $css2; ?>">
							<td style="text-align:center;">
								<?php
								if ( $plugin[2] ) { //if demo
									?>
									<input type="checkbox" id="psts_<?php echo $class; ?>" name="allowed_modules[]" value="<?php echo $class; ?>" disabled="disabled"/>
									<a class="psts-pro-update" href="http://premium.wpmudev.org/project/pro-sites" title="<?php _e( 'Upgrade', 'psts' ); ?> &raquo;"><?php _e( 'Premium Only &raquo;', 'psts' ); ?></a><?php
								} else {
									?>
									<input type="checkbox" id="psts_<?php echo $class; ?>" name="allowed_modules[]" value="<?php echo $class; ?>"<?php checked( $active ); ?> /><?php
								}
								?>
							</td>
							<td><label for="psts_<?php echo $class; ?>"><?php echo esc_attr( $plugin[0] ); ?></label>
							</td>
							<td><?php echo esc_attr( $plugin[1] ); ?></td>
						</tr>
					<?php
					} ?>
					</tbody>
				</table>

				<?php do_action( 'psts_modules_page' ); ?>

				<p class="submit">
					<input type="submit" name="submit_module_settings" class="button-primary" value="<?php _e( 'Save Changes', 'psts' ) ?>"/>
				</p>
			</form>

		</div>
	<?php
	}

	function checkout_redirect_page() {
		//This page should never be shown
		global $blog_id;

		/*
		if( !current_user_can('edit_pages') ) {
			echo "<p>" . __('Nice Try...', 'psts') . "</p>";  //If accessed properly, this message doesn't appear.
			return;
		}
		*/

		echo '<div class="wrap">';
		echo "<script type='text/javascript'>window.location='" . $this->checkout_url( $blog_id ) . "';</script>";
		echo '<a href="' . $this->checkout_url( $blog_id ) . '">Go Here</a>';
		echo '</div>'; //div wrap
	}

	function checkout_grid( $blog_id, $domain = '' ) {

		global $wpdb;
		$curr = '';

		$use_plans_table    = $this->get_setting( 'plans_table_enabled', 'enabled' );
		$show_pricing_table = $this->get_setting( 'comparison_table_enabled' ) ? $this->get_setting( 'comparison_table_enabled' ) : $this->get_setting( 'co_pricing' );
		$content            = "";
		include_once $this->plugin_dir . 'lib/psts_pricing_table.php';
		$pricing_table = ProSites_Pricing_Table::getInstance( array(
			'blog_id' => $blog_id
		) );

		if ( $show_pricing_table === "enabled" ) {
			$content .= $pricing_table->display_plans_table( 'include-pricing' );

			return apply_filters( 'psts_checkout_grid_output', $content );
		}

		if ( $use_plans_table === "enabled" ) {
			$content .= $pricing_table->display_plans_table();

			return apply_filters( 'psts_checkout_grid_output', $content );
		}

		if( 'enabled' === $use_plans_table || 'enabled' === $show_pricing_table ) {
			return false;
		}


		// DO IT THE OLD WAY

		$levels    = (array) get_site_option( 'psts_levels' );
		$recurring = $this->get_setting( 'recurring_subscriptions', 1 );

		//if you want to display the lowest level first on checkout grid add define('PSTS_DONT_REVERSE_LEVELS', true); to your wp-config.php file
		if ( ! ( defined( 'PSTS_DONT_REVERSE_LEVELS' ) && PSTS_DONT_REVERSE_LEVELS ) ) {
			$levels = array_reverse( $levels, true );
		}

		//remove levels that are hidden
		foreach ( $levels as $level_id => $level ) {
			$is_visible = isset( $level['is_visible'] ) ? (bool) $level['is_visible'] : true;
			if ( $is_visible ) {
				continue;
			}
			unset( $levels[ $level_id ] );
		}

		$periods = (array) $this->get_setting( 'enabled_periods' );
		if ( ! empty( $blog_id ) ) {
			$curr = $wpdb->get_row( $wpdb->prepare( "SELECT term, level FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id ) );
		}

		if ( $curr ) {
			$curr->term = ( $curr->term && ! is_numeric( $curr->term ) ) ? $periods[0] : $curr->term; //if term not numeric
			$sel_period = isset( $_POST['period'] ) ? $_POST['period'] : $curr->term;
			$sel_level  = isset( $_POST['level'] ) ? $_POST['level'] : $curr->level;
		} else {
			@$curr->term = null;
			$curr->level = null;
			$sel_period  = isset( $_POST['period'] ) ? $_POST['period'] : ( defined( 'PSTS_DEFAULT_PERIOD' ) ? PSTS_DEFAULT_PERIOD : null );
			$sel_level   = isset( $_POST['level'] ) ? $_POST['level'] : ( defined( 'PSTS_DEFAULT_LEVEL' ) ? PSTS_DEFAULT_LEVEL : null );
		}

		if ( count( $periods ) >= 3 ) {
			$width      = '23%';
			$free_width = '95%';
		} else if ( count( $periods ) == 2 ) {
			$width      = '30%';
			$free_width = '92.5%';
		} else {
			$width      = '40%';
			$free_width = '85%';
		}

		$content = '';

		/*
		//show chosen blog
		$content .= '<div id="psts-chosen-blog">'.sprintf(__('You have chosen to upgrade <strong>%1$s</strong> (%2$s).', 'psts'), get_blog_option($blog_id, 'blogname'), get_blog_option($blog_id, 'siteurl')).'
								 <a id="psts-change-blog" href="'.$this->checkout_url().'">'.__('Change &raquo;', 'psts').'</a><br>
								</div>';
		*/

		$content = apply_filters( 'psts_before_checkout_gridcoupon-submit', $content, $blog_id );

		//add coupon line
		if ( $session_coupon = ProSites_Helper_Session::session( 'COUPON_CODE' ) ) {
			$coupon_value = $this->coupon_value( $session_coupon, 100 );
			$content .= '<div id="psts-coupon-msg">' . sprintf( __( 'Your coupon code <strong>%1$s</strong> has been applied for a discount of %2$s off the first payment. <a href="%3$s">Remove it &raquo;</a>', 'psts' ), esc_html( $session_coupon ), $coupon_value['discount'], get_permalink() . "?bid=$blog_id&remove_coupon=1" ) . '</div>';
		} else if ( $errmsg = $this->errors->get_error_message( 'coupon' ) ) {
			$content .= '<div id="psts-coupon-error" class="psts-error">' . $errmsg . '</div>';
		}

		$content = apply_filters( 'psts_before_checkout_grid', $content, $blog_id );

		$content .= '<table id="psts_checkout_grid" width="100%">';

		if ( $recurring ) {
			$content .= '<tr class="psts_level_head">
					<th>' . __( 'Level', 'psts' ) . '</th>';
			if ( in_array( 1, $periods ) ) {
				$content .= '<th>' . __( 'Monthly', 'psts' ) . '</th>';
			}
			if ( in_array( 3, $periods ) ) {
				$content .= '<th>' . __( 'Every 3 Months', 'psts' ) . '</th>';
			}
			if ( in_array( 12, $periods ) ) {
				$content .= '<th>' . __( 'Every 12 Months', 'psts' ) . '</th>';
			}
			$content .= '</tr>';
		} else {
			$content .= '<tr class="psts_level_head">
					<th>' . __( 'Level', 'psts' ) . '</th>';
			if ( in_array( 1, $periods ) ) {
				$content .= '<th>' . __( '1 Month', 'psts' ) . '</th>';
			}
			if ( in_array( 3, $periods ) ) {
				$content .= '<th>' . __( '3 Months', 'psts' ) . '</th>';
			}
			if ( in_array( 12, $periods ) ) {
				$content .= '<th>' . __( '12 Months', 'psts' ) . '</th>';
			}
			$content .= '</tr>';
		}

		$equiv         = '';
		$coupon_price  = '';
		$setup_fee_amt = $this->get_setting( 'setup_fee', 0 );

		foreach ( $levels as $level => $data ) {
			$content .= '<tr class="psts_level level-' . $level . '">
				<td valign="middle" class="level-name">';
			$content .= apply_filters( 'psts_checkout_grid_levelname', '<h3>' . $data['name'] . '</h3>', $level, $blog_id );
			$content .= '</td>';
			if ( in_array( 1, $periods ) ) {
				$current       = ( $curr->term == 1 && $curr->level == $level ) ? ' opt-current' : '';
				$selected      = ( $sel_period == 1 && $sel_level == $level ) ? ' opt-selected' : '';
				$upgrade_price = ( $recurring ) ? $data['price_1'] : $this->calc_upgrade_cost( $blog_id, $level, 1, $data['price_1'] );

				$session_coupon = ProSites_Helper_Session::session( 'COUPON_CODE' );
				if ( isset( $session_coupon ) && $this->check_coupon( $session_coupon, $blog_id, $level, 1 ) && $coupon_value = $this->coupon_value( $session_coupon, $data['price_1'] ) ) {
					$coupon_price = '<span class="pblg-old-price">' . $this->format_currency( false, $data['price_1'] ) . '</span> <span class="pblg-price">' . $this->format_currency( false, $coupon_value['new_total'] ) . '</span>';
				} elseif ( $upgrade_price != $data['price_1'] ) {
					$coupon_price = '<span class="pblg-old-price">' . $this->format_currency( false, $data['price_1'] ) . '</span> <span class="pblg-price">' . $this->format_currency( false, $upgrade_price ) . '</span>';
				} else {
					$coupon_price = '<span class="pblg-price">' . $this->format_currency( false, $data['price_1'] ) . '</span>';
				}

				//setup fees?
				$setup_fee = '';
				if ( $this->has_setup_fee( $blog_id, $level ) ) {
					$setup_fee = '<span class="psts-setup-fee">+ a one time ' . $this->format_currency( false, $setup_fee_amt ) . ' setup fee</span>';
				}

				if ( in_array( 3, $periods ) || in_array( 12, $periods ) ) {
					$equiv = '<span class="psts-equiv">' . __( 'Try it out!', 'psts' ) . '</span>
	                  <span class="psts-equiv">' . __( 'You can easily switch to a better value plan at any time.', 'psts' ) . '</span>';
				}
				$content .= '<td class="level-option" style="width: ' . $width . '"><div class="pblg-checkout-opt' . $current . $selected . '">
										<input type="hidden" value="' . $level . ':1"/>
										<input type="radio" name="psts-radio" class="psts-radio" id="psts-radio-1-' . $level . '" value="' . $level . ':1" />
										<label for="psts-radio-1-' . $level . '">
										' . $coupon_price . '
										' . $setup_fee . '
										' . $equiv . '
										</label>
										</div></td>';
			}

			if ( in_array( 3, $periods ) ) {
				$current       = ( $curr->term == 3 && $curr->level == $level ) ? ' opt-current' : '';
				$selected      = ( $sel_period == 3 && $sel_level == $level ) ? ' opt-selected' : '';
				$upgrade_price = ( $recurring ) ? $data['price_3'] : $this->calc_upgrade_cost( $blog_id, $level, 3, $data['price_3'] );

				$session_coupon = ProSites_Helper_Session::session( 'COUPON_CODE' );
				if ( isset( $session_coupon ) && $this->check_coupon( $session_coupon, $blog_id, $level, 3 ) && $coupon_value = $this->coupon_value( $session_coupon, $data['price_3'] ) ) {
					$coupon_price = '<span class="pblg-old-price">' . $this->format_currency( false, $data['price_3'] ) . '</span> <span class="pblg-price">' . $this->format_currency( false, $coupon_value['new_total'] ) . '</span>';
					$price        = $coupon_value['new_total'];
				} elseif ( $upgrade_price != $data['price_3'] ) {
					$coupon_price = '<span class="pblg-old-price">' . $this->format_currency( false, $data['price_3'] ) . '</span> <span class="pblg-price">' . $this->format_currency( false, $upgrade_price ) . '</span>';
					$price        = $upgrade_price;
				} else {
					$coupon_price = '<span class="pblg-price">' . $this->format_currency( false, $data['price_3'] ) . '</span>';
					$price        = $data['price_3'];
				}

				$equiv = '<span class="psts-equiv">' . sprintf( __( 'Equivalent to only %s monthly', 'psts' ), $this->format_currency( false, $price / 3 ) ) . '</span>';
				if ( in_array( 1, $periods ) && ( ( $data['price_1'] * 3 ) - $price ) > 0 ) {
					$equiv .= '<span class="psts-equiv">' . sprintf( __( 'Save %s by paying for 3 months in advance!', 'psts' ), $this->format_currency( false, ( $data['price_1'] * 3 ) - $price ) ) . '</span>';
				}

				$content .= '<td class="level-option" style="width: ' . $width . '"><div class="pblg-checkout-opt' . $current . $selected . '">
										<input type="hidden" value="' . $level . ':3"/>
										<input type="radio" name="psts-radio" class="psts-radio" id="psts-radio-3-' . $level . '" value="' . $level . ':3" />
										<label for="psts-radio-3-' . $level . '">
										' . $coupon_price . '
										' . $setup_fee . '
										' . $equiv . '
										</label>
										</div></td>';
			}

			if ( in_array( 12, $periods ) ) {
				$current       = ( $curr->term == 12 && $curr->level == $level ) ? ' opt-current' : '';
				$selected      = ( $sel_period == 12 && $sel_level == $level ) ? ' opt-selected' : '';
				$upgrade_price = ( $recurring ) ? $data['price_12'] : $this->calc_upgrade_cost( $blog_id, $level, 12, $data['price_12'] );

				$session_coupon = ProSites_Helper_Session::session( 'COUPON_CODE' );
				if ( isset( $session_coupon ) && $this->check_coupon( $session_coupon, $blog_id, $level, 12 ) && $coupon_value = $this->coupon_value( $session_coupon, $data['price_12'] ) ) {
					$coupon_price = '<span class="pblg-old-price">' . $this->format_currency( false, $data['price_12'] ) . '</span> <span class="pblg-price">' . $this->format_currency( false, $coupon_value['new_total'] ) . '</span>';
					$price        = $coupon_value['new_total'];
				} elseif ( $upgrade_price != $data['price_12'] ) {
					$coupon_price = '<span class="pblg-old-price">' . $this->format_currency( false, $data['price_12'] ) . '</span> <span class="pblg-price">' . $this->format_currency( false, $upgrade_price ) . '</span>';
					$price        = $upgrade_price;
				} else {
					$coupon_price = '<span class="pblg-price">' . $this->format_currency( false, $data['price_12'] ) . '</span>';
					$price        = $data['price_12'];
				}

				$equiv = '<span class="psts-equiv">' . sprintf( __( 'Equivalent to only %s monthly', 'psts' ), $this->format_currency( false, $price / 12 ) ) . '</span>';
				if ( in_array( 1, $periods ) && ( ( $data['price_1'] * 12 ) - $price ) > 0 ) {
					$equiv .= '<span class="psts-equiv">' . sprintf( __( 'Save %s by paying for a year in advance!', 'psts' ), $this->format_currency( false, ( $data['price_1'] * 12 ) - $price ) ) . '</span>';
				}

				$content .= '<td class="level-option" style="width: ' . $width . '"><div class="pblg-checkout-opt' . $current . $selected . '">
								<input type="hidden" value="' . $level . ':12"/>
								<input type="radio" name="psts-radio" class="psts-radio" id="psts-radio-12-' . $level . '" value="' . $level . ':12" />
								<label for="psts-radio-12-' . $level . '">
								' . $coupon_price . '
								' . $setup_fee . '
								' . $equiv . '
								</label>
								</div></td>';
			}

			$content .= '</tr>';
		}

		$content = apply_filters( 'psts_checkout_grid_before_free', $content, $blog_id, $periods, $free_width );
		// Displays a option for free level, member can continue trial without being redirected to checkout page every time

		if ( ! $this->prevent_dismiss() && ( ! empty ( $domain ) || get_blog_option( $blog_id, 'psts_signed_up' ) ) ) {

			$content .= '<tr class="psts_level level-free">
				<td valign="middle" class="level-name"><h3>' . $this->get_setting( 'free_name', __( 'Free', 'psts' ) ) . '</h3></td>';
			$content .= '<td class="level-option" colspan="' . count( $periods ) . '">';

			if ( is_user_logged_in() && empty ( $domain ) ) {

				$content .= '<a class="pblg-checkout-opt" style="width: ' . $free_width . '" id="psts-free-option" href="' . get_admin_url( $blog_id, 'index.php?psts_dismiss=1', 'http' ) . '" title="' . __( 'Dismiss', 'psts' ) . '">' . $this->get_setting( 'free_msg', __( 'No thank you, I will continue with a basic site for now', 'psts' ) ) . '</a>';

			} else {

				//Checkout With free trial blog
				$content .= '<div class="pblg-checkout-opt" style="width: ' . $free_width . '" id="psts-free-option">
								<input type="hidden" value="0:0"/>
								<input type="radio" name="psts-radio" class="psts-radio" id="psts-radio-0-0" value="0:0" />
								<label for="psts-radio-0-0">' . $this->get_setting( 'free_msg', __( 'No thank you, I will continue with a basic site for now', 'psts' ) ) . '</label>
							</div>';

			}
			$content .= '</td></tr>';
		}

		$content = apply_filters( 'psts_checkout_grid_after_free', $content, $blog_id, $periods, $free_width );

		$content .= '</table>
    						<input type="hidden" id="psts_period" name="period" value="' . $sel_period . '"/>
			      		<input type="hidden" id="psts_level" name="level" value="' . $sel_level . '"/>';

		//allow gateways to add accepted logos on the initial screen
		$content = apply_filters( 'psts_checkout_method_image', $content );

		//coupon form - if you want to hide the coupon box add define('PSTS_DISABLE_COUPON_FORM', true); to your wp-config.php file
		if ( ! ( defined( 'PSTS_DISABLE_COUPON_FORM' ) && PSTS_DISABLE_COUPON_FORM ) ) {
			$coupons = get_site_option( 'psts_coupons' );
			$session_coupon = ProSites_Helper_Session::session( 'COUPON_CODE' );
			if ( is_array( $coupons ) && count( $coupons ) && ! isset( $session_coupon ) ) {
				$content .= '<div id="psts-coupon-block">
		      <small><a id="psts-coupon-link" href="#">' . __( 'Have a coupon code?', 'psts' ) . '</a></small>
		      <div id="psts-coupon-code" class="alignright" style="display: none;">
		        <label for="coupon_code">' . __( 'Enter your code:', 'psts' ) . '</label>
		        <input type="text" name="coupon_code" id="coupon_code" class="cctext" />&nbsp;
		        <input type="submit" name="coupon-submit" class="regbutton" value="' . __( 'Apply &raquo;', 'psts' ) . '" />
		      </div>
		     </div>';
			}
		}

		//display checkout free trial/cancellation message
		$trial_days = $this->get_setting( 'trial_days', 0 );
		if ( $this->is_trial_allowed( $blog_id ) ) {
			$content .= '<p style="padding-top:24px">' . str_replace( 'DAYS', $trial_days, $this->get_setting( 'cancel_message' ) ) . '</p>';
		}

		return $content;
	}

	/**
	 * Checks if a given blog is allowed trial status
	 *
	 * @since 3.4.4
	 *
	 * @param int $blog_id
	 *
	 * @return bool
	 */

	function is_trial_allowed( $blog_id ) {

		$trial_days = $this->get_setting( 'trial_days', 0 );

		//If Trial is not set
		if ( $trial_days == 0 ) {
			return false;
		}

		// If blog exists
		if( ! empty( $blog_id ) ) {
			if ( is_pro_site( $blog_id ) && ! is_pro_trial( $blog_id ) ) {
				return false;
			}

			if ( $this->is_blog_canceled( $blog_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks if a given blog ID is in the pro sites table
	 *
	 * @since 3.4.4
	 *
	 * @param int $blog_id
	 *
	 * @return bool
	 */
	function is_existing( $blog_id ) {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(*)
			FROM {$wpdb->base_prefix}pro_sites
			WHERE blog_ID = %d
		", $blog_id ) );
	}

	function receipt_form( $blog_id, $domain = false ) {
		$content          = '';
		$last_transaction = ! empty( $blog_id ) ? $this->last_transaction( $blog_id ) : $this->last_transaction( '', $domain );

		if ( ! defined( 'PSTS_DISABLE_RECEIPT_FORM' ) && $last_transaction ) {

			if ( isset( $_POST['psts_receipt'] ) ) {
				$this->email_notification( $blog_id, 'receipt', $_POST['receipt_email'] );
				$content .= '<div class="psts-updated">' . sprintf( __( 'Email receipt sent to %s.', 'psts' ), esc_html( $_POST['receipt_email'] ) ) . '</div>';
			} else {
				$content .= '<p id="psts-receipt-block">
					<form action="' . $this->checkout_url( $blog_id ) . '" method="post" autocomplete="off">
					' . __( 'Email a receipt copy for your last payment to:', 'psts' ) . ' <span id="psts-receipt-change"><strong>' . get_blog_option( $blog_id, 'admin_email' ) . '</strong> <small><a href="#">(' . __( 'change', 'psts' ) . ')</a></small></span>
					<input type="text" id="psts-receipt-input" name="receipt_email" value="' . get_blog_option( $blog_id, 'admin_email' ) . '" style="display: none;" />
					<input type="submit" name="psts_receipt" class="regbutton" value="' . __( 'Send &raquo;', 'psts' ) . '" />
					</form></p>';
			}

		}

		return $content;
	}

	function checkout_trial_msg( $value, $blog_id ) {
		$trial_days = $this->get_setting( 'trial_days', 0 );
		if ( $this->is_trial_allowed( $blog_id ) ) {
			return $value . '<p class="helper-message">' . sprintf( __( 'Your <strong>%d day trial</strong> begins once you click the "Subscribe" button below.', 'psts' ), $trial_days ) . '</p>';
		}
	}

	//outputs the checkout form
	function checkout_output( $content ) {
		global $wpdb;
		$has_blog = false;
		//make sure we are in the loop and on current page loop item
		if ( ! in_the_loop() || get_queried_object_id() != get_the_ID() ) {
			return $content;
		}

		//make sure logged in, Or if user comes just after signup, check session for domain name
		$session_data = ProSites_Helper_Session::session( 'new_blog_details' );
		// Get the registration settings of network.
		$registration = get_site_option('registration');

		if( ! is_user_logged_in() || ( ProSites_Helper_ProSite::allow_new_blog() && isset( $_GET['action'] ) && 'new_blog' == $_GET['action'] ) || isset( $_POST['level'] ) || ! empty( $session_data['username'] ) )  {

			$show_signup = $this->get_setting( 'show_signup' );
			$show_signup = 'all' == $registration ? $show_signup : false;

			if( ! is_user_logged_in() && ! $show_signup ) {
				$content .= '<p>' . __( 'You must first login before you can choose a site to upgrade:', 'psts' ) . '</p>';
				$content .= wp_login_form( array( 'echo' => false ) );
				return $content;
			}
			$content = apply_filters( 'psts_primary_checkout_table', $content, '' );

			return $content;
		}
		$current_user_id = get_current_user_id();
		//get allowed roles for checkout
		$checkout_roles = $this->get_setting( 'checkout_roles', array( 'administrator', 'editor' ) );

		//set blog_id
		if (isset($_POST['bid'])){
			$blog_id = intval($_POST['bid']);
		}else if (isset($_GET['bid'])){
			$blog_id = intval($_GET['bid']);
		}else{
			$blog_id = false;

			$blogs_of_user = get_blogs_of_user(get_current_user_id(), false);
			$blogs = array();

			$count = 0;
			$loop_count = 0;
			$per_page = 10;

			$start = isset($_REQUEST['blogs-start'])?intval($_REQUEST['blogs-start']):0;
			$next_start = 0;
			$prev_start = -1;

			foreach ($blogs_of_user as $id => $obj) {
				if ($count >= $per_page) {break;}

				$loop_count++;
				if ($start > $loop_count) {continue;}

				// permission?
				switch_to_blog($id);
				$permission = current_user_can('edit_pages');
				if ($permission) {
					$obj->level = $this->get_level($obj->userblog_id);
					$obj->level_label = ($obj->level) ? $this->get_level_setting($obj->level, 'name') : sprintf(__('Not %s', 'psts'), $this->get_setting('rebrand'));
					$obj->upgrade_label = is_pro_site($obj->userblog_id) ? sprintf(__('Modify "%s"', 'psts'), $obj->blogname) : sprintf(__('Upgrade "%s"', 'psts'), $obj->blogname);
					$obj->checkout_url = $this->checkout_url($obj->userblog_id);

					$blogs[$id] = $obj;
					$count++;
				}
				restore_current_blog();
			}

			$next_start = $loop_count + 1;

			$prev_loop_count = $loop_count;
			if ( count($blogs_of_user) > $next_start ) {
				$prev_loop_count++;
			} else {
				end($blogs_of_user);
			}

			// reverse
			while ( prev($blogs_of_user) ) {
				$prev_loop_count--;
				if ($prev_loop_count == $start) {break;}
			}

			$prev_count = 0;
			// reverse to previous start
			while ( $obj = prev($blogs_of_user) ) {
				$prev_loop_count--;
				if ($prev_loop_count < 0) {break;}
				if ($prev_count >= $per_page) {
					$prev_start = $prev_loop_count;
					break;
				}
				$id = key($blogs_of_user);
				switch_to_blog($id);
				$permission = current_user_can('edit_pages');
				if ($permission) {
					$prev_count++;
				}
				$prev_count;
				restore_current_blog();
			}

			if ( $prev_start > 0 ) {
				$prev_start = $prev_loop_count + 1;
			}

			// user has edit permission for one blog, load checkout page
			global $current_prosite_blog;
			$current_prosite_blog = false;
			if( count($blogs)==1 ) {
				$all_blog_ids = array_keys($blogs);
				$blog_id = intval($all_blog_ids[0]);
				$current_prosite_blog = $blog_id;
			}
		}

		//Check if multiple signups are allowed
		$allow_multi = $this->get_setting('multiple_signup');
		$allow_multi = 'all' == $registration || 'blog' == $registration ? $allow_multi : false;

		if ( $blog_id ) {

			//check for admin permissions for this blog
			switch_to_blog( $blog_id );

			$permission = $this->check_user_role( $current_user_id, $checkout_roles );
			restore_current_blog();
			if ( ! $permission ) {
				$content = '<p>' . __( 'Sorry, but you do not have permission to upgrade this site. Only the site administrator can upgrade their site.', 'psts' ) . '</p>';
				$content .= '<p><a href="' . $this->checkout_url() . '">&laquo; ' . __( 'Choose a different site', 'psts' ) . '</a></p>';

				return $content;
			}

			if ( $this->get_expire( $blog_id ) > 2147483647 ) {
				$level   = $this->get_level_setting( $this->get_level( $blog_id ), 'name' );
				$content = '<p>' . sprintf( __( 'This site has been permanently given %s status.', 'psts' ), $level ) . '</p>';
				$content .= '<p><a href="' . $this->checkout_url() . '">&laquo; ' . __( 'Choose a different site', 'psts' ) . '</a></p>';

				return $content;
			}

			if( $allow_multi ) {
				$content .= '<div class="psts-signup-another"><a href="' . esc_url( $this->checkout_url() . '?action=new_blog' ) . '">' . esc_html__( 'Sign up for another site.', 'psts' ) . '</a>' . '</div>';
			}

			//this is the main hook for new checkout page
			$content = apply_filters( 'psts_primary_checkout_table', $content, $blog_id );

		} elseif ( $session_domain = ProSites_Helper_Session::session( 'domain' ) ) {
			//this is the main hook for new checkout page
			$content = apply_filters( 'psts_primary_checkout_table', $content, '', $session_domain );
		} else { //blogid not set
			$blog_id = 0;
			if ( $blogs ) {
				$content .= '<h3>' . __( 'Please choose a site to Upgrade or Modify:', 'psts' ) . '</h3>';
				$content .= '<ul>';

				foreach ( $blogs as $blog ) {
					$has_blog = true;

					$level         = $this->get_level( $blog->userblog_id );

					/**
					 * @todo Check to make sure there variables are used or removed.
					 */
					$level_label   = ( $level ) ? $this->get_level_setting( $level, 'name' ) : sprintf( __( 'Not %s', 'psts' ), $this->get_setting( 'rebrand' ) );
					$upgrade_label = is_pro_site( $blog->userblog_id ) ? sprintf( __( 'Modify "%s"', 'psts' ), $blog->blogname ) : sprintf( __( 'Upgrade "%s"', 'psts' ), $blog->blogname );
					if( empty( $blog_id ) && is_pro_site( $blog->userblog_id ) ) {
						$blog_id = $blog->userblog_id;
					}

					$content .= '<li><a href="' . $blog->checkout_url . '">' . $blog->upgrade_label . '</a> (<em>' . $blog->siteurl . '</em>) - ' . $blog->level_label . '</li>';
				}
				$content .= '</ul>';

				$content .= '<div id="post-navigator">';
				if ( $prev_start >= 0 ) {
					$content .= '<div class="alignleft"><a href="' . add_query_arg( array( 'blogs-start' => $prev_start ), get_permalink() ) . '">Previous</a></div>';
				}
				if ( count( $blogs_of_user ) > $next_start ) {
					$content .= '<div class="alignright"><a href="' . add_query_arg( array( 'blogs-start' => $next_start ), get_permalink() ) . '">Next</a></div>';
				}
				$content .= '</div>';
				$content .= apply_filters( 'prosites_myaccounts_list', '', $blog_id );

			}

			//show message if no valid blogs
			$session_domain = ProSites_Helper_Session::session( 'domain' );

			//Check if user has signed up for a site already
			$current_user = wp_get_current_user();
			$current_user = !empty( $current_user->data ) ? $current_user->data : '';
			$user_login = !empty( $current_user->user_login ) ? $current_user->user_login : '';

			if( !empty( $user_login ) ) {
				//Query Signup table for domain name
				$query = $wpdb->prepare("SELECT `domain`, `active` from {$wpdb->signups} WHERE `user_login` = %s", $user_login );
				$site = $wpdb->get_row( $query );
				$user_domain = !empty( $site->domain ) ? $site->domain : false;
			}

			if( !empty( $user_domain ) && $allow_multi ) {
				//Already have a site, allow to signup for another
				$inactive_site = !empty( $site->active ) && 1 == $site->active ? '': sprintf( __('Your site <strong>%s</strong> has not been activated yet.', 'psts' ), $user_domain ). '<br/>';
				$content .= '<div class="psts-signup-another">' . $inactive_site . '<a href="' . esc_url( $this->checkout_url() . '?action=new_blog' ) . '">' . esc_html__( 'Sign up for another site.', 'psts' ) . '</a>' . '</div>';
			}elseif( empty( $user_domain ) ) {
				//Don't have a site, let user create one
				$content .= '<div class="psts-signup"><a href="' . esc_url( $this->checkout_url() . '?action=new_blog' ) . '">' . esc_html__( 'Sign up for a site.', 'psts' ) . '</a>' . '</div>';
			}elseif ( ! $has_blog && ! isset( $session_domain ) ) {
				$content .= '<strong>' . __( 'Sorry, but it appears you are not an administrator for any sites.', 'psts' ) . '</strong>';
			}
		}

		return '<div id="psts-checkout-output">' . $content . '</div>'; //div wrap
	}

	/* exclude option from New Site Template plugin copy */
	function blog_template_settings( $and ) {
		$and .= " AND `option_name` != 'psts_signed_up' AND `option_name` != 'psts_action_log' AND `option_name` != 'psts_waiting_step' AND `option_name` != 'psts_payments_log' AND `option_name` != 'psts_used_coupons' AND `option_name` != 'psts_paypal_profile_id' AND `option_name` != 'psts_stripe_canceled' AND `option_name` != 'psts_withdrawn'";

		return $and;
	}

	function pdf_receipt( $payment_info = '' ) {

		require_once( $this->plugin_dir . 'tcpdf-config.php' );
		require_once( $this->plugin_dir . 'tcpdf/config/lang/eng.php' );
		require_once( $this->plugin_dir . 'tcpdf/tcpdf.php' );

		//Make directory for receipt cache
		if ( ! is_dir( K_PATH_CACHE ) ) {
			mkdir( K_PATH_CACHE, 0755, true );
		}
		if ( ! is_writable( K_PATH_CACHE ) ) {
			chmod( K_PATH_CACHE, 0755 );
		}

		//Clean out old cache files
		foreach ( glob( K_PATH_CACHE . '*.pdf' ) as $fname ) {
			$age = time() - filemtime( $fname );
			if ( ( $age > 12 * 60 * 60 ) && ( basename( $fname ) != 'index.php' ) ) { //Don't erase our blocking index.php file
				unlink( $fname ); // more than 12 hours old;
			}
		}

		// create new PDF document
		$pdf = new TCPDF( PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false );

		// Note: If uncommenting below, please remove previous call.
		// Can use the following to change language symbols to appropriate standard, e.g. ISO-638-2 languages.
		// $pdf = new TCPDF( PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, false, 'ISO-639-2', false );

		// set document information
		$pdf->SetCreator( 'Pro Sites' );
		$pdf->SetTitle( __( 'Payment Receipt', 'psts' ) );
		$pdf->SetKeywords( '' );

		// remove default header/footer
		$pdf->setPrintHeader( false );
		$pdf->setPrintFooter( false );

		// set default monospaced font
		$pdf->SetDefaultMonospacedFont( PDF_FONT_MONOSPACED );

		//set margins
		$pdf->SetMargins( PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT );
		$pdf->SetHeaderMargin( PDF_MARGIN_HEADER );
		$pdf->SetFooterMargin( PDF_MARGIN_FOOTER );

		//set auto page breaks
		$pdf->SetAutoPageBreak( true, PDF_MARGIN_BOTTOM );

		//set image scale factor
		$pdf->setImageScale( PDF_IMAGE_SCALE_RATIO );

		//set some language-dependent strings
		global $l;
		$pdf->setLanguageArray( $l );

		// ---------------------------------------------------------

		// set font
		$pdf->SetFont( 'helvetica', '', 14 );

		// add a page
		$pdf->AddPage();

		$html = '';

		$img = $this->get_setting( 'receipt_image' );

		if ( ! empty( $img ) ) {
			$html .= '
			<div><img src="' . $img . '" /><div>
			';
		}

		$html .= make_clickable( wpautop( $payment_info ) );

		try{
		// output the HTML content
		$pdf->writeHTML( $html, true, false, true, false, '' );
		}catch (Exception $e ) {
			error_log( "TCPDF couldn't write HTML to PDF" . $e->get_error_message() );
			return '';
		}

		// ---------------------------------------------------------

		global $blog_id;

		$sitename = sanitize_title( get_blog_option( $blog_id, 'blogname' ) );

		$uid = uniqid( "{$sitename}-{$blog_id}-" );

		$fname = K_PATH_CACHE . "{$uid}.pdf";
		ob_clean();
		try{
			//Close and output PDF document
			$pdf->Output( $fname, 'F' );
			$attachments[] = $fname;

			return $attachments;
		}catch( Exception $e ) {
			error_log("Exception while Outputing PDF receipt: " . $e->getMessage() );
		}

		return '';
	}

	/**
	 * Create the Checkout Page Settings
	 *
	 * @since 3.3.7
	 *
	 * @return settings page for Pro Sites Checkout Page
	 */
	function pricing_table_settings() {
		if ( ! is_super_admin() ) {
			echo "<p>" . __( 'Nice Try...', 'psts' ) . "</p>"; //If accessed properly, this message doesn't appear.
			return;
		}

		global $wpdb, $psts_modules;

		if ( strtolower( $_SERVER['REQUEST_METHOD'] ) == 'post' ) {
			check_admin_referer( 'psts_checkout_settings' );
			$_POST['psts'] = stripslashes_deep( $_POST['psts'] );
			$old_settings  = get_site_option( 'psts_settings' );
			$settings      = array_merge( $old_settings, $_POST['psts'] );
			update_site_option( 'psts_settings', $settings );
			echo '<div id="message" class="updated fade"><p>' . __( 'Settings Saved!', 'psts' ) . '</p></div>';
		}
		include_once $this->plugin_dir . 'lib/psts_pricing_table_admin.php';
		echo apply_filters( 'psts_checkout_page_settings_output', new ProSites_Pricing_Table_Admin() );
	}

	/**
	 * Checks if a particular user role is allowed to perform Pro Sites management
	 *
	 * @param $user_id
	 * @param $roles
	 *
	 * @return bool
	 */
	function check_user_role( $user_id, $roles ) {

		if ( is_numeric( $user_id ) ) {
			$user = get_userdata( $user_id );
		} else {
			$user = wp_get_current_user();
		}

		if ( empty( $user ) ) {
			return false;
		}

		if ( ! $roles ) {
			return false;
		}
		if ( is_array( $roles ) ) {
			foreach ( $roles as $role ) {
				if ( in_array( $role, (array) $user->roles ) ) {
					return true;
				}
			}
		} else {
			return in_array( $roles, (array) $user->roles );
		}
	}

	/**
	 * Enables the Network Used space check for multisite, if quota module is enabled
	 */
	function enable_network_used_space_check() {

		//Check if quota module is enabled
		$modules_enabled = $this->get_setting( 'modules_enabled', array() );

		if ( ! in_array( 'ProSites_Module_Quota', $modules_enabled ) ) {
			return;
		}

		$enable = apply_filters( 'psts_enable_used_space_check', true );
		if ( $enable ) {
			update_site_option( 'upload_space_check_disabled', '0' );
		}
	}

	/**
	 * Disables the blog activation email if Forced checkout on signup is selected
	 * @return bool
	 */
	function disable_user_activation_mail() {

		//If pay before blog is disabled, allow blog activation through email
		$show_signup = $this->get_setting( 'show_signup' );

		if ( 1 != $show_signup && ! class_exists( 'BuddyPress' ) ) {
			return true;
		}

		/* Wordpress do not provide option to filter confirm_blog_signup, we have disabled activation email */
		ob_start();

		return false;
	}

	/**
	 * Redirect user to checkout page after signup
	 *
	 * @param type $blog_id
	 * @param type $user_id
	 * @param type $domain
	 * @param type $path
	 * @param type $site_id
	 * @param type $meta
	 */
	function signup_redirect_checkout() {
		global $wpdb;

		//If pay before blog is disabled, allow blog activation through email
		$show_signup = $this->get_setting( 'show_signup' );

		if ( 1 != $show_signup ) {
			return;
		}

		if ( ( empty( $_POST['signup_blog_url'] ) && empty( $_POST['blogname'] ) ) ||
		     ! isset( $_POST['psts_signed_up'] ) || $_POST['psts_signed_up'] != 'yes'
		) {
			//No post details to check
			return;
		}

		/* Remove confirmation text between filter and action, this could be removed if confirm_blog_signup gets a filter */
		ob_get_clean();

		$blogname  = ! empty( $_POST['blogname'] ) ? $_POST['blogname'] : ( ! empty( $_POST['signup_blog_url'] ) ? $_POST['signup_blog_url'] : '' );
		$blogtitle = ! empty( $_POST['blog_title'] ) ? $_POST['blog_title'] : ( ! empty( $_POST['signup_blog_title'] ) ? $_POST['signup_blog_title'] : '' );

		$blog_details = wpmu_validate_blog_signup( $blogname, $blogtitle );

		if ( empty( $blog_details ['domain'] ) ) {
			return;
		}
		$domain = $blog_details['domain'];
		//Check if blog is in trial or inactive, set session values and redirect to checkout page
		$signup = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE domain = %s", $domain ) );
		if ( ! $signup->active ) {
			ProSites_Helper_Session::session( 'domain', $domain );
			ProSites_Helper_Session::session( 'meta', $signup->meta );
			?>
			<!--redirect to checkout url-->
			<script type="text/javascript">
				window.location = '<?php echo $this->checkout_url(); ?>';
			</script><?php
		}

		return;

	}

	/**
	 * Activates the user blog if a domain is specified and if the blog is not already active
	 *
	 * @param bool $domain
	 * @param bool $trial
	 * @param bool $period
	 * @param bool $level
	 *
	 * @return bool
	 */
	function activate_user_blog( $domain = false, $trial = true, $period = false, $level = false ) {
		global $wpdb, $path;

		$trial_days = $this->get_setting( 'trial_days', 0 );
		if ( ! $domain ) {
			return false;
		}

		//Get activation key from db
		$signup         = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE domain = %s", $domain ) );
		$activation_key = ! empty( $signup->activation_key ) ? $signup->activation_key : '';
		if ( ! $activation_key || $signup->active ) {
			//get blog id
			$fields = array(
				'domain' => $domain,
				'path'   => '/'
			);
			$blog   = get_blog_details( $fields );

			return ! empty( $blog->blog_id ) ? $blog->blog_id : false;
		}
		$result = wpmu_activate_signup( $activation_key );

		if ( empty( $result['user_id'] ) ) {
			return false;
		}
		//Get user login by user id
		$user = get_user_by( 'id', $result['user_id'] );

		if ( empty( $user ) || is_wp_error( $user ) ) {
			return false;
		}

		//Login user to follow up the rest of Pro Site process
		$creds = array(
			'user_login'    => $user->user_login,
			'user_password' => $result['password']
		);
		$user  = wp_signon( $creds, true );
		wp_set_current_user( $user->ID );

		//Set Trial
		if ( $trial ) {
			$this->extend( $result['blog_id'], $period, 'trial', $level, '', strtotime( '+ ' . $trial_days . ' days' ) );

			//Redirect to checkout on next signup
			update_blog_option( $result['blog_id'], 'psts_signed_up', 1 );
		}

		// Unset Domain name from session if its still there
		ProSites_Helper_Session::unset_session( 'domain' );

		if ( isset( $result['blog_id'] ) ) {
			return $result['blog_id'];
		} else {
			return false;
		}
	}

	/**
	 * Fetches signup meta for a domain
	 *
	 * @param string $key
	 *
	 * @return mixed|string, meta value from signup table if there is a associated domain
	 */
	function get_signup_meta( $key = '' ) {
		if ( ! $key ) {
			return false;
		}
		global $wpdb;
		$signup_meta = '';
		$signup      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE activation_key = %s", $key ) );
		if ( ! empty( $signup ) ) {
			$signup_meta = maybe_unserialize( $signup->meta );
		}

		return $signup_meta;
	}

	/**
	 * Updates signup meta for a domain
	 *
	 * @param array $signup_meta
	 *
	 * @param string $key
	 *
	 * @return mixed|string, meta value from signup table if there is a associated domain
	 */
	function update_signup_meta( $signup_meta = array(), $key = '' ) {
		if ( ! $signup_meta || ! $key ) {
			return false;
		}
		global $wpdb;

		$updated = $wpdb->update(
			$wpdb->signups,
			array(
				'meta' => serialize( $signup_meta ), // string
			),
			array(
				'activation_key' => $key
			)
		);

		return $updated;
	}

	/**
	 * Fetches meta for a ProSite
	 *
	 * @param int $blog_id
	 * @param bool $default
	 *
	 * @return bool|mixed|string
	 */
	public static function get_prosite_meta( $blog_id = 0 ) {
		return ProSites_Helper_ProSite::get_prosite_meta( $blog_id );
	}

	/**
	 * Sets meta for a ProSite
	 *
	 * @param array $meta
	 * @param int $blog_id
	 *
	 * @return bool
	 */
	public static function update_prosite_meta( $blog_id = 0, $meta = array() ) {
		return ProSites_Helper_ProSite::update_prosite_meta( $blog_id, $meta );
	}

	/**
	 * Add Custom messages in admin footer
	 *
	 */
	function psts_note() {
		global $current_screen;
		//Add for sites screen
		if ( is_main_network() && 'sites-network' == $current_screen->base ) {
			?>
			<p><strong>&#42 </strong>
			=> <?php _e( "The original Level doesn't exist, it might have been removed.", 'psts' ); ?></p><?php
		}
	}

	/**
	 * Allows to disable the free level option
	 *
	 * @param bool , default false
	 */
	function prevent_dismiss() {
		return apply_filters( 'psts_prevent_dismiss', false );
	}

	function help_text( $message = '', $class = 'period-desc' ) {
		if( empty( $message ) ){
			return false;
		}
		return '<img width="16" height="16" src="' . $this->plugin_url . 'images/help.png" class="help_tip"><div class="psts-help-text-wrapper ' . $class . '"><div class="psts-help-arrow-wrapper"><div class="psts-help-arrow"></div></div><div class="psts-help-text">' . $message . '</div></div>';
	}

	/**
	 * Displays a error on plugin activation
	 *
*@param $message
	 * @param $errno
	 */
	function trigger_install_error( $message, $errno ) {

		if ( isset( $_GET['action'] ) && $_GET['action'] == 'error_scrape' ) {

			echo '<strong>' . $message . '</strong>';

			exit;

		} else {

			trigger_error( $message, $errno );

		}

	}

	public static function filter_html( $content ) {
		$allowed_atts = array(
		'align'    => array(),
       'class'    => array(),
       'id'       => array(),
       'dir'      => array(),
       'lang'     => array(),
       'style'    => array(),
       'xml:lang' => array(),
       'src'      => array(),
       'alt'      => array(),
       'value' =>array(),
       'selected' =>array(),
       'name'=>array(),
       'checked'=>array(),
       );
		$allowed = array(
			'span' => $allowed_atts,
			'div' => $allowed_atts,
			'button' => $allowed_atts,
			'select' => $allowed_atts,
			'input' => $allowed_atts,
			'option' => $allowed_atts,
			'br' => $allowed_atts,
			'hr' => $allowed_atts,
			'h1' => $allowed_atts,
			'h2' => $allowed_atts,
			'h3' => $allowed_atts,
			'h4' => $allowed_atts,
			'h5' => $allowed_atts,
			'h6' => $allowed_atts,
			'strong' => $allowed_atts,
			'em' => $allowed_atts,
			'b' => $allowed_atts,
			'i' => $allowed_atts,
			'style' => $allowed_atts,
		);

		return wp_kses( $content, $allowed );
	}

	function registration_page_styles() {
		if( 'wp-signup.php' == $GLOBALS['pagenow'] ) {

			// On the signup page, but only if it comes from the checkout
			if( defined( 'PSTS_DISABLE_REGISTRATION_OVERRIDE' ) ) {
				return false;
			}

			if( ( ! isset( $_GET['level']) && ! isset( $_GET['period'] ) ) && ( ! isset( $_POST['level']) && ! isset( $_POST['period'] ) ) ) {
				return false;
			}

			// Now we can hack the display specifically for ProSites.
			wp_enqueue_style( 'psts-registration', $this->plugin_url . 'css/registration.css', false, $this->version );
		}
	}

	function prosites_signup_url( $url ) {
		// Do a test here...
		if ( true ) {
//			$url = sprintf( '<a href="%s">%s</a>', esc_url( $this->checkout_url() ), __( 'Register' ) );
		}

		return $url;
	}

	function redirect_buddypress_signup( $location ) {
		$location = '';
		$location = bp_get_root_domain() . '/wp-signup.php';
		return $location;
	}
	/**
    * Checks for Blog activation, if website was signed up using manual payment gateway
    * and assigns the pro site level as per the details in site meta
	*
*@param $blog_id
	* @param $user_id
	* @param $password
	* @param $signup_title
	* @param $meta
	 */
	function process_manual_signup( $blog_id, $user_id, $password, $signup_title, $meta ) {
		//If meta value is not set, return
		if( empty( $meta ) || empty( $blog_id ) || empty( $meta['pro_site_manual_signup']) ) {
			return;
		}

		$manual_signup = $meta['pro_site_manual_signup'];
		$level     = !empty( $manual_signup ) ? $manual_signup['level'] : '';
		$period    = !empty( $manual_signup ) ? $manual_signup['period'] : '';
		$gateway   = !empty( $manual_signup ) ? $manual_signup['gateway'] : '';
		$amount    = !empty( $manual_signup ) ? $manual_signup['amount'] : '';
		$recurring = !empty( $manual_signup ) ? $manual_signup['recurring'] : false;

		if( empty( $level ) || empty( $period ) ) {
			return;
		}
		//Check meta
		$this->extend( $blog_id, $period, $gateway, $level, $amount, false, $recurring );
		$this->record_transaction( $blog_id, 'manual', $amount );

		//Update password, because a new one is generated during wpmu_activate_signup().
		wp_set_password( $password, $user_id );
	}

	/**
    * Check if new blog creation is allowed or not, Show/Hide create new link
    *
	* @param $value
	*
	*@return string
	 */
	function hide_create_new_site_link( $value ) {
		global $current_screen, $psts;
		//List of screens, where we don't interfere
		$return_original = array(
			'settings-network'
		);
		if( !empty( $current_screen ) && in_array( $current_screen->base, $return_original ) ) {
			return $value;
		}

		$allow_new_blog = ProSites_Helper_ProSite::allow_new_blog();

		//Check if multiple signups are allowed for blog, return the value in that case
		if( $allow_new_blog ) {
			return $value;
		}else{
			return 'user';
		}
	}
	/**
    * Try to fetch the latest subscription detail from the respective Gateway
    * to check if the blog is expired
    *
	* @param string $blog_id
    *
	* @return string|null Expiry Date
    */
	function get_subscription_details( $blog_id = '' ) {
		$expiry = '';
		if( empty( $blog_id ) ) {
			return $expiry;
		}
		$gateway = ProSites_Helper_ProSite::get_site_gateway( $blog_id );
		if( 'stripe' == $gateway ) {
			$expiry = ProSites_Gateway_Stripe::get_blog_subscription_expiry( $blog_id );
		}elseif( 'paypal' == $gateway ) {
			$expiry = ProSites_Gateway_PayPalExpressPro::get_blog_subscription_expiry( $blog_id );
		}

		return $expiry;
	}

    public function delete_blog( $blog_id ) {
        global $wpdb;
        $main_site = defined( 'BLOG_ID_CURRENT_SITE' ) ? BLOG_ID_CURRENT_SITE : 1;
        switch_to_blog( $main_site );
        $wpdb->query( "DELETE from {$wpdb->prefix}pro_sites where blog_id='$blog_id'" );
        restore_current_blog();
    }

}

//End of class
//load the class
global $psts;
$psts = new ProSites();
// Load Gateway Currencies
ProSites_Helper_Gateway::load_gateway_currencies();

define ( "MONTHLY", 1 );
define ( "QUARTERLY", 3 );
define ( "YEARLY", 12 );

/* --------------------------------------------------------------------- */
/* ---------------------------- Functions ------------------------------ */
/* --------------------------------------------------------------------- */

/**
 * Check if a given site is Pro or at a given Pro level
 *
 * @since 3.0
 *
 * @param int $blog_id optional - The ID of the site to check. Defaults to current blog.
 * @param int $level optional - Check if site is at this level or below. If ommited checks if at any level.
 *
 * @return bool
 */
function is_pro_site( $blog_id = false, $level = false ) {
	global $psts;

	return $psts->is_pro_site( $blog_id, $level );
}

/**
 * Check if a given user is a member of a Pro site (at any level)
 *
 * @since 3.0
 *
 * @param int $user_id optional - The ID of the user to check. Defaults to current user.
 *
 * @return bool
 */
function is_pro_user( $user_id = false ) {
	global $psts;

	return $psts->is_pro_user( $user_id );
}

/**
 * Check if a given site is in an active trial
 *
 * @since 3.0
 *
 * @param int $blog_id required - The ID of the site to check.
 *
 * @return bool
 */
function is_pro_trial( $blog_id ) {
	global $psts;

	return $psts->is_trial( $blog_id );
}

/*
 * function psts_levels_select
 * Print an html select field to choose level for an external plugin
 *
 * @param string $name Name of the form field
 * @param int $selected the level number to select by default
 *
 * @return echo html select
 */
function psts_levels_select( $name, $selected, $echo = true ) {
	global $psts;
	$psts->levels_select( $name, $selected, $echo );
}

//depreciated!
function is_supporter( $blog_id = false ) {
	return is_pro_site( $blog_id, apply_filters( 'psts_supporter_level', false ) );
}

//depreciated!
function is_supporter_user( $user_id = '' ) {
	return is_pro_user( $user_id );
}

//depreciated!
function supporter_feature_notice() {
	global $psts;
	$psts->feature_notice();
}

//depreciated!
function supporter_get_expire( $blog_id = false ) {
	global $psts;

	return $psts->get_expire( $blog_id );
}

function psts_text_body($phpmailer) {
	$phpmailer->AltBody = strip_tags($phpmailer->Body);
}
