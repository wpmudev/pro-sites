<?php

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

/**
 * The stripe data helper of the plugin.
 *
 * @link   https://premium.wpmudev.org/project/pro-sites
 * @since  3.6.1
 *
 * @author Joel James <joel@incsub.com>
 */
class ProSites_Stripe_Data {

	/**
	 * Stripe merchant countries list.
	 *
	 * @since 3.6.1
	 *
	 * @return array
	 */
	public static function countries() {
		$countries = array(
			'AU' => 'Australia',
			'CA' => 'Canada',
			'IE' => 'Ireland',
			'UK' => 'United Kingdom',
			'US' => 'United States',
			'BE' => 'Belgium',
			'FI' => 'Finland',
			'FR' => 'France',
			'DE' => 'Germany',
			'LU' => 'Luxembourg',
			'NL' => 'Netherlands',
			'ES' => 'Spain',
			'DK' => 'Denmark',
			'NO' => 'Norway',
			'SE' => 'Sweden',
			'AT' => 'Austria',
			'IT' => 'Italy',
			'CH' => 'Switzerland',
		);

		/**
		 * Filter to alter Stripe merchant countries.
		 *
		 * @param array $countries Supported countries.
		 *
		 * @since 3.6.1
		 */
		return apply_filters( 'pro_sites_stripe_merchant_countries', $countries );
	}

	/**
	 * Stripe supported currencies.
	 *
	 * @since 3.6.1
	 *
	 * @return array
	 */
	public static function currencies() {
		$currencies = array(
			'AED' => array( 'United Arab Emirates Dirham', '62F, 2E ,625' ),
			'AFN' => array( 'Afghan Afghani', '60b' ),
			'ALL' => array( 'Albanian Lek', '4c, 65, 6b' ),
			'AMD' => array( 'Armenian Dram', '58F' ),
			'ANG' => array( 'Netherlands Antillean Gulden', '192' ),
			'AOA' => array( 'Angolan Kwanza', '4B, 7A' ),
			'ARS' => array( 'Argentine Peso', '24' ),
			'AUD' => array( 'Australian Dollar', '24' ),
			'AWG' => array( 'Aruban Florin', '192' ),
			'AZN' => array( 'Azerbaijani Manat', '43c, 430, 43d' ),
			'BAM' => array( 'Bosnia & Herzegovina Convertible Mark', '4b, 4d' ),
			'BBD' => array( 'Barbadian Dollar', '24' ),
			'BDT' => array( 'Bangladeshi Taka', '09F3' ),
			'BGN' => array( 'Bulgarian Lev', '43b, 432' ),
			'BIF' => array( 'Burundian Franc', '46, 42, 75' ),
			'BMD' => array( 'Bermudian Dollar', '24' ),
			'BND' => array( 'Brunei Dollar', '24' ),
			'BOB' => array( 'Bolivian Boliviano', '24, 62' ),
			'BRL' => array( 'Brazilian Real', '52, 24' ),
			'BSD' => array( 'Bahamian Dollar', '24' ),
			'BWP' => array( 'Botswana Pula', '50' ),
			'BZD' => array( 'Belize Dollar', '42, 5a, 24' ),
			'CAD' => array( 'Canadian Dollar', '24' ),
			'CDF' => array( 'Congolese Franc', '46, 43' ),
			'CHF' => array( 'Swiss Franc', '43, 48, 46' ),
			'CLP' => array( 'Chilean Peso', '24' ),
			'CNY' => array( 'Chinese Renminbi Yuan', 'a5' ),
			'COP' => array( 'Colombian Peso', '24' ),
			'CRC' => array( 'Costa Rican Colón', '20a1' ),
			'CVE' => array( 'Cape Verdean Escudo', '24' ),
			'CZK' => array( 'Czech Koruna', '4b, 10d' ),
			'DJF' => array( 'Djiboutian Franc', '46, 64, 6A' ),
			'DKK' => array( 'Danish Krone', '6b, 72' ),
			'DOP' => array( 'Dominican Peso', '52, 44, 24' ),
			'DZD' => array( 'Algerian Dinar', '62F, 62C' ),
			'EEK' => array( 'Estonian Kroon', '6b, 72' ),
			'EGP' => array( 'Egyptian Pound', 'a3' ),
			'ETB' => array( 'Ethiopian Birr', '1265, 122D' ),
			'EUR' => array( 'Euro', '20ac' ),
			'FJD' => array( 'Fijian Dollar', '24' ),
			'FKP' => array( 'Falkland Islands Pound', 'a3' ),
			'GBP' => array( 'British Pound', 'a3' ),
			'GEL' => array( 'Georgian Lari', '10DA' ),
			'GIP' => array( 'Gibraltar Pound', 'a3' ),
			'GMD' => array( 'Gambian Dalasi', '44' ),
			'GNF' => array( 'Guinean Franc', '46, 47' ),
			'GTQ' => array( 'Guatemalan Quetzal', '51' ),
			'GYD' => array( 'Guyanese Dollar', '24' ),
			'HKD' => array( 'Hong Kong Dollar', '24' ),
			'HNL' => array( 'Honduran Lempira', '4c' ),
			'HRK' => array( 'Croatian Kuna', '6b, 6e' ),
			'HTG' => array( 'Haitian Gourde', '47' ),
			'HUF' => array( 'Hungarian Forint', '46, 74' ),
			'IDR' => array( 'Indonesian Rupiah', '52, 70' ),
			'ILS' => array( 'Israeli New Sheqel', '20aa' ),
			'INR' => array( 'Indian Rupee', '20B9' ),
			'ISK' => array( 'Icelandic Króna', '6b, 72' ),
			'JMD' => array( 'Jamaican Dollar', '4a, 24' ),
			'JPY' => array( 'Japanese Yen', 'a5' ),
			'KES' => array( 'Kenyan Shilling', '4B, 53, 68' ),
			'KGS' => array( 'Kyrgyzstani Som', '43b, 432' ),
			'KHR' => array( 'Cambodian Riel', '17db' ),
			'KMF' => array( 'Comorian Franc', '43, 46' ),
			'KRW' => array( 'South Korean Won', '20a9' ),
			'KYD' => array( 'Cayman Islands Dollar', '24' ),
			'KZT' => array( 'Kazakhstani Tenge', '43b, 432' ),
			'LAK' => array( 'Lao Kip', '20ad' ),
			'LBP' => array( 'Lebanese Pound', 'a3' ),
			'LKR' => array( 'Sri Lankan Rupee', '20a8' ),
			'LRD' => array( 'Liberian Dollar', '24' ),
			'LSL' => array( 'Lesotho Loti', '4C' ),
			'LTL' => array( 'Lithuanian Litas', '4c, 74' ),
			'LVL' => array( 'Latvian Lats', '4c, 73' ),
			'MAD' => array( 'Moroccan Dirham', '62F, 2E, 645, 2E' ),
			'MDL' => array( 'Moldovan Leu', '6C, 65, 69' ),
			'MGA' => array( 'Malagasy Ariary', '41, 72' ),
			'MKD' => array( 'Macedonian Denar', '434, 435, 43d' ),
			'MNT' => array( 'Mongolian Tögrög', '20ae' ),
			'MOP' => array( 'Macanese Pataca', '4D, 4F, 50, 24' ),
			'MRO' => array( 'Mauritanian Ouguiya', '55, 4D' ),
			'MUR' => array( 'Mauritian Rupee', '20a8' ),
			'MVR' => array( 'Maldivian Rufiyaa', '52, 66' ),
			'MWK' => array( 'Malawian Kwacha', '4D, 4B' ),
			'MXN' => array( 'Mexican Peso', '24' ),
			'MYR' => array( 'Malaysian Ringgit', '52, 4d' ),
			'MZN' => array( 'Mozambican Metical', '4d, 54' ),
			'NAD' => array( 'Namibian Dollar', '24' ),
			'NGN' => array( 'Nigerian Naira', '20a6' ),
			'NIO' => array( 'Nicaraguan Córdoba', '43, 24' ),
			'NOK' => array( 'Norwegian Krone', '6b, 72' ),
			'NPR' => array( 'Nepalese Rupee', '20a8' ),
			'NZD' => array( 'New Zealand Dollar', '24' ),
			'PAB' => array( 'Panamanian Balboa', '42, 2f, 2e' ),
			'PEN' => array( 'Peruvian Nuevo Sol', '53, 2f, 2e' ),
			'PGK' => array( 'Papua New Guinean Kina', '4B' ),
			'PHP' => array( 'Philippine Peso', '20b1' ),
			'PKR' => array( 'Pakistani Rupee', '20a8' ),
			'PLN' => array( 'Polish Złoty', '7a, 142' ),
			'PYG' => array( 'Paraguayan Guaraní', '47, 73' ),
			'QAR' => array( 'Qatari Riyal', 'fdfc' ),
			'RON' => array( 'Romanian Leu', '6c, 65, 69' ),
			'RSD' => array( 'Serbian Dinar', '414, 438, 43d, 2e' ),
			'RUB' => array( 'Russian Ruble', '440, 443, 431' ),
			'RWF' => array( 'Rwandan Franc', '52, 20A3' ),
			'SAR' => array( 'Saudi Riyal', 'fdfc' ),
			'SBD' => array( 'Solomon Islands Dollar', '24' ),
			'SCR' => array( 'Seychellois Rupee', '20a8' ),
			'SEK' => array( 'Swedish Krona', '6b, 72' ),
			'SGD' => array( 'Singapore Dollar', '24' ),
			'SHP' => array( 'Saint Helenian Pound', 'a3' ),
			'SLL' => array( 'Sierra Leonean Leone', '4C, 65' ),
			'SOS' => array( 'Somali Shilling', '53' ),
			'SRD' => array( 'Surinamese Dollar', '24' ),
			'STD' => array( 'São Tomé and Príncipe Dobra', '44, 62' ),
			'SVC' => array( 'Salvadoran Colón', '24' ),
			'SZL' => array( 'Swazi Lilangeni', '45' ),
			'THB' => array( 'Thai Baht', 'e3f' ),
			'TJS' => array( 'Tajikistani Somoni', '73, 6F, 6D, 6F, 6E, 69' ),
			'TOP' => array( 'Tongan Paʻanga', '54, 24' ),
			'TRY' => array( 'Turkish Lira', '20BA' ),
			'TTD' => array( 'Trinidad and Tobago Dollar', '54, 54, 24' ),
			'TWD' => array( 'New Taiwan Dollar', '4e, 54, 24' ),
			'TZS' => array( 'Tanzanian Shilling', '78, 2F, 79' ),
			'UAH' => array( 'Ukrainian Hryvnia', '20b4' ),
			'UGX' => array( 'Ugandan Shilling', '55, 53, 68' ),
			'USD' => array( 'United States Dollar', '24' ),
			'UYU' => array( 'Uruguayan Peso', '24, 55' ),
			'UZS' => array( 'Uzbekistani Som', '43b, 432' ),
			'VND' => array( 'Vietnamese Đồng', '20ab' ),
			'VUV' => array( 'Vanuatu Vatu', '56, 54' ),
			'WST' => array( 'Samoan Tala', '24' ),
			'XAF' => array( 'Central African Cfa Franc', '46, 43, 46, 41' ),
			'XCD' => array( 'East Caribbean Dollar', '24' ),
			'XOF' => array( 'West African Cfa Franc', '43, 46, 41' ),
			'XPF' => array( 'Cfp Franc', '46' ),
			'YER' => array( 'Yemeni Rial', 'fdfc' ),
			'ZAR' => array( 'South African Rand', '52' ),
			'ZMW' => array( 'Zambian Kwacha', '4B' ),
		);

		/**
		 * Filter to alter Stripe supported currencies.
		 *
		 * @param array $countries Supported currencies.
		 *
		 * @since 3.6.1
		 */
		return apply_filters( 'pro_sites_stripe_supported_currencies', $currencies );
	}

	/**
	 * Get the array of month's list.
	 *
	 * @since 3.6.1
	 *
	 * @return array
	 */
	public static function months() {
		$months = array(
			'01' => __( 'Jan', 'psts' ),
			'02' => __( 'Feb', 'psts' ),
			'03' => __( 'Mar', 'psts' ),
			'04' => __( 'Apr', 'psts' ),
			'05' => __( 'May', 'psts' ),
			'06' => __( 'Jun', 'psts' ),
			'07' => __( 'Jul', 'psts' ),
			'08' => __( 'Aug', 'psts' ),
			'09' => __( 'Sep', 'psts' ),
			'10' => __( 'Oct', 'psts' ),
			'11' => __( 'Nov', 'psts' ),
			'12' => __( 'Dec', 'psts' ),
		);

		/**
		 * Filter to alter months list.
		 *
		 * @param array $months Months array.
		 *
		 * @since 3.6.1
		 */
		return apply_filters( 'pro_sites_stripe_months', $months );
	}
}
