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
		add_action( 'admin_menu', array(&$this, 'plug_page') );
		//add_action( 'psts_admin_bar', array(&$this, 'add_menu_admin_bar') );

		add_action( 'psts_settings_page', array(&$this, 'settings') );
		add_action( 'admin_notices', array(&$this, 'message_output') );
		add_action( 'psts_withdraw', array(&$this, 'deactivate_theme') );
		add_action( 'psts_downgrade', array(&$this, 'deactivate_theme') );
	}
	
	function plug_network_page() {
	  $page = add_submenu_page( 'psts', __('Pro Sites Premium Themes', 'psts'), __('Premium Themes', 'psts'), 'manage_network_options', 'psts-themes', array(&$this, 'admin_page') );
	}

	function plug_page() {
    global $submenu, $psts;
		$page = add_submenu_page('themes.php', $psts->get_setting('pt_name'), $psts->get_setting('pt_name'), 'switch_themes', 'premium-themes', array(&$this, 'themes_page') );
	  add_action('admin_print_scripts-' . $page, array(&$this, 'page_scripts') );

	  //add it under pro blogs menu also
		if ( !defined('PSTS_HIDE_THEMES_MENU') ) {
			$page = add_submenu_page('psts-checkout', $psts->get_setting('pt_name'), $psts->get_setting('pt_name'), 'switch_themes', 'premium-themes', array(&$this, 'themes_page') );
			add_action('admin_print_scripts-' . $page, array(&$this, 'page_scripts') );
		}
	}

  function add_menu_admin_bar() {
    global $wp_admin_bar, $psts;

    if ( !current_user_can('switch_themes') )
      return;
        
    $wp_admin_bar->add_menu( array( 'id' => 'psts-themes', 'parent' => 'pro-site', 'title' => $psts->get_setting('pt_name'), 'href' => admin_url('themes.php?page=premium-themes') ) );
    $wp_admin_bar->add_menu( array( 'id' => 'psts-themes-sub', 'parent' => 'themes', 'title' => $psts->get_setting('pt_name'), 'href' => admin_url('themes.php?page=premium-themes') ) );
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

	function message_output() {
	  global $psts, $current_screen;
	  
	  //advertises premium themes on the main themes page.
	  if ($current_screen->id == 'themes')
	   	echo '<div class="updated fade"><p style="font-weight:bold;">'.sprintf(__('Be sure to check out our <a title="%s" href="themes.php?page=premium-themes">%s &raquo;</a>', 'psts'), $psts->get_setting('pt_name'), $psts->get_setting('pt_name')).'</a></p></div>';
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
    $themes = get_themes();
    $psts_allowed_themes = $psts->get_setting('pt_allowed_themes');
    $allowed_themes = get_site_option( "allowedthemes" );
    if( $allowed_themes == false ) {
    	$allowed_themes = array_keys( $themes );
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

		    if( !isset($allowed_themes[$theme_key] ) ) {

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
	
  function page_scripts() {
		global $current_screen;
				
	  if ( current_user_can( 'switch_themes' ) && isset($_GET['action'] ) ) {
			if ( 'activate' == $_GET['action'] ) {
				check_admin_referer('switch-theme_' . $_GET['template']);
				switch_theme($_GET['template'], $_GET['stylesheet']);
				$_GET['activated'] = 'true';
			}
		}
		
	  //add scripts and css
	  add_thickbox();
	  wp_enqueue_script( 'theme-preview' );
	  $css = get_bloginfo('wpurl')."/wp-includes/js/thickbox/thickbox.css";
		wp_enqueue_style('thickbox_css', $css, false, false, 'screen');
		wp_print_styles(array('thickbox_css'));
		
		$help = '<p>' . __('You can see your active theme at the top of the screen. Below are the other themes available that are not currently in use. You can see what your site would look like with one of these themes by clicking the Preview link. To change themes, click the Activate link or upgrade to the needed level.', 'psts') . '</p>';
		add_contextual_help($current_screen, $help);

		//add per level styles
	  echo '<style type="text/css">
						a.nonpsts {color:red;}
						td.level-1 {background-color: #EAFFEF}
						td.level-2 {background-color: #E6FCFF}
						td.level-3 {background-color: #EEEEFF}
						td.level-4 {background-color: #FCFCE9}
						td.level-5 {background-color: #FFECFF}
						td.level-6 {background-color: #DBF0F7}
						td.level-7 {background-color: #FFECEC}
					</style>';
	}
	
	function themes_page() {
		global $psts, $wp_registered_sidebars, $blog_id;

	  $themes = get_themes();
	  $ct = current_theme_info();
	  
	  $allowed_themes = $psts->get_setting('pt_allowed_themes');
	  if( $allowed_themes == false )
	  	$allowed_themes = array();

	  if( isset( $allowed_themes[ esc_html( $ct->stylesheet ) ] ) == false )
	      $allowed_themes[ esc_html( $ct->stylesheet ) ] = true;

	  reset( $themes );
	  
	  //remove themes with no permission
	  foreach( $themes as $key => $theme ) {
      if ( isset( $allowed_themes[ esc_html( $theme[ 'Stylesheet' ] ) ] ) == false ) {
  			unset( $themes[ $key ] );
      } else if ( !$psts->get_level_setting($allowed_themes[esc_html($theme['Stylesheet'])], 'name') ) {
        unset( $themes[ $key ] );
			}
	  }
	  reset( $themes );

	  $title = $psts->get_setting('pt_name', __('Premium Themes', 'psts'));
	  $parent_file = 'themes.php?page=premium-themes';

	  ?>

	 <?php if ( isset($_GET['activated']) ) :
	  		if ( isset($wp_registered_sidebars) && count( (array) $wp_registered_sidebars ) ) { ?>
	  <div id="message2" class="updated fade"><p><?php printf(__('New theme activated. This theme supports widgets, please visit the <a href="%s">widgets settings page</a> to configure them.'), admin_url('widgets.php') ); ?></p></div><?php
	  		} else { ?>
	  <div id="message2" class="updated fade"><p><?php printf(__('New theme activated. <a href="%s">Visit site</a>'), get_bloginfo('url') . '/'); ?></p></div><?php
	  		}
	  endif; ?>

	  <?php
	  unset($themes[$ct->name]);

	  uksort( $themes, "strnatcasecmp" );
	  $theme_total = count( $themes );
	  $per_page = 30;

	  if ( isset( $_GET['pagenum'] ) )
	  	$page = absint( $_GET['pagenum'] );

	  if ( empty($page) )
	  	$page = 1;

	  $start = $offset = ( $page - 1 ) * $per_page;

	  $page_links = paginate_links( array(
	  	'base' => add_query_arg( 'pagenum', '%#%' ) . '#themenav',
	  	'format' => '',
	  	'prev_text' => __('&laquo;'),
	  	'next_text' => __('&raquo;'),
	  	'total' => ceil($theme_total / $per_page),
	  	'current' => $page
	  ));

	  $themes = array_slice( $themes, $start, $per_page );

	  ?>

	  <div class="wrap">
	  <?php screen_icon(); ?>
	  <h2><?php echo esc_html( $title ); ?></h2>

	  <h3><?php _e('Current Theme'); ?></h3>
	  <div id="current-theme">
	  <?php if ( $ct->screenshot ) : ?>
	  <img src="<?php echo $ct->theme_root_uri . '/' . $ct->stylesheet . '/' . $ct->screenshot; ?>" alt="<?php _e('Current theme preview'); ?>" />
	  <?php endif; ?>
	  <h4><?php
	  	/* translators: 1: theme title, 2: theme version, 3: theme author */
	  	printf(__('%1$s %2$s'), $ct->title, $ct->version) ; ?></h4>
	  <p class="theme-description"><?php echo $ct->description; ?></p>
	  <?php if ( $ct->tags ) : ?>
	  <p><?php _e('Tags:'); ?> <?php echo join(', ', $ct->tags); ?></p>
	  <?php endif; ?>

	  </div>

	  <div class="clear"></div>

	  <h3><?php _e('Available Premium Themes', 'psts'); ?></h3>

		<div class="clear"></div>

	  <?php if ( $theme_total ) { ?>

	  <?php if ( $page_links ) : ?>
	  <div class="tablenav">
	  <div class="tablenav-pages"><?php $page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s',
	  	number_format_i18n( $start + 1 ),
	  	number_format_i18n( min( $page * $per_page, $theme_total ) ),
	  	number_format_i18n( $theme_total ),
	  	$page_links
	  ); echo $page_links_text; ?></div>
	  </div>
	  <?php endif; ?>

	  <table id="availablethemes" cellspacing="0" cellpadding="0">
	  <?php
	  $style = '';

	  $theme_names = array_keys($themes);
	  natcasesort($theme_names);

	  $table = array();
	  $rows = ceil(count($theme_names) / 3);
	  for ( $row = 1; $row <= $rows; $row++ )
	  	for ( $col = 1; $col <= 3; $col++ )
	  		$table[$row][$col] = array_shift($theme_names);

	  foreach ( $table as $row => $cols ) {
	  ?>
	  <tr>
	  <?php
	  foreach ( $cols as $col => $theme_name ) {
      //pro blog control
	  	$level = @$allowed_themes[ esc_html( $themes[$theme_name]['Stylesheet'] ) ];

			$class = array('available-theme');
	  	if ( $row == 1 ) $class[] = 'top';
	  	if ( $col == 1 ) $class[] = 'left';
	  	if ( $row == $rows ) $class[] = 'bottom';
	  	if ( $col == 3 ) $class[] = 'right';
	  	
	  	$class[] = 'level-' . $level;
	  ?>
	  	<td class="<?php echo join(' ', $class); ?>">
	  <?php if ( !empty($theme_name) ) :
	  	$template = $themes[$theme_name]['Template'];
	  	$stylesheet = $themes[$theme_name]['Stylesheet'];
	  	$title = $themes[$theme_name]['Title'];
	  	$version = $themes[$theme_name]['Version'];
	  	$description = $themes[$theme_name]['Description'];
	  	$author = $themes[$theme_name]['Author'];
	  	$screenshot = $themes[$theme_name]['Screenshot'];
	  	$stylesheet_dir = $themes[$theme_name]['Stylesheet Dir'];
	  	$template_dir = $themes[$theme_name]['Template Dir'];
	  	$parent_theme = $themes[$theme_name]['Parent Theme'];
	  	$theme_root = $themes[$theme_name]['Theme Root'];
	    $theme_root_uri = $themes[$theme_name]['Theme Root URI'];
	  	$preview_link = esc_url(get_option('home') . '/');
	  	if ( is_ssl() )
	  		$preview_link = str_replace( 'http://', 'https://', $preview_link );
	  	$preview_link = htmlspecialchars( add_query_arg( array('preview' => 1, 'template' => $template, 'stylesheet' => $stylesheet, 'TB_iframe' => 'true' ), $preview_link ) );
	  	$preview_text = esc_attr( sprintf( __('Preview of &#8220;%s&#8221;'), $title ) );
	  	$tags = $themes[$theme_name]['Tags'];
	  	$thickbox_class = 'thickbox thickbox-preview';
	  	$activate_link = wp_nonce_url("themes.php?page=premium-themes&action=activate&amp;template=".urlencode($template)."&amp;stylesheet=".urlencode($stylesheet), 'switch-theme_' . $template);
	  	$activate_text = esc_attr( sprintf( __('Activate &#8220;%s&#8221;'), $title ) );
	  	$actions = array();

	  	if ( is_pro_site(false, $level) || $this->ads_theme() ) {
	      $actions[] = '<a href="' . $activate_link .  '" class="activatelink" title="' . $activate_text . '">' . __('Activate') . '</a>';
	  	} else {
	  	  $rebrand = sprintf( __('%s Only', 'psts'), $psts->get_level_setting($level, 'name') );
	  	  $upgrade_notice = str_replace( 'LEVEL', $psts->get_level_setting($level, 'name'), $psts->get_setting('pt_text') );
	      $actions[] = '<a href="' . $psts->checkout_url($blog_id) .  '" class="activatelink nonpsts" title="' . esc_attr($upgrade_notice) . '">' . $rebrand . '</a>';
			}
			
	    $actions[] = '<a href="' . $preview_link . '" class="thickbox thickbox-preview" title="' . esc_attr(sprintf(__('Preview &#8220;%s&#8221;'), $theme_name)) . '">' . __('Preview') . '</a>';
	  	$actions = apply_filters('theme_action_links', $actions, $themes[$theme_name]);

	  	$actions = implode ( ' | ', $actions );
	  ?>
	  		<a href="<?php echo $preview_link; ?>" class="<?php echo $thickbox_class; ?> screenshot">
	  <?php if ( $screenshot ) : ?>
	  		<img src="<?php echo $theme_root_uri . '/' . $stylesheet . '/' . $screenshot; ?>" alt="" />
	  <?php endif; ?>
	  		</a>
	  <h3><?php
	  	/* translators: 1: theme title, 2: theme version, 3: theme author */
	  	printf(__('%1$s %2$s'), $title, $version) ; ?></h3>
	  <p class="description"><?php echo $description; ?></p>
	  <span class='action-links'><?php echo $actions ?></span>
	  <?php if ( $tags ) : ?>
	  <p><?php _e('Tags:'); ?> <?php echo join(', ', $tags); ?></p>
	  <?php endif; ?>

	  <?php endif; // end if not empty theme_name ?>
	  	</td>
	  <?php } // end foreach $cols ?>
	  </tr>
	  <?php } // end foreach $table ?>
	  </table>
	  <?php } else { ?>
	  <p><?php _e('There are no premium themes available at the moment so there is nothing to show you here.', 'psts'); ?></p>
	  <?php } // end if $theme_total?>
	  <br class="clear" />

	  <?php if ( $page_links ) : ?>
	  <div class="tablenav">
	  <?php echo "<div class='tablenav-pages'>$page_links_text</div>"; ?>
	  <br class="clear" />
	  </div>
	  <?php endif; ?>

	  <br class="clear" />
	  </div>
	  </form>
	  <?php
	}
	
}

//register the module
psts_register_module( 'ProSites_Module_PremiumThemes', __('Premium Themes', 'psts'), __('Allows you to give access to selected themes to a Pro Site level.', 'psts') );
?>