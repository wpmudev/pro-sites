<?php

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

/**
 * The stripe customer functionality of the plugin.
 *
 * @link   https://premium.wpmudev.org/project/pro-sites
 * @since  3.6.1
 *
 * @author Joel James <joel@incsub.com>
 */
class ProSites_Stripe_Customer {

	/**
	 * Retrieve a customer from Stripe.
	 *
	 * Get a Stripe customer object using customer id.
	 *
	 * @param string $id    Stripe customer ID.
	 * @param bool   $force Should get from API forcefully.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Customer|false
	 */
	public function get_customer( $id, $force = false ) {
		$customer = false;

		// If not forced, try cache.
		if ( ! $force ) {
			// Try to get from cache.
			$customer = ProSites_Helper_Cache::get_cache( 'pro_sites_stripe_customer_' . $id, 'psts' );
			// If found in cache, return it.
			if ( ! empty( $customer ) ) {
				return $customer;
			}
		}

		// Get from Stripe API.
		if ( empty( $customer ) ) {
			// Make sure we don't break.
			try {
				$customer = Stripe\Customer::retrieve( $id );
				// If a plan found, return.
				if ( ! empty( $customer ) ) {
					// Set to cache so we can reuse it.
					ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_customer_' . $id, $customer, 'psts' );

					return $customer;
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
	 * List customers from Stripe.
	 *
	 * Get existing customers from Stripe using email.
	 * If limit is set to 1, then it will return the customer
	 * object directly if found.
	 *
	 * @param string $email Stripe customer ID.
	 * @param int    $limit Limit no. of items.
	 * @param bool   $force Should get from API forcefully.
	 *
	 * @since 3.6.1
	 *
	 * @return Stripe\Customer|array|bool Array of Stripe\Customer objects or false.
	 */
	public function list_customers( $email, $limit = 10, $force = false ) {
		// Initialize as array.
		$customers = array();

		// If not forced, try cache.
		if ( ! $force ) {
			// Try to get from cache.
			$customers = ProSites_Helper_Cache::get_cache( 'pro_sites_stripe_list_customers_' . $email, 'psts' );
		}

		// Get from Stripe API.
		if ( empty( $customers ) ) {
			// Make sure we don't break.
			try {
				// Get from Stripe.
				$customers_list = Stripe\Customer::all( array(
					'email' => $email,
					'limit' => $limit,
				) );

				// If customers found, return.
				if ( ! empty( $customers_list->data ) ) {
					// Set to cache so we can reuse it.
					ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_list_customers_' . $email, $customers_list->data, 'psts' );

					$customers = $customers_list->data;
				}
			} catch ( \Exception $e ) {
				// Log error message.
				ProSites_Gateway_Stripe::error_log( $e->getMessage() );

				// Oh well.
				$customers = array();
			}
		}

		// If limit is set to 1, return the object directly.
		if ( ! empty( $customers ) && 1 === $limit ) {
			// Get the first customer.
			$customers = reset( $customers );

			// Set to cache so we can reuse it.
			ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_customer_' . $customers->id, $customers, 'psts' );
		}

		return $customers;
	}

	/**
	 * Create a customer in Stripe.
	 *
	 * We will create the customer using user's
	 * email address.
	 *
	 * @param string $email          Email address.
	 * @param string $source         Source.
	 * @param bool   $check_existing Should check for existing customer using email?.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Customer|false
	 */
	public function create_customer( $email, $source, $check_existing = false ) {
		global $psts;

		// If a customer exist in Stripe with same email, get that.
		if ( $check_existing ) {
			// Get the first customer using email.
			$customer = $this->list_customers( $email, 1 );

			// If found, return that.
			if ( ! empty( $customer ) ) {
				return $customer;
			}
		}

		// Get the main site details.
		$site_name = get_blog_details( 1 )->blogname;

		// Customer arguments.
		$args = array(
			'email'       => $email,
			'source'      => $source,
			'description' => $site_name,
		);

		// Get WP user by email.
		$user = get_user_by( 'email', $email );
		// Set a custom description if we can.
		if ( ! empty( $user ) ) {
			// translators: %1$s Site name, %2$s User's display name.
			$args['description'] = sprintf( __( '%1$s user - %2$s ', 'psts' ), $site_name, $user->display_name );
			// Set username to meta.
			$args['metadata']['user'] = $user->user_login;
		}

		// Make sure we don't break.
		try {
			// Let's create a customer now.
			$customer = Stripe\Customer::create( $args );
			// Set to cache so we can reuse it.
			ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_customer_' . $customer->id, $customer, 'psts' );
		} catch ( \Exception $e ) {
			$customer = false;
			// Oh well.
			$psts->errors->add(
				'stripe',
				__( 'The Stripe customer could not be created. Please try again.', 'psts' )
			);
		}

		return $customer;
	}

	/**
	 * Update a customer in Stripe.
	 *
	 * Please note that you can update only the available fields
	 * of a customer. Refer https://stripe.com/docs/api/customers/update?lang=php
	 * to get the list of fields.
	 * Pass the fields as an array in second argument in key -> value combination.
	 *
	 * @param string $id   Customer ID.
	 * @param array  $args Array of fields to update.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Customer|false
	 */
	public function update_customer( $id, $args ) {
		// Make sure we don't break.
		try {
			// First get the customer.
			$customer = $this->get_customer( $id );
			// Update only if args set.
			if ( ! empty( $args ) ) {
				// Assign each values to customer array.
				foreach ( (array) $args as $key => $value ) {
					$customer->{$key} = $value;
				}
				// Now let's save 'em.
				$customer = $customer->save();

				// Update cached customer.
				ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_customer_' . $id, $customer, 'psts' );
			}
		} catch ( \Exception $e ) {
			// Log error message.
			ProSites_Gateway_Stripe::error_log( $e->getMessage() );

			// Oh well. Failure.
			$customer = false;
		}

		return $customer;
	}

	/**
	 * Delete a customer in Stripe.
	 *
	 * Please note that this will permanently deletes a customer.
	 * It cannot be undone. Also immediately cancels any active
	 * subscriptions on the customer.
	 *
	 * @param string $id Customer ID.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Customer|false
	 */
	public function delete_customer( $id ) {
		// Make sure we don't break.
		try {
			// First get the customer.
			$customer = $this->get_customer( $id );
			// Delete only if it is valid.
			if ( ! empty( $customer ) ) {
				// Now let's save 'em.
				$customer = $customer->delete();

				// Delete cached customer.
				wp_cache_delete( 'pro_sites_stripe_customer_' . $id, 'psts' );
			}
		} catch ( \Exception $e ) {
			// Log error message.
			ProSites_Gateway_Stripe::error_log( $e->getMessage() );

			// Oh well. Failure.
			$customer = false;
		}

		return $customer;
	}

	/**
	 * Get default card of a Stripe customer.
	 *
	 * @param string $customer_id Stripe customer ID.
	 * @param bool   $force       Should force from cache?.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|\Stripe\Card
	 */
	public function default_card( $customer_id, $force = false ) {
		$card = false;

		// If not forced, try cache.
		if ( ! $force ) {
			// Try to get from cache.
			$card = ProSites_Helper_Cache::get_cache( 'pro_sites_stripe_default_card_' . $customer_id, 'psts' );
			// If found in cache, return it.
			if ( ! empty( $card ) ) {
				return $card;
			}
		}

		// Get from Stripe API.
		if ( empty( $card ) ) {
			try {
				// Get Stripe customer.
				$customer = $this->get_customer( $customer_id );

				if ( ! empty( $customer->default_source ) ) {
					// Default card.
					$card = $customer->sources->retrieve( $customer->default_source );

					// Set to cache.
					ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_default_card_' . $customer_id, $card, 'psts' );
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

	/**
	 * Get a card from Stripe API.
	 *
	 * Cards are attached to customers, so we need
	 * customer id to get the card details.
	 *
	 * @param string $card_id     Stripe card ID.
	 * @param string $customer_id Stripe customer ID.
	 * @param bool   $force       Should force from cache?.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|\Stripe\Card
	 */
	public function get_card( $card_id, $customer_id, $force = false ) {
		$card = false;

		// If not forced, try cache.
		if ( ! $force ) {
			// Try to get from cache.
			$card = ProSites_Helper_Cache::get_cache( 'pro_sites_stripe_get_card_' . $card_id, 'psts' );
			// If found in cache, return it.
			if ( ! empty( $card ) ) {
				return $card;
			}
		}

		// Get from Stripe API.
		if ( empty( $card ) ) {
			try {
				// Get Stripe customer.
				$customer = $this->get_customer( $customer_id );
				// Default card.
				$card = $customer->sources->retrieve( $card_id );
				if ( ! empty( $card ) ) {
					// Set to cache.
					ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_get_card_' . $card_id, $card, 'psts' );
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

	/**
	 * Get last paid invoice of the customer.
	 *
	 * @param string $customer_id Customer ID.
	 * @param bool   $force       Skip from cache?.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|mixed|\Stripe\Invoice
	 */
	public function last_invoice( $customer_id, $force = false ) {
		$invoice = false;

		// If not forced, try cache.
		if ( ! $force ) {
			// Try to get from cache.
			$invoice = ProSites_Helper_Cache::get_cache( 'pro_sites_stripe_last_invoice_' . $customer_id, 'psts' );
			// If found in cache, return it.
			if ( ! empty( $invoice ) ) {
				return $invoice;
			}
		}

		// Get from Stripe API.
		if ( empty( $invoice ) ) {
			try {
				// Get the invoice of customer.
				$invoices = Stripe\Invoice::all( array(
					'customer' => $customer_id,
					'limit'    => 1,
				) );

				if ( ! empty( $invoices->data ) ) {
					$invoice = reset( $invoices->data );
					// Set to cache.
					ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_last_invoice_' . $customer_id, $invoice, 'psts' );
				}
			} catch ( \Exception $e ) {
				// Log error message.
				ProSites_Gateway_Stripe::error_log( $e->getMessage() );

				// Well. Failed.
				$invoice = false;
			}
		}

		return $invoice;
	}

	/**
	 * Get upcoming invoice of the customer.
	 *
	 * @param string $customer_id Customer ID.
	 * @param bool   $force       Skip from cache?.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|mixed|\Stripe\Invoice
	 */
	public function upcoming_invoice( $customer_id, $force = false ) {
		$invoice = false;

		// If not forced, try cache.
		if ( ! $force ) {
			// Try to get from cache.
			$invoice = ProSites_Helper_Cache::get_cache( 'pro_sites_stripe_upcoming_invoice_' . $customer_id, 'psts' );
			// If found in cache, return it.
			if ( ! empty( $invoice ) ) {
				return $invoice;
			}
		}

		// Get from Stripe API.
		if ( empty( $invoice ) ) {
			try {
				// Get the invoice of customer.
				$invoice = Stripe\Invoice::upcoming( array(
					'customer' => $customer_id,
				) );

				if ( ! empty( $invoice ) ) {
					// Set to cache.
					ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_upcoming_invoice_' . $customer_id, $invoice, 'psts' );
				}
			} catch ( \Exception $e ) {
				// Log error message.
				ProSites_Gateway_Stripe::error_log( $e->getMessage() );

				// Well. Failed.
				$invoice = false;
			}
		}

		return $invoice;
	}

	/**
	 * Get Stripe customer details using blog id or email.
	 *
	 * Note: Always try to use blog id instead of email.
	 * Using email will make it heavy because we need to
	 * query through all blogs of the user.
	 *
	 * @param int  $blog_id Blog ID.
	 * @param bool $email   Email of user.
	 * @param bool $force   Should we skip cache?.
	 *
	 * @since 3.6.1
	 *
	 * @return object|array
	 */
	public function get_db_customer( $blog_id = false, $email = false, $force = false ) {
		global $wpdb;

		// Get default blog id.
		$blog_id = $blog_id ? $blog_id : get_current_blog_id();

		// Initialize with default values.
		$data                  = new stdClass();
		$data->blog_id         = $blog_id;
		$data->customer_id     = false;
		$data->subscription_id = false;

		// Make sure we have a blog id or email.
		if ( ( empty( $blog_id ) || is_main_site( $blog_id ) ) && empty( $email ) ) {
			return $data;
		}

		// Get cache suffix key.
		$cache_key = empty( $blog_id ) ? $email : $blog_id;

		// If we can get from cache if not forced.
		if ( ! $force ) {
			// If something is there in cache return that.
			$data = ProSites_Helper_Cache::get_cache( 'pro_sites_stripe_db_customer_ ' . $cache_key, 'psts' );
			if ( ! empty( $data ) ) {
				return $data;
			}
		}

		// If we have blog id, try to get.
		if ( ! empty( $blog_id ) && ! is_main_site( $blog_id ) ) {
			// Table name.
			$table = ProSites_Gateway_Stripe::$table;

			// SQL query.
			$sql = $wpdb->prepare(
				"SELECT * FROM $table WHERE blog_id = %d",
				$blog_id
			);

			// Get the row.
			$customer_data = $wpdb->get_row( $sql );
		} elseif ( ! empty( $email ) ) {
			// Get customer data from email.
			$customer_data = $this->get_db_customer_by_email( $email );
		}

		// Just to make sure we don't break.
		if ( ! empty( $customer_data ) ) {
			// Set to cache.
			ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_db_customer_ ' . $cache_key, $customer_data, 'psts' );

			$data = $customer_data;
		}

		return $data;
	}

	/**
	 * Create a customer in Stripe for the blog.
	 *
	 * @param string      $email        Email address.
	 * @param string      $blog_id      Blog ID.
	 * @param string|bool $token        Stripe token.
	 * @param bool        $default_card Make it default card?.
	 * @param bool        $card         Card id.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Customer|false
	 */
	public function set_blog_customer( $email, $blog_id, $token = false, $default_card = true, &$card = false ) {
		global $psts;

		// If wed don't have a blog id, get the Stripe customer id.
		if ( empty( $blog_id ) ) {
			// Get Stripe data from db using email.
			$db_customer = $this->get_db_customer( false, $email );
		} else {
			// Get the Stripe details using blog id.
			$db_customer = $this->get_db_customer( $blog_id );
		}

		// If no customer is found, create new.
		if ( ! empty( $db_customer->customer_id ) ) {
			// Try to get the Stripe customer.
			$customer = $this->get_customer( $db_customer->customer_id );

			// If an existing customer add new card.
			if ( $customer && ! empty( $token ) ) {
				// Attache new card to customer.
				$card = ProSites_Gateway_Stripe::$stripe_charge->create_card( $token, $customer );
				// Make this card as customer's default source.
				if ( ! empty( $card->id ) && $default_card ) {
					$customer = $this->update_customer( $customer->id, array(
						'default_source' => $card->id,
					) );

					// We need only card id.
					$card = $card->id;
				}
			}
		} elseif ( ! empty( $token ) ) {
			// Try to create a Stripe customer.
			$customer = $this->create_customer( $email, $token );
		} else {
			$customer = false;
		}

		// If we could not create/update a customer, add to errors.
		if ( empty( $customer ) ) {
			$psts->errors->add( 'general', __( 'Unable to Create/Retrieve Stripe Customer.', 'psts' ) );
		}

		return $customer;
	}

	/**
	 * Get Stripe customer data using email.
	 *
	 * @param string $email Email.
	 *
	 * @since 3.6.1
	 *
	 * @return array|object|stdClass
	 */
	private function get_db_customer_by_email( $email ) {
		// Initialize the row as an empty object.
		$data = new stdClass();

		// Get user using email.
		$user = get_user_by( 'email', $email );

		// Get user object.
		if ( $user ) {
			$blogs = get_blogs_of_user( $user->ID );
			foreach ( $blogs as $blog ) {
				// Do not consider main site.
				if ( is_main_site( $blog->userblog_id ) ) {
					continue;
				}

				// Get the customer data using blog id.
				$data = $this->get_db_customer( $blog->userblog_id );
				// Return early if data found.
				if ( ! empty( $data ) ) {
					// Set to cache.
					ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_db_customer_ ' . $email, 'psts' );

					return $data;
				}
			}
		}

		return $data;
	}

	/**
	 * Get Stripe customer db data using subscription id.
	 *
	 * @param string $sub_id Subscription ID.
	 * @param bool   $force  Should forcefully get from db?.
	 *
	 * @since 3.6.1
	 *
	 * @return array|object|stdClass
	 */
	public function get_db_customer_by_subscription( $sub_id, $force = false ) {
		global $wpdb;

		$data = false;

		// Continue only if subscription id found.
		if ( ! empty( $sub_id ) ) {
			// If we can get from cache if not forced.
			if ( ! $force ) {
				// If something is there in cache return that.
				$data = ProSites_Helper_Cache::get_cache( 'pro_sites_stripe_db_customer_ ' . $sub_id, 'psts' );
				if ( ! empty( $data ) ) {
					return $data;
				}
			}

			// Table name.
			$table = ProSites_Gateway_Stripe::$table;

			// SQL query.
			$sql = $wpdb->prepare(
				"SELECT * FROM $table WHERE subscription_id = %s",
				$sub_id
			);

			// Get the row.
			$data = $wpdb->get_row( $sql );

			// Return early if data found.
			if ( ! empty( $data ) ) {
				// Set to cache.
				ProSites_Helper_Cache::set_cache( 'pro_sites_stripe_db_customer_ ' . $sub_id, $data, 'psts' );

				return $data;
			}
		}

		return $data;
	}

	/**
	 * Get Stripe customer using blog id.
	 *
	 * If customer id is set in db, we will get
	 * the customer object from API.
	 *
	 * @param int $blog_id Blog ID.
	 *
	 * @since 3.6.1
	 *
	 * @return Stripe\Customer|bool
	 */
	public function get_customer_by_blog( $blog_id ) {
		$customer = false;

		$blog_id = (int) $blog_id;

		// Do not continue if we don't have a valid blog id.
		if ( empty( $blog_id ) ) {
			return $customer;
		}

		// Get customer id of the blog.
		$customer_data = $this->get_db_customer( $blog_id );

		// Now try to get the Stripe customer.
		if ( ! empty( $customer_data->customer_id ) ) {
			$customer = $this->get_customer( $customer_data->customer_id );
		}

		return $customer;
	}

	/**
	 * Set Stripe customer id and subscription id.
	 *
	 * Note: We may use this function to update the customer
	 * id first and then later update the subscription.
	 *
	 * @param int    $blog_id         Blog ID.
	 * @param string $customer_id     Stripe customer ID.
	 * @param string $subscription_id Stripe subscription id.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	public function set_db_customer( $blog_id, $customer_id, $subscription_id = null ) {
		global $wpdb;

		$done = false;

		// If we have blog id update stripe customer id and subscription id.
		if ( ! empty( $blog_id ) ) {
			// Table name.
			$table = ProSites_Gateway_Stripe::$table;

			// If not recurring payment.
			if ( empty( $subscription_id ) ) {
				// On duplicate we will overwrite.
				$sql = $wpdb->prepare(
					"INSERT INTO $table (blog_id, customer_id) VALUES (%d, %s) ON DUPLICATE KEY UPDATE customer_id = VALUES(customer_id), subscription_id = NULL",
					$blog_id,
					$customer_id
				);
			} else {
				// On duplicate we will overwrite.
				$sql = $wpdb->prepare(
					"INSERT INTO $table (blog_id, customer_id, subscription_id) VALUES (%d, %s, %s) ON DUPLICATE KEY UPDATE customer_id = VALUES(customer_id), subscription_id = VALUES(subscription_id)",
					$blog_id,
					$customer_id,
					$subscription_id
				);
			}

			// Run the sql query.
			$done = $wpdb->query( $sql );
		}

		return ( ! empty( $done ) );
	}

	/**
	 * Transfer customer data in DB to new blog.
	 *
	 * Note: This will work only if the new blog id
	 * is not already exist in DB.
	 *
	 * @param int $new_blog_id New blog ID.
	 * @param int $old_blog_id Old blog ID.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	public function transfer_db_customer( $new_blog_id, $old_blog_id ) {
		global $wpdb;

		// Continue only if blog id's are different.
		if ( $new_blog_id !== $old_blog_id ) {
			return (bool) $wpdb->update(
				ProSites_Gateway_Stripe::$table,
				array( 'blog_id' => $new_blog_id ),
				array( 'blog_id' => $old_blog_id ),
				'%d',
				'%d'
			);
		}

		return false;
	}

	/**
	 * Delete Stripe customer id and subscription id from DB.
	 *
	 * @param int $blog_id Blog ID.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	public function delete_db_customer( $blog_id ) {
		global $wpdb;

		// If we have blog id delete the item from DB.
		if ( ! empty( $blog_id ) ) {
			// Table name.
			$table = ProSites_Gateway_Stripe::$table;

			// Run delete query.
			return $wpdb->delete( $table, array( 'blog_id' => $blog_id ), array( '%d' ) );
		}

		return false;
	}
}
