<?php

class ProSites_Pricing_Table {
	private $blog_id;
	private $sel_period;
	private $sel_level;
	private $periods;
	private $include_pricing = false;

	private static $instance;

	private function __construct( $args ) {
		$this->blog_id = $args ['blog_id'] ? $args ['blog_id'] : null;
		$this->load_periods();
		$this->get_selected();
	}

	private function get_levels() {
		$levels = (array) get_site_option( 'psts_levels' );
		if ( ! ( defined( 'PSTS_DONT_REVERSE_LEVELS' ) && PSTS_DONT_REVERSE_LEVELS ) ) {
			$levels = array_reverse( $levels, true );
		}
		foreach ( $levels as $level_id => $level ) {
			$is_visible = isset( $level['is_visible'] ) ? (bool) $level['is_visible'] : true;
			if ( $is_visible ) {
				continue;
			}
			unset( $levels[ $level_id ] );
		}

		return $levels;
	}

	private function get_selected() {
		global $wpdb, $psts;

		$blog_id = $this->blog_id;
		$curr    = $wpdb->get_row( $wpdb->prepare( "SELECT term, level FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id ) );

		$featured = $psts->get_setting( 'featured_level' );

		if ( $curr ) {
			$curr->term = ( $curr->term && ! is_numeric( $curr->term ) ) ? $periods[0] : $curr->term;
			$sel_period = isset( $_POST['period'] ) ? $_POST['period'] : $curr->term;
			$sel_level  = isset( $_POST['level'] ) ? $_POST['level'] : $curr->level;
		} else {
			@$curr->term = null;
			$curr->level = null;
			$sel_period  = isset ( $_POST['period'] ) ? $_POST['period'] : ( defined( 'PSTS_DEFAULT_PERIOD' ) ? PSTS_DEFAULT_PERIOD : MONTHLY );
			$sel_level   = isset ( $_POST['level'] ) ? $_POST['level'] : ( defined( 'PSTS_DEFAULT_LEVEL' ) ? PSTS_DEFAULT_LEVEL : $featured );
		}
		$this->sel_period = $sel_period;
		$this->sel_level  = $sel_level;
	}

	private function load_periods() {
		global $psts;
		$this->periods = (array) $psts->get_setting( 'enabled_periods' );
	}

	public static function getInstance( $args ) {
		if ( ! isset ( self::$instance ) ) {
			$className      = __CLASS__;
			self::$instance = new $className ( $args );
		}

		return self::$instance;
	}

	/**
	 * Display Coupon detail if applied on checkout
	 *
	 * @param $content
	 *
	 * @return mixed|string|void
	 */
	public function show_coupon_feedback( $content ) {

		global $psts;

		$blog_id = $this->blog_id;

		$content = apply_filters( 'psts_before_checkout_grid_coupon', $content, $this->blog_id );

		$coupon_code = ProSites_Helper_Session::session( 'COUPON_CODE' );
		if ( isset( $coupon_code ) ) {

			$coupon_value = $psts->coupon_value( $coupon_code, 100 );
			$content .= '<div id="psts-coupon-msg">' . sprintf( __( 'Your coupon code <strong>%1$s</strong> has been applied for a discount of %2$s off the first payment. <a href="%3$s">Remove it &raquo;</a>', 'psts' ), esc_html( $coupon_code ), $coupon_value['discount'], get_permalink() . "?bid=$blog_id&remove_coupon=1" ) . '</div>';

		} else if ( $errmsg = $psts->errors->get_error_message( 'coupon' ) ) {
			$content .= '<div id="psts-coupon-error" class="psts-error">' . $errmsg . '</div>';
		}

		return $content;
	}

	private function get_periods_nav() {
		global $psts;

		$period_labels = array(
			MONTHLY   => "Monthly",
			QUARTERLY => "Quarterly",
			YEARLY    => "Yearly",
		);
		$content       = '<ul class="tab-menu">';
		$selected      = $this->sel_period ? $this->sel_period : MONTHLY;
		foreach ( $this->periods as $key => $value ) {
			$selected_class = $selected == $value ? "selected" : "";
			$content .= '<li class="' . strtolower( $period_labels [ $value ] ) . ' period ' . $selected_class . '"><a class="button period" href="' . $psts->checkout_url( $this->blog_id ) . '" data-period="' . $value . '">' . $period_labels [ $value ] . '</a></li>';
		}
		$content .= "</ul>";

		return $content;
	}

	private function get_level_description( $level_price, $period = null ) {
		global $psts;
		$content = "";
		$period  = $period ? $period : $this->sel_period;
		switch ( $period ) {
			case MONTHLY:
				$content = '<p><strong>' . __( 'Try it out!', 'psts' ) . '</strong></p>';
				if ( in_array( QUARTERLY, $this->periods ) || in_array( QUARTERLY, $this->periods ) ) {
					$content .= '<p class="save">' . __( 'You can easily switch to a better value plan at any time.', 'psts' ) . '</p>';
				}
				$content = apply_filters( 'psts_pricing_plan_description', $content, MONTHLY, $this->periods );
				break;
			case QUARTERLY:
				$content = '<p class="equivalent">' . sprintf( __( 'Equivalent to only <br />  %s monthly', 'psts' ), $psts->format_currency( false, $level_price [ QUARTERLY ] / 3 ) ) . '</p>';
				if ( in_array( MONTHLY, $this->periods ) && ( ( $level_price [ MONTHLY ] * 3 ) - $level_price [ QUARTERLY ] ) > 0 ) {
					$content .= '<p class="save">' . sprintf( __( 'Save %s by paying for 3 months in advance!', 'psts' ), $psts->format_currency( false, ( $level_price [ MONTHLY ] * 3 ) - $level_price [ QUARTERLY ] ) ) . '</p>';
				}
				$content = apply_filters( 'psts_pricing_plan_description', $content, QUARTERLY, $this->periods );
				break;
			case YEARLY:
				$content = '<p class="equivalent">' . sprintf( __( 'Equivalent to only <br />%s monthly', 'psts' ), $psts->format_currency( false, $level_price [ YEARLY ] / 12 ) ) . '</p>';
				if ( in_array( MONTHLY, $this->periods ) && ( ( $level_price [ MONTHLY ] * 12 ) - $level_price [ YEARLY ] ) > 0 ) {
					$content .= '<p class="save">' . sprintf( __( 'Save %s by paying for a year in advance!', 'psts' ), $psts->format_currency( false, ( $level_price [ MONTHLY ] * 12 ) - $level_price [ YEARLY ] ) ) . '</p>';
				}
				$content = apply_filters( 'psts_pricing_plan_description', $content, YEARLY, $this->periods );
				break;
		}

		return $content;
	}

	private function get_coupon_link() {

		if ( ! ( defined( 'PSTS_DISABLE_COUPON_FORM' ) && PSTS_DISABLE_COUPON_FORM ) ) {
			$content = '<div class="coupon-wrapper">';
			$coupons = get_site_option( 'psts_coupons' );

			$coupon_code = ProSites_Helper_Session::session( 'COUPON_CODE' );
			if ( is_array( $coupons ) && count( $coupons ) && ! isset ( $coupon_code ) ) {
				$content .= '<div id="psts-coupon-block">
                                <small><a id="psts-coupon-link" href="#">' . __( 'Have a coupon code?', 'psts' ) . '</a></small>
					            <div id="psts-coupon-code" style="display: none;">
					                <label for="coupon_code">' . __( 'Enter your code:', 'psts' ) . '</label>
					                <input type="text" name="coupon_code" id="coupon_code" />&nbsp;
					                <input type="submit" name="coupon-submit" class="regbutton" value="' . __( 'Apply &raquo;', 'psts' ) . '" />
					            </div>
					         </div>';
			}
			$content .= '</div>';
		}

		return $content;
	}

	private function get_plan_content( $period, $data, $level, $level_name ) {
		global $psts;

		$level_prices         = array(
			MONTHLY   => $data ['price_1'],
			QUARTERLY => $data ['price_3'],
			YEARLY    => $data ['price_12'],
		);
		$level_price          = $psts->format_currency( false, $level_prices [ $period ] );
		$price                = $level_price;
		$discount_price_level = $price;
		$recurring            = $psts->get_setting( 'recurring_subscriptions', true );
		$upgrade_price        = ( $recurring ) ? $level_prices[ $period ] : $psts->calc_upgrade_cost( $this->blog_id, $level, $period, $level_prices[ $period ] );
		$coupon_code          = ProSites_Helper_Session::session( 'COUPON_CODE' );

		if ( isset ( $coupon_code ) && $psts->check_coupon( $coupon_code, $this->blog_id, $level, $period ) && $coupon_value = $psts->coupon_value( $coupon_code, $level_prices [ $period ] ) ) {
			$level_discount_price = $psts->format_currency( false, $coupon_value ['new_total'] );
			$discount_price_level = '<del>' . $level_price . '</del><strong class="coupon">' . $level_discount_price . '</strong>';
		} elseif ( $upgrade_price != $level_prices[ $period ] ) {
			$discount_price_level = '<del>' . $level_price . '</del><strong class="coupon">' . $psts->format_currency( false, $upgrade_price ) . '</strong>';
		}

		$setup_fee_label = '';
		if ( $psts->has_setup_fee( $this->blog_id, $level ) ) {
			$setup_fee_amt   = ProSites_Helper_Settings::setup_fee();
			$setup_fee       = $psts->format_currency( false, $setup_fee_amt );
			$setup_fee_label = '<p class="setup fee">+ a one time ' . $setup_fee . ' setup fee.</p>';
		}
		$level_description = $this->get_level_description( $level_prices, $period );
		$choose_plan       = '<a href="#" class="button choose-plan" data-level="' . $level . '" data-level-name="' . strtolower( $level_name ) . '">Choose Plan</a>';
		$cur_period        = $this->sel_period ? $this->sel_period : MONTHLY;
		$style             = $period == $cur_period ? "" : 'style="display:none;"';
		$selected          = $this->sel_level == $level ? "selected" : "";
		$content           = '<li class="' . strtolower( $level_name ) . ' column period_' . $period . ' ' . $selected . ' " ' . $style . '><p class="plan price">' . $discount_price_level .
		                     '</p><span> per ' . $period . ' month' . ( $period > 1 ? "s" : "" ) . '</span>' .
		                     $setup_fee_label .
		                     $level_description .
		                     $choose_plan .
		                     '</li>';

		return $content;
	}

	/**
	 * List of modules Level wise
	 * @return mixed|string|void
	 */
	private function get_plans_extra_content() {
		global $psts;

		$content = "";
		$trial_days = $psts->get_setting( 'trial_days', 0 );

		if ( $this->include_pricing ) {
			$content .= $this->display_pricing_table();
		}

		$content = apply_filters( 'psts_checkout_method_image', $content );

		$content .= '<div class="terpsts-wrapper">';
			if ( $psts->is_trial_allowed( $this->blog_id ) ) {
				$content .= '<p style="padding-top:24px">' . str_replace( 'DAYS', $trial_days, $psts->get_setting( 'cancel_message' ) ) . '</p>';
			}
		$content .= '</div>';

		$content .= '<div class="bulk-updates-wrapper">';
			$content = apply_filters( 'psts_checkout_grid_before_free', $content, $this->blog_id, $this->periods, '100%' );
		$content .= '</div>';

		$content .= '<div class="free-msg-wrapper">';
			if ( get_blog_option( $this->blog_id, 'psts_signed_up' ) && ! apply_filters( 'psts_prevent_dismiss', false ) ) {
				$content .= '<tr class="psts_level level-free">
                    <td valign="middle" class="level-name"><h3>' . $psts->get_setting( 'free_name', __( 'Free', 'psts' ) ) . '</h3></td>';
					$content .= '<td class="level-option" colspan="' . count( $this->periods ) . '">';
					$content .= '<a class="pblg-checkout-opt" style="width:100%" id="psts-free-option" href="' . get_admin_url( $this->blog_id, 'index.php?psts_dismiss=1', 'http' ) . '" title="' . __( 'Dismiss', 'psts' ) . '">' . $psts->get_setting( 'free_msg', __( 'No thank you, I will continue with a basic site for now', 'psts' ) ) . '</a>';
				$content .= '</td></tr>';
			}
		$content .= '</div>';
		$content = apply_filters( 'psts_checkout_grid_after_free', $content, $this->blog_id, $this->periods, '100%' );

		return $content;
	}

	private function adjustBrightness( $hex, $steps ) {
		// Steps should be between -255 and 255. Negative = darker, positive = lighter
		$steps = max( - 255, min( 255, $steps ) );

		// Format the hex color string
		$hex = str_replace( '#', '', $hex );
		if ( strlen( $hex ) == 3 ) {
			$hex = str_repeat( substr( $hex, 0, 1 ), 2 ) . str_repeat( substr( $hex, 1, 1 ), 2 ) . str_repeat( substr( $hex, 2, 1 ), 2 );
		}

		// Get decimal values
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		// Adjust number of steps and keep it inside 0 to 255
		$r = max( 0, min( 255, $r + $steps ) );
		$g = max( 0, min( 255, $g + $steps ) );
		$b = max( 0, min( 255, $b + $steps ) );

		$r_hex = str_pad( dechex( $r ), 2, '0', STR_PAD_LEFT );
		$g_hex = str_pad( dechex( $g ), 2, '0', STR_PAD_LEFT );
		$b_hex = str_pad( dechex( $b ), 2, '0', STR_PAD_LEFT );

		return '#' . $r_hex . $g_hex . $b_hex;
	}

	private function get_plans_table() {
		global $psts;

		$equiv         = '';
		$coupon_price  = '';
		$setup_fee_amt = ProSites_Helper_Settings::setup_fee();
		$levels        = $this->get_levels();
		$column_width  = 100 / ( count( $levels ) + 1 );
		$custom_style  = "";
		foreach ( $levels as $level ) {
			$level_name      = $level ['name'];
			$rgb_color_start = $psts->get_setting( 'pricing_table_level_' . $level_name . '_color' );
			if ( empty ( $rgb_color_start ) ) {
				continue;
			}
			$rgb_color_end     = $this->adjustBrightness( $rgb_color_start, - 100 );
			$rgb_color         = 'background: ' . $rgb_color_start . ';
background: -moz-linear-gradient(top,  ' . $rgb_color_start . ' 0%, ' . $rgb_color_end . ' 100%);
background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,' . $rgb_color_start . '), color-stop(100%,' . $rgb_color_end . '));
background: -webkit-linear-gradient(top,  ' . $rgb_color_start . ' 0%,' . $rgb_color_end . ' 100%);
background: -o-linear-gradient(top,  ' . $rgb_color_start . ' 0%,' . $rgb_color_end . ' 100%);
background: -ms-linear-gradient(top,  ' . $rgb_color_start . ' 0%,' . $rgb_color_end . ' 100%);
background: linear-gradient(to bottom,  ' . $rgb_color_start . ' 0%,' . $rgb_color_end . ' 100%);
filter: progid:DXImageTransform.Microsoft.gradient( startColorstr=\'' . $rgb_color_start . '\', endColorstr=\'' . $rgb_color_end . '\',GradientType=0 );';
			$rgb_lighter_color = $this->adjustBrightness( $rgb_color_start, 180 );
			$level_name        = strtolower( $level_name );
			$custom_style .= '
      #plans-table .plan.labels li.' . $level_name . '.selected, 
      #plans-table plan.labels li.' . $level_name . '.selected:hover {
        background:' . $rgb_color . ';
      }
      #plans-table .plan.description > .column.' . $level_name . '.selected, 
      #comparison-table .column.' . $level_name . '.selected {
        background:' . $rgb_lighter_color . ';
      }
      ';
		}
		$content = '
    <style type="text/css">
      ' . $custom_style . '
      @media only screen and (min-width: 480px) {
        .column {
          width:' . $column_width . '%!important;
        }
      }
    </style>
    <div class="plans body">';
		// Render table headers
		$content .= '<div class="header row">
						<ul class="plan labels">
							<li class="price column">Select your plan!</li>';
		$selected_period = $this->sel_period;
		$body_content    = "";
		$selected        = $this->sel_level;
		foreach ( $levels as $level => $data ) {
			$level_name     = apply_filters( 'psts_checkout_grid_levelname', $data['name'], $level, $this->blog_id );
			$selected_class = $selected == $level ? "selected" : "";
			$content .= '<li class="' . strtolower( $level_name ) . ' column ' . $selected_class . '"><a class="button choose-plan" href="#" data-level="' . $level . '" data-level-name="' . strtolower( $level_name ) . '" >' . $level_name . '</a></li>';

			foreach ( $this->periods as $period ) {
				$body_content .= $this->get_plan_content( $period, $data, $level, $level_name );
			}
		}
		$content .= '</ul></div>';
		// Render table rows
		$content .= '<div class="body row"><ul class="plan description"><li class="price column">
      <h3>Price</h3>
      ' . $this->get_coupon_link() . '
    </li>';
		$content .= $body_content;
		$content .= '</ul>
    <div class="clearfix"></div>';
		$content .= '
      </div>
    </div>
    <input type="hidden" id="psts_period" value="' . ( $this->sel_period ? $this->sel_period : MONTHLY ) . '" name="period" />
    <input type="hidden" id="psts_level" value="' . $this->sel_level . '" name="level" />';

		return $content;
	}

	private function get_modules() {
		global $psts;

		return $psts->get_setting( 'modules_enabled' );
	}

	private function filter_modules( $modules ) {
		global $psts;

		$modules_order = $psts->get_setting( 'pricing_table_order' ) ? $psts->get_setting( 'pricing_table_order' ) : "";
		if ( "" != trim( $modules_order ) ) {
			$order       = explode( ",", $modules_order );
			$sorted_data = array();
			for ( $i = 0; $i < count( $order ); $i ++ ) {
				if ( ! is_numeric( $order [ $i ] ) ) {
					continue;
				}
				$sorted_data[] = $modules [ $order [ $i ] - 1 ];
			}
			$modules = $sorted_data;
		}

		$data = array();
		foreach ( $modules as $class_name ) {
//			$is_visible = $psts->get_setting( 'pricing_table_module_' . $class_name . '_visible' ) ? $psts->get_setting( 'pricing_table_module_' . $class_name . '_visible' ) : false;

			$data[] = $class_name;
		}

		return $data;
	}

	/**
	 * Returns all modules which are visible and it's availability as per level
	 * @return string, List of all the features
	 * @Todo: Do not show feature which are not enabled for any of the levels
	 */
	private function get_module_features() {
		global $psts;

		$modules = $this->get_modules();
		$content = '<ul class="module features">';
		$levels  = $this->get_levels();
		$modules = $this->filter_modules( $modules );
		foreach ( $modules as $class_name ) {
			$exclude_class = array(
				'ProSites_Module_ProWidget',
				'ProSites_Module_PayToBlog'
			);
			if ( in_array( $class_name, $exclude_class ) ) {
				continue;
			}
			global $$class_name;
			$module_label       = "";
			$module_description = "";
			if ( class_exists( $class_name ) ) {
				if ( property_exists( $class_name, 'user_label' ) ) {
					$module_label = $class_name::$user_label;
				}
				if ( property_exists( $class_name, 'user_description' ) ) {
					$module_description = $class_name::$user_description;
				}
			}
			$module_label       = $psts->get_setting( 'pricing_table_module_' . $class_name . '_label' ) ? $psts->get_setting( 'pricing_table_module_' . $class_name . '_label' ) : $module_label;
			$module_description = $psts->get_setting( 'pricing_table_module_' . $class_name . '_description' ) ? $psts->get_setting( 'pricing_table_module_' . $class_name . '_description' ) : $module_description;
			$content .= '<li class="row">
				            <div class="feature-name column">
				                <label>' . $module_label . '<span class="helper icon">&nbsp;</span></label>
				                <div class="helper wrapper">
				                    <div class="helper content">' . $module_description . '</div>
						        </div>
							</div>';

			//Check the Availability of a module for a particular level
			foreach ( $levels as $level_id => $level ) {
				$module_includes = $is_visible = false;

				//Check if class has some level settings
				$default_value = ( method_exists( $class_name, 'required_level' ) && $level_id >= $$class_name->required_level() ) ? "enabled" : "disabled";

				//Fetch class settings
				$is_visible = $psts->get_setting( 'pricing_table_module_' . $class_name . '_visible' );

				//Check if there is no settings, Use the default settings
				$is_visible = $is_visible ? $is_visible : ( $default_value ? $default_value : "disabled" );

				$level_includes_text = $psts->get_setting( 'pricing_table_module_' . $class_name . '_included_' . $level_id );
				$module_includes     = ! empty( $level_includes_text ) ? $level_includes_text : $module_includes;

				if ( empty ( $module_includes ) ) {
					$level_includes_check_mark = $psts->get_setting( 'pricing_table_module_' . $class_name . '_has_thick_' . $level_id );
					if ( $is_visible == 'enabled' ) {
						$module_includes = true;
					} else {
						if ( class_exists( $class_name ) ) {
							if ( method_exists( $class_name, 'is_included' ) ) {
								$is_included = call_user_func( $class_name . '::is_included', $level_id );
								$module_includes = $is_included;
							}
						}
					}
				}

				if ( ! empty ( $module_includes ) && ! is_bool( $module_includes ) ) {
					$module_includes = '<div class="text">' . $module_includes . '</div>';
				} elseif ( $module_includes === true ) {
					$module_includes = '<div class="check-mark">&#x2713;</div>';
				} else {
					$module_includes = '<div class="cross">X</div>';
				}
				$selected = $this->sel_level == $level_id ? "selected" : "";
				$content .= '<div class="' . strtolower( $level ['name'] ) . ' column ' . $selected . '">' . $module_includes . '</div>';
			}
			$content .= '</li>';
		}
		$content .= '<li class="row"><div class="column"></div>';
		foreach ( $levels as $level_id => $level ) {
			$content .= '<div class="column">
			          <a href="#" class="button choose-plan" data-level="' . $level_id . '" data-level-name="' . strtolower( $level ['name'] ) . '">Choose Plan</a>
		            </div>';
		}
		$content .= '</li>';
		$content .= '
        </ul>
        <div class="clearfix"></div>';

		return $content;
	}

	/**
	 * Display Pricing table (Plans and Comparison ) on checkout page
	 *
	 * @param string $include_pricing
	 *
	 * @return mixed|string|void
	 */
	public function display_plans_table( $include_pricing = 'no' ) {

		$this->include_pricing = 'include-pricing' == $include_pricing ? true : false;

		$content = '<section id="plans-table">';
		$content = apply_filters( 'psts_pricing_coupon_feedback', $this->show_coupon_feedback( $content ) );
		$content = apply_filters( 'psts_before_checkout_grid', $content, $this->blog_id );
		$content .= $this->get_periods_nav();
		$content = apply_filters( 'psts_after_pricing_periods', $content, $this->blog_id );
		$content .= $this->get_plans_table();
		$content .= '</section>';
		$content .= $this->get_plans_extra_content();

		return $content;
	}

	public function display_pricing_table() {
		$content = '<section id="comparison-table">';
		$content .= '<h3>' . apply_filters( 'psts_checkout_grid_comparison_label', "Features", $this->blog_id ) . '</h3>';
		$content .= $this->get_module_features();
		$content .= '</section>';

		return $content;
	}
}