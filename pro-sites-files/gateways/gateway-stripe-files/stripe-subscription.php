<?php

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

/**
 * The stripe subscription functionality of the plugin.
 *
 * @link   https://premium.wpmudev.org/project/pro-sites
 * @since  3.6.1
 *
 * @author Joel James <joel@incsub.com>
 */
class ProSites_Stripe_Subscription {

	/**
	 * Retrieve a subscription from Stripe API.
	 *
	 * Get a Stripe subscription object using subscription id.
	 *
	 * @param string $id    Stripe subscription ID.
	 * @param bool   $force Should get from API forcefully.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Subscription|false
	 */
	public function get_subscription( $id, $force = false ) {
		// If not forced, try cache.
		if ( ! $force ) {
			// Try to get from cache.
			$subscription = wp_cache_get( 'pro_sites_stripe_subscription_' . $id, 'psts' );
			// If found in cache, return it.
			if ( ! empty( $subscription ) ) {
				return $subscription;
			}
		}

		// Get from Stripe API.
		if ( empty( $subscription ) ) {
			// Make sure we don't break.
			try {
				$subscription = Stripe\Subscription::retrieve( $id );
				// If a plan found, return.
				if ( ! empty( $subscription ) ) {
					// Set to cache so we can reuse it.
					wp_cache_set( 'pro_sites_stripe_subscription_' . $id, $subscription, 'psts' );

					return $subscription;
				}
			} catch ( \Exception $e ) {
				// Oh well.
				return false;
			}
		}

		return false;
	}

	/**
	 * Create new subscription for a customer.
	 *
	 * @param string $customer_id Customer ID.
	 * @param string $plan_id     Plan ID.
	 * @param array  $args        Array of fields to set.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Subscription|false Created subscription object or false.
	 */
	public function create_subscription( $customer_id, $plan_id, $args = array() ) {
		// Make sure we don't break.
		try {
			// Make sure we have a working plan.
			$plan = ProSites_Gateway_Stripe::$stripe_plan->get_plan( $plan_id );
			// Make sure we have a valid customer.
			$customer = ProSites_Gateway_Stripe::$stripe_customer->get_customer( $customer_id );
			// Create subscription if we have a valid customer and plan.
			if ( $plan && $customer ) {
				// Set default arguments.
				$sub_args = array(
					'customer' => $customer_id,
					'items'    => array(
						array(
							'plan' => $plan_id,
						),
					),
				);

				// Do not make plan conflicts.
				if ( isset( $args['plan'] ) ) {
					unset( $args['plan'] );
				}

				// Assign each args to subscription fields array.
				if ( ! empty( $args ) ) {
					foreach ( $args as $key => $value ) {
						$sub_args[ $key ] = $value;
					}
				}
				// Let's create a subscription now.
				$subscription = Stripe\Subscription::create( $sub_args );
				// Set to cache so we can reuse it.
				wp_cache_set( 'pro_sites_stripe_subscription_' . $subscription->id, $subscription, 'psts' );
			} else {
				$subscription = false;
			}
		} catch ( \Exception $e ) {
			$subscription = false;
		}

		return $subscription;
	}

	/**
	 * Update a subscription in Stripe.
	 *
	 * Please note that you can update only the available fields
	 * of a subscription. Refer https://stripe.com/docs/api/subscriptions/update?lang=php
	 * to get the list of fields.
	 * Pass the fields as an array in second argument in key -> value combination.
	 *
	 * @param string $id   Subscription ID.
	 * @param array  $args Array of fields to update.
	 *
	 * @return \Stripe\Subscription|false
	 */
	public function update_subscription( $id, $args = array() ) {
		// Make sure we don't break.
		try {
			// First get the subscription.
			$subscription = $this->get_subscription( $id );
			// Update only if args set.
			if ( ! empty( $args ) ) {
				// Assign each values to subscription array.
				foreach ( (array) $args as $key => $value ) {
					$subscription->{$key} = $value;
				}
				// Now let's save 'em.
				$subscription = $subscription->save();

				// Update cached subscription.
				wp_cache_set( 'pro_sites_stripe_subscription_' . $id, $subscription, 'psts' );
			}
		} catch ( \Exception $e ) {
			// Oh well. Failure.
			$subscription = false;
		}

		return $subscription;
	}

	/**
	 * Cancel a subscription in Stripe using API.
	 *
	 * @param string $id        Stripe subscription ID.
	 * @param bool   $immediate Cancel subscription immediately.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Subscription|bool
	 */
	public function cancel_subscription( $id, $immediate = true ) {
		$cancelled = false;
		// Make sure we don't break.
		try {
			// First get the subscription.
			$subscription = $this->get_subscription( $id );
			// Cancel the subscription immediately.
			if ( ! empty( $subscription ) && $immediate ) {
				$cancelled = $subscription->cancel();
			} elseif ( ! empty( $subscription ) && ! $immediate ) {
				// Oh we need to wait. Let's cancel on expiry.
				$subscription->cancel_at_period_end = true;
				$subscription->save();
			}
		} catch ( \Exception $e ) {
			// Oh well. Failure.
			$cancelled = false;
		}

		// Delete cached subscription.
		if ( $cancelled ) {
			wp_cache_delete( 'pro_sites_stripe_subscription_' . $id, 'psts' );
		}

		return $cancelled;
	}

	/**
	 * Get Stripe customer of a subscription.
	 *
	 * @param string $subscription_id Subscription ID.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Customer|false
	 */
	public function get_customer( $subscription_id ) {
		// Get the subscription.
		$subscription = $this->get_subscription( $subscription_id );

		// We have a valid subscription and customer.
		if ( ! empty( $subscription->customer ) ) {
			return ProSites_Gateway_Stripe::$stripe_customer->get_customer( $subscription->customer );
		}

		return false;
	}

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
				$charge = Stripe\Charge::retrieve( $charge_id );
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
	 * Get subscription id from Pro Sites db.
	 *
	 * @param int  $blog_id Blog ID.
	 * @param bool $email   Email address.
	 * @param bool $force   Force from Stripe.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|string
	 */
	public function get_subscription_id( $blog_id, $email = false, $force = false ) {
		// Try to get data from customers table.
		$db_data = ProSites_Gateway_Stripe::$stripe_customer->get_db_customer(
			$blog_id,
			$email,
			$force
		);

		// Subscription id found.
		if ( ! empty( $db_data->subscription_id ) ) {
			return $db_data->subscription_id;
		}

		return false;
	}

	/**
	 * Create new subscription for the blog.
	 *
	 * @param int         $blog_id     Blog ID.
	 * @param string      $email       Email address.
	 * @param string      $customer_id Customer ID.
	 * @param string|bool $plan_id     Plan ID or false if plan id is in arguments.
	 * @param array       $args        Arguments for subscription.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|Stripe\Subscription
	 */
	public function set_blog_subscription( $blog_id, $email, $customer_id, $plan_id = false, $args = array() ) {
		global $psts;

		// Get the subscription id for the email/blog id.
		$subscription_id = $this->get_subscription_id( $blog_id, $email );

		// Check if that subscription is valid now.
		$subscription = $this->get_subscription( $subscription_id );

		// If an existing site, try to generate arguments if missing.
		if ( empty( $args ) ) {
			$args = $this->get_existing_args( $blog_id );
		}

		// Make sure plan id is set.
		$plan_id = $this->set_plan_id( $plan_id, $args );

		// Subscription does not exist or cancelled, so create new.
		if ( empty( $subscription ) || 'canceled' === $subscription->status ) {
			// Add a log that subscription was deleted.
			if ( isset( $subscription->status ) && 'canceled' === $subscription->status ) {
				$psts->log_action(
					$blog_id,
					// translators: %s Subscription id.
					sprintf( __( 'Previous subscription (%s) was cancelled. So new subscription has been created.', 'psts' ), $subscription_id )
				);
			}

			// Now let's create new subscription.
			$subscription = $this->create_subscription( $customer_id, $plan_id, $args );
		} else {
			// We have an active subscription, so update to new plan.
			$args['plan'] = $plan_id;

			// Get the existing plan id.
			$existing_plan = $subscription->plan->id;

			// Update the subscription.
			$subscription = $this->update_subscription( $subscription_id, $args );

			if ( $plan_id !== $existing_plan ) {
				// Copy meta values from subscription.
				$inv_args = empty( $subscription->metadata ) ? array() : $subscription->metadata;

				// Create and charge a new invoice immediately for the subscription.
				$this->create_invoice( $customer_id, $subscription_id, true, $inv_args );
			}
		}

		// Let us add/update db details also.
		if ( ! empty( $subscription->id ) ) {
			ProSites_Gateway_Stripe::$stripe_customer->set_db_customer(
				$blog_id,
				$customer_id,
				$subscription->id
			);
		}

		return $subscription;
	}

	/**
	 * Cancel a subscription using blog id.
	 *
	 * Cancel a blog subscription which is registered
	 * using Stripe. All the cancellation actions are
	 * executed in this function.
	 * Note: We have removed legacy table support here because
	 * old table structure is no longer valid.
	 *
	 * @param int  $blog_id   Blog ID.
	 * @param bool $immediate Cancel subscription immediately.
	 * @param bool $message   Show message?.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	public function cancel_blog_subscription( $blog_id, $immediate = false, $message = false ) {
		global $psts, $current_user;

		$cancelled = false;

		// Get the subscription data using blog id.
		$customer_data = ProSites_Gateway_Stripe::$stripe_customer->get_db_customer( $blog_id );

		// We need both subscription id and customer id.
		if ( ! empty( $customer_data->subscription_id ) ) {
			// Now cancel the subscription.
			$cancelled = $this->cancel_subscription( $customer_data->subscription_id, $immediate );
		}

		// Ok so we have cancelled. Run the post-cancellation actions.
		if ( ! empty( $cancelled ) ) {
			// Record cancel status.
			$psts->record_stat( $blog_id, 'cancel' );

			// Send cancellation email notification.
			$psts->email_notification( $blog_id, 'canceled' );

			// Update the cancellation flag.
			update_blog_option( $blog_id, 'psts_stripe_canceled', 1 );
			update_blog_option( $blog_id, 'psts_is_canceled', 1 );

			// Get the expire date of the blog.
			$end_date = $psts->get_expire( $blog_id );

			$psts->log_action(
				$blog_id,
				// translators: %1$s: Cancelled by user, %2$d: Subscription end date.
				sprintf(
					__( 'Subscription successfully cancelled by %1$s. They should continue to have access until %2$s', 'psts' ),
					$current_user->display_name,
					empty( $end_date ) ? '' : date_i18n( get_option( 'date_format' ), $end_date )
				)
			);
		} else {
			$psts->log_action(
				$blog_id,
				sprintf( __( 'Attempt to cancel subscription for blog %d failed', 'psts' ), $blog_id )
			);
		}

		// Show message if requested explicitly.
		if ( $cancelled && $message ) {
			// @todo Come back here and do this.
		}

		return $cancelled;
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
			$args = array(
				'customer'    => $customer,
				'amount'      => ProSites_Gateway_Stripe::format_price( $fee ),
				'currency'    => ProSites_Gateway_Stripe::get_currency(),
				'description' => __( 'One-time setup fee', 'psts' ),
			);
			// Create invoice item.
			Stripe\InvoiceItem::create( $args );
		} catch ( \Exception $e ) {
			// Oh well.
		}
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
			$invoice = Stripe\Invoice::create( $inv_args );

			// If asked to pay immediately, pay now.
			if ( $pay ) {
				$invoice->pay();
			}
		} catch ( \Exception $e ) {
			$invoice = false;
		}

		return $invoice;
	}

	/**
	 * Get default arguments.
	 *
	 * For existing sites, we will set the level and
	 * period meta values also.
	 *
	 * @param int $blog_id Blog ID.
	 *
	 * @since 3.6.1
	 *
	 * @return array
	 */
	private function get_existing_args( $blog_id ) {
		// Get existing site's data.
		$site_data = ProSites_Helper_ProSite::get_site( $blog_id );

		// Arguments for the subscription.
		$args = array(
			'prorate' => true,
		);

		// If data found, get the existing plan id.
		if ( isset( $site_data->level, $site_data->period ) ) {
			// Arguments for the subscription.
			$args['metadata'] = array(
				'period'  => $site_data->term,
				'level'   => $site_data->level,
				'blog_id' => $blog_id,
			);

			// Set trial if enabled.
			$args = ProSites_Gateway_Stripe::set_trial( $args );
		}

		return $args;
	}

	/**
	 * Get plan id from arguments.
	 *
	 * If plan id is not given directly, get from
	 * the arguments.
	 *
	 * @param bool  $plan_id Plan ID or false.
	 * @param array $args    Arguments.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|string
	 */
	private function set_plan_id( $plan_id = false, $args = array() ) {
		// If plan id is not set, try to generate using arguments.
		if ( empty( $plan_id ) && ! empty( $args['level'] ) && ! empty( $args['period'] ) ) {
			$plan_id = ProSites_Gateway_Stripe::$stripe_plan->get_id(
				$args['level'],
				$args['period']
			);
		}

		return $plan_id;
	}
}
