<?php
class ProSitesTestsUtility {
	public static $bCloned = false;

	public static function cloneDB() {

		// Don't do this too often
		if( ! self::$bCloned ) {

			self::copyFromLive( 'blogs' );
			self::copyFromLive( 'blog_versions' );
			self::copyFromLive( 'commentmeta' );
			self::copyFromLive( 'comments' );
			self::copyFromLive( 'links' );
			self::copyFromLive( 'options' );
			self::copyFromLive( 'postmeta' );
			self::copyFromLive( 'posts' );
			self::copyFromLive( 'pro_sites' );
			self::copyFromLive( 'pro_sites_daily_stats' );
			self::copyFromLive( 'pro_sites_signup_stats' );
			self::copyFromLive( 'registration_log' );
			self::copyFromLive( 'signups' );
			self::copyFromLive( 'site' );
			self::copyFromLive( 'sitemeta' );
			self::copyFromLive( 'terms' );
			self::copyFromLive( 'term_relationships' );
			self::copyFromLive( 'term_taxonomy' );
			self::copyFromLive( 'usermeta' );
			self::copyFromLive( 'users' );
		}

		self::$bCloned = true;
	}

	public static function copyFromLive( $tablename ) {
		global $wpdb;
		$wpdb->query( "TRUNCATE wptests_{$tablename};" );
		$wpdb->query( "INSERT INTO wptests_{$tablename} SELECT * FROM wp_{$tablename};" );
	}

	public static function setupCoupons() {

		$coupons = get_site_option( 'psts_coupons');
		if( !empty( $coupons ) ) {
			return $coupons;
		}

		$coupons = array(
			'ALLANY' => array(
				'discount'         => 100.0,
				'discount_type'    => 'pct',
				'valid_for_period' => array(
					0 => '0',
				),
				'start'            => 1423699200,
				'end'              => false,
				'level'            => 0,
				'uses'             => '',
			),
			'ABC'    => array(
				'discount'         => 50.0,
				'discount_type'    => 'amt',
				'valid_for_period' => array(
					0 => '1',
					1 => '12',
				),
				'start'            => 1423785600,
				'end'              => 1426204800,
				'level'            => 2,
				'uses'             => 10,
			),
			'EXTRA'  => array(
				'discount'         => 11.0,
				'discount_type'    => 'amt',
				'valid_for_period' => null,
				'start'            => 1423785600,
				'end'              => false,
				'level'            => 0,
				'uses'             => '',
			),
		);

		update_site_option( 'psts_coupons', $coupons );
//		return $coupons;
	}

	public static function setupSites( $object ) {

		$sites = wp_get_sites();
		if( count( $sites ) < 5 ) {
//			$object->factory->blog->create_many( 5 );
			$object->factory_create_many( 'blog', 5 );
		}

		return true;
	}


	public static function setupData( $object ) {

		// Setup the blogs
		self::setupSites( $object );
		echo "\n== Sites created. ==";

		// Setup coupon data
		self::setupCoupons();
		echo "\n== Coupons added. ==";

	}



}