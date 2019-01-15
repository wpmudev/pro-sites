<?php

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

/**
 * The stripe charge functionality of the plugin.
 *
 * @link   https://premium.wpmudev.org/project/pro-sites
 * @since  3.6.1
 *
 * @author Joel James <joel@incsub.com>
 */
class ProSites_Stripe_Charge {

	/**
	 * Get a charge object from Stripe.
	 *
	 * @param string $charge_id Charge ID.
	 * @param bool   $force     Skip cache?.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|mixed|\Stripe\Charge
	 */
	public function get_charge( $charge_id, $force = false ) {
		// If not forced, try cache.
		if ( ! $force ) {
			// Try to get from cache.
			$charge = wp_cache_get( 'pro_sites_stripe_charge_' . $charge_id, 'psts' );
			// If found in cache, return it.
			if ( ! empty( $charge ) ) {
				return $charge;
			}
		}

		// Get from Stripe API.
		if ( empty( $charge ) ) {
			// Make sure we don't break.
			try {
				$charge = \Stripe\Charge::retrieve( $charge_id );
				// If a plan found, return.
				if ( ! empty( $charge ) ) {
					// Set to cache so we can reuse it.
					wp_cache_set( 'pro_sites_stripe_charge_' . $charge_id, $charge, 'psts' );

					return $charge;
				}
			} catch ( \Exception $e ) {
				// Oh well.
				return false;
			}
		}

		return false;
	}

	/**
	 * Create an invoice-item or charge in Stripe.
	 *
	 * Create an invoice item or charge against a customer.
	 *
	 * @param string $customer_id Customer ID.
	 * @param int    $amount      Total amount.
	 * @param string $type        charge or invoiceitem.
	 * @param string $desc        Description.
	 * @param array  $args        Additional arguments
	 *                            (https://stripe.com/docs/api/charges/create?lang=php,
	 *                            https://stripe.com/docs/api/invoiceitems/create?lang=php).
	 *
	 * @since 3.6.1
	 *
	 * @return bool|\Stripe\InvoiceItem|\Stripe\Charge
	 */
	public function create_item( $customer_id, $amount, $type = 'charge', $desc = '', $args = array() ) {
		// Make sure arguments are array.
		$args = (array) $args;

		// Set required arguments.
		$args['customer'] = $customer_id;
		$args['amount']   = ProSites_Gateway_Stripe::format_price( $amount );
		$args['currency'] = ProSites_Gateway_Stripe::get_currency();
		if ( ! empty( $desc ) ) {
			$args['description'] = $desc;
		}

		// Make sure we don't break.
		try {
			// If a charge.
			if ( 'charge' === $type ) {
				$charge = \Stripe\Charge::create( $args );
			} else {
				// Let's create a subscription now.
				$charge = \Stripe\InvoiceItem::create( $args );
			}
		} catch ( \Exception $e ) {
			$charge = false;
		}

		return $charge;
	}

	/**
	 * Charge setup fee for a subscription.
	 *
	 * If a setup fee is set, we need to charge it
	 * separately as an invoice item.
	 * See https://stripe.com/docs/api/invoiceitems/create?lang=php
	 * This will be charged in next payment.
	 *
	 * @param string $customer Stripe customer id.
	 * @param mixed  $fee      Item fee.
	 *
	 * @since 3.6.1
	 *
	 * @return void
	 */
	public function charge_setup_fee( $customer, $fee ) {
		// Make sure we don't break.
		try {
			// Create invoice item.
			$this->create_item(
				$customer,
				$fee,
				'invoiceitem',
				__( 'One-time setup fee', 'psts' )
			);
		} catch ( \Exception $e ) {
			// Oh well.
		}
	}

	/**
	 * Refund a charge's amount.
	 *
	 * You can make partial refund or full refund.
	 *
	 * @param string   $charge_id Stripe charge id.
	 * @param bool|int $amount    Amount to refund. Ignore to refund full.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	public function refund_charge( $charge_id, $amount = false ) {
		// Get the charge object.
		$charge = $this->get_charge( $charge_id );

		// If charge object found continue.
		if ( ! empty( $charge ) ) {
			try {
				// Set the amount.
				$args = $amount ? array( 'amount' => $amount ) : null;

				// Process the refund.
				$charge->refund( $args );
			} catch ( \Exception $e ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Create an invoice in Stripe.
	 *
	 * Create an invoice against a subscription in Stripe.
	 * This can be used when upgrading a subscription.
	 *
	 * @param string $customer_id     Customer ID.
	 * @param string $subscription_id Subscription ID.
	 * @param bool   $pay             Should pay immediately?.
	 * @param array  $args            Additional arguments (https://stripe.com/docs/api/invoices/create?lang=php).
	 *
	 * @since 3.6.1
	 *
	 * @return bool|\Stripe\Invoice
	 */
	public function create_invoice( $customer_id, $subscription_id, $pay = false, $args = array() ) {
		// Make sure we don't break.
		try {
			// Set required arguments.
			$inv_args = array(
				'customer'     => $customer_id,
				'subscription' => $subscription_id,
			);

			// Assign each args to subscription fields array.
			if ( ! empty( $args ) ) {
				foreach ( $args as $key => $value ) {
					$inv_args[ $key ] = $value;
				}
			}

			// Let's create a subscription now.
			$invoice = \Stripe\Invoice::create( $inv_args );

			// If asked to pay immediately, pay now.
			if ( $pay ) {
				$invoice->pay();
			}
		} catch ( \Exception $e ) {
			$invoice = false;
		}

		return $invoice;
	}
}
