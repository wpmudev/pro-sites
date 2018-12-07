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
	public static function get_id( $level, $period ) {

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
	public static function get_plan( $id, $force = false ) {
		// If forced, use API.
		if ( $force ) {
			// Make sure we don't break.
			try {
				$plan = Stripe\Plan::retrieve( $id );
			} catch ( \Exception $e ) {
				// Oh well.
				return false;
			}
		} else {
			// Get all plans.
			$plans = self::get_plans();

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
	 * @param int    $level  Level.
	 * @param int    $period Period.
	 * @param string $name   Plan name.
	 * @param        $price  Plan price.
	 *
	 * @since 3.6.1
	 *
	 * @return \Stripe\Plan|false Created plan object or false.
	 */
	public static function create_plan( $level, $period, $name, $price ) {
		global $psts;

		// Get our custom plan id.
		$plan_id = self::get_id( $level, $period );
		// Get current currency.
		$currency = $psts->get_setting( 'currency', 'USD' );

		// Setup the plan data.
		$plan = array(
			'id'             => $plan_id,
			'amount'         => $price,
			'currency'       => $currency,
			'interval'       => 'month',
			'interval_count' => $period,
			'nickname'       => '',
			'product'        => array(
				'name' => '',
			),
		);

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
	 * Update a plan name in Stripe.
	 *
	 * We can update only plan name. If anything else needs to be changed,
	 * delete the plan and create new one.
	 *
	 * @param string $id   Plan ID.
	 * @param string $name Plan name.
	 */
	public static function update_name( $id, $name ) {
		// Try to get the plan.
		$plan = self::get_plan( $id );

		// If plan found and name is given.
		if ( $plan && ! empty( $name ) ) {
			try {
				$plan->nickname = $name;
				$plan->save();

				// Delete cached plans.
				wp_cache_delete( 'stripe_plans_cached', 'psts' );
			} catch ( Exception $e ) {
				// Oh well.
			}
		}
	}

	/**
	 * Delete a plan from Stripe using API.
	 *
	 * @param string $id Stripe plan ID.
	 *
	 * @since 3.6.1
	 *
	 * @return bool
	 */
	public static function delete_plan( $id ) {

		// Make sure we don't break.
		try {
			// First get the plan.
			$plan = self::get( $id );
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
		if ( $deleted && ! empty( $plan->product ) ) {
			self::delete_product( $plan->product );
		}

		return $deleted;
	}

	/**
	 * Retrieve a plan from Stripe API.
	 *
	 * @param bool $force Should get from API forcefully.
	 *
	 * @since 3.6.1
	 *
	 * @return array Array of Stripe plan objects.
	 */
	public static function get_plans( $force = false ) {
		// Plans array.
		$plans = array();

		// If not forced, try to get from cache.
		if ( ! $force ) {
			$plans = wp_cache_get( 'stripe_plans_cached', 'psts' );
		}

		// If nothing in cache, get from Stripe.
		if ( empty( $plans ) ) {
			// Make sure we don't break.
			try {
				// Get 
				$plans = Stripe\Plan::all();
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
	public static function get_product( $product_id, $force = false ) {
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
	public static function delete_product( $product_id ) {
		// Try to get the product.
		$product = self::get_product( $product_id );

		// If not forced, or nothing in cache, get from Stripe API.
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
}
