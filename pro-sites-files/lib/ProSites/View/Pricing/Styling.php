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
				<h3><?php esc_html_e( 'Table Layout', 'psts'); ?></h3>
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
				<p class="description clear"><?php esc_html_e( 'Select the base layout for the pricing tables. This will provide the initial CSS for your pricing tables.', 'psts'); ?><br />
					<?php esc_html_e( 'For more detailed customisation you will need to alter the CSS in your theme or use a custom CSS plugin.', 'psts'); ?></p>
				<h3><?php esc_html_e( 'Colors', 'psts'); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php _e( 'Primary', 'psts' ) ?></th>
						<td>
							<input type="text" name="psts[pricing_table_primary_color]" class="color-picker" value="<?php echo $psts->get_setting( 'pricing_table_primary_color', '' ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Feature Row Background', 'psts' ) ?></th>
						<td>
							<table>
								<tr>
									<td><p><?php _e( 'Odd rows', 'psts' ); ?></p><input type="text" name="psts[pricing_table_alternate_row_color]" class="color-picker" value="<?php echo $psts->get_setting( 'pricing_table_alternate_row_color', '' ); ?>" /></td>
									<td><p><?php _e( 'Event rows', 'psts' ); ?></p><input type="text" name="psts[pricing_table_even_row_color]" class="color-picker" value="<?php echo $psts->get_setting( 'pricing_table_even_row_color', '' ); ?>" /></td>
								</tr>
							</table>


						</td>
					</tr>

				</table>

			</div>
			<?php

		}

		public static function get_styles_from_options() {
			global $psts;
			$layout_option = $psts->get_setting( 'pricing_table_layout', 'option1' );

			$style = '';

			$style .= self::primary_color_style( $layout_option );
			$style .= self::feature_row_style( $layout_option );

			return $style;
		}

		private static function primary_color_style( $layout_option ) {
			global $psts;

			$style = '';

			$color = $psts->get_setting( 'pricing_table_primary_color', '' );

			if( ! empty( $color ) ) {

				switch( $layout_option ) {

					case 'option1':
							$style .= "
								.pricing-column .title,
								.pricing-column .title:after,
								.pricing-column .summary,
							    .pricing-column .summary.no-periods,
							    .pricing-column .summary .period-selector,
							    .pricing-column .sub-title,
							    .pricing-column .feature-section,
							    .pricing-column:first-child .feature-section,
							    .pricing-column .button-box,
							    .tax-checkout-notice,
							    .tax-checkout-evidence
								{
									border-color: {$color};
								}

							";
						break;

				}


			}

			return $style;

		}


		private static function feature_row_style( $layout_option ) {
			global $psts;

			$style = '';

			// ALTERNATE (Odd)
			$color = $psts->get_setting( 'pricing_table_alternate_row_color', '' );
			if( ! empty( $color ) ) {

				switch( $layout_option ) {

					case 'option1':
						$style .= "
							    .pricing-column .feature.alternate
								{
									background-color: {$color};
								}
							";
						break;

				}
			}

			// NORMAL (Even)
			$color = $psts->get_setting( 'pricing_table_even_row_color', '' );
			if( ! empty( $color ) ) {

				switch( $layout_option ) {

					case 'option1':
						$style .= "
							    .pricing-column .feature
								{
									background-color: {$color};
								}

							";
						break;

				}
			}

			return $style;

		}



	}
}