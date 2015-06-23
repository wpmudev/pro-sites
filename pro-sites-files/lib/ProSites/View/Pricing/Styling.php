<?php

if ( ! class_exists( 'ProSites_View_Pricing_Styling' ) ) {
	class ProSites_View_Pricing_Styling {


		// Render Tab content
		public static function render_tab_pricing_style() {
			global $psts;

			$active_tab = ProSites_Helper_Tabs_Pricing::get_active_tab();
			ProSites_Helper_Settings::settings_header( $active_tab );

			?>
			<div class="pricing-styles">
				<input type="hidden" name="pricing_settings" value="<?php echo esc_attr( $active_tab['tab_key'] ); ?>"/>
				<h3><?php esc_html_e( 'Table Layout', 'psts' ); ?></h3>
				<div class="table-layout">
					<label>
						<input type="radio" name="psts[pricing_table_layout]" value="option1" <?php checked( $psts->get_setting( 'pricing_table_layout', 'option1' ), 'option1' ); ?> />
						<div class="layout-img option1"></div>
					</label>
					<label>
						<input type="radio" name="psts[pricing_table_layout]" value="option2" <?php checked( $psts->get_setting( 'pricing_table_layout', 'option1' ), 'option2' ); ?> />
						<div class="layout-img option2"></div>
					</label>
					<label>
						<input type="radio" name="psts[pricing_table_layout]" value="option3" <?php checked( $psts->get_setting( 'pricing_table_layout', 'option1' ), 'option3' ); ?> />
						<div class="layout-img option3"></div>
					</label>
					<label>
						<input type="radio" name="psts[pricing_table_layout]" value="option4" <?php checked( $psts->get_setting( 'pricing_table_layout', 'option1' ), 'option4' ); ?> />
						<div class="layout-img option4"></div>
					</label>
					<label>
						<input type="radio" name="psts[pricing_table_layout]" value="option5" <?php checked( $psts->get_setting( 'pricing_table_layout', 'option1' ), 'option5' ); ?> />
						<div class="layout-img option5"></div>
					</label>
				</div>
				<p class="description clear"><?php esc_html_e( 'Select the base layout for the pricing tables. This will provide the initial CSS for your pricing tables.', 'psts' ); ?>
					<br/>
					<?php esc_html_e( 'For more detailed customisation you will need to alter the CSS in your theme, use a custom CSS plugin or the custom CSS box on this page.', 'psts' ); ?></p>

				<?php
				$normal_heading   = __( 'Normal Level', 'psts' );
				$selected_heading = __( 'Selected Level', 'psts' );
				$featured_heading = __( 'Featured Level', 'psts' );
				$styles           = $psts->get_setting( 'checkout_style' );
				?>
				<h3><?php esc_html_e( 'Customise Columns', 'psts' ); ?></h3>
				<p class="description clear"><?php esc_html_e( 'Use the settings below to customize your checkout table. Each column here represents the state of your level in the checkout table: normal, selected level or featured level. Note: This overrides the defaults for each of the layouts above.', 'psts' ); ?>
				<table class="form-table checkout_style">
					<tr>
						<th></th>
						<th scope="row"><?php echo esc_html( $normal_heading ); ?></th>
						<th scope="row"><?php echo esc_html( $selected_heading ); ?></th>
						<th scope="row"><?php echo esc_html( $featured_heading ); ?></th>
					</tr>
					<tr>
						<td><?php _e( 'Columns Background', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_column_bg]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_column_bg', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_column_bg_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_column_bg_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_column_bg_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_column_bg_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Columns Border Color', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_border_color]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_border_color', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_border_color_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_border_color_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_border_color_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_border_color_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Columns Border Width', 'psts' ) ?></td>
						<td>
							<select name="psts[checkout_style][pricing_style_border_width]" class="chosen">
								<?php
								$border_width         = self::get_style( 'pricing_style_border_width', $styles );
								$border_width_options = '';

								for ( $counter = 0; $counter <= 50; $counter ++ ) {
									$border_width_options .= '<option value="' . $counter . '"' . ( $counter == $border_width ? ' selected' : '' ) . '>' . ( ( $counter ) ? $counter : 'default' ) . '</option>' . "\n";
								}
								echo $border_width_options;
								?>
							</select>
						</td>
						<td>
							<select name="psts[checkout_style][pricing_style_border_width_selected]" class="chosen">
								<?php
								$border_width         = self::get_style( 'pricing_style_border_width_selected', $styles );
								$border_width_options = '';

								for ( $counter = 0; $counter <= 50; $counter ++ ) {
									$border_width_options .= '<option value="' . $counter . '"' . ( $counter == $border_width ? ' selected' : '' ) . '>' . ( ( $counter ) ? $counter : 'default' ) . '</option>' . "\n";
								}
								echo $border_width_options;
								?>
							</select>
						</td>
						<td>
							<select name="psts[checkout_style][pricing_style_border_width_featured]" class="chosen">
								<?php
								$border_width         = self::get_style( 'pricing_style_border_width_featured', $styles );
								$border_width_options = '';

								for ( $counter = 0; $counter <= 50; $counter ++ ) {
									$border_width_options .= '<option value="' . $counter . '"' . ( $counter == $border_width ? ' selected' : '' ) . '>' . ( ( $counter ) ? $counter : 'default' ) . '</option>' . "\n";
								}
								echo $border_width_options;
								?>
							</select>
						</td>
					</tr>
					<tr>
						<td><?php _e( 'Title Text', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_title_color]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_title_color', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_title_color_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_title_color_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_title_color_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_title_color_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Title Background', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_title_bg]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_title_bg', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_title_bg_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_title_bg_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_title_bg_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_title_bg_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Price Text', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_price_color]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_price_color', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_price_color_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_price_color_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_price_color_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_price_color_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Price Background', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_price_bg]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_price_bg', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_price_bg_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_price_bg_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_price_bg_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_price_bg_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Subtitle Text', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_subtitle_color]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_subtitle_color', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_subtitle_color_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_subtitle_color_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_subtitle_color_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_subtitle_color_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Subtitle Background', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_subtitle_bg]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_subtitle_bg', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_subtitle_bg_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_subtitle_bg_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_subtitle_bg_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_subtitle_bg_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Features Row Text', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_features_text_color]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_features_text_color', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_features_text_color_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_features_text_color_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_features_text_color_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_features_text_color_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Features Row Background', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_features_text_bg]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_features_text_bg', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_features_text_bg_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_features_text_bg_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_features_text_bg_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_features_text_bg_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Features Row Text (Alternate/Odd)', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_features_alt_text_color]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_features_alt_text_color', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_features_alt_text_color_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_features_alt_text_color_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_features_alt_text_color_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_features_alt_text_color_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Features Row Background (Alternate/Odd)', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_features_alt_bg]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_features_alt_bg', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_features_alt_bg_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_features_alt_bg_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_features_alt_bg_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_features_alt_bg_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Button Container', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_container]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_container', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_container_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_container_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_container_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_container_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Button Text', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_text_color]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_text_color', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_text_color_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_text_color_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_text_color_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_text_color_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Button Background', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_bg]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_bg', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_bg_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_bg_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_bg_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_bg_featured', '', $styles ); ?>"/></td>

					</tr>
					<tr>
						<td><?php _e( 'Button Text (Hover)', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_hover_text_color]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_hover_text_color', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_hover_text_color_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_hover_text_color_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_hover_text_color_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_hover_text_color_featured', '', $styles ); ?>"/></td>
					</tr>
					<tr>
						<td><?php _e( 'Button Background (Hover)', 'psts' ) ?></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_hover_bg]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_hover_bg', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_hover_bg_selected]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_hover_bg_selected', '', $styles ); ?>"/></td>
						<td><input type="text" name="psts[checkout_style][pricing_style_button_hover_bg_featured]" class="color-picker" value="<?php echo self::get_style( 'pricing_style_button_hover_bg_featured', '', $styles ); ?>"/></td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Custom CSS', 'psts' ); ?></h3>
				<p class="description clear"><?php esc_html_e( 'You can use this box to enter your CSS, customise your theme or use a custom CSS plugin. You can avoid altering the CSS of the rest of your site by prefixing all rules you specify with "#prosites-checkout-table". Note: The CSS you specify in this box will be filtered before saving to protect your site. Any non-CSS text will be removed.', 'psts' ); ?></p>
				<textarea class="custom-css" name="psts[checkout_style][pricing_table_custom_css]"><?php echo sprintf( self::get_style( 'pricing_table_custom_css', '', $styles ) ); ?></textarea>

			</div>
		<?php

		}

		public static function get_styles_from_options() {
			global $psts;

			$options = $checkout_style = $psts->get_setting( 'checkout_style', array() );

			$style = '';

			foreach ( $options as $key => $value ) {

				switch ( $key ) {

					case 'pricing_table_custom_css':
						$style .= ! empty( $value ) ? $value : '';
						break;

					case 'pricing_style_column_bg';
						break;
					case 'pricing_style_column_bg_selected';
						break;
					case 'pricing_style_column_bg_featured';
						break;

					case 'pricing_style_border_color';
						break;
					case 'pricing_style_border_color_selected';
						break;
					case 'pricing_style_border_color_featured';
						break;

					case 'pricing_style_border_width';
						break;
					case 'pricing_style_border_width_selected';
						break;
					case 'pricing_style_border_width_featured';
						break;

					case 'pricing_style_title_color';
						break;
					case 'pricing_style_title_color_selected';
						break;
					case 'pricing_style_title_color_featured';
						break;

					case 'pricing_style_title_bg';
						break;
					case 'pricing_style_title_bg_selected';
						break;
					case 'pricing_style_title_bg_featured';
						break;

					case 'pricing_style_price_color';
						break;
					case 'pricing_style_price_color_selected';
						break;
					case 'pricing_style_price_color_featured';
						break;

					case 'pricing_style_price_bg';
						break;
					case 'pricing_style_price_bg_selected';
						break;
					case 'pricing_style_price_bg_featured';
						break;

					case 'pricing_style_subtitle_color';
						break;
					case 'pricing_style_subtitle_color_selected';
						break;
					case 'pricing_style_subtitle_color_featured';
						break;

					case 'pricing_style_subtitle_bg';
						break;
					case 'pricing_style_subtitle_bg_selected';
						break;
					case 'pricing_style_subtitle_bg_featured';
						break;

					case 'pricing_style_features_text_color';
						break;
					case 'pricing_style_features_text_color_selected';
						break;
					case 'pricing_style_features_text_color_featured';
						break;

					case 'pricing_style_features_text_bg';
						break;
					case 'pricing_style_features_text_bg_selected';
						break;
					case 'pricing_style_features_text_bg_featured';
						break;

					case 'pricing_style_features_alt_text_color';
						break;
					case 'pricing_style_features_alt_text_color_selected';
						break;
					case 'pricing_style_features_alt_text_color_featured';
						break;

					case 'pricing_style_features_alt_bg';
						break;
					case 'pricing_style_features_alt_bg_selected';
						break;
					case 'pricing_style_features_alt_bg_featured';
						break;

					case 'pricing_style_button_container';
						break;
					case 'pricing_style_button_container_selected';
						break;
					case 'pricing_style_button_container_featured';
						break;

					case 'pricing_style_button_text_color';
						break;
					case 'pricing_style_button_text_color_selected';
						break;
					case 'pricing_style_button_text_color_featured';
						break;

					case 'pricing_style_button_bg';
						break;
					case 'pricing_style_button_bg_selected';
						break;
					case 'pricing_style_button_bg_featured';
						break;

					case 'pricing_style_button_hover_text_color';
						break;
					case 'pricing_style_button_hover_text_color_selected';
						break;
					case 'pricing_style_button_hover_text_color_featured';
						break;

					case 'pricing_style_button_hover_bg';
						break;
					case 'pricing_style_button_hover_bg_selected';
						break;
					case 'pricing_style_button_hover_bg_featured';
						break;

				}

			}
			
			return $style;
		}

		public static function get_style( $key, $default = '', $options = false ) {
			global $psts;
			if ( empty( $options ) ) {
				$options = $checkout_style = $psts->get_setting( 'checkout_style', array() );
			}

			if ( isset( $options[ $key ] ) ) {
				return $options[ $key ];
			} else {
				return $default;
			}
		}


	}
}