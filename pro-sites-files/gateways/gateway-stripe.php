<?php
/**
 * Pro Sites (Gateway: Stripe Payment Gateway).
 *
 * @package    Gateways
 * @subpackage Stripe
 */

// Include data class.
require_once 'gateway-stripe-files/data/stripe-data.php';

/**
 * Class ProSites_Gateway_Stripe.
 */
class ProSites_Gateway_Stripe {

	/**
	 * ID of the gateway.
	 *
	 * @var string
	 *
	 * @since 3.6.1
	 */
	private static $id = 'stripe';

	/**
	 * Stripe table name.
	 *
	 * @var string
	 *
	 * @since 3.6.1
	 */
	public static $table;

	/**
	 * Stripe API version.
	 *
	 * Make sure everyone is using the same API version. we can update this if/when necessary.
	 * If we don't set this, Stripe will use latest version, which may break our implementation.
	 * See https://stripe.com/docs/upgrades
	 *
	 * @var string
	 *
	 * @since 3.6.1
	 */
	private $api_version = '2018-11-08';

	/**
	 * Stripe public key for API.
	 *
	 * @var string|null
	 *
	 * @since 3.6.1
	 */
	private static $public_key;

	/**
	 * Stripe secret key for API.
	 *
	 * @var string|null
	 *
	 * @since 3.6.1
	 */
	private static $secret_key;

	/**
	 * Stripe plans custom class.
	 *
	 * @var \ProSites_Stripe_Plan
	 *
	 * @since 3.6.1
	 */
	public static $stripe_plan;

	/**
	 * Stripe customer custom class.
	 *
	 * @var \ProSites_Stripe_Customer
	 *
	 * @since 3.6.1
	 */
	public static $stripe_customer;

	/**
	 * Stripe subscription custom class.
	 *
	 * @var \ProSites_Stripe_Subscription
	 *
	 * @since 3.6.1
	 */
	public static $stripe_subscription;

	/**
	 * Stripe charge custom class.
	 *
	 * @var \ProSites_Stripe_Charge
	 *
	 * @since 3.6.1
	 */
	public static $stripe_charge;

	/**
	 * Current level of the site.
	 *
	 * This will be 0 if no site is set yet.
	 *
	 * @var int
	 *
	 * @since 3.6.1
	 */
	public static $level = 0;

	/**
	 * Current period of the site.
	 *
	 * This will be 0 if no site is set yet.
	 *
	 * @var int
	 *
	 * @since 3.6.1
	 */
	public static $period = 0;

	/**
	 * Current blog id.
	 *
	 * This will be 0 if no site is set yet.
	 *
	 * @var int
	 *
	 * @since 3.6.1
	 */
	private static $blog_id = 0;

	/**
	 * Current email address.
	 *
	 * @var string
	 *
	 * @since 3.6.1
	 */
	private static $email;

	/**
	 * Current site's domain.
	 *
	 * @var string
	 *
	 * @since 3.6.1
	 */
	private static $domain;

	/**
	 * Flag to check if the blog is existing.
	 *
	 * @var bool
	 *
	 * @since 3.6.1
	 */
	public static $existing = false;

	/**
	 * Flag to check if the blog is being upgraded.
	 *
	 * @var bool
	 *
	 * @since 3.6.1
	 */
	public static $upgrading = false;

	/**
	 * Flag to show/hide payment message.
	 *
	 * @var bool
	 *
	 * @since 3.6.1
	 */
	private static $show_completed = false;

	/**
	 * ProSites_Gateway_Stripe constructor.
	 */
	public function __construct() {
		global $wpdb, $psts;

		// Our stripe table.
		self::$table = $wpdb->base_prefix . 'pro_sites_stripe_customers';

		// Get Stripe API keys.
		self::$secret_key = $psts->get_setting( 'stripe_secret_key' );
		self::$public_key = $psts->get_setting( 'stripe_publishable_key' );

		// Setup the Stripe library.
		$this->init_lib();

		// Run installation script.
		$this->install();

		// Gateway settings.
		add_action( 'psts_gateway_settings', array( $this, 'settings' ) );
		add_action( 'psts_settings_process', array( $this, 'settings_process' ), 10 );

		// Handle webhook notifications.
		add_action( 'wp_ajax_nopriv_psts_stripe_webhook', array( $this, 'webhook_handler' ) );

		// Update plans in Stripe if necessary.
		add_action( 'update_site_option_psts_levels', array( $this, 'update_plans' ), 10, 2 );
		// One level deleted, sync with Stripe.
		add_action( 'psts_delete_level', array( $this, 'update_plans' ), 10, 2 );
		// New level added, sync with Stripe.
		add_action( 'psts_add_level', array( $this, 'update_plans' ), 10, 2 );

		// Cancel subscriptions on blog deletion.
		add_action( 'delete_blog', array( $this, 'delete_blog' ) );
		// Cancel subscription when gateway is changed from Stripe to something else.
		add_action( 'psts_gateway_change_from_stripe', array( $this, 'change_gateway' ) );
		// Cancelling subscription.
		add_action( 'psts_cancel_subscription_stripe', array( $this, 'cancel_blog' ), 10, 2 );

		// Should we force SSL?.
		add_filter( 'psts_force_ssl', array( $this, 'force_ssl' ) );

		// Manual reactivation.
		add_action( 'psts_attempt_stripe_reactivation', array( $this, 'manual_reactivation' ) );

		// Create transaction object.
		add_filter( 'prosites_transaction_object_create', array( $this, 'create_transaction_object' ), 10, 3 );

		// Site details page.
		add_action( 'psts_subscription_info', array( $this, 'subscription_info' ) );
		add_action( 'psts_subscriber_info', array( $this, 'subscriber_info' ) );
		add_filter( 'psts_current_plan_info_retrieved', array( $this, 'current_plan_info' ), 1, 4 );

		// Customer card update form.
		add_filter( 'prosites_myaccount_details', array( $this, 'render_update_form' ), 10, 2 );

		// Subscription modification form.
		add_action( 'psts_modify_form', array( $this, 'modification_form' ) );
		add_action( 'psts_modify_process', array( $this, 'process_modification' ) );

		// Process transfer.
		add_action( 'psts_transfer_pro', array( $this, 'process_transfer' ), 10, 2 );

		// Next payment date.
		add_filter( 'psts_next_payment', array( $this, 'next_payment_date' ) );

		// Show admin notices if required.
		add_action( 'admin_notices', array( &$this, 'admin_notices' ), 99 );

		// Set a flag if last payment is failed.
		add_filter( 'psts_blog_info_payment_failed', array( $this, 'payment_failed' ), 10, 2 );

		// Front end messages.
		add_filter( 'psts_blog_info_args', array( $this, 'messages' ), 10, 2 );
		add_filter( 'psts_render_notification_information', array( $this, 'messages' ), 10, 2 );

		// Register front end scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );

		// Get the expiry date.
		add_filter( 'psts_get_blog_subscription_expiry', array( $this, 'subscription_expiry' ), 10, 3 );
	}

	/**
	 * Register scripts and styles for checkout form.
	 *
	 * We don't enqueue the scripts and styles now. Let's do that
	 * when we render checkout form.
	 *
	 * @since 3.6.1
	 *
	 * @return void
	 */
	public function register_scripts() {
		global $psts;

		// Register custom checkout form script.
		wp_register_script(
			'psts-stripe-checkout-js',
			$psts->plugin_url . 'gateways/gateway-stripe-files/assets/js/checkout.js',
			array(),
			$psts->version,
			true
		);
	}

	/**
	 * Load Stripe PHP library.
	 *
	 * If Stripe library is not loaded already, load now.
	 *
	 * @since  3.6.1
	 * @access private
	 *
	 * @return void
	 */
	private function init_lib() {
		// Pro Sites global object.
		global $psts;

		// Setup the Stripe library and custom classes.
		if ( ! class_exists( 'Stripe\Stripe' ) ) {
			require_once $psts->plugin_dir . 'gateways/gateway-stripe-files/lib/init.php';
		}

		// Setup the Stripe library and custom classes.
		if ( class_exists( 'Stripe\Stripe' ) ) {
			require_once $psts->plugin_dir . 'gateways/gateway-stripe-files/stripe-plan.php';
			require_once $psts->plugin_dir . 'gateways/gateway-stripe-files/stripe-customer.php';
			require_once $psts->plugin_dir . 'gateways/gateway-stripe-files/stripe-subscription.php';
			require_once $psts->plugin_dir . 'gateways/gateway-stripe-files/stripe-charge.php';
		}

		// Set the sub class objects.
		self::$stripe_plan         = new ProSites_Stripe_Plan();
		self::$stripe_charge       = new ProSites_Stripe_Charge();
		self::$stripe_customer     = new ProSites_Stripe_Customer();
		self::$stripe_subscription = new ProSites_Stripe_Subscription();

		// We can not continue without API key.
		if ( ! empty( self::$secret_key ) ) {
			// Setup API key.
			Stripe\Stripe::setApiKey( self::$secret_key );
			// Set API version.
			Stripe\Stripe::setApiVersion( $this->api_version );
			// Set app info. This name should not be translated.
			Stripe\Stripe::setAppInfo( 'Pro Sites', $psts->version, network_home_url() );
		}
	}

	/**
	 * Get gateway slug/name.
	 *
	 * Return the unique id of the gateway.
	 *
	 * @return string
	 */
	public static function get_slug() {
		return self::$id;
	}

	/**
	 * Get gateway's title name.
	 *
	 * @since 3.6.1
	 *
	 * @return array
	 */
	public static function get_name() {
		return array(
			'stripe' => __( 'Stripe', 'psts' ),
		);
	}

	/**
	 * Install and upgrade functions.
	 *
	 * Run the installation or upgrade functions
	 * when the plugin is installed for the first time
	 * or updated to a new version.
	 *
	 * @access private
	 * @since  3.6.1
	 *
	 * @return void
	 */
	private function install() {
		global $psts;

		// Current Stripe version.
		$stripe_version = $psts->get_setting( 'stripe_version' );

		// Update install script if necessary.
		if ( empty( $stripe_version ) || $stripe_version !== $psts->version ) {
			// Create or upgrade tables.
			$this->create_tables();

			// Upgrade and Sync plans to Stripe.
			$this->update_plans();

			// Update the version.
			$psts->update_setting( 'stripe_version', $psts->version );
		}
	}

	/**
	 * Perform actions when 'Pro Sites' > 'Settings' update.
	 *
	 * In this example, update Stripe plans with Pro Sites levels.
	 *
	 * @param string $gateway Current gateway.
	 *
	 * @return void
	 */
	public function settings_process( $gateway ) {
		// If current gateway is Stripe, update the plans.
		if ( get_class() === $gateway ) {
			$this->update_plans( get_site_option( 'psts_levels' ) );
		}
	}

	/**
	 * Delete a particular blog and it's subscription.
	 *
	 * @param int $blog_id Blog ID.
	 *
	 * @since 3.6.1
	 *
	 * @return void
	 */
	public function delete_blog( $blog_id ) {
		// Cancel the blog subscription.
		self::$stripe_subscription->cancel_blog_subscription( $blog_id, true, true, true );

		// Delete the blog data from DB.
		self::$stripe_customer->delete_db_customer( $blog_id );

		// Clear all Pro Sites cache.
		ProSites_Helper_Cache::refresh_cache();
	}

	/**
	 * Cancel a particular blog's subscription.
	 *
	 * @param int  $blog_id         Blog ID.
	 * @param bool $display_message Display cancellation message.
	 *
	 * @since 3.6.1
	 *
	 * @return void
	 */
	public function cancel_blog( $blog_id, $display_message = false ) {
		// Cancel the blog subscription.
		self::$stripe_subscription->cancel_blog_subscription( $blog_id, true, false, $display_message );

		// Clear all Pro Sites cache.
		ProSites_Helper_Cache::refresh_cache();
	}

	/**
	 * Cancel Stripe subscription when gateway is changed.
	 *
	 * @param int $blog_id Blog ID.
	 *
	 * @since 3.6.1
	 *
	 * @return void
	 */
	public function change_gateway( $blog_id ) {
		// Cancel the blog subscription in Stripe.
		self::$stripe_subscription->cancel_blog_subscription( $blog_id );

		// Clear all Pro Sites cache.
		ProSites_Helper_Cache::refresh_cache();
	}

	/**
	 * Settings page for the Stripe gateway.
	 *
	 * Renders the Stripe settings for 'Pro Sites' > 'Payment Gateways' > 'Stripe'.
	 * Settings markup is in separate view file.
	 *
	 * @since 3.6.1
	 *
	 * @return void
	 */
	public function settings() {
		include_once 'gateway-stripe-files/views/admin/settings.php';
	}

	/**
	 * Update Stripe plans based on the levels.
	 *
	 * If levels changed, we need to make this happen in Stripe
	 * also. All the existing subscriptions will remain same unless
	 * it is updated.
	 *
	 * @param array $levels     New levels.
	 * @param array $old_levels Old levels.
	 *
	 * @since  3.6.1
	 * @access private
	 *
	 * @return void
	 */
	public function update_plans( $levels = array(), $old_levels = array() ) {
		// If levels are not given, get from db.
		if ( empty( $levels ) ) {
			$levels = get_site_option( 'psts_levels', array() );
		}

		// If old levels are not given, get from db.
		if ( empty( $old_levels ) ) {
			$old_levels = get_site_option( 'psts_levels', array() );
		}

		// No plans. Oh boy.
		if ( empty( $levels ) ) {
			return;
		}

		// Sync levels.
		self::$stripe_plan->sync_levels( $levels, $old_levels );
	}

	/**
	 * Create custom table for the Stripe gateway.
	 *
	 * We need a custom table to store Stripe customer ID and
	 * subscription ID for the blog.
	 *
	 * @since  3.6.1
	 * @aceess private
	 *
	 * @return void
	 */
	private function create_tables() {
		global $psts;

		$table = self::$table;

		// Stripe table schema.
		$table = "CREATE TABLE $table (
		  blog_id bigint(20) NOT NULL,
			customer_id char(20) NOT NULL,
			subscription_id char(22) NULL,
			PRIMARY KEY  (blog_id),
			UNIQUE KEY ix_subscription_id (subscription_id)
		) DEFAULT CHARSET=utf8;";

		// We can continue only if upgrade is not disabled.
		if ( ! defined( 'DO_NOT_UPGRADE_GLOBAL_TABLES' ) || ! DO_NOT_UPGRADE_GLOBAL_TABLES ) {
			// Make sure we have dbDelta function.
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// Using dbDelta we won't break anything.
			dbDelta( $table );

			// Modify stripe customers table, if we have latest version.
			if ( version_compare( $psts->version, '3.5.9.3', '>=' ) ) {
				// Handle failed upgrades.
				$this->upgrade_table_indexes();
			}
		}
	}

	/**
	 * Upgrade stripe customers table structure.
	 *
	 * We are using dbDelta in install() method to create and
	 * upgrade stripe customers database table. But if the PS
	 * plugin was installed before version 3.5, there is a chance
	 * that the upgrade will fail to set unique id for subscription id.
	 * And the old customer id will still be a unique id.
	 * We need to drop the old unique id and then add new unique id.
	 * Also we need to make unique id nullable, because we may get error
	 * for old entries without subscription id.
	 *
	 * @since  3.6.0
	 * @access private
	 *
	 * @return void
	 */
	private function upgrade_table_indexes() {
		global $wpdb;

		$table = self::$table;

		// Get all indexes on customer id and subscription id.
		$indexes = $wpdb->get_results( "SHOW INDEX FROM $table WHERE column_name = 'customer_id'" );
		if ( ! empty( $indexes ) ) {
			foreach ( $indexes as $index ) {
				// If it is a unique key, drop it.
				if ( empty( $index->Non_unique ) ) {
					$wpdb->query( "ALTER TABLE $table DROP INDEX $index->Key_name" );
				}
			}
		}

		// Sometimes old installation may have empty subscription ids, so we need to make sure nullable.
		$wpdb->query( "ALTER TABLE $table CHANGE subscription_id subscription_id char(22) NULL" );
		// Make sure all empty subscription IDs are NULL. dbDelta will not hable this.
		$wpdb->query( "UPDATE $table SET subscription_id = NULL WHERE subscription_id = ''" );

		// If unique key is not set for subscription id, set.
		$index_exists = $wpdb->query( "SHOW INDEX FROM $table WHERE KEY_NAME = 'ix_subscription_id'" );
		if ( empty( $index_exists ) ) {
			// Set unique key to subscription id.
			$wpdb->query( "ALTER TABLE $table ADD UNIQUE KEY ix_subscription_id (subscription_id)" );
		}
	}

	/**
	 * Get current active global currency.
	 *
	 * @since 3.6.1
	 *
	 * @return string
	 */
	public static function get_currency() {
		global $psts;

		// Get current currency.
		return $psts->get_setting( 'currency', 'USD' );
	}

	/**
	 * Get supported currencies.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public static function get_supported_currencies() {
		// Get the supported currencies.
		return ProSites_Stripe_Data::currencies();
	}

	/**
	 * Get the supported countries.
	 *
	 * @since 3.6.1
	 *
	 * @return array
	 */
	public static function get_merchant_countries() {
		// Get the merchant countries.
		return ProSites_Stripe_Data::countries();
	}

	/**
	 * Force SSL if not enabled.
	 *
	 * @param bool $force Should force?.
	 *
	 * @since 3.5.0
	 *
	 * @return bool
	 */
	public function force_ssl( $force ) {
		global $psts;

		// Should force?.
		if ( (bool) $psts->get_setting( 'stripe_ssl', false ) ) {
			return true;
		}

		return $force;
	}

	/**
	 * Get the information for the Subscription metabox when managing Pro Site.
	 *
	 * @param int $blog_id Blog ID.
	 *
	 * @since 3.0
	 *
	 * @return void
	 */
	public function subscription_info( $blog_id ) {
		// We don't have to continue if the site is using another gateway.
		if ( ! ProSites_Helper_Gateway::is_last_gateway_used( $blog_id, self::get_slug() ) ) {
			return;
		}

		global $psts;

		// Get Stripe customer and subscription data.
		$customer_data = self::$stripe_customer->get_db_customer( $blog_id );

		// Get the Stripe customer.
		$customer = self::$stripe_customer->get_customer( $customer_data->customer_id );

		// Continue only if customer found.
		if ( empty( $customer ) ) {
			// Show message.
			esc_html_e( 'This site is using different gateway so their information is not accessible.', 'psts' );

			return;
		}

		// Cancellation flag.
		$is_cancelled = get_blog_option( $blog_id, 'psts_stripe_canceled' );

		// Default card.
		$card = self::$stripe_customer->default_card( $customer->id );

		// Last invoice.
		$last_invoice = self::$stripe_customer->last_invoice( $customer->id );

		// Expiry date.
		$expire = $psts->get_expire( $blog_id );

		// File that contains subscription info.
		include_once 'gateway-stripe-files/views/admin/subscription-info.php';
	}

	/**
	 * Get the information for the subscriber metabox when managing Pro Site.
	 *
	 * @param int $blog_id Blog ID.
	 *
	 * @since 3.0
	 *
	 * @return void
	 */
	public function subscriber_info( $blog_id ) {
		// We don't have to continue if the site is using another gateway.
		if ( ! ProSites_Helper_Gateway::is_last_gateway_used( $blog_id, self::get_slug() ) ) {
			return;
		}

		// Get the Stripe customer.
		$customer = self::$stripe_customer->get_customer_by_blog( $blog_id );

		// Continue only if customer found.
		if ( empty( $customer ) ) {
			// Show message.
			esc_html_e( 'This site is using different gateway so their information is not accessible.', 'psts' );

			return;
		}

		// Default card.
		$card = self::$stripe_customer->default_card( $customer->id );

		// File that contains subscription info.
		include_once 'gateway-stripe-files/views/admin/subscriber-info.php';
	}

	/**
	 * Fetch the next billing date for the subscription.
	 *
	 * @param int    $expiry  Expiry date.
	 * @param int    $blog_id Blog ID.
	 * @param string $gateway Current gateway.
	 *
	 * @since 3.6.1
	 *
	 * @return int
	 */
	public function subscription_expiry( $expiry, $blog_id, $gateway ) {
		// Continue only if Stripe and a valid blog id is found.
		if ( self::get_slug() !== $gateway || empty( $blog_id ) ) {
			return $expiry;
		}

		// Try to get the Stripe subscription.
		$subscription = self::$stripe_subscription->get_subscription_by_blog( $blog_id );
		// If we have a subscription.
		if ( ! empty( $subscription->current_period_end ) ) {
			return $subscription->current_period_end;
		}

		return $expiry;
	}

	/**
	 * Show payment completed message on front end.
	 *
	 * @param array $messages Current messages.
	 * @param int   $blog_id  Blog ID.
	 *
	 * @since 3.6.1
	 *
	 * @return string
	 */
	public function messages( $messages, $blog_id ) {
		global $psts;

		// Site status flags.
		$pending   = (bool) get_blog_option( $blog_id, 'psts_stripe_waiting' );
		$cancelled = (bool) get_blog_option( $blog_id, 'psts_stripe_canceled' );
		$end_date  = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );

		// Show pending message if we need data from Stripe.
		if ( $pending && ! $cancelled ) {
			$messages['pending'] = '<div id="psts-general-error" class="psts-warning">' . __( 'There are pending changes to your account. This message will disappear once these pending changes are completed.', 'psts' ) . '</div>';
		}

		// Show payment completed message after registration.
		if ( self::$show_completed ) {
			$messages['complete_message']   = '<div id="psts-complete-msg">' . __( 'Your payment was successfully recorded! You should be receiving an email receipt shortly.', 'psts' ) . '</div>';
			$messages['thanks_message']     = '<p>' . $psts->get_setting( 'stripe_thankyou' ) . '</p>';
			$messages['visit_site_message'] = '<p><a href="' . get_admin_url( $blog_id, '', 'http' ) . '">' . __( 'Go to your site &raquo;', 'psts' ) . '</a></p>';
		}

		// Show cancellation message if required.
		if ( ! empty( $messages['cancel'] ) ) {
			$messages['cancellation_message'] = '<div class="psts-warning"><p>' . sprintf( __( 'Your %1$s subscription has been canceled. You should continue to have access until %2$s.', 'psts' ), $psts->get_setting( 'rebrand' ), $end_date ) . '</p></div>';
		}

		return $messages;
	}

	/**
	 * Show subscription details on front end.
	 *
	 * @param array  $info    Current info data.
	 * @param int    $blog_id Current blog id.
	 * @param string $domain  Current domain.
	 * @param string $gateway Current gateway.
	 *
	 * @since 3.6.1
	 *
	 * @return array $info
	 */
	public function current_plan_info( $info, $blog_id, $domain, $gateway ) {
		// Continue only if Stripe is the gateway.
		if ( self::get_slug() !== $gateway ) {
			return $info;
		}

		// Get the available data.
		$info = self::$stripe_plan->plan_info( $blog_id, $info );

		return $info;
	}

	/**
	 * Show admin notices for Pro Sites.
	 *
	 * If payment notifications are pending from Stripe, or if current
	 * site is on trial, show admin notice.
	 *
	 * @since 3.0
	 *
	 * @return void
	 */
	public function admin_notices() {
		global $psts;

		// Get current blog id.
		$blog_id = get_current_blog_id();

		// We don't have to show this on main site.
		if ( is_main_site( $blog_id ) ) {
			return;
		}

		// Site status flag.
		$pending = (bool) get_blog_option( $blog_id, 'psts_stripe_waiting' );

		// Show alert message if we are waiting for webhook.
		if ( $pending ) {
			echo '<div class="updated">';
			echo '<p><strong>';
			// Show trial message.
			if ( ProSites_Helper_Registration::is_trial( $blog_id ) ) {
				// translators: %1$s Plan name, %2$s Expiry date.
				printf( esc_html__( 'You are currently signed up for your chosen plan, %1$s. The first payment is due on %2$s. Enjoy your free trial.', 'psts' ),
					$psts->get_level_setting( $psts->get_level( $blog_id ), 'name' ),
					self::format_date( $psts->get_expire( $blog_id ) )
				);
			} else {
				esc_html_e( 'There are pending changes to your account. This message will disappear once these pending changes are completed.', 'psts' );
			}
			echo '</strong></p>';
			echo '</div>';
		}
	}

	/**
	 * Modify the payment failed flag.
	 *
	 * If we receive payment failed webhook from Stripe,
	 * set the flag to true.
	 *
	 * @param bool $failed  Is failed?.
	 * @param int  $blog_id Blog ID.
	 *
	 * @since 3.0
	 *
	 * @return bool
	 */
	public function payment_failed( $failed, $blog_id ) {
		// Check for the failed flag.
		if ( (bool) get_blog_option( $blog_id, 'psts_stripe_payment_failed' ) ) {
			$failed = true;
		}

		return $failed;
	}

	/**
	 * Timestamp of next payment if subscription active, else return false.
	 *
	 * Catch, Stripe only does the next customer invoice and may not include
	 * the subscription of this site. So in that case we will not get the
	 * subscription.
	 *
	 * @param int $blog_id Blog ID.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|null
	 */
	public function next_payment_date( $blog_id ) {
		$next_billing = false;

		// Get customer and subcription ids from DB.
		$customer_data = self::$stripe_customer->get_db_customer( $blog_id );

		// Continue only if not cancelled and customer data is found.
		if ( $customer_data && (bool) get_blog_option( $blog_id, 'psts_stripe_canceled' ) ) {
			// Get upcoming invoice.
			$upcoming_invoice = self::$stripe_customer->upcoming_invoice( $customer_data->customer_id );
			if ( isset( $upcoming_invoice->lines->data ) ) {
				// Loop through all line items to get subscription.
				foreach ( $upcoming_invoice->lines->data as $line_item ) {
					// If a subscription item is found break.
					if ( 'subscription' === $line_item->type ) {
						// Make sure the subscription is matching.
						if ( $customer_data->subscription_id === $line_item->subscription ) {
							$next_billing = $upcoming_invoice->next_payment_attempt;
						}

						break;
					}
				}
			}
		}

		return $next_billing;
	}

	/**
	 * Renders the modify Pro Site status content for Stripe.
	 *
	 * @param int $blog_id Current blog id.
	 *
	 * @since 3.6.1
	 *
	 * @return void
	 */
	public function modification_form( $blog_id ) {
		// We don't have to continue if the site is using another gateway.
		if ( ! ProSites_Helper_Gateway::is_last_gateway_used( $blog_id, self::get_slug() ) ) {
			return;
		}

		global $psts;

		// Expiry date of blog.
		$expiry_date = self::format_date( $psts->get_expire( $blog_id ) );
		// Is blog cancelled?.
		$cancelled = (bool) get_blog_option( $blog_id, 'psts_stripe_canceled' );
		// Get customer data.
		$customer_data = self::$stripe_customer->get_db_customer( $blog_id );

		// Continue only if customer id found.
		if ( ! empty( $customer_data->customer_id ) ) {
			// Last invoice.
			$last_invoice = self::$stripe_customer->last_invoice( $customer_data->customer_id );
			// Last payment amount.
			$last_payment = empty( $last_invoice->total ) ? false : self::format_price( $last_invoice->total, false );

			// File that contains modify form.
			include_once 'gateway-stripe-files/views/admin/modify-form.php';
		}
	}

	/**
	 * Process the subscription modification request.
	 *
	 * @param int $blog_id Blog ID.
	 *
	 * @since 3.6.1
	 *
	 * @return void
	 */
	public function process_modification( $blog_id ) {
		global $psts, $current_user;

		// Current action.
		$action = self::from_request( 'stripe_mod_action' );

		// First we need to clear caches.
		ProSites_Helper_Cache::refresh_cache();

		// Get customer and subcription ids from DB.
		$customer_data = self::$stripe_customer->get_db_customer( $blog_id );

		// We need an action man!.
		if ( empty( $action ) || empty( $customer_data->customer_id ) ) {
			return;
		}

		// Last invoice.
		$last_invoice = self::$stripe_customer->last_invoice( $customer_data->customer_id );
		// Last Stripe charge of the invoice.
		$last_charge = empty( $last_invoice->charge ) ? false : $last_invoice->charge;
		// Last charge amount.
		$last_charge_amount = empty( $last_charge->amount ) ? 0 : self::format_price( $last_charge->amount );
		// Last invoice total.
		$last_invoice_total = empty( $last_invoice->total ) ? 0 : self::format_price( $last_invoice->total, false );
		// Current user's name.
		$display_name = $current_user->display_name;
		// Default messages.
		$success_message = $error_message = $skip_log = false;

		switch ( $action ) {
			case 'cancel':
				// Cancel the blog subscription.
				if ( self::$stripe_subscription->cancel_blog_subscription( $blog_id ) ) {
					// End date of site.
					$end_date = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
					// Log the success message.
					$success_message = sprintf( __( 'Subscription successfully cancelled by %1$s. They should continue to have access until %2$s', 'psts' ), $display_name, $end_date );
				} else {
					// Log the failed message.
					$error_message = sprintf( __( 'Attempt to Cancel Subscription by %1$s failed', 'psts' ), $display_name );
				}
				$skip_log = true;
				break;

			case 'cancel_refund':
				// First cancel the subscription.
				if ( self::$stripe_subscription->cancel_blog_subscription( $blog_id ) && $last_charge ) {
					$refund_amount = $psts->format_currency( false, $last_invoice_total );
					// Now process the refund.
					if ( self::$stripe_charge->refund_charge( $last_charge, false, $error ) ) {
						// Log the success message.
						$success_message = sprintf( __( 'A full (%1$s) refund of last payment completed by %2$s.', 'psts' ), $refund_amount, $display_name );
					} else {
						// Log the failed message.
						$error_message = sprintf( __( 'Attempt to issue a full (%1$s) refund of last payment by %2$s is failed with an error: %3$s', 'psts' ), $refund_amount, $display_name, $error );
					}
				}
				break;

			case 'refund':
				$refund_amount = $psts->format_currency( false, $last_invoice_total );
				// Process the refund.
				if ( self::$stripe_charge->refund_charge( $last_charge, false, $error ) ) {
					// Log the transaction.
					$psts->record_refund_transaction( $blog_id, $last_charge->id, $last_charge_amount );
					// Log the success message.
					$success_message = sprintf( __( 'A full (%1$s) refund of last payment completed by %2$s, and the subscription was not cancelled.', 'psts' ), $refund_amount, $display_name );
				} else {
					// Log the failed message.
					$error_message = sprintf( __( 'Attempt to issue a full (%1$s) refund of last payment by %2$s is failed with an error: %3$s', 'psts' ), $refund_amount, $display_name, $error );
				}
				break;

			case 'partial_refund':
				// Refund amount.
				$refund_amount = self::from_request( 'refund_amount' );
				// Format the price.
				$refund_amount = self::format_price( $refund_amount );
				// Process the partial refund.
				if ( $refund_amount ) {
					$refund_amount_formatted = $psts->format_currency( false, $refund_amount );
					// Process the refund.
					if ( self::$stripe_charge->refund_charge( $last_charge, $refund_amount, $error ) ) {
						// Log the transaction.
						$psts->record_refund_transaction( $blog_id, $last_charge->id, $refund_amount );
						// Log the success message.
						$success_message = sprintf( __( 'A partial (%1$s) refund of last payment completed by %2$s The subscription was not cancelled.', 'psts' ), $refund_amount_formatted, $display_name );
					} else {
						// Log the failed message.
						$error_message = sprintf( __( 'Attempt to issue a partial (%1$s) refund of last payment by %2$s is failed with an error: %3$s', 'psts' ), $refund_amount_formatted, $display_name, $error );
					}
				}
				break;
		}

		if ( ! empty( $success_message ) ) {
			// Log success message.
			if ( ! $skip_log ) {
				$psts->log_action( $blog_id, $success_message );
			}
			echo '<div class="updated fade"><p>' . $success_message . '</p></div>';
		} elseif ( ! empty( $error_message ) ) {
			// Log error message.
			if ( ! $skip_log ) {
				$psts->log_action( $blog_id, $error_message );
			}
			echo '<div class="error fade"><p>' . $error_message . '</p></div>';
		}
	}

	/**
	 * Handle transferring pro status from one blog to another.
	 *
	 * Also updates the subscription metadata in Stripe.
	 *
	 * @param int $from_id From blog id.
	 * @param int $to_id   To blog id.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function process_transfer( $from_id, $to_id ) {
		// Seriously? Why? How?.
		if ( $from_id === $to_id ) {
			return false;
		}

		// First we need to clear caches.
		ProSites_Helper_Cache::refresh_cache();

		// Get the customer data.
		$customer_data = self::$stripe_customer->get_db_customer( $from_id );

		// If we have a subscription id.
		if ( ! empty( $customer_data->subscription_id ) ) {
			// Update the subscription meta in Stripe.
			self::$stripe_subscription->transfer_blog_subscription( $customer_data->subscription_id, $to_id );
		}

		// Ok, transfer db data.
		if ( ! empty( $customer_data->blog_id ) && $customer_data->blog_id === $from_id ) {
			self::$stripe_customer->transfer_db_customer( $to_id, $from_id );
		}

		return true;
	}

	/**
	 * Render Stripe card update form.
	 *
	 * For existing customers, render a separate form to update
	 * their card for the subscription.
	 *
	 * @param string $content Additional content.
	 * @param int    $blog_id Blog ID.
	 *
	 * @since 3.6.1
	 *
	 * @return string
	 */
	public function render_update_form( $content, $blog_id ) {
		global $current_site, $psts;

		// Oh hey, we need blog id.
		if ( empty( $blog_id ) || (bool) get_blog_option( $blog_id, 'psts_stripe_canceled' ) ) {
			return $content;
		}

		// Get Stripe customer object.
		$customer = self::$stripe_customer->get_customer_by_blog( $blog_id );
		// Continue only if customer is not found.
		if ( empty( $customer ) ) {
			return $content;
		}

		// Turn on output buffering.
		ob_start();

		// Set Stripe API keys and other config options.
		wp_localize_script( 'psts-stripe-checkout-js', 'psts_stripe', array(
			'publisher_key' => self::$public_key,
			'locale'        => get_locale(),
			'email'         => self::get_email(),
			'image'         => get_site_icon_url( 512, '', 1 ),
			'name'          => $current_site->site_name,
			'description'   => __( 'Update your card details', 'psts' ),
		) );

		// Form action url.
		$url = add_query_arg( array( 'update_stripe_card' => 1 ), $psts->checkout_url( $blog_id ) );

		// File that contains checkout form.
		include_once 'gateway-stripe-files/views/frontend/card-update.php';

		// Get the content as a string.
		$content = ob_get_clean();

		return $content;
	}

	/**
	 * Render the gateway form in front end.
	 *
	 * Render the content of Stripe gateway tab in front end.
	 *
	 * @param array  $render_data Data for render.
	 * @param array  $args        Arguments for the form.
	 * @param int    $blog_id     Blog ID.
	 * @param string $domain      Site domain.
	 *
	 * @since 3.6.1
	 *
	 * @return string
	 */
	public static function render_gateway( $render_data = array(), $args, $blog_id, $domain ) {
		global $psts, $current_user;

		// First we need to clear caches.
		ProSites_Helper_Cache::refresh_cache();

		// If there were any errors in checkout.
		self::set_errors();

		// Get the error messages.
		$error_messages = empty( $psts->errors ) ? false : $psts->errors->get_error_message( 'stripe' );

		// Set new/upgrading blog data to render data array.
		foreach ( array( 'new_blog_details', 'upgraded_blog_details', 'activation_key' ) as $key ) {
			$render_data[ $key ] = isset( $render_data[ $key ] ) ? $render_data[ $key ] : ProSites_Helper_Session::session( $key );
		}

		// Stripe publishable key.
		$public_key = self::$public_key;

		// Set the default values.
		$activation_key = $user_name = $new_blog = $customer = $card = $sub_id = false;

		// New blog data.
		$blog_data = empty( $render_data['new_blog_details'] ) ? array() : $render_data['new_blog_details'];

		// Set period and levels.
		$period = empty( $blog_data['period'] ) ? ProSites_Helper_ProSite::default_period() : (int) $blog_data['period'];
		$level  = empty( $blog_data['level'] ) ? 0 : (int) $blog_data['level'];
		$level  = empty( $render_data['upgraded_blog_details']['level'] ) ? $level : (int) $render_data['upgraded_blog_details']['level'];
		// We need to get the email.
		$email = self::get_email( $render_data );

		// Current action.
		$action = self::from_request( 'action', false, 'get' );

		// Set a flag that it is new blog.
		if ( ProSites_Helper_ProSite::allow_new_blog() && ( self::from_request( 'new_blog' ) || 'new_blog' === $action ) ) {
			$new_blog = true;
		}

		// If blog id is found in url.
		$bid = self::from_request( 'bid', $blog_id, 'get' );

		if ( ! empty( $bid ) ) {
			// Get customer data from DB.
			$customer_data = self::$stripe_customer->get_db_customer( $bid );
			// Get Stripe customer object.
			if ( ! empty( $customer_data->customer_id ) ) {
				$customer = self::$stripe_customer->get_customer( $customer_data->customer_id );
			}

			// Get subscription's default card.
			if ( isset( $customer_data->subscription_id ) ) {
				$card = self::$stripe_subscription->default_card( $customer_data->subscription_id );
			}

			// If default card is not found, get customer's default card.
			if ( ! empty( $customer ) && empty( $card ) ) {
				$card = self::$stripe_customer->default_card( $customer->id );
			}
		}

		// This is a new blog.
		if ( isset( $render_data['activation_key'] ) ) {
			// Get the activation key.
			$activation_key = $render_data['activation_key'];
			// If new blog details is found.
			if ( ! empty( $blog_data ) ) {
				// Get the data.
				$user_name  = empty( $blog_data['username'] ) ? '' : $blog_data['username'];
				$user_email = empty( $blog_data['email'] ) ? '' : $blog_data['email'];
				$blogname   = empty( $blog_data['blogname'] ) ? '' : $blog_data['blogname'];
				$blog_title = empty( $blog_data['title'] ) ? '' : $blog_data['title'];
			}
		}

		// Turn on output buffering.
		ob_start();

		// File that contains checkout form.
		include_once 'gateway-stripe-files/views/frontend/checkout.php';

		wp_enqueue_script( 'psts-stripe-checkout-js' );

		// Get the content as a string.
		$content = ob_get_clean();

		return $content;
	}

	/**
	 * Handles the form processing for Stripe payments.
	 *
	 * This method is required to process the payment from Stripe.
	 * Stripe will be redirecting to the same page after the payment.
	 *
	 * @param array  $data    Form data.
	 * @param int    $blog_id Blog ID.
	 * @param string $domain  Site domain.
	 *
	 * @since 3.6.1
	 *
	 * @return void|bool
	 */
	public static function process_checkout_form( $data = array(), $blog_id, $domain ) {
		// First we need to clear caches.
		ProSites_Helper_Cache::refresh_cache();

		global $psts, $current_user;

		// Set new/upgrading blog data to render data array.
		foreach ( array( 'new_blog_details', 'upgraded_blog_details', 'COUPON_CODE', 'activation_key' ) as $key ) {
			// If missing, try to get from session.
			$data[ $key ] = isset( $data[ $key ] ) ? $data[ $key ] : ProSites_Helper_Session::session( $key );
		}

		// New blog id.
		self::$blog_id = empty( $blog_id ) ? self::from_request( 'bid', 0, false ) : $blog_id;

		// If this is a card update form.
		if ( 1 === (int) self::from_request( 'update_stripe_card', 0 ) ) {
			return self::process_card_update();
		}

		// Continue only if payment form is submitted.
		if ( 1 !== (int) self::from_request( 'psts_stripe_checkout', 0 ) ) {
			return false;
		}

		// Set the level.
		self::$level = (int) self::from_request( 'level' );
		// Set the period.
		self::$period = (int) self::from_request( 'period' );

		// Get the Stripe data.
		$stripe_token = self::from_request( 'stripeToken' );

		// Are we processing an existing site.
		self::$existing = ! empty( self::$blog_id );

		// Domain name.
		self::$domain = $domain;

		// We need to get the email.
		self::$email = self::get_email( $data );

		// Do not continue if level and period is not set.
		if ( empty( self::$level ) || empty( self::$period ) ) {
			$psts->errors->add( 'stripe', __( 'Please choose your desired level and payment plan.', 'psts' ) );

			return false;
		}

		// Get existing user's password.
		$user_password = self::from_request( 'wp_password' );
		// If they password is entered, verify that.
		if ( ! empty( $user_password ) && is_user_logged_in() ) {
			if ( ! wp_check_password( $user_password, $current_user->data->user_pass, $current_user->ID ) ) {
				$psts->errors->add( 'stripe', __( 'The password you entered is incorrect.', 'psts' ) );

				return false;
			}
		}

		// We need Stripe token.
		if ( empty( $stripe_token ) && empty( $user_password ) ) {
			$psts->errors->add( 'stripe', __( 'There was an error processing your Credit Card with Stripe. Please try again.', 'psts' ) );

			return false;
		}

		// We have level and period, so get the Stripe plan id.
		$plan_id = self::$stripe_plan->get_id( self::$level, self::$period );

		// Set a flag if we are upgrading a blog.
		if ( self::$existing ) {
			// Get the existing site data.
			$site_data = ProSites_Helper_ProSite::get_site( self::$blog_id );
			// If plans have changed, set the upgrade flag to true.
			if ( ! empty( $site_data->level ) && ! empty( $site_data->term ) && self::$stripe_plan->get_id( $site_data->level, $site_data->term ) !== $plan_id ) {
				self::$upgrading = true;
			}
		}

		// Do not continue if plan does not exist.
		if ( ! self::$stripe_plan->get_plan( $plan_id ) ) {
			// translators: %1$s Stripe plan ID.
			$psts->errors->add( 'stripe', sprintf( __( 'Stripe plan %1$s does not exist.', 'psts' ), $plan_id ) );

			return false;
		}

		// Should we set this as default card.
		$make_default_card = (bool) self::from_request( 'default_card' );

		// Create or update Stripe customer.
		$customer = self::$stripe_customer->set_blog_customer(
			self::$email,
			self::$blog_id,
			$stripe_token,
			$make_default_card,
			$card // Pass by reference.
		);

		// If new customer, get the default source id.
		if ( empty( $card ) && ! empty( $customer->default_source ) ) {
			$card = $customer->default_source;
		}

		$error_code = empty( $psts->errors ) ? '' : $psts->errors->get_error_codes();

		// If Customer object is not set/ Or we have checkout errors.
		if ( empty( $customer ) || ! empty( $error_code ) ) {
			return self::update_errors();
		}

		// Now process the payments.
		if ( self::process_payment( $data, $customer, $plan_id, $card ) ) {
			self::$show_completed = true;
		} else {
			$psts->errors->add( 'stripe', __( 'An unknown error occurred while processing your payment. Please try again.', 'psts' ) );
		}

		return true;
	}

	/**
	 * Process card update form to Stripe.
	 *
	 * We need to attach the new source to customer
	 * and then update the subscription to use the
	 * new card for the future payments.
	 *
	 * @since 3.6.1
	 *
	 * @return void|bool
	 */
	private static function process_card_update() {
		// Get token id from form data.
		$token = self::from_request( 'stripe_token' );

		// We need blog id.
		if ( empty( self::$blog_id ) || empty( $token ) ) {
			return false;
		}

		// Get Stripe customer using blog id.
		$customer = self::$stripe_customer->get_customer_by_blog( self::$blog_id );
		if ( $customer ) {
			// Add the card to customer.
			$card = self::$stripe_charge->create_card( $token, $customer );
			if ( isset( $card->id ) ) {
				// Get the subscription.
				$subscription = self::$stripe_subscription->get_subscription_by_blog( self::$blog_id );
				if ( isset( $subscription->id ) ) {
					// Use the new source for future payments.
					self::$stripe_subscription->update_subscription(
						$subscription->id,
						array(
							'default_source' => $card->id,
						)
					);
				}
			}
		}

		return true;
	}

	/**
	 * Process the payment form the registration.
	 *
	 * If a customer is not already create in Stripe, create
	 * one or get the existing one. Then create a Stripe subscription
	 * and assign it to the site.
	 *
	 * @param array           $process_data Processed data.
	 * @param Stripe\Customer $customer     Stripe customer.
	 * @param string          $plan_id      Stripe plan id.
	 * @param string|bool     $card         Card id for the payment (if empty default card will be used).
	 *
	 * @since 3.6.1
	 *
	 * @return bool True if payment was success.
	 */
	private static function process_payment( $process_data, $customer, $plan_id, $card = false ) {
		global $psts;

		$message = '';

		// Get the level + period amount.
		$amount = $total = $psts->get_level_setting( self::$level, 'price_' . self::$period );

		// Is recurring subscriptions enabled?.
		$recurring = (bool) $psts->get_setting( 'recurring_subscriptions', 1 );

		// Get the tax object.
		$tax_object = self::tax_object();

		// Fix for email body issue.
		add_action( 'phpmailer_init', 'psts_text_body' );

		// If a setup fee is set, charge it.
		if ( $psts->has_setup_fee( self::$blog_id, self::$level ) ) {
			// Charge the setup fee.
			$total = self::charge_setup_fee( $total, $customer, $recurring );
		}

		// If a coupon is applied, adjust the amount.
		$coupon = self::maybe_apply_coupon( $process_data, $amount, $total );

		// Stripe description.
		$desc = self::get_description( $amount, $total, $recurring, $process_data );

		if ( $recurring ) {
			// Process recurring payment.
			$processed = self::process_recurring( $process_data, $plan_id, $customer, $tax_object, $coupon, $amount, $desc, $card );
		} else {
			// Calculate the total amount if we are upgrading.
			if ( self::$upgrading ) {
				$total = $psts->calc_upgrade_cost( self::$blog_id, self::$level, self::$period, $total );
			}

			// Process one time payment.
			$processed = self::process_single( $process_data, $customer, $tax_object, $amount, $total, $desc, $card );
		}

		// Get the existing site data.
		$site_data = ProSites_Helper_ProSite::get_site( self::$blog_id );

		// If plans have changed, set the upgrade flag to true.
		if ( ! empty( $site_data->level ) && ! empty( $site_data->term ) && self::$stripe_plan->get_id( $site_data->level, $site_data->term ) !== $plan_id ) {
			$updated = array(
				'render'      => true,
				'blog_id'     => self::$blog_id,
				'level'       => self::$level,
				'period'      => self::$period,
				'prev_level'  => $site_data->level,
				'prev_period' => $site_data->term,
			);

			// Set updated session.
			ProSites_Helper_Session::session( 'plan_updated', $updated );
		}

		// Set the stat flags.
		if ( self::$upgrading ) {
			$psts->record_stat( self::$blog_id, 'upgrade' );
		} elseif ( self::$existing ) {
			$psts->record_stat( self::$blog_id, 'modify' );
		} else {
			$psts->record_stat( self::$blog_id, 'signup' );
		}

		// We don't need the coupon anymore.
		if ( isset( $coupon->id ) ) {
			if ( ! self::$stripe_plan->delete_coupon( $coupon->id ) ) {
				wp_mail(
					get_blog_option( self::$blog_id, 'admin_email' ),
					__( 'Error deleting temporary Stripe coupon code. Attention required!.', 'psts' ),
					sprintf( __( 'An error occurred when attempting to delete temporary Stripe coupon code %1$s. You will need to manually delete this coupon via your Stripe account.', 'psts' ), $process_data['COUPON_CODE'] ),
					array( 'content-type' => 'text/html' )
				);
			}
		}

		// Log user subscription details.
		if ( ( ! self::$existing || $psts->is_blog_canceled( self::$blog_id ) ) && ! empty( $customer->id ) ) {
			// Added for affiliate system link.
			if ( ! $recurring ) {
				$message = sprintf( __( 'User completed new payment via CC: Site created/extended (%1$s) - Customer ID: %2$s', 'psts' ), $desc, $customer->id );
			}
		} else {
			$message = sprintf( __( 'User modifying subscription via CC: Plan changed to (%1$s) - %2$s', 'psts' ), $desc, $customer->id );
		}

		// Log message.
		if ( ! empty( $message ) ) {
			$psts->log_action( self::$blog_id, $message, self::$domain );
		}

		// Display GA ecommerce in footer.
		$psts->create_ga_ecommerce( self::$blog_id, self::$period, $total, self::$level, '', self::$domain );

		// Remove email body issue action.
		remove_action( 'phpmailer_init', 'psts_text_body' );

		// Delete cancellation flags.
		if ( $processed && ! empty( self::$blog_id ) ) {
			// Delete cancellation falgs.
			delete_blog_option( self::$blog_id, 'psts_stripe_canceled' );
			delete_blog_option( self::$blog_id, 'psts_is_canceled' );
		}

		/**
		 * Action hook to process something when recurring payment processed.
		 *
		 * @param bool             $processed Was the payment success.
		 * @param \Stripe\Customer $customer  Stripe customer.
		 * @param int              $blog_id   Blog ID.
		 * @param float            $amount    Total amount.
		 * @param int              $period    Period.
		 * @param int              $level     Level.
		 *
		 * @since 3.6.1
		 */
		do_action( 'pro_sites_payment_processed', $processed, $customer, self::$blog_id, $amount, self::$period, self::$level );

		return $processed;
	}

	/**
	 * Process the recurring payment for the registration.
	 *
	 * For the recurring payment, we need to create subscription
	 * in Stripe for the customer, so that Stripe will automatically
	 * process the renewals.
	 *
	 * @param array            $data       Form data.
	 * @param string           $plan_id    Stripe plan id.
	 * @param \Stripe\Customer $customer   Stripe customer.
	 * @param object           $tax_object Tax object.
	 * @param \Stripe\Coupon   $coupon     Stripe coupon object.
	 * @param float            $amount     Plan amount.
	 * @param string           $desc       Description for log.
	 * @param string|bool      $card       Card id for the payment (if empty default card will be used).
	 *
	 * @since 3.6.1
	 *
	 * @return bool True if payment was success.
	 */
	private static function process_recurring( $data, $plan_id, $customer, $tax_object, $coupon, $amount, $desc, $card = false ) {
		global $psts;

		// If customer created, now let's create a subscription.
		if ( ! empty( $customer->id ) ) {
			// Activation key.
			$activation_key = self::get_activation_key( self::$blog_id );

			$sub_args = array(
				'prorate' => true,
			);

			// Apply tax if required.
			if ( $tax_object->apply_tax ) {
				$sub_args['tax_percent'] = self::format_price( $tax_object->tax_rate );
			}

			// If there is a coupon, apply that.
			if ( ! empty( $coupon->id ) ) {
				$sub_args['coupon'] = $coupon->id;
			}

			// Apply trial if applicable.
			if ( $psts->is_trial_allowed( self::$blog_id ) ) {
				$sub_args = self::set_trial( $sub_args, $data );
			}

			// Meta data for blog details.
			$sub_args['metadata'] = array(
				'blog_id'    => self::$blog_id,
				'domain'     => self::$domain,
				'period'     => self::$period,
				'level'      => self::$level,
				'activation' => $activation_key,
			);

			// Should we update the db later?.
			$update_db = empty( self::$blog_id );

			// Now create the subscription.
			$subscription = self::$stripe_subscription->set_blog_subscription(
				self::$blog_id,
				$customer->id,
				$plan_id,
				$sub_args,
				$desc,
				$card
			);

			// Now activate the blog.
			if ( ! empty( $subscription ) ) {
				self::extend_recurring_blog( $activation_key, $subscription, $data, $amount, true );
			}

			if ( ! empty( self::$blog_id ) ) {
				// Make sure we set subscription data in DB.
				if ( $update_db && ! empty( $subscription->id ) && ! empty( $customer->id ) ) {
					self::$stripe_customer->set_db_customer(
						self::$blog_id,
						$customer->id,
						$subscription->id
					);
				}

				// If blog id is not set, set now.
				if ( empty( $subscription->metadata['blog_id'] ) && ! empty( $subscription->id ) ) {
					// Get existing meta data.
					$meta = $subscription->metadata;
					// Add blog id to it.
					$meta['blog_id'] = self::$blog_id;
					// Now update the subscription in Stripe.
					self::$stripe_subscription->update_subscription( $subscription->id, array(
						'metadata' => $meta,
					) );
				}

				return true;
			}

			/**
			 * Action hook to process something when recurring payment processed.
			 *
			 * @param \Stripe\Subscription $subscription Stripe subscription.
			 * @param \Stripe\Customer     $customer     Stripe customer.
			 * @param int                  $blog_id      Blog ID.
			 * @param float                $amount       Total amount.
			 * @param int                  $period       Period.
			 * @param int                  $level        Level.
			 *
			 * @since 3.6.1
			 */
			do_action( 'pro_sites_stripe_recurring_processed', $subscription, $customer, self::$blog_id, $amount, self::$period, self::$level );

			/**
			 * Keeping it for backward compatibility.
			 */
			do_action( 'supporter_payment_processed', self::$blog_id, $amount, self::$period, self::$level );
		}

		return false;
	}

	/**
	 * Process the single payment for the registration.
	 *
	 * For the one time payment, we will not create Stripe
	 * subscription. We can make the payment using the invoice
	 * payment. Customer needs to make the payments manually once
	 * the site reach the expiry date.
	 *
	 * @param array            $data       Form data.
	 * @param \Stripe\Customer $customer   Stripe customer.
	 * @param object           $tax_object Tax object.
	 * @param float            $amount     Amount of plan.
	 * @param float            $total      Total amount.
	 * @param string           $desc       Description for log.
	 * @param string|bool      $card       Card id for the payment (if empty default card will be used).
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	private static function process_single( $data, $customer, $tax_object, $amount, $total, &$desc, $card = false ) {
		global $psts;

		// If customer created, now let's create a subscription.
		if ( ! empty( $customer->id ) ) {
			// Activation key.
			$activation_key = self::get_activation_key( self::$blog_id );

			// Stripe description.
			$desc = self::get_description( $amount, $total, false, $data );

			// Apply tax if required.
			if ( $tax_object->apply_tax ) {
				$total    = $total + ( $total * $tax_object->tax_rate );
				$tax_rate = self::format_price( $tax_object->tax_rate );
				// Update the description.
				$desc .= sprintf( __( '(includes tax of %s%% [%s])', 'psts' ), $tax_rate, $tax_object->country );
			}

			// Charge arguments.
			$charge_args = array(
				'metadata' => array(
					'blog_id' => self::$blog_id,
					'domain'  => self::$domain,
					'period'  => self::$period,
					'level'   => self::$level,
				),
			);

			// Set the activation key.
			if ( ! empty( $activation_key ) ) {
				$charge_args['metadata']['activation'] = $activation_key;
			}

			// Set the tax evidence.
			if ( $tax_object->apply_tax ) {
				$charge_args['metadata']['tax_evidence'] = ProSites_Helper_Tax::get_evidence_string( $tax_object );
			}

			// Charge the amount now.
			if ( $total > 0 ) {
				// Set the charging card.
				if ( ! empty( $card ) ) {
					$charge_args['source'] = $card;
				}
				$result = self::$stripe_charge->create_item(
					$customer->id,
					$total,
					'charge',
					$desc,
					$charge_args,
					true
				);
			} else {
				// If trial is enabled, create invoice item.
				$result = self::$stripe_charge->create_item(
					$customer->id,
					$total,
					'invoiceitem',
					$desc,
					$charge_args,
					true
				);
			}

			if ( ! empty( $result ) ) {
				// Activate the blog and extend.
				self::extend_single_blog( $activation_key, $data, $amount );
			}

			// Make sure we set customer data in DB.
			if ( ! empty( self::$blog_id ) && ! empty( $result->id ) && ! empty( $customer->id ) ) {
				self::$stripe_customer->set_db_customer(
					self::$blog_id,
					$customer->id
				);

				// If blog id is not set, set now.
				if ( empty( $result->metadata['blog_id'] ) ) {
					// Get existing meta data.
					$meta = $result->metadata;
					// Add blog id to it.
					$meta['blog_id'] = self::$blog_id;
					// Now update the subscription in Stripe.
					self::$stripe_charge->update_charge( $result->id, array(
						'metadata' => $meta,
					) );
				}

				// If a valid email is found, send receipt.
				if ( ! empty( self::$email ) ) {
					$psts->email_notification( self::$blog_id, 'receipt', self::$email );
				}

				return true;
			}

			/**
			 * Action hook to process something when single payment processed.
			 *
			 * @param \Stripe\Customer $customer Stripe customer.
			 * @param int              $blog_id  Blog ID.
			 * @param float            $amount   Total amount.
			 * @param int              $period   Period.
			 * @param int              $level    Level.
			 *
			 * @since 3.6.1
			 */
			do_action( 'pro_sites_stripe_single_processed', $customer, self::$blog_id, $amount, self::$period, self::$level );

			/**
			 * Keeping it for backward compatibility.
			 */
			do_action( 'supporter_payment_processed', self::$blog_id, $amount, self::$period, self::$level );
		}

		return false;
	}

	/**
	 * Manually attempt to reactivate a cancelled subscription.
	 *
	 * If a subscription was cancelled and not reached the end date
	 * yet, we will re-activate the subscription. If not, we will
	 * create new subscription.
	 *
	 * @param int $blog_id Blog ID.
	 *
	 * @since 3.6.1
	 *
	 * @return void|bool
	 */
	public function manual_reactivation( $blog_id ) {
		global $psts;

		// Get customer data.
		$customer_data = self::$stripe_customer->get_db_customer( $blog_id );

		// We need a valid subscription.
		if ( empty( $customer_data->subscription_id ) ) {
			return false;
		}

		// Initialize the subscription.
		$subscription = false;

		// Continue if subscription id found.
		if ( $customer_data->subscription_id ) {
			// Try to get the existing subscription.
			$subscription = self::$stripe_subscription->get_subscription( $customer_data->subscription_id );

			/**
			 * We can not reactivate if subscription is immediately cancelled.
			 * See https://stripe.com/docs/billing/subscriptions/canceling-pausing#reactivating-canceled-subscriptions
			 */
			if ( ! empty( $subscription->status ) && 'canceled' !== $subscription->status ) {
				// Save to reactivate.
				$subscription = self::$stripe_subscription->update_subscription(
					$customer_data->subscription_id,
					array( 'cancel_at_period_end' => false ) // Do not cancel on end date.
				);
			} else {
				$subscription = false;
			}
		}

		// Ok, success.
		if ( ! empty( $subscription ) ) {
			// Remove the flags.
			delete_blog_option( $blog_id, 'psts_stripe_canceled' );
			delete_blog_option( $blog_id, 'psts_is_canceled' );
			// Record modification status.
			$psts->record_stat( $blog_id, 'modify' );
			// Log the reactivation.
			$psts->log_action( $blog_id, __( 'Stripe subscription reactivated manually.', 'psts' ) );
		} else {
			$psts->log_action( $blog_id, __( 'Stripe cannot re-activate this subscription.', 'psts' ) );
		}
	}

	/**
	 * Record the transaction data to db.
	 *
	 * @param object $object Event data from Stripe.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function record_transaction( $object ) {
		// We need data from Stripe.
		if ( empty( $object ) ) {
			return;
		}

		// Get the object prepared.
		$object = ProSites_Helper_Transaction::object_from_data(
			$object,
			get_class()
		);

		// Record the object.
		ProSites_Helper_Transaction::record( $object );

	}

	/**
	 * Create transaction object for Stripe.
	 *
	 * Create transaction object from the Stripe event
	 * data received via webhook.
	 *
	 * @param object $object  Transaction object.
	 * @param array  $data    Transaction data.
	 * @param string $gateway Current gateway.
	 *
	 * @since 3.6.1
	 *
	 * @return mixed
	 */
	public function create_transaction_object( $object, $data, $gateway ) {
		// Continue only for Stripe.
		if ( get_class() !== $gateway || empty( $data ) ) {
			return $object;
		}

		// Get the subscription id.
		$subscription = empty( $data->subscription ) ? false : $data->subscription;
		// Line objects array.
		$line_objects = array();

		// Basic invoice data.
		$object->invoice_number = $data->id;
		$object->invoice_date   = date( 'Y-m-d', $data->date );
		$object->currency_code  = strtoupper( $data->currency );
		// General (used for transaction recording).
		$object->total       = self::format_price( $data->total, false );
		$object->tax_percent = self::format_price( $data->tax_percent, false );
		$object->subtotal    = self::format_price( $data->subtotal, false );
		$object->tax         = self::format_price( $data->tax, false );

		// Get the line items.
		if ( ! empty( $data->lines->data ) ) {
			// Loop through each items.
			foreach ( $data->lines->data as $line ) {
				// Set basic line data.
				$line_object              = new stdClass();
				$line_object->id          = $line->id;
				$line_object->amount      = self::format_price( $line->amount, false );
				$line_object->quantity    = $line->quantity;
				$line_object->custom_id   = $line->id;
				$line_object->description = '';

				// Get a description.
				if ( isset( $line->description ) ) {
					$line_object->description = $line->description;
				} elseif ( isset( $line->plan->name ) ) {
					$line_object->description = $line->plan->name;
				}

				// Get some subscription data.
				if ( isset( $line->type ) && 'subscription' === $line->type ) {
					// Get the subscription id.
					$subscription = empty( $sub_id ) ? $line->subscription : $subscription;
					// Level and period data.
					$object->level  = isset( $line->metadata->level ) ? $line->metadata->level : '';
					$object->period = isset( $line->metadata->period ) ? $line->metadata->period : '';
				}

				// Ok, check if we have tax applied.
				if ( isset( $line->metadata->tax_evidence ) && empty( $object->evidence ) ) {
					try {
						// Get tax evidence.
						$tax_evidence                 = $line->metadata->tax_evidence;
						$object->evidence             = ProSites_Helper_Transaction::evidence_from_json( $tax_evidence );
						$object->billing_country_code = ProSites_Helper_Transaction::country_code_from_data( $tax_evidence, $object );
					} catch ( \Exception $e ) {
						// Ah well.
						self::error_log( $e->getMessage() );
					}
				}

				// Add to line objects.
				$line_objects[] = $line_object;
			}
		}

		// Get Stripe subscription object.
		$blog_id = self::$stripe_subscription->get_blog_id_by_subscription( $subscription );
		// Set blog id.
		if ( $blog_id ) {
			$object->blog_id = $blog_id;
		}

		// We need tax evidence field.
		$object->evidence = empty( $object->evidence ) ? null : $object->evidence;

		$object->gateway = get_class();

		// Set line objects.
		$object->transaction_lines = $line_objects;

		return $object;
	}

	/**
	 * The heart of the Stripe API integration.
	 *
	 * Handle the communication from Stripe. Stripe sends
	 * various notifications about the payments. Based on the
	 * event type, process the payments for the sites.
	 * See https://stripe.com/docs/api/events/types
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	public function webhook_handler() {
		// Retrieve the request's body and parse it as JSON.
		$input      = @file_get_contents( 'php://input' );
		$event_json = json_decode( $input );
		$event_type = $event_json->type;
		$event_id   = $event_json->id;

		// Continue only if a valid event.
		if ( ! $this->valid_event( $event_type ) || ! isset( $event_json->data->object ) ) {
			return false;
		}

		// First we need to clear caches.
		ProSites_Helper_Cache::refresh_cache();

		// Get event data.
		$event_data = $event_json->data->object;

		global $psts;

		// Get Stripe subscription for the event.
		$subscription = self::$stripe_subscription->get_webhook_subscription( $event_data );

		// If we have a subscription, now try to get the blog id.
		self::$blog_id = isset( $subscription->id ) ?
			self::$stripe_subscription->get_blog_id_by_subscription( $subscription->id ) : 0;

		// We can continue only if we were able to find the blog id.
		if ( empty( self::$blog_id ) ) {
			return false;
		}

		// Site details.
		$site_data = ProSites_Helper_ProSite::get_site( self::$blog_id );
		if ( empty( $site_data ) ) {
			return false;
		}

		// Get level name.
		$level_name = $psts->get_level_setting( $site_data->level, 'name' );
		// Get total amount.
		$total = self::$stripe_charge->get_webhook_total_amount( $event_data );
		// Format the amount.
		$total = self::format_price( $total, false );
		// Get amount in text.
		$amount = $psts->format_currency( false, $total );
		// Get the period.
		$period = number_format_i18n( $site_data->term );
		// Get the charge id.
		$charge_id = isset( $event_data->charge ) ? $event_data->charge : $event_data->id;

		// Process the events.
		switch ( $event_type ) {
			case 'customer.subscription.created':
				$psts->email_notification( self::$blog_id, 'success' );
				$psts->log_action( self::$blog_id, sprintf( __( 'Stripe webhook "%1$s (%2$s)" received: Customer successfully subscribed to %3$s: %4$s every %5$s month(s).', 'psts' ), $event_type, $event_id, $level_name, $amount, $period ) );
				break;

			case 'customer.subscription.updated':
				// Subscription cancelled in Stripe.
				if ( ! empty( $subscription->cancel_at_period_end ) ) {
					// Cancelled in Stripe.
					update_blog_option( self::$blog_id, 'psts_stripe_canceled', 1 );
					// Get the end date.
					$date = date_i18n( get_option( 'date_format' ), $subscription->current_period_end );
					$psts->log_action( self::$blog_id, sprintf( __( 'Stripe webhook "%1$s (%2$s)" received. The customer\'s subscription has been set to cancel at the end of the billing period: %3$s.', 'psts' ), $event_type, $event_id, $date ) );
				} else {
					$psts->log_action( self::$blog_id, sprintf( __( 'Stripe webhook "%1$s (%2$s)" received. The customer\'s subscription was successfully updated (%3$s: %4$s every %5$s month(s)).', 'psts' ), $event_type, $event_id, $level_name, $amount, $period ) );
				}
				break;

			case 'customer.subscription.deleted':
				// Cancellation flag.
				update_blog_option( self::$blog_id, 'psts_stripe_canceled', 1 );
				$psts->log_action( self::$blog_id, sprintf( __( 'Stripe webhook "%1$s (%2$s)" received: The subscription has been canceled', 'psts' ), $event_type, $event_id ) );
				// Cancel the blog subscription.
				self::$stripe_subscription->cancel_blog_subscription( self::$blog_id, false, false, false );
				break;

			case 'invoice.payment_succeeded':
				delete_blog_option( self::$blog_id, 'psts_stripe_payment_failed' );
				$date = date_i18n( get_option( 'date_format' ), $event_json->created );
				// Is trial active?.
				$is_trial = isset( $subscription->status ) && 'trialing' === $subscription->status;
				$psts->log_action( self::$blog_id, sprintf( __( 'Stripe webhook "%1$s (%2$s)" received: The %3$s payment was successfully received. Date: "%4$s", Charge ID "%5$s"', 'psts' ), $event_type, $event_id, $amount, $date, $charge_id ) );
				// Extend the blog if required.
				self::maybe_extend(
					$total, // Total amount paid.
					$subscription->current_period_end, // Subscription end date.
					true, // Is a payment?.
					true, // Is recurring?.
					$is_trial
				);
				// Log successful payment transaction.
				self::record_transaction( $event_data );
				break;

			case 'invoice.payment_failed':
				$date = date_i18n( get_option( 'date_format' ), $event_json->created );
				update_blog_option( self::$blog_id, 'psts_stripe_payment_failed', 1 );
				$psts->log_action( self::$blog_id, sprintf( __( 'Stripe webhook "%1$s (%2$s)" received: The %3$s payment has failed. Date: "%4$s", Charge ID "%5$s"', 'psts' ), $event_type, $event_id, $amount, $date, $charge_id ) );
				$psts->email_notification( self::$blog_id, 'failed' );
				break;

			case 'charge.dispute.created':
				$psts->log_action( self::$blog_id, sprintf( __( 'Stripe webhook "%1$s (%2$s)" received: The customer disputed a charge with their bank (chargeback), Charge ID "%3$s"', 'psts' ), $event_type, $event_id, $charge_id ) );
				$psts->withdraw( self::$blog_id );
				break;

		}

		return true;
	}

	/**
	 * Extend a blog once the payment is successful.
	 *
	 * If new subscription we will activate the blog first.
	 *
	 * @param string              $activation_key Activation key.
	 * @param Stripe\Subscription $subscription   Stripe subscription.
	 * @param array               $data           Process data.
	 * @param int                 $amount         Total amount.
	 * @param bool                $recurring      Is this a recurring payment.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	private static function extend_recurring_blog( $activation_key, $subscription, $data, $amount = 0, $recurring = false ) {
		$activated = self::$existing;

		// Is current subscription on trial.
		$is_trial = isset( $subscription->status ) && 'trialing' === $subscription->status;

		// Get the expiry date.
		$expire = $is_trial ? $subscription->trial_end : $subscription->current_period_end;

		// Activate the blog if new registration.
		if ( ! $activated ) {
			// Try to get the activation key using blog id.
			if ( empty( $activation_key ) && ! empty( self::$blog_id ) ) {
				$activation_key = ProSites_Helper_ProSite::get_activation_key( self::$blog_id );
			}

			// Activate the blog now.
			$result = ProSites_Helper_Registration::activate_blog(
				$activation_key,
				$is_trial,
				self::$period,
				self::$level,
				$expire,
				false
			);

			if ( ! empty( $result['blog_id'] ) ) {
				// If blog id is not found, try to get it after activation.
				self::$blog_id = empty( self::$blog_id ) ? $result['blog_id'] : self::$blog_id;

				// Set the flag.
				$activated = true;
			}
		}

		// Set values to session.
		self::set_session_data( $data );

		// Extend the site expiry date.
		self::maybe_extend( $amount, $expire, true, $recurring, $is_trial );

		return $activated;
	}

	/**
	 * Extend a single payment a blog once the payment is successful.
	 *
	 * If new subscription we will activate the blog first.
	 *
	 * @param string $activation_key Activation key.
	 * @param array  $data           Process data.
	 * @param int    $total          Total amount.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	private static function extend_single_blog( $activation_key, $data, $total = 0 ) {
		$activated = self::$existing;

		if ( ! $activated ) {
			// Try to get the activation key using blog id.
			if ( empty( $activation_key ) && ! empty( self::$blog_id ) ) {
				$activation_key = ProSites_Helper_ProSite::get_activation_key( self::$blog_id );
			}

			// Activate the blog now.
			$result = ProSites_Helper_Registration::activate_blog( $activation_key, false, self::$period, self::$level, false, false, false );

			if ( ! empty( $result['blog_id'] ) ) {
				// If blog id is not found, try to get it after activation.
				self::$blog_id = empty( self::$blog_id ) ? $result['blog_id'] : self::$blog_id;

				// Set the flag.
				$activated = true;
			}
		}

		// Set values to session.
		self::set_session_data( $data );

		// Extend the site expiry date.
		self::maybe_extend( $total, false, true, false );

		return $activated;
	}


	/**
	 * Set values to session object.
	 *
	 * If a new blog is being created or a blog
	 * is being upgraded, set the session values
	 * so we can re-use it later.
	 *
	 * @param array $data Process data.
	 *
	 * @since 3.6.1
	 *
	 * @return void
	 */
	private static function set_session_data( $data ) {
		// Current session key base.
		$base_key = isset( $data['new_blog_details'] ) ? 'new_blog_details' : 'upgraded_blog_details';

		// Get existing data.
		$existing = (array) ProSites_Helper_Session::session( $base_key );

		// Session values.
		$session_data = array(
			'level'           => self::$level,
			'period'          => self::$period,
			'blog_id'         => self::$blog_id,
			'payment_success' => true,
			'site_activated'  => true,
		);

		// If an existing site, clear password from session.
		if ( self::$existing && isset( $existing['user_pass'] ) ) {
			unset( $existing['user_pass'] );
		}

		// New session data.
		$session_data = array_merge( $session_data, $existing );

		// Set session now.
		ProSites_Helper_Session::session( $base_key, $session_data );
	}

	/**
	 * Extend a blog's expiry date if required.
	 *
	 * If the new plan is different than exsting one,
	 * we need to extend the site. Or if this is called
	 * for a payment, extend it.
	 *
	 * @param float $amount    Total amount.
	 * @param int   $expire    Expiry date.
	 * @param bool  $payment   Is this a payment?.
	 * @param bool  $recurring Is recurring?.
	 * @param bool  $is_trial  Is trialing?.
	 *
	 * @since 3.0
	 *
	 * @return bool
	 */
	private static function maybe_extend( $amount, $expire, $payment = true, $recurring = false, $is_trial = false ) {
		global $psts;

		// Initialize flag as false.
		$extended = $old_plan = false;

		// Get existing site's data.
		$site_data = ProSites_Helper_ProSite::get_site( self::$blog_id );
		// If data found, get the existing plan id.
		if ( ! empty( $site_data->level ) && ! empty( $site_data->term ) ) {
			// Get the old plan.
			$old_plan = self::$stripe_plan->get_id( $site_data->level, $site_data->term );

			// In case if we don't have level and period yet.
			self::$level  = empty( self::$level ) ? $site_data->level : self::$level;
			self::$period = empty( self::$period ) ? $site_data->term : self::$period;
		}

		// Expiry dates are same. Then why would we waste time? Bail.
		if ( isset( $site_data->expire ) && (int) $expire === (int) $site_data->expire ) {
			return false;
		}

		// Set old and new Stripe plans.
		$new_plan = self::$stripe_plan->get_id( self::$level, self::$period );

		// Consider old plan as same as new, if empty.
		$old_plan = empty( $old_plan ) ? $new_plan : $old_plan;

		// If new subscription, extend it.
		if ( $old_plan !== $new_plan || $payment ) {
			// Extend the site.
			$psts->extend(
				self::$blog_id,
				self::$period,
				self::get_slug(),
				self::$level,
				$amount,
				$expire,
				$recurring,
				false,
				false,
				$is_trial
			);

			// Flag extension.
			$extended = true;
		} elseif ( $is_trial ) {
			// Set trial.
			ProSites_Helper_Registration::set_trial( self::$blog_id, 1 );
		}

		return $extended;
	}

	/**
	 * Adjust the coupon amount if applied.
	 *
	 * If a coupon is applied by the user on registration,
	 * apply that and adjust the payment amount.
	 *
	 * @param array $data   Process data.
	 * @param float $amount Amount.
	 * @param float $total  Total amount.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|\Stripe\Coupon
	 */
	private static function maybe_apply_coupon( $data, $amount, &$total ) {
		// Get the coupon data.
		$coupon_data = self::get_coupon_data( $data );
		// Do not continue if coupon is not valid.
		if ( empty( $coupon_data ) ) {
			return false;
		}

		// Now get the off amount.
		$amount_off = $amount - $coupon_data['value'];
		// Round the value to two digits.
		$amount_off = number_format( $amount_off, 2, '.', '' );

		// Apply to total amount.
		$total -= $amount_off;

		// Avoid negative amounts.
		$total = 0 > $total ? 0 : $total;

		$args = array(
			'amount_off'      => self::format_price( $amount_off ),
			'duration'        => $coupon_data['lifetime'],
			'currency'        => self::get_currency(),
			'max_redemptions' => 1,
		);

		// Create or get stripe coupon.
		$stripe_coupon = self::$stripe_plan->create_coupon( $args, $data['COUPON_CODE'] );

		return $stripe_coupon;
	}

	/**
	 * Get Pro Sites coupon data if a coupon is applied.
	 *
	 * We need to validate the coupon code and then get the
	 * coupon object.
	 *
	 * @param array $data Process data.
	 *
	 * @since 3.6.1
	 *
	 * @return array|bool
	 */
	private static function get_coupon_data( $data ) {
		// Do not continue if coupon is not valid.
		if ( ! isset( $data['COUPON_CODE'] ) || ! ProSites_Helper_Coupons::check_coupon(
				$data['COUPON_CODE'],
				self::$blog_id,
				self::$level,
				self::$period,
				self::$domain
			)
		) {
			return false;
		}

		// Get adjusted amounts.
		$adjusted_values = ProSites_Helper_Coupons::get_adjusted_level_amounts( $data['COUPON_CODE'] );
		// Get coupon properties.
		$coupon_obj = ProSites_Helper_Coupons::get_coupon( $data['COUPON_CODE'] );
		// Get the lifetime of coupon.
		$lifetime = isset( $coupon_obj['lifetime'] ) && 'indefinite' === $coupon_obj['lifetime'] ? 'forever' : 'once';
		// Get the adjusted amount for the level and period.
		$coupon_value = $adjusted_values[ self::$level ][ 'price_' . self::$period ];

		return array(
			'coupon'   => $coupon_obj,
			'lifetime' => $lifetime,
			'value'    => $coupon_value,
		);
	}

	/**
	 * Charge the setup fee in Stripe.
	 *
	 * Setup fee is charged separately as a Stripe invpice
	 * item and the payment will be process with next payment.
	 *
	 * @param float           $amount    Amount.
	 * @param Stripe\Customer $customer  Stripe customer.
	 * @param bool            $recurring Is recurring payment?.
	 *
	 * @since 3.6.1
	 *
	 * @return float $total Total amount including setup fee.
	 */
	private static function charge_setup_fee( $amount, $customer, $recurring = true ) {
		// Get the setup fee.
		$setup_fee = ProSites_Helper_Settings::setup_fee();
		// Include setup fee to total.
		$total = $setup_fee + $amount;

		// If recurring payment charge setup fee in Stripe.
		if ( $recurring ) {
			self::$stripe_charge->charge_setup_fee(
				$customer->id,
				$setup_fee
			);
		}

		return $total;
	}

	/**
	 * Get the payment description for Stripe.
	 *
	 * We need to generate different payment descriptions based
	 * on the coupon, setup fee and recurring payments.
	 *
	 * @param float $amount    Amount.
	 * @param float $total     Total amount.
	 * @param bool  $recurring Is recurring?.
	 * @param array $data      Process data.
	 *
	 * @since 3.6.1
	 *
	 * @return string
	 */
	private static function get_description( $amount, $total, $recurring, $data ) {
		global $psts, $current_site;

		// Get currency.
		$currency = self::get_currency();
		// Site name.
		$site_name = $current_site->site_name;
		// Is setup fee is enabled and can be applied to this site?.
		$has_setup_fee = $psts->has_setup_fee( self::$blog_id, self::$level );
		// Did the user apply a valid coupon?.
		$coupon = self::get_coupon_data( $data );

		// If we are upgrading, calculate the total.
		if ( self::$upgrading ) {
			$total = $psts->calc_upgrade_cost( self::$blog_id, self::$level, self::$period, $total );
		}

		// Get the total amount after applying coupon.
		if ( isset( $coupon['lifetime'] ) && 'forever' === $coupon['lifetime'] ) {
			$total = $coupon['value'];
		}

		// Format the with currency.
		$amount = $psts->format_currency( $currency, $amount );
		$total  = $psts->format_currency( $currency, $total );

		// For recurring subscriptions.
		if ( $recurring ) {
			// If we have a setup fee or coupon applied.
			if ( $has_setup_fee || $coupon ) {
				if ( 1 === self::$period ) {
					$desc = sprintf( __( '%1$s for the first month, then %2$s each month', 'psts' ), $total, $amount );
				} else {
					$desc = sprintf( __( '%1$s for the first %2$s month period, then %3$s every %4$s months', 'psts' ), $total, self::$period, $amount, self::$period );
				}
			} else {
				if ( 1 === self::$period ) {
					$desc = sprintf( __( '%s each month', 'psts' ), $amount );
				} else {
					$desc = sprintf( __( '%1$s every %2$s months', 'psts' ), $amount, self::$period );
				}
			}
		} else {
			// If we have a setup fee or coupon applied.
			if ( 1 === self::$period ) {
				$desc = sprintf( __( '%s for 1 month', 'psts' ), $total );
			} else {
				$desc = sprintf( __( '%1$s for %2$s months', 'psts' ), $total, self::$period );
			}
		}

		// Append the site name and level name.
		$desc = $site_name . ' ' . $psts->get_level_setting( self::$level, 'name' ) . ': ' . $desc;

		/**
		 * Filter to override the Stripe description.
		 *
		 * @param string $desc      Description.
		 * @param int    $period    Period.
		 * @param  int   $level     Level.
		 * @param float  $amount    Initial amount.
		 * @param float  $total     Total amount.
		 * @param  int   $blog_id   Current blog id.
		 * @param string $domain    Domain name.
		 * @param bool   $recurring Is recurring?.
		 *
		 * @since 3.0
		 */
		return apply_filters( 'psts_stripe_checkout_desc', $desc, self::$period, self::$level, $amount, $total, self::$blog_id, self::$domain, $recurring );
	}

	/**
	 * Set trial for the subscription.
	 *
	 * If trial is enabled add trial end date to the
	 * Stripe subscription.
	 *
	 * @param array    $args    Subscription arguments.
	 * @param array    $data    Form data.
	 * @param int|bool $blog_id Blog ID.
	 *
	 * @since 3.6.1
	 *
	 * @return array
	 */
	public static function set_trial( $args = array(), $data = array(), $blog_id = false ) {
		global $psts;

		// Get blog id.
		$blog_id = $blog_id ? $blog_id : self::$blog_id;

		// No. of trial days.
		$trial_days = (int) $psts->get_setting( 'trial_days', 0 );

		// Only if trial enabled.
		if ( $trial_days > 0 ) {
			// Customer is new so add trial days.
			if ( isset( $data['new_blog_details'] ) || ! $psts->is_existing( $blog_id ) ) {
				$args['trial_end'] = strtotime( '+ ' . $trial_days . ' days' );
			} elseif ( is_pro_trial( $blog_id ) && $psts->get_expire( $blog_id ) > time() ) {
				// Customer's trial is still valid so carry over existing expiration date.
				$args['trial_end'] = $psts->get_expire( $blog_id );
			}
		}

		return $args;
	}

	/**
	 * Get activation key of currently processing blog.
	 *
	 * @param int $blog_id Blog ID.
	 *
	 * @since 3.6.1
	 *
	 * @return mixed|null|string
	 */
	private static function get_activation_key( $blog_id = 0 ) {
		// Try to get from $_POST data.
		$activation_key = self::from_request( 'activation', '' );
		// If not, get using blog id.
		$activation_key = empty( $activation_key ) ? ProSites_Helper_ProSite::get_activation_key( $blog_id ) : $activation_key;

		return $activation_key;
	}

	/**
	 * Get the tax object for the purchase.
	 *
	 * @since 3.6.1
	 *
	 * @return object $tax_object
	 */
	private static function tax_object() {
		// Do we already have a tax object in session?.
		$tax_object = ProSites_Helper_Session::session( 'tax_object' );

		// If not found, create one.
		if ( empty( $tax_object ) || empty( $tax_object->evidence ) ) {
			// Get tax object.
			$tax_object = ProSites_Helper_Tax::get_tax_object();
			// Set to cache.
			ProSites_Helper_Session::session( 'tax_object', $tax_object );
		}

		return $tax_object;
	}

	/**
	 * Get email for the current registration.
	 *
	 * We need the email address of the current
	 * registration. Same email will be used to
	 * create Stripe customer.
	 *
	 * @param array $process_data Process data.
	 *
	 * @since 3.6.1
	 *
	 * @return string|false
	 */
	private static function get_email( $process_data = array() ) {
		global $current_user;

		// First try to get the email.
		$email = empty( $current_user->user_email ) ? false : $current_user->user_email;

		// Email is empty so try to get user email.
		if ( empty( $email ) ) {
			// Let's try to get signup email.
			$email = self::from_request( 'user_email' );
		}

		// Email is empty.
		if ( empty( $email ) ) {
			// Let's try to get signup email.
			$email = self::from_request( 'signup_email' );
		}

		// Again email is empty.
		if ( empty( $email ) ) {
			// Let's try to get from blog email.
			$email = self::from_request( 'blog_email' );
		}

		// Again email is empty.
		if ( empty( $email ) ) {
			// Get email from Stripe.
			$email = self::from_request( 'stripeEmail' );
		}

		// In case if email is not set, try to get from process data.
		if ( empty( $email ) && isset( $process_data['new_blog_details']['user_email'] ) ) {
			$email = $process_data['new_blog_details']['user_email'];
		}

		return $email;
	}

	/**
	 * Flag to process the form on render.
	 *
	 * We are coming back to the same form after payment
	 * so we need to process the payment.
	 *
	 * @since 3.0
	 *
	 * @return bool
	 */
	public static function process_on_render() {
		/**
		 * Filter to disable process on render.
		 *
		 * @since 3.6.1
		 */
		return apply_filters( 'pro_sites_stripe_process_on_render', true );
	}

	/**
	 * Set errors to WP_Error.
	 *
	 * If we have errors during form process,
	 * add them to errors list so we can log them.
	 *
	 * @since 3.6.1
	 *
	 * @return void
	 */
	private static function set_errors() {
		global $psts;

		// If there were any errors in checkout.
		if ( ! empty( $_POST['errors'] ) ) {
			if ( is_wp_error( $_POST['errors'] ) ) {
				$error_messages = $_POST['errors']->get_error_messages();
				if ( ! empty( $error_messages ) ) {
					$psts->errors = $_POST['errors'];
				}
			}
		}
	}

	/**
	 * Store Checkout errors in $_POST.
	 *
	 * @since 3.0
	 *
	 * @return bool
	 */
	private static function update_errors() {
		global $psts;

		// If there are any errors, store them in $_POST.
		$error_codes = $psts->errors->get_error_codes();

		// Set to $_POST.
		if ( is_wp_error( $psts->errors ) && ! empty( $error_codes ) ) {
			$_POST['errors'] = $psts->errors;
		}

		return false;
	}

	/**
	 * Get a value from $_POST global.
	 *
	 * @param string $string  String name.
	 * @param mixed  $default Default value.
	 * @param string $type    Type of request.
	 *
	 * @since  3.6.1
	 *
	 * @return mixed
	 */
	public static function from_request( $string, $default = false, $type = 'post' ) {
		switch ( $type ) {
			case 'post':
				// Get data from post.
				$value = isset( $_POST[ $string ] ) ? $_POST[ $string ] : false; // input var okay.
				break;
			case 'get':
				$value = isset( $_GET[ $string ] ) ? $_GET[ $string ] : false; // input var okay.
				break;
			default:
				$value = isset( $_REQUEST[ $string ] ) ? $_REQUEST[ $string ] : false; // input var okay.
		}


		// If empty return default value.
		if ( ! empty( $value ) ) {
			return $value;
		}

		return $default;
	}

	/**
	 * Get formatted currency.
	 *
	 * We need to make sure currency is supported by
	 * the gateway.
	 *
	 * @param int  $amount   Amount as int.
	 * @param bool $multiply true for multiply, false for division.
	 *
	 * @since 3.6.1
	 *
	 * @return float|int
	 */
	public static function format_price( $amount, $multiply = true ) {
		/**
		 * Zero decimal currencies.
		 *
		 * @param array List Currencies that has zero decimal.
		 */
		$zero_decimal_currencies = apply_filters( 'pro_sites_zero_decimal_currencies', array( 'JPY' ) );

		// Is a zero decimal currency.
		$zero_decimal = in_array( self::get_currency(), $zero_decimal_currencies, true );

		// If not a zero decimal currency, get float value.
		if ( ! $zero_decimal ) {
			$amount = $multiply ? floatval( $amount ) * 100 : $amount / 100;
		}

		return $amount;
	}

	/**
	 * Get formatted date.
	 *
	 * Format the given date to a format.
	 *
	 * @param string      $date   Date.
	 * @param string|bool $format Date format.
	 *
	 * @since 3.6.1
	 *
	 * @return string
	 */
	public static function format_date( $date, $format = false ) {
		// If format is not given, get default.
		$format = empty( $format ) ? get_option( 'date_format' ) : $format;

		return date_i18n( $format, $date );
	}

	/**
	 * Verify that the current event is valid.
	 *
	 * @param string $event The event type.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	private function valid_event( $event ) {
		// List of valid events.
		$valid_events = array(
			'charge.dispute.created',
			'customer.subscription.created',
			'customer.subscription.updated',
			'customer.subscription.deleted',
			'invoice.payment_succeeded',
			'invoice.payment_failed',
		);

		// Filter hook to modify list of valid hooks.
		$valid_events = apply_filters( 'psts_valid_stripe_events', $valid_events );

		return in_array( $event, $valid_events, true );
	}

	/**
	 * Log Stripe errors to error log.
	 *
	 * @param string $message Error text.
	 *
	 * @since 3.6.1
	 * @uses  error_log()
	 *
	 * @return void
	 */
	public static function error_log( $message ) {
		global $psts;

		// Log only if enabled.
		if ( $psts->get_setting( 'stripe_debug' ) && ! empty( $message ) ) {
			error_log( $message );
		}
	}
}

// Register the gateway.
psts_register_gateway(
	'ProSites_Gateway_Stripe',
	__( 'Stripe', 'psts' ),
	__( 'Stripe handles everything, including storing cards, subscriptions, and direct payouts to your bank account.', 'psts' )
);
