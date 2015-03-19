<?php

if ( ! class_exists( 'ProSites_View_Coupons' ) ) {
	class ProSites_View_Coupons {

		public static function get_page_name() {
			return __( 'Pro Sites Coupons', 'psts' );
		}

		public static function get_menu_name() {
			return __( 'Coupons', 'psts' );
		}

		public static function get_description() {
			return __( 'You can create, delete, or update coupon codes for your network here.', 'psts' );
		}

		public static function get_page_slug() {
			return 'psts-coupons';
		}

		public static function render_page() {
			if ( ! is_super_admin() ) {
				echo "<p>" . __( 'Nice Try...', 'psts' ) . "</p>"; //If accessed properly, this message doesn't appear.
				return false;
			}

//			ProSites_Model_Coupons::process_form();

			self::process_coupon_forms();
			self::admin_coupons();
			self::admin_render_import();
		}

		/**
		 * Still using legacy coupons code below
		 */
		public static function admin_coupons() {
			global $psts, $wp;

			if ( ! is_super_admin() ) {
				echo "<p>" . __( 'Nice Try...', 'psts' ) . "</p>"; //If accessed properly, this message doesn't appear.
				return;
			}

			?>

			<script type="text/javascript">
				jQuery(document).ready(function ($) {
					jQuery.datepicker.setDefaults(jQuery.datepicker.regional['<?php echo $psts->language; ?>']);
					jQuery('.pickdate').datepicker({
						dateFormat: 'yy-mm-dd',
						changeMonth: true,
						changeYear: true,
						minDate: 0,
						firstDay: <?php echo ( get_option( 'start_of_week' ) == '0' ) ? 7 : get_option( 'start_of_week' ); ?>
					});
				});
			</script>
			<div class="wrap">
				<div class="icon32"><img src="<?php echo $psts->plugin_url . 'images/coupon.png'; ?>"/></div>
				<h2><?php _e( 'Pro Sites Coupons', 'psts' ); ?></h2>

				<p><?php _e( 'You can create, delete, or update coupon codes for your network here.', 'psts' ) ?></p>
				<?php

				$coupons = get_site_option( 'psts_coupons' );
				$error   = false;

				//delete checked coupons
				if ( isset( $_POST['allcoupon_delete'] ) ) {
					//check nonce
					check_admin_referer( 'psts_coupons' );

					if ( is_array( $_POST['coupons_checks'] ) ) {
						//loop through and delete
						foreach ( $_POST['coupons_checks'] as $del_code ) {
							unset( $coupons[ $del_code ] );
						}

						update_site_option( 'psts_coupons', $coupons );
						//display message confirmation
						echo '<div class="updated fade"><p>' . __( 'Coupon(s) succesfully deleted.', 'psts' ) . '</p></div>';
					}
				}

				//save or add coupon
				if ( isset( $_POST['submit_settings'] ) ) {
					//check nonce
					check_admin_referer( 'psts_coupons' );

					$error = false;

					$new_coupon_code = preg_replace( '/[^A-Z0-9_-]/', '', strtoupper( $_POST['coupon_code'] ) );
					if ( ! $new_coupon_code ) {
						$error[] = __( 'Please enter a valid Coupon Code', 'psts' );
					}

					$coupons[ $new_coupon_code ]['lifetime'] = $_POST['lifetime'];
					if ( $coupons[ $new_coupon_code ]['lifetime'] != 'first' && $coupons[ $new_coupon_code ]['lifetime'] != 'indefinite' ) {
						$error[] = __( 'Please choose a valid Coupon Lifetime', 'psts' );
					}

					$coupons[ $new_coupon_code ]['discount'] = round( $_POST['discount'], 2 );
					if ( $coupons[ $new_coupon_code ]['discount'] <= 0 ) {
						$error[] = __( 'Please enter a valid Discount Amount', 'psts' );
					}

					$coupons[ $new_coupon_code ]['discount_type'] = $_POST['discount_type'];
					if ( $coupons[ $new_coupon_code ]['discount_type'] != 'amt' && $coupons[ $new_coupon_code ]['discount_type'] != 'pct' ) {
						$error[] = __( 'Please choose a valid Discount Type', 'psts' );
					}
					//Coupon Valid for Period
					$coupons[ $new_coupon_code ]['valid_for_period'] = isset( $_POST['valid_for_period'] ) ? $_POST['valid_for_period'] : array();

					$coupons[ $new_coupon_code ]['start']            = strtotime( $_POST['start'] );
					if ( $coupons[ $new_coupon_code ]['start'] === false ) {
						$error[] = __( 'Please enter a valid Start Date', 'psts' );
					}

					$coupons[ $new_coupon_code ]['end'] = strtotime( $_POST['end'] );
					if ( $coupons[ $new_coupon_code ]['end'] && $coupons[ $new_coupon_code ]['end'] < $coupons[ $new_coupon_code ]['start'] ) {
						$error[] = __( 'Please enter a valid End Date not earlier than the Start Date', 'psts' );
					}

					$coupons[ $new_coupon_code ]['level'] = intval( $_POST['level'] );

					$coupons[ $new_coupon_code ]['uses'] = ( is_numeric( $_POST['uses'] ) ) ? (int) $_POST['uses'] : '';

					if ( ! $error ) {
						update_site_option( 'psts_coupons', $coupons );
						$new_coupon_code = '';
						echo '<div class="updated fade"><p>' . __( 'Coupon succesfully saved.', 'psts' ) . '</p></div>';
					} else {
						echo '<div class="error"><p>' . implode( '<br />', $error ) . '</p></div>';
					}
				}

				//if editing a coupon
				$new_coupon_code = isset ( $_GET['code'] ) ? $_GET['code'] : '';

				$apage = isset( $_GET['apage'] ) ? intval( $_GET['apage'] ) : 1;
				$num   = isset( $_GET['num'] ) ? intval( $_GET['num'] ) : 20;

				$coupon_list = get_site_option( 'psts_coupons' );
				$levels      = (array) get_site_option( 'psts_levels' );
				$total       = ( is_array( $coupon_list ) ) ? count( $coupon_list ) : 0;

				if ( ! empty( $total ) ) {
					$coupon_list = array_slice( $coupon_list, intval( ( $apage - 1 ) * $num ), intval( $num ) );
				}

				$request = remove_query_arg( 'apage' );
				$nav_args = array(
					'base' => @add_query_arg('apage','%#%'),
					'total'   => ceil( $total / $num ),
					'current' => $apage,
					'add_args' => array( 'page' => 'psts-coupons'),
				);

				$coupon_navigation = paginate_links( $nav_args );
				$page_link         = ( $apage > 1 ) ? '&amp;apage=' . $apage : '';
				?>

				<form id="form-coupon-list" action="<?php echo network_admin_url( 'admin.php?page=psts-coupons' ); ?>" method="post">
					<?php wp_nonce_field( 'psts_coupons' ) ?>
					<div class="tablenav">
						<?php if ( $coupon_navigation ) {
					echo "<div class='tablenav-pages'>$coupon_navigation</div>";
				} ?>

						<div class="alignleft">
							<input type="submit" value="<?php _e( 'Delete', 'psts' ) ?>" name="allcoupon_delete" class="button-secondary delete"/>
							<br class="clear"/>
						</div>
					</div>

					<br class="clear"/>

					<?php
				// define the columns to display, the syntax is 'internal name' => 'display name'
				$posts_columns = array(
					'code'      => __( 'Coupon Code', 'psts' ),
					'lifetime'  => __( 'Lifetime', 'psts' ),
					'discount'  => __( 'Discount', 'psts' ),
					'start'     => __( 'Start Date', 'psts' ),
					'end'       => __( 'Expire Date', 'psts' ),
					'level'     => __( 'Level', 'psts' ),
					'period'    => __( 'Period', 'psts' ),
					'used'      => __( 'Used', 'psts' ),
					'remaining' => __( 'Remaining Uses', 'psts' ),
					'edit'      => __( 'Edit', 'psts' )
				);
				?>

					<table width="100%" cellpadding="3" cellspacing="3" class="widefat">
						<thead>
							<tr>
								<th scope="col" class="check-column"><input type="checkbox"/></th>
								<?php foreach ( $posts_columns as $column_id => $column_display_name ) {
					$col_url = $column_display_name;
					?>
					<th scope="col"><?php echo $col_url ?></th>
				<?php } ?>
							</tr>
						</thead>
						<tbody id="the-list">
						<?php
				$bgcolor = isset( $class ) ? $class : '';
				if ( is_array( $coupon_list ) && count( $coupon_list ) ) {
					foreach ( $coupon_list as $coupon_code => $coupon ) {
						$class = ( isset( $class ) && 'alternate' == $class ) ? '' : 'alternate';

						//assign classes based on coupon availability
						//$class = ($psts->check_coupon($coupon_code)) ? $class . ' coupon-active' : $class . ' coupon-inactive';

						echo '<tr class="' . $class . ' blog-row"><th scope="row" class="check-column"><input type="checkbox" name="coupons_checks[]"" value="' . $coupon_code . '" /></th>';

						foreach ( $posts_columns as $column_name => $column_display_name ) {
							switch ( $column_name ) {
								case 'code':
									?>
									<th scope="row">
													<?php echo $coupon_code; ?>
												</th>
									<?php
									break;
								case 'lifetime':
									$lifetime_label = array(
										'first' => __( 'First payment', 'psts' ),
										'indefinite' => __( 'Indefinite', 'psts' ),
									);
									?>
									<th scope="row">
										<?php echo $lifetime_label[ $coupon['lifetime'] ]; ?>
									</th>
									<?php
									break;
								case 'discount':
									?>
									<th scope="row">
													<?php
													if ( $coupon['discount_type'] == 'pct' ) {
														echo $coupon['discount'] . '%';
													} else if ( $coupon['discount_type'] == 'amt' ) {
														echo $psts->format_currency( '', $coupon['discount'] );
													}
													?>
												</th>
									<?php
									break;

								case 'start':
									?>
									<th scope="row">
													<?php echo date_i18n( get_option( 'date_format' ), $coupon['start'] ); ?>
												</th>
									<?php
									break;

								case 'end':
									?>
									<th scope="row">
													<?php echo ( $coupon['end'] ) ? date_i18n( get_option( 'date_format' ), $coupon['end'] ) : __( 'No End', 'psts' ); ?>
												</th>
									<?php
									break;

								case 'level':
									?>
									<th scope="row">
													<?php echo isset( $levels[ $coupon['level'] ] ) ? $coupon['level'] . ': ' . $levels[ $coupon['level'] ]['name'] : __( 'Any Level', 'psts' ); ?>
												</th>
									<?php
									break;

								case 'period':
									?>
									<th scope="row">
										<?php
											//echo isset( $levels[ $coupon['period'] ] ) ? $coupon['period'] . ': ' . $levels[ $coupon['period'] ]['name'] : __( 'Any Level', 'psts' );
											$zero = true;
											if( isset( $coupon['valid_for_period'] ) ) {

												foreach( $coupon['valid_for_period'] as $i => $period ) {
													if( ! empty( $period ) ) {
														$zero = false;
														echo $period . __( 'm' );
														if ( $i !== count( $coupon['valid_for_period'] ) - 1 ) {
															echo ',';
														}
													}
												}
											}
											if( $zero ) {
												echo '-';
											}
										?>
									</th>
									<?php
									break;

								case 'used':
									?>
									<th scope="row">
													<?php echo isset( $coupon['used'] ) ? number_format_i18n( $coupon['used'] ) : 0; ?>
												</th>
									<?php
									break;

								case 'remaining':
									?>
									<th scope="row">
													<?php
													if ( isset( $coupon['uses'] ) && ! empty( $coupon['uses'] ) ) {
														echo number_format_i18n( intval( $coupon['uses'] ) - intval( @$coupon['used'] ) );
													} else {
														_e( 'Unlimited', 'psts' );
													}
													?>
												</th>
									<?php
									break;

								case 'edit':
									?>
									<th scope="row">
													<a href="admin.php?page=psts-coupons<?php echo $page_link; ?>&amp;code=<?php echo $coupon_code; ?>#add_coupon"><?php _e( 'Edit', 'psts' ) ?>&raquo;</a>
												</th>
									<?php
									break;

							}
						}
						?>
									</tr>
									<?php
					}
				} else {
					?>
					<tr style='background-color: <?php echo $bgcolor; ?>'>
									<td colspan="9"><?php _e( 'No coupons yet.', 'psts' ) ?></td>
								</tr>
				<?php
				} // end if coupons
				?>
						</tbody>
						<tfoot>
							<tr>
								<th scope="col" class="check-column"><input type="checkbox"/></th>
								<?php foreach ( $posts_columns as $column_id => $column_display_name ) {
					$col_url = $column_display_name;
					?>
					<th scope="col"><?php echo $col_url ?></th>
				<?php } ?>
							</tr>
						</tfoot>
					</table>

					<div class="tablenav">
						<?php if ( $coupon_navigation ) {
					echo "<div class='tablenav-pages'>$coupon_navigation</div>";
				} ?>
					</div>

					<div id="poststuff" class="metabox-holder">

						<div class="postbox">
							<h3 class="hndle" style="cursor:auto;"><span>
							<?php
				if ( isset( $_GET['code'] ) || $error ) {
					_e( 'Edit Coupon', 'psts' );
				} else {
					_e( 'Add Coupon', 'psts' );
				}
				$periods = $psts->get_setting( 'enabled_periods', 0 );
				?></span></h3>

							<div class="inside">
							<?php
				$coupon_life      = 'first';
				$discount         = '';
				$discount_type    = '';
				$start            = date( 'Y-m-d' );
				$end              = '';
				$uses             = '';
				$valid_for_period = array();
				//setup defaults
				if ( isset( $new_coupon_code ) && isset( $coupons[ $new_coupon_code ] ) ) {
					$coupon_life      = $coupons[ $new_coupon_code ]['lifetime'];
					$discount         = ( $coupons[ $new_coupon_code ]['discount'] && $coupons[ $new_coupon_code ]['discount_type'] == 'amt' ) ? round( $coupons[ $new_coupon_code ]['discount'], 2 ) : $coupons[ $new_coupon_code ]['discount'];
					$discount_type    = $coupons[ $new_coupon_code ]['discount_type'];
					$start            = ( $coupons[ $new_coupon_code ]['start'] ) ? date( 'Y-m-d', $coupons[ $new_coupon_code ]['start'] ) : date( 'Y-m-d' );
					$end              = ( $coupons[ $new_coupon_code ]['end'] ) ? date( 'Y-m-d', $coupons[ $new_coupon_code ]['end'] ) : '';
					$uses             = $coupons[ $new_coupon_code ]['uses'];
					$valid_for_period = isset( $coupons[ $new_coupon_code ]['valid_for_period'] ) ? $coupons[ $new_coupon_code ]['valid_for_period'] : array();
				}
				?>
							<table id="add_coupon">
								<thead>
									<tr>
										<th class="coupon-code">
											<?php echo __( 'Coupon Code', 'psts' ) . $psts->help_text( __( 'Letters and numbers only', 'psts' ) ); ?>
										</th>
										<th class="coupon-life">
											<?php echo __( 'Lifetime', 'psts' ) . $psts->help_text( __( 'For the first payment only or the life of the account.', 'psts' ) ); ?>
										</th>
										<th><?php _e( 'Discount', 'psts' ) ?></th>
										<th><?php _e( 'Start Date', 'psts' ) ?></th>
										<th class="expire-date">
											<?php echo __( 'Expire Date', 'psts' ) . $psts->help_text( __( 'No end if left blank', 'psts' ) ); ?>
										</th>
										<th>
											<?php _e( 'Level', 'psts' ) ?>
										</th>
										<th class="coupon-period">
											<?php echo __( 'Period', 'psts' ) . $psts->help_text( __( 'Allows you to limit the availability of coupon for selected subscription period.', 'psts' ) ); ?>
										</th>
										<th class="allowed-users">
											<?php echo __( 'Allowed Uses', 'psts' ) . $psts->help_text( __( 'Unlimited if blank', 'psts' ) ); ?>
										</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>
											<input value="<?php echo $new_coupon_code ?>" name="coupon_code" type="text" style="text-transform: uppercase;"/>
										</td>
										<td>
											<select name="lifetime" class="chosen">
												<option value="first"<?php selected( $coupon_life, 'first' ) ?>><?php esc_html_e( 'First payment'); ?></option>
												<option value="indefinite"<?php selected( $coupon_life, 'indefinite' ) ?>><?php esc_html_e( 'Indefinite'); ?></option>
											</select>
										</td>
										<td>
											<input value="<?php echo $discount; ?>" size="3" name="discount" type="text"/>
											<select name="discount_type" class="chosen narrow">
												<option value="amt"<?php selected( $discount_type, 'amt' ) ?>><?php echo $psts->format_currency(); ?></option>
												<option value="pct"<?php selected( $discount_type, 'pct' ) ?>>%</option>
											</select>
										</td>
										<td>
											<input value="<?php echo $start; ?>" class="pickdate" size="11" name="start" type="text"/>
										</td>
										<td>
											<input value="<?php echo $end; ?>" class="pickdate" size="11" name="end" type="text"/>
										</td>
										<td>
											<select name="level" class="chosen">
												<option value="0"><?php _e( 'Any Level', 'psts' ) ?></option>
												<?php
				foreach ( $levels as $key => $value ) {
					?>
					<option value="<?php echo $key; ?>"<?php selected( @$coupons[ $new_coupon_code ]['level'], $key ) ?>><?php echo $key . ': ' . $value['name']; ?></option><?php
				}
				?>
											</select>
										</td>
										<?php
				if ( ! empty( $periods ) ) {
					?>
					<td>
					<select name="valid_for_period[]" multiple class="psts-period chosen" data-placeholder="Select Period">
												<option value="0" <?php echo in_array( 0, $valid_for_period ) ? 'selected' : ''; ?>><?php _e( 'Any Period', 'psts' ) ?></option>
						<?php
						foreach ( $periods as $period ) {
							$text = $period == 1 ? __( 'month', 'psts') : __( 'months', 'psts' );
							?>
							<option value="<?php echo $period; ?>"<?php echo in_array( $period, $valid_for_period ) ? 'selected' : ''; ?>><?php echo $period . ' ' . $text; ?></option><?php
						}
						?>
												</select>
					</td><td>
						<input value="<?php echo $uses; ?>" size="4" name="uses" type="text"/>
					</td><?php
				} ?>
									</tr>
								</tbody>
							</table>

							<p class="submit">
								<input type="submit" name="submit_settings" class="button-primary" value="<?php _e( 'Save Coupon', 'psts' ) ?>"/>
							</p>
							</div>
						</div>

					</div>
				</form>

			</div>
			<?php
		}

		private static function admin_render_import() {

			$csv_fields = array(
				'coupon_code' => __( 'Letters and numbers only. No spaces.', 'psts' ),
				'lifetime' => __( 'How long a coupon\'s discount is active for. "first" - for the first payment only. "indefinite" - for the life of the site.', 'psts' ),
				'discount' => __( 'Numeric value of discount to be applied without symbols.', 'psts' ),
				'type' => __( 'Specify \'amt\' for amount and \'pct\' for percentage.', 'psts' ),
				'start_date' => __( 'Coupon start date in YYYY-MM-DD format or empty.', 'psts' ),
				'expiry_date' => __( 'Coupon expiry date in YYYY-MM-DD format or empty.', 'psts' ),
				'level' => __( 'Numeric number of the level the coupon applies to (as per \'Levels\' setting). 0 for all levels.', 'psts' ),
				'uses' => __( 'Number of times this coupon can be used. Specify 0 for no restrictions.', 'psts' ),
				'period' => __( 'Payment period the coupon applies to. 0 for all periods. 1 for 1 Month, 3 for 3 Months, 12 for 12 Months. Use the | symbol for multiple options. e.g. 3|12', 'psts' ),
			);

			?>
			<div id="poststuff" class="metabox-holder">
			<div class="postbox">
			<h3 class="hndle" style="cursor:auto;"><span><?php esc_html_e( 'Import Coupons', 'psts' ); ?></span></h3>
				<div class="inside">
					<p class="description">
						<?php esc_html_e( 'Select a CSV file containing your coupons with the following headings in the given order:', 'psts' ); ?>
						<ul>
						<?php
							foreach( $csv_fields as $field => $description ) {
								?>
									<li><?php echo '<strong>' . esc_html( $field ) . '</strong> - ' . esc_html( $description ); ?></li>
								<?php
							}
						?>
						</ul>
					</p>
					<form id="form-coupon-import" action="<?php echo network_admin_url( 'admin.php?page=psts-coupons' ); ?>" method="post" enctype="multipart/form-data">
						<?php wp_nonce_field( 'psts_coupons_import' ) ?>
						<p>
<!--						<label for="uploadfiles[]">-->
							<?php esc_html_e( 'Select the CSV file you want to import: ', 'psts' ); ?><br /><input type="file" name="uploadfiles[]" id="uploadfiles" size="35" class="uploadfiles" />
<!--						</label>-->
						</p>
						<input class="button" type="submit" name="coupon-import-csv" value="<?php esc_attr_e( 'Import Coupons', 'psts' );?>" />
					</form>
				</div>
			</div>
			</div>
			<?php
		}

		private static function process_coupon_forms() {

			if ( isset( $_POST['coupon-import-csv'] ) ) {
				check_admin_referer( 'psts_coupons_import' );

				$uploadfiles = $_FILES['uploadfiles'];

				if ( is_array( $uploadfiles ) ) {
					foreach ( $uploadfiles['name'] as $key => $value ) {
						if ( $uploadfiles['error'][$key] == 0 && 'text/csv' == $uploadfiles['type'][$key] ) {

							$filetmp = $uploadfiles['tmp_name'][$key];

							$filename = $uploadfiles['name'][$key];

							//extract the extension, but keep just in case there are multiple dots, resconstruct
							$filename = explode( '.', $filename );
							$extension = array_pop( $filename );
							$filename = implode( '.', $filename );

							$filetitle = preg_replace('/\.[^.]+$/', '', basename( $filename ) );
							$filename = $filetitle . '.' . $extension;

							$upload_dir = wp_upload_dir();

							$i = 0;
							while ( file_exists( $upload_dir['path'] .'/' . $filename ) ) {
								$filename = $filetitle . '_' . $i . '.' . $extension;
								$i++;
							}

							$destination = $upload_dir['path'] . '/' . $filename;


							if ( !is_writeable( $upload_dir['path'] ) ) {
								// Not writable
								return;
							}

							if ( !@move_uploaded_file( $filetmp, $destination) ){
								// Saving failed
								continue;
							}

							$added = ProSites_Helper_Coupons::process_coupon_import( $destination );
							//display message confirmation
							echo '<div class="updated fade"><p>' . sprintf( __( '%d coupon(s) imported.', 'psts' ), $added ) . '</p></div>';

						} else {
							// ERROR MSG
						}
					}
				}
			}


		}

	}

}