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

if ( ! class_exists( 'ProSites_Helper_Tabs' ) ) {

	class ProSites_Helper_Tabs {

		public static function render_child( $child, $callback_parent = 'ProSites_Helper_Tabs', $settings_header = array(), $options = array(), $persistent = array() ) {

			// Get options
			$defaults = array(
				'header_save_button'  => false,
				'section_save_button' => false,
				'nonce_name'          => null,
				'button_name'         => null,
			);
			$options  = wp_parse_args( $options, $defaults );
			extract( $options );

			// Create $settings_header if not exist
			if ( empty( $settings_header ) ) {
				$settings_header = array(
					'title' => __( 'This title needs a new name.', 'psts' ),
					'desc'  => __( 'Pass it as argument 2 in render()', 'psts' ),
				);
			}

			// Note: IDE error, but it will be created from extract() function.
			if ( ! isset( $settings_header['page_header'] ) || ! $settings_header['page_header'] ) {
				$settings_header['header_save_button'] = $header_save_button;
			}
			$settings_header['button_name'] = $button_name;

			$tabs = call_user_func( array( $child, 'get_tabs' ) );

			// Render tabbed interface.
			?>
			<div class="psts-wrap wrap">
				<?php
				if ( ! empty( $nonce_name ) ) {
					$nonce_name = sanitize_text_field( $nonce_name );
					wp_nonce_field( $nonce_name );
				}

				ProSites_Helper_Settings::settings_header( $settings_header );

				reset( $tabs ); // If the first key has already been used
				$active_tab = ! empty( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : key( $tabs );

				self::vertical_tabs( $tabs, $active_tab, $persistent );

				// Call the appropriate form to render.
				$callback_name = 'render_tab_' . str_replace( '-', '_', $active_tab );
				if ( method_exists( $callback_parent, $callback_name ) ) {
					$render_callback = array( $callback_parent, $callback_name );
				} else {
					$render_callback = array( get_class(), 'render_generic_tab' );
				}
				/**
				 * Allow to plugin external gateways
				 */
				$render_callback = apply_filters(
					'prosites_settings_tabs_render_callback',
					$render_callback,
					$active_tab
				);
				?>
				<div class="psts-settings">
					<?php
					//echo $callback_parent . '::' . $callback_name;
					$html = call_user_func( $render_callback );

					if ( ! empty( $section_save_button ) && ! empty( $button_name ) ) {
						$html .= '<hr />
							<p class="section-save-button">
								<input type="submit" name="submit_' . esc_attr( $button_name ) . '_section" class="button-primary" value="' . esc_attr( __( 'Save Changes', 'psts' ) ) . '"/>
							</p>';
					}
					$html = apply_filters( 'prosites_settings_tab_content_' . $callback_name, $html );
					echo $html;
					?>
				</div>
			</div>
			<?php
		}

		public static function vertical_tabs( $tabs, $active_tab = null, $persistent = array() ) {
			reset( $tabs );
			$first_key = key( $tabs );

			// Setup navigation tabs.
			if ( empty( $active_tab ) ) {
				$active_tab = ! empty( $_GET['tab'] ) ? $_GET['tab'] : $first_key;
			}

			if ( ! array_key_exists( $active_tab, $tabs ) ) {
				$active_tab = $first_key;
			}

			// Render tabbed interface.
			?>
			<div class="psts-tab-container">
				<ul class="psts-tabs" style="">
					<?php foreach ( $tabs as $tab_name => $tab ) :
						$tab_class = $tab_name == $active_tab ? 'active' : '';
						$url = $tab['url'];

						if ( ! empty( $tab['class'] ) ) {
							$tab_class .= ' ' . $tab['class'];
						}

						foreach ( $persistent as $param ) {
							$value = @$_REQUEST[ $param ];
							$url   = add_query_arg( $param, $value, $url );
						}
						?>
						<li class="psts-tab <?php echo esc_attr( $tab_class ); ?> ">
							<a class="psts-tab-link" href="<?php echo esc_url( $url ); ?>">
								<?php echo esc_html( $tab['title'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php

			// Return current active tab.
			return $active_tab;
		}

		public static function render_generic_tab() {

			ob_start();
			do_action( 'psts_settings_page' );
			$content = ob_get_clean();

			if ( empty( $content ) ) {
				_e( 'Settings page not found.', 'psts' );
			} else {
				echo $content;
			}

		}

		public static function get_tabs() {
			die( 'Please override get_tabs(),' );
		}

		public static function get_active_tab_child( $child ) {
			$tabs = call_user_func( array( $child, 'get_tabs' ) );
			reset( $tabs ); // If the first key has already been used
			$active_tab                     = ! empty( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : key( $tabs );
			$tabs[ $active_tab ]['tab_key'] = $active_tab;

			return $tabs[ $active_tab ];
		}

	}

}
	