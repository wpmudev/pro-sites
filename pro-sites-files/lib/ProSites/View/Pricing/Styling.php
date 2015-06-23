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

			$style .= self::get_column_background( $options );
			$style .= self::get_column_borders( $options );
			$style .= self::get_column_title( $options );
			$style .= self::get_column_price( $options );


			$value = self::get_style( 'pricing_style_subtitle_color', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_subtitle_color_selected', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_subtitle_color_featured', '', $options );
			if( ! empty( $value ) ) {

			}


			$value = self::get_style( 'pricing_style_subtitle_bg', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_subtitle_bg_selected', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_subtitle_bg_featured', '', $options );
			if( ! empty( $value ) ) {

			}


			$value = self::get_style( 'pricing_style_features_text_color', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_features_text_color_selected', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_features_text_color_featured', '', $options );
			if( ! empty( $value ) ) {

			}


			$value = self::get_style( 'pricing_style_features_text_bg', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_features_text_bg_selected', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_features_text_bg_featured', '', $options );
			if( ! empty( $value ) ) {

			}


			$value = self::get_style( 'pricing_style_features_alt_text_color', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_features_alt_text_color_selected', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_features_alt_text_color_featured', '', $options );
			if( ! empty( $value ) ) {

			}


			$value = self::get_style( 'pricing_style_features_alt_bg', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_features_alt_bg_selected', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_features_alt_bg_featured', '', $options );
			if( ! empty( $value ) ) {

			}


			$value = self::get_style( 'pricing_style_button_container', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_button_container_selected', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_button_container_featured', '', $options );
			if( ! empty( $value ) ) {

			}


			$value = self::get_style( 'pricing_style_button_text_color', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_button_text_color_selected', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_button_text_color_featured', '', $options );
			if( ! empty( $value ) ) {

			}


			$value = self::get_style( 'pricing_style_button_bg', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_button_bg_selected', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_button_bg_featured', '', $options );
			if( ! empty( $value ) ) {

			}


			$value = self::get_style( 'pricing_style_button_hover_text_color', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_button_hover_text_color_selected', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_button_hover_text_color_featured', '', $options );
			if( ! empty( $value ) ) {

			}


			$value = self::get_style( 'pricing_style_button_hover_bg', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_button_hover_bg_selected', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_style_button_hover_bg_featured', '', $options );
			if( ! empty( $value ) ) {

			}

			$value = self::get_style( 'pricing_table_custom_css', '', $options );
			$style .= ! empty( $value ) ? $value : '';

			return $style;
		}

		private static function add_class_style( $css, $value, $option = '' ) {

			$replace = '';

			switch( $option ) {
				case 'selected':
					$replace = '.pricing-column.chosen-plan';
					break;
				case 'featured':
					$replace = '.pricing-column.featured';
					break;
			}

			if( ! empty( $replace ) ) {
				$css = str_replace( '.pricing-column', $replace, $css );
			}

			$css = str_replace( 'XXX', $value, $css );

			return $css;
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

		private static function get_column_background( $options ) {
			$style = '';

			$the_style = "
					.pricing-column .title,
					.pricing-column .summary,
					.pricing-column .sub-title,
					.pricing-column .summary,
					.pricing-column .button-box,
					.pricing-column li ul.feature-section,
					.pricing-column .feature,
					 .pricing-column .feature.alternate {
						background: XXX;
					}";
			$value = self::get_style( 'pricing_style_column_bg', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_style, $value );
				$style .="
					.pricing-column .button-box.no-button,
					.pricing-column:first-child .title,
					.pricing-column:first-child .summary {
						background: none;
					}
					.pricing-column:first-child .summary .period-selector {
						background: {$value};
					}
				";
			}

			$value = self::get_style( 'pricing_style_column_bg_featured', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_style, $value, 'featured' );
			}

			$value = self::get_style( 'pricing_style_column_bg_selected', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_style, $value, 'selected' );
			}

			return $style;
		}

		private static function get_column_borders( $options ) {
			global $psts;

			$layout = $psts->get_setting( 'pricing_table_layout', 'option1' );


			$style = '';

			$the_css = "
				.pricing-column .period-selector select,
				.pricing-column .sub-title,
				.pricing-column .title,
				.pricing-column .summary,
				.pricing-column .summary.no-periods,
				.pricing-column .summary .period-selector,
				.pricing-column .sub-title,
				.pricing-column .feature-section,
				.pricing-column:first-child .feature-section,
				.pricing-column .button-box {
				    border-color: XXX;
				}
			";

			if( 'option1' == $layout ) {
				$the_css .= "
					.pricing-column .title:after {
						border-bottom-color: XXX;
					}
				";
			}

			$value = self::get_style( 'pricing_style_border_color', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value );
			}

			$value = self::get_style( 'pricing_style_border_color_featured', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value, 'featured' );
			}

			$value = self::get_style( 'pricing_style_border_color_selected', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value, 'selected' );
			}

			$the_css = "
				.pricing-column .period-selector select,
				.pricing-column .sub-title,
				.pricing-column .title,
				.pricing-column .summary,
				.pricing-column .summary.no-periods,
				.pricing-column .summary .period-selector,
				.pricing-column .sub-title,
				.pricing-column .feature-section,
				.pricing-column:first-child .feature-section,
				.pricing-column .button-box {
				    border-width: XXXpx;
				}
			";

			$value = self::get_style( 'pricing_style_border_width', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value );
			}

			$value = self::get_style( 'pricing_style_border_width_selected', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value, 'featured' );
			}

			$value = self::get_style( 'pricing_style_border_width_featured', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value, 'selected' );
			}

			return $style;
		}

		private static function get_column_title( $options ) {

			$style = '';

			$the_css = "
				.pricing-column .title {
				    color: XXX;
				}
			";

			$value = self::get_style( 'pricing_style_title_color', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value );
			}

			$value = self::get_style( 'pricing_style_title_color_featured', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value, 'featured' );
			}

			$value = self::get_style( 'pricing_style_title_color_selected', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value, 'selected' );
			}

			$the_css = "
				.pricing-column .title {
				    background: XXX;
				}
			";

			$value = self::get_style( 'pricing_style_title_bg', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value );
			}

			$value = self::get_style( 'pricing_style_title_bg_featured', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value, 'featured' );
			}

			$value = self::get_style( 'pricing_style_title_bg_selected', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value, 'selected' );
			}

			return $style;

		}

		private static function get_column_price( $options ) {

			$style = '';

			$the_css = "
				.pricing-column .price {
				    color: XXX;
				}
			";

			$value = self::get_style( 'pricing_style_price_color', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value );
			}

			$value = self::get_style( 'pricing_style_price_color_featured', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value, 'featured' );
			}

			$value = self::get_style( 'pricing_style_price_color_selected', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value, 'selected' );
			}

			$the_css = "
				.pricing-column .price {
				    background: XXX !important;
				}
			";

			$value = self::get_style( 'pricing_style_price_bg', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value );
			}

			$value = self::get_style( 'pricing_style_price_bg_featured', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value, 'featured' );
			}

			$value = self::get_style( 'pricing_style_price_bg_selected', '', $options );
			if( ! empty( $value ) ) {
				$style .= self::add_class_style( $the_css, $value, 'selected' );
			}


			return $style;

		}

	}
}