<?php
require_once( dirname( __FILE__ ) . '/objects/prosites-tests-utility.php' );

class TestCouponCheck extends WP_UnitTestCase {

	function test_check_coupon() {

		ProSitesTestsUtility::cloneDB();
		$this->assertFalse( ProSites_Helper_Coupons::check_coupon( 'FALSEVALUE' ) );
		$this->assertTrue( ProSites_Helper_Coupons::check_coupon( 'ABC' ) );

	}

	function test_get_adjusted_level_amounts() {

		ProSitesTestsUtility::cloneDB();

		$test_coupons = array( 'AL0U0P0', 'ABC', 'PL0U0P312', 'PL1U10P312', 'PL2U0P112', 'PL3U0P13' );

		foreach( $test_coupons as $coupon ) {
			echo $coupon . "\n";
			$level_list = ProSites_Helper_Coupons::get_adjusted_level_amounts( $coupon );
			$expected_level_list = $this->_get_expected( $coupon, $level_list );
			$this->assertEquals( $expected_level_list, $level_list );
		}

	}

	private function _get_expected( $coupon, $returned ) {

		$coupon = preg_replace( '/[^A-Z0-9_-]/', '', strtoupper( $coupon ) );
		/*
		 * Level 1 - 5, 10, 50
		 * Level 2 - 10, 20, 100
		 * Level 3 - 15, 35, 150
		 */

		$expected = array(
			'ABC' => array(
				'1' => array(
					'price_1' => 0.0,
					'price_3' => 0.0,
					'price_12' => 0.0,
				),
				'2' => array(
					'price_1' => 0.0,
					'price_3' => 0.0,
					'price_12' => 0.0,
				),
				'3' => array(
					'price_1' => 0.0,
					'price_3' => 0.0,
					'price_12' => 0.0,
				),
			),
			'PL0U0P312' => array(
				'1' => array(
					'price_1' => false,
					'price_3' => 0.0,
					'price_12' => 0.0,
				),
				'2' => array(
					'price_1' => false,
					'price_3' => 0.0,
					'price_12' => 0.0,
				),
				'3' => array(
					'price_1' => false,
					'price_3' => 0.0,
					'price_12' => 0.0,
				),
			),
			'PL1U10P312' => array(
				'1' => array(
					'price_1' => false,
					'price_3' => 0.0,
					'price_12' => 0.0,
				),
				'2' => false,
				'3' => false,
			),
			'PL2U0P112' => array(
				'1' => false,
				'2' => array(
					'price_1' => 0.0,
					'price_3' => false,
					'price_12' => 0.0,
				),
				'3' => false,
			),
			'PL3U0P13' => array(
				'1' => false,
				'2' => false,
				'3' => array(
					'price_1' => 0.0,
					'price_3' => 0.0,
					'price_12' => false,
				),
			),
			'AL0U0P0' =>  array(
				'1' => array(
					'price_1' => 1.0,
					'price_3' => 6.0,
					'price_12' => 46.0,
				),
				'2' => array(
					'price_1' => 6.0,
					'price_3' => 16.0,
					'price_12' => 96.0,
				),
				'3' => array(
					'price_1' => 11.0,
					'price_3' => 31.0,
					'price_12' => 146.0,
				),
			),
		);

		$compare = array();

		foreach( $returned as $index => $level ) {
			$keys = array_keys( $level );

			$compare[ $index ] = array();
			foreach( $keys as $key ) {
				if( 'price_1' != $key && 'price_3' != $key && 'price_12' != $key ) {
					$compare[ $index ][ $key ] = $returned[ $index ][ $key ];
				} else {
					$use_sample = $expected[ $coupon ][ $index ];
					if( ! $use_sample ) {
						$compare[ $index ][ $key ] = $returned[ $index ][ $key ];
					} else {
						$use_price_sample = $expected[ $coupon ][ $index ][ $key ];
						if( false === $use_price_sample  ) {
							$compare[ $index ][ $key ] = $returned[ $index ][ $key ];
						} else {
							$compare[ $index ][ $key ] = $expected[ $coupon ][ $index ][ $key ];
						}
					}
				}
			}
		}

		return $compare;
	}

}