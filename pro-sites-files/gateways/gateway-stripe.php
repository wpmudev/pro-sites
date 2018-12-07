<?php
/**
 * Pro Sites (Gateway: Stripe Payment Gateway).
 *
 * @package    Gateways
 * @subpackage Stripe
 */

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
	private $table;

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
	 * Stripe secret key for API.
	 *
	 * @var string|null
	 *
	 * @since 3.6.1
	 */
	private $secret_key;

	/**
	 * ProSites_Gateway_Stripe constructor.
	 */
	public function __construct() {
		global $wpdb, $psts;

		// Our stripe table.
		$this->table = $wpdb->base_prefix . 'pro_sites_stripe_customers';

		// Get Stripe API key.
		$this->secret_key = $psts->get_setting( 'stripe_secret_key' );

		// Setup the Stripe library.
		$this->init_lib();

		// Run installation script.
		$this->install();

		// Gateway settings.
		add_action( 'psts_gateway_settings', array( $this, 'settings' ) );
		add_action( 'psts_settings_process', array( $this, 'settings_process' ), 10, 1 );

		// Handle webhook notifications.
		add_action( 'wp_ajax_nopriv_psts_stripe_webhook', array( $this, 'webhook_handler' ) );

		// Cancel subscriptions on blog deletion.
		add_action( 'delete_blog', array( $this, 'cancel_subscription' ) );
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
			require_once $psts->plugin_dir . 'gateways/gateway-stripe-files/stripe-plan.php';
			require_once $psts->plugin_dir . 'gateways/gateway-stripe-files/stripe-customer.php';
			require_once $psts->plugin_dir . 'gateways/gateway-stripe-files/stripe-subscription.php';
		}

		// We can not continue without API key.
		if ( ! empty( $this->secret_key ) ) {
			// Setup API key.
			Stripe\Stripe::setApiKey( $this->secret_key );
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
	 *
	 * @return void
	 */
	private function install() {
		global $psts;

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
	 * @param $gateway_class
	 */
	public function settings_process( $gateway_class ) {
		// If current gateway is Stripe, update the plans.
		if ( get_class() === $gateway_class ) {
			$this->update_plans(
				'psts_levels',
				get_site_option( 'psts_levels' ),
				get_site_option( 'psts_levels' )
			);
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
		include_once 'gateway-stripe-files/views/settings.php';
	}

	/**
	 * Update Stripe plans based on the levels.
	 *
	 * If levels changed, we need to make this happen in Stripe
	 * also. All the existing subscriptions will remain same unless
	 * it is updated.
	 *
	 * @since  3.6.1
	 * @access private
	 *
	 * @return void
	 */
	private function update_plans() {
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
}

// Register the gateway.
psts_register_gateway(
	'ProSites_Gateway_Stripe',
	__( 'Stripe', 'psts' ),
	__( 'Stripe handles everything, including storing cards, subscriptions, and direct payouts to your bank account.', 'psts' )
);
