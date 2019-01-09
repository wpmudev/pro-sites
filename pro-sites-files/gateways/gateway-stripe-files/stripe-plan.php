<?php

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

/**
 * The stripe plans functionality of the plugin.
 *
 * @link   https://premium.wpmudev.org/project/pro-sites
 * @since  3.6.1
 *
 * @author Joel James <joel@incsub.com>
 */
class ProSites_Stripe_Plan {

	/**
	 * Get a plan id based upon a given level and period.
	 *
	 * @note  Making changes to this may break existing plans.
	 *
	 * @param int $level  Level ID.
	 * @param int $period Period ID.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public function get_id( $level, $period ) {

		// A unique id using site url.
		$uid = str_replace(
			array( 'http://', 'https://', '/', '.' ),
			array( '', '', '', '_' ),
			network_home_url()
		);

		// Joing site url, level and period.
		$plan_id = $uid . '_' . $level . '_' . $period;

		/**
		 * Make plan ID filterable.
		 *
		 * @param string $plan_id Plan ID.
		 *
		 * @since 3.6.1
		 */
		return apply_filters( 'pro_sites_stripe_plan_id', $plan_id );
	}

	/**
	 * Retrieve a plan from Stripe API.
	 *
	 * We will try to get it from cache if already retrieved.
	 * Making API calls everytime is not a good idea.
	 *
	 * @param string $id    Stripe plan ID.
	 * @param bool   $force Should get from API forcefully.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Plan|false
	 */
	public function get_plan( $id, $force = false ) {
		// If forced, use API.
		if ( $force ) {
			// Make sure we don't break.
			try {
				$plan = Stripe\Plan::retrieve( $id );
				// If a plan found, return.
				if ( ! empty( $plan ) ) {
					return $plan;
				}
			} catch ( \Exception $e ) {
				// Oh well.
				return false;
			}
		} else {
			// Get all plans.
			$plans = $this->get_plans();

			// If no plans found, bail.
			if ( empty( $plans ) ) {
				return false;
			}

			// Loop through each plans and check if a plan exist.
			foreach ( $plans as $plan ) {
				if ( ! empty( $plan->id ) && $plan->id === $id ) {
					return $plan;
				}
			}
		}

		return false;
	}

	/**
	 * Add a new plan to Stripe via API.
	 *
	 * @param int    $level        Level.
	 * @param int    $period       Period.
	 * @param string $name         Plan name.
	 * @param int    $price        Plan price.
	 * @param string $product_name Product name.
	 * @param string $product_id   Product ID.
	 * @param string $interval     Interval type (day, month, year).
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Plan|false Created plan object or false.
	 */
	public function create_plan( $level, $period, $name, $price, $product_name, $product_id = '', $interval = 'month' ) {
		global $psts;

		// Get our custom plan id.
		$plan_id = $this->get_id( $level, $period );
		// Get current currency.
		$currency = $psts->get_setting( 'currency', 'USD' );

		// Setup the plan data.
		$plan = array(
			'id'             => $plan_id,
			'amount'         => ProSites_Gateway_Stripe::format_price( $price ),
			'currency'       => $currency,
			'interval'       => $interval,
			'interval_count' => $period,
			'nickname'       => $name,
		);

		// If product is found, assign that.
		if ( ! empty( $product_id ) ) {
			$plan['product'] = $product_id;
		} else {
			$plan['product']['name'] = $product_name;
		}

		// Make sure we don't break anything.
		try {
			// Call the API and create the plan.
			$plan = Stripe\Plan::create( $plan );
		} catch ( \Exception $e ) {
			$plan = false;
		}

		return $plan;
	}

	/**
	 * Update a plan in Stripe.
	 *
	 * We can update only plan name and product. If anything else
	 * needs to be changed, delete the plan and create new one.
	 *
	 * @param string $id   Plan ID.
	 * @param array  $args Plan fields and values.
	 *
	 * @return bool|\Stripe\Plan
	 */
	public function update_plan( $id, $args = array() ) {
		// Try to get the plan.
		$plan = $this->get_plan( $id );

		/**
		 * Filter to allow more fields to update.
		 *
		 * @param array
		 *
		 * @since 3.6.1
		 */
		$allowed_fields = apply_filters( 'prosites_stripe_plan_allowed_update_fields', array(
			'nickname',
			'product',
		) );

		// If plan found and name is given.
		if ( $plan && ! empty( $args ) ) {
			try {
				foreach ( $args as $key => $value ) {
					// Only update allowed fields.
					if ( in_array( $key, $allowed_fields, true ) ) {
						$plan->{$key} = $value;
					}
				}
				// Save plan.
				$plan = $plan->save();

				// Delete cached plans.
				wp_cache_delete( 'stripe_plans_cached', 'psts' );
			} catch ( \Exception $e ) {
				// Oh well.
				$plan = false;
			}
		}

		return $plan;
	}

	/**
	 * Delete a plan from Stripe using API.
	 *
	 * @param string $id             Stripe plan ID.
	 * @param bool   $delete_product Should delete the parent product?.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	public function delete_plan( $id, $delete_product = false ) {
		$deleted = false;
		// Make sure we don't break.
		try {
			// First get the plan.
			$plan = $this->get_plan( $id );
			// If plan is found, attempt to delete.
			if ( ! empty( $plan ) ) {
				$deleted = $plan->delete();
				// Delete cached plans.
				wp_cache_delete( 'stripe_plans_cached', 'psts' );
			}
		} catch ( \Exception $e ) {
			// Oh well.
			$deleted = false;
		}

		// Delete the plan product also.
		if ( $deleted && ! empty( $plan ) && ! empty( $plan->product ) && $delete_product ) {
			$this->delete_product( $plan->product );
		}

		return $deleted;
	}

	/**
	 * Retrieve a plan from Stripe API.
	 *
	 * @param int  $limit No. of items to get.
	 * @param bool $force Should get from API forcefully.
	 *
	 * @since 3.6.1
	 *
	 * @return array Array of Stripe plan objects.
	 */
	public function get_plans( $limit = 100, $force = false ) {
		// Plans array.
		$plans = array();

		/**
		 * Filter to change plans count.
		 *
		 * @param int $limit No. of items.
		 *
		 * @since 3.6.1
		 */
		$limit = (int) apply_filters( 'pro_sites_stripe_get_plans_limit', $limit );

		// If not forced, try to get from cache.
		if ( ! $force ) {
			$plans = wp_cache_get( 'stripe_plans_cached', 'psts' );
		}

		// If nothing in cache, get from Stripe.
		if ( empty( $plans ) ) {
			// Make sure we don't break.
			try {
				// Get all plans.
				$plans = Stripe\Plan::all( array(
					'limit' => $limit,
				) );
				if ( ! empty( $plans->data ) ) {
					$plans = $plans->data;
				}
			} catch ( \Exception $e ) {
				// Oh well.
			}

			// Add to cache.
			if ( ! empty( $plans ) ) {
				wp_cache_set( 'stripe_plans_cached', $plans, 'psts' );
			}
		}

		return $plans;
	}

	/**
	 * Retrieve a plan product from Stripe API.
	 *
	 * We will try to get it from cache if already retrieved.
	 * Making API calls everytime is not a good idea.
	 *
	 * @param string $product_id Stripe product ID.
	 * @param bool   $force      Should get from API forcefully.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Product|false
	 */
	public function get_product( $product_id, $force = false ) {
		$product = false;
		// If not forced, try to get from cache.
		if ( ! $force ) {
			$product = wp_cache_get( 'stripe_product_cached_' . $product_id, 'psts' );
		}

		// If not forced, or nothing in cache, get from Stripe API.
		if ( $force || empty( $product ) ) {
			// Make sure we don't break.
			try {
				$product = Stripe\Product::retrieve( $product_id );
			} catch ( \Exception $e ) {
				// Oh well.
				$product = false;
			}

			// Set to cache.
			if ( ! empty( $product ) ) {
				wp_cache_set( 'stripe_product_cached_' . $product_id, $product, 'psts' );
			}
		}

		return $product;
	}

	/**
	 * Update plan product name using Stripe API.
	 *
	 * @param string $product_id Stripe product ID.
	 * @param string $name       Product name.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|\Stripe\Product
	 */
	public function update_product_name( $product_id, $name = '' ) {
		// Try to get the product.
		$product = $this->get_product( $product_id );

		// If product found and name is given.
		if ( $product && ! empty( $name ) ) {
			try {
				$product->name = $name;
				$product       = $product->save();

				// Update the cached product.
				wp_cache_set( 'stripe_product_cached_' . $product_id, $product, 'psts' );
			} catch ( \Exception $e ) {
				// Oh well.
				$product = false;
			}
		}

		return $product;
	}

	/**
	 * Delete a plan product from Stripe API.
	 *
	 * We need to delete the plan product when we delete
	 * a plan.
	 *
	 * @param string $product_id Stripe product ID.
	 *
	 * @since 3.6.1
	 *
	 * @return void
	 */
	public function delete_product( $product_id ) {
		// Try to get the product.
		$product = $this->get_product( $product_id );

		// Delete if Product found.
		if ( ! empty( $product ) ) {
			// Make sure we don't break.
			try {
				$product->delete();
			} catch ( \Exception $e ) {
				// Oh well.
			}

			// Clear the cache.
			wp_cache_delete( 'stripe_product_cached_' . $product_id, 'psts' );
		}
	}

	/**
	 * Sync Pro Sites levels with Stripe plans.
	 *
	 * If levels changed, we need to make this happen in Stripe
	 * also. All the existing subscriptions will remain same unless
	 * it is updated.
	 *
	 * @todo   Handle deleted plans.
	 *
	 * @param array $levels     New levels.
	 * @param array $old_levels Old levels.
	 *
	 * @since  3.6.1
	 * @access private
	 *
	 * @return void
	 */
	public function sync_levels( $levels = array(), $old_levels = array() ) {
		// We need some plans, you know?.
		if ( empty( $levels ) || ! is_array( $levels ) ) {
			return;
		}

		// Go through each levels.
		foreach ( $levels as $level_id => $level ) {
			// Get plans prepared from levels.
			$plans = $this->prepare_plan_data( $level );

			// Update all plans where ever required.
			$this->update_plans( $plans, $level_id, $level );
		}

		// We need to clear the cache.
		wp_cache_delete( 'stripe_plans_cached', 'psts' );
	}

	/**
	 * Prepare plans for a level.
	 *
	 * @param array $level Level data.
	 *
	 * @since 3.6.1
	 *
	 * @return array
	 */
	private function prepare_plan_data( $level ) {
		$plans = array(
			1  => array(
				'desc'  => 'Monthly',
				'price' => isset( $level['price_1'] ) ? $level['price_1'] : 0,
			),
			3  => array(
				'desc'  => 'Quarterly',
				'price' => isset( $level['price_3'] ) ? $level['price_3'] : 0,
			),
			12 => array(
				'desc'  => 'Yearly',
				'price' => isset( $level['price_12'] ) ? $level['price_12'] : 0,
			),
		);

		return $plans;
	}

	/**
	 * Update each plans against each levels.
	 *
	 * If price changed, we need to delete the old plan
	 * and then create new one in Stripe. Existing subscriptions
	 * will still work.
	 * Level names are products and periods are plans.
	 * We will handle the old plans upgrade also here.
	 *
	 * @param array  $plans    Plan data.
	 * @param int    $level_id Level number.
	 * @param string $level    Level data.
	 *
	 * @since 3.6.1
	 *
	 * @return void
	 */
	private function update_plans( $plans, $level_id, $level ) {
		// Initial values.
		$product_id = $product = $create_new = false;

		// We need some plans.
		if ( empty( $plans ) ) {
			return;
		}

		// Get the currency.
		$currency = ProSites_Gateway_Stripe::get_currency();

		// Loop through each plans.
		foreach ( $plans as $period => $plan ) {
			// Get the custom plan id.
			$plan_id = $this->get_id( $level_id, $period );

			// Product name is our level name.
			$product_name = $level['name'];

			// Try to get the Stripe plan.
			$stripe_plan = $this->get_plan( $plan_id );

			// If we have product already.
			if ( empty( $product_id ) && ! empty( $stripe_plan->product ) ) {
				$product_id = $stripe_plan->product;
				$product    = $this->get_product( $product_id );
			}

			// Plan price.
			$plan_price = ProSites_Gateway_Stripe::format_price( $plan['price'] );

			// If Stripe plan not found.
			if ( ! empty( $stripe_plan ) ) {
				// Update the product name if required.
				if ( ! empty( $product->name ) && $product_name !== $product->name ) {
					$product = $this->update_product_name( $product_id, $product_name );
				}

				// Ok, now everything seems ok. Nothing changed.
				if ( (int) $stripe_plan->amount === (int) $plan_price &&
				     strtolower( $stripe_plan->currency ) === strtolower( $currency ) &&
				     ( empty( $stripe_plan->product ) || ( $stripe_plan->product === $product_id ) ) &&
				     $stripe_plan->nickname === $plan['desc']
				) {
					continue;
				}

				/**
				 * If products are different, use one for same level.
				 * Upgrading to new plan structure will be handled here because
				 * we will use same product for all plans in a level.
				 */
				if ( ! empty( $stripe_plan->product ) && $product_id !== $stripe_plan->product ) {
					$this->update_plan( $plan_id, array(
						'product' => $product_id,
					) );
				}

				// Price change or currency change happened
				// We need to delete old plan and create new one.
				if ( (int) $stripe_plan->amount !== (int) $plan_price ||
				     // Currency changed?.
				     strtolower( $stripe_plan->currency ) !== strtolower( $currency )
				) {
					// Delete the plan.
					$this->delete_plan( $plan_id );
					$create_new = true;
				}

				/**
				 * You can not update name when switching the product of a plan.
				 * So we need to update the name of the plan separately.
				 */
				if ( $plan['desc'] !== $stripe_plan->nickname && ! $create_new ) {
					// Update the plan name.
					$this->update_plan( $plan_id, array(
						'nickname' => $plan['desc'],
					) );
				}
			} else {
				$create_new = true;
			}

			// We need to create new plan.
			if ( $create_new ) {
				// Create new plan.
				$created_plan = $this->create_plan( $level_id, $period, $plan['desc'], $plan['price'], $product_name, $product_id );

				// If we have created product already, use that for all other plans of same level.
				if ( ! empty( $created_plan->product ) ) {
					$product_id = $created_plan->product;
				}
			}
		}
	}

	/**
	 * Create a new coupon in Stripe.
	 *
	 * @param array       $args Coupon arguments.
	 * @param string|bool $id   Coupon ID.
	 *
	 * @since 3.6.1
	 *
	 * @return bool|\Stripe\Coupon
	 */
	public function create_coupon( $args, $id = false ) {
		// Make sure we don't break.
		try {
			// Set coupon id.
			if ( ! empty( $id ) ) {
				$args['id'] = $id;
			}
			// Let's create a coupon now.
			$coupon = Stripe\Coupon::create( $args );
			// Set to cache so we can reuse it.
			if ( ! empty( $coupon ) ) {
				wp_cache_set( 'pro_sites_stripe_coupon_' . $id, $coupon, 'psts' );
			}
		} catch ( \Exception $e ) {
			// Oh well.
			$coupon = false;
		}

		return $coupon;
	}

	/**
	 * Retrieve a coupon from Stripe.
	 *
	 * @param string $id    Coupon ID.
	 * @param bool   $force Should force from API?.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Coupon|bool|mixed
	 */
	public function get_coupon( $id, $force = false ) {
		// If not forced, try cache.
		if ( ! $force ) {
			// Try to get from cache.
			$coupon = wp_cache_get( 'pro_sites_stripe_coupon_' . $id, 'psts' );
			if ( ! empty( $coupon ) ) {
				return $coupon;
			}
		} else {
			// Make sure we don't break.
			try {
				$coupon = Stripe\Coupon::retrieve( $id );
				// If a coupon found, return.
				if ( ! empty( $coupon ) ) {
					// Set to cache so we can reuse it.
					wp_cache_set( 'pro_sites_stripe_coupon_' . $id, $coupon, 'psts' );
				}

				return $coupon;
			} catch ( \Exception $e ) {
				// Oh well.
				return false;
			}
		}

		return false;
	}
}
