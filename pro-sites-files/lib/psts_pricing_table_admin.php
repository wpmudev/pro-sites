<?php

class ProSites_Pricing_Table_Admin extends WP_List_Table {

	function __construct() {
		parent::__construct( array(
				'singular' => 'psts_co_feature',
				'plural'   => 'psts_co_features',
				'ajax'     => true
			)
		);
	}

	/**
	 * @TODO: Show Table as it is displayed in frontend
	 *
	 * @param $which
	 */
	function extra_tablenav( $which ) {
		if ( $which == "top" ) {
			$cur_level = 1;
			if ( ! empty ( $_REQUEST['level_id'] ) ) {
				$cur_level = $_REQUEST['level_id'];
			}
			$levels = (array) get_site_option( 'psts_levels' );
			if ( ! ( defined( 'PSTS_DONT_REVERSE_LEVELS' ) && PSTS_DONT_REVERSE_LEVELS ) ) {
				$levels = array_reverse( $levels, true );
			}
			$level_nav = '<ul class="subsubsub">';
			$count     = 1;
			foreach ( $levels as $level_id => $level ) {
				$class_name = $cur_level == $level_id ? "current" : "";
				if ( $count == count( $levels ) ) {
					$level_nav .= '<li class="' . strtolower( $level['name'] ) . '"><a class="' . $class_name . '" href="?page=' . $_REQUEST['page'] . '&action=filter&level_id=' . $level_id . '">' . $level['name'] . '</a></li>';
				} else {
					$level_nav .= '<li class="' . strtolower( $level['name'] ) . '"><a class="' . $class_name . '" href="?page=' . $_REQUEST['page'] . '&action=filter&level_id=' . $level_id . '">' . $level['name'] . '</a> | </li>';
				}
				$count ++;
			}
			$level_nav .= '</ul>';
			echo $level_nav;
		}
	}

	function column_default( $item, $column_name ) {
		global $psts;
		$field = '';
		switch ( $column_name ) {
			case 'psts_co_order_id':
				$field = '<span class="' . $column_name . '" data-order="' . $item [ $column_name ] . '">' . $item [ $column_name ] . '</span>';
				break;
			case 'psts_co_visible':
				global $$item ['psts_co_class_name'];
				//Check if class has some level settings
				$default_value = ( method_exists( $$item ['psts_co_class_name'], 'required_level' ) && $item ['psts_co_level_id'] >= $$item ['psts_co_class_name']->required_level() ) ? "enabled" : "disabled";

				$check_value = $psts->get_setting( 'pricing_table_module_' . $item ['psts_co_class_name'] . '_visible' );

				//Check if there is a setting by user, otherwise check for default
				$check_value = $check_value ? $check_value : ( $default_value ? $default_value : "disabled" );

				if ( strtolower( $_SERVER['REQUEST_METHOD'] ) == 'post' ) {
					if ( array_key_exists( 'pricing_table_module_' . $item ['psts_co_class_name'] . '_visible', $_POST['psts'] ) ) {
						$check_value = $_POST['psts'][ 'pricing_table_module_' . $item ['psts_co_class_name'] . '_visible' ];
					} else {
						$check_value = "";
						$psts->update_setting( 'pricing_table_module_' . $item ['psts_co_class_name'] . '_visible', $check_value );
					}
				}
				$field = '<input type="checkbox" id="psts[pricing_table_module_' . $item ['psts_co_class_name'] . '_visible]" name="psts[pricing_table_module_' . $item ['psts_co_class_name'] . '_visible]" value="enabled" ' . checked( $check_value, 'enabled', false ) . ' />';
				break;
			case 'psts_co_has_thick':
				$check_value = $psts->get_setting( 'pricing_table_module_' . $item ['psts_co_class_name'] . '_has_thick_' . $item ['psts_co_level_id'] ) ? $psts->get_setting( 'pricing_table_module_' . $item ['psts_co_class_name'] . '_has_thick_' . $item ['psts_co_level_id'] ) : "disabled";
				if ( strtolower( $_SERVER['REQUEST_METHOD'] ) == 'post' ) {
					if ( array_key_exists( 'pricing_table_module_' . $item ['psts_co_class_name'] . '_has_thick_' . $item ['psts_co_level_id'], $_POST['psts'] ) ) {
						$check_value = $_POST['psts'][ 'pricing_table_module_' . $item ['psts_co_class_name'] . '_has_thick_' . $item ['psts_co_level_id'] ];
					} else {
						$check_value = "";
						$psts->update_setting( 'pricing_table_module_' . $item ['psts_co_class_name'] . '_has_thick_' . $item ['psts_co_level_id'], $check_value );
					}
				}
				$field = '<input type="checkbox" id="psts[pricing_table_module_' . $item ['psts_co_class_name'] . '_has_thick_' . $item ['psts_co_level_id'] . ']" name="psts[pricing_table_module_' . $item ['psts_co_class_name'] . '_has_thick_' . $item ['psts_co_level_id'] . ']" value="enabled" ' . checked( $check_value, 'enabled', false ) . ' />';
				break;
			case 'psts_co_description':
				$field_content = $psts->get_setting( 'pricing_table_module_' . $item ['psts_co_class_name'] . '_description' ) ? $psts->get_setting( 'pricing_table_module_' . $item ['psts_co_class_name'] . '_description' ) : $item [ $column_name ];
				$field         = strip_tags( $field_content );
				if ( $item ['psts_co_class_name'] == $this->edit_item ) {
					$field = wp_editor( $item[ $column_name ], "psts_pricing_table_module_" . $item ['psts_co_class_name'] . '_description' );
				}
				break;
			case 'psts_co_included':
				//Text for modules, in place of right or wrong mark
				$cur_level = 1;
				if ( ! empty ( $_REQUEST['level_id'] ) ) {
					$cur_level = $_REQUEST['level_id'];
				}
				if ( ( $item ['psts_co_class_name'] == $this->edit_item ) && ( $item ['psts_co_level_id'] == $cur_level ) ) {
					$field_value = $this->get_include_text( $item ['psts_co_class_name'], $item ['psts_co_level_id'] );
					$field       = '<textarea id="psts[pricing_table_module_' . $item ['psts_co_class_name'] . '_included_' . $item ['psts_co_level_id'] . ']" name="psts[pricing_table_module_' . $item ['psts_co_class_name'] . '_included_' . $item ['psts_co_level_id'] . ']" cols="14">' . $field_value . '</textarea>';
				}
				break;
			case 'psts_co_level_id':
				$levels = (array) get_site_option( 'psts_levels' );
				$field  = $levels [ $item[ $column_name ] ]['name'];
				break;
			default:
				$field = $item[ $column_name ];
		}

		return $field;
	}

	function column_psts_co_name( $item ) {
		$cur_level = 1;
		if ( ! empty ( $_REQUEST['level_id'] ) ) {
			$cur_level = $_REQUEST['level_id'];
		}
		$actions = array(
			'edit' => sprintf( '<a href="?page=%s&action=%s&module=%s&level_id=%s">Edit</a>', $_REQUEST['page'], 'feature_edit', $item['psts_co_class_name'], $cur_level )
		);
		if ( $item ['psts_co_class_name'] == $this->edit_item ) {
			$field = '<input type="text" class="ptitle" name="psts[pricing_table_module_' . $item ['psts_co_class_name'] . '_label]" value="' . $item['psts_co_name'] . '" />';

			return sprintf( '%1$s%2$s',
				$field,
				$this->row_actions( $actions )
			);
		} else {
			return sprintf( '%1$s%2$s',
				$item['psts_co_name'],
				$this->row_actions( $actions )
			);
		}
	}

	function get_columns() {
		return $columns = array(
			'psts_co_visible'     => __( 'Visible' ),
			'psts_co_name'        => __( 'Name' ),
			'psts_co_description' => __( 'Description' ),
			'psts_co_included'    => __( 'Included in Plan' ),
			'psts_co_class_name'  => __( 'Class Name' ),
			'psts_co_order_id'    => __( 'Order' ),
			'psts_co_level_id'    => __( 'Level' )
		);
	}

	function get_sortable_columns() {
		$sortable_columns = array(
			'psts_co_name' => array( 'psts_co_name', false )
		);

		return $sortable_columns;
	}

	function process_bulk_action() {
		$this->edit_item = null;
		if ( 'feature_edit' === $this->current_action() ) {
			$this->edit_item = ( ! empty( $_REQUEST['module'] ) ) ? $_REQUEST['module'] : null;
		}
	}

	function prepare_items() {
		global $psts, $psts_modules;
		$modules = $psts->get_setting( 'modules_enabled', array() );

		$perpage = 20;

		$columns  = $this->get_columns();
		$hidden   = array(
			'psts_co_class_name',
			'psts_co_order_id',
			'psts_co_level_id'
		);
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$current_page = $this->get_pagenum();
		$totalitems   = count( $modules );
		$totalpages   = ceil( $totalitems / $perpage );
		$this->set_pagination_args( array(
				"total_items" => $totalitems,
				"total_pages" => $totalpages,
				"per_page"    => $perpage
			)
		);
		$this->process_bulk_action();
		$this->load_data( $modules, $perpage );
	}

	function load_data( $items, $perpage ) {
		global $psts, $psts_modules;
		$data      = array();
		$cur_level = 1;
		if ( ! empty ( $_REQUEST['level_id'] ) ) {
			$cur_level = $_REQUEST['level_id'];
		}
		$cur_level = intval( $cur_level );

		foreach ( $items as $index => $module ) {
			global $$module;
			$required_level = '';
			//Get Level Preference for each module and mark it visible as per settings
			//Check if class exists and if there is any function t return minimum level
			if ( class_exists( $module ) && method_exists( $$module, 'required_level' ) ) {
				$required_level = $$module->required_level();
			}
			$module_is_visible    = $required_level <= $cur_level ? true : false;
			$module_label         = $psts->get_setting( 'pricing_table_module_' . $module . '_label' ) ? $psts->get_setting( 'pricing_table_module_' . $module . '_label' ) : $module::$user_label;
			$module_desc          = $psts->get_setting( 'pricing_table_module_' . $module . '_description' ) ? $psts->get_setting( 'pricing_table_module_' . $module . '_description' ) : $module::$user_description;
			$module_included_text = $psts->get_setting( 'pricing_table_module_' . $module . '_included' ) ? $psts->get_setting( 'pricing_table_module_' . $module . '_included' ) : "";
			$levels               = (array) get_site_option( 'psts_levels' );
			if ( ! ( defined( 'PSTS_DONT_REVERSE_LEVELS' ) && PSTS_DONT_REVERSE_LEVELS ) ) {
				$levels = array_reverse( $levels, true );
			}
			foreach ( $levels as $level_id => $level ) {
				if ( $cur_level !== $level_id ) {
					continue;
				}
				$data[] = array(
					'psts_co_visible'     => $module_is_visible,
					'psts_co_order_id'    => ( $index + 1 ),
					'psts_co_name'        => $module_label,
					'psts_co_description' => $module_desc,
					'psts_co_class_name'  => $module,
					'psts_co_included'    => $module_included_text,
					'psts_co_level_id'    => $level_id
				);
			}
		}
		$modules_order = $psts->get_setting( 'pricing_table_order' ) ? $psts->get_setting( 'pricing_table_order' ) : "";
		if ( count( array_filter( explode( ",", $modules_order ), array(
				&$this,
				"trim_array"
			) ) ) !== count( $data )
		) {
			$modules_order = "";
		}
		if ( ! empty ( $modules_order ) ) {
			$order       = explode( ",", $modules_order );
			$sorted_data = array();
			for ( $i = 0; $i < count( $order ); $i ++ ) {
				if ( ! is_numeric( $order [ $i ] ) ) {
					continue;
				}
				$sorted_data[] = $data [ ( $order [ $i ] - 1 ) ];
			}
			$data = $sorted_data;
		}
		if ( ! empty ( $_REQUEST['orderby'] ) ) {
			usort( $data, function ( $a, $b ) {
				$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'psts_co_name';
				$order   = ( ! empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'asc';
				$result  = strcmp( $a[ $orderby ], $b[ $orderby ] );

				return ( $order === 'asc' ) ? $result : - $result;
			} );
		}
		$current_page = $this->get_pagenum();
		$data         = array_slice( $data, ( ( $current_page - 1 ) * $perpage ), $perpage );
		$this->items  = $data;
	}

	private function trim_array( $var ) {
		return ( trim( $var ) ? true : false );
	}

	function render_settings() {
		global $psts;
		$levels                    = get_site_option( 'psts_levels' );
		$level_count               = 1;
		$featured_level_options    = '';
		$colorpicker_level_options = '';
		foreach ( $levels as $level ) {

			$selected = ( $psts->get_setting( 'featured_level' ) == $level_count ) ? ' selected="selected"' : '';

			$featured_level_options .= '<option value="' . $level_count . '"' . $selected . '>' . $level_count . ':' . $level['name'] . '</option>';

			$level_count ++;
			// Load Color Picker Options
			$colorpicker_level_options .= '<tr>
												<th scope="row">' . $level['name'] . '</th>
												<td>
													<input type="text" class="color-picker" name="psts[pricing_table_level_' . $level['name'] . '_color]" value="' . $psts->get_setting( 'pricing_table_level_' . $level['name'] . '_color' ) . '"><br/>
													<small>Choose a color to identify this plan</small>
												</td>
											</tr>';
		}

		$enable_pricing_value = $psts->get_setting( 'comparison_table_enabled' ) ? $psts->get_setting( 'comparison_table_enabled' ) : $psts->get_setting( 'co_pricing' );
		$enable_plans_table   = $psts->get_setting( 'plans_table_enabled', 'enabled' );

		if ( strtolower( $_SERVER['REQUEST_METHOD'] ) == 'post' ) {
			if ( array_key_exists( 'comparison_table_enabled', $_POST['psts'] ) ) {
				$enable_pricing_value = $_POST['psts']['comparison_table_enabled'];
			} else {
				$enable_pricing_value = "disabled";
				$psts->update_setting( 'comparison_table_enabled', $enable_pricing_value );
			}
			if ( array_key_exists( 'plans_table_enabled', $_POST['psts'] ) ) {
				$enable_plans_table = $_POST['psts']['plans_table_enabled'];
			} else {
				$enable_plans_table = "disabled";
				$psts->update_setting( 'plans_table_enabled', $enable_plans_table );
			}
		}
		$modules_order = $psts->get_setting( 'pricing_table_order' ) ? $psts->get_setting( 'pricing_table_order' ) : "";
		$this->prepare_items(); ?>

		<!-- Pricing Table Settings page-->
		<div class="wrap">

			<div class="icon32">
				<img src="<?php echo $psts->plugin_url; ?>images/settings.png"/>
			</div>

			<h2><?php echo apply_filters( 'psts_checkout_page_settings_title', __( 'Pro Sites Pricing Table Settings', 'psts' ) ); ?></h2>

			<form method="post" action="">
				<!-- Description-->
				<p>
					<?php echo apply_filters( 'psts_checkout_page_settings_helper', __( 'You can enable plans &amp; pricing and comparison table settings here.', 'psts' ) ); ?>
					<input type="submit" name="submit_settings" class="button-primary alignright" value="Save Changes">
				</p>

				<div style="clear:both;"><br/></div>
				<div class="metabox-holder general-settings">

					<!-- Plans and Pricing-->
					<div class="postbox">
						<h3 class="hndle">
							<span><?php echo __( 'Plans &amp; Pricing', 'psts' ); ?></span>
						</h3>

						<div class="inside">
							<table class="form-table">
								<tr>
									<th><?php echo __( 'Use Plans &amp; Pricing Table', 'psts' ); ?></th>
									<td>
										<input type="checkbox" id="psts[plans_table_enabled]" name="psts[plans_table_enabled]" value="enabled" <?php echo checked( $enable_plans_table, 'enabled', false ); ?> />
										<label for="psts[plans_table_enabled]">
											&nbsp;<?php echo __( 'Check this option to display only the plans and pricing table.', 'psts' ); ?></label>
									</td>
								</tr>
								<tr>
									<th><?php echo __( 'Show Comparison Table', 'psts' ); ?></th>
									<td>
										<input type="checkbox" id="psts[comparison_table_enabled]" name="psts[comparison_table_enabled]" value="enabled" <?php echo checked( $enable_pricing_value, 'enabled', false ); ?>/>
										<label for="psts[comparison_table_enabled]">
											&nbsp;<?php echo __( 'Selecting this option will show both the plans and comparison tables.', 'psts' ); ?></label>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<!-- Plans Settings -->
					<div class="postbox">
						<h3 class="hndle">
							<span><?php echo __( 'Plans Settings', 'psts' ); ?></span>
						</h3>

						<div class="inside">
							<table class="form-table">
								<tr>
									<th><?php echo __( 'Featured Level', 'psts' ); ?></th>
									<td>
										<select name="psts[featured_level]"><?php echo $featured_level_options; ?></select>
									</td>
								</tr>
								<?php echo $colorpicker_level_options; ?>
							</table>
						</div>
					</div>

					<!-- Comparison Modules -->
					<div class="postbox">
						<h3 class="hndle">
							<span><?php echo __( 'Comparison Modules', 'psts' ); ?></span>
						</h3>

						<div class="inside">
							<?php echo $this->display(); ?>
						</div>

					</div>

					<input type="hidden" id="psts[pricing_table_order]" name="psts[pricing_table_order]" value="<?php echo $modules_order; ?>"/>
					<?php echo wp_nonce_field( 'psts_checkout_page_settings', 'psts_checkout_page_settings' ); ?>

					<!-- Submit-->
					<p class="submit alignright">
						<input type="submit" name="submit_settings" class="button-primary" value="Save Changes">
					</p>

				</div>
				<?php echo wp_nonce_field( 'psts_checkout_settings' ); ?>
			</form>
		</div>
		<!--		End of div wrap-->
	<?php
	}

	public function __toString() {
		$this->render_settings();

		return "";
	}

	/**
	 * Returns a text to be displayed in pricing table, level wise for a module
	 *
	 * @param $class_name
	 * @param $level
	 */
	public function get_include_text( $class_name, $level ) {
		global $psts;
		//Array of classes which have numeric limits level wise
		$classes_with_text = array(
			'ProSites_Module_PostThrottling',
			'ProSites_Module_Quota',
			'ProSites_Module_PostingQuota'
		);
		$include_text      = $psts->get_setting( 'pricing_table_module_' . $class_name . '_included_' . $level );

		//If there is no include text and class is one of the selected class, get the text as per settings
		if ( empty( $include_text ) && in_array( $class_name, $classes_with_text ) ) {

			global $$class_name;
			$include_text = $$class_name->include_text( $level );

		}

		return $include_text;
	}
}

?>