<?php
/**
 * PDF settings to control page layout for the PDF receipt
 *
 */
define( 'K_TCPDF_EXTERNAL_CONFIG', true ); //Use the config here.

define( 'K_PATH_MAIN', $this->plugin_dir . 'tcpdf/' );
define( 'K_PATH_URL', $this->plugin_url . 'tcpdf/' );
define( 'K_PATH_FONTS', K_PATH_MAIN . 'fonts/' );
define( 'K_PATH_CACHE', K_PATH_MAIN . 'cache/' );
define( 'K_PATH_URL_CACHE', K_PATH_URL . 'cache/' );
define( 'K_PATH_IMAGES', K_PATH_MAIN . 'images/' );
define( 'K_BLANK_IMAGE', K_PATH_IMAGES . '_blank.png' );
define( 'PDF_PAGE_FORMAT', 'A4' );
define( 'PDF_PAGE_ORIENTATION', 'P' );
define( 'PDF_CREATOR', 'TCPDF' );
define( 'PDF_AUTHOR', 'TCPDF' );
define( 'PDF_HEADER_TITLE', 'Receipt' );
define( 'PDF_HEADER_STRING', "" );
define( 'PDF_HEADER_LOGO', 'tcpdf_logo.jpg' );
define( 'PDF_HEADER_LOGO_WIDTH', 30 );
define( 'PDF_UNIT', 'mm' );
define( 'PDF_MARGIN_HEADER', 5 );
define( 'PDF_MARGIN_FOOTER', 10 );
define( 'PDF_MARGIN_TOP', 27 );
define( 'PDF_MARGIN_BOTTOM', 25 );
define( 'PDF_MARGIN_LEFT', 15 );
define( 'PDF_MARGIN_RIGHT', 15 );
define( 'PDF_FONT_NAME_MAIN', 'helvetica' );
define( 'PDF_FONT_SIZE_MAIN', 10 );
define( 'PDF_FONT_NAME_DATA', 'helvetica' );
define( 'PDF_FONT_SIZE_DATA', 8 );
define( 'PDF_FONT_MONOSPACED', 'courier' );
define( 'PDF_IMAGE_SCALE_RATIO', 1.25 );
define( 'HEAD_MAGNIFICATION', 1.1 );
define( 'K_CELL_HEIGHT_RATIO', 1.25 );
define( 'K_TITLE_MAGNIFICATION', 1.3 );
define( 'K_SMALL_RATIO', 2 / 3 );
define( 'K_THAI_TOPCHARS', true );
define( 'K_TCPDF_CALLS_IN_HTML', false );