<?php
/**
 * @copyright Incsub (http://incsub.com/)
 *
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,
 * MA 02110-1301 USA
 *
 */

if ( ! class_exists( 'ProSites_Helper_Tabs_Gateways' ) ) {
	class ProSites_Helper_Tabs_Gateways extends ProSites_Helper_Tabs {

		public static function render( $callback_parent = 'ProSites_Helper_Tabs', $settings_header = array(), $options = array(), $persistent = array() ) {
			parent::render_child( get_class(), $callback_parent, $settings_header, $options, $persistent );
		}

		public static function get_active_tab() {
			return parent::get_active_tab_child( get_class() );
		}

		public static function get_tabs() {

			$section_options = array(
				'header_save_button' => true,
				'button_name'        => 'gateways',
			);

			$tabs = array(
				'gateway_prefs' => array_merge( $section_options, array(
					'title' => __( 'Gateway Settings', 'psts' ),
					'desc'               => array(
						__( 'Choose how Pro Sites should handle multiple active payment gateways', 'psts' ),
					),
					'class' => 'prosites-gateway-pref',
				) ),
//				'twocheckout' => array_merge( $section_options, array(
//					'title' => __( '2Checkout', 'psts' ),
//					'desc'               => array(
//						__( "Accept Credit Cards, PayPal, and Debit Cards", 'psts' ) .
//						' <a href="https://www.2checkout.com" target="_blank">' . __( 'More Info &raquo;', 'psts' ) . '</a>',
//					),
//				) ),
				'paypal' => array_merge( $section_options, array(
					'title' => __( 'PayPal Express/Pro', 'psts' ),
					'desc'               => array(
						__( 'Express Checkout is PayPal\'s premier checkout solution, which streamlines the checkout process for buyers and keeps them on your site after making a purchase.', 'psts' ),
					),
				) ),
				'stripe' => array_merge( $section_options, array(
					'title' => __( 'Stripe', 'psts' ),
					'desc'               => array(
						__( 'Stripe makes it easy to start accepting credit cards directly on your site with full PCI compliance', 'psts' ),
					),
				) ),
				'manual' => array_merge( $section_options, array(
					'title' => __( 'Manual Payments', 'psts' ),
					'desc'               => array(
						__( 'Record payments manually, such as by Cash, Check, EFT, or an unsupported gateway.', 'psts' ),
					),
				) ),
			);

			$page = sanitize_html_class( @$_GET['page'], 'gateway_prefs' );

			foreach ( $tabs as $key => $tab ) {
				$tabs[ $key ]['url'] = sprintf(
					'admin.php?page=%1$s&tab=%2$s',
					esc_attr( $page ),
					esc_attr( $key )
				);
			}

//			$tabs = self::remove_disabled_module_tabs( $tabs );
			return apply_filters( 'prosites_gateways_tabs', $tabs );

		}

	}
}