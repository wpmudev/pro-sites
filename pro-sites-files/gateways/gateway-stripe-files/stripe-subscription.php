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
			$subscription = ProSites_Helper_Cache::get_cache( 'pro_sites_stripe_subscription_' . $id, 'psts' );
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
					ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_subscription_' . $id, $subscription, 'psts' );

					return $subscription;
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
	 * Create new subscription for a customer.
	 *
	 * @param string $customer_id Customer ID.
	 * @param string $plan_id     Plan ID.
	 * @param array  $args        Array of fields to set.
	 * @param bool   $error       Should add the errors?.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Subscription|false Created subscription object or false.
	 */
	public function create_subscription( $customer_id, $plan_id, $args = array(), $error = false ) {
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
				ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_subscription_' . $subscription->id, $subscription, 'psts' );
			} else {
				$subscription = false;
			}
		} catch ( \Exception $e ) {
			// Log error message.
			ProSites_Gateway_Stripe::error_log( $e->getMessage() );

			// Let the user know what happened.
			if ( $error ) {
				$GLOBALS['psts']->errors->add( 'stripe', $e->getMessage() );
			}

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
	 * @param string $id    Subscription ID.
	 * @param array  $args  Array of fields to update.
	 * @param bool   $error Should add the errors?.
	 *
	 * @return \Stripe\Subscription|false
	 */
	public function update_subscription( $id, $args = array(), $error = false ) {
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
				ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_subscription_' . $id, $subscription, 'psts' );
			}
		} catch ( \Exception $e ) {
			// Log error message.
			ProSites_Gateway_Stripe::error_log( $e->getMessage() );

			// Let the user know what happened.
			if ( $error ) {
				$GLOBALS['psts']->errors->add( 'stripe', $e->getMessage() );
			}

			// Oh well. Failure.
			$subscription = false;
		}

		return $subscription;
	}

	/**
	 * Cancel a subscription in Stripe using API.
	 *
	 * @param string      $id        Stripe subscription ID.
	 * @param bool        $immediate Cancel subscription immediately.
	 * @param bool|string $error     Error message.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Subscription|bool
	 */
	public function cancel_subscription( $id, $immediate = true, &$error = false ) {
		$cancelled = false;
		// Make sure we don't break.
		try {
			// First get the subscription.
			$subscription = $this->get_subscription( $id );
			// It is already canceled.
			if ( ! empty( $subscription->status ) && 'canceled' === $subscription->status ) {
				$cancelled = true;
			} elseif ( ! empty( $subscription ) && $immediate ) {
				// Cancel the subscription immediately.
				$cancelled = $subscription->cancel();
			} elseif ( ! empty( $subscription ) && ! $immediate ) {
				if ( empty( $subscription->cancel_at_period_end ) ) {
					// Oh we need to wait. Let's cancel on expiry.
					$subscription->cancel_at_period_end = true;
					$subscription                       = $subscription->save();
					ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_subscription_' . $id, $subscription, 'psts' );
				}
				$cancelled = true;
			}
		} catch ( \Exception $e ) {
			// Get the error message.
			$error = $e->getMessage();

			// Log error message.
			ProSites_Gateway_Stripe::error_log( $error );

			// Oh well. Failure.
			$cancelled = false;
		}

		// Delete cached subscription.
		if ( $cancelled && $immediate ) {
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
	 * Get subscription id from Pro Sites db.
	 *
	 * @param int  $blog_id Blog ID.
	 * @param bool $force   Force from Stripe.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|string
	 */
	public function get_subscription_id( $blog_id, $force = false ) {
		// Try to get data from customers table.
		$db_data = ProSites_Gateway_Stripe::$stripe_customer->get_db_customer(
			$blog_id,
			false, // Do not use email here, it will replace existing subscriptions.
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
	 * @param string      $customer_id Customer ID.
	 * @param string|bool $plan_id     Plan ID or false if plan id is in arguments.
	 * @param array       $args        Arguments for subscription.
	 * @param string      $desc        Description for log.
	 * @param string|bool $card        Card id for the payment (if empty default card will be used).
	 *
	 * @since 3.6.1
	 *
	 * @return bool|Stripe\Subscription
	 */
	public function set_blog_subscription( $blog_id, $customer_id, $plan_id = false, $args = array(), $desc = '', $card = false ) {
		global $psts;

		// Get the subscription id for the email/blog id.
		$subscription_id = $this->get_subscription_id( $blog_id );

		// Check if that subscription is valid now.
		$subscription = $this->get_subscription( $subscription_id );

		// Make sure plan id is set.
		$plan_id = $this->set_plan_id( $plan_id, $args );

		// Try to generate arguments if missing.
		$args = $this->subscription_args( $blog_id, $args );

		// Set default payment source.
		if ( ! empty( $card ) ) {
			$args['default_source'] = $card;
		}

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
			$subscription = $this->create_subscription( $customer_id, $plan_id, $args, true );

			// New subscription created.
			if ( ! empty( $subscription->id ) ) {
				$psts->log_action(
					$blog_id,
					sprintf( __( 'User creating new subscription via CC: Subscription created (%1$s) - Customer ID: %2$s', 'psts' ), $desc, $customer_id )
				);
			}
		} else {
			// Reactivate if a subscription was set to cancel at the end.
			if ( ! empty( $subscription->cancel_at_period_end ) ) {
				$args['cancel_at_period_end'] = false;
				$psts->log_action(
					$blog_id,
					// translators: %s Subscription id.
					sprintf( __( 'Previous cancelled subscription (%s) was successfully reactivated.', 'psts' ), $subscription_id )
				);
			}

			$plan_changed = false;

			// We have an active subscription, so update to new plan.
			$args['plan'] = $plan_id;

			// Get the existing plan id.
			$existing_plan = $subscription->plan->id;

			// If we are changing plans.
			if ( $plan_id !== $existing_plan ) {
				$plan_changed = true;
				// Update meta for plan change.
				if ( isset( $args['metadata'] ) ) {
					$args['metadata']['plan_change'] = 'yes';
				} else {
					// In case if no meta.
					$args['metadata'] = array(
						'plan_change' => 'yes',
					);
				}
			}

			// Update the subscription.
			$subscription = $this->update_subscription( $subscription_id, $args, true );

			if ( $plan_changed ) {
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
	 * @param int  $blog_id   Blog ID.
	 * @param bool $stripe    Should cancel Stripe subscription?.
	 * @param bool $immediate Cancel Stripe subscription immediately.
	 * @param bool $message   Show message?.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	public function cancel_blog_subscription( $blog_id, $stripe = true, $immediate = false, $message = false ) {
		global $psts, $current_user;

		// Blog cancellation status.
		$blog_canceled = (bool) get_blog_option( $blog_id, 'psts_is_canceled' );

		// Asked to cancel Stripe subscription.
		if ( $stripe ) {
			// Get the subscription data using blog id.
			$customer_data = ProSites_Gateway_Stripe::$stripe_customer->get_db_customer( $blog_id );
			// We need both subscription id and customer id.
			if ( ! empty( $customer_data->subscription_id ) ) {
				// Now cancel the subscription.
				if ( ! $this->cancel_subscription( $customer_data->subscription_id, $immediate ) ) {
					// Log about failure.
					$psts->log_action(
						$blog_id,
						sprintf( __( 'Attempt to cancel Stripe subscription for blog %d failed', 'psts' ), $blog_id )
					);

					return false;
				} else {
					// Stripe cancellation flag.
					update_blog_option( $blog_id, 'psts_stripe_canceled', 1 );
				}
			}
		}

		// No need to process the cancellation again, if already cancelled.
		if ( ! $blog_canceled ) {
			// Record cancel status.
			$psts->record_stat( $blog_id, 'cancel' );
			// Cancellation flag.
			update_blog_option( $blog_id, 'psts_is_canceled', 1 );
			// Get the expire date of the blog.
			$end_date = $psts->get_expire( $blog_id );
			// Log about cancellation.
			$psts->log_action(
				$blog_id,
				// translators: %1$s: Cancelled by user, %2$d: Subscription end date.
				sprintf(
					__( 'Subscription successfully cancelled by %1$s. They should continue to have access until %2$s', 'psts' ),
					empty( $current_user->display_name ) ? __( 'Stripe', 'psts' ) : $current_user->display_name,
					empty( $end_date ) ? '' : date_i18n( get_option( 'date_format' ), $end_date )
				)
			);

			// Send cancellation email notification.
			$psts->email_notification( $blog_id, 'canceled' );
		}

		// Show message if requested explicitly.
		if ( $message ) {
			// Show cancellation.
			add_filter( 'psts_blog_info_cancelled', '__return_true' );
			add_filter( 'psts_render_notification_information', function ( $info ) {
				$info['cancel'] = true;

				return $info;
			}, 10 );
		}

		return true;
	}

	/**
	 * Transfer blog subscription to another one.
	 *
	 * We need to update the Stripe subscription meta data
	 * with the new blog id.
	 *
	 * @param string $subscription_id Stripe subscription id.
	 * @param int    $new_blog_id     Blog id.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	public function transfer_blog_subscription( $subscription_id, $new_blog_id ) {
		// Get Stripe subscription.
		$subscription = $this->get_subscription( $subscription_id );
		// We need a valid subscription.
		if ( ! isset( $subscription->metadata ) ) {
			return false;
		}

		// Get the meta data.
		$metadata = $subscription->metadata;

		// We don't have to continue if we already have same blog id.
		if ( empty( $metadata['blog_id'] ) || $new_blog_id !== $metadata['blog_id'] ) {
			// Update to new blog id.
			$metadata['blog_id'] = $new_blog_id;
			// Update the subscription.
			$subscription = $this->update_subscription(
				$subscription_id,
				array(
					'metadata' => $metadata,
				)
			);
			// Make return success status.
			if ( empty( $subscription ) ) {
				return false;
			} else {
				return true;
			}
		}

		return true;
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

		// Initialize meta data.
		if ( ! isset( $args['metadata'] ) ) {
			$args['metadata'] = array();
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
			if ( ! isset( $args['metadata']['level'], $args['metadata']['period'] ) ) {
				$args['metadata']['level']   = $site_data->level;
				$args['metadata']['period']  = $site_data->term;
				$args['metadata']['blog_id'] = $blog_id;
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

	/**
	 * Get Stripe subscription from webhook data.
	 *
	 * We need to get the possible subscription id
	 * from event json and get the subscription data.
	 *
	 * @param object $object Stripe webhook data.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|Stripe\Subscription
	 */
	public function get_webhook_subscription( $object ) {
		// Do not continue if required data is empty.
		if ( empty( $object->object ) ) {
			return false;
		}

		// Subscription id.
		$subscription_id = false;

		// Get the subscription id in different events.
		switch ( $object->object ) {
			// On an invoice payment event.
			case 'invoice':
				$subscription_id = $object->subscription;
				break;
			// On a subscription create/update/delete event.
			case 'subscription':
				$subscription_id = $object->id;
				break;
			// On a charge dispute event.
			case 'dispute':
				// We need to get the charge object from Stripe.
				$charge = ProSites_Gateway_Stripe::$stripe_charge->get_charge( $object->charge );
				// If we have a valid charge object, get it's invoice.
				if ( ! empty( $charge->invoice ) ) {
					// Get the invoice object.
					$invoice = ProSites_Gateway_Stripe::$stripe_charge->get_invoice( $charge->invoice );
					// Get the subscription id from invoice object.
					$subscription_id = empty( $invoice->subscription ) ? false : $invoice->subscription;
				}
				break;
		}


		// Continue only if we have a valid subscription id.
		if ( empty( $subscription_id ) ) {
			return false;
		}

		// Get the subscription id.
		return $this->get_subscription( $subscription_id );
	}

	/**
	 * Get blog id using Stripe subscription id.
	 *
	 * @param string $sub_id Subscription ID.
	 * @param bool   $force  Should forcefully get from db?.
	 *
	 * @since 3.6.1
	 *
	 * @return int|false
	 */
	public function get_blog_id_by_subscription( $sub_id, $force = false ) {
		$blog_id = false;

		// We need subscription id.
		if ( empty( $sub_id ) ) {
			return $blog_id;
		}

		// If we can get from cache if not forced.
		if ( ! $force ) {
			// If something is there in cache return that.
			$blog_id = ProSites_Helper_Cache::get_cache( 'pro_sites_blog_id_by_subscription_ ' . $sub_id, 'psts' );
			if ( ! empty( $blog_id ) ) {
				return $blog_id;
			}
		}

		// Return early if data found.
		if ( empty( $blog_id ) ) {
			// First try to get from local db.
			$customer_data = ProSites_Gateway_Stripe::$stripe_customer->get_db_customer_by_subscription( $sub_id );
			// If found, use that.
			if ( ! empty( $customer_data->blog_id ) ) {
				$blog_id = $customer_data->blog_id;
			} else {
				// If not found locally, try to get the Stripe subscription.
				$subscription = $this->get_subscription( $sub_id );
				// If we have a blog id in subscription meta, get that.
				if ( ! empty( $subscription->metadata ) && isset( $subscription->metadata['blog_id'] ) ) {
					$blog_id = $subscription->metadata['blog_id'];
				}
			}

			// Set to cache.
			ProSites_Helper_Cache::set_cache( 'pro_sites_blog_id_by_subscription_ ' . $sub_id, $blog_id, 'psts' );
		}

		return $blog_id;
	}

	/**
	 * Get default card of a Stripe subscription.
	 *
	 * If not default source is set, will return false.
	 *
	 * @param string $sub_id Stripe subscription ID.
	 * @param bool   $force  Should force from cache?.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|\Stripe\Card
	 */
	public function default_card( $sub_id, $force = false ) {
		$card = false;

		// If not forced, try cache.
		if ( ! $force ) {
			// Try to get from cache.
			$card = ProSites_Helper_Cache::get_cache( 'pro_sites_stripe_sub_default_card_' . $sub_id, 'psts' );
			// If found in cache, return it.
			if ( ! empty( $card ) ) {
				return $card;
			}
		}

		// Get from Stripe API.
		if ( empty( $card ) ) {
			try {
				// Get Stripe customer.
				$subscription = $this->get_subscription( $sub_id );

				// If a default source is set, get the card data.
				if ( ! empty( $subscription->default_source ) && isset( $subscription->customer ) ) {
					// Get the card details.
					$card = ProSites_Gateway_Stripe::$stripe_customer->get_card(
						$subscription->default_source,
						$subscription->customer
					);

					// Set to cache.
					if ( ! empty( $card ) ) {
						ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_sub_default_card_' . $sub_id, $card, 'psts' );
					}
				}
			} catch ( \Exception $e ) {
				// Log error message.
				ProSites_Gateway_Stripe::error_log( $e->getMessage() );

				// Well. Failed.
				$card = false;
			}
		}

		return $card;
	}
}
