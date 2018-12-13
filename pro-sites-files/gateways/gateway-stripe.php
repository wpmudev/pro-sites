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
	private $id = 'stripe';

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
	 * Current level of the site.
	 *
	 * This will be 0 if no site is set yet.
	 *
	 * @var int
	 *
	 * @since 3.6.1
	 */
	private static $level = 0;

	/**
	 * Current period of the site.
	 *
	 * This will be 0 if no site is set yet.
	 *
	 * @var int
	 *
	 * @since 3.6.1
	 */
	private static $period = 0;

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

		// Cancel subscriptions on blog deletion.
		add_action( 'delete_blog', array( $this, 'cancel_subscription' ) );

		// Should we force SSL?.
		add_filter( 'psts_force_ssl', array( $this, 'force_ssl' ) );
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
		}

		// Set the sub class objects.
		self::$stripe_plan         = new ProSites_Stripe_Plan();
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
	public function get_slug() {
		return $this->id;
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
		if ( empty( $stripe_version ) || $stripe_version != $psts->version ) {
			$this->create_tables();
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
	}

	/**
	 * Cancel a particular blog's subscription.
	 *
	 * @param int  $blog_id      Blog ID.
	 * @param bool $show_message Display cancellation message.
	 *
	 * @since 3.6.1
	 *
	 * @return void
	 */
	public function cancel_subscription( $blog_id, $show_message = false ) {
		// Get the customer data.
		$customer = $this->get_customer( $blog_id );

		// Can't really do anything.
		if ( empty( $customer ) ) {
			return;
		}

		// Should we show cancellation message?
		if ( $show_message ) {
			// Show cancellation message.
		}
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

		// No plans. Oh boy.
		if ( empty( $levels ) && empty( $old_levels ) ) {
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

		// Amount to pay.
		$amount = 500;
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
		foreach ( array( 'new_blog_details', 'upgraded_blog_details', 'activation_key' ) as $key ) {
			// If missing, try to get from session.
			$render_data[ $key ] = isset( $render_data[ $key ] ) ? $render_data[ $key ] : ProSites_Helper_Session::session( $key );
		}

		// Set the level.
		self::$level = self::from_request( 'level' );
		// Set the period.
		self::$period = self::from_request( 'period' );

		// Get the Stripe data.
		$stripe_token = self::from_request( 'stripeToken' );

		// New blog id.
		self::$blog_id = empty( $blog_id ) ? self::from_request( 'bid', 0, false ) : $blog_id;

		// Domain name.
		self::$domain = $domain;

		// We need to get the email.
		self::$email = self::get_email( $data );

		// Do not continue if level and period is not set.
		if ( empty( self::$level ) || empty( self::$period ) ) {
			$psts->errors->add( 'general', __( 'Please choose your desired level and payment plan.', 'psts' ) );
		}

		// We need Stripe token.
		if ( empty( $stripe_token ) ) {
			$psts->errors->add( 'stripe', __( 'There was an error processing your Credit Card with Stripe. Please try again.', 'psts' ) );
		}

		// We have level and period, so get the Stripe plan id.
		$plan_id = self::$stripe_plan->get_id( self::$level, self::$period );

		// Do not continue if plan does not exist.
		if ( ! self::$stripe_plan->get_plan( $plan_id ) ) {
			// translators: %1$s Stripe plan ID.
			$psts->errors->add( 'general', sprintf( __( 'Stripe plan %1$s does not exist.', 'psts' ), $plan_id ) );
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
		// Get the tax evidence.
		$evidence_string = ProSites_Helper_Tax::get_evidence_string( $tax_object );

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
			$processed = self::process_recurring( $process_data, $plan_id, $customer, $tax_object, $coupon );
		} else {
			// We are upgrading a blog, so calculate the upgrade cost.
			if ( ! empty( self::$blog_id ) ) {
				$total = $psts->calc_upgrade_cost( self::$blog_id, self::$level, self::$period, $total );
			}

			// Stripe description.
			$desc = self::get_description( $amount, $total, $recurring );

			// Process one time payment.
			$processed = self::process_single( $process_data, $customer, $tax_object, $coupon );
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
	 *
	 * @since 3.6.1
	 *
	 * @return bool True if payment was success.
	 */
	private static function process_recurring( $data, $plan_id, $customer, $tax_object, $coupon ) {
		global $psts;

		// Activation key.
		$activation_key = self::from_request( 'activation', '' );

		// If customer created, now let's create a subscription.
		if ( ! empty( $customer->id ) ) {
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
				$sub_args = self::set_trial( self::$blog_id, $sub_args, $data );
			}

			// Meta data for blog details.
			$sub_args['metadata'] = array(
				'blog_id'    => self::$blog_id,
				'domain'     => self::$domain,
				'period'     => self::$period,
				'level'      => self::$level,
				'activation' => $activation_key,
			);

			// Now create the subscription.
			$subscription = self::$stripe_subscription->set_blog_subscription( self::$blog_id, self::$email, $customer->id, $plan_id, $sub_args );

			// Now activate the blog.
			if ( ! empty( $subscription ) ) {
				self::activate_blog( $activation_key, $subscription, $data );
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
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	private static function process_single( $data, $customer, $tax_object, $coupon ) {

		return false;
	}

	/**
	 * Activate a blog once the payment is successful.
	 *
	 * @param string              $activation_key Activation key.
	 * @param Stripe\Subscription $subscription   Stripe subscription.
	 * @param array               $data           Process data.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	private static function activate_blog( $activation_key, $subscription, $data ) {
		$activated = false;

		// Try to get the activation key using blog id.
		if ( empty( $activation_key ) && ! empty( self::$blog_id ) ) {
			$activation_key = ProSites_Helper_ProSite::get_activation_key( self::$blog_id );
		}

		// Is current subscription on trial.
		$is_trial = isset( $subscription->status ) && 'trialing' === $subscription->status;

		// Get the expiry date.
		$expire = $is_trial ? $subscription->trial_end : $subscription->current_period_end;

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

		// Set values to session.
		self::set_session_data( $data );

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
			'amount_off' => self::format_price( $amount_off ),
			'duration'   => $lifetime,
			'currency'   => self::get_currency(),
			//'max_redemptions' => 1,
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
		self::$stripe_subscription->charge_setup_fee(
			$customer->id,
			$setup_fee
		);

		return $total;
	}

	/**
	 * Set trial for the subscription.
	 *
	 * If trial is enabled add trial end date to the
	 * Stripe subscription.
	 *
	 * @param array $args Subscription arguments.
	 * @param array $data Form data.
	 *
	 * @since 3.6.1
	 *
	 * @return array
	 */
	private static function set_trial( $args = array(), $data = array() ) {
		global $psts;

		// No. of trial days.
		$trial_days = $psts->get_setting( 'trial_days', 0 );

		// Customer is new so add trial days.
		if ( isset( $data['new_blog_details'] ) || ! $psts->is_existing( self::$blog_id ) ) {
			$args['trial_end'] = strtotime( '+ ' . $trial_days . ' days' );
		} elseif ( is_pro_trial( self::$blog_id ) && $psts->get_expire( self::$blog_id ) > time() ) {
			// Customer's trial is still valid so carry over existing expiration date.
			$args['trial_end'] = $psts->get_expire( self::$blog_id );
		}

		return $args;
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
}

// Register the gateway.
psts_register_gateway(
	'ProSites_Gateway_Stripe',
	__( 'Stripe', 'psts' ),
	__( 'Stripe handles everything, including storing cards, subscriptions, and direct payouts to your bank account.', 'psts' )
);
