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
			$charge = ProSites_Helper_Cache::get_cache( 'pro_sites_stripe_charge_' . $charge_id, 'psts' );
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
				// If a charge found, return.
				if ( ! empty( $charge ) ) {
					// Set to cache so we can reuse it.
					ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_charge_' . $charge_id, $charge, 'psts' );

					return $charge;
				}
			} catch ( \Exception $e ) {
				// Log error message.
				ProSites_Gateway_Stripe::error_log( $e->getMessage() );

				// Oh well.
				return false;
			}
		}

		return false;
	}

	/**
	 * Get an invoice object from Stripe.
	 *
	 * @param string $invoice_id Invoice ID.
	 * @param bool   $force      Skip cache?.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|mixed|\Stripe\Invoice
	 */
	public function get_invoice( $invoice_id, $force = false ) {
		// If not forced, try cache.
		if ( ! $force ) {
			// Try to get from cache.
			$invoice = ProSites_Helper_Cache::get_cache( 'pro_sites_stripe_invoice_' . $invoice_id, 'psts' );
			// If found in cache, return it.
			if ( ! empty( $invoice ) ) {
				return $invoice;
			}
		}

		// Get from Stripe API.
		if ( empty( $invoice ) ) {
			// Make sure we don't break.
			try {
				$invoice = \Stripe\Invoice::retrieve( $invoice_id );
				// If an invoice found, return.
				if ( ! empty( $invoice ) ) {
					// Set to cache so we can reuse it.
					ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_invoice_' . $invoice_id, $invoice, 'psts' );

					return $invoice;
				}
			} catch ( \Exception $e ) {
				// Log error message.
				ProSites_Gateway_Stripe::error_log( $e->getMessage() );

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
	 * @param bool   $error       Should add the errors?.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|\Stripe\InvoiceItem|\Stripe\Charge
	 */
	public function create_item( $customer_id, $amount, $type = 'charge', $desc = '', $args = array(), $error = false ) {
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
			// Log error message.
			ProSites_Gateway_Stripe::error_log( $e->getMessage() );

			// Let the user know what happened.
			if ( $error ) {
				$GLOBALS['psts']->errors->add( 'stripe', $e->getMessage() );
			}

			$charge = false;
		}

		return $charge;
	}

	/**
	 * Update a charge in Stripe.
	 *
	 * Please note that you can update only the available fields
	 * of a charge. Refer https://stripe.com/docs/api/charges/update?lang=php
	 * to get the list of fields.
	 * Pass the fields as an array in second argument in key -> value combination.
	 *
	 * @param string $id   Charge ID.
	 * @param array  $args Array of fields to update.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|\Stripe\Charge
	 */
	public function update_charge( $id, $args = array() ) {
		// Make sure we don't break.
		try {
			// First get the charge.
			$charge = $this->get_charge( $id );
			// Update only if args set.
			if ( ! empty( $charge ) && ! empty( $args ) ) {
				// Assign each values to charge array.
				foreach ( (array) $args as $key => $value ) {
					$charge->{$key} = $value;
				}
				// Now let's save 'em.
				$charge = $charge->save();

				// Update cached subscription.
				ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_charge_' . $id, $charge, 'psts' );
			}
		} catch ( \Exception $e ) {
			// Log error message.
			ProSites_Gateway_Stripe::error_log( $e->getMessage() );

			// Oh well. Failure.
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
				__( 'One-time setup fee', 'psts' ),
				array(),
				true
			);
		} catch ( \Exception $e ) {
			// Oh well.
			// Log error message.
			ProSites_Gateway_Stripe::error_log( $e->getMessage() );
		}
	}

	/**
	 * Refund a charge's amount.
	 *
	 * You can make partial refund or full refund.
	 *
	 * @param string      $charge_id Stripe charge id.
	 * @param bool|int    $amount    Amount to refund. Ignore to refund full.
	 * @param bool|string $error     Error message.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	public function refund_charge( $charge_id, $amount = false, &$error = 'Unknown' ) {
		// Get the charge object.
		$charge = $this->get_charge( $charge_id );

		// If charge object found continue.
		if ( ! empty( $charge ) ) {
			try {
				// Set the amount.
				$args = $amount ? array( 'amount' => $amount ) : null;

				// Process the refund.
				if ( $charge->refund( $args ) ) {
					return true;
				}
			} catch ( \Exception $e ) {
				// Get the error message in case required.
				$error = $e->getMessage();

				return false;
			}
		}

		return false;
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
			// Log error message.
			ProSites_Gateway_Stripe::error_log( $e->getMessage() );

			$invoice = false;
		}

		return $invoice;
	}

	/**
	 * Create a new card source for the customer.
	 *
	 * We will create a new card from the given source and
	 * attach to the customer.
	 *
	 * @param string          $token    Token for source.
	 * @param Stripe\Customer $customer Customer object.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|Stripe\Card
	 */
	public function create_card( $token, $customer ) {
		// Do not continue if we don't have a valid customer or token.
		if ( ! isset( $customer->sources ) || empty( $token ) ) {
			return false;
		}

		// Make sure we don't break.
		try {
			// Create new card.
			$card = $customer->sources->create( array(
				'source' => $token,
			) );
		} catch ( \Exception $e ) {
			// Log error message.
			ProSites_Gateway_Stripe::error_log( $e->getMessage() );

			$card = false;
		}

		return $card;
	}

	/**
	 * Get the invoice items from Stripe webhook data.
	 *
	 * @param object $object Stripe webhook data.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|array
	 */
	public function get_webhook_invoice_items( $object ) {
		$invoice_items = false;

		// Do not continue if required data is empty.
		if ( empty( $object->object ) ) {
			return false;
		}

		$plan_change = false;

		if ( 'invoice' === $object->object ) {
			// Initialize invoice item model.
			$invoice_items = new ProSites_Model_Receipt();

			// We have a changed plan.
			if ( isset( $object->metadata->plan_change ) && 'yes' === $object->metadata->plan_change ) {
				$plan_change = true;
			}

			// Loop through each line items.
			foreach ( $object->lines->data as $line_item ) {
				// Don't process subscription now.
				if ( 'subscription' === $line_item->type ) {
					continue;
				}

				// Get upgrades/downgrades.
				if ( $plan_change && $line_item->proration ) {
					// Get plan name.
					$plan_name = empty( $line_item->plan ) ? '' : $line_item->plan->nickname;
					// Get total amount.
					$amount = ProSites_Gateway_Stripe::format_price( $line_item->amount, false );
					// Add new invoice item.
					$invoice_items->add_item(
						$amount,
						sprintf( __( 'Plan Adjustments: %s', 'psts' ), $plan_name )
					);
				}
			}

			// If discount applied, get details.
			if ( isset( $object->discount->coupon ) && ! empty( $invoice_items ) ) {
				$discount = ProSites_Gateway_Stripe::format_price( $object->discount->coupon->amount_off, false );
				$invoice_items->add_item( $discount, __( 'Coupon Applied', 'psts' ) );
			}

			// Get invoice items.
			if ( ! empty( $invoice_items ) ) {
				$invoice_items = $invoice_items->get_items();
			}

			// We don't need to set invoice item if no items found.
			$invoice_items = ( ! empty( $invoice_items ) && count( $invoice_items ) > 0 ) ? $invoice_items : false;
		}

		return $invoice_items;
	}

	/**
	 * Get total amount from Stripe webhook data.
	 *
	 * @param object $object Stripe webhook data.
	 *
	 * @since 3.6.1
	 *
	 * @return int
	 */
	public function get_webhook_total_amount( $object ) {
		$total = 0;

		// Do not continue if required data is empty.
		if ( empty( $object->object ) ) {
			return $total;
		}

		// Get the total amount id in different events.
		switch ( $object->object ) {
			// On an invoice payment event.
			case 'invoice':
				// Get the invoice total.
				$total = $object->total;
				break;
			// On a subscription create/update/delete event.
			case 'subscription':
				// Get the plan amount.
				$total = isset( $object->plan->amount ) ? $object->plan->amount : 0;
				break;
			// On a charge dispute event.
			case 'dispute':
				// Dispute amount.
				$total = $object->amount;
				break;
		}

		return $total;
	}
}
