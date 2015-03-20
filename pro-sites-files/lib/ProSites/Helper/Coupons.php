<?php

if ( ! class_exists( 'ProSites_Helper_Coupons' ) ) {

	class ProSites_Helper_Coupons {


		/**
		 * checks a coupon code for validity. Return boolean
		 *
		 * @param $code , Coupon Code
		 * @param int|bool $blog_id , Blog Id for which coupon is being used
		 * @param int|bool $level , Site Level
		 * @param int|string $period , Period
		 * @param string $domain ,Domain name for which user ois signing up, Used in case of pay before blog creation
		 * @param mixed $coupons , Provide a coupon array or it will pull from database
		 *
		 * @return bool
		 */
		public static function check_coupon( $code, $blog_id = false, $level = false, $period = '', $domain = '', $coupons = false ) {
			global $wpdb;
			$coupon_code = preg_replace( '/[^A-Z0-9_-]/', '', strtoupper( $code ) );

			//empty code
			if ( ! $coupon_code ) {
				return false;
			}

			if( ! $coupons ) {
				$coupons = (array) get_site_option( 'psts_coupons' );
			}

			/**
			 * Allow plugins to override coupon check by returning a boolean value
			 *
			 * @param string , $coupon_code
			 * @param int , $blog_id
			 * @param int , $level Pro Level
			 * @param int , $period
			 * @param string , $domain, available at the time of signup
			 *
			 */
			if ( is_bool( $override = apply_filters( 'psts_check_coupon', null, $coupon_code, $blog_id, $level, $coupons, $period, $domain ) ) ) {
				return $override;
			}

			//no record for code
			if ( ! isset( $coupons[ $coupon_code ] ) || ! is_array( $coupons[ $coupon_code ] ) ) {
				return false;
			}

			//if specific level and not proper level
			if ( $level && $coupons[ $coupon_code ]['level'] != 0 && $coupons[ $coupon_code ]['level'] != $level ) {
				return false;
			}

			//If allowed for specific period only
			$valid_for_period = $coupons[ $coupon_code ]['valid_for_period'];

			//If user has selected period limit
			if ( $period && ! empty( $valid_for_period ) &&
			     //if user has not selected Any period option
			     ! in_array( 0, $valid_for_period ) &&
			     //Current Selected period is not in specified period list
			     ! in_array( $period, $valid_for_period )
			) {
				return false;
			}

			//start date not valid yet
			if ( time() < $coupons[ $coupon_code ]['start'] ) {
				return false;
			}

			//if end date and expired
			if ( isset( $coupons[ $coupon_code ]['end'] ) && $coupons[ $coupon_code ]['end'] && time() > $coupons[ $coupon_code ]['end'] ) {
				return false;
			}

			//check remaining uses
			if ( isset( $coupons[ $coupon_code ]['uses'] ) && $coupons[ $coupon_code ]['uses'] && ( intval( $coupons[ $coupon_code ]['uses'] ) - intval( @$coupons[ $coupon_code ]['used'] ) ) <= 0 ) {
				return false;
			}

			//check if the blog has used the coupon before
			if ( ! empty( $blog_id ) ) {
				$used = get_blog_option( $blog_id, 'psts_used_coupons' );
				if ( is_array( $used ) && in_array( $coupon_code, $used ) ) {
					return false;
				}
			} else {
				//Check if domain has already used the coupon
				$signup_meta = '';
				$signup      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE domain = %s", $domain ) );
				if ( ! empty( $signup ) ) {
					$signup_meta = maybe_unserialize( $signup->meta );
				}
				$psts_used_coupons = ! empty ( $signup_meta['psts_used_coupons'] ) ? $signup_meta['psts_used_coupons'] : array();
				//If the coupon is used, return false
				if ( $psts_used_coupons && in_array( $coupon_code, $psts_used_coupons ) ) {
					return false;
				}
			}

			//everything passed so it's valid
			return true;
		}

		//get coupon value. Returns array(discount, new_total) or false for invalid code
		public static function coupon_value( $code, $total, $coupons = false ) {
			global $psts;

			if ( self::check_coupon( $code ) ) {

				if( ! $coupons ) {
					$coupons     = (array) get_site_option( 'psts_coupons' );
				}

				$coupon_code = preg_replace( '/[^A-Z0-9_-]/', '', strtoupper( $code ) );
				if ( $coupons[ $coupon_code ]['discount_type'] == 'amt' ) {
					$new_total = round( $total - $coupons[ $coupon_code ]['discount'], 2 );
					$new_total = ( $new_total < 0 ) ? 0.00 : $new_total;
					$discount  = '-' . $psts->format_currency( '', $coupons[ $coupon_code ]['discount'] );

					return array( 'discount' => $discount, 'new_total' => $new_total );
				} else {
					$new_total = round( $total - ( $total * ( $coupons[ $coupon_code ]['discount'] * 0.01 ) ), 2 );
					$new_total = ( $new_total < 0 ) ? 0.00 : $new_total;
					$discount  = '-' . $coupons[ $coupon_code ]['discount'] . '%';

					return array( 'discount' => $discount, 'new_total' => $new_total );
				}

			} else {
				return false;
			}
		}

		//record coupon use. Returns boolean successful
		public static function use_coupon( $code, $blog_id, $domain = false, $coupons = false ) {
			global $wpdb;
			if ( self::check_coupon( $code, $blog_id, '', '', $domain ) ) {

				if( ! $coupons ) {
					$coupons     = (array) get_site_option( 'psts_coupons' );
				}

				$coupon_code = preg_replace( '/[^A-Z0-9_-]/', '', strtoupper( $code ) );

				//increment count
				@$coupons[ $coupon_code ]['used'] ++;
				update_site_option( 'psts_coupons', $coupons );

				ProSites_Helper_Session::unset_session( 'COUPON_CODE' );

				if ( ! empty( $blog_id ) ) {
					//If it's a existing blog, check for previous used coupons
					$used = (array) get_blog_option( $blog_id, 'psts_used_coupons' );
				}
				//New blog won't have any previous coupons
				$used[] = $coupon_code;

				if ( ! empty( $blog_id ) ) {

					update_blog_option( $blog_id, 'psts_used_coupons', $used );

				} else {
					//Update Signup meta for used coupons
					$signup_meta = '';
					$signup      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE domain = %s", $domain ) );
					if ( ! empty( $signup ) ) {
						$signup_meta = maybe_unserialize( $signup->meta );
					}
					$signup_meta['psts_used_coupons'] = $used;
					$wpdb->update(
						$wpdb->signups,
						array(
							'meta' => serialize( $signup_meta ), // string
						),
						array(
							'domain' => $domain
						)
					);
				}

				return true;
			} else {
				return false;
			}
		}

		public static function get_adjusted_level_amounts( $code, $coupons = false ) {

			if( ! $coupons ) {
				$coupons     = (array) get_site_option( 'psts_coupons' );
			}

			$coupon_code = preg_replace( '/[^A-Z0-9_-]/', '', strtoupper( $code ) );

			$coupon = isset( $coupons[ $coupon_code ] ) ? $coupons[ $coupon_code ] : false;

			$level_list = get_site_option( 'psts_levels' );

			if( ! empty( $coupon ) ) {
				foreach ( $level_list as $level_id => $level ) {

					$period_restriction = false;
					$time_restriction   = false;
					$use_restriction    = false;

					// Is the level allowed?
					if ( $coupon['level'] == $level_id || 0 == $coupon['level'] ) {

						// Get period restrictions
						if ( ! empty( $coupon['valid_for_period'] ) && ( isset( $coupon['valid_for_period'][0] ) && ! empty( $coupon['valid_for_period'][0] ) ) ) {
							$period_restriction = $coupon['valid_for_period'];
						}

						// Is there a time restriction?
						$now = time();
						if ( ! empty( $coupon['start'] ) && $now < $coupon['start'] ) {
							$time_restriction = true;
						}
						if ( ! empty( $coupon['end'] ) && $now > $coupon['end'] ) {
							$time_restriction = true;
						}

						// Is there a use restriction?
						if ( isset( $coupon['uses'] ) && ! empty( $coupon['uses'] ) ) {
							$uses = $coupon['uses'];
							$used = 0;

							if ( isset( $coupon['used'] ) && ! empty( $coupon['used'] ) ) {
								$used = $coupon['used'];
							}

							$use_restriction = $used >= $uses;
						}

						if( $time_restriction || $use_restriction ) {
							continue;
						}

						if( ! empty( $period_restriction ) && is_array( $period_restriction ) ) {
							$discount = $coupon['discount'];
							$discount_type = strtoupper( $coupon['discount_type'] );

							foreach( $period_restriction as $period ) {
								$original = $level_list[ $level_id ][ 'price_' . $period ];
								if( 'PCT' == $discount_type ) {
									$discounted = $original - ( $original * $discount / 100 );
								} else {
									$discounted = $original - $discount;
								}
								if( 0 > $discounted ) {
									$discounted = 0.0;
								}
								$level_list[ $level_id ][ 'price_' . $period ] = $discounted * 1.0;
							}
						} else {
							$discount = $coupon['discount'];
							$discount_type = strtoupper( $coupon['discount_type'] );

							$price_keys = array( 'price_1', 'price_3', 'price_12');
							foreach( $price_keys as $price_key ) {
								$original = $level_list[ $level_id ][ $price_key ];
								if( 'PCT' == $discount_type ) {
									$discounted = $original - ( $original * $discount / 100 );
								} else {
									$discounted = $original - $discount;
								}
								if( 0 > $discounted ) {
									$discounted = 0.0;
								}
								$level_list[ $level_id ][ $price_key ] = $discounted * 1.0;
							}
						}


					}

				}
			}
			return $level_list;
		}

		public static function get_coupon( $coupon_code, $coupons = false ) {
			$coupon_code = preg_replace( '/[^A-Z0-9_-]/', '', strtoupper( $coupon_code ) );

			if( ! $coupons ) {
				$coupons = (array) get_site_option( 'psts_coupons' );
			}

			$keys = array_keys( $coupons );

			if( in_array( $coupon_code, $keys ) ) {
				return $coupons[ $coupon_code ];
			} else {
				return array();
			}
		}

		public static function apply_coupon_to_checkout() {

			$doing_ajax    = defined( 'DOING_AJAX' ) && DOING_AJAX ? true : false;
			$ajax_response = array();

			if ( $doing_ajax ) {

				$coupon_code = sanitize_text_field( $_POST['coupon_code'] );

				$valid_coupon = self::check_coupon( $coupon_code );
				if( ! empty( $valid_coupon ) ) {
					$ajax_response['valid'] = true;
					ProSites_Helper_Session::session( 'COUPON_CODE', $coupon_code );
				} else {
					$ajax_response['valid'] = false;
					ProSites_Helper_Session::unset_session( 'COUPON_CODE' );
				}

//				$ajax_response['value'] = self::coupon_value( $coupon_code, '200' );
				$first_periods = array(
					'price_1' => __('first month only', 'psts' ),
					'price_3' => __('first 3 months only', 'psts' ),
					'price_12' => __('first 12 months only', 'psts' ),
				);

				// New pricing
				if( $valid_coupon ) {
					$original_levels = get_site_option( 'psts_levels' );
					$level_list      = self::get_adjusted_level_amounts( $coupon_code );
					$coupon_obj = self::get_coupon( $coupon_code );
					foreach ( $level_list as $key => $level ) {
						unset( $level_list[ $key ]['is_visible'] );
						unset( $level_list[ $key ]['name'] );
						unset( $level_list[ $key ]['setup_fee'] );

						if ( $original_levels[ $key ]['price_1'] == $level['price_1'] ) {
							$level_list[ $key ]['price_1_adjust'] = false;
							unset( $level_list[ $key ]['price_1'] );
						} else {
							$level_list[ $key ]['price_1']        = '<div class="plan-price coupon-amount">' . ProSites_Helper_UI::rich_currency_format( $level['price_1'] ) . '</div>';
							if( 'first' == $coupon_obj['lifetime'] ) {
								$level_list[ $key ]['price_1_period'] = '<div class="period coupon-period">' . $first_periods['price_1'] . '</div>';
							} else {
								$level_list[ $key ]['price_1_period'] = '';
							}
							$level_list[ $key ]['price_1_adjust'] = true;
						}
						if ( $original_levels[ $key ]['price_3'] == $level['price_3'] ) {
							$level_list[ $key ]['price_3_adjust'] = false;
							unset( $level_list[ $key ]['price_3'] );
						} else {
							$level_list[ $key ]['price_3']        = '<div class="plan-price coupon-amount">' . ProSites_Helper_UI::rich_currency_format( $level['price_3'] ) . '</div>';
							$total_1 = $original_levels[ $key ]['price_1'] * 3;
							$total_3 = $level['price_3'];
							$monthly = $level['price_3'] / 3;
							$saving = $total_1 - $total_3;
							$level_list[ $key ]['price_3_monthly'] = '<div class="monthly-price coupon-amount">' . ProSites_Helper_UI::rich_currency_format( $monthly ) . '</div>';
							$level_list[ $key ]['price_3_savings'] = '<div class="savings-price coupon-amount">' . ProSites_Helper_UI::rich_currency_format( $saving ) . '</div>';
							if( 'first' == $coupon_obj['lifetime'] ) {
								$level_list[ $key ]['price_3_period'] = '<div class="period coupon-period">' . $first_periods['price_3'] . '</div>';
							} else {
								$level_list[ $key ]['price_3_period'] = '';
							}
							$level_list[ $key ]['price_3_adjust'] = true;
						}
						if ( $original_levels[ $key ]['price_12'] == $level['price_12'] ) {
							$level_list[ $key ]['price_12_adjust'] = false;
							unset( $level_list[ $key ]['price_12'] );
						} else {
							$level_list[ $key ]['price_12']        = '<div class="plan-price coupon-amount">' . ProSites_Helper_UI::rich_currency_format( $level['price_12'] ) . '</div>';
							$total_1 = $original_levels[ $key ]['price_1'] * 12;
							$total_12 = $level['price_12'];
							$monthly = $level['price_12'] / 12;
							$saving = $total_1 - $total_12;
							$level_list[ $key ]['price_12_monthly'] = '<div class="monthly-price coupon-amount">' . ProSites_Helper_UI::rich_currency_format( $monthly ) . '</div>';
							$level_list[ $key ]['price_12_savings'] = '<div class="savings-price coupon-amount">' . ProSites_Helper_UI::rich_currency_format( $saving ) . '</div>';
							if( 'first' == $coupon_obj['lifetime'] ) {
								$level_list[ $key ]['price_12_period'] = '<div class="period coupon-period">' . $first_periods['price_12'] . '</div>';
							} else {
								$level_list[ $key ]['price_12_period'] = '';
							}
							$level_list[ $key ]['price_12_adjust'] = true;
						}
					}
					$ajax_response['levels'] = $level_list;
				}

				$response = array(
					'what'   => 'response',
					'action' => 'apply_coupon_to_checkout',
					'id'     => 1, // success status
					'data'   => json_encode( $ajax_response ),
				);

				// Buffer used to isolate AJAX response from unexpected output
				ob_end_clean();
				ob_start();
				$xmlResponse = new WP_Ajax_Response( $response );
				$xmlResponse->send();
				ob_end_flush();
			}

		}

		public static function process_coupon_import( $filepath ) {

			$fp = fopen( $filepath, 'r' );

			$tokens = array();
			$token_keys = array();
			$added_coupons = 0;

			// Extract coupons from CSV
			$count = 0;
			while ( ( $line = fgets( $fp, 4096 ) ) !== false ) {
				$count++;

				$line = trim( $line );
				$parts = explode( ',', $line );
				if( 1 == $count ) {
					$temp = array();
					foreach( $parts as $part ) {
						$temp[] = $part;
					}
					$token_keys = $temp;
				} else {
					$temp = array();
					foreach( $parts as $index => $part ) {
						if( ! empty( $token_keys[ $index ] ) ) {
							if( 'period' == $token_keys[ $index ] ) {

								$periods = explode( '|', $part );
								$periods = is_array( $periods ) ? array_filter( $periods ) : $periods;

								if( ! is_array( $periods ) || empty( $periods ) ) {
									$periods = 0;
								}

								if( ! is_array( $periods ) ) {
									$periods = array( $periods );
								}
								$temp[ $token_keys[ $index ] ] = $periods;

							} else {
								$temp[ $token_keys[ $index ] ] = $part;
							}

						}
					}
					$tokens[] = $temp;
				}
			}
			if ( !feof( $fp ) ) {
				// something went wrong
			}
			fclose( $fp );

			// Add coupons to ProSites if they don't exist.
			$coupons = get_site_option( 'psts_coupons' );

			if( empty( $coupons ) ) {
				$coupons = array();
			}

			$existing_coupons = array_keys( $coupons );

			foreach( $tokens as $token ) {
				$coupon_code = preg_replace( '/[^A-Z0-9_-]/', '', strtoupper( $token['coupon_code'] ) );

				if( ! in_array( $coupon_code, $existing_coupons ) ) {
					$added_coupons += 1;
					$coupons[ $coupon_code ]                     = array();
					$coupons[ $coupon_code ]['lifetime']    = empty( $token['lifetime'] ) ? 'first' : $token['lifetime'];
					$coupons[ $coupon_code ]['discount']         = empty( $token['discount'] ) ? 0 : $token['discount'];
					$coupons[ $coupon_code ]['discount_type']    = empty( $token['type'] ) ? 'amt' : $token['type'];
					$coupons[ $coupon_code ]['valid_for_period'] = empty( $token['period'] ) ? null : $token['period'];
					$coupons[ $coupon_code ]['start']            = empty( $token['start_date'] ) ? false : strtotime( $token['start_date'] );
					$coupons[ $coupon_code ]['end']              = empty( $token['end_date'] ) ? false : strtotime( $token['end_date'] );
					$coupons[ $coupon_code ]['level']            = empty( $token['level'] ) ? 0 : $token['level'];
					$coupons[ $coupon_code ]['uses']             = empty( $token['uses'] ) ? 0 : $token['uses'];
				}
			}

			update_site_option( 'psts_coupons', $coupons );

			return $added_coupons;
		}


	}

}