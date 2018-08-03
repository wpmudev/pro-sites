<?php
/*
Plugin Name: Pro Sites (Feature: Upgrade Admin Links)
*/
class ProSites_Module_UpgradeAdminLinks {

	static $user_label;
	static $user_description;

	// Module name for registering
	public static function get_name() {
		return __('Upgrade Admin Menu Links', 'psts');
	}

	// Module description for registering
	public static function get_description() {
		return __('Allows you to add custom menu items in admin panel that will encourage admins to get higher level by redirecting to upgrade page.', 'psts');
	}

	function __construct() {

		self::$user_label       = __( 'Admin Menu Upgrade Link', 'psts' );
		self::$user_description = __( 'Displays a convenient upgrade link in the admin menu.', 'psts' );

		add_action( 'psts_settings_page', array(&$this, 'settings') );
		add_action( 'admin_menu', array(&$this, 'admin_menu'), 20 );
		add_action('admin_print_scripts', array(&$this, 'admin_print_scripts'), 99);
		add_filter('psts_settings_filter', array(&$this, 'psts_settings_filter'), 10, 1);

		add_action('init', array(&$this, 'redirect'), 10, 1);
	}

	function admin_print_scripts() {
		global $pagenow;
		if($pagenow == 'admin.php' && isset($_REQUEST['page']) && $_REQUEST['page'] == 'psts-settings') {
			?>
			<script type="text/javascript">
				(function($) {
					$(document).ready(function() {
						var new_key;

						$('.psts-ual-menu-item-remove').click(function(event) {
							event.preventDefault();

							$(this).parent().remove();
						});

						$('#psts-ual-menu-item-add').click(function(event) {
							event.preventDefault();

							var item_new = $('#psts-ual-menu-item-new').clone().removeAttr('id').show();

							//set correct new key value
							if(typeof new_key === "undefined")
								new_key = Number($(this).attr('href').substring(1));
							else
								new_key = new_key + 1;
							item_new.find('input, select').each(function(){
								$(this).attr('name',$(this).attr('name').replace('new_key',new_key));
							});

							$('#psts-ual-menu-items').append(item_new);
						});
					});
				})(jQuery);
			</script>
		<?php
		}
	}

	function admin_menu() {
		global $submenu, $psts;

		$blog_id = get_current_blog_id();
		$menu_items = $psts->get_setting('ual', array());

		foreach ($menu_items as $id => $options) {
			if($options['name'] && !is_pro_site($blog_id, $options['level']))
				if(!$options['parent'])
					add_menu_page( $options['name'], $options['name'], 'manage_options', 'upgrade_redirect&source=test', array(&$this, 'redirect'), '', $options['priority'] );
				else {
					$redirect_url = $psts->checkout_url($blog_id, 'Menu - '.$options['name']);
					$submenu[$options['parent']] = $this->magic_insert($options['priority']-1,array($options['name'], 'manage_options', $redirect_url),$submenu[$options['parent']]);
				}
		}
	}

	function redirect() {
		if(isset($_GET['page']) && $_GET['page'] == 'upgrade_redirect') {
			global $psts;

			wp_redirect($psts->checkout_url(get_current_blog_id(), 'Menu - '.$_GET['source']));
			exit();
		}
	}

	function psts_settings_filter($psts_options) {
		if(isset($psts_options['ual']) && is_array($psts_options['ual']))
			foreach ($psts_options['ual'] as $id => $options) {
				if((isset($options['name']) && !$options['name']) || !isset($options['name']))
					unset($psts_options['ual'][$id]);
			}
		return $psts_options;
	}

	function settings() {
		global $psts;
		$menu_items = $psts->get_setting('ual', array());
		end($menu_items);
		$menu_items_new_key = (count($menu_items)) ? key($menu_items)+1 : '0';
		$menu_items['new'] = array('name' => '', 'parent' => '', 'priority' => '10', 'level' => '');

		$levels = (array)get_site_option( 'psts_levels' );
		$menu_parents = array(
			'None' => '',
			'Dashboard' => 'index.php',
			'Posts' => 'edit.php',
			'Media' => 'upload.php',
			'Links' => 'link-manager.php',
			'Pages' => 'edit.php?post_type=page',
			'Comments' => 'edit-comments.php',
			'Appearance' => 'themes.php',
			'Plugins' => 'plugins.php',
			'Users' => 'users.php',
			'Tools' => 'tools.php',
			'Settings' => 'options-general.php'
		);
		?>
<!--		<div class="postbox">-->
<!--			<h3 class="hndle" style="cursor:auto;"><span>--><?php //_e('Upgrade Admin Menu Links', 'psts') ?><!--</span> - <span class="description">--><?php //_e('Allows you to add custom menu items in admin panel that will encurage admins to get higher level by redirecting to upgrade page.', 'psts') ?><!--</span></h3>-->
			<div class="inside">
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e('Menu Items', 'psts') ?></th>
						<td>
							<div id="psts-ual-menu-items">
								<?php
								$count = 0;
								foreach ($menu_items as $id => $options) {
									$count ++;

									$form_id = ($id === 'new') ? 'new_key' : $id;
									$additional_attrs = ($id === 'new') ? 'id="psts-ual-menu-item-new" style="display:none;"' : '';
									?>
									<p class="psts-ual-menu-item"<?php echo $additional_attrs; ?>>
										<?php _e('Name:', 'psts') ?>
										<input type="text" class="small-text" name="psts[ual][<?php echo esc_attr($form_id); ?>][name]" value="<?php echo esc_attr($options['name']); ?>" size="30">
										<?php _e('Parent:', 'psts') ?>
										<select name="psts[ual][<?php echo esc_attr($form_id); ?>][parent]">
											<?php
											foreach ($menu_parents as $parent => $value) {
												?><option value="<?php echo $value; ?>"<?php selected($options['parent'], $value) ?>><?php echo esc_attr($parent); ?></option><?php
											}
											?>
										</select>
										<?php _e('Position Priority:', 'psts') ?>
										<input name="psts[ual][<?php echo esc_attr($form_id); ?>][priority]" class="small-text" type="number" value="<?php echo esc_attr($options['priority']); ?>" step="1" min="1">
										<?php _e('Required level:', 'psts') ?>
										<select name="psts[ual][<?php echo esc_attr($form_id); ?>][level]">
											<?php
											foreach ($levels as $level => $value) {
												?><option value="<?php echo $level; ?>"<?php selected($options['level'], $level) ?>><?php echo $level . ': ' . esc_attr($value['name']); ?></option><?php
											}
											?>
										</select>
										<a class="button button-secondary psts-ual-menu-item-remove" href="#"><?php _e('Remove', 'psts') ?></a>
									</p>
								<?php
								}
								?>
							</div>

							<p><a id="psts-ual-menu-item-add" class="button button-secondary" href="#<?php echo $menu_items_new_key; ?>"><?php _e('Add new menu item', 'psts') ?></a></p>
							<p class="description"><?php _e('Add admin menu items redirecting to upgrade page for sites without required level.', 'psts') ?></p>
						</td>
					</tr>
				</table>
			</div>
<!--		</div>-->
	<?php
	}

	function magic_insert($index,$value,$input_array) {
		if (isset($input_array[$index])) {
			$output_array = array($index=>$value);
			foreach($input_array as $k=>$v) {
				if ($k<$index) {
					$output_array[$k] = $v;
				} else {
					if (isset($output_array[$k]) ) {
						$output_array[$k+1] = $v;
					} else {
						$output_array[$k] = $v;
					}
				}
			}
		} else {
			$output_array = $input_array;
			$output_array[$index] = $value;
		}

		ksort($output_array);
		return $output_array;
	}

	public static function hide_from_pricing_table() {
		return true;
	}
}
