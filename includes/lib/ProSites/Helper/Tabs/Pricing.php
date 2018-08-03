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

if ( ! class_exists( 'ProSites_Helper_Tabs_Pricing' ) ) {
	class ProSites_Helper_Tabs_Pricing extends ProSites_Helper_Tabs {

		public static function render( $callback_parent = 'ProSites_Helper_Tabs', $settings_header = array(), $options = array(), $persistent = array() ) {
			parent::render_child( get_class(), $callback_parent, $settings_header, $options, $persistent );
		}

		public static function get_active_tab() {
			return parent::get_active_tab_child( get_class() );
		}

		public static function get_tabs() {

			$section_options = array(
				'header_save_button' => true,
				'button_name'        => 'pricing',
			);

			$tabs = array(
				'pricing_table' => array_merge( $section_options, array(
					'title' => __( 'Pricing Table', 'psts' ),
					'desc'               => array(
						__( 'Choose Pricing Table Preferences.', 'psts' ),
					),
					'class' => '',
				) ),
				'comparison_table' => array_merge( $section_options, array(
					'title' => __( 'Feature Table', 'psts' ),
					'desc'               => array(
						__( 'Choose Feature Table Preferences', 'psts' ),
					),
				) ),
				'pricing_style' => array_merge( $section_options, array(
					'title' => __( 'Styling', 'psts' ),
					'desc'               => array(
						__( 'Modify styling of pricing and features tables.', 'psts' ),
					),
				) ),
			);

			$page = sanitize_html_class( @$_GET['page'], 'pricing_table' );

			foreach ( $tabs as $key => $tab ) {
				$tabs[ $key ]['url'] = sprintf(
					'admin.php?page=%1$s&tab=%2$s',
					esc_attr( $page ),
					esc_attr( $key )
				);
			}

//			$tabs = self::remove_disabled_module_tabs( $tabs );
			return apply_filters( 'prosites_pricing_tabs', $tabs );

		}

	}
}