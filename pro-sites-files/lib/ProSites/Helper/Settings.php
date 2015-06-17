<?php

if ( ! class_exists( 'ProSites_Helper_Settings' ) ) {

	class ProSites_Helper_Settings {

		public static function settings_header( $args = null ) {
			$defaults = array(
				'title' => '',
				'title_icon_class' => '',
				'desc' => '',
				'bread_crumbs' => null,
				'page_header' => false,
				'header_save_button' => false,
				'button_name' => null,
			);
			$args = wp_parse_args( $args, $defaults );
			$args = apply_filters( 'prosites_helper_html_settings_header_args', $args );
			extract( $args );

			if ( ! is_array( $desc ) ) {
				$desc = array( $desc );
			}

			$context = ! empty( $page_header ) ? 'psts-settings-header-' : 'psts-settings-';
			$header_tag = ! empty( $page_header ) ? 'h2' : 'h3';

//			MS_Helper_Html::bread_crumbs( $bread_crumbs );
			?>
			<div class="<?php echo esc_attr( $context ); ?>title-wrapper">

			<?php if ( empty( $page_header) ) { ?>
				<input type="hidden" name="active_tab" value="<?php echo esc_attr( $tab_key ); ?>" />
			<?php } ?>

			<?php
			if( ! empty( $header_save_button ) && ! empty( $button_name ) ) {
				?>
				<p class="header-save-button">
					<input type="submit" name="submit_<?php echo esc_attr( $button_name ); ?>_header" class="button-primary" value="<?php _e( 'Save Changes', 'psts' ) ?>"/>
				</p>
				<?php
			}
			?>
			<<?php echo $header_tag; ?> class="<?php echo esc_attr( $context ); ?>title">
				<?php if ( ! empty( $title_icon_class ) ) : ?>
					<i class="<?php echo esc_attr( $title_icon_class ); ?>"></i>
				<?php endif; ?>
				<?php printf( $title ); ?>
			</<?php echo $header_tag; ?>>
			<div class="<?php echo esc_attr( $context ); ?>desc-wrapper">
				<?php foreach ( $desc as $description ) : ?>
					<div class="<?php echo esc_attr( $context ); ?>desc psts-description">
						<?php printf( $description ); ?>
					</div>
				<?php endforeach; ?>
			</div>
			</div>
		<?php
		}






	}


}