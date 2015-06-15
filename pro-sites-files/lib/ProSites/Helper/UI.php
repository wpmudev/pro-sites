<?php

if ( ! class_exists( 'ProSites_Helper_UI' ) ) {

	class ProSites_Helper_UI {

		public static function help_text( $message = '' ) {
			global $psts;

			if( empty( $message ) ){
				return false;
			}
			return '<img width="16" height="16" src="' . $psts->plugin_url . 'images/help.png" class="help_tip"><div class="psts-help-text-wrapper period-desc"><div class="psts-help-arrow-wrapper"><div class="psts-help-arrow"></div></div><div class="psts-help-text">' . $message . '</div></div>';
		}

		/**
		 * Enqueue the main style and js
		 */
		public static function load_psts_style() {
			wp_enqueue_style( 'psts-style' );
			wp_enqueue_script( 'psts-js' );
		}

		/**
		 * Loads the Chosen Style and script
		 */
		public static function load_chosen() {
			wp_enqueue_style( 'chosen' );
			wp_enqueue_script( 'chosen' );
		}

		public static function rich_currency_format( $amount, $plain = false ) {
			global $psts;
			$currency = $psts->get_setting( 'currency', 'USD' );

			if( $plain ) {
				return $psts->format_currency( $currency, $amount );
			}

			// get the currency symbol
			$symbol = @$psts->currencies[ $currency ][1];
			// if many symbols are found, rebuild the full symbol
			$symbols = explode( ', ', $symbol );
			if ( is_array( $symbols ) ) {
				$symbol = "";
				foreach ( $symbols as $temp ) {
					$symbol .= '&#x' . $temp . ';';
				}
			} else {
				$symbol = '&#x' . $symbol . ';';
			}
			$symbol = apply_filters( 'prosite_currency_symbol', $symbol, $currency );

			//check decimal option
			if ( $psts->get_setting( 'curr_decimal' ) === '0' ) {
				$decimal_place = 0;
				$zero          = '0';
			} else {
				$decimal_place = 2;
				$zero          = '0.00';
			}

			$symbol = '<span class="symbol">' . $symbol . '</span>';

			$amount = number_format( floatval( $amount ), $decimal_place );
			$amount = explode( '.', $amount );

			$left = $amount[0];
			$left = '<span class="whole">' . $left . '</span>';

			$right = count( $amount ) > 1 ? $amount[1] : '';
			$right = ! empty( $right ) ? '<span class="decimal">' . $right . '</span>' : '<span class="decimal hidden"></span>';

			if( ! empty( $amount[1] ) ) {
				$amount = $left . '<span class="dot">.</span>' . $right;
			} else {
				$amount = $left . '<span class="dot hidden">.</span>' . $right;
			}

			$symbol_position = $psts->get_setting( 'curr_symbol_position', 1 );
			/*
			 * 1 - Left Tight
			 * 2 - Left Space
			 * 3 - Right Tight
			 * 4 - Right Space
			 */
			$symbol_position = apply_filters( 'prosite_currency_symbol_position', $symbol_position, $currency );

			//format currency amount according to preference
			if ( $symbol_position == 1 ) {
				return $symbol . $amount;
			} else if ( $symbol_position == 2 ) {
				return $symbol . ' ' . $amount;
			} else if ( $symbol_position == 3 ) {
				return $amount . $symbol;
			} else if ( $symbol_position == 4 ) {
				return $amount . ' ' . $symbol;
			}

		}



	}

}