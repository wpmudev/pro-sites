<?php

if ( ! class_exists( 'ProSites_View_Front_Checkout' ) ) {
	class ProSites_View_Front_Checkout {

		public static $new_signup = false;
		public static $default_period = 'price_1';
		public static $selected_level = 0;

		public static function render_checkout_page( $content, $blog_id, $domain = false, $selected_period = 'price_1', $selected_level = false ) {
			global $psts, $current_prosite_blog, $wpdb;

			// Clear sessions if user is not logged in.
			if ( ! is_user_logged_in() ) {
				ProSites_Helper_Session::unset_session( 'new_blog_details' );
				ProSites_Helper_Session::unset_session( 'upgraded_blog_details' );
				ProSites_Helper_Session::unset_session( 'activation_key' );
			}

			// If user is not logged in, show login form and redirect.
			if ( isset( $_GET['bid'] ) && $_GET['bid'] > 0 && ! is_user_logged_in() ) {
				return self::get_login_form();
			}

			$taxamo_enabled = $psts->get_setting( 'taxamo_status', 0 );

			do_action( 'prosites_before_checkout_page', $blog_id );

			if ( $taxamo_enabled ) {
				// Prepare for location based TAX (Taxamo does its own checking client side)
				ProSites_Helper_Geolocation::init_geolocation();
			}

			// Reposition coupon based on option
			$coupons_enabled        = $psts->get_setting( 'coupons_enabled' );
			$coupons_enabled        = 'enabled' === $coupons_enabled ? true : false;
			$pt_pos                 = $psts->get_setting( 'pricing_table_coupon_position', 'option1' );

			// Are the tables enabled?
			$plans_table_enabled    = $psts->get_setting( 'plans_table_enabled', 'enabled' );
			$plans_table_enabled    = 'enabled' === $plans_table_enabled ? true : false;

			$features_table_enabled = $psts->get_setting( 'comparison_table_enabled' );
			$features_table_enabled = 'enabled' === $features_table_enabled ? true : false;

			if( 'option2' == $pt_pos  && $plans_table_enabled && $coupons_enabled ) {
				//If Coupons are enabled and set to show at the bottom OR
				//If feature column is not enabled, show coupon at the bottom
				add_filter( 'prosites_inner_pricing_table_post', array( get_class(), 'render_standalone_coupon' ) );
			}

			// Add period selector above table based on option
			if ( 'option2' == $psts->get_setting( 'pricing_table_period_position', 'option1' ) && $plans_table_enabled ) {
				add_filter( 'prosites_inner_pricing_table_pre', array( get_class(), 'render_standalone_periods' ) );
			}

			$session_data = ProSites_Helper_Session::session();
			// If its in session, get it
			if ( isset( $session_data['new_blog_details'] ) && isset( $session_data['new_blog_details']['level'] ) ) {
				$selected_period = 'price_' . ( (int) $session_data['new_blog_details']['period'] );
				$selected_level  = (int) $session_data['new_blog_details']['level'];
			}

			// User is not logged in and this is not a new registration.
			// Get them to sign up! (or login)
			if ( ! is_user_logged_in() && empty( $blog_id ) && ! isset( $session_data['new_blog_details'] ) && empty( $current_prosite_blog ) ) {
				self::$new_signup = true;
			}
			// Get blog_id from the session...
			if ( isset( $session_data['new_blog_details'] ) && isset( $session_data['new_blog_details']['blog_id'] ) ) {
				$blog_id = $session_data['new_blog_details']['blog_id'];
			}

			// Or if we're at checkout and already have a blog (1 blog only!)
			$blog_id = empty( $blog_id ) && ! empty( $current_prosite_blog ) ? $current_prosite_blog : $blog_id;

			//If no blog id, get it from pro sites table, using activation key
			if ( empty( $blog_id ) && ! empty( $session_data['activation_key'] ) ) {
				$blog_id = $wpdb->get_var( "SELECT blog_id FROM {$wpdb->base_prefix}pro_sites WHERE identifier='" . $session_data['activation_key'] . "'" );
			}

			self::$default_period = apply_filters( 'prosites_render_checkout_page_period', $selected_period, $blog_id );
			self::$selected_level = apply_filters( 'prosites_render_checkout_page_level', $selected_level, $blog_id );

			$columns = self::get_pricing_columns( true, $features_table_enabled );

			$content .= apply_filters( 'prosites_inner_pricing_table_pre', self::render_tables_wrapper( 'pre' ), $blog_id );
			if ( $plans_table_enabled ) {
				$content .= self::render_pricing_columns( $columns, $blog_id );
			} else {
				$content .= self::render_pricing_grid( $columns, $blog_id );
			}
			$content .= apply_filters( 'prosites_inner_pricing_table_post', self::render_tables_wrapper( 'post' ), $blog_id );

			$content = apply_filters( 'prosites_post_pricing_table_content', $content, $blog_id );

			if ( self::$new_signup && ! is_user_logged_in() ) {
				$content .= self::render_login();
			}
			// Signup registration
			$content .= ProSites_View_Front_Registration::render_signup_form();

			$content .= ProSites_View_Front_Gateway::render_checkout( array(), $blog_id, $domain );

			return apply_filters( 'prosites_render_checkout_page', $content, $blog_id, $domain );

			// Reset
			self::$new_signup = false;
		}

		private static function render_pricing_columns( $columns, $blog_id = false, $echo = false ) {
			global $psts;

			$content       = '';
			$periods       = (array) $psts->get_setting( 'enabled_periods' );
			$show_periods  = 2 <= count( $periods ) ? true : false;
			$total_columns = 1;

			//First Column
			$column_keys        = array_keys( $columns[0] );
			$show_pricing_table = in_array( 'title', $column_keys );
			$show_feature_table = in_array( 'sub_title', $column_keys );
			$show_buy_buttons   = in_array( 'button', $column_keys );
			$add_coupon         = in_array( 'coupon', $column_keys );

			//Show first column, if feature table is enabled or period selector position is in first column, coupon is to be shown in first column
			$show_first_column = $show_feature_table ||
			                     ( 'option2' != $psts->get_setting( 'pricing_table_period_position', 'option1' ) && $show_periods ) ||
			                     ( $add_coupon && 'option1' == $psts->get_setting( 'pricing_table_coupon_position', 'option1' ) );

			if ( $show_first_column ) {
				$total_columns = count( $columns );
			} elseif ( count( $columns ) > 1 ) {
				$total_columns = count( $columns ) - 1;
			}

			$total_width = 100.0;
			$total_width -= 6.0; // account for extra space around featured plan
			$column_width  = $total_width / $total_columns;
			$column_width  = ! empty( $column_width ) ? number_format( $column_width, 1 ) : $column_width;
			$feature_width = $column_width + 6.0;
			$normal_style  = 'width: ' . $column_width . '%; ';
			$feature_style = 'width: ' . $feature_width . '%; ';
			//$show_buy_buttons = false;

			foreach ( $columns as $key => $column ) {
				$style     = true === $column['featured'] ? $feature_style : $normal_style;
				$col_class = true === $column['featured'] ? ' featured' : '';
				$level_id  = isset( $column['level_id'] ) ? $column['level_id'] : 0;

				// Has a chosen plan been given? Note: Period should already be set.
				if ( ! empty( self::$selected_level ) && 0 != $level_id ) {
					$col_class = $level_id == (int) self::$selected_level ? $col_class . ' chosen-plan' : $col_class;
				}

				// Remove the 0 column
				$override = '';
				if ( empty( $level_id ) ) {
					$override = $show_first_column ? '' : ' hidden';
					$col_class .= ' ' . $override;
					//continue;
				}
				$content .= '<ul class="pricing-column psts-level-' . esc_attr( $level_id ) . esc_attr( $col_class ) . '" style="' . esc_attr( $style ) . '">';

				if ( $show_pricing_table ) {
					if ( empty( $column['title'] ) ) {
						//Add No summary class for feature section styling
						$no_summary = empty( $column['summary'] ) ? ' no-summary' : '';
						$content .= '<li class="title no-title' . $no_summary . '"></li>';
					} else {
						$content .= '<li class="title">' . ProSites::filter_html( $column['title'] ) . '</li>';
					}

					if ( ! empty( $column['summary'] ) ) {
						$content .= '<li class="summary' . $override . '">' . ProSites::filter_html( $column['summary'] ) . '</li>';
					} else {
						$content .= '<li class="summary no-periods"></li>';
					}
				}

				if ( $show_feature_table ) {
					$features_class = $show_pricing_table ? '' : ' no-header';
					if ( empty( $column['sub_title'] ) ) {
						$content .= '<li class="sub-title no-title' . $features_class . '"></li>';
					} else {
						$content .= '<li class="sub-title' . $features_class . '">' . ProSites::filter_html( $column['sub_title'] ) . '</li>';
					}

					$content .= '<li class="feature-section-wrapper"><ul class="feature-section">';

					foreach ( $column['features'] as $index => $feature ) {
						$alt = isset( $feature['alt'] ) && true == $feature['alt'] ? 'alternate' : '';

						$content .= '<li class="feature feature-' . $index . ' ' . $alt . '"><div class="feature-content">';

						if ( ! empty( $feature['name'] ) ) {
							$content .= '<div class="feature-name">' . ProSites::filter_html( $feature['name'] ) . '</div>';
						}
						if ( ! empty( $feature['description'] ) ) {
							$content .= '<div class="feature-description">' . ProSites::filter_html( $feature['description'] ) . '</div>';
						}
						if ( ! empty( $feature['indicator'] ) ) {
							$content .= '<div class="feature-indicator">' . ProSites::filter_html( $feature['indicator'] ) . '</div>';
						}
						if ( ! empty( $feature['text'] ) ) {
							$content .= '<div class="feature-text">' . ProSites::filter_html( $feature['text'] ) . '</div>';
						}

						$content .= '</div></li>';
					}

					$content .= '</ul></li>';

				}

				if ( $show_buy_buttons ) {
					if ( empty( $column['button'] ) ) {
						if ( $add_coupon && 'option1' == $psts->get_setting( 'pricing_table_coupon_position', 'option1' ) ) {
							$content .= '<li class="coupon">';
							$content .= '<div class="coupon-box">';
							$content .= '<input type="text" name="apply-coupon" placeholder="' . __( 'Enter coupon', 'psts' ) . '" />';
							$content .= '<a name="apply-coupon-link" class="apply-coupon-link">' . $column['coupon'] . '</a>';
							$content .= '</div>';
							$content .= '</li>';
						} else {
							$content .= '<li class="button-box no-button"></li>';
						}
					} else {
						$content .= '<li class="button-box">' . $column['button'] . '</li>';
					}
				}

				$content .= '</ul>';

			}

			$allow_free = $psts->get_setting( 'free_signup' );
			$style      = $total_columns > 1 ? 'margin-left: ' . $column_width . '%; ' : 'top: 18px; margin-bottom: 15px;';
			$style      .= $total_columns > 1 ? 'width: ' . ( 100 - $column_width ) . '%; ' : '';
			$content    = apply_filters( 'psts_checkout_before_free', $content, $blog_id, $style );
			if ( $allow_free ) {
				$content .= self::render_free( $style, $blog_id );
			}
			$content = apply_filters( 'psts_checkout_after_free', $content, $blog_id, $style );

			if ( $echo ) {
				echo $content;
			}

			return $content;
		}

		public static function get_pricing_columns( $show_header, $show_features, $show_buy_button = true ) {
			global $psts;

			$columns = array();

			$level_list = get_site_option( 'psts_levels' );

			//Filter Level List based upon visibility
			if ( ! empty( $level_list ) && is_array( $level_list ) ) {
				foreach ( $level_list as $level_key => $level ) {
					if ( empty( $level['is_visible'] ) || $level['is_visible'] == 0 ) {
						unset( $level_list[ $level_key ] );
					}
				}
			}
			$total_plans   = count( $level_list );
			$total_columns = $total_plans + 1;

			$periods      = (array) $psts->get_setting( 'enabled_periods' );
			$show_periods = true;
			if ( 2 > count( $periods ) ) {
				$total_columns = $total_columns - 1;
				$show_periods  = false;
			}

			$default_order = array();
			for ( $i = 1; $i <= $total_plans; $i ++ ) {
				$default_order[] = $i;
			}
			$default_order        = implode( ',', $default_order );
			$pricing_levels_order = $psts->get_setting( 'pricing_levels_order', $default_order );
			$pricing_levels_order = explode( ',', $pricing_levels_order );

			//Check if the associated level exists in level list, else remove it
			if ( ! empty( $pricing_levels_order ) && is_array( $pricing_levels_order ) ) {
				foreach ( $pricing_levels_order as $key => $level ) {
					if ( empty( $level_list[ $level ] ) ) {
						unset( $pricing_levels_order[ $key ] );
					}
				}
			}
			// Now add the levels that got missed at the end
			foreach ( array_keys( $level_list ) as $level ) {
				if ( ! in_array( $level, $pricing_levels_order ) ) {
					array_push( $pricing_levels_order, $level );
				}
			}

			// Initialize all columns
			for ( $i = 0; $i < $total_columns; $i ++ ) {
				$columns[] = array();
			}

			$col_count = 0;
			if ( $show_header ) {

				if ( $show_periods ) {
					$columns[ $col_count ]['title']   = '';
					$columns[ $col_count ]['summary'] = self::get_header_details();
					$col_count                        += 1;
				} else {
					if ( $show_features ) {
						$columns[ $col_count ]['title']   = '';
						$columns[ $col_count ]['summary'] = '';
						$col_count                        += 1;
					}
				}

				foreach ( $pricing_levels_order as $level ) {
					$columns[ $col_count ]             = self::get_header_details( $level );
					$columns[ $col_count ]['level_id'] = $level;
					$col_count                         += 1;
				}
			}
			$enabled_modules = $psts->get_setting( 'modules_enabled', array() );
			$feature_table   = ProSites_Model_Pricing::load_feature_settings();

			$show_table = false;
			//Iterate over feature table, and check if any of the module is set to visible
			foreach ( $feature_table as $feature => $feature_array ) {
				if ( is_array( $feature_table[ $feature ] ) && ! empty( $feature_table[ $feature ]['visible'] ) ) {
					$show_table = true;
				}
			}
			//If there are any modules to compare
			if ( $show_features && $show_table && ( count( $enabled_modules ) > 0 || ! empty( $feature_table['feature_order'] ) ) ) {
				// Set first row
				$col_count                          = 0;
				$row_count                          = 0;
				$columns[ $col_count ]['alt']       = $row_count % 2 != 0;
				$columns[ $col_count ]['sub_title'] = __( 'Compare Features', 'psts' );
				$columns[ $col_count ]['features']  = array();
				$col_count                          += 1;
				foreach ( $pricing_levels_order as $level ) {
					$columns[ $col_count ]['alt']       = $row_count % 2 != 0;
					$columns[ $col_count ]['sub_title'] = '';
					$columns[ $col_count ]['features']  = array();
					$col_count                          += 1;
				}
				$row_count += 1;

				$feature_order = $feature_table['feature_order'];
				$feature_order = explode( ',', $feature_order );
				$feature_order = array_filter( $feature_order );

				foreach ( $feature_order as $index => $feature_key ) {

					if ( empty( $feature_table[ $feature_key ]['visible'] ) ) {
						continue;
					}

					if ( isset( $feature_table[ $feature_key ]['module'] ) && ! in_array( $feature_table[ $feature_key ]['module'], $enabled_modules ) ) {
						continue;
					}

					$col_count                                           = 0;
					$columns[ $col_count ]['features'][ $index ]['name'] = $feature_table[ $feature_key ]['name'];
					$columns[ $col_count ]['features'][ $index ]['description'] = $feature_table[ $feature_key ]['description'];
					$columns[ $col_count ]['features'][ $index ]['alt']  = $row_count % 2 != 0;
					$col_count                                           += 1;

					foreach ( $pricing_levels_order as $level ) {
						$columns[ $col_count ]['features'][ $index ]['indicator'] = self::get_feature_indicator( $feature_table[ $feature_key ], $level );
						$columns[ $col_count ]['features'][ $index ]['text']      = isset( $feature_table[ $feature_key ]['levels'][ $level ] ) ? $feature_table[ $feature_key ]['levels'][ $level ]['text'] : '';
						$columns[ $col_count ]['features'][ $index ]['alt']       = $row_count % 2 != 0;
						$col_count                                                += 1;
					}

					$row_count += 1;
				}

			}

			if ( $show_buy_button ) {

				$col_count = 0;
				if ( $show_header ) {

					if ( $show_periods || $show_features ) {
						$columns[ $col_count ]['button'] = '';
						$col_count                       += 1;
					}

					foreach ( $pricing_levels_order as $level ) {
						if ( ! self::$new_signup ) {
							$columns[ $col_count ]['button'] = '<button class="choose-plan-button">' . __( 'Choose Plan', 'psts' ) . '</button>';
						} else {
							$columns[ $col_count ]['button'] = '<button class="choose-plan-button register-new">' . __( 'Sign Up', 'psts' ) . '</button>';
						}

						$col_count += 1;
					}
				}

			}

			$coupons_enabled = $psts->get_setting( 'coupons_enabled' );
			$coupons_enabled = 'enabled' === $coupons_enabled ? true : false;

			if ( $coupons_enabled ) {
				$col_count                       = 0;
				$columns[ $col_count ]['coupon'] = __( 'Apply coupon', 'psts' );
			}

			if ( 'enabled' == $psts->get_setting( 'psts_checkout_show_featured' ) ) {
				$featured_level = $psts->get_setting( 'featured_level' );
			} else {
				$featured_level = - 1;
			}

			foreach ( $columns as $key => $column ) {
				if ( empty( $featured_level ) || empty( $column['level_id'] ) || $column['level_id'] != $featured_level ) {
					$columns[ $key ]['featured'] = false;
				} else {
					$columns[ $key ]['featured'] = true;
				}
			}

			return apply_filters( 'prosites_pricing_table_columns', $columns );
		}

		private static function get_header_details( $level = false ) {
			global $psts;

			$recurring = $psts->get_setting( 'recurring_subscriptions', 1 );

			$active_periods = (array) $psts->get_setting( 'enabled_periods' );

			self::$default_period = 'price_' . ProSites_Helper_ProSite::default_period();

			$periods = array(
				'price_1'  => __( 'every month', 'psts' ),
				'price_3'  => __( 'every 3 months', 'psts' ),
				'price_12' => __( 'every 12 months', 'psts' ),
			);

			$periods_non_recurring = array(
				'price_1'  => __( 'for 1 month', 'psts' ),
				'price_3'  => __( 'for 3 months', 'psts' ),
				'price_12' => __( 'for 12 months', 'psts' ),
			);

			$payment_type = array(
				'price_1'  => __( 'Monthly', 'psts' ),
				'price_3'  => __( 'Quarterly', 'psts' ),
				'price_12' => __( 'Annually', 'psts' ),
			);

			$plan_text = apply_filters( 'prosites_pricing_labels', array(
				'payment_type' => __( 'Payment period', 'psts' ),
				'setup'        => __( 'Plus a One Time %s Setup Fee', 'psts' ),
				'summary'      => __( 'That\'s equivalent to <strong>only %s Monthly</strong>. ', 'psts' ),
				'saving'       => __( 'A saving of <strong>%s</strong> by paying for %d months in advance.', 'psts' ),
				'monthly'      => __( 'Take advantage of <strong>extra savings</strong> by paying in advance.', 'psts' ),
				'monthly_alt'  => __( '<em>Try it out!</em><br /><span>You can easily upgrade to a better value plan at any time.</span>', 'psts' )
			), $level );

			if ( empty( $level ) ) {

				if ( 'option1' != $psts->get_setting( 'pricing_table_period_position', 'option1' ) ) {
					//return '<div class="period-selector placeholder"></div>';
					return '';
				}
				$content = '<div class="period-selector"><div class="heading">' . esc_html( $plan_text['payment_type'] ) . '</div>
				<select class="chosen">';
				if ( in_array( 1, $active_periods ) ) {
					$content .= '<option value="price_1" ' . selected( self::$default_period, 'price_1', false ) . '>' . esc_html( $payment_type['price_1'] ) . '</option>';
				}
				if ( in_array( 3, $active_periods ) ) {
					$content .= '<option value="price_3" ' . selected( self::$default_period, 'price_3', false ) . '>' . esc_html( $payment_type['price_3'] ) . '</option>';
				}
				if ( in_array( 12, $active_periods ) ) {
					$content .= '<option value="price_12" ' . selected( self::$default_period, 'price_12', false ) . '>' . esc_html( $payment_type['price_12'] ) . '</option>';
				}
				$content .= '</select></div>';

				return $content;
			} else {

				$content = '';

				if ( 'enabled' == $psts->get_setting( 'psts_checkout_show_featured' ) ) {
					$featured_level = $psts->get_setting( 'featured_level' );
				} else {
					$featured_level = - 1;
				}

				$level_list       = get_site_option( 'psts_levels' );
				$setup_fee_amount = $psts->get_setting( 'setup_fee', 0 );

				$level_details = array();

				$level_details['title'] = apply_filters( 'prosites_pricing_level_title', $level_list[ $level ]['name'], $level );

				// Is this the featured level?
				if ( $featured_level == $level ) {
					$level_details['featured'] = true;
				} else {
					$level_details['featured'] = false;
				}
				$level_details['featured'] = apply_filters( 'prosites_pricing_level_featured', $level_details['featured'], $level );

				$setup_msg = '';

				$show_setup_fee = self::show_setup_fee( $setup_fee_amount );

				//If there is a setup fee and it is suppose to be displayed
				if ( $show_setup_fee ) {
					$setup_fee       = ProSites_Helper_UI::rich_currency_format( $setup_fee_amount );
					$setup_fee_plain = ProSites_Helper_UI::rich_currency_format( $setup_fee_amount, true );
					$setup_msg       = '<div class="setup-fee">' . sprintf( $plan_text['setup'], $setup_fee ) . '</div>';
					$setup_msg       .= '<div class="price-plain hidden setup-fee-plain">' . $setup_fee_plain . '</div>';
				}

				$level_details['breakdown']   = array();
				$level_details['savings_msg'] = array();
				foreach ( $periods as $period_key => $period ) {

					if ( ! in_array( (int) str_replace( 'price_', '', $period_key ), $active_periods ) ) {
						continue;
					}

					switch ( $period_key ) {
						case 'price_1':
							$months = 1;
							break;
						case 'price_3':
							$months = 3;
							break;
						case 'price_12':
							$months = 12;
							break;
					}

					$display_style = self::$default_period != $period_key ? ' hide' : '';
					$create_hidden = false;

					if ( 1 == count( $active_periods ) ) {
						$display_style = '';
						$create_hidden = (int) str_replace( 'price_', '', $period_key );
					}

					if ( ! $recurring ) {
						$period = $periods_non_recurring[ $period_key ];
					}

					// Get level price and format it
					$price          = ProSites_Helper_UI::rich_currency_format( $level_list[ $level ][ $period_key ] );
					$price_plain    = ProSites_Helper_UI::rich_currency_format( $level_list[ $level ][ $period_key ], true );
					$period_content = '<div class="price ' . esc_attr( $period_key ) . esc_attr( $display_style ) . '">';
					$period_content .= '<div class="plan-price original-amount">' . $price . '</div>';
					$period_content .= '<div class="price-plain hidden plan-' . $level . '' . $months . '-plain">' . $price_plain . '</div>';
					$period_content .= '<div class="period original-period">' . esc_html( $period ) . '</div>';
					$period_content .= ! empty( $setup_msg ) ? $setup_msg : '';
					if ( count( $active_periods ) == 1 ) {
						$period_content .= '<div class="hidden" name="single_period">' . $create_hidden . '</div>';
					}
					$period_content                            .= '</div>';
					$level_details['breakdown'][ $period_key ] = str_replace( 'hide', '', $period_content );
					$content                                   .= $period_content;

					$monthly_price = $level_list[ $level ]['price_1'];

					$monthly_calculated = $level_list[ $level ][ $period_key ] / $months * 1.0;
					$difference         = ( $monthly_price - $monthly_calculated ) * $months;

					$calculated_monthly   = ProSites_Helper_UI::rich_currency_format( $monthly_calculated );
					$calculated_saving    = ProSites_Helper_UI::rich_currency_format( $difference );
					$formatted_calculated = '<div class="monthly-price original-amount">' . $calculated_monthly . '</div>';
					$formatted_calculated .= '<div class="monthly-price-hidden hidden">' . $calculated_monthly . '</div>';
					$formatted_savings    = '<div class="savings-price original-amount">' . $calculated_saving . '</div>';
					$formatted_savings    .= '<div class="savings-price-hidden hidden">' . $calculated_saving . '</div>';

					$summary_msg = sprintf( $plan_text['monthly'] );


					$periods      = (array) $psts->get_setting( 'enabled_periods' );
					$show_periods = 2 <= count( $periods ) ? true : false;
					$override     = $show_periods ? '' : 'no-periods';

					if ( $months > 1 ) {
						$summary_msg = sprintf( $plan_text['summary'], $formatted_calculated );

						if ( $difference > 0.0 ) {
							$summary_msg .= sprintf( $plan_text['saving'], $formatted_savings, $months );
						}
						$level_details['savings_msg'][ $period_key ] = '<div class="level-summary ' . esc_attr( $period_key ) . ' ' . $override . '">' . $summary_msg . '</div>';
					} else {
						$level_details['savings_msg'][ $period_key ] = '<div class="level-summary ' . esc_attr( $period_key ) . ' ' . $override . '">' . wp_kses( $plan_text['monthly_alt'], array(
								'br'   => array(),
								'em'   => array(),
								'span' => array()
							) ) . '</div>';
					}

					$content .= '<div class="level-summary ' . esc_attr( $period_key ) . ' ' . $override . esc_attr( $display_style ) . '">' . $summary_msg . '</div>';
				}

				$level_details['summary'] = apply_filters( 'prosites_pricing_summary_text', $content, $level );

				return $level_details;
			}


		}

		private static function get_feature_indicator( $feature, $level ) {

			$status      = isset( $feature['levels'][ $level ]['status'] ) ? $feature['levels'][ $level ]['status'] : 'none';
			$easy_status = is_array( $status );
			$status      = is_array( $status ) ? $status['display'] : $status;

			$active_status = isset( $feature['active'] ) ? $feature['active'] : '';

			$status_array = array(
				'tick'  => '&#x2713',
				'cross' => '&#x2718',
			);

			// Across levels
			if ( ! empty( $active_status ) ) {

				if ( 'module' == $active_status ) {
					$module    = $feature['module'];
					$is_active = true;
					if ( method_exists( $module, 'is_active' ) ) {
						$is_active = call_user_func( $module . '::is_active' );
					}

					if ( $is_active ) {
						$status = 'tick';
					} else {
						$status = 'cross';
					}

				} else {
					$status = 'none';
				}
			}


			if ( $easy_status ) {
				// Status is given
				return '<span class="text">' . $status . '</span>';
			} else {

				// Calculate status
				switch ( $status ) {
					case 'module':
						$module = $feature['module'];
						if ( method_exists( $module, 'get_level_status' ) ) {
							$status = call_user_func( $module . '::get_level_status', $level );
						} else {
							$status = 'none';
						}
						break;
					case 'inverse':
						$module = $feature['module'];
						if ( method_exists( $module, 'get_level_status' ) ) {
							$status = call_user_func( $module . '::get_level_status', $level );
							$status = 'cross' == $status ? 'tick' : 'cross';
						} else {
							$status = 'none';
						}
						break;
				}

				switch ( $status ) {
					case 'tick':
					case 'cross':
						return '<span class="icon-' . $status . '"></span>';
						break;
					case 'none':
						return '';
						break;
				}

				return '';
			}

		}

		public static function render_tables_wrapper( $section, $echo = false ) {
			$content = '';
			$period  = str_replace( 'price_', '', self::$default_period );
			$level   = self::$selected_level;
			switch ( $section ) {

				case 'pre':
					$content .= '<div id="prosites-checkout-table" data-period="' . $period . '" data-level="' . $level . '">';
					break;

				case 'post':
					$content .= '</div>';
					break;

			}

			if ( $echo ) {
				echo $content;
			}

			return $content;
		}

		public static function render_login() {
			$content = sprintf( '<div class="login-existing">
					%s <a class="login-toggle" href="%s" title="%s">%s</a>
					<!-- Login Form -->
					%s
				</div>',
				esc_html__( 'Already have a site?', 'psts' ), // Catchphrase
				esc_url( wp_login_url( get_permalink() ) ), // Login URL
				esc_attr__( 'Login', 'psts' ), // Anchor Title
				esc_html__( 'Login now.', 'psts' ), // Anchor Text
				wp_login_form( array( 'echo' => false ) ) // Login Form
			);

			return $content;
		}

		/**
		 * Get login form html.
		 *
		 * @return string
		 */
		public static function get_login_form() {

			$is_ssl = is_ssl() ? 'https://' : 'http://';

			$content = "<p>" . esc_attr__( 'You must be logged in to upgrade this blog. Please login below.', 'psts' ) . "</p>";
			$content .= wp_login_form(
				array(
					'echo'     => false,
					'remember' => true,
					'redirect' => $is_ssl . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
				)
			);

			return $content;
		}

		public static function render_free( $style, $blog_id ) {
			global $psts;

			$session_data = ProSites_Helper_Session::session( 'new_blog_details' );

			$free_text = $psts->get_setting( 'free_msg' );
			$content   = '';
			if ( ! isset( $_GET['bid'] ) && empty( $blog_id ) && ! isset( $session_data['new_blog_details']['blogname'] ) && ProSites_Helper_ProSite::allow_new_blog() ) {
				$content = '<div class="free-plan-link" style="' . esc_attr( $style ) . '">';
				$content .= apply_filters( 'prosites_checkout_free_link', '<a>' . esc_html( $free_text ) . '</a>', $blog_id );
				$content .= '</div>';
			} else {
				if ( empty( $blog_id ) && ! empty( $_GET['bid'] ) ) {
					$blog_id = (int) $_GET['bid'];
				}
				if ( ! is_pro_site( $blog_id ) && ProSites_Helper_ProSite::allow_new_blog() ) {
					$free_link = apply_filters( 'prosites_checkout_free_link', '<a class="pblg-checkout-opt" style="width:100%" id="psts-free-option" href="' . get_admin_url( $blog_id, 'index.php?psts_dismiss=1', 'http' ) . '" title="' . __( 'Dismiss', 'psts' ) . '">' . $psts->get_setting( 'free_msg', __( 'No thank you, I will continue with a basic site for now', 'psts' ) ) . '</a>', $blog_id );
					$content   = '<div class="free-plan-link-logged-in" style="' . esc_attr( $style ) . '"><p>' . esc_html__( 'Your current site is a basic site with no extra features. Upgrade now by selecting a plan above.', 'psts' ) . '</p><p>' . $free_link . '</p></div>';
				}
			}

			return $content;
		}

		private static function render_pricing_grid( $columns, $blog_id = false, $echo = false ) {
			global $psts;

			$levels    = (array) get_site_option( 'psts_levels' );
			$periods   = (array) $psts->get_setting( 'enabled_periods' );
			$recurring = $psts->get_setting( 'recurring_subscriptions', 1 );

			//remove levels that are hidden
			foreach ( $levels as $level_id => $level ) {
				$is_visible = isset( $level['is_visible'] ) ? (bool) $level['is_visible'] : true;
				if ( $is_visible ) {
					continue;
				}
				unset( $columns[ $level_id ] );
			}

			$sel_level  = self::$selected_level;
			$sel_period = self::$default_period;

			if ( count( $periods ) >= 3 ) {
				$width      = '23%';
				$free_width = '95%';
			} else if ( count( $periods ) == 2 ) {
				$width      = '30%';
				$free_width = '92.5%';
			} else {
				$width      = '40%';
				$free_width = '85%';
			}

			$content = '';

			// TODO: Add coupon filter, apply_filters( 'psts_before_checkout_grid', $content, $blog_id );

			$content = apply_filters( 'psts_before_checkout_grid', $content, $blog_id );
			$content .= '<table id="psts_checkout_grid" width="100%">';

			if ( $recurring ) {
				$content .= '<tr class="psts_level_head">
					<th>' . __( 'Level', 'psts' ) . '</th>';
				if ( in_array( 1, $periods ) ) {
					$content .= '<th>' . __( 'Monthly', 'psts' ) . '</th>';
				}
				if ( in_array( 3, $periods ) ) {
					$content .= '<th>' . __( 'Every 3 Months', 'psts' ) . '</th>';
				}
				if ( in_array( 12, $periods ) ) {
					$content .= '<th>' . __( 'Every 12 Months', 'psts' ) . '</th>';
				}
				$content .= '</tr>';
			} else {
				$content .= '<tr class="psts_level_head">
					<th>' . __( 'Level', 'psts' ) . '</th>';
				if ( in_array( 1, $periods ) ) {
					$content .= '<th>' . __( '1 Month', 'psts' ) . '</th>';
				}
				if ( in_array( 3, $periods ) ) {
					$content .= '<th>' . __( '3 Months', 'psts' ) . '</th>';
				}
				if ( in_array( 12, $periods ) ) {
					$content .= '<th>' . __( '12 Months', 'psts' ) . '</th>';
				}
				$content .= '</tr>';
			}

			foreach ( $columns as $index => $column ) {
				$level_id = ! empty( $column['level_id'] ) ? $column['level_id'] : '';
				if ( empty( $level_id ) || 0 == $level_id ) {
					continue;
				}
				$content .= '<tr class="psts_level level-' . $level_id . '">
				<td valign="middle" class="level-name">';
				$content .= apply_filters( 'psts_checkout_grid_levelname', '<h3>' . $column['title'] . '</h3>', $level, $blog_id );
				$content .= '</td>';

				if ( in_array( 1, $periods ) ) {
					$content .= '<td class="level-option" style="width: ' . $width . '"><div class="pblg-checkout-opt">';
					$content .= $columns[ $index ]['breakdown']['price_1'];
					$content .= $columns[ $index ]['savings_msg']['price_1'];
					$content .= '</div></td>';
				}

				if ( in_array( 3, $periods ) ) {
					$content .= '<td class="level-option" style="width: ' . $width . '"><div class="pblg-checkout-opt">';
					$content .= $columns[ $index ]['breakdown']['price_3'];
					$content .= $columns[ $index ]['savings_msg']['price_3'];
					$content .= '</div></td>';
				}

				if ( in_array( 12, $periods ) ) {
					$content .= '<td class="level-option" style="width: ' . $width . '"><div class="pblg-checkout-opt">';
					$content .= $columns[ $index ]['breakdown']['price_12'];
					$content .= $columns[ $index ]['savings_msg']['price_12'];
					$content .= '</div></td>';
				}

				$content .= '</tr>';
			}

			$column_keys = ! empty( $columns ) ? array_keys( $columns[0] ) : '';
			$add_coupon  = ! empty( $column_keys ) ? in_array( 'coupon', $column_keys ) : false;
			if ( $add_coupon ) {
				$content .= '<tr>';
				$content .= '<td colspan="' . ( count( $periods ) + 1 ) . '">';
				$content .= '<div class="pricing-column grid-checkout"><div class="coupon">';
				$content .= '<div class="coupon-box">';
				$content .= '<input type="text" name="apply-coupon" placeholder="' . __( 'Enter coupon', 'psts' ) . '" />';
				$content .= '<a name="apply-coupon-link" class="apply-coupon-link">' . esc_html__( 'Apply Coupon', 'psts' ) . '</a>';
				$content .= '</div></div></div>';
				$content .= '</td>';
				$content .= '</tr>';
			}

			$content .= '</table>';

			$allow_free = $psts->get_setting( 'free_signup' );
			$content    = apply_filters( 'psts_checkout_grid_before_free', $content, $blog_id, $periods, $free_width );
			if ( $allow_free ) {
				$style   = 'width: ' . $free_width . '; ';
				$content .= self::render_free( $style, $blog_id );
			}
			$content = apply_filters( 'psts_checkout_grid_after_free', $content, $blog_id, $periods, $free_width );

			return $content;
		}

		private static function is_level_visible() {

		}

		/**
		 * Displays Coupon Box on checkout page
		 *
		 * @param $content
		 *
		 * @return string
		 */
		public static function render_standalone_coupon( $content ) {

			$content = '
			<div class="coupon-wrapper">
				<div class="coupon-box post-table">
					<span><input type="text" name="apply-coupon" placeholder="' . esc_attr__( 'Enter Coupon Code', 'psts' ) . '" /></span>
					<button name="apply-coupon-link" class="apply-coupon-link">' . esc_html__( 'Apply Coupon', 'psts' ) . '</button>
				</div>
			</div>
			' . $content;

			return $content;
		}


		public static function render_standalone_periods( $content ) {
			global $psts;

			$active_periods = (array) $psts->get_setting( 'enabled_periods' );

			$periods = array(
				'1'  => __( 'Monthly', 'psts' ),
				'3'  => __( 'Quarterly', 'psts' ),
				'12' => __( 'Annually', 'psts' )
			);

			if ( count( $active_periods ) > 1 ) {

				$content .= '<div class="period-selector-container">';

				foreach ( $active_periods as $period ) {

					$content .= '
					<label>
						<input type="radio" name="period-selector-top" value="price_' . $period . '"' . checked( self::$default_period, 'price_' . $period, false ) . ' />
						<div class="period-option period' . $period . '">' . esc_html( $periods[ $period ] ) . '</div>
					</label>
					';

				}

				$content .= '</div><div class="period-separator"></div>';
			}

			return $content;
		}


		/**
		 * Whether to display setup fee in pricing table
		 *
		 * @param $setup_fee_amount
		 *
		 * @return bool
		 */
		static function show_setup_fee( $setup_fee_amount ) {

			//If there is no setup fee
			if( empty( $setup_fee_amount ) ) {
				return false;
			}

			global $psts;
			$display_setup_fee = false;

			//Check if we need to display for upgrades or not
			$apply_setup_fee_upgrade = $psts->get_setting( 'apply_setup_fee_upgrade', false );

			//Check if blog id is set
			$blog_id = ! empty( $_GET['bid'] );

			//If user is logged in 1. Another Blog, 2. Upgrade the existing blog
			if ( is_user_logged_in() ) {
				//If there is blog id -> Upgrade Page
				if ( $blog_id && $apply_setup_fee_upgrade ) {
					$display_setup_fee = true;
				} elseif ( ! empty( $_GET['action'] ) && 'new_blog' == $_GET['action'] ) {
					//For setting up a new blog
					$display_setup_fee = true;
				}
			} elseif ( ! is_user_logged_in() ) {
				//For new users
				$display_setup_fee = true;
			}

			return $display_setup_fee;
		}

	}
}