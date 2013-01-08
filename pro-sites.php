<?php
/*
Plugin Name: Pro Sites (Formerly Supporter)
Plugin URI: http://premium.wpmudev.org/project/pro-sites
Description: The ultimate multisite site upgrade plugin, turn regular sites into multiple pro site subscription levels selling access to storage space, premium themes, premium plugins and much more!
Author: Aaron Edwards (Incsub)
Version: 3.3.4
Author URI: http://premium.wpmudev.org
Text Domain: psts
Domain Path: /pro-sites-files/languages/
Network: true
WDP ID: 49
*/

/*
Copyright 2007-2012 Incsub (http://incsub.com)

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

  var $version = '3.3.4';
  var $location;
  var $language;
  var $plugin_dir = '';
  var $plugin_url = '';
  var $pro_sites = array();
  var $level = array();
	var $checkout_processed = false;

  function ProSites() {
    $this->__construct();
  }

  function __construct() {
    //setup our variables
    $this->init_vars();

    //install plugin
    register_activation_hook( __FILE__, array($this, 'install') );
		
		//load dashboard notice
    include_once( $this->plugin_dir . 'dash-notice/wpmudev-dash-notification.php' );
		
    //load plugins
    require_once( $this->plugin_dir . 'plugins-loader.php' );

    //localize
		add_action( 'plugins_loaded', array(&$this, 'localization') );

		//admin page stuff
		add_action( 'network_admin_menu', array(&$this, 'plug_network_pages') );
		add_action( 'admin_menu', array(&$this, 'plug_pages') );
		add_action( 'admin_bar_menu', array(&$this, 'add_menu_admin_bar'), 100);
		add_action( 'wp_head', array(&$this, 'add_menu_admin_bar_css') );
		add_action( 'admin_head', array(&$this, 'add_menu_admin_bar_css') );
		add_filter( 'wpmu_blogs_columns', array(&$this, 'add_column') );
		add_action( 'manage_sites_custom_column', array(&$this, 'add_column_field'), 1, 3 );

		add_action( 'init', array(&$this, 'check') );
		add_action( 'load-toplevel_page_psts-checkout', array(&$this, 'redirect_checkout') );
		add_action( 'admin_init', array(&$this, 'signup_redirect'), 100 ); //delay to make sure it is last hook to admin_init

		//trials
		add_action( 'wpmu_new_blog', array(&$this, 'trial_extend') );
		add_action( 'admin_notices', array(&$this, 'trial_notice'), 2 );

		add_action( 'pre_get_posts', array(&$this, 'checkout_page_load') );

		//handle signup pages
		add_action( 'signup_blogform', array(&$this, 'signup_output') );
		add_action( 'bp_after_blog_details_fields', array(&$this, 'signup_output') );
		add_action( 'signup_extra_fields', array(&$this, 'signup_override') );
		add_filter( 'add_signup_meta', array(&$this, 'signup_save') );
		add_filter( 'bp_signup_usermeta', array(&$this, 'signup_save') );


		add_action( 'psts_process_stats', array(&$this, 'process_stats') ); //cronjob hook
		add_filter( 'blog_template_exclude_settings', array(&$this, 'blog_template_settings') ); // exclude pro site setting from blog template copies

		//update install script if necessary
		if ($this->get_setting('version') != $this->version) {
			$this->install();
		}

  }

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

  function localization() {
    // Load up the localization file if we're using WordPress in a different language
  	// Place it in this plugin's "languages" folder and name it "psts-[value in wp-config].mo"
    if ($this->location == 'plugins')
      load_plugin_textdomain( 'psts', false, '/pro-sites/pro-sites-files/languages/' );
    else if ($this->location == 'mu-plugins')
      load_muplugin_textdomain( 'psts', '/pro-sites-files/languages/' );

    //setup language code for jquery datepicker translation
    $temp_locales = explode('_', get_locale());
  	$this->language = ($temp_locales[0]) ? $temp_locales[0] : 'en';
  }

  function init_vars() {
    //setup proper directories
    if (defined('WP_PLUGIN_URL') && defined('WP_PLUGIN_DIR') && file_exists(WP_PLUGIN_DIR . '/pro-sites/' . basename(__FILE__))) {
      $this->location = 'plugins';
      $this->plugin_dir = WP_PLUGIN_DIR . '/pro-sites/pro-sites-files/';
      $this->plugin_url = plugins_url( '/pro-sites-files/', __FILE__ );
  	} else if (defined('WPMU_PLUGIN_URL') && defined('WPMU_PLUGIN_DIR') && file_exists(WPMU_PLUGIN_DIR . '/' . basename(__FILE__))) {
      $this->location = 'mu-plugins';
      $this->plugin_dir = WPMU_PLUGIN_DIR . '/pro-sites-files/';
      $this->plugin_url = WPMU_PLUGIN_URL . '/pro-sites-files/';
  	} else {
      wp_die(__('There was an issue determining where Pro Sites is installed. Please reinstall.', 'psts'));
    }

    //load data structures
		require_once( $this->plugin_dir . 'data.php' );
  }


	function install() {
		global $wpdb, $current_site;

		//rename tables if upgrading from old supporter
		if (get_site_option("supporter_installed") == "yes") {
			$wpdb->query("RENAME TABLE `{$wpdb->base_prefix}supporters` TO `{$wpdb->base_prefix}pro_sites`");
			$wpdb->query("RENAME TABLE `{$wpdb->base_prefix}supporter_signup_stats` TO `{$wpdb->base_prefix}pro_sites_signup_stats`");
			$wpdb->query("RENAME TABLE `{$wpdb->base_prefix}supporter_daily_stats` TO `{$wpdb->base_prefix}pro_sites_daily_stats`");
			delete_site_option( "supporter_installed" );
		}

		$table1 = "CREATE TABLE {$wpdb->base_prefix}pro_sites (
		  blog_ID bigint(20) NOT NULL,
		  level int(3) NOT NULL DEFAULT 1,
		  expire bigint(20) NOT NULL,
		  gateway varchar(25) NULL DEFAULT 'PayPal',
		  term varchar(25) NULL DEFAULT NULL,
		  amount varchar(10) NULL DEFAULT NULL,
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
		
		if ( !defined('DO_NOT_UPGRADE_GLOBAL_TABLES') ) {
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($table1);
			dbDelta($table2);
			dbDelta($table3);
		}
		
    // add stats cron job action only to main site (or it may be running all the time!)
		switch_to_blog($current_site->blog_id);
		if ( !wp_next_scheduled('psts_process_stats')) {
		  //get end of day
    	$time = strtotime( date("Y-m-d 23:50:00") );
			wp_schedule_event($time, 'daily', 'psts_process_stats');
		}
		restore_current_blog();

		//our default settings
    $default_settings = array (
      'base_country' => 'US',
      'currency' => 'USD',
      'curr_symbol_position' => 1,
      'curr_decimal' => 1,
      'rebrand' => __('Pro Site', 'psts'),
      'lbl_signup' => __('Pro Upgrade', 'psts'),
      'lbl_curr' => __('Your Account', 'psts'),
      'gateways_enabled' => array( 'ProSites_Gateway_PayPalExpressPro' ),
			'modules_enabled' => array(),
			'enabled_periods' => array(1,3,12),
			'hide_adminbar' => 0,
			'hide_adminbar_super' => 0,
			'free_name' => __('Free', 'psts'),
			'free_msg' => __('No thank you, I will continue with a basic site for now', 'psts'),
			'trial_level' => 1,
			'trial_days' => get_site_option("supporter_free_days"),
      'trial_message' => __('You have DAYS days left in your LEVEL free trial. Checkout now to prevent losing LEVEL features &raquo;', 'psts'),
			'ga_ecommerce' => 'none',
			'signup_message' => __('Would you like to upgrade this site to Pro?', 'psts'),
			'feature_message' => __('Upgrade to LEVEL to access this feature &raquo;', 'psts'),
      'active_message' => __('Your Pro Site privileges will expire on: DATE<br />Unless you have canceled your subscription or your site was upgraded via the Bulk Upgrades tool, your Pro Site privileges will automatically be renewed.', 'psts'),
      'success_subject' => __('Thank you for becoming a Pro Site member!', 'psts'),
      'success_msg' => __("Thank you for becoming a Pro Site member!

We have received your first subscription payment and you can now access all LEVEL features!

Subscription payments should show on your credit card or bank statement as \"THIS COMPANY\". If you ever need to view, modify, upgrade, or cancel your Pro Site subscription you can do so here:
http://mysite.com/pro-site/

If you ever have any billing questions please contact us:
http://mysite.com/contact/

Thanks again for joining!", 'psts'),
      'canceled_subject' => __('Your Pro Site subscription has been canceled', 'psts'),
      'canceled_msg' => __("Your Pro Site subscription has been canceled.

You should continue to have access until ENDDATE.

We are very sorry to see you go, but we are looking forward to you subscribing to our services again.

You can resubscribe at any time here:
http://mysite.com/pro-site/

Thanks!", 'psts'),
      'receipt_subject' => __('Your Pro Site payment receipt', 'psts'),
      'receipt_msg' => __("Your Pro Site subscription payment was successful!

PAYMENTINFO

Subscription payments should show on your credit card or bank statement as \"YOUR COMPANY\". If you ever need to view, modify, upgrade, or cancel your Pro Site subscription you can do so here:
http://mysite.com/pro-site/

If you ever have any billing questions please contact us:
http://mysite.com/contact/

Thanks again for being a valued member!", 'psts'),
      'failed_subject' => __('Your Pro Site subscription payment failed', 'psts'),
      'failed_msg' => __("It seems like there is a problem with your latest Pro Site subscription payment, sorry about that.

Please update your payment information or change your payment method as soon as possible to avoid a lapse in Pro Site features:
http://mysite.com/pro-site/

If you're still having billing problems please contact us for help:
http://mysite.com/contact/

Many thanks again for being a member!", 'psts'),
			'pypl_site' => 'US',
			'pypl_currency' => 'USD',
			'pypl_status' => 'test',
			'pypl_enable_pro' => 0,
			'stripe_ssl' => 0,
			'mp_name' => __('Manual Payment', 'psts'),
			'pt_name' => __('Premium Themes', 'psts'),
   		'pt_text' => __('Upgrade to LEVEL to activate this premium theme &raquo;', 'psts'),
   		'ps_level' => 1,
   		'ps_email' => get_site_option("admin_email"),
   		'ps_name' => __('Premium Support', 'psts'),
   		'ps_message' => __('You can send us a priority direct email support request here if you need help with your site.', 'psts'),
   		'ps_notice' => __('To enable premium support, please upgrade to LEVEL &raquo;', 'psts'),
   		'publishing_level' => 1,
   		'publishing_message_posts' => __('To enable publishing posts, please upgrade to LEVEL &raquo;', 'psts'),
   		'publishing_message_pages' => __('To enable publishing pages, please upgrade to LEVEL &raquo;', 'psts'),
   		'quota_message' => __('For SPACE of upload space, upgrade to LEVEL!', 'psts'),
   		'quota_out_message' => __('You are out of upload space! Please upgrade to LEVEL to enable SPACE of storage space.', 'psts'),
   		'xmlrpc_level' => 1,
   		'xmlrpc_message' => __('To enable XML-RPC remote publishing please upgrade to LEVEL &raquo;', 'psts'),
   		'bp_notice' => __('Upgrade to LEVEL to access this feature &raquo;', 'psts'),
   		'pp_name' => __('Premium Plugins', 'psts'),
   		'ads_name' => __('Disable Ads', 'psts'),
   		'ads_level' => 1,
   		'ads_enable_blogs' => 0,
   		'ads_count' => 3,
   		'ads_before_page' => 0,
   		'ads_after_page' => 0,
   		'ads_before_post' => 0,
   		'ads_after_post' => 0,
   		'ads_themes' => 0,
   		'bu_email' => get_site_option("supporter_paypal_email"),
   		'bu_status' => 'test',
   		'bu_payment_type' => 'recurring',
   		'bu_level' => 1,
   		'bu_credits_1' => 10,
   		'bu_option_msg' => __('Upgrade CREDITS sites to LEVEL for one year for only PRICE:', 'psts'),
   		'bu_checkout_msg' => __('You can upgrade multiple sites at a lower cost by purchasing Pro Site credits below. After purchasing your credits just come back to this page, search for your sites via the tool at the bottom of the page, and upgrade them to Pro Site status. Each site is upgraded for one year.', 'psts'),
   		'bu_payment_msg' => __('Depending on your payment method it may take just a few minutes (Credit Card or PayPal funds) or it may take several days (eCheck) for your Pro Site credits to become available.', 'psts'),
   		'bu_name' => __('Bulk Upgrades', 'psts'),
			'bu_link_msg' => __('Purchase credits to upgrade multiple sites for one discounted price!', 'psts'),
			'ptb_front_disable' => 1,
   		'ptb_front_msg' => __('This site is temporarily disabled until payment is received. Please check back later.', 'psts'),
   		'ptb_checkout_msg' => __('You must pay to enable your site.', 'psts'),
			'pq_level' => 1,
			'pq_quotas' => array('post' => array('quota' => 'unlimited'), 'page' => array('quota' => 'unlimited')),
			'uh_level' => 1,
			'uh_message' => __('To enable the embedding html, please upgrade to LEVEL &raquo;', 'psts')
    );
    $settings = wp_parse_args( (array)get_site_option('psts_settings'), $default_settings );
    update_site_option( 'psts_settings', $settings );

		//default level
		$default_levels = array (
		  1 => array(
				'name' => __('Pro', 'psts'),
				'price_1' => get_site_option("supporter_1_whole_cost").'.'.get_site_option("supporter_1_partial_cost"),
				'price_3' => get_site_option("supporter_3_whole_cost").'.'.get_site_option("supporter_3_partial_cost"),
				'price_12' => get_site_option("supporter_12_whole_cost").'.'.get_site_option("supporter_12_partial_cost")
			)
		);
		if (!get_site_option('psts_levels'))
    	add_site_option( 'psts_levels', $default_levels );

		//create a checkout page if not existing
		add_action( 'init', array(&$this, 'create_checkout_page') );

		$this->update_setting('version', $this->version);
	}

	//an easy way to get to our settings array without undefined indexes
	function get_setting($key, $default = null) {
    $settings = get_site_option( 'psts_settings' );
    $setting = isset($settings[$key]) ? $settings[$key] : $default;
		return apply_filters( "psts_setting_$key", $setting, $default );
	}

	function update_setting($key, $value) {
    $settings = get_site_option( 'psts_settings' );
    $settings[$key] = $value;
		return update_site_option('psts_settings', $settings);
	}

	function get_level_setting($level, $key, $default = null) {
    $levels = (array)get_site_option( 'psts_levels' );
    return isset($levels[$level][$key]) ? $levels[$level][$key] : $default;
	}

	function update_level_setting($level, $key, $value) {
    $levels = (array)get_site_option( 'psts_levels' );
    $levels[$level][$key] = $value;
		return update_site_option('psts_levels', $levels);
	}

	function trial_extend($blog_id) {
		$trial_days = $this->get_setting('trial_days');
		if ( $trial_days > 0 ) {
			$extend = $trial_days * 86400;
			$this->extend($blog_id, $extend, 'Trial', $this->get_setting('trial_level', 1));
		}
	}

	function trial_notice() {
		global $wpdb, $blog_id;
		if ( !is_main_site() && current_user_can('edit_pages') && $this->get_setting('trial_days') ) {
			$expire = $wpdb->get_var("SELECT expire FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id' AND gateway = 'Trial' AND expire >= '" . time() . "' LIMIT 1");
			if ($expire) {
				$days = round( ( $expire - time() ) / 86400 ); //calculate days left rounded
				$notice = str_replace( 'LEVEL', $this->get_level_setting($this->get_setting('trial_level', 1), 'name'), $this->get_setting('trial_message') );
				$notice = str_replace( 'DAYS', $days, $notice );
				echo '
					<div class="update-nag">
						<a href="' . $this->checkout_url($blog_id) . '">' . $notice . '</a>
					</div>';
			}
		}
	}
	
	function is_trial($blog_id) {
		global $wpdb;
		return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id' AND gateway = 'Trial' AND expire >= '" . time() . "' LIMIT 1");
	}
	
	//run daily via wp_cron
	function process_stats() {
	  global $wpdb;

	  $date = date("Y-m-d", time());

	  //don't process if already completed today (in case wp_cron goes nutzy)
	  $existing = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_daily_stats WHERE date = '" . $date . "'");
	  if ($existing)
	    return;

	  $active_pro_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE expire > '" . time() . "'");
	  $expired_pro_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE expire <= '" . time() . "'");
	  $term_1_pro_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term = 1 AND expire > '" . time() . "'");
	  $term_3_pro_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term = 3 AND expire > '" . time() . "'");
	  $term_12_pro_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term = 12 AND expire > '" . time() . "'");
	  $term_manual_pro_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term NOT IN (1,3,12) AND expire > '" . time() . "'");

		//get level counts
    $levels = get_site_option('psts_levels');
    for ($i=1; $i<=10; $i++) $level_count[$i] = 0; //prefill the array
		if (is_array($levels) && count($levels) > 1) {
			foreach ($levels as $level => $data) {
				//if last level include all previous ones greater than that level, in case a level was deleted
				if (count($levels) == $level)
					$level_count[$level] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE level >= $level AND expire > '" . time() . "'");
				else
          $level_count[$level] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE level = $level AND expire > '" . time() . "'");
			}
		} else {
			$level_count[1] = $active_pro_sites;
		}

	  $wpdb->query( "INSERT INTO {$wpdb->base_prefix}pro_sites_daily_stats ( date, supporter_count, expired_count, term_count_1, term_count_3, term_count_12, term_count_manual, level_count_1, level_count_2, level_count_3, level_count_4, level_count_5, level_count_6, level_count_7, level_count_8, level_count_9, level_count_10 ) VALUES ( '$date', $active_pro_sites, $expired_pro_sites, $term_1_pro_sites, $term_3_pro_sites, $term_12_pro_sites, $term_manual_pro_sites, {$level_count[1]}, {$level_count[2]}, {$level_count[3]}, {$level_count[4]}, {$level_count[5]}, {$level_count[6]}, {$level_count[7]}, {$level_count[8]}, {$level_count[9]}, {$level_count[10]} )" );
	}

	/*
	Used for stats, must be called by payment gateways
	--------------------------------------------------
	Parameters:
	$blog_id = blog's id
	$action = "signup", "cancel", "modify", "upgrade"
	*/
	function record_stat($blog_id, $action) {
		global $wpdb;
		//only record one stat action per blog per day
		$exists = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE blog_ID = %s AND action = %s AND time_stamp = %s", $blog_id, $action, date('Y-m-d')) );
		if ( !$exists ) {
      $wpdb->insert( "{$wpdb->base_prefix}pro_sites_signup_stats", array( 'blog_ID' => $blog_id, 'action' => $action, 'time_stamp' => date('Y-m-d') ), array( '%d', '%s', '%s' ) );
		}
	}

  //returns html of a weekly summary
	function weekly_summary() {
	  global $wpdb;

    $img_base = $this->plugin_url. 'images/';

	  //count total
    $current_total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE expire > '" . time() . "'");

	  $date = date("Y-m-d", strtotime( "-1 week" ) );
	  $last_total = $wpdb->get_var("SELECT supporter_count FROM {$wpdb->base_prefix}pro_sites_daily_stats WHERE date >= '$date' ORDER BY date ASC LIMIT 1");

	  if ($current_total > $last_total) {
	    $active_diff = "<img src='{$img_base}green-up.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n($current_total-$last_total) . "</span>";
	  } else if ($current_total < $last_total) {
	    $active_diff = "<img src='{$img_base}red-down.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n(-($current_total-$last_total)) . "</span>";
	  } else {
	    $active_diff = "<span style='font-size: 18px; font-family: arial;'>" . __('no change', 'psts') . "</span>";
	  }

	  $text = sprintf(__('%s active Pro Sites %s since last week', 'psts'), "<p><span style='font-size: 24px; font-family: arial;'>" . number_format_i18n($current_total) . "</span><span style='color: rgb(85, 85, 85);'>", "</span>$active_diff<span style='color: rgb(85, 85, 85);'>") . "</span></p>";

	  //activity stats
	  $week_start = strtotime( "-1 week" );
    $week_start_date = date('Y-m-d', $week_start);
	  $this_week['total_signups'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'signup' AND time_stamp >= '$week_start_date'");
	  $this_week['upgrades'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'upgrade' AND time_stamp >= '$week_start_date'");
	  $this_week['cancels'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'cancel' AND time_stamp >= '$week_start_date'");

	  $week_end = $week_start;
	  $week_start = strtotime( "-1 week", $week_start );
    $week_start_date = date('Y-m-d', $week_start);
    $week_end_date = date('Y-m-d', $week_end);
	  $last_week['total_signups'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'signup' AND time_stamp >= '$week_start_date' AND time_stamp < '$week_end_date'");
	  $last_week['upgrades'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'upgrade' AND time_stamp >= '$week_start_date' AND time_stamp < '$week_end_date'");
	  $last_week['cancels'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'cancel' AND time_stamp >= '$week_start_date' AND time_stamp < '$week_end_date'");

	  if ($this_week['total_signups'] > $last_week['total_signups']) {
	    $diff = "<img src='{$img_base}green-up.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n($this_week['total_signups']-$last_week['total_signups']) . "</span>";
	  } else if ($this_week['total_signups'] < $last_week['total_signups']) {
	    $diff = "<img src='{$img_base}red-down.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n(-($this_week['total_signups']-$last_week['total_signups'])) . "</span>";
	  } else {
	    $diff = "<span style='font-size: 18px; font-family: arial;'>" . __('no change', 'psts') . "</span>";
	  }

    $text .= sprintf(__('%s new signups this week %s compared to last week', 'psts'), "\n<p><span style='font-size: 24px; font-family: arial;'>" . number_format_i18n($this_week['total_signups']) . "</span><span style='color: rgb(85, 85, 85);'>", "</span>$diff<span style='color: rgb(85, 85, 85);'>") . "</span></p>";

	  if ($this_week['upgrades'] > $last_week['upgrades']) {
	    $diff = "<img src='{$img_base}green-up.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n($this_week['upgrades']-$last_week['upgrades']) . "</span>";
	  } else if ($this_week['upgrades'] < $last_week['upgrades']) {
	    $diff = "<img src='{$img_base}red-down.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n(-($this_week['upgrades']-$last_week['upgrades'])) . "</span>";
	  } else {
	    $diff = "<span style='font-size: 18px; font-family: arial;'>" . __('no change', 'psts') . "</span>";
	  }

		$text .= sprintf(__('%s upgrades this week %s compared to last week', 'psts'), "\n<p><span style='font-size: 24px; font-family: arial;'>" . number_format_i18n($this_week['upgrades']) . "</span><span style='color: rgb(85, 85, 85);'>", "</span>$diff<span style='color: rgb(85, 85, 85);'>") . "</span></p>";

	  if ($this_week['cancels'] > $last_week['cancels']) {
	    $diff = "<img src='{$img_base}red-up.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n($this_week['cancels']-$last_week['cancels']) . "</span>";
	  } else if ($this_week['cancels'] < $last_week['cancels']) {
	    $diff = "<img src='{$img_base}green-down.gif'><span style='font-size: 18px; font-family: arial;'>" . number_format_i18n(-($this_week['cancels']-$last_week['cancels'])) . "</span>";
	  } else {
	    $diff = "<span style='font-size: 18px; font-family: arial;'>" . __('no change', 'psts') . "</span>";
	  }

	  $text .= sprintf(__('%s cancelations this week %s compared to last week', 'psts'), "\n<p><span style='font-size: 24px; font-family: arial;'>" . number_format_i18n($this_week['cancels']) . "</span><span style='color: rgb(85, 85, 85);'>", "</span>$diff<span style='color: rgb(85, 85, 85);'>") . "</span></p>";

	  return $text;
	}

	function plug_network_pages() {
		global $psts_plugin_loader;

		//main page
		$page = add_menu_page( __('Pro Sites', 'psts'), __('Pro Sites', 'psts'), 'manage_network_options', 'psts', array(&$this, 'admin_modify'), $this->plugin_url . 'images/plus.png' );
		$page = add_submenu_page( 'psts', __('Manage Sites', 'psts'), __('Manage Sites', 'psts'), 'manage_network_options', 'psts', array(&$this, 'admin_modify') );
		
    do_action('psts_page_after_main');

		//stats page
    $page = add_submenu_page( 'psts', __('Pro Sites Statistics', 'psts'), __('Statistics', 'psts'), 'manage_network_options', 'psts-stats', array(&$this, 'admin_stats') );
		add_action('admin_print_scripts-' . $page, array(&$this, 'scripts_stats'));

    do_action('psts_page_after_stats');

		//coupons page
    $page = add_submenu_page( 'psts', __('Pro Sites Coupons', 'psts'), __('Coupons', 'psts'), 'manage_network_options', 'psts-coupons', array(&$this, 'admin_coupons') );
		add_action('admin_print_scripts-' . $page, array(&$this, 'scripts_coupons'));
    add_action('admin_print_styles-' . $page, array(&$this, 'css_coupons') );

    do_action('psts_page_after_coupons');

		//levels page
    $page = add_submenu_page( 'psts', __('Pro Sites Levels', 'psts'), __('Levels', 'psts'), 'manage_network_options', 'psts-levels', array(&$this, 'admin_levels') );

		do_action('psts_page_after_levels');

		//modules page
    $page = add_submenu_page( 'psts', __('Pro Sites Modules & Gateways', 'psts'), __('Modules/Gateways', 'psts'), 'manage_network_options', 'psts-modules', array(&$this, 'admin_modules') );

    do_action('psts_page_after_modules');

		//settings page
		$page = add_submenu_page( 'psts', __('Pro Sites Settings', 'psts'), __('Settings', 'psts'), 'manage_network_options', 'psts-settings', array(&$this, 'admin_settings') );

  	do_action('psts_page_after_settings');
	}

	function plug_pages() {
		if ( !is_main_site() ) {
			$label = is_pro_site() ? $this->get_setting('lbl_curr') : $this->get_setting('lbl_signup');
			add_menu_page($label, $label, 'edit_pages', 'psts-checkout', array(&$this, 'checkout_redirect_page'), $this->plugin_url . 'images/plus.png', 3);
		}
	}
	
	function add_menu_admin_bar_css() {

    if ( is_main_site() || !is_admin_bar_showing() || !is_user_logged_in() || $this->get_setting('hide_adminbar') )
        return;
		
		//styles the upgrade button
		?><style type="text/css">#wpadminbar li#wp-admin-bar-pro-site {float:right;}#wpadminbar li#wp-admin-bar-pro-site a{padding-top:3px !important;height:25px !important;border-right:1px solid #333 !important;}#wpadminbar li#wp-admin-bar-pro-site a span{display:block;color:#fff;font-weight:bold;font-size:11px;margin:0px 1px 0px 1px;padding:0 30px !important;border:1px solid #409ed0 !important;height:18px !important;line-height:18px !important;border-radius:4px;-moz-border-radius:4px;-webkit-border-radius:4px;background-image:-moz-linear-gradient( bottom, #3b85ad, #419ece ) !important;background-image:-ms-linear-gradient( bottom, #3b85ad, #419ece ) !important;background-image:-webkit-gradient( linear, left bottom, left top, from( #3b85ad ), to( #419ece ) ) !important;background-image:-webkit-linear-gradient( bottom, #419ece, #3b85ad ) !important;background-image:linear-gradient( bottom, #3b85ad, #419ece ) !important;}#wpadminbar li#wp-admin-bar-pro-site a:hover span{background-image:-moz-linear-gradient( bottom, #0B93C5, #3b85ad ) !important;background-image:-ms-linear-gradient( bottom, #0B93C5, #3b85ad ) !important;background-image:-webkit-gradient( linear, left bottom, left top, from( #0B93C5 ), to( #3b85ad ) ) !important;background-image:-webkit-linear-gradient( bottom, #3b85ad, #0B93C5 ) !important;background-image:linear-gradient( bottom, #0B93C5, #3b85ad ) !important;border:1px solid #3b85ad !important;color:#E8F3F8;}</style><?php
	}
	
	function add_menu_admin_bar() {
    global $wp_admin_bar, $blog_id, $wp_version;

    if ( is_main_site() || !is_admin_bar_showing() || !is_user_logged_in() )
        return;
		
		//add user admin bar upgrade button
		if ( !$this->get_setting('hide_adminbar') ) {
			if ( current_user_can('edit_pages') ) {
				$checkout = $this->checkout_url($blog_id);
			} else {
				$checkout = $this->checkout_url();
			}
	
			$label = is_pro_site() ? $this->get_setting('lbl_curr') : $this->get_setting('lbl_signup');
			$label = '<span>' . esc_attr($label) . '</span>';
				
			$wp_admin_bar->add_menu( array( 'id' => 'pro-site', 'parent' => (version_compare($wp_version, '3.3', '>=') ? 'top-secondary' : false), 'title' => $label, 'href' => $checkout ) );
		}
		
		//add superadmin status menu
		if ( is_super_admin() && !$this->get_setting('hide_adminbar_super') ) {
			$sup_title = is_pro_site() ? $this->get_level_setting($this->get_level($blog_id), 'name') : false;
			if (!$sup_title) {
				$sup_title = (function_exists('psts_hide_ads') && psts_hide_ads($blog_id))
					? __('Upgraded', 'psts')
					: __('Free', 'psts')
				;
			}
			$expire = $this->get_expire($blog_id);
			if ($expire > 2147483647)
				$expire = __("Permanent", "psts");
			else
				$expire = $expire ? date("Y-m-d", $expire) : __("N/A", "psts");
			$sup_title .= " [{$expire}]";
			$wp_admin_bar->add_menu(array(
				'title' => $sup_title,
				'href' => network_admin_url('admin.php?page=psts&bid=' . $blog_id),
				'parent' => false,
				'id' => 'psts-status'
			));
		}
	}
	
	function checkout_url($blog_id = false) {
	  global $current_site;

	  $url = $this->get_setting('checkout_url');
		
		/*
	  //just in case the checkout page was not created do it now
	  if (!$url) {
	    $this->create_checkout_page();
	    $url = $this->get_setting('checkout_url');
	  }
		*/
		
		//change to ssl if required
    if ( apply_filters('psts_force_ssl', false) ) {
      $url = str_replace('http://', 'https://', $url);
		}

		if ($blog_id)
		  $url .= '?bid=' . $blog_id;

	  return $url;
	}

	function redirect_checkout() {
	  global $blog_id;
		wp_redirect($this->checkout_url($blog_id));
	}

	//creates the checkout page on install and updates
	function create_checkout_page() {
		global $current_site;

	  //insert new page if not existing
	  switch_to_blog( $current_site->blog_id );
		$page = get_post( $this->get_setting('checkout_page') );
		if ( !$page || $page->post_status == 'trashed' ) {
	    $id = wp_insert_post( array('post_title' => $this->get_setting('rebrand'), 'post_status' => 'publish', 'post_type' => 'page', 'comment_status' => 'closed', 'ping_status' => 'closed', 'post_content' => stripslashes(get_site_option('supporter_message'))) );
			$this->update_setting('checkout_page', $id);
			$this->update_setting('checkout_url', get_permalink($id));
	  } else {
			$this->update_setting('checkout_url', get_permalink($this->get_setting('checkout_page')));
		}
	  restore_current_blog();
	}

	function checkout_page_load() {
	  //don't check on other blogs
	  if ( !is_main_site() )
	    return;
		
		//prevent weird redo when theme has multiple query loops
		if ($this->checkout_processed)
			return;
		
    //check if on checkout page
	  if (!$this->get_setting('checkout_page') || get_queried_object_id() != $this->get_setting('checkout_page'))
	    return;

	  //force ssl on the checkout page if required by gateway
	  if ( apply_filters('psts_force_ssl', false) && !is_ssl() ) {
			wp_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
			exit();
	  }

	  //make sure session is started
	  if (session_id() == "")
	  	session_start();
		
		//passed all checks, flip one time flag
		$this->checkout_processed = true;
		
		//remove all filters except shortcodes and checkout form
		remove_all_filters('the_content');
		add_filter('the_content', 'do_shortcode');
    add_filter('the_content', array(&$this, 'checkout_output'), 15);

    wp_enqueue_script('psts-checkout', $this->plugin_url . 'js/checkout.js', array('jquery'), $this->version );
    if ( !current_theme_supports( 'psts_style' ) )
			wp_enqueue_style('psts-checkout', $this->plugin_url . 'css/checkout.css', false, $this->version );

		//setup error var
		$this->errors = new WP_Error();

		//set blog_id
		if (isset($_POST['bid']))
		  $blog_id = intval($_POST['bid']);
		else if (isset($_GET['bid']))
		  $blog_id = intval($_GET['bid']);
    else
		  $blog_id = false;

		if ($blog_id) {

      add_filter( 'the_title', array(&$this, 'page_title_output'), 99, 2 );
			add_filter( 'bp_page_title', array(&$this, 'page_title_output'), 99, 2 );

		  //clear coupon if link clicked
		  if (isset($_GET['remove_coupon']))
		    unset($_SESSION['COUPON_CODE']);

		  //check for coupon session variable
		  if (isset($_SESSION['COUPON_CODE'])) {
			  if ( $this->check_coupon($_SESSION['COUPON_CODE'], $blog_id, intval(@$_POST['level'])) ) {
			    $coupon = true;
			  } else {
			    if (isset($_POST['level']) && is_numeric($_POST['level'])) {
			    	$this->errors->add('coupon', __('Sorry, the coupon code you entered is not valid for your chosen level.', 'psts'));
					} else {
					  $this->errors->add('coupon', __('Whoops! The coupon code you entered is not valid.', 'psts'));
			      unset($_SESSION['COUPON_CODE']);
					}
				}
			}

		  if (isset($_POST['coupon-submit'])) {
		    $code = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($_POST['coupon_code']));
		    $coupon = $this->check_coupon($code, $blog_id);
		    if ($coupon) {
		      $_SESSION['COUPON_CODE'] = $code;
		      $this->log_action( $blog_id, __("User added a valid coupon to their order on the checkout page:", 'psts') . ' ' . $code );
		    } else {
		      $this->errors->add('coupon', __('Whoops! The coupon code you entered is not valid.', 'psts'));
		      $this->log_action( $blog_id, __("User attempted to add an invalid coupon to their order on the checkout page:", 'psts') . ' ' . $code );
		    }
		  }
					
			do_action('psts_checkout_page_load', $blog_id); //for gateway plugins to hook into
		} else {
			//code for unique coupon links
			if (isset($_GET['coupon'])) {
		    $code = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($_GET['coupon']));
		    if ($this->check_coupon($code))
		      $_SESSION['COUPON_CODE'] = $code;
			}
		}
	}

	function check() {
	  global $blog_id;
		if ( is_pro_site($blog_id) ) {
			do_action('psts_active');
		} else {
			do_action('psts_inactive');

			//fire hooks on first encounter
			if (get_option('psts_withdrawn') === '0')
	      $this->withdraw($blog_id);
		}
	}

	//sends email notification to the user
	function email_notification($blog_id, $action, $email = false) {
	  global $wpdb;
		
		if (!$email)
			$email = get_blog_option($blog_id, 'admin_email');
		
	  if ($action == 'success') {

      $message = str_replace( 'LEVEL', $this->get_level_setting($this->get_level($blog_id), 'name'), $this->get_setting('success_msg') );
			$message = str_replace( 'SITEURL', get_site_url( $blog_id ), $message );
			$message = str_replace( 'SITENAME', get_blog_option($blog_id, 'blogname'), $message );

	    wp_mail( $email, $this->get_setting('success_subject'), $message );

	    $this->log_action( $blog_id, sprintf(__('Signup success email sent to %s', 'psts'), $email) );

	  } else if ($action == 'receipt') {
			//grab default payment info
      $result = $wpdb->get_row("SELECT * FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'");
      if ($result->term == 1 || $result->term == 3 || $result->term == 12)
        $term = sprintf(__('Every %s Month(s)', 'psts'), $result->term);
      else
        $term = $result->term;

      if ($result->gateway)
        $payment_info .= sprintf(__('Payment Method: %s', 'psts'), $result->gateway)."\n";
      if ($term)
      	$payment_info .= sprintf(__('Payment Term: %s', 'psts'), $term)."\n";
      $payment_info .= sprintf(__('Payment Amount: %s', 'psts'), $result->amount . ' ' . $this->get_setting('currency'))."\n";

	    $message = str_replace( 'PAYMENTINFO', apply_filters('psts_payment_info', $payment_info, $blog_id), $this->get_setting('receipt_msg') );
      $message = str_replace( 'LEVEL', $this->get_level_setting($this->get_level($blog_id), 'name'), $message );
			$message = str_replace( 'SITEURL', get_site_url( $blog_id ), $message );
			$message = str_replace( 'SITENAME', get_blog_option($blog_id, 'blogname'), $message );

	    wp_mail( $email, $this->get_setting('receipt_subject'), $message );

      $this->log_action( $blog_id, sprintf(__('Payment receipt email sent to %s', 'psts'), $email) );

	  } else if ($action == 'canceled') {

	    //get end date from expiration
	    $end_date = date_i18n(get_blog_option($blog_id, 'date_format'), $this->get_expire($blog_id));

	    $message = str_replace( 'ENDDATE', $end_date, $this->get_setting('canceled_msg') );
	    $message = str_replace( 'LEVEL', $this->get_level_setting($this->get_level($blog_id), 'name'), $message );
			$message = str_replace( 'SITEURL', get_site_url( $blog_id ), $message );
			$message = str_replace( 'SITENAME', get_blog_option($blog_id, 'blogname'), $message );

	    wp_mail( $email, $this->get_setting('canceled_subject'), $message );

      $this->log_action( $blog_id, sprintf(__('Subscription canceled email sent to %s', 'psts'), $email) );

	  } else if ($action == 'failed') {

	    $message = str_replace( 'LEVEL', $this->get_level_setting($this->get_level($blog_id), 'name'), $this->get_setting('failed_msg') );
			$message = str_replace( 'SITEURL', get_site_url( $blog_id ), $message );
			$message = str_replace( 'SITENAME', get_blog_option($blog_id, 'blogname'), $message );
			wp_mail( $email, $this->get_setting('failed_subject'), $this->get_setting('failed_msg') );

	    $this->log_action( $blog_id, sprintf(__('Payment failed email sent to %s', 'psts'), $email) );

	  }
	}

  //log blog actions for an audit trail
	function log_action($blog_id, $note) {
	  //grab data
	  $log = get_blog_option($blog_id, 'psts_action_log');

	  if (!is_array($log))
	    $log = array();

	  //append
	  $timestamp = microtime(true);

		//make sure timestamp is unique by padding seconds, or they will be overwritten
	  while (isset($log[$timestamp]))
	    $timestamp += 0.0001;

	  $log[$timestamp] = $note;

	  //save
	  update_blog_option($blog_id, 'psts_action_log', $log);
	}

	//record last payment
	function record_transaction($blog_id, $txn_id, $amt) {
	  $trans_meta = get_blog_option($blog_id, 'psts_payments_log');

	  $trans_meta[$txn_id]['txn_id'] = $txn_id;
	  $trans_meta[$txn_id]['timestamp'] = time();
	  $trans_meta[$txn_id]['amount'] = $amt;
	  $trans_meta[$txn_id]['refunded'] = false;
	  update_blog_option($blog_id, 'psts_payments_log', $trans_meta);
	}

	//record payment refund
	function record_refund_transaction($blog_id, $txn_id, $refunded) {
	  $trans_meta = get_blog_option($blog_id, 'psts_payments_log');

		if ( isset($trans_meta[$txn_id]) ) {
			//add to previous refund if there was one
		  if ($trans_meta[$txn_id]['refunded'])
		    $refunded = $refunded + $trans_meta[$txn_id]['refunded'];

      $trans_meta[$txn_id]['refunded'] = $refunded;
			update_blog_option($blog_id, 'psts_payments_log', $trans_meta);
		}
	}

	//get last transaction details
	function last_transaction($blog_id) {
	  $trans_meta = get_blog_option($blog_id, 'psts_payments_log');

	  if ( is_array( $trans_meta ) ) {
	    return array_pop( $trans_meta );
	  } else {
	    return false;
	  }
	}

	function is_pro_site($blog_id = false, $level = false) {
		global $wpdb, $current_site;

		if ( !$blog_id ) {
			$blog_id = $wpdb->blogid;
		}
    $blog_id = intval($blog_id);
		
		// Allow plugins to short-circuit
		$pro = apply_filters( 'is_pro_site', null, $blog_id );
		if ( !is_null($pro) )
			return $pro;
		
		//check cache first
		if ( $level ) { //level is passed, check level
			if ($level == 0) {
				return true;
			} else if ( isset( $this->pro_sites[$blog_id][$level] ) && is_bool( $this->pro_sites[$blog_id][$level] ) ) {
				return $this->pro_sites[$blog_id][$level];
			}
		} else { //any level will do
      if ( isset( $this->pro_sites[$blog_id] ) && is_array( $this->pro_sites[$blog_id] ) ) {
				foreach ($this->pro_sites[$blog_id] as $key => $value) {
					if ($value) return true;
				}
			}
		}

		//check if main site
		if ( is_main_site($blog_id) ) {
			return true;
	  } else { //finally go to DB
			$now = time();
   		$data = $wpdb->get_row("SELECT expire, level FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'");
			if (is_object($data)) {
				if ($level) {
					if ( $data->expire && $data->expire > $now && $level <= $data->level ) {
	        	for ($i = 1; $i <= $data->level; $i++)
			        $this->pro_sites[$blog_id][$i] = true; //update cache
						return true;
					} else {
					  $levels = (array)get_site_option('psts_levels');
	        	for ($i = $level; $i <= count($levels); $i++)
			        $this->pro_sites[$blog_id][$i] = false; //update cache
						return false;
					}
				} else { //any level will do
	        if ( $data->expire && $data->expire > $now ) {
	        	for ($i = 1; $i <= $data->level; $i++)
			        $this->pro_sites[$blog_id][$i] = true; //update cache
						return true;
					} else {
	        	for ($i = 1; $i <= $data->level; $i++)
			        $this->pro_sites[$blog_id][$i] = false; //update cache
						return false;
					}
				}
			} else {
      	for ($i = 1; $i <= $level; $i++)
	        $this->pro_sites[$blog_id][$i] = false; //update cache
				return false;
			}
		}
	}


	/*
	Useful in plugins to test users. Checks if any of the blogs they are a member of
	are supporter blogs, which works but is resource intensive and a bit wacky at best,
	because a supporter blog may have a thousand users, and they would all be "pro_sites".
	*/
	function is_pro_user($user_id = false) {
		global $wpdb, $current_user, $current_site;

		if ( !$user_id ) {
			$user_id = $current_user->ID;
		}
    $user_id = intval($user_id);

		if ( is_super_admin($user_id) )
			return true;

		//very db intensive, so we cache (1 hour)
		$expire_time = time()-3600;
		@list($expire, $is_pro) = get_user_meta($user_id, 'psts_user', true);
		if ($expire && $expire >= $expire_time) {
	    return $is_pro;
	  }

		//TODO - add option to select which user levels from supporter blog will be supporter user. Right now it's all (>= Subscriber)
		//$results = $wpdb->get_results("SELECT * FROM `$wpdb->usermeta` WHERE `user_id` = $user_id AND `meta_key` LIKE 'wp_%_capabilities' AND `meta_value` LIKE '%administrator%'");
		$results = $wpdb->get_results("SELECT * FROM `$wpdb->usermeta` WHERE `user_id` = $user_id AND `meta_key` LIKE '{$wpdb->base_prefix}%_capabilities'");
	  if (!$results) {
	    //update cache
	    update_user_meta($user_id, 'psts_user', array(time(), 0));
	    return false;
	  }

	  foreach ($results as $row) {
		  $tmp = explode('_', $row->meta_key);
		  //skip main blog
		  if ($tmp[1] != $current_site->blogid)
	      $blog_ids[] = $tmp[1];
	  }
	  $blog_ids = implode(',',$blog_ids);

	  $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE expire > '" . time() . "' AND blog_ID IN ($blog_ids)");
	  if ($count) {
	    update_user_meta($user_id, 'psts_user', array(time(), 1)); //update cache
	    return true;
	  } else {
	    //update cache
	    update_user_meta($user_id, 'psts_user', array(time(), 0)); //update cache
	    return false;
	  }
	}

	//returns the level if blog is paid up
  function get_level($blog_id = '') {
		global $wpdb;

		if ( empty( $blog_id ) ) {
			$blog_id = $wpdb->blogid;
		}

		//check cache
		if ( isset($this->level[$blog_id]) )
		  return $this->level[$blog_id];
		
		if (!is_pro_site($blog_id))
			return 0;
		
    $sql = "SELECT level FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'";

		$level = $wpdb->get_var($sql);
		if ($level) {
		  $this->level[$blog_id] = $level;
			return $level;
		} else {
		  unset($this->level[$blog_id]);
			return 0;
		}
	}

	function get_expire($blog_id = '') {
		global $wpdb;

		if ( empty( $blog_id ) ) {
			$blog_id = $wpdb->blogid;
		}

		$expire = $wpdb->get_var("SELECT expire FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'");
		if ($expire) {
			return $expire;
		} else {
			return false;
		}
	}

	function extend($blog_id, $extend, $gateway = false, $level = 1, $amount = false) {
		global $wpdb, $current_site;

		$now = time();

    $exists = $this->get_expire($blog_id);
		if ( $exists ) {
		  $old_expire = $exists;
			if ( $now > $old_expire ) {
				$old_expire = $now;
			}
		} else {
			$old_expire = $now;
		}

	  $term = $extend;
		if ( $extend == '1' ) {
			//$extend = 2629744;
			$extend = strtotime("+1 month");
			$extend = $extend - time();
			$extend = $extend + 3600;
		} else if ( $extend == '3' ) {
			//$extend = 7889231;
			$extend = strtotime("+3 months");
			$extend = $extend - time();
			$extend = $extend + 3600;
		} else if ( $extend == '12' ) {
			//$extend = 31556926;
			$extend = strtotime("+1 year");
			$extend = $extend - time();
			$extend = $extend + 3600;
		} else {
			$term = false;
		}

		$new_expire = $old_expire + $extend;
    if ($extend >= 9999999999) {
      $new_expire = 9999999999;
      $term = __('Permanent', 'psts');
		}

		$old_level = $this->get_level($blog_id);

		$extra_sql = ($gateway) ? ", gateway = '$gateway'" : '';
		$extra_sql .= ($amount) ? ", amount = '$amount'" : '';
		$extra_sql .= ($term) ? ", term = '$term'" : '';
		
		if ($exists)
	  	$wpdb->query("UPDATE {$wpdb->base_prefix}pro_sites SET expire = '$new_expire', level = '$level'$extra_sql WHERE blog_ID = '$blog_id'");
		else
		  $wpdb->query("INSERT INTO {$wpdb->base_prefix}pro_sites (blog_ID, expire, level, gateway, term) VALUES ('$blog_id', '$new_expire', '$level', '$gateway', '$term')");

		unset($this->pro_sites[$blog_id]); //clear cache
		unset($this->level[$blog_id]); //clear cache

		if ($new_expire >= 9999999999)
			$this->log_action( $blog_id, __('Pro Site status expiration permanently extended.', 'psts') );
		else
			$this->log_action( $blog_id, sprintf( __('Pro Site status expiration extended until %s.', 'psts'), date_i18n( get_blog_option($current_site->blog_id, 'date_format'), $new_expire ) ) );

	  do_action('psts_extend', $blog_id, $new_expire, $level);

	  //fire level change
		if ( intval($exists) <= time() ) { //count reactivating account as upgrade
			do_action('psts_upgrade', $blog_id, $level, 0);
		} else {
			if ( $old_level < $level ) {
				$this->log_action( $blog_id, sprintf( __('Pro Site level upgraded from "%s" to "%s".', 'psts'), $this->get_level_setting($old_level, 'name'), $this->get_level_setting($level, 'name') ) );
				do_action('psts_upgrade', $blog_id, $level, $old_level);
			} else if ( $old_level > $level ) {
				$this->log_action( $blog_id, sprintf( __('Pro Site level downgraded from "%s" to "%s".', 'psts'), $this->get_level_setting($old_level, 'name'), $this->get_level_setting($level, 'name') ) );
				do_action('psts_downgrade', $blog_id, $level, $old_level);
			}
		}

	  //flip flag after action fired
	  update_blog_option($blog_id, 'psts_withdrawn', 0);

	  //force to checkout screen next login
	  update_blog_option($blog_id, 'psts_signed_up', 0);
	}

	function withdraw($blog_id, $withdraw = false) {
		global $wpdb;

		if ($withdraw) {
			if ( $withdraw == '1' ) {
				$withdraw = 2629744;
			} else if ( $withdraw == '3' ) {
				$withdraw = 7889231;
			} else if ( $withdraw == '12' ) {
				$withdraw = 31556926;
			}
			$new_expire = $this->get_expire($blog_id) - $withdraw;
		} else {
      $new_expire = time() - 1;
		}
	  $wpdb->query("UPDATE {$wpdb->base_prefix}pro_sites SET expire = '$new_expire' WHERE blog_ID = '$blog_id'");

    unset($this->pro_sites[$blog_id]); //clear cache

    $this->log_action( $blog_id, __('Pro Site status has been withdrawn.', 'psts') );

	  do_action('psts_withdraw', $blog_id, $new_expire);

	  //flip flag after action fired
	  update_blog_option($blog_id, 'psts_withdrawn', 1);

	  //force to checkout screen next login
	  if ($new_expire <= time())
	  	update_blog_option($blog_id, 'psts_signed_up', 1);

	}


  //checks a coupon code for validity. Return boolean
  function check_coupon($code, $blog_id = false, $level = false) {
    $coupon_code = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($code));

    //empty code
    if (!$coupon_code)
      return false;

    $coupons = (array)get_site_option('psts_coupons');
		
		//allow plugins to override coupon check by returning a boolean value
		if ( is_bool( $override = apply_filters('psts_check_coupon', null, $coupon_code, $blog_id, $level, $coupons) ) )
			return $override;
		
    //no record for code
    if (!isset($coupons[$coupon_code]) || !is_array($coupons[$coupon_code]))
      return false;

    //if specific level and not proper level
    if ($level && $coupons[$coupon_code]['level'] != 0 && $coupons[$coupon_code]['level'] != $level)
      return false;

    //start date not valid yet
    if (time() < $coupons[$coupon_code]['start'])
      return false;

    //if end date and expired
    if (isset($coupons[$coupon_code]['end']) && $coupons[$coupon_code]['end'] && time() > $coupons[$coupon_code]['end'])
      return false;

    //check remaining uses
    if (isset($coupons[$coupon_code]['uses']) && $coupons[$coupon_code]['uses'] && (intval($coupons[$coupon_code]['uses']) - intval(@$coupons[$coupon_code]['used'])) <= 0)
      return false;
		
		//check if the blog has used the coupon before
		if ($blog_id) {
			$used = get_blog_option($blog_id, 'psts_used_coupons');
			if (is_array($used) && in_array($coupon_code, $used))
				return false;
		}
		
    //everything passed so it's valid
    return true;
  }

  //get coupon value. Returns array(discount, new_total) or false for invalid code
  function coupon_value($code, $total) {
    if ($this->check_coupon($code)) {
      $coupons = (array)get_site_option('psts_coupons');
      $coupon_code = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($code));
      if ($coupons[$coupon_code]['discount_type'] == 'amt') {
        $new_total = round($total - $coupons[$coupon_code]['discount'], 2);
        $new_total = ($new_total < 0) ? 0.00 : $new_total;
        $discount = '-' . $this->format_currency('', $coupons[$coupon_code]['discount']);
        return array('discount' => $discount, 'new_total' => $new_total);
      } else {
        $new_total = round($total - ($total * ($coupons[$coupon_code]['discount'] * 0.01)), 2);
        $new_total = ($new_total < 0) ? 0.00 : $new_total;
        $discount = '-' . $coupons[$coupon_code]['discount'] . '%';
        return array('discount' => $discount, 'new_total' => $new_total);
      }

    } else {
      return false;
    }
  }

  //record coupon use. Returns boolean successful
  function use_coupon($code, $blog_id) {
    if ($this->check_coupon($code, $blog_id)) {
      $coupons = (array)get_site_option('psts_coupons');
      $coupon_code = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($code));

      //increment count
      @$coupons[$coupon_code]['used']++;
      update_site_option('psts_coupons', $coupons);

      unset($_SESSION['COUPON_CODE']);
			
			$used = (array)get_blog_option($blog_id, 'psts_used_coupons');
			$used[] = $coupon_code;
			update_blog_option($blog_id, 'psts_used_coupons', $used);
			
      return true;
    } else {
      return false;
    }
  }

  //display currency symbol
  function format_currency($currency = '', $amount = false) {

    if (!$currency)
      $currency = $this->get_setting('currency', 'USD');

    // get the currency symbol
    $symbol = @$this->currencies[$currency][1];
    // if many symbols are found, rebuild the full symbol
    $symbols = explode(', ', $symbol);
    if (is_array($symbols)) {
      $symbol = "";
      foreach ($symbols as $temp) {
        $symbol .= '&#x'.$temp.';';
      }
    } else {
      $symbol = '&#x'.$symbol.';';
    }

		//check decimal option
    if ( $this->get_setting('curr_decimal') === '0' ) {
      $decimal_place = 0;
      $zero = '0';
		} else {
      $decimal_place = 2;
      $zero = '0.00';
		}

    //format currency amount according to preference
    if ($amount) {
			
      if ($this->get_setting('curr_symbol_position') == 1 || !$this->get_setting('curr_symbol_position'))
        return $symbol . number_format_i18n($amount, $decimal_place);
      else if ($this->get_setting('curr_symbol_position') == 2)
        return $symbol . ' ' . number_format_i18n($amount, $decimal_place);
      else if ($this->get_setting('curr_symbol_position') == 3)
        return number_format_i18n($amount, $decimal_place) . $symbol;
      else if ($this->get_setting('curr_symbol_position') == 4)
        return number_format_i18n($amount, $decimal_place) . ' ' . $symbol;

    } else if ($amount === false) {
      return $symbol;
    } else {
      if ($this->get_setting('curr_symbol_position') == 1 || !$this->get_setting('curr_symbol_position'))
        return $symbol . $zero;
      else if ($this->get_setting('curr_symbol_position') == 2)
        return $symbol . ' ' . $zero;
      else if ($this->get_setting('curr_symbol_position') == 3)
        return $zero . $symbol;
      else if ($this->get_setting('curr_symbol_position') == 4)
        return $zero . ' ' . $symbol;
    }
  }

  /*
	 * This is rather complicated, but essentialy it works like:
	 * Pass it a new amt and period, then it finds the old amt
	 * and period, to calculate how much money is unused from their last payment.
	 * Then it takes that money and applies it to the cost per day of the new plan,
	 * returning the timestamp of the day the first payment of the new plan should take place.
	 */
  function calc_upgrade($blog_id, $new_amt, $new_level, $new_period) {
		global $wpdb;

		$old = $wpdb->get_row("SELECT expire, level, term, amount FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'");
		if (!$old)
			return false;

		//if level is not being raised not an upgrade
		if ($new_level <= $old->level)
		  return false;

	  //some complicated math calculating the prorated amt left and applying it to the price of new plan
	  $diff = $old->expire - time();
	  $duration = $old->term * 30.4166 * 24 * 60 * 60; //number of seconds in the period
	  $left = $duration - ($duration - $diff);
	  if ($left <= 0 || empty($old->amount) || $old->amount <= 0)
	    return false;
	  $prorate_amt = $old->amount * ($left / $duration);
	  $new_duration = $new_period * 30.4166 * 24 * 60 * 60; //number of seconds in the period
	  $first_payment = ($prorate_amt / ($new_amt / $new_duration)) + time(); //return timestamp of first payment date
    $first_payment = intval(round($first_payment));

	  return ($first_payment > time()) ? $first_payment : false;
	}

	//filters the titles for our custom pages
  function page_title_output($title, $id = null) {

    //filter out nav titles
		if (!in_the_loop() || get_queried_object_id() != $id)
		  return $title;

    //set blog_id
		if (isset($_POST['bid']))
		  $blog_id = intval($_POST['bid']);
		else if (isset($_GET['bid']))
		  $blog_id = intval($_GET['bid']);
    else
    	return $title;
		
		$url = str_replace( 'http://', '', get_site_url($blog_id, '', 'http') );
		
    return sprintf(__('%1$s: %2$s (%3$s)', 'psts'), $title, get_blog_option($blog_id, 'blogname'), $url);
  }

	function signup_redirect() {
		global $blog_id;

	  //dismiss redirect if link is clicked or paid
	  if (isset($_GET['psts_dismiss']) || is_pro_site())
	    update_option('psts_signed_up', 0);
		
		//skip redirect on bulk upgrades page
		if ( isset($_GET['page']) && $_GET['page'] == 'psts-bulk-upgrades' )
			return true;
		
	  //force to checkout page
	  if (!is_super_admin() && ( ( get_option('psts_signed_up') && current_user_can('edit_pages') ) || apply_filters('psts_force_redirect', false) ) ) {
	    wp_redirect($this->checkout_url($blog_id));
			exit();
	  }
	}

  function scripts_checkout() {
	  wp_enqueue_script('psts-checkout', $this->plugin_url . 'js/checkout.js', array('jquery'), $this->version );
	}

	function scripts_stats() {
	  wp_enqueue_script('flot', $this->plugin_url . 'js/jquery.flot.min.js', array('jquery'), $this->version );
	  wp_enqueue_script('flot_pie', $this->plugin_url . 'js/jquery.flot.pie.min.js', array('jquery', 'flot'), $this->version );
	  wp_enqueue_script('flot_xcanvas', $this->plugin_url . 'js/excanvas.pack.js', array('jquery', 'flot'), $this->version );
	}

  function scripts_coupons() {
		wp_enqueue_script( 'jquery-datepicker', $this->plugin_url . 'datepicker/js/datepicker.min.js', array('jquery', 'jquery-ui-core'), $this->version);

		//only load languages for datepicker if not english (or it will show Chinese!)
		if ($this->language != 'en')
			wp_enqueue_script( 'jquery-datepicker-i18n', $this->plugin_url . 'datepicker/js/datepicker-i18n.min.js', array('jquery', 'jquery-ui-core', 'jquery-datepicker'), $this->version);
	}

	//enqeue datepicker css on coupons screen
  function css_coupons() {
    wp_enqueue_style( 'jquery-datepicker-css', $this->plugin_url . 'datepicker/css/ui-lightness/datepicker.css', false, $this->version);
  }

	function feature_notice($level = 1) {
    global $blog_id;
		$feature_message = str_replace( 'LEVEL', $this->get_level_setting($level, 'name', $this->get_setting('rebrand')), $this->get_setting('feature_message') );
		echo '<div id="message" class="error"><p><a href="' . $this->checkout_url($blog_id) . '">' . $feature_message . '</a></p></div>';
	}
	
	function levels_select($name, $selected) {
		?>
		<select name="<?php echo $name; ?>" id="psts-level-select">
			<?php
			$levels = (array)get_site_option( 'psts_levels' );
			foreach ($levels as $level => $value) {
			?><option value="<?php echo $level; ?>"<?php selected($selected, $level) ?>><?php echo $level . ': ' . esc_attr($value['name']); ?></option><?php
			}
			?>
		</select>
		<?php
	}
	
	function signup_output() {

	  if ($this->get_setting('show_signup') && !isset($_GET[sanitize_title($this->get_setting('rebrand'))]) && !isset($_POST['psts_signed_up_override'])) {
	  ?>
	  <div class="register-section clear" id="supporter">
			<label class="label"><?php echo $this->get_setting('rebrand'); ?></label>
	    <?php echo $this->get_setting('signup_message'); ?>

			<label class="checkbox" for="psts_signed_up_yes">
				<input type="radio" id="psts_signed_up_yes" name="psts_signed_up" value="yes"<?php echo (!isset($_POST['psts_signed_up']) || $_POST['psts_signed_up']=='yes') ? ' checked="checked"' : ''; ?> />
				<strong><?php _e( "I'm Interested", 'psts' ); ?></strong>
			</label>
			<label class="checkbox" for="psts_signed_up_no">
				<input type="radio" id="psts_signed_up_no" name="psts_signed_up" value="no"<?php echo (isset($_POST['psts_signed_up']) && $_POST['psts_signed_up']=='no') ? ' checked="checked"' : ''; ?> />
				<strong><?php _e( "Not Now", 'psts' ); ?></strong>
			</label>
		</div>

	  <?php
	  } else if (isset($_GET[sanitize_title($this->get_setting('rebrand'))]) || isset($_POST['psts_signed_up_override'])) {
	    echo '<input type="hidden" name="psts_signed_up" value="yes" />';
	    echo '<input type="hidden" name="psts_signed_up_override" value="1" />';
	  }

	}

	function signup_override() {
	  //carries the hidden signup field over from user to blog signup
	  if (isset($_GET[sanitize_title($this->get_setting('rebrand'))]) || isset($_POST['psts_signed_up_override'])) {
	    echo '<input type="hidden" name="psts_signed_up_override" value="1" />';
	  }
	}

	function signup_save($meta) {
	  if (isset($_POST['psts_signed_up'])) {
	    $meta['psts_signed_up'] = ($_POST['psts_signed_up']=='yes') ? 1 : 0;
	  }

	  return $meta;
	}

	function add_column( $columns ) {

		$first_array = array_splice ($columns, 0, 2);
		$columns = array_merge ($first_array, array('psts' => __('Pro Site', 'psts')), $columns);

		return $columns;
	}

	function add_column_field( $column, $blog_id ) {
		$this->column_field_cache($blog_id);

		if ( $column == 'psts' ) {
			if ( isset($this->column_fields[$blog_id]) ) {
				echo "<a title='".__('Manage site &raquo;', 'psts')."' href='" .network_admin_url('settings.php?page=psts&bid='.$blog_id). "'>".$this->column_fields[$blog_id]."</a>";
			} else {
	      echo "<a title='".__('Manage site &raquo;', 'psts')."' href='" .network_admin_url('settings.php?page=psts&bid='.$blog_id). "'>".__('Manage &raquo;', 'psts')."</a>";
	    }
		}
	}

	function column_field_cache($blog_id) {
		global $wpdb;

		if( !isset($this->column_fields[$blog_id]) ) {
			$psts = $wpdb->get_results( "SELECT blog_ID, level FROM {$wpdb->base_prefix}pro_sites WHERE expire > '" . time() . "'" );
			foreach ($psts as $row) {
			  $level = $row->level . ': ' . $this->get_level_setting($row->level, 'name');
				$this->column_fields[$row->blog_ID] = $level;
			}
		}
	}
	
	//returns the js needed to record ecommerce transactions.
	function create_ga_ecommerce($blog_id, $period, $amount, $level, $city = '', $state = '', $country = '') {
		global $current_site;
		
		$name = $this->get_level_setting($level, 'name');
		$category = $period.' Month';
		$sku = 'level'.$level.'_'.$period.'month';
		$order_id = $blog_id.'_'.time();
		$store_name = $current_site->site_name . ' ' . $this->get_setting('rebrand');
		
		if ($this->get_setting('ga_ecommerce') == 'old') {

			$js = '<script type="text/javascript">
try{
	pageTracker._addTrans(
		"'.$order_id.'",          // order ID - required
		"'.esc_js($store_name).'",// affiliation or store name
		"'.$amount.'",            // total - required
		"",                       // tax
		"",                       // shipping
		"'.esc_js($city).'",      // city
		"'.esc_js($state).'",     // state or province
		"'.esc_js($country).'"    // country
	);
	pageTracker._addItem(
		"'.$order_id.'",    // order ID - necessary to associate item with transaction
		"'.$sku.'",         // SKU/code - required
		"'.esc_js($name).'",// product name
		"'.$category.'",    // category or variation
		"'.$amount.'",      // unit price - required
		"1"                 // quantity - required
	);

	pageTracker._trackTrans(); //submits transaction to the Analytics servers
} catch(err) {}
</script>
';

	  } else if ($this->get_setting('ga_ecommerce') == 'new') {
			
			$js = '<script type="text/javascript">
_gaq.push(["_addTrans",
	"'.$order_id.'",          // order ID - required
	"'.esc_js($store_name).'",// affiliation or store name
	"'.$amount.'",            // total - required
	"",                       // tax
	"",                       // shipping
	"'.esc_js($city).'",      // city
	"'.esc_js($state).'",     // state or province
	"'.esc_js($country).'"    // country
]);
_gaq.push(["_addItem",
	"'.$order_id.'",    // order ID - necessary to associate item with transaction
	"'.$sku.'",         // SKU/code - required
	"'.esc_js($name).'",// product name
	"'.$category.'",    // category
	"'.$amount.'",      // unit price - required
	"1"                 // quantity - required
]);
_gaq.push(["_trackTrans"]);
</script>
';
		
		}
		
		//add to footer
		if ( !empty($js) ) {
		  $function = "echo '$js';";
      add_action( 'wp_footer', create_function('', $function), 99999 );
		}
	}
	
	//------------------------------------------------------------------------//
	//---Page Output Functions------------------------------------------------//
	//------------------------------------------------------------------------//

  function admin_modify() {
		global $wpdb, $current_user;

		if ( !is_super_admin() ) {
			echo "<p>" . __('Nice Try...', 'psts') . "</p>";  //If accessed properly, this message doesn't appear.
			return;
		}
		
		//add manual log entries
		if ( isset($_POST['log_entry']) ) {
			$this->log_action( (int)$_GET['bid'], $current_user->display_name . ': "' . strip_tags(stripslashes($_POST['log_entry'])) . '"' );
			echo '<div id="message" class="updated fade"><p>'.__('Log entry added.', 'psts').'</p></div>';
		}
				
    //extend blog
    if ( isset($_POST['psts_extend']) ) {
      check_admin_referer('psts_extend'); //check nonce

      if ( isset($_POST['extend_permanent']) ) {
        $extend = 9999999999;
      } else {
				$months = $_POST['extend_months'];
				$days = $_POST['extend_days'];
				$extend = strtotime("+$months Months $days Days") - time();
			}
			$this->extend((int)$_POST['bid'], $extend, __('Manual', 'psts'), $_POST['extend_level']);
			echo '<div id="message" class="updated fade"><p>'.__('Site Extended.', 'psts').'</p></div>';
		}		
			
		if ( isset($_POST['psts_transfer_pro']) ) {
			$new_bid = (int)$_POST['new_bid'];
			$current_bid = (int)$_GET['bid'];
			if ( !$new_bid ) {
				echo '<div id="message" class="error"><p>'.__('Please enter the Blog ID of a site to transfer too.', 'psts').'</p></div>';
			} else if ( is_pro_site($new_bid) ) {
				echo '<div id="message" class="error"><p>'.__('Could not transfer Pro Status: The chosen site already is a Pro Site. You must remove Pro status and cancel any existing subscriptions tied to that site.', 'psts').'</p></div>';
			} else {
				$current_level = $wpdb->get_row("SELECT * FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$current_bid'");
				$new_expire = $current_level->expire - time();
				$this->extend($new_bid, $new_expire, $current_level->gateway, $current_level->level, $current_level->amount);
				$wpdb->query("UPDATE {$wpdb->base_prefix}pro_sites SET term = '{$current_level->term}' WHERE blog_ID = '$new_bid'");
				$this->withdraw($current_bid);
				$this->log_action( $current_bid, sprintf(__('Pro Status transferred by %s to BlogID: %d', 'psts'), $current_user->display_name, $new_bid) );
				$this->log_action( $new_bid, sprintf(__('Pro Status transferred by %s from BlogID: %d', 'psts'), $current_user->display_name, $current_bid) );
				do_action('psts_transfer_pro', $current_bid, $new_bid); //for gateways to hook into for api calls, etc.
				echo '<div id="message" class="updated fade"><p>'.sprintf(__('Pro Status transferred to BlogID: %d', 'psts'), (int)$_POST['new_bid']).'</p></div>';
			}
		}
	
		//remove blog
    if ( isset($_POST['psts_modify']) ) {
      check_admin_referer('psts_modify'); //check nonce

      do_action('psts_modify_process', (int)$_POST['bid']);

      if ( isset($_POST['psts_remove']) ) {
        $this->withdraw((int)$_POST['bid']);
				echo '<div id="message" class="updated fade"><p>'.__('Pro Site Status Removed.', 'psts').'</p></div>';
			}
			
			if ( isset($_POST['psts_receipt']) ) {
        $this->email_notification((int)$_POST['bid'], 'receipt', $_POST['receipt_email']);
				echo '<div id="message" class="updated fade"><p>'.__('Email receipt sent.', 'psts').'</p></div>';
			}

		}
			
		//check blog_id
		if( isset( $_GET['bid'] ) ) {
			$blog_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}blogs WHERE blog_ID = '" . (int)$_GET['bid'] . "'");
			if ( !$blog_count ) {
				echo '<div id="message" class="updated fade"><p>'.__('Invalid blog ID. Please try again.', 'psts').'</p></div>';
    		$blog_id = false;
			} else {
				$blog_id = (int)$_GET['bid'];
			}
		} else {
			$blog_id = false;
		}

		?>
		<div class="wrap">
		<script type="text/javascript">
  	  jQuery(document).ready(function () {
  		  jQuery('input.psts_confirm').click(function() {
          var answer = confirm("<?php _e('Are you sure you really want to do this?', 'psts'); ?>")
          if (answer){
              return true;
          } else {
              return false;
          };
        });
  		});
  	</script>
  	<div class="icon32"><img src="<?php echo $this->plugin_url . 'images/modify.png'; ?>" /></div>
    <h2><?php _e('Pro Sites Management', 'psts'); ?></h2>

    <?php if ( $blog_id ) { ?>
    	<h3><?php _e('Manage Site', 'psts') ?>
			<?php
      if ($name = get_blog_option($blog_id, 'blogname'))
        echo ': '.$name.' (Blog ID: '.$blog_id.')';

      echo '</h3>';

  		$levels = (array)get_site_option('psts_levels');
  		$current_level = $this->get_level($blog_id);
      $expire = $this->get_expire($blog_id);
      $result = $wpdb->get_row("SELECT * FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'");
      if ($result) {
				if ($result->term == 1 || $result->term == 3 || $result->term == 12)
	        $term = sprintf(__('%s Month', 'psts'), $result->term);
	      else
	        $term = $result->term;
			} else {
				$term = 0;
			}

      if ($expire && $expire > time()) {
        echo '<p><strong>'.__('Current Pro Site', 'psts').'</strong></p>';

        echo '<ul>';
				if ($expire > 2147483647)
					echo '<li>'.__('Pro Site privileges will expire: <strong>Never</strong>', 'psts').'</li>';
				else
        	echo '<li>'.sprintf(__('Pro Site privileges will expire on: <strong>%s</strong>', 'psts'), date_i18n(get_option('date_format'), $expire)).'</li>';

        echo '<li>'.sprintf(__('Level: <strong>%s</strong>', 'psts'), $current_level . ' - ' . @$levels[$current_level]['name']).'</li>';
        if ($result->gateway)
					echo '<li>'.sprintf(__('Payment Gateway: <strong>%s</strong>', 'psts'), $result->gateway).'</li>';
        if ($term)
        	echo '<li>'.sprintf(__('Payment Term: <strong>%s</strong>', 'psts'), $term).'</li>';
        echo '</ul>';

      } else if ($expire && $expire <= time()) {
        echo '<p><strong>'.__('Expired Pro Site', 'psts').'</strong></p>';

        echo '<ul>';
        echo '<li>'.sprintf(__('Pro Site privileges expired on: <strong>%s</strong>', 'psts'), date_i18n(get_option('date_format'), $expire)).'</li>';

        echo '<li>'.sprintf(__('Previous Level: <strong>%s</strong>', 'psts'), $current_level . ' - ' . @$levels[$current_level]['name']).'</li>';
        if ($result->gateway)
					echo '<li>'.sprintf(__('Previous Payment Gateway: <strong>%s</strong>', 'psts'), $result->gateway).'</li>';
        if ($term)
					echo '<li>'.sprintf(__('Previous Payment Term: <strong>%s</strong>', 'psts'), $term).'</li>';
        echo '</ul>';

      } else {
        echo '<p><strong>"'.get_blog_option($blog_id, 'blogname').'" '.__('has never been a Pro Site.', 'psts').'</strong></p>';
      }

		//meta boxes hooked by gateway plugins
    if ( has_action('psts_subscription_info') || has_action('psts_subscriber_info') ) { ?>
    <div class="metabox-holder">
      <?php if ( has_action('psts_subscription_info') ) { ?>
			<div style="width: 49%;" class="postbox-container">
        <div class="postbox">
          <h3 class='hndle'><span><?php _e('Subscription Information', 'psts'); ?></span></h3>
          <div class="inside">
          <?php do_action('psts_subscription_info', $blog_id); ?>
          </div>
				</div>
			</div>
			<?php } ?>

      <?php if ( has_action('psts_subscriber_info') ) { ?>
      <div style="width: 49%;margin-left: 2%;" class="postbox-container">
        <div class="postbox">
          <h3 class='hndle'><span><?php _e('Subscriber Information', 'psts'); ?></span></h3>
          <div class="inside">
        	<?php do_action('psts_subscriber_info', $blog_id); ?>
          </div>
				</div>
			</div>
			<?php } ?>

      <div class="clear"></div>
    </div>
    <?php } ?>

	  <div id="poststuff" class="metabox-holder">
	    <div class="postbox">
	      <h3 class='hndle'><span><?php _e('Account History', 'psts') ?></span></h3>
	      <div class="inside">
	        <span class="description"><?php _e('This logs basically every action done in the system regarding the site for an audit trail.', 'psts'); ?></span>
	        <div style="height:150px;overflow:auto;margin-top:5px;margin-bottom:5px;">
	          <table class="widefat">
	            <?php
	            $log = get_blog_option($blog_id, 'psts_action_log');
	            if (is_array($log) && count($log)) {
	              $log = array_reverse($log, true);
	              foreach ($log as $timestamp => $memo) {
	                $class = (isset($class) && $class == 'alternate') ? '' : 'alternate';
	                echo '<tr class="'.$class.'"><td><strong>' . date_i18n( __('Y-m-d g:i:s a', 'psts'), $timestamp ) . '</strong></td><td>' . esc_html($memo) . '</td></tr>';
								}
	            } else {
	              echo '<tr><td colspan="2">'.__('No history recorded for this site yet.', 'psts').'</td></tr>';
	            }
							?>
	          </table>
	        </div>
					<form method="post" action="">
						<input type="text" placeholder="Add a custom log entry..." name="log_entry" style="width:91%;" /> <input type="submit" class="button-secondary" name="add_log_entry" value="<?php _e('Add &raquo;', 'psts') ?>" style="width:8%;float:right;" />
					</form>
	      </div>
	    </div>
	  </div>


    <div id="poststuff" class="metabox-holder">

      <div style="width: 49%;" class="postbox-container">
      <div class="postbox">
		    <h3 class='hndle'><span><?php _e('Manually Extend Pro Site Status', 'psts') ?></span></h3>
		    <div class="inside">
		      <span class="description"><?php _e('Please note that these changes will not adjust the payment dates or level for any existing subscription.', 'psts'); ?></span>
		      <form method="post" action="">
		      <table class="form-table">
		        <?php wp_nonce_field('psts_extend') ?>
		        <input type="hidden" name="bid" value="<?php echo $blog_id; ?>" />
		        <tr valign="top">
		        <th scope="row"><?php _e('Period', 'psts') ?></th>
		        <td><select name="extend_months">
		      	<?php
		      		for ( $counter = 0; $counter <= 36; $counter += 1) {
		            echo '<option value="' . $counter . '">' . $counter . '</option>' . "\n";
		      		}
		        ?>
		        </select><?php _e('Months', 'psts'); ?>
		        <select name="extend_days">
		      	<?php
		      		for ( $counter = 0; $counter <= 30; $counter += 1) {
		            echo '<option value="' . $counter . '">' . $counter . '</option>' . "\n";
		      		}
		        ?>
		        </select><?php _e('Days', 'psts'); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php _e('or', 'psts'); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		        <label><input type="checkbox" name="extend_permanent" value="1" /> <?php _e('Permanent', 'psts'); ?></label>
		        <br /><?php _e('Period you wish to extend the site. Leave at zero to only change the level.', 'psts'); ?></td>
		        </tr>
		        <tr valign="top">
		        <th scope="row"><?php _e('Level', 'psts') ?></th>
		        <td><select name="extend_level">
		      	<?php
          		foreach ($levels as $level => $value) {
								?><option value="<?php echo $level; ?>"<?php selected($current_level, $level) ?>><?php echo $level . ': ' . esc_attr($value['name']); ?></option><?php
							}
		        ?>
		        </select>
		        <br /><?php _e('Choose what level the site should have access to.', 'psts'); ?></td>
		        </tr>
		        <tr valign="top">
							<td colspan="2" style="text-align:right;"><input class="button-primary" type="submit" name="psts_extend" value="<?php _e('Extend &raquo;', 'psts') ?>" /></td>
						</tr>
		      </table>
					<hr />
		      <table class="form-table">
		        <tr valign="top">
							<td><label>Transfer Pro status to Blog ID: <input type="text" name="new_bid" size="3" /></label></td>
							<td style="text-align:right;"><input class="button-primary psts_confirm" type="submit" name="psts_transfer_pro" value="<?php _e('Transfer &raquo;', 'psts') ?>" /></td>
						</tr>
		      </table>
		      </form>
		    </div>
			</div>
	    </div>

      <?php if ( is_pro_site($blog_id) || has_action('psts_modify_form') ) { ?>
	    <div style="width: 49%;margin-left: 2%;" class="postbox-container">
	      <div class="postbox">
	      <h3 class='hndle'><span><?php _e('Modify Pro Site Status', 'psts') ?></span></h3>
	      <div class="inside">
	        <form method="post" action="">
	        <?php wp_nonce_field('psts_modify') ?>
					<input type="hidden" name="bid" value="<?php echo $blog_id; ?>" />

          <?php do_action('psts_modify_form', $blog_id); ?>

					<?php if ( is_pro_site($blog_id) ) { ?>
          <p><label><input type="checkbox" name="psts_remove" value="1" /> <?php _e('Remove Pro status from this site.', 'psts'); ?></label></p>
	    		<?php } ?>
					
					<?php if ($last_payment = $this->last_transaction($blog_id)) { ?>
					<p><label><input type="checkbox" name="psts_receipt" value="1" /> <?php _e('Email a receipt copy for last payment to:', 'psts'); ?> <input type="text" name="receipt_email" value="<?php echo get_blog_option($blog_id, 'admin_email'); ?>" /></label></p>
					<?php } ?>
					
					<p class="submit">
	        <input type="submit" name="psts_modify" class="button-primary psts_confirm" value="<?php _e('Modify &raquo;', 'psts') ?>" />
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
	      <h3 class='hndle'><span><?php _e('Manage a Site', 'psts') ?></span></h3>
	      <div class="inside">
	        <form method="get" action="">
	        <table class="form-table">
	          <input type="hidden" name="page" value="psts" />
	          <tr valign="top">
	          <th scope="row"><?php _e('Blog ID:', 'psts') ?></th>
	          <td><input type="text" size="17" name="bid" value="" /> <input type="submit" value="<?php _e('Continue &raquo;', 'psts') ?>" /></td></tr>
	        </table>
	        </form>
					<hr />
					<form method="get" action="sites.php" name="searchform">
	        <table class="form-table">
	          <tr valign="top">
	          <th scope="row"><?php _e('Or search for a site:', 'psts') ?></th>
	          <td><input type="text" size="17" value="" name="s"/> <input type="submit" value="<?php _e('Search Sites &raquo;', 'psts') ?>" id="submit_sites" name="submit"/></td></tr>
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

		if ( !is_super_admin() ) {
			echo "<p>" . __('Nice Try...', 'psts') . "</p>";  //If accessed properly, this message doesn't appear.
			return;
		}

		$levels = get_site_option('psts_levels');

    $active_pro_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE expire > '" . time() . "'");
    //$expired_pro_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE expire <= '" . time() . "'");
    $term_1_pro_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term = 1 AND expire > '" . time() . "'");
    $term_3_pro_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term = 3 AND expire > '" . time() . "'");
    $term_12_pro_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term = 12 AND expire > '" . time() . "'");
    $term_manual_pro_sites = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE term NOT IN (1,3,12) AND expire > '" . time() . "'");
    $show_term = $term_1_pro_sites + $term_3_pro_sites + $term_12_pro_sites + $term_manual_pro_sites;
    //ratio levels
    if (is_array($levels) && count($levels) > 1) {
			foreach ($levels as $level => $data) {
				//if last level include all previous ones greater than that level, in case a level was deleted
				if (count($levels) == $level)
					$level_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE level >= $level AND expire > '" . time() . "'");
				else
          $level_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE level = $level AND expire > '" . time() . "'");

        $ratio_levels[] = '{ label: "'.esc_js($level.': '.$this->get_level_setting($level, 'name')).' ('.$level_count.')", data: '.$level_count.'}';
			}
		} else {
  		$ratio_levels[] = '{ label: "'.esc_js('1: '.$this->get_level_setting(1, 'name')).' ('.$active_pro_sites.')", data: '.$active_pro_sites.'}';
		}


    if ($active_pro_sites) {

    //build gateway dataset
    $gateways = $wpdb->get_results("SELECT DISTINCT(gateway) FROM {$wpdb->base_prefix}pro_sites WHERE expire > '" . time() . "'");
    foreach ($gateways as $gateway) {
      $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites WHERE gateway = '".$gateway->gateway."' AND expire > '" . time() . "'");
      $gates[] = '{ label: "'.$gateway->gateway.' ('.$count.')", data: '.$count.' }';
    }
    $gates = implode(', ', (array)$gates);

    //get monthly stats
		if (!defined('PSTS_STATS_MONTHS'))
			define('PSTS_STATS_MONTHS', 12);
    $month_data = array();
    for ($i = 1; $i <= PSTS_STATS_MONTHS; $i++) {
      if ($i == 1) {
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-d', strtotime('+1 month', strtotime($month_start)));
      } else {
        $month_start = date('Y-m-d', strtotime('-1 month', strtotime($month_start)));
        $month_end = date('Y-m-d', strtotime('+1 month', strtotime($month_start)));
      }

      $month_stamp = strtotime($month_start);
      $month_data[$month_stamp]['signups'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'signup' AND time_stamp >= '$month_start' AND time_stamp < '$month_end'");
      $month_data[$month_stamp]['mods'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'modify' AND time_stamp >= '$month_start' AND time_stamp < '$month_end'");
      $month_data[$month_stamp]['upgrades'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'upgrade' AND time_stamp >= '$month_start' AND time_stamp < '$month_end'");
      $month_data[$month_stamp]['cancels'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'cancel' AND time_stamp >= '$month_start' AND time_stamp < '$month_end'");

    }
    $month_data = array_reverse($month_data, true);

    foreach ($month_data as $month => $nums) {
      $month = $month * 1000;

      $m1[] = '['.$month.', '.$nums['signups'].']';
      $m2[] = '['.$month.', '.$nums['upgrades'].']';
      $m3[] = '['.$month.', '.$nums['mods'].']';
      $m4[] = '['.$month.', '.$nums['cancels'].']';
    }
    $m1 = implode(', ', (array)$m1);
    $m2 = implode(', ', (array)$m2);
    $m3 = implode(', ', (array)$m3);
    $m4 = implode(', ', (array)$m4);

    //get weekly stats
    $week_data = array();
    $start = time();
    for ($i = 1; $i <= 26; $i++) { //Only show 6 months of weekly data
      if ($i == 1) {
        $week_start = strtotime("-$i week", $start);
				$week_start_date = date('Y-m-d', $week_start);
        $week_data[$week_start]['signups'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'signup' AND time_stamp >= '$week_start_date'");
				$week_data[$week_start]['upgrades'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'upgrade' AND time_stamp >= '$week_start_date'");
        $week_data[$week_start]['mods'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'modify' AND time_stamp >= '$week_start_date'");
        $week_data[$week_start]['cancels'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'cancel' AND time_stamp >= '$week_start_date'");
      } else {
        $week_end = $week_start;
        $week_start = strtotime("-$i weeks", $start);
				$week_start_date = date('Y-m-d', $week_start);
				$week_end_date = date('Y-m-d', $week_end);
        $week_data[$week_start]['signups'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'signup' AND time_stamp >= '$week_start_date' AND time_stamp < '$week_end_date'");
        $week_data[$week_start]['upgrades'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'upgrade' AND time_stamp >= '$week_start_date' AND time_stamp < '$week_end_date'");
        $week_data[$week_start]['mods'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'modify' AND time_stamp >= '$week_start_date' AND time_stamp < '$week_end_date'");
        $week_data[$week_start]['cancels'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_signup_stats WHERE action = 'cancel' AND time_stamp >= '$week_start_date' AND time_stamp < '$week_end_date'");
      }
    }
    $week_data = array_reverse($week_data, true);

    foreach ($week_data as $week => $nums) {
      $week = $week * 1000;

      $w1[] = '['.$week.', '.$nums['signups'].']';
      $w2[] = '['.$week.', '.$nums['upgrades'].']';
      $w3[] = '['.$week.', '.$nums['mods'].']';
      $w4[] = '['.$week.', '.$nums['cancels'].']';
    }
    $w1 = implode(', ', (array)$w1);
    $w2 = implode(', ', (array)$w2);
    $w3 = implode(', ', (array)$w3);
    $w4 = implode(', ', (array)$w4);

    //get daily totals
    $date = date('Y-m-d', strtotime('-'.PSTS_STATS_MONTHS.' months', time()));
    $days = $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}pro_sites_daily_stats WHERE date >= '$date' ORDER BY date", ARRAY_A);
    if ($days) {
      $level_count = array();
			foreach ($days as $day => $nums) {
        $day_code = strtotime($nums['date'])*1000;

        $pro_sites[] = '['.$day_code.', '.$nums['supporter_count'].']';
        $term_1[] = '['.$day_code.', '.$nums['term_count_1'].']';
        $term_3[] = '['.$day_code.', '.$nums['term_count_3'].']';
        $term_12[] = '['.$day_code.', '.$nums['term_count_12'].']';
        $term_manual[] = '['.$day_code.', '.$nums['term_count_manual'].']';

				//get level counts
				if (is_array($levels) && count($levels) > 1) {
					foreach ($levels as $level => $data) {
					  $level_count[$level][] = '['.$day_code.', '.$nums['level_count_'.$level].']';
					}
				}
      }
      $pro_sites = implode(', ', (array)$pro_sites);
      $term_1 = implode(', ', (array)$term_1);
      $term_3 = implode(', ', (array)$term_3);
      $term_12 = implode(', ', (array)$term_12);
      $term_manual = implode(', ', (array)$term_manual);
      foreach ($level_count as $level => $data)
      	$level_counts[$level] = implode(', ', (array)$data);
    }

    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
      //set data
      var pro_sites = { label: "<?php echo esc_js(__('Total Pro Sites', 'psts')); ?>", color: 3, data: [<?php echo $pro_sites; ?>] };
			<?php
      foreach ($level_counts as $level => $data) {
        //daily stats
        echo 'var level_'.$level.' = { label: "'.esc_js($level.': '.$this->get_level_setting($level, 'name')).'", data: ['.$data.'] };';
        $daily_stats_levels .= ", level_$level";
			}
			?>

      var term_1 = { label: "<?php echo esc_js(__('1 Month', 'psts')); ?>", data: [<?php echo $term_1; ?>] };
      var term_3 = { label: "<?php echo esc_js(__('3 Month', 'psts')); ?>", data: [<?php echo $term_3; ?>] };
      var term_12 = { label: "<?php echo esc_js(__('12 Month', 'psts')); ?>", data: [<?php echo $term_12; ?>] };
      var term_manual = { label: "<?php echo esc_js(__('Manual', 'psts')); ?>", data: [<?php echo $term_manual; ?>] };

      var m1 = { label: "<?php echo esc_js(__('Signups', 'psts')); ?>", color: 3, data: [<?php echo $m1; ?>] };
      var m2 = { label: "<?php echo esc_js(__('Upgrades', 'psts')); ?>", color: 5, data: [<?php echo $m2; ?>] };
      var m3 = { label: "<?php echo esc_js(__('Modifications', 'psts')); ?>", color: 10, data: [<?php echo $m3; ?>] };
      var m4 = { label: "<?php echo esc_js(__('Cancelations', 'psts')); ?>", color: 2, data: [<?php echo $m4; ?>] };

      var w1 = { label: "<?php echo esc_js(__('Signups', 'psts')); ?>", color: 3, data: [<?php echo $w1; ?>] };
      var w2 = { label: "<?php echo esc_js(__('Upgrades', 'psts')); ?>", color: 5, data: [<?php echo $w2; ?>] };
      var w3 = { label: "<?php echo esc_js(__('Modifications', 'psts')); ?>", color: 10, data: [<?php echo $w3; ?>] };
      var w4 = { label: "<?php echo esc_js(__('Cancelations', 'psts')); ?>", color: 2, data: [<?php echo $w4; ?>] };

      var pie_ratio = [
        <?php echo implode(', ', $ratio_levels); ?>
			];

    	var pie_gateways = [<?php echo $gates; ?>];

    	var pie_terms = [
        { label: "<?php echo esc_js(__('1 Month', 'psts')); ?> (<?php echo $term_1_pro_sites; ?>)",  data: <?php echo $term_1_pro_sites; ?>},
        { label: "<?php echo esc_js(__('3 Month', 'psts')); ?> (<?php echo $term_3_pro_sites; ?>)",  data: <?php echo $term_3_pro_sites; ?>},
        { label: "<?php echo esc_js(__('12 Month', 'psts')); ?> (<?php echo $term_12_pro_sites; ?>)",  data: <?php echo $term_12_pro_sites; ?>},
        { label: "<?php echo esc_js(__('Manual', 'psts')); ?> (<?php echo $term_manual_pro_sites; ?>)",  data: <?php echo $term_manual_pro_sites; ?>}
    	];

      //set options
      var graph_options1 = {
        xaxis: { mode: "time", minTickSize: [1, "month"], timeformat: "%b %y" },
        yaxis: { min: 0, minTickSize: 1, tickDecimals: 0 },
        lines: { show: true },
        points: { show: true },
        legend: { show: true, backgroundOpacity: 0.5, position: "nw" },
        grid: { hoverable: true, clickable: false }
      };

      var graph_options2 = {
        xaxis: { mode: "time", minTickSize: [1, "month"], timeformat: "%b %y" },
        yaxis: { min: 0, minTickSize: 1, tickDecimals: 0 },
        lines: { show: true },
        points: { show: true },
        legend: { show: true, backgroundOpacity: 0.5, position: "nw" },
        grid: { hoverable: true, clickable: false }
      };

      var graph_options3 = {
        xaxis: { mode: "time", minTickSize: [1, "month"], timeformat: "%b %y" },
        yaxis: { minTickSize: 1, tickDecimals: 0 },
        lines: { show: true },
        points: { show: false },
        legend: { show: true, backgroundOpacity: 0.5, position: "nw" }
      };

      var pie_options = {
        series: {
          pie: {
            show: true,
            radius: 1,
            label: {
                show: true,
                radius: 3/4,
                formatter: function(label, series) {
                    return '<div style="font-size:8pt;font-weight:bold;text-align:center;padding:2px;color:white;">'+Math.round(series.percent)+'%</div>';
                },
                background: { opacity: 0.5 }
            }
          }
        },
        legend: { show: true, backgroundOpacity: 0.5 }
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
      jQuery(window).resize(function($) {
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
        $('<div id="tooltip">' + contents + '</div>').css( {
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
            var monthname=new Array("Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec");
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
            var date = dt.getFullYear() + "/" + (dt.getMonth()+1) + "/" + dt.getDate();
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
    <div class="icon32"><img src="<?php echo $this->plugin_url . 'images/stats.png'; ?>" /></div>
    <h2><?php _e('Pro Sites Statistics', 'psts'); ?></h2>

    <?php echo $this->weekly_summary(); ?>

    <div class="metabox-holder">

    <div class="postbox">
	    <h3 class='hndle'><span><?php _e('Monthly Activity Summary', 'psts') ?></span></h3>
	    <div class="inside">
	      <div id="monthly_signup_stats" style="margin:20px;height:300px"><?php _e('No data available yet', 'psts') ?></div>
	    </div>
    </div>

    <div class="postbox">
	    <h3 class='hndle'><span><?php _e('Weekly Activity Summary', 'psts') ?></span></h3>
	    <div class="inside">
	      <div id="weekly_signup_stats" style="margin:20px;height:300px"><?php _e('No data available yet', 'psts') ?></div>
	    </div>
    </div>

    <div class="postbox">
	    <h3 class='hndle'><span><?php _e('Ratios', 'psts') ?></span></h3>
	    <div class="inside">

	      <div style="width:40%;height:300px;float:left;margin-bottom:25px;">
	      <h4 style="margin-left:20%;"><?php printf(__('Current Pro Site Levels (%s Total)', 'psts'), number_format_i18n($active_pro_sites)); ?></h4>
	      <div id="pie-ratio" style="width:100%;height:100%;"><?php _e('No data available yet', 'psts') ?></div>
	      </div>

	      <div style="width:40%;height:300px;float:left;margin-left:10%;margin-bottom:25px;">
	      <h4 style="margin-left:20%;"><?php _e('Current Gateway Use', 'psts') ?></h4>
	      <div id="pie-gateway" style="width:100%;height:100%;"><?php _e('No data available yet', 'psts') ?></div>
	      </div>
	      <div class="clear" style="margin-bottom:50px;"></div>

	    </div>
    </div>

    <div class="postbox">
	    <h3 class='hndle'><span><?php _e('Pro Sites History', 'psts') ?></span></h3>
	    <div class="inside">
	      <div id="daily_stats" style="margin:20px;height:300px"><?php _e('No data available yet', 'psts') ?></div>
	    </div>
    </div>

    <div class="postbox">
	    <h3 class='hndle'><span><?php _e('Pro Sites Term History', 'psts') ?></span></h3>
	    <div class="inside">
	      <div id="daily_term_stats" style="margin:20px;height:300px;"><?php _e('No data available yet', 'psts') ?></div>
	      <h4 style="margin-left:10%;"><?php _e('Current Terms', 'psts') ?></h4>
	      <div id="pie-terms" style="width:40%;height:300px;margin-bottom:25px;"><?php _e('No data available yet', 'psts') ?></div>
	    </div>
    </div>

    </div>
    <?php
    } else {
      echo '<h3>'.__('No data available yet', 'psts').'</h3>';
    }
    echo '</div>';
	}

	function admin_coupons() {
		global $wpdb;

		if ( !is_super_admin() ) {
			echo "<p>" . __('Nice Try...', 'psts') . "</p>";  //If accessed properly, this message doesn't appear.
			return;
		}

    ?>
		<script type="text/javascript">
  	  jQuery(document).ready(function ($) {
  	    jQuery.datepicker.setDefaults(jQuery.datepicker.regional['<?php echo $this->language; ?>']);
  		  jQuery('.pickdate').datepicker({dateFormat: 'yy-mm-dd', changeMonth: true, changeYear: true, minDate: 0, firstDay: <?php echo (get_option('start_of_week')=='0') ? 7 : get_option('start_of_week'); ?>});
  		});
  	</script>
		<div class="wrap">
    <div class="icon32"><img src="<?php echo $this->plugin_url . 'images/coupon.png'; ?>" /></div>
    <h2><?php _e('Pro Sites Coupons', 'psts'); ?></h2>
    <p><?php _e('You can create, delete, or update coupon codes for your network here.', 'psts') ?></p>
    <?php

    $coupons = get_site_option('psts_coupons');
    $error = false;

    //delete checked coupons
  	if (isset($_POST['allcoupon_delete'])) {
      //check nonce
      check_admin_referer('psts_coupons');

      if (is_array($_POST['coupons_checks'])) {
        //loop through and delete
        foreach ($_POST['coupons_checks'] as $del_code)
          unset($coupons[$del_code]);

        update_site_option('psts_coupons', $coupons);
        //display message confirmation
        echo '<div class="updated fade"><p>'.__('Coupon(s) succesfully deleted.', 'psts').'</p></div>';
      }
    }

    //save or add coupon
    if (isset($_POST['submit_settings'])) {
      //check nonce
      check_admin_referer('psts_coupons');

      $error = false;

      $new_coupon_code = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($_POST['coupon_code']));
      if (!$new_coupon_code)
        $error[] = __('Please enter a valid Coupon Code', 'psts');

      $coupons[$new_coupon_code]['discount'] = round($_POST['discount'], 2);
      if ($coupons[$new_coupon_code]['discount'] <= 0)
        $error[] = __('Please enter a valid Discount Amount', 'psts');

      $coupons[$new_coupon_code]['discount_type'] = $_POST['discount_type'];
      if ($coupons[$new_coupon_code]['discount_type'] != 'amt' && $coupons[$new_coupon_code]['discount_type'] != 'pct')
        $error[] = __('Please choose a valid Discount Type', 'psts');

      $coupons[$new_coupon_code]['start'] = strtotime($_POST['start']);
      if ($coupons[$new_coupon_code]['start'] === false)
        $error[] = __('Please enter a valid Start Date', 'psts');

      $coupons[$new_coupon_code]['end'] = strtotime($_POST['end']);
      if ($coupons[$new_coupon_code]['end'] && $coupons[$new_coupon_code]['end'] < $coupons[$new_coupon_code]['start'])
        $error[] = __('Please enter a valid End Date not earlier than the Start Date', 'psts');

      $coupons[$new_coupon_code]['level'] = intval($_POST['level']);

      $coupons[$new_coupon_code]['uses'] = (is_numeric($_POST['uses'])) ? (int)$_POST['uses'] : '';

      if (!$error) {
        update_site_option('psts_coupons', $coupons);
        $new_coupon_code = '';
        echo '<div class="updated fade"><p>'.__('Coupon succesfully saved.', 'psts').'</p></div>';
      } else {
        echo '<div class="error"><p>'.implode('<br />', $error).'</p></div>';
			}
    }

    //if editing a coupon
    if (isset($_GET['code'])) {
      $new_coupon_code = $_GET['code'];
    }

    $apage = isset( $_GET['apage'] ) ? intval( $_GET['apage'] ) : 1;
		$num = isset( $_GET['num'] ) ? intval( $_GET['num'] ) : 20;

		$coupon_list = get_site_option('psts_coupons');
		$levels = (array)get_site_option('psts_levels');
		$total = (is_array($coupon_list)) ? count($coupon_list) : 0;

    if ($total)
      $coupon_list = array_slice($coupon_list, intval(($apage-1) * $num), intval($num));

		$coupon_navigation = paginate_links( array(
			'base' => add_query_arg( 'apage', '%#%' ),
			'format' => '',
			'total' => ceil($total / $num),
			'current' => $apage
		));
		$page_link = ($apage > 1) ? '&amp;apage='.$apage : '';
		?>

		<form id="form-coupon-list" action="<?php echo network_admin_url('admin.php?page=psts-coupons'); ?>" method="post">
    <?php wp_nonce_field('psts_coupons') ?>
		<div class="tablenav">
			<?php if ( $coupon_navigation ) echo "<div class='tablenav-pages'>$coupon_navigation</div>"; ?>

			<div class="alignleft">
				<input type="submit" value="<?php _e('Delete', 'psts') ?>" name="allcoupon_delete" class="button-secondary delete" />
				<br class="clear" />
			</div>
		</div>

		<br class="clear" />

		<?php
		// define the columns to display, the syntax is 'internal name' => 'display name'
		$posts_columns = array(
			'code'         => __('Coupon Code', 'psts'),
			'discount'     => __('Discount', 'psts'),
			'start'        => __('Start Date', 'psts'),
			'end'          => __('Expire Date', 'psts'),
      'level'        => __('Level', 'psts'),
      'used'         => __('Used', 'psts'),
      'remaining'    => __('Remaining Uses', 'psts'),
			'edit'         => __('Edit', 'psts')
		);
		?>

		<table width="100%" cellpadding="3" cellspacing="3" class="widefat">
			<thead>
				<tr>
				<th scope="col" class="check-column"><input type="checkbox" /></th>
				<?php foreach($posts_columns as $column_id => $column_display_name) {
					$col_url = $column_display_name;
					?>
					<th scope="col"><?php echo $col_url ?></th>
				<?php } ?>
				</tr>
			</thead>
			<tbody id="the-list">
			<?php
			if ( is_array($coupon_list) && count($coupon_list) ) {
				$bgcolor = isset($class) ? $class : '';
				foreach ($coupon_list as $coupon_code => $coupon) {
					$class = (isset($class) && 'alternate' == $class) ? '' : 'alternate';

          //assign classes based on coupon availability
          //$class = ($this->check_coupon($coupon_code)) ? $class . ' coupon-active' : $class . ' coupon-inactive';

					echo '<tr class="'.$class.' blog-row">
                  <th scope="row" class="check-column">
									<input type="checkbox" name="coupons_checks[]"" value="'.$coupon_code.'" />
								  </th>';

					foreach( $posts_columns as $column_name=>$column_display_name ) {
						switch($column_name) {
							case 'code': ?>
								<th scope="row">
									<?php echo $coupon_code; ?>
								</th>
							<?php
							break;

							case 'discount': ?>
								<th scope="row">
									<?php
									if ($coupon['discount_type'] == 'pct') {
                    echo $coupon['discount'].'%';
                  } else if ($coupon['discount_type'] == 'amt') {
                    echo $this->format_currency('', $coupon['discount']);
                  }
                  ?>
								</th>
							<?php
							break;

							case 'start': ?>
								<th scope="row">
                  <?php echo date_i18n( get_option('date_format'), $coupon['start'] ); ?>
								</th>
							<?php
							break;

							case 'end': ?>
								<th scope="row">
									<?php echo ($coupon['end']) ? date_i18n( get_option('date_format'), $coupon['end'] ) : __('No End', 'psts'); ?>
								</th>
							<?php
							break;

							case 'level': ?>
								<th scope="row">
									<?php echo isset($levels[$coupon['level']]) ? $coupon['level'] . ': ' . $levels[$coupon['level']]['name'] : __('Any Level', 'psts'); ?>
								</th>
							<?php
							break;

							case 'used': ?>
								<th scope="row">
									<?php echo isset($coupon['used']) ? number_format_i18n($coupon['used']) : 0; ?>
								</th>
							<?php
							break;

							case 'remaining': ?>
								<th scope="row">
									<?php
                  if (isset($coupon['uses']))
                    echo number_format_i18n(intval($coupon['uses']) - intval(@$coupon['used']));
                  else
                    _e('Unlimited', 'psts');
                  ?>
								</th>
							<?php
							break;

              case 'edit': ?>
								<th scope="row">
									<a href="admin.php?page=psts-coupons<?php echo $page_link; ?>&amp;code=<?php echo $coupon_code; ?>#add_coupon"><?php _e('Edit', 'psts') ?>&raquo;</a>
								</th>
							<?php
							break;

						}
					}
					?>
					</tr>
					<?php
				}
			} else { ?>
				<tr style='background-color: <?php echo $bgcolor; ?>'>
					<td colspan="9"><?php _e('No coupons yet.', 'psts') ?></td>
				</tr>
			<?php
			} // end if coupons
			?>

			</tbody>
			<tfoot>
				<tr>
				<th scope="col" class="check-column"><input type="checkbox" /></th>
				<?php foreach($posts_columns as $column_id => $column_display_name) {
					$col_url = $column_display_name;
					?>
					<th scope="col"><?php echo $col_url ?></th>
				<?php } ?>
				</tr>
			</tfoot>
		</table>

		<div class="tablenav">
			<?php if ( $coupon_navigation ) echo "<div class='tablenav-pages'>$coupon_navigation</div>"; ?>
		</div>

		<div id="poststuff" class="metabox-holder">

		<div class="postbox">
      <h3 class='hndle'><span>
      <?php
      if ( isset($_GET['code']) || $error ) {
        _e('Edit Coupon', 'psts');
      } else {
        _e('Add Coupon', 'psts');
      }
      ?></span></h3>
      <div class="inside">
        <?php
      	//setup defaults
      	if (isset($new_coupon_code) && isset($coupons[$new_coupon_code])) {
          $discount = ($coupons[$new_coupon_code]['discount'] && $coupons[$new_coupon_code]['discount_type'] == 'amt') ? round($coupons[$new_coupon_code]['discount'], 2) : $coupons[$new_coupon_code]['discount'];
          $discount_type = $coupons[$new_coupon_code]['discount_type'];
          $start = ($coupons[$new_coupon_code]['start']) ? date('Y-m-d', $coupons[$new_coupon_code]['start']) : date('Y-m-d');
          $end = ($coupons[$new_coupon_code]['end']) ? date('Y-m-d', $coupons[$new_coupon_code]['end']) : '';
          $uses = $coupons[$new_coupon_code]['uses'];
        } else {
					$new_coupon_code = '';
					$discount = '';
          $discount_type = '';
          $start = date('Y-m-d');
          $end = '';
          $uses = '';
				}
      	?>
        <table id="add_coupon">
        <thead>
        <tr>
          <th>
          <?php _e('Coupon Code', 'psts') ?><br />
            <small style="font-weight: normal;"><?php _e('Letters and Numbers only', 'psts') ?></small>
            </th>
          <th><?php _e('Discount', 'psts') ?></th>
          <th><?php _e('Start Date', 'psts') ?></th>
          <th>
            <?php _e('Expire Date', 'psts') ?><br />
            <small style="font-weight: normal;"><?php _e('No end if blank', 'psts') ?></small>
          </th>
          <th>
            <?php _e('Level', 'psts') ?>
          </th>
          <th>
            <?php _e('Allowed Uses', 'psts') ?><br />
            <small style="font-weight: normal;"><?php _e('Unlimited if blank', 'psts') ?></small>
          </th>
        </tr>
        </thead>
        <tbody>
        <tr>
          <td>
            <input value="<?php echo $new_coupon_code ?>" name="coupon_code" type="text" style="text-transform: uppercase;" />
          </td>
          <td>
            <input value="<?php echo $discount; ?>" size="3" name="discount" type="text" />
            <select name="discount_type">
             <option value="amt"<?php selected($discount_type, 'amt') ?>><?php echo $this->format_currency(); ?></option>
             <option value="pct"<?php selected($discount_type, 'pct') ?>>%</option>
            </select>
          </td>
          <td>
            <input value="<?php echo $start; ?>" class="pickdate" size="11" name="start" type="text" />
          </td>
          <td>
            <input value="<?php echo $end; ?>" class="pickdate" size="11" name="end" type="text" />
          </td>
          <td>
            <select name="level">
             <option value="0"><?php _e('Any Level', 'psts') ?></option>
             <?php
						 foreach ($levels as $key => $value) {
							?><option value="<?php echo $key; ?>"<?php selected(@$coupons[$new_coupon_code]['level'], $key) ?>><?php echo $key . ': ' . $value['name']; ?></option><?php
						 }
						 ?>
            </select>
          </td>
          <td>
            <input value="<?php echo $uses; ?>" size="4" name="uses" type="text" />
          </td>
        </tr>
        </tbody>
        </table>

        <p class="submit">
          <input type="submit" name="submit_settings" class="button-primary" value="<?php _e('Save Coupon', 'psts') ?>" />
        </p>
      </div>
    </div>

    </div>
		</form>

		</div>
		<?php
	}

	function admin_levels() {
		global $wpdb;

		if ( !is_super_admin() ) {
			echo "<p>" . __('Nice Try...', 'psts') . "</p>";  //If accessed properly, this message doesn't appear.
			return;
		}
		?>
		<div class="wrap">
		<script type="text/javascript">
    jQuery(document).ready(function($) {
      jQuery('#level-delete').click(function() {
        return confirm("<?php _e('Are you sure you really want to remove this level? This will also delete all feature settings for the level.', 'psts'); ?>");
      });

		  if(!$('#enable_1').is(':checked')) {
		    $('.price-1').attr('disabled', true);
		  }
		  if(!$('#enable_3').is(':checked')) {
		    $('.price-3').attr('disabled', true);
		  }
		  if(!$('#enable_12').is(':checked')) {
		    $('.price-12').attr('disabled', true);
		  }

		  $('#enable_1').change(function() {
		    if(this.checked) {
		      $('.price-1').removeAttr('disabled');
				} else {
		      $('.price-1').attr('disabled', true);
		    }
			});
			$('#enable_3').change(function() {
		    if(this.checked) {
		      $('.price-3').removeAttr('disabled');
				} else {
		      $('.price-3').attr('disabled', true);
		    }
			});
			$('#enable_12').change(function() {
		    if(this.checked) {
		      $('.price-12').removeAttr('disabled');
				} else {
		      $('.price-12').attr('disabled', true);
		    }
			});
    });
    </script>
    <div class="icon32"><img src="<?php echo $this->plugin_url . 'images/levels.png'; ?>" /></div>
    <h2><?php _e('Pro Sites Levels', 'psts'); ?></h2>
    <?php

    $levels = (array)get_site_option('psts_levels');

    //delete checked levels
  	if (isset($_POST['delete_level'])) {
      //check nonce
      check_admin_referer('psts_levels');

      if (is_array($levels) && count($levels) > 1) {
        $level_num = count($levels);
        $null = array_pop($levels);
        update_site_option('psts_levels', $levels);

        //display message confirmation
        echo '<div class="updated fade"><p>'.sprintf(__('Level %s successfully deleted.', 'psts'), number_format_i18n($level_num)).'</p></div>';
      }
    }

    //add level
    if (isset($_POST['add_level'])) {
      //check nonce
      check_admin_referer('psts_levels');

      $error = false;

      if ( empty($_POST['add_name']) )
        $error[] = __('Please enter a valid level name.', 'psts');

      if ( empty($_POST['add_price_1']) && empty($_POST['add_price_3']) && empty($_POST['add_price_12']) )
        $error[] = __('You must enter a price for at least one payment period.', 'psts');

      if (!$error) {
        $levels[] = array('name' => stripslashes(trim(wp_filter_nohtml_kses($_POST['add_name']))),
													'price_1' => round(@$_POST['add_price_1'], 2),
													'price_3' => round(@$_POST['add_price_3'], 2),
													'price_12' => round(@$_POST['add_price_12'], 2)
										);
        update_site_option('psts_levels', $levels);
        echo '<div class="updated fade"><p>'.__('Level added.', 'psts').'</p></div>';
      } else {
        echo '<div class="error"><p>'.implode('<br />', $error).'</p></div>';
			}
    }

		//save levels
    if (isset($_POST['save_levels'])) {
      //check nonce
      check_admin_referer('psts_levels');

			$periods = array();
			if (isset($_POST['enable_1']))
			  $periods[] = 1;
      if (isset($_POST['enable_3']))
			  $periods[] = 3;
      if (isset($_POST['enable_12']))
			  $periods[] = 12;

			$this->update_setting('enabled_periods', $periods);

			foreach ($_POST['name'] as $level => $name) {
			  $stripped_name = stripslashes(trim(wp_filter_nohtml_kses($name)));
			  $name = empty($stripped_name) ? $levels[$level]['name'] : $stripped_name;
        $levels[$level]['name'] = $name;
        $levels[$level]['price_1'] = round(@$_POST['price_1'][$level], 2);
        $levels[$level]['price_3'] = round(@$_POST['price_3'][$level], 2);
        $levels[$level]['price_12'] = round(@$_POST['price_12'][$level], 2);
			}

      update_site_option('psts_levels', $levels);
      echo '<div class="updated fade"><p>'.__('Levels saved.', 'psts').'</p></div>';
    }

		$level_list = get_site_option('psts_levels');
		$last_level = (is_array($level_list)) ? count($level_list) : 0;
		$periods = (array)$this->get_setting('enabled_periods');
		?>

		<form id="form-level-list" action="" method="post">
    <?php wp_nonce_field('psts_levels') ?>

		<?php
		// define the columns to display, the syntax is 'internal name' => 'display name'
		$posts_columns = array(
			'level'        => __('Level', 'psts'),
			'name'     		 => __('Name', 'psts'),
			'price_1'      => __('1 Month Price', 'psts'),
			'price_3'      => __('3 Month Price', 'psts'),
      'price_12'     => __('12 Month Price', 'psts'),
			'edit'         => ''
		);
		?>
    <h3><?php _e('Edit Pro Site Levels', 'psts') ?></h3>
		<span class="description"><?php _e('Pro Sites will have the features assigned to all level numbers at or less than their own. You can disable a subscription period by unchecking it. Modifying the prices of a level will not change the current subsciption rate or plan for existing sites in that level. When you delete a level, existing sites in that level will retain the features of all levels below their current level number.', 'psts') ?></span>
		<table width="100%" cellpadding="3" cellspacing="3" class="widefat">
			<thead>
				<tr>
					<th scope="col"><?php _e('Level', 'psts'); ?></th>
					<th scope="col"><?php _e('Name', 'psts'); ?></th>
					<th scope="col"><label><input name="enable_1" id="enable_1" value="1" title="<?php _e('Enable 1 Month Checkout', 'psts'); ?>" type="checkbox"<?php checked(in_array(1, $periods)); ?>> <?php _e('1 Month Price', 'psts'); ?></label></th>
					<th scope="col"><label><input name="enable_3" id="enable_3" value="1" title="<?php _e('Enable 3 Month Checkout', 'psts'); ?>" type="checkbox"<?php checked(in_array(3, $periods)); ?>> <?php _e('3 Month Price', 'psts'); ?></label></th>
					<th scope="col"><label><input name="enable_12" id="enable_12" value="1" title="<?php _e('Enable 12 Month Checkout', 'psts'); ?>" type="checkbox"<?php checked(in_array(12, $periods)); ?>> <?php _e('12 Month Price', 'psts'); ?></label></th>
					<th scope="col"></th>
				</tr>
			</thead>
			<tbody id="the-list">
			<?php
			if ( is_array($level_list) && count($level_list) ) {
				$bgcolor = $class = '';
				foreach ($level_list as $level_code => $level) {
					$class = ('alternate' == $class) ? '' : 'alternate';

					echo '<tr class="'.$class.' blog-row">';

					foreach( $posts_columns as $column_name => $column_display_name ) {
						switch ( $column_name ) {
							case 'level': ?>
								<td scope="row" style="padding-left: 20px;">
									<strong><?php echo $level_code; ?></strong>
								</td>
							<?php
							break;

							case 'name': ?>
								<td scope="row">
         					<input value="<?php echo esc_attr($level['name']) ?>" size="50" maxlength="100" name="name[<?php echo $level_code; ?>]" type="text" />
								</td>
							<?php
							break;

							case 'price_1': ?>
								<td scope="row">
                  <label><?php echo $this->format_currency(); ?></label><input class="price-1" value="<?php echo ( $level['price_1'] ) ? number_format( (float)$level['price_1'], 2, '.', '' ) : ''; ?>" size="4" name="price_1[<?php echo $level_code; ?>]" type="text" />
								</td>
							<?php
							break;

       				case 'price_3': ?>
        				<td scope="row">
                  <label><?php echo $this->format_currency(); ?></label><input class="price-3" value="<?php echo ( $level['price_3'] ) ? number_format( (float)$level['price_3'], 2, '.', '' ) : ''; ?>" size="4" name="price_3[<?php echo $level_code; ?>]" type="text" />
								</td>
							<?php
							break;

       				case 'price_12': ?>
        				<td scope="row">
                  <label><?php echo $this->format_currency(); ?></label><input class="price-12" value="<?php echo ( $level['price_12'] ) ? number_format( (float)$level['price_12'], 2, '.', '' ) : ''; ?>" size="4" name="price_12[<?php echo $level_code; ?>]" type="text" />
								</td>
							<?php
							break;

              case 'edit': ?>
								<td scope="row">
								<?php if ( $level_code == $last_level && $level_code != 1 ) { ?>
         					<input class="button" type="submit" id="level-delete" name="delete_level" value="<?php _e('Delete &raquo;', 'psts') ?>" />
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
			} else { ?>
				<tr style='background-color: <?php echo $bgcolor; ?>'>
					<td colspan="6"><?php _e('No levels yet.', 'psts') ?></td>
				</tr>
			<?php
			} // end if levels
			?>

			</tbody>
		</table>
		<p class="submit">
      <input type="submit" name="save_levels" class="button-primary" value="<?php _e('Save Levels', 'psts') ?>" />
    </p>

		<h3><?php _e('Add New Level', 'psts') ?></h3>
		<span class="description"><?php _e('You can add a new Pro Site level here.', 'psts') ?></span>
    <table width="100%" cellpadding="3" cellspacing="3" class="widefat">
      <thead>
				<tr>
          <th scope="col"><?php _e('Level', 'psts'); ?></th>
					<th scope="col"><?php _e('Name', 'psts'); ?></th>
					<th scope="col"><?php _e('1 Month Price', 'psts'); ?></th>
					<th scope="col"><?php _e('3 Month Price', 'psts'); ?></th>
					<th scope="col"><?php _e('12 Month Price', 'psts'); ?></th>
					<th scope="col"></th>
				</tr>
			</thead>
			<tbody id="the-list">
				<tr>
				  <td scope="row" style="padding-left: 20px;">
						<strong><?php echo $last_level + 1; ?></strong>
					</td>
          <td>
            <input value="" size="50" maxlength="100" name="add_name" type="text" />
          </td>
          <td>
            <label><?php echo $this->format_currency(); ?></label><input class="price-1" value="" size="4" name="add_price_1" type="text" />
          </td>
          <td>
            <label><?php echo $this->format_currency(); ?></label><input class="price-3" value="" size="4" name="add_price_3" type="text" />
          </td>
          <td>
            <label><?php echo $this->format_currency(); ?></label><input class="price-12" value="" size="4" name="add_price_12" type="text" />
          </td>
          <td>
            <input class="button" type="submit" name="add_level" value="<?php _e('Add &raquo;', 'psts') ?>" />
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
		global $wpdb, $psts_modules, $psts_gateways;

		if ( !is_super_admin() ) {
			echo "<p>" . __('Nice Try...', 'psts') . "</p>";  //If accessed properly, this message doesn't appear.
			return;
		}

		if ( isset( $_POST['submit_settings'] ) ) {
      //check nonce
      check_admin_referer('psts_modules');

      $this->update_setting('modules_enabled', $_POST['allowed_modules']);
      $this->update_setting('gateways_enabled', $_POST['allowed_gateways']);

      do_action('psts_modules_save');

      echo '<div class="updated fade"><p>'.__('Settings Saved', 'psts').'</p></div>';
		}
		?>
		<div class="wrap">
    <div class="icon32" id="icon-plugins"></div>
    <h2><?php _e('Pro Sites Modules and Gateways', 'psts'); ?></h2>

    <form method="post" action="">
      <?php wp_nonce_field('psts_modules') ?>

      <h3><?php _e('Enable Modules', 'psts') ?></h3>
      <span class="description"><?php _e('Select the modules you would like to use below. You can then configure their options on the settings page.', 'psts') ?></span>
      <table class="widefat">
  			<thead>
  				<tr>
  					<th style="width: 15px;"><?php _e('Enable', 'psts') ?></th>
  					<th><?php _e('Module Name', 'psts') ?></th>
  					<th><?php _e('Description', 'psts') ?></th>
  				</tr>
  			</thead>
  			<tbody id="plugins">
        <?php
        $css = '';
        $css2 = '';
				uasort( $psts_modules, create_function('$a,$b', 'if ($a[0] == $b[0]) return 0;return ($a[0] < $b[0])? -1 : 1;') ); //sort modules by name
        foreach ((array)$psts_modules as $class => $plugin) {
          $css = ('alt' == $css) ? '' : 'alt';
          if ( in_array($class, (array)$this->get_setting('modules_enabled')) ) {
    				$css2 = ' active';
    				$active = true;
    			} else {
            $active = false;
					}

					?>
  				<tr valign="top" class="<?php echo $css.$css2; ?>">
  					<td style="text-align:center;">
       			<?php
	       		if ($plugin[2]) { //if demo
	          	?><input type="checkbox" id="psts_<?php echo $class; ?>" name="allowed_modules[]" value="<?php echo $class; ?>" disabled="disabled" /> <a class="psts-pro-update" href="http://premium.wpmudev.org/project/pro-sites" title="<?php _e('Upgrade', 'psts'); ?> &raquo;"><?php _e('Premium Only &raquo;', 'psts'); ?></a><?php
						} else {
	            ?><input type="checkbox" id="psts_<?php echo $class; ?>" name="allowed_modules[]" value="<?php echo $class; ?>"<?php checked($active); ?> /><?php
						}
      			?>
            </td>
       			<td><label for="psts_<?php echo $class; ?>"><?php echo esc_attr($plugin[0]); ?></label></td>
  					<td><?php echo esc_attr($plugin[1]); ?></td>
  				</tr>
    			<?php
				}
        ?>
  			</tbody>
  		</table>

      <h3><?php _e('Choose a Gateway', 'psts') ?></h3>
      <span class="description"><?php _e('Select the gateway you would like to enable below. You can then configure its options on the settings page.', 'psts') ?></span>
      <table class="widefat">
  			<thead>
  				<tr>
  					<th style="width: 15px;"><?php _e('Enable', 'psts') ?></th>
  					<th><?php _e('Gateway Name', 'psts') ?></th>
  					<th><?php _e('Description', 'psts') ?></th>
  				</tr>
  			</thead>
  			<tbody id="plugins">
        <?php
        foreach ((array)$psts_gateways as $class => $plugin) {
          $css = ('alt' == $css) ? '' : 'alt';
          if ( in_array($class, (array)$this->get_setting('gateways_enabled')) ) {
    				$css2 = ' active';
    				$active = true;
    			} else {
            $active = false;
					}

					?>
  				<tr valign="top" class="<?php echo $css.$css2; ?>">
  					<td style="text-align:center;">
       			<?php
	       		if ($plugin[2]) { //if demo
	          	?><input type="radio" id="psts_<?php echo $class; ?>" name="allowed_gateways[]" value="<?php echo $class; ?>" disabled="disabled" /> <a class="psts-pro-update" href="http://premium.wpmudev.org/project/pro-sites" title="<?php _e('Upgrade', 'psts'); ?> &raquo;"><?php _e('Premium Only &raquo;', 'psts'); ?></a><?php
						} else {
	            ?><input type="radio" id="psts_<?php echo $class; ?>" name="allowed_gateways[]" value="<?php echo $class; ?>"<?php checked($active); ?> /><?php
						}
      			?>
            </td>
  					<td><label for="psts_<?php echo $class; ?>"><?php echo esc_attr($plugin[0]); ?></label></td>
  					<td><?php echo esc_attr($plugin[1]); ?></td>
  				</tr>
    			<?php
				}
        ?>
  			</tbody>
  		</table>

      <?php do_action('psts_modules_page'); ?>

      <p class="submit">
        <input type="submit" name="submit_settings" class="button-primary" value="<?php _e('Save Changes', 'psts') ?>" />
      </p>
    </form>

		</div>
		<?php
	}

  function admin_settings() {
		global $wpdb;

		if ( !is_super_admin() ) {
			echo "<p>" . __('Nice Try...', 'psts') . "</p>";  //If accessed properly, this message doesn't appear.
			return;
		}

    //process form
	  if ( isset( $_POST['submit_settings'] ) ) {
	    //check nonce
      check_admin_referer('psts_settings');
			
			//strip slashes from all inputs
			$_POST['psts'] = stripslashes_deep($_POST['psts']);
			
			$_POST['psts']['hide_adminbar'] = isset($_POST['psts']['hide_adminbar']) ? $_POST['psts']['hide_adminbar'] : 0; //handle checkbox
			$_POST['psts']['hide_adminbar_super'] = isset($_POST['psts']['hide_adminbar_super']) ? $_POST['psts']['hide_adminbar_super'] : 0; //handle checkbox
      $_POST['psts']['show_signup'] = isset($_POST['psts']['show_signup']) ? $_POST['psts']['show_signup'] : 0; //handle checkbox

      //merge settings
      $old_settings = get_site_option('psts_settings');
      $settings = array_merge($old_settings, apply_filters('psts_settings_filter', $_POST['psts']));
      update_site_option('psts_settings', $settings);

			do_action('psts_settings_process');
			do_action('supporter_settings_process'); //depreciated

      //create a checkout page if not existing
      $this->create_checkout_page();

			echo '<div id="message" class="updated fade"><p>'.__('Settings Saved!', 'psts').'</p></div>';
		}
		$levels = (array)get_site_option( 'psts_levels' );
		?>
		<div class="wrap">
    <div class="icon32"><img src="<?php echo $this->plugin_url . 'images/settings.png'; ?>" /></div>
    <h2><?php _e('Pro Sites Settings', 'psts'); ?></h2>

		<div class="metabox-holder">
      <form method="post" action="">
      <?php wp_nonce_field('psts_settings') ?>

      <div class="postbox">
	      <h3 class='hndle'><span><?php _e('General Settings', 'psts') ?></span></h3>
	      <div class="inside">
	      	<table class="form-table">
	          <tr valign="top">
	          <th scope="row"><?php _e('Rebrand Pro Sites', 'psts') ?></th>
	          <td><input type="text" name="psts[rebrand]" value="<?php echo esc_attr($this->get_setting('rebrand')); ?>" />
	          <br /><?php _e('Rename "Pro Sites" for users to whatever you want like "Pro" or "Plus".', 'psts'); ?></td>
	          </tr>
						<tr valign="top">
	          <th scope="row"><?php _e('Admin Menu Button Labels', 'psts') ?></th>
	          <td>
							<label><input type="text" name="psts[lbl_signup]" value="<?php echo esc_attr($this->get_setting('lbl_signup')); ?>" /> <?php _e('Not Pro', 'psts'); ?></label><br />
							<label><input type="text" name="psts[lbl_curr]" value="<?php echo esc_attr($this->get_setting('lbl_curr')); ?>" /> <?php _e('Current Pro', 'psts'); ?></label>
						</td>
	          </tr>
						<tr valign="top">
	          <th scope="row"><?php _e('Hide Admin Bar Button', 'psts'); ?></th>
	          <td><label><input type="checkbox" name="psts[hide_adminbar]" value="1"<?php checked($this->get_setting('hide_adminbar')); ?> />
	          <?php _e('Remove the Pro Sites upgrade menu button from the admin bar', 'psts'); ?></label>
	          </td>
	          </tr>
						<tr valign="top">
	          <th scope="row"><?php _e('Hide Superadmin Admin Bar Pro Status', 'psts'); ?></th>
	          <td><label><input type="checkbox" name="psts[hide_adminbar_super]" value="1"<?php checked($this->get_setting('hide_adminbar_super')); ?> />
	          <?php _e('Remove the Super Admin Pro Site status menu from the admin bar', 'psts'); ?></label>
	          </td>
	          </tr>
						<tr valign="top">
	          <th scope="row"><?php _e('Free Level', 'psts') ?></th>
	          <td>
							<span class="description"><?php _e('Pro Sites has a built-in free level by default. Configure how this level is displayed on the checkout form:', 'psts') ?></span><br />
							<label><input type="text" name="psts[free_name]" value="<?php echo esc_attr($this->get_setting('free_name')); ?>" /> <?php _e('Free Level Name', 'psts'); ?></label><br />
							<label><input type="text" size="50" name="psts[free_msg]" value="<?php echo esc_attr($this->get_setting('free_msg')); ?>" /> <?php _e('Free Level Message', 'psts'); ?></label>
						</td>
	          </tr>
	          <tr valign="top">
	          <th scope="row"><?php _e('Show Option On Signup', 'psts'); ?></th>
	          <td><label><input type="checkbox" name="psts[show_signup]" value="1"<?php checked($this->get_setting('show_signup')); ?> />
	          <?php _e('Display an option on the signup page', 'psts'); ?></label>
	          <br /><?php _e('You can force and hide the signup option by linking to the signup page like this: ', 'psts'); ?><em>wp-signup.php?<?php echo sanitize_title($this->get_setting('rebrand')); ?>=1</em></td>
	          </tr>
	          <tr valign="top">
	          <th scope="row"><?php _e('Signup Message', 'psts') ?></th>
	          <td>
	          <textarea name="psts[signup_message]" rows="3" wrap="soft" id="signup_message" style="width: 95%"/><?php echo esc_textarea($this->get_setting('signup_message')); ?></textarea>
	          <br /><?php _e('Optional - HTML allowed - This message is displayed on the signup page if the box is checked above.', 'psts') ?></td>
	          </tr>
	          <tr valign="top">
	          <th scope="row"><?php _e('Checkout Page', 'psts') ?></th>
	          <td>
	          <?php _e('You can create a sales message that is shown at the top of the checkout page. (Hint - make it colorful with images and such!)', 'psts') ?>
	          <br /><a href="<?php echo get_edit_post_link($this->get_setting('checkout_page')); ?>" title="<?php _e('Edit Checkout Page &raquo;', 'psts'); ?>"><?php _e('Edit Checkout Page &raquo;', 'psts'); ?></a><br />
						<small><?php _e('If for some reason you need to regenerate the checkout page, simply trash the current page above then save this settings form. A new checkout page will be created with a slug and title based on the rebrand option above.', 'psts') ?></small></td>
	          </tr>
	          <tr valign="top">
	          <th scope="row"><?php _e('Pro Site Feature Message', 'psts') ?></th>
	          <td>
	          <input name="psts[feature_message]" type="text" id="feature_message" value="<?php echo esc_attr($this->get_setting('feature_message')); ?>" style="width: 95%" />
	          <br /><?php _e('Required - No HTML - This message is displayed when a feature is accessed on a site that does not have access to it. "LEVEL" will be replaced with the needed level name for the feature.', 'psts') ?></td>
	          </tr>
						<tr valign="top">
						<th scope="row"><?php _e('Free Trial', 'psts') ?></th>
						<td><select name="psts[trial_days]">
						<?php
						$trial_days = $this->get_setting('trial_days');
						for ( $counter = 0; $counter <=  365; $counter++) {
						  echo '<option value="' . $counter . '"' . ($counter == $trial_days ? ' selected' : '') . '>' . (($counter) ? $counter : __('Disabled', 'psts')) . '</option>' . "\n";
						}
						?>
						</select>
						<?php _e('Free days for all new sites.', 'psts'); ?></td>
						</tr>
						<tr valign="top">
					  <th scope="row"><?php _e('Free Trial Level', 'psts') ?></th>
					  <td>
					  <select name="psts[trial_level]">
						<?php
						foreach ($levels as $level => $value) {
							?><option value="<?php echo $level; ?>"<?php selected($this->get_setting('trial_level', 1), $level) ?>><?php echo $level . ': ' . esc_attr($value['name']); ?></option><?php
						}
						?>
		        </select>
		        <?php _e('Select the level given to sites during their trial period.', 'psts') ?>
						</td>
					  </tr>
						<tr valign="top">
						<th scope="row"><?php _e('Free Trial Message', 'psts') ?></th>
						<td><input type="text" name="psts[trial_message]" id="trial_message" value="<?php esc_attr_e($this->get_setting('trial_message')); ?>" style="width: 95%" />
						<br /><?php _e('Required - This message is displayed on the dashboard notifying how many days left in their free trial. "DAYS" will be replaced with the number of days left in the trial. "LEVEL" will be replaced with the needed level name.', 'psts') ?></td>
						</tr>
						<tr>
						<th scope="row"><?php _e('Google Analytics Ecommerce Tracking', 'psts') ?></th>
						<td>
						<select name="psts[ga_ecommerce]">
							<option value="none"<?php selected($this->get_setting('ga_ecommerce'), 'none') ?>><?php _e('None', 'psts') ?></option>
							<option value="new"<?php selected($this->get_setting('ga_ecommerce'), 'new') ?>><?php _e('Asynchronous Tracking Code', 'psts') ?></option>
							<option value="old"<?php selected($this->get_setting('ga_ecommerce'), 'old') ?>><?php _e('Old Tracking Code', 'psts') ?></option>
						</select>
						<br /><span class="description"><?php _e('If you already use Google Analytics for your website, you can track detailed ecommerce information by enabling this setting. Choose whether you are using the new asynchronous or old tracking code. Before Google Analytics can report ecommerce activity for your website, you must enable ecommerce tracking on the profile settings page for your website. <a href="http://analytics.blogspot.com/2009/05/how-to-use-ecommerce-tracking-in-google.html" target="_blank">More information &raquo;</a>', 'psts') ?></span>
						</td>
						</tr>
	          <?php do_action('psts_general_settings'); ?>
	        </table>
	      </div>
      </div>

      <div class="postbox">
        <h3 class='hndle'><span><?php _e('Email Notifications', 'psts') ?></span></h3>
        <div class="inside">
          <table class="form-table">
            <tr>
    				<th scope="row"><?php _e('Pro Site Signup', 'psts'); ?></th>
    				<td>
    				<span class="description"><?php _e('The email text sent to your customer to confirm a new Pro Site signup. "LEVEL" will be replaced with the site\'s level. "SITENAME" and "SITEURL" will also be replaced with their associated values. No HTML allowed.', 'psts') ?></span><br />
            <label><?php _e('Subject:', 'psts'); ?><br />
            <input class="pp_emails_sub" name="psts[success_subject]" value="<?php echo esc_attr($this->get_setting('success_subject')); ?>" maxlength="150" style="width: 95%" /></label><br />
            <label><?php _e('Message:', 'psts'); ?><br />
            <textarea class="pp_emails_txt" name="psts[success_msg]" style="width: 95%"><?php echo esc_textarea($this->get_setting('success_msg')); ?></textarea>
            </label>
            </td>
            </tr>
            <tr>
    				<th scope="row"><?php _e('Pro Site Canceled', 'psts'); ?></th>
    				<td>
    				<span class="description"><?php _e('The email text sent to your customer when they cancel their membership. "ENDDATE" will be replaced with the date when their Pro Site access ends. "LEVEL" will be replaced with the site\'s level. "SITENAME" and "SITEURL" will also be replaced with their associated values. No HTML allowed.', 'psts') ?></span><br />
            <label><?php _e('Subject:', 'psts'); ?><br />
            <input class="pp_emails_sub" name="psts[canceled_subject]" value="<?php echo esc_attr($this->get_setting('canceled_subject')); ?>" maxlength="150" style="width: 95%" /></label><br />
            <label><?php _e('Message:', 'psts'); ?><br />
            <textarea class="pp_emails_txt" name="psts[canceled_msg]" style="width: 95%"><?php echo esc_textarea($this->get_setting('canceled_msg')); ?></textarea>
            </label>
            </td>
            </tr>
            <tr>
    				<th scope="row"><?php _e('Payment Receipt', 'psts'); ?></th>
    				<td>
    				<span class="description"><?php _e('The email receipt text sent to your customer on every successful subscription payment. You must include the "PAYMENTINFO" code which will be replaced with payment details. "SITENAME" and "SITEURL" will also be replaced with their associated values. No HTML allowed.', 'psts') ?></span><br />
            <label><?php _e('Subject:', 'psts'); ?><br />
            <input class="pp_emails_sub" name="psts[receipt_subject]" value="<?php echo esc_attr($this->get_setting('receipt_subject')); ?>" maxlength="150" style="width: 95%" /></label><br />
            <label><?php _e('Message:', 'psts'); ?><br />
            <textarea class="pp_emails_txt" name="psts[receipt_msg]" style="width: 95%"><?php echo esc_textarea($this->get_setting('receipt_msg')); ?></textarea>
            </label>
            </td>
            </tr>
            <tr>
    				<th scope="row"><?php _e('Payment Problem', 'psts'); ?></th>
    				<td>
    				<span class="description"><?php _e('The email text sent to your customer when a scheduled payment fails. "LEVEL" will be replaced with the site\'s level. "SITENAME" and "SITEURL" will also be replaced with their associated values. No HTML allowed.', 'psts') ?></span><br />
            <label><?php _e('Subject:', 'psts'); ?><br />
            <input class="pp_emails_sub" name="psts[failed_subject]" value="<?php echo esc_attr($this->get_setting('failed_subject')); ?>" maxlength="150" style="width: 95%" /></label><br />
            <label><?php _e('Message:', 'psts'); ?><br />
            <textarea class="pp_emails_txt" name="psts[failed_msg]" style="width: 95%"><?php echo esc_textarea($this->get_setting('failed_msg')); ?></textarea>
            </label>
            </td>
            </tr>
            <?php do_action('psts_email_settings'); ?>
          </table>
        </div>
      </div>

      <div class="postbox">
        <h3 class='hndle'><span><?php _e('Currency Settings', 'psts') ?></span> - <span class="description"><?php _e('These preferences affect display only. Your payment gateway of choice may not support every currency listed here.', 'psts') ?></span></h3>
        <div class="inside">
          <table class="form-table">
    				<tr valign="top">
            <th scope="row"><?php _e('Currency Symbol', 'psts') ?></th>
    				<td>
              <select id="psts-currency-select" name="psts[currency]">
                <?php
                foreach ($this->currencies as $key => $value) {
                  ?><option value="<?php echo $key; ?>"<?php selected($this->get_setting('currency'), $key); ?>><?php echo esc_attr($value[0]) . ' - ' . $this->format_currency($key); ?></option><?php
                }
                ?>
              </select>
      				</td>
            </tr>
            <tr valign="top">
            <th scope="row"><?php _e('Currency Symbol Position', 'psts') ?></th>
            <td>
            <label><input value="1" name="psts[curr_symbol_position]" type="radio"<?php checked($this->get_setting('curr_symbol_position', 1), 1); ?>>
    				<?php echo $this->format_currency(); ?>100</label><br />
    				<label><input value="2" name="psts[curr_symbol_position]" type="radio"<?php checked($this->get_setting('curr_symbol_position'), 2); ?>>
    				<?php echo $this->format_currency(); ?> 100</label><br />
    				<label><input value="3" name="psts[curr_symbol_position]" type="radio"<?php checked($this->get_setting('curr_symbol_position'), 3); ?>>
    				100<?php echo $this->format_currency(); ?></label><br />
    				<label><input value="4" name="psts[curr_symbol_position]" type="radio"<?php checked($this->get_setting('curr_symbol_position'), 4); ?>>
    				100 <?php echo $this->format_currency(); ?></label>
            </td>
            </tr>
            <tr valign="top">
            <th scope="row"><?php _e('Show Decimal in Prices', 'psts') ?></th>
            <td>
            <label><input value="1" name="psts[curr_decimal]" type="radio"<?php checked($this->get_setting('curr_decimal', 1), 1); ?>>
    				<?php _e('Yes', 'psts') ?></label>
    				<label><input value="0" name="psts[curr_decimal]" type="radio"<?php checked($this->get_setting('curr_decimal'), 0); ?>>
    				<?php _e('No', 'psts') ?></label>
            </td>
            </tr>
          </table>
        </div>
      </div>

	    	<?php	do_action('psts_gateway_settings'); ?>
	      <?php do_action('psts_settings_page'); ?>

	        <p class="submit">
	        	<input type="submit" name="submit_settings" class="button-primary" value="<?php _e('Save Changes', 'psts') ?>" />
	        </p>
        </form>
      </div>
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
	  echo "<script type='text/javascript'>window.location='".$this->checkout_url($blog_id)."';</script>";
	  echo '<a href="'.$this->checkout_url($blog_id).'">Go Here</a>';
	  echo '</div>'; //div wrap
	}

	function checkout_grid($blog_id) {
		global $wpdb;

    $levels = (array)get_site_option('psts_levels');

		//if you want to display the lowest level first on checkout grid add define('PSTS_DONT_REVERSE_LEVELS', true); to your wp-config.php file
		if ( !(defined('PSTS_DONT_REVERSE_LEVELS') && PSTS_DONT_REVERSE_LEVELS) )
    	$levels = array_reverse($levels, true);

    $periods = (array)$this->get_setting('enabled_periods');
    $curr = $wpdb->get_row("SELECT term, level FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'");
    if ($curr) {
			$curr->term = ($curr->term && !is_numeric($curr->term)) ? $periods[0] : $curr->term; //if term not numeric
			$sel_period = isset($_POST['period']) ? $_POST['period'] : $curr->term;
			$sel_level = isset($_POST['level']) ? $_POST['level'] : $curr->level;
		} else {
			$curr->term = null;
			$curr->level = null;
			$sel_period = isset($_POST['period']) ? $_POST['period'] : (defined('PSTS_DEFAULT_PERIOD') ? PSTS_DEFAULT_PERIOD : null);
			$sel_level = isset($_POST['level']) ? $_POST['level'] : (defined('PSTS_DEFAULT_LEVEL') ? PSTS_DEFAULT_LEVEL : null);
		}

		if (count($periods) >= 3) {
		  $width = '23%';
			$free_width = '95%';
		} else if (count($periods) == 2) {
		  $width = '30%';
			$free_width = '92.5%';
		} else {
		  $width = '40%';
			$free_width = '85%';
		}

		$content = '';
		
		/*
		//show chosen blog
		$content .= '<div id="psts-chosen-blog">'.sprintf(__('You have chosen to upgrade <strong>%1$s</strong> (%2$s).', 'psts'), get_blog_option($blog_id, 'blogname'), get_blog_option($blog_id, 'siteurl')).'
								 <a id="psts-change-blog" href="'.$this->checkout_url().'">'.__('Change &raquo;', 'psts').'</a><br>
								</div>';
		*/

		$content = apply_filters('psts_before_checkout_grid_coupon', $content, $blog_id);

		//add coupon line
    if ( isset($_SESSION['COUPON_CODE']) ) {
      $coupon_value = $this->coupon_value($_SESSION['COUPON_CODE'], 100);
      $content .= '<div id="psts-coupon-msg">'.sprintf(__('Your coupon code <strong>%1$s</strong> has been applied for a discount of %2$s off the first payment. <a href="%3$s">Remove it &raquo;</a>', 'psts'), esc_html($_SESSION['COUPON_CODE']), $coupon_value['discount'], get_permalink()."?bid=$blog_id&remove_coupon=1").'</div>';
    } else if ($errmsg = $this->errors->get_error_message('coupon')) {
			$content .= '<div id="psts-coupon-error" class="psts-error">'.$errmsg.'</div>';
		}

    $content = apply_filters('psts_before_checkout_grid', $content, $blog_id);

    $content .= '<table id="psts_checkout_grid" width="100%">';
    $content .= '<tr class="psts_level_head">
				<th>'.__('Level', 'psts').'</th>';
			if (in_array(1, $periods))
			  $content .= '<th>'.__('Monthly', 'psts').'</th>';
			if (in_array(3, $periods))
    		$content .= '<th>'.__('Every 3 Months', 'psts').'</th>';
			if (in_array(12, $periods))
    		$content .= '<th>'.__('Every 12 Months', 'psts').'</th>';
			$content .= '</tr>';
		
		$equiv = '';
		$coupon_price = '';
		
		foreach ($levels as $level => $data) {
      $content .= '<tr class="psts_level level-'.$level.'">
				<td valign="middle" class="level-name">';
			$content .= apply_filters('psts_checkout_grid_levelname', '<h3>'.$data['name'].'</h3>', $level, $blog_id);
			$content .= '</td>';
			if (in_array(1, $periods)) {
			  $current = ($curr->term == 1 && $curr->level == $level) ? ' opt-current' : '';
			  $selected = ($sel_period == 1 && $sel_level == $level) ? ' opt-selected' : '';
     		if (isset($_SESSION['COUPON_CODE']) && $this->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $level) && $coupon_value = $this->coupon_value($_SESSION['COUPON_CODE'], $data['price_1'])) {
     			$coupon_price = '<span class="pblg-old-price">'.$this->format_currency(false, $data['price_1']).'</span> <span class="pblg-price">'.$this->format_currency(false, $coupon_value['new_total']).'</span>';
				} else {
          $coupon_price = '<span class="pblg-price">'. $this->format_currency(false, $data['price_1']) . '</span>';
				}

				if (in_array(3, $periods) || in_array(12, $periods)) {
					$equiv = '<span class="psts-equiv">'.__('Try it out!', 'psts').'</span>
	                  <span class="psts-equiv">'.__('You can easily upgrade to a better value plan at any time.', 'psts').'</span>';
				}
				$content .= '<td class="level-option" style="width: '.$width.'"><div class="pblg-checkout-opt'.$current.$selected.'">
										<input type="hidden" value="'.$level.':1"/>
										<input type="radio" name="psts-radio" class="psts-radio" id="psts-radio-1-'.$level.'" value="'.$level.':1" />
										<label for="psts-radio-1-'.$level.'">
										'.$coupon_price.'
										'.$equiv.'
										</label>
										</div></td>';
			}

   		if (in_array(3, $periods)) {
			  $current = ($curr->term == 3 && $curr->level == $level) ? ' opt-current' : '';
			  $selected = ($sel_period == 3 && $sel_level == $level) ? ' opt-selected' : '';
        if (isset($_SESSION['COUPON_CODE']) && $this->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $level) && $coupon_value = $this->coupon_value($_SESSION['COUPON_CODE'], $data['price_3'])) {
       		$coupon_price = '<span class="pblg-old-price">'.$this->format_currency(false, $data['price_3']).'</span> <span class="pblg-price">'.$this->format_currency(false, $coupon_value['new_total']).'</span>';
          $price = $coupon_value['new_total'];
				} else {
          $coupon_price = '<span class="pblg-price">'. $this->format_currency(false, $data['price_3']) . '</span>';
          $price = $data['price_3'];
				}

				$equiv = '<span class="psts-equiv">'.sprintf(__('Equivalent to only %s monthly', 'psts'), $this->format_currency(false, $price/3)).'</span>';
				if (in_array(1, $periods) && (($data['price_1']*3) - $price) > 0)
					$equiv .= '<span class="psts-equiv">'.sprintf(__('Save %s by paying for 3 months in advance!', 'psts'), $this->format_currency(false, ($data['price_1']*3) - $price)).'</span>';

				$content .= '<td class="level-option" style="width: '.$width.'"><div class="pblg-checkout-opt'.$current.$selected.'">
										<input type="hidden" value="'.$level.':3"/>
										<input type="radio" name="psts-radio" class="psts-radio" id="psts-radio-3-'.$level.'" value="'.$level.':3" />
										<label for="psts-radio-3-'.$level.'">
										'.$coupon_price.'
										'.$equiv.'
										</label>
										</div></td>';
      }

   		if (in_array(12, $periods)) {
			  $current = ($curr->term == 12 && $curr->level == $level) ? ' opt-current' : '';
			  $selected = ($sel_period == 12 && $sel_level == $level) ? ' opt-selected' : '';
        if (isset($_SESSION['COUPON_CODE']) && $this->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $level) && $coupon_value = $this->coupon_value($_SESSION['COUPON_CODE'], $data['price_12'])) {
       		$coupon_price = '<span class="pblg-old-price">'.$this->format_currency(false, $data['price_12']).'</span> <span class="pblg-price">'.$this->format_currency(false, $coupon_value['new_total']).'</span>';
          $price = $coupon_value['new_total'];
				} else {
          $coupon_price = '<span class="pblg-price">'. $this->format_currency(false, $data['price_12']) . '</span>';
          $price = $data['price_12'];
				}

				$equiv = '<span class="psts-equiv">'.sprintf(__('Equivalent to only %s monthly', 'psts'), $this->format_currency(false, $price/12)).'</span>';
				if (in_array(1, $periods) && (($data['price_1']*12) - $price) > 0)
					$equiv .= '<span class="psts-equiv">'.sprintf(__('Save %s by paying for a year in advance!', 'psts'), $this->format_currency(false, ($data['price_1']*12) - $price)).'</span>';

				$content .= '<td class="level-option" style="width: '.$width.'"><div class="pblg-checkout-opt'.$current.$selected.'">
								<input type="hidden" value="'.$level.':12"/>
								<input type="radio" name="psts-radio" class="psts-radio" id="psts-radio-12-'.$level.'" value="'.$level.':12" />
								<label for="psts-radio-12-'.$level.'">
								'.$coupon_price.'
								'.$equiv.'
								</label>
								</div></td>';
			}

			$content .= '</tr>';
		}
		
		$content = apply_filters('psts_checkout_grid_before_free', $content, $blog_id, $periods, $free_width);
		
		//show dismiss button link if needed
    if (get_blog_option($blog_id, 'psts_signed_up') && !apply_filters('psts_prevent_dismiss', false) ) {
			$content .= '<tr class="psts_level level-free">
				<td valign="middle" class="level-name"><h3>'.$this->get_setting('free_name', __('Free', 'psts')).'</h3></td>';
			$content .= '<td class="level-option" colspan="'.count($periods).'">';
      $content .= '<a class="pblg-checkout-opt" style="width: '.$free_width.'" id="psts-free-option" href="'.get_admin_url($blog_id, 'index.php?psts_dismiss=1', 'http').'" title="'.__('Dismiss', 'psts').'">'.$this->get_setting('free_msg', __('No thank you, I will continue with a basic site for now', 'psts')).'</a>';
      $content .= '</td></tr>';
    }
		
		$content = apply_filters('psts_checkout_grid_after_free', $content, $blog_id, $periods, $free_width);
		
  	$content .= '</table>
    						<input type="hidden" id="psts_period" name="period" value="' . $sel_period . '"/>
			      		<input type="hidden" id="psts_level" name="level" value="' . $sel_level . '"/>';

		//allow gateways to add accepted logos on the initial screen
    $content = apply_filters('psts_checkout_method_image', $content);

		//coupon form - if you want to hide the coupon box add define('PSTS_DISABLE_COUPON_FORM', true); to your wp-config.php file
		if ( !(defined('PSTS_DISABLE_COUPON_FORM') && PSTS_DISABLE_COUPON_FORM) ) {
	    $coupons = get_site_option('psts_coupons');
	    if ( is_array($coupons) && count($coupons) && !isset($_SESSION['COUPON_CODE']) ) {
		    $content .= '<div id="psts-coupon-block">
		      <small><a id="psts-coupon-link" href="#">'.__('Have a coupon code?', 'psts').'</a></small>
		      <div id="psts-coupon-code" class="alignright" style="display: none;">
		        <label for="coupon_code">'.__('Enter your code:', 'psts').'</label>
		        <input type="text" name="coupon_code" id="coupon_code" class="cctext" />&nbsp;
		        <input type="submit" name="coupon-submit" class="regbutton" value="'.__('Apply &raquo;', 'psts').'" />
		      </div>
		     </div>';
			}
		}

    return $content;
	}
	
	function receipt_form($blog_id) {
		$content = '';
		if ( !defined('PSTS_DISABLE_RECEIPT_FORM') && $this->last_transaction($blog_id) ) {
			
			if ( isset($_POST['psts_receipt']) ) {
        $this->email_notification($blog_id, 'receipt', $_POST['receipt_email']);
				$content .= '<div class="psts-updated">'.sprintf(__('Email receipt sent to %s.', 'psts'), esc_html($_POST['receipt_email'])).'</div>';
			} else {
				$content .= '<p id="psts-receipt-block">
					<form action="'.$this->checkout_url($blog_id).'" method="post" autocomplete="off">
					'.__('Email a receipt copy for your last payment to:', 'psts').' <span id="psts-receipt-change"><strong>' . get_blog_option($blog_id, 'admin_email') . '</strong> <small><a href="#">('.__('change', 'psts').')</a></small></span>
					<input type="text" id="psts-receipt-input" name="receipt_email" value="' . get_blog_option($blog_id, 'admin_email') . '" style="display: none;" /> 
					<input type="submit" name="psts_receipt" class="regbutton" value="'.__('Send &raquo;', 'psts').'" />
					</form></p>';
			}

		}

    return $content;
	}
	
	//outputs the checkout form
	function checkout_output($content) {

		//make sure we are in the loop and on current page loop item
		if (!in_the_loop() || get_queried_object_id() != get_the_ID())
			return $content;

	  //make sure logged in
	  if (!is_user_logged_in()) {
	    $content .= '<p>' . __('You must first login before you can choose a site to upgrade:', 'psts') . '</p>';
	    $content .= wp_login_form( array('echo' => false) );
	    return $content;
	  }

    //set blog_id
		if (isset($_POST['bid']))
		  $blog_id = intval($_POST['bid']);
		else if (isset($_GET['bid']))
		  $blog_id = intval($_GET['bid']);
    else
		  $blog_id = false;

	  if ($blog_id) {

	    //check for admin permissions for this blog
	    switch_to_blog($blog_id);
	    $permission = current_user_can('edit_pages');
	    restore_current_blog();
	    if (!$permission) {
	      $content = '<p>' . __('Sorry, but you do not have permission to upgrade this site. Only the site administrator can upgrade their site.', 'psts') . '</p>';
	      $content .= '<p><a href="' . $this->checkout_url() . '">&laquo; ' . __('Choose a different site', 'psts') . '</a></p>';
	      return $content;
	    }
			
			if ($this->get_expire($blog_id) > 2147483647) {
				$level = $this->get_level_setting($this->get_level($blog_id), 'name');
				$content = '<p>' . sprintf(__('This site has been permanently given %s status.', 'psts'), $level) . '</p>';
	      $content .= '<p><a href="' . $this->checkout_url() . '">&laquo; ' . __('Choose a different site', 'psts') . '</a></p>';
	      return $content;
			}
			
			//this is the main hook for gateways to add all their code
      $content = apply_filters('psts_checkout_output', $content, $blog_id);

	  } else { //blogid not set
	    $blogs = get_blogs_of_user(get_current_user_id());
	    if ($blogs) {
	      $content .= '<h3>' . __('Please choose a site to Upgrade or Modify:', 'psts') . '</h3>';
	      $content .= '<ul>';
	      foreach ($blogs as $blog) {

	        //check for permission
	        switch_to_blog($blog->userblog_id);
	        $permission = current_user_can('edit_pages');
	        restore_current_blog();
	        if (!$permission)
	          continue;

	        $has_blog = true;
					
					$level = $this->get_level($blog->userblog_id);
					$level_label = ($level) ? $this->get_level_setting($level, 'name') : sprintf(__('Not %s', 'psts'), $this->get_setting('rebrand'));
					$upgrade_label = is_pro_site($blog->userblog_id) ? sprintf(__('Modify "%s"', 'psts'), $blog->blogname) : sprintf(__('Upgrade "%s"', 'psts'), $blog->blogname);

	        $content .= '<li><a href="' . $this->checkout_url($blog->userblog_id) . '">' . $upgrade_label . '</a> (<em>' . $blog->siteurl . '</em>) - ' . $level_label . '</li>';
	      }
	      $content .= '</ul>';
	    }

	    //show message if no valid blogs
	    if (!$has_blog)
	      $content .= '<strong>' . __('Sorry, but it appears you are not an administrator for any sites.', 'psts') . '</strong>';

	  }

		return '<div id="psts-checkout-output">' . $content . '</div>'; //div wrap
	}


	/* exclude option from New Site Template plugin copy */
	function blog_template_settings( $and ) {
		$and .= " AND `option_name` != 'psts_signed_up' AND `option_name` != 'psts_action_log' AND `option_name` != 'psts_waiting_step' AND `option_name` != 'psts_payments_log' AND `option_name` != 'psts_used_coupons' AND `option_name` != 'psts_paypal_profile_id' AND `option_name` != 'psts_stripe_canceled' AND `option_name` != 'psts_withdrawn'";
		return $and;
	}
}

//load the class
global $psts;
$psts = new ProSites();


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
 * @return bool
 */
function is_pro_site($blog_id = false, $level = false) {
  global $psts;
	return $psts->is_pro_site($blog_id, $level);
}

/**
 * Check if a given user is a member of a Pro site (at any level)
 *
 * @since 3.0
 *
 * @param int $user_id optional - The ID of the user to check. Defaults to current user.
 * @return bool
 */
function is_pro_user($user_id = false) {
  global $psts;
	return $psts->is_pro_user($user_id);
}

/**
 * Check if a given site is in an active trial
 *
 * @since 3.0
 *
 * @param int $blog_id required - The ID of the site to check.
 * @return bool
 */
function is_pro_trial($blog_id) {
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
function psts_levels_select($name, $selected) {
	global $psts;
	$psts->levels_select($name, $selected);
}

//depreciated!
function is_supporter($blog_id = false) {
	return is_pro_site( $blog_id, apply_filters( 'psts_supporter_level', false ) );
}

//depreciated!
function is_supporter_user($user_id = '') {
  return is_pro_user( $user_id );
}

//depreciated!
function supporter_feature_notice() {
	global $psts;
	$psts->feature_notice();
}

//depreciated!
function supporter_get_expire($blog_id = false) {
	global $psts;
	return $psts->get_expire($blog_id);
}
?>