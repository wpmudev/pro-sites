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
				$subscription = \Stripe\Subscription::retrieve( $id );
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
				$subscription = \Stripe\Subscription::create( $sub_args );
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
			if ( ! empty( $subscription ) && ! empty( $args ) ) {
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
	 * Get Stripe subscription using blog id.
	 *
	 * If subscription id is set in db, we will get
	 * the subscription object from API.
	 *
	 * @param int $blog_id Blog ID.
	 *
	 * @since 3.6.1
	 *
	 * @return Stripe\Subscription|bool
	 */
	public function get_subscription_by_blog( $blog_id ) {
		$subscription = false;

		// Get subscription id of the blog.
		$sub_id = $this->get_subscription_id( $blog_id );

		// Now try to get the Stripe subscription.
		if ( ! empty( $sub_id ) ) {
			$subscription = $this->get_subscription( $sub_id );
		}

		return $subscription;
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

		// Make sure plan id is set.
		$plan_id = $this->set_plan_id( $plan_id, $args );

		// Try to generate arguments if missing.
		$args = $this->subscription_args( $blog_id, $args );

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
				ProSites_Gateway_Stripe::$stripe_charge->create_invoice( $customer_id, $subscription_id, true, $inv_args );
			}
		}

		// Let us add/update db details also.
		if ( ! empty( $subscription->id ) && ! empty( $blog_id ) ) {
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
	 * @param int  $blog_id     Blog ID.
	 * @param bool $immediate   Cancel subscription immediately.
	 * @param bool $message     Show message?.
	 * @param bool $stripe_only Should cancel only Stripe subscription?.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	public function cancel_blog_subscription( $blog_id, $immediate = false, $message = false, $stripe_only = false ) {
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
		if ( ! empty( $cancelled ) && ! $stripe_only ) {
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
		} elseif ( ! $stripe_only ) {
			$psts->log_action(
				$blog_id,
				sprintf( __( 'Attempt to cancel Stripe subscription for blog %d failed', 'psts' ), $blog_id )
			);
		}

		// Show message if requested explicitly.
		if ( $cancelled && $message ) {
			// Show cancellation.
			add_filter( 'psts_blog_info_cancelled', '__return_true' );
		}

		return $cancelled;
	}

	/**
	 * Get default arguments.
	 *
	 * For existing sites, we will set the level and
	 * period meta values also.
	 *
	 * @param int   $blog_id Blog ID.
	 * @param array $args    Argumes.
	 *
	 * @since 3.6.1
	 *
	 * @return array
	 */
	private function subscription_args( $blog_id, $args = array() ) {
		global $psts;

		// Default args.
		if ( empty( $args ) ) {
			$args = array(
				'prorate' => true,
			);
		}

		// If it is a new blog, try if we can set missing values.
		if ( ! ProSites_Gateway_Stripe::$existing ) {
			$args['metadata'] = array(
				'period'     => empty( $args['period'] ) ? ProSites_Gateway_Stripe::$period : $args['period'],
				'level'      => empty( $args['level'] ) ? ProSites_Gateway_Stripe::$level : $args['level'],
				'blog_id'    => empty( $args['blog_id'] ) ? $blog_id : $args['blog_id'],
				'activation' => empty( $args['activation'] ) ? '' : $args['activation'],
				'domain'     => empty( $args['domain'] ) ? '' : $args['domain'],
			);

			// Set trial for new sites, if not already added.
			if ( ! isset( $args['trial_end'] ) && $psts->is_trial_allowed( $blog_id ) ) {
				$args = ProSites_Gateway_Stripe::set_trial( $args );
			}

			return $args;
		}

		// Get existing site's data.
		$site_data = ProSites_Helper_ProSite::get_site( $blog_id );

		// If required data is found.
		if ( isset( $site_data->level, $site_data->term ) ) {
			// In case level and period is not set.
			if ( ! isset( $args['level'], $args['period'] ) ) {
				$args['metadata'] = array(
					'period'  => $site_data->term,
					'level'   => $site_data->level,
					'blog_id' => $blog_id,
				);
			}

			// If we are upgrading to new level.
			if ( isset( $args['metadata']['level'], $args['metadata']['period'] ) && (
					$args['metadata']['level'] !== $site_data->level ||
					$args['metadata']['period'] !== $site_data->term
				)
			) {
				$updated = array(
					'render'      => true,
					'blog_id'     => $blog_id,
					'level'       => $args['level'],
					'period'      => $args['period'],
					'prev_level'  => $site_data->level,
					'prev_period' => $site_data->term,
				);

				ProSites_Helper_Session::session( 'plan_updated', $updated );
			}
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
