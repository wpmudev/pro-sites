<?php

if ( ! class_exists( 'ProSites_View_Front_Checkout' ) ) {
	class ProSites_View_Front_Checkout {

		public static function render_checkout_page( $content, $blog_id, $domain = false ) {
			global $psts;

			// Are the tables enabled?
			$plans_table_enabled = $psts->get_setting('plans_table_enabled');
			$plans_table_enabled = 'enabled' === $plans_table_enabled ? true : false;
			$features_table_enabled = $psts->get_setting( 'comparison_table_enabled' );
			$features_table_enabled = 'enabled' === $features_table_enabled ? true : false;

			$columns = self::get_pricing_columns( $plans_table_enabled, $features_table_enabled );

			$content = self::render_tables_wrapper( 'pre' );
			$content .= self::render_pricing_columns( $columns );
			$content .= self::render_tables_wrapper( 'post' );

			// Hook for the gateways
			$content = apply_filters( 'psts_checkout_output', $content, $blog_id, $domain );

			return $content;

		}

		private static function render_pricing_columns( $columns, $echo = false ) {
			ob_start();

			$total_columns = count( $columns );
			$total_width = 100.0;
			$total_width -= 6.0; // account for extra space around featured plan
			$column_width = $total_width / $total_columns;
			$feature_width = $column_width + 6.0;
			$normal_style = 'width: ' . $column_width . '%; ';
			$feature_style = 'width: ' . $feature_width . '%; ';

			$column_keys = array_keys( $columns );
			$show_pricing_table = in_array( 'title', $column_keys );
			$show_feature_table = in_array( 'sub_title', $column_keys );
			$show_buy_buttons = in_array( 'button', $column_keys );
//			$show_buy_buttons = false;

			foreach( $columns as $key => $column ) {
				$style = true === $column['featured'] ? $feature_style : $normal_style;
				$col_class = true === $column['featured'] ? ' featured' : '';
				?><ul class="pricing-column <?php echo esc_attr( $col_class ); ?>" style="<?php echo esc_attr( $style ); ?>"><?php

				if( $show_pricing_table ) {
					if( empty( $column['title'] ) ) {
						echo '<li class="title no-title"></li>';
					} else {
						echo '<li class="title">' . ProSites::filter_html( $column['title'] ) . '</li>';
					}

					echo '<li class="summary">' . ProSites::filter_html( $column['summary'] ) . '</li>';
				}

				if( $show_feature_table ) {
					$features_class = $show_pricing_table ? '' : 'no-header';
					if( empty( $column['sub_title'] ) ) {
						echo '<li class="sub-title no-title ' . $features_class . '"></li>';
					} else {
						echo '<li class="sub-title ' . $features_class . '">' . ProSites::filter_html( $column['sub_title'] ) . '</li>';
					}

					?><li><ul class="feature-section"><?php

						foreach( $column['features'] as $index => $feature ) {
							$alt = isset( $feature['alt'] ) && true == $feature['alt'] ? 'alternate' : '';

							?><li class="feature feature-<?php echo $index; ?> <?php echo $alt; ?>"><?php
							if( isset( $feature['name'] ) && ! empty( $feature['name'] ) ) {
								echo '<div class="feature-name">' . ProSites::filter_html( $feature['name'] ) . '</div>';
							}
							if( isset( $feature['indicator'] ) && ! empty( $feature['indicator'] ) ) {
								echo '<div class="feature-indicator">' . ProSites::filter_html( $feature['indicator'] ) . '</div>';
							}
							if( isset( $feature['text'] ) && ! empty( $feature['text'] ) ) {
								echo '<div class="feature-text">' . ProSites::filter_html( $feature['text'] ) . '</div>';
							}

							?></li><?php
						}

					?></ul></li><?php
				}

				if( $show_buy_buttons ) {
					if( empty( $column['button'] ) ) {
						echo '<li class="button-box no-button"></li>';
					} else {
						echo '<li class="button-box">' . $column['button'] . '</li>';
					}
				}



				?></ul><?php
			}

			$content = ob_get_clean();

			if( $echo ) {
				echo $content;
			}

			return $content;
		}

		public static function get_pricing_columns( $show_header, $show_features, $show_buy_button = true ) {
			global $psts;

			$columns = array();

			$level_list = get_site_option( 'psts_levels' );
			$total_plans = count( $level_list );

			$default_order = array();
			for( $i = 1; $i <= $total_plans; $i++ ) {
				$default_order[] = $i;
			}
			$default_order = implode( ',', $default_order );
			$pricing_levels_order = $psts->get_setting( 'pricing_levels_order', $default_order );
			$pricing_levels_order = explode( ',', $pricing_levels_order );

			$featured_level = $psts->get_setting( 'featured_level' );

			// Initialize all columns
			for( $i = 0; $i <= $total_plans; $i++ ) {
				$columns[] = array();
			}

			$col_count = 0;
			if( $show_header ) {
				$columns[ $col_count ]['title'] = '';
				$columns[ $col_count ]['summary'] = self::get_header_details();
				$columns[ $col_count ]['featured'] = false;
				$col_count += 1;

				foreach( $pricing_levels_order as $level ) {
					$columns[ $col_count ] = self::get_header_details( $level );
					$col_count += 1;
				}
			}

			if( $show_features ) {

				// Set first row
				$col_count = 0;
				$row_count = 0;
				$columns[ $col_count ]['alt'] = $row_count %2 != 0;
				$columns[ $col_count ]['sub_title'] = __( 'Compare Features', 'psts' );
				$columns[ $col_count ]['features'] = array();
				$col_count += 1;
				foreach( $pricing_levels_order as $level ) {
					$columns[ $col_count ]['alt'] = $row_count %2 != 0;
					$columns[ $col_count ]['sub_title'] = '';
					$columns[ $col_count ]['features'] = array();
					$col_count += 1;
				}
				$row_count += 1;

				$feature_table = ProSites_Model_Pricing::load_feature_settings();
				$feature_order = $feature_table['feature_order'];
				$feature_order = explode( ',', $feature_order );
				$feature_order = array_filter( $feature_order );
				$enabled_modules = $psts->get_setting( 'modules_enabled', array() );

				foreach( $feature_order as $index => $feature_key ) {

					if( empty( $feature_table[ $feature_key ]['visible'] ) ) {
						continue;
					}

					if( isset( $feature_table[ $feature_key ]['module'] ) && ! in_array( $feature_table[ $feature_key ]['module'], $enabled_modules ) )  {
						continue;
					}

					$col_count = 0;
					$columns[ $col_count ]['features'][ $index ]['name'] = $feature_table[ $feature_key ]['description'];
					$columns[ $col_count ]['features'][ $index ]['alt'] = $row_count %2 != 0;
					$col_count += 1;

					foreach( $pricing_levels_order as $level ) {
						$columns[ $col_count ]['features'][ $index ]['indicator'] = self::get_feature_indicator( $feature_table[ $feature_key ], $level );
						$columns[ $col_count ]['features'][ $index ]['text'] = $feature_table[ $feature_key ]['levels'][ $level ]['text'];
						$columns[ $col_count ]['features'][ $index ]['alt'] = $row_count %2 != 0;
						$col_count += 1;
					}

					$row_count += 1;
				}

			}

			if( $show_buy_button ) {

				$col_count = 0;
				if( $show_header ) {
					$columns[ $col_count ]['button'] = '';
					$col_count += 1;

					foreach( $pricing_levels_order as $level ) {
						$columns[ $col_count ]['button'] = '<button>' . __( 'Choose Plan', 'psts' ) . '</button>';
						$col_count += 1;
					}
				}

			}

			return $columns;
		}

		private static function get_header_details( $level = false ) {

			$periods = array(
				'price_1' => __('per 1 month', 'psts' ),
				'price_3' => __('per 3 months', 'psts' ),
				'price_12' => __('per 12 months', 'psts' ),
			);

			$payment_type = array(
				'price_1' => __('Monthly', 'psts' ),
				'price_3' => __('Quarterly', 'psts' ),
				'price_12' => __('Annually', 'psts' ),
			);

			$plan_text = array(
				'payment_type' => __( 'Payment period', 'psts' ),
				'setup' => __( 'Plus a One Time %s Setup Fee', 'psts' ),
				'summary' => __( 'That\'s equivalent to <strong>only %s Monthly</strong>', 'psts' ),
				'saving' => __( '<br />saving you <strong>%s</strong> by paying for %d months in advanced.', 'psts' ),
				'monthly' => __( 'Take advantage of <strong>extra savings</strong> by paying in advance.', 'psts' ),
			);

			if( empty( $level ) ) {
				ob_start();

				?><div class="period-selector"><div class="heading"><?php echo esc_html( $plan_text['payment_type'] ); ?></div>
				<select class="chosen">
					<option value="price_1"><?php echo esc_html( $payment_type['price_1'] ); ?></option>
					<option value="price_3"><?php echo esc_html( $payment_type['price_3'] ); ?></option>
					<option value="price_12"><?php echo esc_html( $payment_type['price_12'] ); ?></option>
				</select></div><?php

				return ob_get_clean();
			} else {
				global $psts;
				$featured_level = $psts->get_setting( 'featured_level' );
				$level_list = get_site_option( 'psts_levels' );
				$setup_fee_amount = $psts->get_setting( 'setup_fee', 0 );

				$level_details = array();

				$level_details['title'] = $level_list[ $level ]['name'];

				// Is this the featured level?
				if( $featured_level == $level ) {
					$level_details['featured'] = true;
				} else {
					$level_details['featured'] = false;
				}

				ob_start();

				$setup_fee = ProSites_Helper_UI::rich_currency_format( $setup_fee_amount );
				$setup_msg = '';
				if( ! empty( $setup_fee_amount ) ) {
					$setup_msg = '<div class="setup-fee">' . sprintf( $plan_text['setup'], $setup_fee ) . '</div>';
				}

				foreach( $periods as $period_key => $period ) {

					switch( $period_key ) {
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

					$display_style = 'price_1' != $period_key ? ' hide' : '';

					// Get level price and format it
					$price = ProSites_Helper_UI::rich_currency_format( $level_list[ $level ][ $period_key ] );
					echo '<div class="price ' . esc_attr( $period_key ) . esc_attr( $display_style ) . '">';
					echo $price;
					echo '<div class="period">' . esc_html( $period ) . '</div>';
					echo ! empty( $setup_msg ) ? $setup_msg : '';
					echo '</div>';

					$monthly_price = $level_list[ $level ]['price_1'];

					$monthly_calculated = $level_list[ $level ][ $period_key ] / $months * 1.0;
					$difference = ( $monthly_price - $monthly_calculated ) * $months;

					$formatted_calculated = ProSites_Helper_UI::rich_currency_format( $monthly_calculated );
					$formatted_savings = ProSites_Helper_UI::rich_currency_format( $difference );

					$summary_msg = $plan_text['monthly'];
					if( $months > 1 ) {
						$summary_msg = sprintf( $plan_text['summary'], $formatted_calculated );
						if( $difference > 0.0 ) {
							$summary_msg .= sprintf( $plan_text['saving'], $formatted_savings, $months );
						}

					}

					?><div class="level-summary <?php echo esc_attr( $period_key ) . esc_attr( $display_style ); ?>">
						<?php echo $summary_msg; ?>
					</div><?php
				}

				$content = ob_get_clean();

				$level_details['summary'] = $content;

				return $level_details;
			}


		}

		private static function get_feature_indicator( $feature, $level ) {

			$status = isset( $feature['levels'][ $level ]['status'] ) ? $feature['levels'][ $level ]['status'] : 'none';
			$easy_status = is_array( $status );
			$status = is_array( $status ) ? $status['display'] : $status;

			$active_status = isset( $feature['active'] ) ? $feature['active'] : '';

			$status_array = array(
				'tick' => '&#x2713',
				'cross' => '&#x2718',
			);

			// Across levels
			if( ! empty( $active_status ) ) {

				if( 'module' == $active_status ) {
					$module    = $feature['module'];
					$is_active = true;
					if ( method_exists( $module, 'is_active' ) ) {
						$is_active = call_user_func( $module . '::is_active' );
					}

					if( $is_active ) {
						$status = 'tick';
					} else {
						$status = 'cross';
					}

				} else {
					$status = 'none';
				}
			}


			if( $easy_status ) {
				// Status is given
				return '<span class="text">' .$status . '</span>';
			} else {

				// Calculate status
				switch( $status ) {
					case 'module':
						$module = $feature['module'];
						if( method_exists( $module, 'get_level_status' ) ) {
							$status = call_user_func( $module . '::get_level_status', $level );
						} else {
							$status = 'none';
						}
						break;
					case 'inverse':
						$module = $feature['module'];
						if( method_exists( $module, 'get_level_status' ) ) {
							$status = call_user_func( $module . '::get_level_status', $level );
							$status = 'cross' == $status ? 'tick' : 'cross';
						} else {
							$status = 'none';
						}
						break;
				}

				switch( $status ) {
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













		public static function render_tables_wrapper ( $section,  $echo = false ) {
			ob_start();

			switch( $section ) {

				case 'pre':
					echo '<div id="prosites-checkout-table">';
					break;

				case 'post':
					echo '</div>';
					break;

			}

			$content = ob_get_clean();

			if( $echo ) {
				echo $content;
			}

			return $content;
		}






	}
}