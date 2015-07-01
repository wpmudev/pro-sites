<?php

if ( ! class_exists( 'ProSites_View_Pricing' ) ) {
	class ProSites_View_Pricing {

		public static function get_page_name() {
			return __( 'Pro Sites Pricing Table', 'psts' );
		}

		public static function get_menu_name() {
			return __( 'Pricing Tables', 'psts' );
		}

		public static function get_description() {
			return __( 'You can enable plans & pricing and feature table settings here. ', 'psts' );
		}

		public static function get_page_slug() {
			return 'psts-pricing-settings';
		}

		public static function render_page() {

			if ( ! is_super_admin() ) {
				echo "<p>" . __( 'Nice Try...', 'psts' ) . "</p>"; //If accessed properly, this message doesn't appear.
				return false;
			}

			// Might move this to a controller, not sure if needed yet.
			ProSites_Model_Pricing::process_form();

			?>
			<form method="post" action="">
				<?php

				$page_header_options = array(
					'title'       => self::get_page_name(),
					'desc'        => self::get_description(),
					'page_header' => true,
				);

				$options = array(
					'header_save_button'  => true,
					'section_save_button' => true,
					'nonce_name'          => 'psts_pricing_settings',
					'button_name'         => 'pricing',
				);

				ProSites_Helper_Tabs_Pricing::render( get_class(), $page_header_options, $options );

				?>

			</form>
		<?php
		}

		/**
		 * Pricing Table
		 *
		 * @return string
		 */
		public static function render_tab_pricing_table() {
			global $psts;

			$active_tab = ProSites_Helper_Tabs_Pricing::get_active_tab();
			ProSites_Helper_Settings::settings_header( $active_tab );

			//			$class_name = 'ProSites_Gateway_2Checkout';
			$featured_level      = $psts->get_setting( 'featured_level' );
			$plans_table_enabled = $psts->get_setting( 'plans_table_enabled', 'enabled' );

			$coupons_enabled       = $psts->get_setting( 'coupons_enabled' );
			$highlight_featured    = $psts->get_setting( 'psts_checkout_show_featured' );
			$checked               = 'enabled' == $plans_table_enabled ? 'enabled' : 'disabled';
			$coupons_checked       = 'enabled' == $coupons_enabled ? 'enabled' : 'disabled';
			$show_featured_checked = 'enabled' == $highlight_featured ? 'enabled' : 'disabled';

			$pricing_gateways_style     = $psts->get_setting( 'pricing_gateways_style', 'tabbed' );
			$pricing_table_period_style = $psts->get_setting( 'pricing_table_period_style' );

			?>
			<input type="hidden" name="pricing_settings" value="<?php echo esc_attr( $active_tab['tab_key'] ); ?>"/>
			<table class="form-table">
				<tr>
					<th scope="row"><?php _e( 'Enable Pricing Table', 'psts' ) ?></th>
					<td>
						<input type="checkbox" name="psts[plans_table_enabled]" value="1" <?php checked( $checked, 'enabled' ); ?> />
					</td>
				</tr>

				<?php
				//					$option = '<tr>
				//						<th scope="row">' . __ ( 'Period Style', 'psts' ) .
				//							'<br/><span class="description" style="font-weight:normal; color:#888; ">' . __( 'Select how users will select the plan period.', 'psts' ) . '</span></th>
				//						<td>
				//							<select name="psts[pricing_table_period_style]" class="chosen">
				//								<option
				//									value="dropdown"' . selected( $pricing_table_period_style, 'dropdown', false ) . '>' . __( 'Drop-down list', 'psts' ) . '</option>
				//								<option
				//									value="radio"' . selected( $pricing_table_period_style, 'radio', false ) . '>' . __( 'Radio button', 'psts' ) . '</option>
				//								<option
				//									value="raw"' . selected( $pricing_table_period_style, 'raw', false ) . '>' . __( 'Raw', 'psts' ) . '</option>
				//							</select>
				//						</td>
				//					</tr>';
				//					echo $option;
				?>
				<!-- @todo THIS NEEDS TO BE IMPLEMENTED ASAP -->
				<tr>
					<th scope="row"><?php esc_html_e( 'Period Selector Position', 'psts' ) ?></th>
					<td>
						<label>
							<p><input type="radio" name="psts[pricing_table_period_position]" value="option1" <?php checked( $psts->get_setting( 'pricing_table_period_position', 'option1' ), 'option1' ); ?> />
								<?php esc_html_e( 'First column (Part of table)', 'psts' ); ?></p>
						</label>
						<label>
							<p><input type="radio" name="psts[pricing_table_period_position]" value="option2" <?php checked( $psts->get_setting( 'pricing_table_period_position', 'option1' ), 'option2' ); ?> />
								<?php esc_html_e( 'Above the table', 'psts' ); ?></p>
							<p class="description"><?php esc_html_e( 'For visual purposes, moving the period selector to the top will also remove the first/details column from the table. If the coupons box is attached to the first column it will automatically be moved below the table.', 'psts' ); ?></p>
						</label>
						<!-- <label>
							<p><input type="radio" name="psts[pricing_table_period_position]" value="option3" <?php //checked( $psts->get_setting( 'pricing_table_period_position', 'option1' ), 'option3' ); ?> />
								<?php //esc_html_e( 'Below the table', 'psts' ); ?></p>
						</label> -->
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e( 'Allow Coupons', 'psts' ) ?></th>
					<td>
						<input type="checkbox" name="psts[coupons_enabled]" value="1" <?php checked( $coupons_checked, 'enabled' ); ?> />
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Coupon Position', 'psts' ) ?></th>
					<td>
						<label>
							<p><input type="radio" name="psts[pricing_table_coupon_position]" value="option1" <?php checked( $psts->get_setting( 'pricing_table_coupon_position', 'option1' ), 'option1' ); ?> />
								<?php esc_html_e( 'First column (Part of table)', 'psts' ); ?></p>
						</label>
						<label>
							<p><input type="radio" name="psts[pricing_table_coupon_position]" value="option2" <?php checked( $psts->get_setting( 'pricing_table_coupon_position', 'option1' ), 'option2' ); ?> />
								<?php esc_html_e( 'Below checkout table.', 'psts' ); ?></p>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e( 'Highlight \'Featured\' level', 'psts' ) ?></th>
					<td>
						<input type="checkbox" name="psts[psts_checkout_show_featured]" value="1" <?php checked( $show_featured_checked, 'enabled' ); ?> />
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e( 'Gateways layout', 'psts' ); ?>
						<br/><span class="description" style="font-weight:normal; color:#888; "><?php _e( 'Select how the gateways will be shown.', 'psts' ) ?></span></th>
					<td>
						<select name="psts[pricing_gateways_style]" class="chosen">
							<option value="tabbed"<?php selected( $pricing_gateways_style, 'tabbed' ) ?>><?php _e( 'Tabbed layout', 'psts' ) ?></option>
							<option value="raw"<?php selected( $pricing_gateways_style, 'raw' ) ?>><?php _e( 'Raw HTML layout', 'psts' ) ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e( 'Level Order', 'psts' ) ?>
						<br/><span class="description" style="font-weight:normal; color:#888; "><?php _e( 'Select the order that you want your levels to appear in the pricing and feature tables.', 'psts' ) ?></span></th>
					<td>

						<?php
						$level_list = get_site_option( 'psts_levels' );
						$last_level = ( is_array( $level_list ) ) ? count( $level_list ) : 0;
						$periods    = (array) $psts->get_setting( 'enabled_periods' );

						$default_order = array();
						for ( $i = 1; $i <= $last_level; $i ++ ) {
							$default_order[] = $i;
						}
						$default_order = implode( ',', $default_order );

						$pricing_levels_order = $psts->get_setting( 'pricing_levels_order', $default_order );
						$pricing_levels_order = explode( ',', $pricing_levels_order );

						$remove_pricing_item = false;
						if ( count( $pricing_levels_order ) != count( $level_list ) ) {

							foreach ( $level_list as $level_code => $level ) {

								if ( ! in_array( $level_code, $pricing_levels_order ) && count( $level_list ) > count( $pricing_levels_order ) ) {
									$pricing_levels_order[] = $level_code;
								} else {
									$remove_pricing_item = true;
								}

							}

						}

						// Make sure the level doesn't show up if its been deleted.
						if( $remove_pricing_item ) {
							foreach( $pricing_levels_order as $item_key => $order_item ) {
								if( ! in_array( $order_item, array_keys( $level_list ) ) ) {
									unset( $pricing_levels_order[ $item_key ] );
								}
							}
						}


						// define the columns to display, the syntax is 'internal name' => 'display name'
						$posts_columns = array(
							'level'       => array(
								'title' => __( 'Level', 'psts' ),
								'width' => '35px',
							),
							'name'        => array(
								'title' => __( 'Name', 'psts' ),
								'width' => '',
							),
							'pricing'     => array(
								'title' => __( 'Pricing', 'psts' ),
								'width' => '',
							),
							'is_featured' => array(
								'title' => __( 'Featured Level', 'psts' ),
								'width' => '',
							),
						);
						?>

						<table width="100%" cellpadding="3" cellspacing="3" class="widefat pricing-table" id="prosites-level-list">
							<thead>
								<tr>
								<?php
								foreach ( $posts_columns as $col ) {
									$style = ! empty( $col['width'] ) ? ' style="max-width:' . $col['width'] . '"' : '';
									echo '<th scope="col"' . $style . '>' . esc_html( $col['title'] ) . '</th>';
								}

								?>
								</tr>
							</thead>
							<tbody id="the-list">
								<?php
								if ( is_array( $level_list ) && count( $level_list ) ) {
									$bgcolor = $class = '';
									foreach ( $pricing_levels_order as $order ) {
										$level_code = $order;
										$level = !empty( $level_list[ $order ] ) ? $level_list[ $order ] : '';
										if( empty( $level ) ) {
											continue;
										}
										$class = ( 'alternate' == $class ) ? '' : 'alternate';
										$level      = $level_list[ $order ];
										$class      = ( 'alternate' == $class ) ? '' : 'alternate';

										echo '<tr class="' . $class . ' blog-row" data-level="' . $level_code . '">';

										foreach ( $posts_columns as $column_name => $column_display_name ) {
											switch ( $column_name ) {
												case 'level':
													?>
													<td scope="row" style="padding-left: 20px;">
															<strong><?php echo $level_code; ?></strong>
														</td>
													<?php
													break;

												case 'name':
													?>
													<td scope="row">
															<strong><?php echo esc_html( $level['name'] ); ?></strong>
														</td>
													<?php
													break;

												case 'pricing':

													$period_1  = ( isset( $level['price_1'] ) ) ? $psts->format_currency() . number_format( (float) $level['price_1'], 2, '.', '' ) : '';
													$period_3  = ( isset( $level['price_3'] ) ) ? $psts->format_currency() . number_format( (float) $level['price_3'], 2, '.', '' ) : '';
													$period_12 = ( isset( $level['price_12'] ) ) ? $psts->format_currency() . number_format( (float) $level['price_12'], 2, '.', '' ) : '';

													echo '<td>' . $period_1 . ' / ' . $period_3 . ' / ' . $period_12 . '</td>';

													break;

												case 'is_featured':
													?>
													<td scope="row">
															<?php $is_featured = $featured_level == $level_code ? 1 : 0; ?>
														<input value="<?php echo esc_attr( $level_code ); ?>" name="psts[featured_level]" type="radio" <?php echo checked( $is_featured, 1 ); ?> />
														</td>
													<?php
													break;

											}
										}
										?>
									</tr>
								<?php
									}
									?>
										<input type="hidden" name="psts[pricing_levels_order]" value="<?php echo implode( ',', $pricing_levels_order ); ?>" />
									<?php
								} else {
									?>
									<tr style='background-color: <?php echo $bgcolor; ?>'>
									<td colspan="6"><?php _e( 'No levels yet.', 'psts' ) ?></td>
								</tr>
								<?php
								} // end if levels
								?>

							</tbody>
						</table>

					</td>
				</tr>
			</table>

			<?php
			//			$gateway = new ProSites_Gateway_2Checkout();
			//			echo $gateway->settings();
		}

		/**
		 * Pricing Table
		 *
		 * @return string
		 */
		public static function render_tab_comparison_table() {
			global $psts;

			$active_tab = ProSites_Helper_Tabs_Pricing::get_active_tab();
			ProSites_Helper_Settings::settings_header( $active_tab );

			$plans_table_enabled = $psts->get_setting( 'comparison_table_enabled' );
			$checked             = 'enabled' == $plans_table_enabled ? 'enabled' : 'disabled';
			$level_list          = get_site_option( 'psts_levels' );
			$last_level          = ( is_array( $level_list ) ) ? count( $level_list ) : 0;

			$table_settings  = ProSites_Model_Pricing::load_feature_settings();
			$enabled_modules = $psts->get_setting( 'modules_enabled', array() );

			?>
			<input type="hidden" name="pricing_settings" value="<?php echo esc_attr( $active_tab['tab_key'] ); ?>"/>
			<table class="form-table">
				<tr>
					<th scope="row"><?php _e( 'Enable Feature Table', 'psts' ) ?></th>
					<td>
						<input type="checkbox" name="psts[comparison_table_enabled]" value="1" <?php checked( $checked, 'enabled' ); ?> />
					</td>
				</tr>

				<!-- MODULE TABLE -->
				<tr id="module-comparison-table">
					<td colspan="2">
						<div class="form-description"><?php _e( 'Use the form below to build your feature comparison table.', 'psts' ); ?></div>
						<div class="level-select-bar">
							<?php
							//								_e( 'Select level: ', 'psts' );
							if ( is_array( $level_list ) && count( $level_list ) ) {
								foreach ( $level_list as $key => $level ) {
									$class = 1 == $key ? 'selected' : '';

									echo '<strong><a data-id="' . $key . '" class="' . $class . '">' . $level['name'] . '</a></strong>';
									if ( $key != ( count( $level_list ) ) ) {
										echo ' | ';
									}

								}
							}
							?>
							<input type="hidden" name="current_level" value="1"/>
						</div>

						<?php
						// define the columns to display, the syntax is 'internal name' => 'display name'
						$posts_columns = array(
							'order'       => array(
								'title' => __( '#', 'psts' ),
								'width' => '8px',
								'class' => '',
							),
							'visible'     => array(
								'title' => __( 'Visible', 'psts' ),
								'width' => '36px',
								'class' => '',
							),
							'name'        => array(
								'title' => __( 'Name', 'psts' ),
								'small' => __( '(double-click to change)' ),
								'width' => '150px',
								'class' => '',
							),
							'description' => array(
								'title' => __( 'Description', 'psts' ),
								'small' => __( '(double-click to change)' ),
								'width' => '',
								'class' => '',
							),
							'tick_cross'  => array(
								'title' => __( 'Indicator', 'psts' ),
								'small' => __( '(for selected level)', 'psts' ),
								'width' => '60px',
								'class' => 'level-settings',
							),
							'custom'      => array(
								'title' => __( 'Custom text', 'psts' ),
								'small' => __( '(for selected level)', 'psts' ),
								'width' => '',
								'class' => '',
							),
						);

						$status_array_normal = array(
							'tick'  => '&#x2713',
							'cross' => '&#x2718',
						);

						$status_array_module = array(
							'module'  => __( 'Level: %s', 'psts' ),
							'inverse' => __( 'Invert: %s', 'psts' ),
						);

						$hover_actions = array(
							'edit'  => __( 'edit', 'psts' ),
							'save'  => __( 'change', 'psts' ),
							'reset' => __( 'reset', 'psts' ),
						);

						$feature_order = array();

						?>

						<table width="100%" cellpadding="3" cellspacing="3" class="widefat feature-table" id="prosites-level-list">
							<thead>
								<tr>
								<?php
								foreach ( $posts_columns as $col ) {
									$style = ! empty( $col['width'] ) ? ' style="max-width:' . $col['width'] . '"' : '';
									$class = ! empty( $col['class'] ) ? ' class="' . $col['class'] . '"' : '';
									$small = ! empty( $col['small'] ) ? ' <small>' . esc_html( $col['small'] ) . '</small>' : '';
									echo '<th scope="col"' . $style . $class . '>' . esc_html( $col['title'] ) . $small . '</th>';
								}

								?>
								</tr>
							</thead>
							<tbody id="the-list">
								<?php
								if ( ! empty( $table_settings ) ) {
									$bgcolor       = $class = '';
									$count         = 0;
									$modules_array = array();
									foreach ( $table_settings as $key => $setting ) {
										if ( 'modules' == $key || 'feature_order' == $key || 'levels' == $key ) {
											continue;
										}
										// don't show disabled modules
										if ( isset( $setting['module'] ) && ! in_array( $setting['module'], $enabled_modules ) ) {
											continue;
										}

										$feature_order[] = $key;

										$count += 1;
										$level_code = 0;
										//										$level = $level_list[ $order ];
										$class = $count % 2 == 0 ? '' : 'alternate';
										$class .= empty( $setting['module'] ) ? ' custom' : ' module';

										echo '<tr class="' . $class . ' blog-row" data-level="' . $level_code . '">';

										foreach ( $posts_columns as $column_name => $column ) {
											switch ( $column_name ) {
												case 'order':
													$style = ! empty( $column['width'] ) ? ' max-width:' . $column['width'] . ';' : '';
													?>
													<td scope="row" style="padding-left: 10px; <?php echo $style; ?>" class="order-col">
														<div class="position"><?php echo $count; ?></div>
														<?php
														if ( isset( $setting['custom'] ) ) {
															echo '<input type="hidden" name="psts[feature_table][' . $key . '][custom]" value="' . esc_attr( $setting['custom'] ) . '" />';
															echo '<a class="delete"><span class="dashicons dashicons-trash"></span></a>';
														}
														if ( isset( $setting['module'] ) ) {
															$modules_array[] = $setting['module'];
															echo '<input type="hidden" name="psts[feature_table][' . $key . '][module]" value="' . esc_attr( $setting['module'] ) . '" />';
															echo '<input type="hidden" name="psts[feature_table][' . $key . '][module_key]" value="' . $key . '" />';
														}
														?>
													</td>
													<?php
													break;
												case 'visible':
													?>
													<td scope="row" style="padding-left: 20px;">
														<?php
														if ( ! isset( $setting['visible'] ) ) {
															$setting['visible'] = false;
														}
														?>
														<input type="checkbox" name="psts[feature_table][<?php echo $key; ?>][visible]" value="1" <?php checked( $setting['visible'] ) ?>>
													</td>
													<?php
													break;

												case 'name':
													$original_value = '';
													if ( isset( $setting['module'] ) && ! empty( $setting['module'] ) ) {
														if ( method_exists( $setting['module'], 'get_name' ) ) {
															$original_value = call_user_func( $setting['module'] . '::get_name' );
														}
													}
													if ( isset( $setting['custom'] ) && ! empty( $setting['custom'] ) ) {
														$original_value = $setting['name'];
													}
													?>
													<td scope="row">
														<div class="text-item"><?php echo esc_html( $setting['name'] ); ?></div>
														<div class="edit-box" style="display:none">
															<input class="editor" type="text" name="psts[feature_table][<?php echo $key; ?>][name]" value="<?php echo esc_html( $setting['name'] ); ?>"/><br/>
															<span><a class="save-link"><?php echo esc_html( $hover_actions['save'] ); ?></a> <a style="margin-left: 10px;" class="reset-link"><?php echo esc_html( $hover_actions['reset'] ); ?></a></span>
														</div>
														<input type="hidden" value="<?php echo esc_html( $original_value ); ?>"/>
													</td>
													<?php
													break;

												case 'description':
													if ( isset( $setting['module'] ) && ! empty( $setting['module'] ) ) {
														if ( method_exists( $setting['module'], 'get_description' ) ) {
															$original_value = call_user_func( $setting['module'] . '::get_description' );
														}
													}
													if ( isset( $setting['custom'] ) && ! empty( $setting['custom'] ) ) {
														$original_value = $setting['description'];
													}
													?>
													<td scope="row">
														<div class="text-item"><?php echo esc_html( $setting['description'] ); ?></div>
														<div class="edit-box" style="display:none">
															<textarea class="editor" type="text" name="psts[feature_table][<?php echo $key; ?>][description]"><?php echo esc_html( $setting['description'] ); ?></textarea><br/>
															<span><a class="save-link"><?php echo esc_html( $hover_actions['save'] ); ?></a> <a style="margin-left: 10px;" class="reset-link"><?php echo esc_html( $hover_actions['reset'] ); ?></a></span>
														</div>
														<input type="hidden" value="<?php echo esc_html( $original_value ); ?>"/>
													</td>
													<?php
													break;

												case 'tick_cross':
													?>
													<td scope="row" class="<?php echo esc_attr( $column['class'] ); ?>">
														<?php

														// We're working with level based settings
														if ( is_array( $level_list ) && count( $level_list ) ) {
															foreach ( $level_list as $level_id => $level ) {
																if ( ! empty( $setting['levels'][ $level_id ]['status'] ) && ! is_array( $setting['levels'][ $level_id ]['status'] ) ) {
																	$status       = $setting['levels'][ $level_id ]['status'];
																	$level_status = '';
																	if ( isset( $setting['module'] ) && method_exists( $setting['module'], 'get_level_status' ) ) {
																		$level_status = call_user_func( $setting['module'] . '::get_level_status', $level_id );
																	}
																	if ( ! empty( $setting['module'] ) ) {
																		$chosen_array = $status_array_module;
																		$invert       = 'tick' == $level_status ? 'cross' : 'tick';

																		$chosen_array['module']  = sprintf( $chosen_array['module'], $status_array_normal[ $level_status ] );
																		$chosen_array['inverse'] = sprintf( $chosen_array['inverse'], $status_array_normal[ $invert ] );

																	} else {
																		$chosen_array = $status_array_normal;
																	}
																	if ( ! empty( $setting['module'] ) ) {
																		//																	echo '*hide* ' . $setting['levels'][ $key ]['status'];
																	} else {
																		//																	echo '*hide* Not a module';
																	}
																	?>
																	<!-- Change name... -->
																	<select class="chosen" name="psts[feature_table][<?php echo $key; ?>][levels][<?php echo $level_id; ?>][status]" data-level="level-<?php echo $level_id; ?>[status]">
																    <?php
																    foreach ( $chosen_array as $item_key => $item ) {
																	    echo '<option value="' . esc_attr( $item_key ) . '" ' . selected( $status, $item_key ) . '>' . $item . '</option>';
																    }
																    echo '<option value="none" ' . selected( $status, 'none' ) . '>' . __( 'None', 'psts' ) . '</option>';
																    ?>
																	</select>
																<?php
																} elseif ( isset( $setting['levels'][ $level_id ]['status'] ) && is_array( $setting['levels'][ $level_id ]['status'] ) ) {

																	//																	$new_status = $setting['levels'][ $level_id ]['status'];
																	//																	if( method_exists( $setting[ 'module' ], 'get_level_status' ) ) {
																	//																		$new_status = call_user_func( $setting[ 'module' ] . '::get_level_status', $level_id );
																	//																		$old_status = $setting['levels'][ $level_id ]['status'] ;
																	//																		if( 'none' != $old_status['selection'] ) {
																	//																			$new_status['selection'] = $new_status['value'];
																	//																		} else {
																	//																			$new_status['selection'] = 'none';
																	//																		}
																	//
																	//																	}
																	//																	$keys = array_keys( $new_status );
																	$keys = array_keys( $setting['levels'][ $level_id ]['status'] );

																	foreach ( $keys as $index ) {
																		echo '<input type="hidden" name="psts[feature_table][' . $key . '][levels][' . $level_id . '][status][' . $index . ']" value="' . $setting['levels'][ $level_id ]['status'][ $index ] . '" />';
																	}

																	?>

																	<select class="chosen" name="psts[feature_table][<?php echo $key; ?>][levels][<?php echo $level_id; ?>][status][selection]">
																    <?php
																    $value     = $setting['levels'][ $level_id ]['status']['value'];
																    $selection = isset( $setting['levels'][ $level_id ]['status']['selection'] ) ? $setting['levels'][ $level_id ]['status']['selection'] : $value;
																    $selected  = selected( $selection, $value, false );
																    echo '<option value="' . esc_attr( $value ) . '" ' . $selected . '>' . esc_html( $setting['levels'][ $level_id ]['status']['display'] ) . '</option>';
																    echo '<option value="none" ' . selected( $selection, 'none' ) . '>' . __( 'None', 'psts' ) . '</option>';
																    ?>
																	</select>
																<?php
																}
															}
														}

														// There are no level specific settings
														if ( isset( $setting['active'] ) && ( ! empty( $setting['active'] ) || false === $setting['active'] ) ) {
															$module_active = true;

															if ( method_exists( $setting['module'], 'is_active' ) ) {
																$module_active = call_user_func( $setting['module'] . '::is_active' );
															}

															$active_status = array(
																'active'   => array(
																	'title'  => __( 'Active: %s', 'psts' ),
																	'status' => 'tick',
																),
																'inactive' => array(
																	'title'  => __( 'Not active: %s', 'psts' ),
																	'status' => 'cross',
																),
															);

															$value = $setting['active'];
															if ( $module_active ) {
																$option = '<option value="module" ' . selected( $value, 'module', false ) . '>' . sprintf( $active_status['active']['title'], $status_array_normal[ $active_status['active']['status'] ] ) . '</option>';
															} else {
																$option = '<option value="module" ' . selected( $value, 'module', false ) . '>' . sprintf( $active_status['inactive']['title'], $status_array_normal[ $active_status['inactive']['status'] ] ) . '</option>';
															}

															?>
															<select class="chosen" name="psts[feature_table][<?php echo $key; ?>][active]">
															    <?php
															    echo $option;
															    echo '<option value="none" ' . selected( $value, 'none' ) . '>' . __( 'None', 'psts' ) . '</option>';
															    ?>
																</select>
														<?php
														}

														?>
													</td>
													<?php
													break;

												case 'custom':
													?>
													<td scope="row">
														<?php
														$x = '';
														if ( is_array( $level_list ) && count( $level_list ) ) {
															foreach ( $level_list as $level_id => $level ) {
																?>
																<textarea name="psts[feature_table][<?php echo $key; ?>][levels][<?php echo $level_id; ?>][text]"><?php echo esc_html( $setting['levels'][ $level_id ]['text'] ); ?></textarea>
															<?php
															}
														}
														?>
													</td>
													<?php
													break;

											}
										}
										?>
									</tr>
								<?php
										$level_keys = array_keys( $level_list );
										$level_keys = implode( ',', $level_keys );
									}
									?>
										<input type="hidden" name="psts[feature_table][modules]" value="<?php echo implode( ',', $modules_array ); ?>" />
										<input type="hidden" name="psts[feature_table][levels]" value="<?php echo $level_keys; ?>" />
<!--										<input type="hidden" name="psts[pricing_levels_order]" value="--><?php //echo implode( ',' , $pricing_levels_order ); ?><!--" />-->
									<?php
								} else {
									?>
									<tr class='no-features'>
										<td colspan="6"><?php _e( 'No features added yet.', 'psts' ) ?></td>
									</tr>
								<?php
								} // end if levels
								?>

							</tbody>
						</table>

						<?php
						// Add order...
						$feature_order = implode( ',', $feature_order );
						echo '<input type="hidden" name="psts[feature_table][feature_order]" value="' . $feature_order . '" />';
						// Mark for delete...
						echo '<input type="hidden" name="mark_for_delete" value="" />';
						?>

					</td>
				</tr>


				<tr id="add-feature-box">
					<td colspan="2">
						<strong><?php _e( 'Add custom feature', 'psts' ); ?></strong>
						<table id="add-pricing-feature" class="form-table">
							<thead>
							<tr>
								<th><?php _e( 'Name', 'psts' ); ?></th>
								<th><?php _e( 'Description', 'psts' ); ?></th>
								<th><?php _e( 'Custom text', 'psts' ); ?></th>
								<th></th>
							</tr>
							</thead>
							<tbody>
							<tr class="alternate">
								<td>
									<input name="new-feature-name" type="text"/>
									<input name="new-feature-levels" type="hidden" value="<?php echo count( $level_list ); ?>"/>
								</td>
								<td><textarea name="new-feature-description"></textarea></td>
								<td><textarea name="new-feature-text"></textarea></td>
								<td><input type="button" class="button" name="add-feature-button" id="add-feature-button" value="Add"/></td>

							</tr>
							</tbody>
						</table>

					</td>
				</tr>

			</table>
			<?php
			//			$gateway = new ProSites_Gateway_2Checkout();
			//			echo $gateway->settings();

		}

		public static function render_tab_pricing_style() {
			ProSites_View_Pricing_Styling::render_tab_pricing_style();
		}


	}
}