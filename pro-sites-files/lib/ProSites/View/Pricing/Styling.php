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
							
							<!--<input type="checkbox" name="psts[coupons_enabled]" value="1" --><?php //checked( $coupons_checked, 'enabled' ); ?><!-- />-->
						</td>
					</tr>

				</table>

			</div>
			<?php

		}





	}
}