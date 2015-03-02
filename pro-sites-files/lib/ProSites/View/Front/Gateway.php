<?php

if ( ! class_exists( 'ProSites_View_Front_Gateway' ) ) {
	class ProSites_View_Front_Gateway {

		public static function render_checkout( $blog_id, $domain ) {
			global $psts;

			$content = '';

			// Add existing account filter
			add_filter( 'prosites_render_checkout_page', 'ProSites_View_Front_Gateway::prepend_plan_details', 10, 3 );

			$gateways = self::get_gateways();
			$gateway_details = self::get_gateway_details( $gateways );

			$primary_gateway = $gateway_details['primary'];
			$secondary_gateway = $gateway_details['secondary'];
			$manual_gateway = $gateway_details['manual'];

			/**
			 * Process forms
			 * -------------
			 */
			if( ! empty( $primary_gateway ) && method_exists( $gateways[ $primary_gateway ]['class'], 'process_checkout_form' ) ) {
				$primary_args = call_user_func( $gateways[ $primary_gateway ]['class'] . '::process_checkout_form', $blog_id, $domain );
			}
			if( ! empty( $secondary_gateway ) && method_exists( $gateways[ $secondary_gateway ]['class'], 'process_checkout_form' ) ) {
				$secondary_args = call_user_func( $gateways[ $secondary_gateway ]['class'] . '::process_checkout_form', $blog_id, $domain );
			}
			if( ! empty( $manual_gateway ) && method_exists( $gateways[ $manual_gateway ]['class'], 'process_checkout_form' ) ) {
				$manual_args = call_user_func( $gateways[ $manual_gateway ]['class'] . '::process_checkout_form', $blog_id, $domain );
			}

			$tabbed = 'tabbed' == $psts->get_setting( 'pricing_gateways_style', 'tabbed' ) ? true : false;
			$hidden_class = empty( $_POST ) ? 'hidden' : '';

			$content .= '<div' . ( $tabbed ? ' id="gateways"' : '' ) . ' class="gateways checkout-gateways ' . $hidden_class . '">';

			// Render tabs
			if( $tabbed && count( $gateways ) > 1 ) {
				$content .= '<ul>';
				if( ! empty( $primary_gateway ) ) {
					$content .= '<li><a href="#gateways-1">' . esc_html( $psts->get_setting( 'checkout_gateway_primary_label' ) ) . '</a></li>';
				}
				if( ! empty( $secondary_gateway ) ) {
					$content .= '<li><a href="#gateways-2">' . esc_html( $psts->get_setting( 'checkout_gateway_secondary_label' ) ) . '</a></li>';
				}
				if( ! empty( $manual_gateway ) ) {
					$content .= '<li><a href="#gateways-3">' . esc_html( $psts->get_setting( 'checkout_gateway_manual_label' ) ) . '</a></li>';
				}
	            $content .= '</ul>';
			}

			// Primary
			if( ! empty( $primary_gateway ) && method_exists( $gateways[ $primary_gateway ]['class'], 'render_gateway' ) ) {
				$content .= '<div id="gateways-1" class="gateway gateway-primary">';
				$content .= call_user_func( $gateways[ $primary_gateway ]['class'] . '::render_gateway', $primary_args, $blog_id, $domain );
				$content .= '</div>';
			}

			// Secondary
			if( ! empty( $secondary_gateway ) && method_exists( $gateways[ $primary_gateway ]['class'], 'render_gateway' ) ) {
				$content .= '<div id="gateways-2" class="gateway gateway-secondary">';
				$content .= call_user_func( $gateways[ $secondary_gateway ]['class'] . '::render_gateway', $secondary_args, $blog_id, $domain, false );
				$content .= '</div>';
			}

			// Manual
			if( ! empty( $manual_gateway ) && method_exists( $gateways[ $primary_gateway ]['class'], 'render_gateway' ) ) {
				$content .= '<div id="gateways-3" class="gateway gateway-manual">';
				$content .= call_user_func( $gateways[ $manual_gateway ]['class'] . '::render_gateway', $manual_args, $blog_id, $domain, false );
				$content .= '</div>';
			}
			$content .= '</div>';

			return $content;

		}

		public static function render_current_plan_information( $blog_id, $domain, $gateways, $gateway_order ) {
			global $psts, $wpdb, $current_site, $current_user;

			$site_name = $current_site->site_name;
			$img_base  = $psts->plugin_url . 'images/';
			$info_retrieved = false;
			$content = '';

			// Is this a trial, if not, get the normal gateway data?
			$sql = $wpdb->prepare( "SELECT `gateway` FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %s", $blog_id );
			$result = $wpdb->get_row( $sql );
			if( ! empty( $result ) && 'Trial' == $result->gateway ) {
				$info_retrieved = ProSites_Gateway_Trial::get_existing_user_information( $blog_id, $domain );
			} else {
				foreach ( $gateway_order as $key ) {
					if ( ! empty( $key ) && empty( $info_retrieved ) && method_exists( $gateways[ $key ]['class'], 'get_existing_user_information' ) ) {
						$info_retrieved = call_user_func( $gateways[ $key ]['class'] . '::get_existing_user_information', $blog_id, $domain );
					}
				}
			}

			// Notifications
			$content .= self::get_notifications_only( $info_retrieved );

			// Output level information
			if( ! empty( $info_retrieved ) && empty( $info_retrieved['complete_message'] ) ) {

				$content .= '<ul class="psts-info-list">';
				//level
				if ( ! empty( $info_retrieved['level'] ) ) {
					$content .= '<li class="psts-level">' . esc_html__( 'Level:', 'psts') . ' <strong>' . $info_retrieved['level'] . '</strong></li>';
				}
				//payment method
				if( ! empty( $info_retrieved['card_type'] ) ) {
					$content .= '<li class="psts-payment-method">' . sprintf( __( 'Payment method: <strong>%1$s card</strong> ending in <strong>%2$s</strong>. Expires <strong>%3$s/%4$s</strong>', 'psts' ), $info_retrieved['card_type'], $info_retrieved['card_reminder'], $info_retrieved['card_expire_month'], $info_retrieved['card_expire_year'] ) . '</li>';
				}
				//last payment
				if( ! empty( $info_retrieved['last_payment_date'] ) ) {
					$content .= '<li class="psts-last-payment">' . esc_html__( 'Last payment date:', 'psts' ) . ' <strong>' . date_i18n( get_option( 'date_format' ), $info_retrieved['last_payment_date'] ) . '</strong></li>';
				}
				//next payment
				if( ! empty( $info_retrieved['next_payment_date'] ) ) {
					$content .= '<li class="psts-next-payment">' . esc_html__( 'Next payment date:', 'psts' ) . ' <strong>' . date_i18n( get_option( 'date_format' ), $info_retrieved['next_payment_date'] ) . '</strong></li>';
				}
				//period
				if ( ! empty( $info_retrieved['period'] ) && ! empty( $info_retrieved['recurring'] ) ) {
					$content .= '<li class="psts-period">' . esc_html__( 'Renewal Period:', 'psts') . sprintf( __( ' Every <strong>%d</strong> month(s)', 'psts' ), $info_retrieved['period'] ) . '</li>';
				}
				// Is recurring?
				if( empty( $info_retrieved['recurring'] ) ) {
					$content .= '<li class="psts-expiry">' . esc_html__( 'Plan expires on:', 'psts' ) . ' <strong>' . $info_retrieved['expires'] . '</strong></li>';
				}

				$content .= '</ul>';

				// Cancel link?
				if( ! empty( $info_retrieved['cancel_link'] ) ) {
					$content .= '<div class="psts-cancel-link">' . $info_retrieved['cancel_link'] . $info_retrieved['cancel_info'] . '</div>';
				}
				// Receipt form
				if( ! empty( $info_retrieved['receipt_form'] ) ) {
					$content .= '<div class="psts-receipt-link">' . $info_retrieved['receipt_form'] . '</div>';
				}

			}

			return '<div id="psts_existing_info"><h2>' . esc_html__( 'Your current plan', 'psts' ) . '</h2>' . $content . '</div>';

		}


		public static function get_gateways() {
			global $psts;

			$gateways = array();
			$active_gateways = (array) $psts->get_setting( 'gateways_enabled' );
			foreach( $active_gateways as $active_gateway ) {
				if( method_exists( $active_gateway, 'get_name' ) ) {
					$name = call_user_func( $active_gateway . '::get_name' );
					$gateways[ key( $name ) ] = array(
						'name'  => array_pop( $name ),
						'class' => $active_gateway
					);
				}
			}
			return $gateways;
		}

		public static function get_gateway_details( $gateways ) {
			global $psts;

			$gateway_details = array();
			$active_count = count( $gateways );

			if( 1 == $active_count ) {
				$gateway_details['primary'] = key( $gateways[0] );
				reset( $gateways );
			} else {
				$gateway_details['primary'] = $psts->get_setting( 'gateway_pref_primary' );
				$gateway_details['secondary']  = $psts->get_setting( 'gateway_pref_secondary' );
				$use_manual = $psts->get_setting( 'gateway_pref_use_manual' );

				if( 'manual' != $gateway_details['primary'] && 'manual' != $gateway_details['secondary'] && $use_manual ) {
					$gateway_details['manual'] = 'manual';
				} else {
					$gateway_details['manual'] = '';
				}
				$gateway_order = array( $gateway_details['primary'], $gateway_details['secondary'], $gateway_details['manual'] );
				$gateway_order = array_filter( $gateway_order );
				$gateway_details['order'] = $gateway_order;
			}

			return $gateway_details;

		}

		public static function get_notifications_only( $info_retrieved ) {
			$content = '';

			// Get pending message
			if( ! empty( $info_retrieved['pending'] ) ) {
				$content .= $info_retrieved['pending'];
			}

			// Get trial message
			if( ! empty( $info_retrieved['trial'] ) ) {
				$content .= $info_retrieved['trial'];
			}

			// Get complete message
			if( ! empty( $info_retrieved['complete_message'] ) ) {
				$content .= $info_retrieved['complete_message'];
				$content .= $info_retrieved['thanks_message'];
				$content .= $info_retrieved['visit_site_message'];
			}

			// Get cancellation message
			if( ! empty( $info_retrieved['cancellation_message'] ) ) {
				$content .= $info_retrieved['cancellation_message'];
			}

			return $content;
		}

		public static function render_notification_information( $blog_id, $domain, $gateways, $gateway_order ) {
			$content = '';
			$info_retrieved = '';

			foreach( $gateway_order as $key ) {
				if( ! empty( $key ) && empty( $info_retrieved ) && method_exists( $gateways[ $key ]['class'], 'get_existing_user_information' ) ) {
					$info_retrieved = call_user_func( $gateways[ $key ]['class'] . '::get_existing_user_information', $blog_id, $domain, false );
				}
			}

			// Notifications
			$content .= self::get_notifications_only( $info_retrieved );

			return $content;
		}


		public static function prepend_plan_details( $content, $blog_id, $domain ) {

			$plan_content    = '';
			$gateways        = self::get_gateways();
			$gateway_details = self::get_gateway_details( $gateways );

			// No existing details for a new signup
			if( ProSites_View_Front_Checkout::$new_signup ) {
				return $content;
			}

			if ( is_pro_site( $blog_id ) ) {
				// EXISTING DETAILS

				$plan_content = self::render_current_plan_information( $blog_id, $domain, $gateways, $gateway_details['order'] );
				$plan_content .= '<h2>' . esc_html__( 'Change your plan', 'psts' ) . '</h2>';
			} else {
				// NOTIFICATIONS ONLY

				$plan_content = self::render_notification_information( $blog_id, $domain, $gateways, $gateway_details['order'] );
			}

			return $plan_content . $content;

		}

	}

}