<?php

if ( ! class_exists( 'ProSites_Model_Pricing' ) ) {

	class ProSites_Model_Pricing {

		public static function process_form() {
			global $psts;

			//process form
			if ( isset( $_POST['submit_pricing'] ) || isset( $_POST['submit_pricing_section'] ) || isset( $_POST['submit_pricing_header'] ) ) {

				//check nonce
				check_admin_referer( 'psts_pricing_settings' );

				// get settings
				$old_settings = get_site_option( 'psts_settings' );

				$active_tab =  ProSites_Helper_Tabs_Pricing::get_active_tab();
				$active_tab = $active_tab['tab_key'];

				switch ( $active_tab ) {

					case 'pricing_table':

						if( isset( $_POST['psts']['plans_table_enabled'] ) ) {
							$_POST['psts']['plans_table_enabled'] = 'enabled';
						} else {
							$_POST['psts']['plans_table_enabled'] = 'disabled';
						}

						if( isset( $_POST['psts']['coupons_enabled'] ) ) {
							$_POST['psts']['coupons_enabled'] = 'enabled';
						} else {
							$_POST['psts']['coupons_enabled'] = 'disabled';
						}

						if( isset( $_POST['psts']['psts_checkout_show_featured'] ) ) {
							$_POST['psts']['psts_checkout_show_featured'] = 'enabled';
						} else {
							$_POST['psts']['psts_checkout_show_featured'] = 'disabled';
						}

						if( isset( $_POST['psts']['pricing_gateways_tabbed'] ) ) {
							$_POST['psts']['pricing_gateways_tabbed'] = 'enabled';
						} else {
							$_POST['psts']['pricing_gateways_tabbed'] = 'disabled';
						}

						break;

					case 'comparison_table':

						if( isset( $_POST['psts']['comparison_table_enabled'] ) ) {
							$_POST['psts']['comparison_table_enabled'] = 'enabled';
						} else {
							$_POST['psts']['comparison_table_enabled'] = 'disabled';
						}

						if( isset( $_POST['psts']['feature_table'] ) ) {
							$_POST['psts']['feature_table'] = self::sanitize_post_vars( $_POST['psts']['feature_table'] );
						}

						// Delete custom features
						if( isset( $_POST['mark_for_delete'] ) ) {
							$marked = sanitize_text_field( $_POST['mark_for_delete'] );
							$marked = explode( ',', $marked );
							$marked = array_filter( $marked );

							if( ! empty( $marked ) ) {
								foreach( $marked as $item ) {
									unset( $_POST['psts']['feature_table'][ $item ] );
								}
							}
						}

						break;

					case 'pricing_style':

						// Sanitize the custom CSS

						if( isset( $_POST['psts']['pricing_table_custom_css'] ) ) {
							if ( ! class_exists( 'CSSTidy_Sanitize_WP' ) ) {
								require $psts->plugin_dir . 'lib/external/csstidy/class.csstidy_sanitize_wp.php';
							}
							$_POST['psts']['pricing_table_custom_css'] = CSSTidy_Sanitize_WP::sanitize_css( $_POST['psts']['pricing_table_custom_css'] );
						}


						break;
				}

				if( isset( $_POST['psts'] ) ) {
					$settings = array_merge( $old_settings, apply_filters( 'psts_settings_filter', $_POST['psts'], $active_tab ) );
					update_site_option( 'psts_settings', $settings );
				}

				do_action( 'psts_pricing_settings_process', $active_tab );

				echo '<div id="message" class="updated fade"><p>' . __( 'Pricing Table settings saved!', 'psts' ) . '</p></div>';


			}

		}

		private static function sanitize_post_vars( $array ) {

			$sanitize = array(
				'int' => array( 'visible', 'order'),
				'text' => array( 'name', 'description', 'module', 'custom', 'status', 'type', 'display', 'value', 'text', 'active' ),
			);

			foreach( $array as $key => $value ) {
				if( ! is_array( $value ) ) {
					if( in_array( $key, $sanitize['int'] ) ) {
						$array[ $key ] = (int) $value;
					}
					if( in_array( $key, $sanitize['text'] ) ) {
						$array[ $key ] = stripslashes( sanitize_text_field( $value ) );
					}
				} else {
					$value = self::sanitize_post_vars( $value );
					$array[ $key ] = $value;
				}
			}

			return $array;

		}

		public static function load_feature_settings() {
			global $psts;

			$level_list = get_site_option( 'psts_levels' );
			$level_keys = array_keys( $level_list );
			$enabled_modules = $psts->get_setting( 'modules_enabled', array() );

			$table_settings = $psts->get_setting( 'feature_table', array() );

			$configured_modules = isset( $table_settings['modules'] ) ? $table_settings['modules'] : '';
			$configured_modules = explode( ',', $configured_modules );

			$non_features = array(
				'modules',
				'levels',
				'feature_order',
			);

			// clean up if the module is deactivated
			foreach( $configured_modules as $key => $module ) {
				if( ! in_array( $module, $enabled_modules ) ) {
					unset( $configured_modules[$key] );
				}
			}

			$level_defaults = array(
				'module' => array(
					'status' => 'module',
					'text' => '',
				),
				'custom' => array(
					'status' => 'none',
					'text' => '',
				),
				'sitewide' => array(
					'text' => '',
				)
			);

			// validate levels
			foreach( $table_settings as $feature_key => $feature ) {

				if( in_array( $feature_key, $non_features ) ) {
					continue;
				}

				$type = '';
				if( isset( $feature['custom'] ) ) {
					$type = 'custom';
				}
				if( isset( $feature['module'] ) ) {
					$type = 'module';
				}
				if( isset( $feature['active'] ) ) {
					$type = 'sitewide';
				}


				if( isset( $feature['levels'] ) ) {

					// Add or remove levels
					if( count( $feature['levels'] ) != count( $level_keys ) ) {

						// Add missing
						if( count( $feature['levels'] ) < count( $level_keys ) ) {

							$feature_level_keys = array_keys( $feature['levels'] );
							foreach( $level_keys as $l_key ) {
								if( ! in_array( $l_key, $feature_level_keys ) ) {

									switch( $type ) {
										case 'module':
											if( $type && method_exists( $feature[ 'module' ], 'get_level_status' ) ) {
												$status = call_user_func( $feature[ 'module' ] . '::get_level_status', $l_key );
												$status = is_array( $status ) ? $status : 'module';
											} else {
												$status = $level_defaults['module']['status'];
											}
											$text = $level_defaults['module']['text'];
											break;
										case 'custom':
											$status = $level_defaults['custom']['status'];
											$text = $level_defaults['module']['text'];
											break;
										case 'sitewide':
											$status = false;
											$text = $level_defaults['module']['text'];
											break;
									}

									$table_settings[ $feature_key ]['levels'][ $l_key ] = array();
									if( ! empty( $status ) ) {
										$table_settings[ $feature_key ]['levels'][ $l_key ]['status'] = $status;
									}
									$table_settings[ $feature_key ]['levels'][ $l_key ]['text'] = $text;

								}
							}

						} else {
							// Remove excess
							$new_array = array();

							foreach( $feature['levels'] as $f_key => $f_value ) {
								if( in_array( $f_key, $level_keys ) ) {
									$new_array[ $f_key ] = $f_value;
								}
							}

							$table_settings[ $feature_key ]['levels'] = $new_array;
						}
					}

					// Fill missing bits
					foreach( $feature['levels'] as $key => $level ) {
						switch ( $type ) {
							case 'module':
								if( ! isset( $level['status'] ) ) {
									if( $type && method_exists( $feature[ 'module' ], 'get_level_status' ) ) {
										$status = call_user_func( $feature[ 'module' ] . '::get_level_status', $key );
										$status = is_array( $status ) ? $status : 'module';
									} else {
										$status = $level_defaults['module']['status'];
									}

									$table_settings[ $feature_key ]['levels'][ $key ]['status'] = $status;
								}
								if( is_array( $table_settings[ $feature_key ]['levels'][ $key ]['status'] ) ) {
									$new_status = $table_settings[ $feature_key ]['levels'][ $key ]['status'];
									if( method_exists( $feature[ 'module' ], 'get_level_status' ) ) {
										$new_status = call_user_func( $feature[ 'module' ] . '::get_level_status', $key );
										$old_status = $table_settings[ $feature_key ]['levels'][ $key ]['status'];
										if( 'none' != $old_status['selection'] ) {
											$new_status['selection'] = $new_status['value'];
										} else {
											$new_status['selection'] = 'none';
										}
									}
									$table_settings[ $feature_key ]['levels'][ $key ]['status'] = $new_status;
								}
								if( ! isset( $level['text'] ) ) {
									$table_settings[ $feature_key ]['levels'][ $key ]['text'] = $level_defaults[ $type ]['text'];
								}
								break;
							case 'custom':
								if( ! isset( $level['status'] ) ) {
									$table_settings[ $feature_key ]['levels'][ $key ]['status'] = $level_defaults[ $type ]['status'];
								}
								if( ! isset( $level['text'] ) ) {
									$table_settings[ $feature_key ]['levels'][ $key ]['text'] = $level_defaults[ $type ]['text'];
								}
								break;
							case 'sitewide':
								if( isset( $level['status'] ) ) {
									unset( $table_settings[ $feature_key ]['levels'][ $key ]['status'] );
								}
								if( ! isset( $level['text'] ) ) {
									$table_settings[ $feature_key ]['levels'][ $key ]['text'] = $level_defaults[ $type ]['text'];
								}
								break;
						}

					}


				}

			}

			if( ! empty( $enabled_modules ) ) {
				foreach( $enabled_modules as $module ) {

					// Skip certain modules
					if( method_exists( $module, 'hide_from_pricing_table' ) ) {
						if( call_user_func( $module . '::hide_from_pricing_table' ) ) {
							continue;
						}
					}

					if( ! in_array( $module, $configured_modules ) ) {

						// Set basic config
						$name = call_user_func( $module . '::get_name' );
						$feature = array(
							'visible' => false,
							'name' => $name,
							'description' => call_user_func( $module . '::get_description' ),
							'module' => $module,
							'order' => 0,
						);

						$level_settings = array();

						$no_status = true;
						if( ! empty( $level_list ) ) {
							foreach( $level_list as $level_code => $level ) {

								$level_settings[ $level_code ] = array();

								if( method_exists( $module, 'get_level_status') ) {
									$level_settings[ $level_code ]['status'] = call_user_func( $module . '::get_level_status', $level_code );
									$no_status = empty( $level_settings[ $level_code ]['status'] ) ? true : false;
								} else {
									$level_settings[ $level_code ]['status'] = '';
								}

								$level_settings[ $level_code ]['text'] = 'Level ' . $level_code;
//								$level_settings[ $level_code ]['custom'] = '';

							}
						}

						$feature['levels'] = $level_settings;

						if( $no_status ) {
							if( method_exists( $module, 'is_active' ) ) {
								$feature['active'] = call_user_func( $module . '::is_active' );
							} else {
								$feature['active'] = true;
							}
						}

						$key = sanitize_title( $name );
						$table_settings[ $key ] = $feature;

						$configured_modules[] = $module;
					}
				}

				$table_settings['modules'] = $configured_modules;
			}

			return $table_settings;
		}

	}

}