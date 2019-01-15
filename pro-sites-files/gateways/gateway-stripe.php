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
		add_action( 'psts_settings_process', array( $this, 'settings_process' ), 10, 1 );

		// Handle webhook notifications.
		add_action( 'wp_ajax_nopriv_psts_stripe_webhook', array( $this, 'webhook_handler' ) );

		// Update plans in Stripe if necessary.
		add_action( 'update_site_option_psts_levels', array( $this, 'update_plans' ), 10, 2 );
		// One level deleted, sync with Stripe.
		add_action( 'psts_delete_level', array( $this, 'update_plans' ), 10, 2 );
		// New level added, sync with Stripe.
		add_action( 'psts_add_level', array( $this, 'update_plans' ), 10, 2 );

		// Cancel subscriptions on blog deletion.
		add_action( 'delete_blog', array( $this, 'cancel_subscription' ) );

		// Should we force SSL?.
		add_filter( 'psts_force_ssl', array( $this, 'force_ssl' ) );

		// Manual extension.
		add_action( 'psts_stripe_extension', array( $this, 'manual_reactivation' ) );

		// Create transaction object.
		add_filter( 'prosites_transaction_object_create', array( $this, 'create_transaction_object' ), 10, 3 );

		// Site details page.
		add_action( 'psts_subscription_info', array( $this, 'subscription_info' ) );
		add_action( 'psts_subscriber_info', array( $this, 'subscriber_info' ) );

		// Subscription modification form.
		add_action( 'psts_modify_form', array( $this, 'modification_form' ) );
		add_action( 'psts_modify_process', array( $this, 'process_modification' ) );

		// Next payment date.
		add_filter( 'psts_next_payment', array( $this, '$upcoming_invoice' ) );

		// Show admin notices if required.
		add_action( 'admin_notices', array( &$this, 'admin_notices' ), 99 );

		// Set a flag if last payment is failed.
		add_filter( 'psts_blog_info_payment_failed', array( $this, 'payment_failed' ), 10, 2 );
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
			// Set app info.
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
	 * Create transaction object for Stripe.
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
	public static function webhook_handler() {
		// Handle webhook.
		return false;
	}

	/**
	 * Cancel a particular blog's subscription.
	 *
	 * @param int  $blog_id      Blog ID.
	 * @param bool $show_message Display cancellation message.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	public function cancel_subscription( $blog_id, $show_message = false ) {
		// Cancel the blog subscription.
		return self::$stripe_subscription->cancel_blog_subscription(
			$blog_id,
			false,
			$show_message
		);
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

		// Stripe table schema.
		$table = "CREATE TABLE $this->table (
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

		// Get all indexes on customer id and subscription id.
		$indexes = $wpdb->get_results( "SHOW INDEX FROM $this->table WHERE column_name = 'customer_id'" );
		if ( ! empty( $indexes ) ) {
			foreach ( $indexes as $index ) {
				// If it is a unique key, drop it.
				if ( empty( $index->Non_unique ) ) {
					$wpdb->query( "ALTER TABLE $this->table DROP INDEX $index->Key_name" );
				}
			}
		}

		// Sometimes old installation may have empty subscription ids, so we need to make sure nullable.
		$wpdb->query( "ALTER TABLE $this->table CHANGE subscription_id subscription_id char(22) NULL" );
		// Make sure all empty subscription IDs are NULL. dbDelta will not hable this.
		$wpdb->query( "UPDATE $this->table SET subscription_id = NULL WHERE subscription_id = ''" );

		// If unique key is not set for subscription id, set.
		$index_exists = $wpdb->query( "SHOW INDEX FROM $this->table WHERE KEY_NAME = 'ix_subscription_id'" );
		if ( empty( $index_exists ) ) {
			// Set unique key to subscription id.
			$wpdb->query( "ALTER TABLE $this->table ADD UNIQUE KEY ix_subscription_id (subscription_id)" );
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
	 * @since 3.5.0
	 *
	 * @return bool
	 */
	public function force_ssl() {
		global $psts;

		// Should force?.
		return (bool) $psts->get_setting( 'stripe_ssl', false );
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

		// Get the Stripe customer.
		$customer = self::$stripe_customer->get_customer_by_blog( $blog_id );
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

		// Show alert message if we are waiting for webhook.
		if ( (bool) get_blog_option( $blog_id, 'psts_stripe_waiting' ) && ! is_main_site() ) {
			echo '<div class="updated">';
			echo '<p><strong>';
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
			$last_payment = empty( $last_invoice->total ) ? false : $last_invoice->total / 100;

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

		// Get customer and subcription ids from DB.
		$customer_data = self::$stripe_customer->get_db_customer( $blog_id );

		if ( empty( $action ) || empty( $customer_data->customer_id ) ) {
			return;
		}

		// Last invoice.
		$last_invoice = self::$stripe_customer->last_invoice( $customer_data->customer_id );
		// Last Stripe charge of the invoice.
		$last_charge = empty( $last_invoice->charge ) ? false : $last_invoice->charge;

		switch ( $action ) {
			case 'cancel':
				// Cancel the blog subscription.
				self::$stripe_subscription->cancel_blog_subscription( $blog_id );
				break;

			case 'cancel_refund':
				// First cancel the subscription.
				if ( self::$stripe_subscription->cancel_blog_subscription( $blog_id ) && $last_charge ) {
					// Now process the refund.
					self::$stripe_charge->refund_charge( $last_charge );
				}
				break;

			case 'refund':
				// Process the refund.
				if ( self::$stripe_charge->refund_charge( $last_charge ) ) {
					// translators: %1$s Amount, %2$s User.
					$psts->log_action( $blog_id, sprintf( __( 'A full (%1$s) refund of last payment completed by %2$s, and the subscription was not cancelled.', 'psts' ), $psts->format_currency( false, $last_invoice->total ), $current_user->display_name ) );
				} else {
					// translators: %1$s Amount, %2$s User.
					$psts->log_action( $blog_id, sprintf( __( 'Attempt to issue a full (%1$s) refund of last payment by %2$s failed.', 'psts' ), $psts->format_currency( false, $last_invoice->total ), $current_user->display_name ) );
				}
				break;

			case 'partial_refund':
				// Refund amount.
				$refund_amount = self::from_request( 'refund_amount' );
				// Process the partial refund.
				self::$stripe_charge->refund_charge( $last_charge, $refund_amount );
				break;
		}
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
		// If there were any errors in checkout.
		self::set_errors();

		// Set new/upgrading blog data to render data array.
		foreach ( array( 'new_blog_details', 'upgraded_blog_details', 'activation_key' ) as $key ) {
			$render_data[ $key ] = isset( $render_data[ $key ] ) ? $render_data[ $key ] : ProSites_Helper_Session::session( $key );
		}

		// Stripe publishable key.
		$public_key = self::$public_key;

		// Set the default values.
		$activation_key = $user_name = $new_blog = false;

		// New blog data.
		$blog_data = empty( $render_data['new_blog_details'] ) ? array() : $render_data['new_blog_details'];

		// Set period and levels.
		$period = empty( $blog_data['period'] ) ? ProSites_Helper_ProSite::default_period() : (int) $blog_data['period'];
		$level  = empty( $blog_data['level'] ) ? 0 : (int) $blog_data['level'];
		$level  = empty( $render_data['upgraded_blog_details']['level'] ) ? $level : (int) $render_data['upgraded_blog_details']['level'];

		// Set a flag that it is new blog.
		if ( ProSites_Helper_ProSite::allow_new_blog() && ( isset( $_POST['new_blog'] ) || ( isset( $_GET['action'] ) && 'new_blog' === $_GET['action'] ) ) ) {
			$new_blog = true;
		}

		// If blog id is found in url.
		$bid = isset( $_GET['bid'] ) ? (int) $_GET['bid'] : false;
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
		global $psts;

		// Set new/upgrading blog data to render data array.
		foreach ( array( 'new_blog_details', 'upgraded_blog_details', 'COUPON_CODE', 'activation_key' ) as $key ) {
			// If missing, try to get from session.
			$data[ $key ] = isset( $data[ $key ] ) ? $data[ $key ] : ProSites_Helper_Session::session( $key );
		}

		// Set the level.
		self::$level = self::from_request( 'level' );
		// Set the period.
		self::$period = self::from_request( 'period' );

		// Get the Stripe data.
		$stripe_token = self::from_request( 'stripeToken' );

		// New blog id.
		self::$blog_id = empty( $blog_id ) ? self::from_request( 'bid', 0, false ) : $blog_id;

		// Are we processing an existing site.
		self::$existing = ! empty( self::$blog_id );

		// Domain name.
		self::$domain = $domain;

		// We need to get the email.
		self::$email = self::get_email( $data );

		// Do not continue if level and period is not set.
		if ( empty( self::$level ) || empty( self::$period ) ) {
			$psts->errors->add( 'general', __( 'Please choose your desired level and payment plan.', 'psts' ) );

			return false;
		}

		// We need Stripe token.
		if ( empty( $stripe_token ) ) {
			$psts->errors->add( 'stripe', __( 'There was an error processing your Credit Card with Stripe. Please try again.', 'psts' ) );

			return false;
		}

		// We have level and period, so get the Stripe plan id.
		$plan_id = self::$stripe_plan->get_id( self::$level, self::$period );

		// Do not continue if plan does not exist.
		if ( ! self::$stripe_plan->get_plan( $plan_id ) ) {
			// translators: %1$s Stripe plan ID.
			$psts->errors->add( 'general', sprintf( __( 'Stripe plan %1$s does not exist.', 'psts' ), $plan_id ) );

			return false;
		}

		// Create or update Stripe customer.
		$customer = self::$stripe_customer->set_blog_customer( self::$email, self::$blog_id, $stripe_token );

		$error_code = empty( $psts->errors ) ? '' : $psts->errors->get_error_codes();

		// If Customer object is not set/ Or we have checkout errors.
		if ( empty( $customer ) || ! empty( $error_code ) ) {
			return self::update_errors();
		}

		// Now process the payments.
		$processed = self::process_payment( $data, $customer, $plan_id );
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
	 *
	 * @since 3.6.1
	 *
	 * @return bool True if payment was success.
	 */
	private static function process_payment( $process_data, $customer, $plan_id ) {
		global $psts;

		$processed = false;

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
			$total = self::charge_setup_fee( $amount, $customer );
		}

		// If a coupon is applied, adjust the amount.
		if ( isset( $process_data['COUPON_CODE'] ) ) {
			// Adjust the coupon amount.
			$coupon = self::apply_coupon( $process_data['COUPON_CODE'], $amount, $total );
		} else {
			$coupon = false;
		}

		if ( $recurring ) {
			// Stripe description.
			$desc = self::get_description( $amount, $total, $recurring );

			// Process recurring payment.
			$processed = self::process_recurring( $process_data, $plan_id, $customer, $tax_object, $coupon, $total );
		} else {
			// We are upgrading a blog, so calculate the upgrade cost.
			if ( ! empty( self::$blog_id ) ) {
				$total = $psts->calc_upgrade_cost( self::$blog_id, self::$level, self::$period, $total );
			}

			// Process one time payment.
			$processed = self::process_single( $process_data, $customer, $tax_object, $coupon, $total );
		}

		// Remove email body issue action.
		remove_action( 'phpmailer_init', 'psts_text_body' );

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
	 * @param float            $total      Total amount.
	 *
	 * @since 3.6.1
	 *
	 * @return bool True if payment was success.
	 */
	private static function process_recurring( $data, $plan_id, $customer, $tax_object, $coupon, $total ) {
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
				self::$email,
				$customer->id,
				$plan_id,
				$sub_args
			);

			// Now activate the blog.
			if ( ! empty( $subscription ) ) {
				self::extend_recurring_blog( $activation_key, $subscription, $data, $total, true );
			}

			if ( ! empty( self::$blog_id ) ) {
				// Make sure we set subscription data in DB.
				if ( $update_db && ! empty( $subscription->id ) && ! empty( $customer->id ) ) {
					ProSites_Gateway_Stripe::$stripe_customer->set_db_customer(
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
			}

			// We don't need the coupon anymore.
			if ( isset( $coupon->id ) ) {
				self::$stripe_plan->delete_coupon( $coupon->id );
			}
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
	 * @param \Stripe\Coupon   $coupon     Stripe coupon object.
	 * @param float            $total      Total amount.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	private static function process_single( $data, $customer, $tax_object, $coupon, $total ) {
		global $psts;

		$amount = 0;

		// If customer created, now let's create a subscription.
		if ( ! empty( $customer->id ) ) {
			// Calculate the total amount if we are upgrading.
			$total = $psts->calc_upgrade_cost( self::$blog_id, self::$level, self::$period, $total );

			// Activation key.
			$activation_key = self::get_activation_key( self::$blog_id );

			// Apply tax if required.
			if ( $tax_object->apply_tax ) {
				$amount   = $total + ( $total * $tax_object->tax_rate );
				$tax_rate = self::format_price( $tax_object->tax_rate );
			}

			// Stripe description.
			$desc = self::get_description( $amount, $total, false );

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

			// If trial is not applicable charge directly.
			if ( ! $psts->is_trial_allowed( self::$blog_id ) && $amount > 0 ) {
				$result = self::$stripe_charge->create_item(
					$customer->id,
					$amount,
					'charge',
					$desc,
					$charge_args
				);
			} else {
				// If trial is enabled, create invoice item.
				$result = self::$stripe_charge->create_item(
					$customer->id,
					$amount,
					'invoiceitem',
					$desc,
					$charge_args
				);
			}

			if ( ! empty( $result ) ) {
				// Activate the blog and extend.
				self::extend_single_blog( $activation_key, $data, $amount );
			}

			// Make sure we set customer data in DB.
			if ( ! empty( self::$blog_id ) && ! empty( $result->id ) && ! empty( $customer->id ) ) {
				ProSites_Gateway_Stripe::$stripe_customer->set_db_customer(
					self::$blog_id,
					$customer->id,
					null
				);
			}

			// We don't need the coupon anymore.
			if ( isset( $coupon->id ) ) {
				self::$stripe_plan->delete_coupon( $coupon->id );
			}
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
	 * @param int  $blog_id Blog ID.
	 * @param bool $force   Forcing reactivation manually.
	 *                      Set this to true if this function is not
	 *                      being called from reactivation form.
	 *
	 * @since 3.6.1
	 *
	 * @return void|bool
	 */
	public function manual_reactivation( $blog_id, $force = false ) {
		global $psts;

		// Do this only if forced or from reactivation form.
		if ( ! self::from_request( 'attempt_stripe_reactivation' ) && ! $force ) {
			return false;
		}

		// Get customer data.
		$customer_data = self::$stripe_customer->get_db_customer( $blog_id );

		// Initialize the subscription.
		$subscription = false;

		// Continue if subscription id found.
		if ( $customer_data->subscription_id ) {
			// Try to get the existing subscription.
			$subscription = self::$stripe_subscription->get_subscription( $customer_data->subscription_id );

			/**
			 * We can reactivate only if subscription is immediately cancelled.
			 * See https://stripe.com/docs/billing/subscriptions/canceling-pausing#reactivating-canceled-subscriptions
			 */
			if ( ! empty( $subscription->status ) && 'canceled' !== $subscription->status ) {
				// Do not cancel on end date.
				$subscription->cancel_at_period_end = false;
				// Save to reactivate.
				$subscription = $subscription->save();
			} else {
				$subscription = false;
			}
		}

		// Try to create new subscription.
		if ( empty( $subscription ) ) {
			$subscription = self::$stripe_subscription->set_blog_subscription(
				$blog_id,
				false,
				$customer_data->customer_id
			);
		}

		// Ok, success.
		if ( ! empty( $subscription ) ) {
			// Remove the flag.
			delete_blog_option( $blog_id, 'psts_stripe_canceled' );
			// Log the reactivation.
			$psts->log_action( $blog_id, __( 'Stripe subscription reactivated manually.', 'psts' ) );
		} else {
			$psts->log_action( $blog_id, __( 'Stripe cannot re-activate this subscription.', 'psts' ) );
		}
	}

	/**
	 * Extend a blog once the payment is successful.
	 *
	 * If new subscription we will activate the blog first.
	 *
	 * @param string              $activation_key Activation key.
	 * @param Stripe\Subscription $subscription   Stripe subscription.
	 * @param array               $data           Process data.
	 * @param int                 $total          Total amount.
	 * @param bool                $recurring      Is this a recurring payment.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	private static function extend_recurring_blog( $activation_key, $subscription, $data, $total = 0, $recurring = false ) {
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
				$expire
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
		self::maybe_extend( $total, $expire, true, $recurring );

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
			$result = ProSites_Helper_Registration::activate_blog( $activation_key, false, self::$period, self::$level );

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
	 * @param array $args      Additional arguments for email.
	 *
	 * @since 3.0
	 *
	 * @return bool
	 */
	private static function maybe_extend( $amount, $expire, $payment = true, $recurring = false, $args = array() ) {
		global $psts;

		// Initialize flag as false.
		$extended = false;

		// Set old and new Stripe plans.
		$new_plan = $old_plan = self::$stripe_plan->get_id( self::$level, self::$period );

		// Get existing site's data.
		$site_data = ProSites_Helper_ProSite::get_site( self::$blog_id );
		// If data found, get the existing plan id.
		if ( isset( $site_data->level, $site_data->period ) ) {
			$old_plan = self::$stripe_plan->get_id( $site_data->level, $site_data->period );
		}

		// Last email sent.
		$last_extended = (int) get_blog_option( self::$blog_id, 'psts_stripe_last_email_receipt' );

		// Last extended + 5 minutes.
		$last_extended = empty( $last_extended ) ? time() : $last_extended + 300;

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
				$recurring
			);

			// Flag extension.
			$extended = true;
		}

		// We need to send receipt, if not sent already.
		if ( $payment && time() > $last_extended ) {
			$psts->email_notification( self::$blog_id, 'receipt', false, $args );
			// Track email receipt sent.
			update_blog_option( self::$blog_id, 'psts_stripe_last_email_receipt', time() );
		}

		return $extended;
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
		$base_key = isset( $data['new_blog_details'] ) ? 'new_blog_details' : 'upgrade_blog_details';

		// Session values.
		$session_data = array(
			'level'           => self::$level,
			'period'          => self::$period,
			'blog_id'         => self::$blog_id,
			'payment_success' => true,
		);

		// Loop through each items and set.
		foreach ( $session_data as $key => $value ) {
			ProSites_Helper_Session::session( array(
				$base_key,
				$key,
			), $value );
		}
	}

	/**
	 * Adjust the amount applying the coupon.
	 *
	 * If a coupon is applied by the user on registration,
	 * apply that and adjust the payment amount.
	 *
	 * @param bool  $coupon Coupon code.
	 * @param float $amount Amount.
	 * @param float $total  Total amount.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|\Stripe\Coupon
	 */
	private static function apply_coupon( $coupon, $amount, &$total ) {
		// Do not continue if coupon is not valid.
		if ( ! ProSites_Helper_Coupons::check_coupon( $coupon, self::$blog_id, self::$level, self::$period, self::$domain ) ) {
			return false;
		}

		// Get adjusted amounts.
		$adjusted_values = ProSites_Helper_Coupons::get_adjusted_level_amounts( $coupon );
		// Get coupon properties.
		$coupon_obj = ProSites_Helper_Coupons::get_coupon( $coupon );
		// Get the lifetime of coupon.
		$lifetime = isset( $coupon_obj['lifetime'] ) && 'indefinite' === $coupon_obj['lifetime'] ? 'forever' : 'once';
		// Get the adjusted amount for the level and period.
		$coupon_value = $adjusted_values[ self::$level ][ 'price_' . self::$period ];

		// Now get the off amount.
		$amount_off = $amount - $coupon_value;
		// Round the value to two digits.
		$amount_off = number_format( $amount_off, 2, '.', '' );

		// Apply to total amount.
		$total -= $amount_off;

		// Avoid negative amounts.
		$total = 0 > $total ? 0 : $total;

		$args = array(
			'amount_off'      => self::format_price( $amount_off ),
			'duration'        => $lifetime,
			'currency'        => self::get_currency(),
			'max_redemptions' => 1,
		);

		// Create or get stripe coupon.
		$stripe_coupon = self::$stripe_plan->create_coupon( $args, $coupon );

		return $stripe_coupon;
	}

	/**
	 * Charge the setup fee in Stripe.
	 *
	 * Setup fee is charged separately as a Stripe invpice
	 * item and the payment will be process with next payment.
	 *
	 * @param float           $amount   Amount.
	 * @param Stripe\Customer $customer Stripe customer.
	 *
	 * @since 3.6.1
	 *
	 * @return float $total Total amount including setup fee.
	 */
	private static function charge_setup_fee( $amount, $customer ) {
		global $psts;

		// Get the setup fee.
		$setup_fee = (float) $psts->get_setting( 'setup_fee', 0 );
		// Include setup fee to total.
		$total = $setup_fee + $amount;

		// Now charge setup fee in Stripe.
		self::$stripe_charge->charge_setup_fee(
			$customer->id,
			$setup_fee
		);

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
	 *
	 * @since 3.6.1
	 *
	 * @return mixed|void
	 */
	private static function get_description( $amount, $total, $recurring ) {
		$desc = '';

		/**
		 * Filter to override the Stripe description.
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
		$email = empty( $current_user->user_email ) ? get_blog_option( self::$blog_id, 'admin_email' ) : $current_user->user_email;

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
	 * @param int $amount Amount as int.
	 *
	 * @since 3.6.1
	 *
	 * @return float|int
	 */
	public static function format_price( $amount ) {
		/**
		 * Zero decimal currencies.
		 *
		 * @param array List Currencies that has zero decimal.
		 */
		$zero_decimal_currencies = apply_filters( 'pro_sites_zero_decimal_currencies', array( 'JPY' ) );

		// If not a zero decimal currency, get float value.
		$amount = in_array( self::get_currency(), $zero_decimal_currencies, true )
			? $amount : floatval( $amount ) * 100;

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
}

// Register the gateway.
psts_register_gateway(
	'ProSites_Gateway_Stripe',
	__( 'Stripe', 'psts' ),
	__( 'Stripe handles everything, including storing cards, subscriptions, and direct payouts to your bank account.', 'psts' )
);
