<?php
/*
Pro Sites (Module: Premium Themes)
*/
class ProSites_Module_PremiumThemes {

	function ProSites_Module_PremiumThemes() {
		$this->__construct();
	}

	function __construct() {
		add_action( 'psts_page_after_modules', array(&$this, 'plug_network_page') );

		add_action( 'psts_settings_page', array(&$this, 'settings') );
		add_action( 'psts_withdraw', array(&$this, 'deactivate_theme') );
		add_action( 'psts_downgrade', array(&$this, 'deactivate_theme') );
		
		add_action( 'admin_print_styles-themes.php', array(&$this, 'themes_styles') );
		add_action( 'admin_footer-themes.php', array(&$this, 'themes_scripts') );
		
		add_action( 'customize_controls_print_footer_scripts', array(&$this, 'customize_controls_print_footer_scripts') );
		
		add_filter( 'theme_action_links', array(&$this, 'theme_action_links'), 100, 2);
		add_filter( 'site_option_allowedthemes', array(&$this, 'site_option_allowedthemes'), 100);
	}
	
	function plug_network_page() {
		$page = add_submenu_page( 'psts', __('Pro Sites Premium Themes', 'psts'), __('Premium Themes', 'psts'), 'manage_network_options', 'psts-themes', array(&$this, 'admin_page') );
	}
	
	function theme_action_links( $actions, $theme ) {
		global $psts, $blog_id;

		if ( is_network_admin() )
			return $actions;
		
		$ct = wp_get_theme();
	  
		$allowed_themes = $psts->get_setting('pt_allowed_themes');
		if ( $allowed_themes == false )
			$allowed_themes = array();
		
		if ( isset( $allowed_themes[ esc_html( $ct->stylesheet ) ] ) == false )
			$allowed_themes[ esc_html( $ct->stylesheet ) ] = true;
		
		if ( isset( $allowed_themes[ esc_html( $theme[ 'Stylesheet' ] ) ] ) && $allowed_themes[esc_html($theme['Stylesheet'])] &&
		     !is_pro_site($blog_id, $allowed_themes[ $theme[ 'Stylesheet' ] ]) && !$this->ads_theme() ) {
			
			$rebrand = sprintf( __('%s Only', 'psts'), $psts->get_level_setting($allowed_themes[ $theme[ 'Stylesheet' ] ], 'name') );
	  	$upgrade_notice = str_replace( 'LEVEL', $psts->get_level_setting($allowed_themes[ $theme[ 'Stylesheet' ] ], 'name'), $psts->get_setting('pt_text') );
			$actions['activate'] = '<a href="' . $psts->checkout_url($blog_id) .  '" class="activatelink nonpsts" data-level="' . $allowed_themes[ $theme[ 'Stylesheet' ] ] . '" title="' . esc_attr($upgrade_notice) . '">' . $rebrand . '</a>';	
		}
		
		return $actions;
	}
	
	function site_option_allowedthemes($themes) {
		global $psts;

		if ( is_network_admin() )
			return $themes;
		
		$allowed_themes = $psts->get_setting('pt_allowed_themes');
		if ( $allowed_themes == false )
			$allowed_themes = array();
		
		if (count($allowed_themes) > 0) {
			if (!is_array($themes)) {
				$themes = array();
			}
			
			foreach ($allowed_themes as $key => $allowed_theme) {
				$themes[$key] = $allowed_theme;
			}
		}
		
		return $themes;
	}
	
	function themes_styles() {
		echo '<style type="text/css">
			a.nonpsts {color:red;}
			div.level-1 a.screenshot {box-shadow: 0 43px 30px -30px #EAFFEF;}
			div.level-2 a.screenshot {box-shadow: 0 43px 30px -30px #E6FCFF;}
			div.level-3 a.screenshot {box-shadow: 0 43px 30px -30px #EEEEFF;}
			div.level-4 a.screenshot {box-shadow: 0 43px 30px -30px #FCFCE9;}
			div.level-5 a.screenshot {box-shadow: 0 43px 30px -30px #FFECFF;}
			div.level-6 a.screenshot {box-shadow: 0 43px 30px -30px #DBF0F7;}
			div.level-7 a.screenshot {box-shadow: 0 43px 30px -30px #FFECEC;}
		</style>';
	}
	
	function themes_scripts() {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function() {
			var specialThemes = jQuery("a[data-level]");
			//alert(test);
			jQuery.each(specialThemes, function(index, value) { 
				jQuery(value).parents(".available-theme").addClass("level-"+jQuery(value).attr('data-level'));
			});
		});
		</script>
		<?php
	}

	function deactivate_theme($blog_id) {
    global $psts;
    
		$current_theme = get_blog_option($blog_id, 'stylesheet');
    $psts_allowed_themes = $psts->get_setting('pt_allowed_themes');
    
    //if not using pro theme skip
    if ( !isset($psts_allowed_themes[$current_theme]) )
      return;
    
    //if they have permission for this theme skip
    if ( is_pro_site($blog_id, $psts_allowed_themes[$current_theme]) || $this->ads_theme() )
      return;
    
	  //check for our default theme plugin first
	  if (function_exists('default_theme_switch_theme')) {
	    default_theme_switch_theme($blog_id);
	  } else {
	    switch_to_blog($blog_id);
	    switch_theme( WP_DEFAULT_THEME, WP_DEFAULT_THEME );
	    restore_current_blog();
	  }
	}
	
	//for ads module to allow premium themes
	function ads_theme() {
		global $psts;
    
		if (function_exists('psts_hide_ads') && $psts->get_setting('ads_themes') && psts_hide_ads())
	    return true;
	  else
	    return false;
	}
	
	function settings() {
	  global $psts;
		?>
		<div class="postbox">
		  <h3 class='hndle'><span><?php _e('Premium Themes', 'psts') ?></span> - <span class="description"><?php _e('Allows you to give access to selected themes to a Pro Site level.', 'psts') ?></span></h3>
		  <div class="inside">
			  <table class="form-table">
				  <tr valign="top">
				  <th scope="row"><?php _e('Rename Feature', 'psts') ?></th>
				  <td>
				  <input type="text" name="psts[pt_name]" value="<?php echo esc_attr($psts->get_setting('pt_name', __('Premium Themes', 'psts'))); ?>" size="30" />
				  <br /><?php _e('Required - No HTML! - Make this short and sweet.', 'psts') ?></td>
				  </tr>
				  <tr valign="top">
				  <th scope="row"><?php _e('Theme Preview Message', 'psts') ?></th>
				  <td>
				  <input type="text" name="psts[pt_text]" value="<?php echo esc_attr($psts->get_setting('pt_text', __('Upgrade to LEVEL to activate this premium theme &raquo;', 'psts'))); ?>" style="width: 95%" />
				  <br /><?php _e('Required - No HTML! - This message is displayed when the wrong level site is previewing a premium theme. "LEVEL" will be replaced with the needed level name for that theme.', 'psts') ?></td>
				  </tr>
			  </table>
		  </div>
		</div>
	  <?php
	}

	function admin_page() {
    global $psts;
    
    if (isset($_POST['save_themes'])) {
      //check nonce
      check_admin_referer('psts_themes');

			$psts_allowed_themes = array();

      if (is_array($_POST['theme'])) {
        foreach ($_POST['theme'] as $theme => $value) {
					if ( $value ) //only add themes with a level
						$psts_allowed_themes[$theme] = $value;
        }
        $psts->update_setting('pt_allowed_themes', $psts_allowed_themes);
      } else {
        $psts->update_setting('pt_allowed_themes', array(0));
      }

      echo '<div id="message" class="updated fade"><p>' . __('Settings Saved!', 'psts') . '</p></div>';
    }

    // Site Themes
    $themes = wp_get_themes();
    $psts_allowed_themes = $psts->get_setting('pt_allowed_themes');
    $allowed_themes = get_site_option( "allowedthemes" );
    if( $allowed_themes == false ) {
    	$allowed_themes = array();
    }
		$levels = (array)get_site_option('psts_levels');
	  ?>
    <div class="wrap">
    <div class="icon32" id="icon-themes"></div>
    <h2><?php _e('Premium Themes', 'psts'); ?></h2>
    <p><?php _e('Select the minimum Pro Site level for premium themes that you want to enable for sites of that level or above. Only <a href="themes.php?theme_status=disabled">disabled network themes</a> are shown in this list. ', 'psts'); ?></p>

		<form method="post" action="">
    <?php wp_nonce_field('psts_themes') ?>
    
  	<table class="widefat">
			<thead>
				<tr>
					<th style="width:15%;"><?php _e('Minimum Level', 'psts') ?></th>
					<th style="width:25%;"><?php _e('Theme', 'psts') ?></th>
					<th style="width:10%;"><?php _e('Version', 'psts') ?></th>
					<th style="width:60%;"><?php _e('Description', 'psts') ?></th>
				</tr>
			</thead>
			<tbody id="plugins">
			<?php
			$class = '';
			foreach( (array) $themes as $key => $theme ) {
				$theme_key = esc_html($theme['Stylesheet']);
				$class = ('alt' == $class) ? '' : 'alt';

		    if( !isset($allowed_themes[$theme_key]) ) {

  				?>
  				<tr valign="top" class="<?php echo $class; ?>">
  					<td>
            <select name="theme[<?php echo $theme_key ?>]">
             <option value="0"><?php _e('None', 'psts') ?></option>
             <?php
						 foreach ($levels as $key => $value) {
							?><option value="<?php echo $key; ?>"<?php selected(@$psts_allowed_themes[$theme_key], $key) ?>><?php echo $key . ': ' . esc_attr($value['name']); ?></option><?php
						 }
						 ?>
            </select>
            </td>
  					<td><?php echo $theme['Name']; ?></td>
  					<td><?php echo $theme['Version']; ?></td>
  					<td><?php echo $theme['Description']; ?></td>
  				</tr>
  			<?php
        }
      } ?>
			</tbody>
		</table>

		<p class="submit"><input type="submit" name="save_themes" class="button-primary" value="<?php _e('Save Changes', 'psts') ?>" /></p>
  	</form>
		</div>
	  <?php
	}
	
	function customize_controls_print_footer_scripts() {
		global $psts, $blog_id;
		
		$theme = wp_get_theme($_REQUEST['theme']);
		
		$allowed_themes = $psts->get_setting('pt_allowed_themes');
		if ( $allowed_themes == false )
			$allowed_themes = array();
			
		if ( isset( $allowed_themes[ esc_html( $theme[ 'Stylesheet' ] ) ] ) && $allowed_themes[esc_html($theme['Stylesheet'])] &&
		     !is_pro_site($blog_id, $allowed_themes[ $theme[ 'Stylesheet' ] ]) && !$this->ads_theme() ) {
			
			$rebrand = sprintf( __('%s Only', 'psts'), $psts->get_level_setting($allowed_themes[ $theme[ 'Stylesheet' ] ], 'name') );
	  	$upgrade_notice = str_replace( 'LEVEL', $psts->get_level_setting($allowed_themes[ $theme[ 'Stylesheet' ] ], 'name'), $psts->get_setting('pt_text') );
			$upgrade_link = '<a href="' . $psts->checkout_url($blog_id) .  '" class="activatelink nonpsts button-primary" title="' . esc_attr($upgrade_notice) . '">' . $rebrand . '</a>';	
			?>
			<script type="text/javascript">
				jQuery('#save').remove();
				jQuery('#customize-header-actions').prepend('<?php echo $upgrade_link; ?>');
			</script>
			<?php
		}
	}
}

//register the module
psts_register_module( 'ProSites_Module_PremiumThemes', __('Premium Themes', 'psts'), __('Allows you to give access to selected themes to a Pro Site level.', 'psts') );
?>