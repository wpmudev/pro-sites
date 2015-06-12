<?php

if ( ! class_exists( 'ProSites_Helper_IMSI' ) ) {

	class ProSites_Helper_IMSI {

		public static function init() {

		}

		private static function validate_imsi( $imsi ) {

			$eu_countries = array_keys( ProSites_Helper_Geolocation::get_EU_countries() );
			$data = false;

			// If its a valid MCC and MNC
			$operator = self::check_mnc( $imsi );

			if ( $operator ) {
				$mcc_list = self::get_mcc_list();

				$data                = new stdClass;
				$data->mcc           = self::get_mcc( $imsi );
				$data->country_name  = $mcc_list[ $data->mcc ]['country'];
				$data->country_code  = $mcc_list[ $data->mcc ]['country_code'];
				$data->operator_code = $operator[0];
				$data->operator      = $operator[1];
				$data->is_EU         = in_array( $data->country_code, $eu_countries );

				return $data;
			} else {
				return false;
			}

		}

		private static function check_mcc( $imsi ) {
			$mcc_list = self::get_mcc_list();
			$mcc      = self::get_mcc( $imsi );
			return in_array( $mcc, array_keys( $mcc_list ) );
		}

		private static function check_mnc( $imsi ) {

			if ( ! self::check_mcc( $imsi ) ) {
				return false;
			}

			$mcc_list = self::get_mcc_list();
			$mcc      = self::get_mcc( $imsi );
			$mnc2     = substr( $imsi, 3, 2 );
			$mnc3     = substr( $imsi, 3, 3 );

			$operators = array_keys( $mcc_list[ $mcc ]['operators'] );
			$mnc       = in_array( $mnc2, $operators ) ? $mnc2 : ( in_array( $mnc3, $operators ) ? $mnc3 : false );

			if ( ! empty( $mnc ) && 'Operational' == $mcc_list[ $mcc ]['operators'][ $mnc ]['status'] ) {
				return array( $mnc, $mcc_list[ $mcc ]['operators'][ $mnc ] );
			} else {
				return false;
			}
		}

		private static function get_mcc( $imsi ) {
			return substr( $imsi, 0, 3 );
		}

		public static function validate_imsi_ajax() {
			$imsi          = sanitize_text_field( $_POST['imsi'] );
			$doing_ajax    = defined( 'DOING_AJAX' ) && DOING_AJAX ? true : false;
			$ajax_response = array();



			if ( $doing_ajax ) {
				$imsi_data = self::validate_imsi( $imsi );
				$ajax_response['imsi_data'] = json_encode( $imsi_data );
			}

			$response = array(
				'what'   => 'validate_imsi',
				'action' => 'validate_imsi',
				'id'     => 1, // success status
				'data'   => json_encode( $ajax_response ),
			);
			ob_end_clean();
			ob_start();
			$xmlResponse = new WP_Ajax_Response( $response );
			$xmlResponse->send();
			ob_end_flush();
		}

		private static function get_mcc_list() {
			return array(
				'289' => array(
					'country'      => 'Abkhazia',
					'country_code' => '',
					'operators'    => array(
						'67' => array(
							'network' => '',
							'brand'   => 'Aquafon',
							'status'  => 'Operational',
						),
						'68' => array(
							'network' => '',
							'brand'   => 'A-Mobile',
							'status'  => 'Operational',
						),
					),
				),
				'412' => array(
					'country'      => 'Afghanistan',
					'country_code' => 'AF',
					'operators'    => array(
						'01' => array(
							'network' => 'Afghan Wireless Communication Company',
							'brand'   => 'AWCC',
							'status'  => 'Operational',
						),
						'20' => array(
							'network' => 'Telecom Development Company Afghanistan Ltd.',
							'brand'   => 'Roshan',
							'status'  => 'Operational',
						),
						'40' => array(
							'network' => 'MNT Group Afganistan ',
							'brand'   => 'MTN',
							'status'  => 'Operational',
						),
						'50' => array(
							'network' => 'Etisalat Afghanistan',
							'brand'   => 'Etisalat',
							'status'  => 'Operational',
						),
					),
				),
				'276' => array(
					'country'      => 'Albania',
					'country_code' => 'AL',
					'operators'    => array(
						'01' => array(
							'network' => 'Albanian Mobile Communications',
							'brand'   => 'AMS',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Vodafone Albania ',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Eagle Mobile ',
							'brand'   => 'Eagle Mobile ',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Plus Communcation',
							'brand'   => 'Plus Communication',
							'status'  => 'Operational',
						),
					),
				),
				'603' => array(
					'country'      => 'Algeria',
					'country_code' => 'DZ',
					'operators'    => array(
						'01' => array(
							'network' => 'ATM Mobilis',
							'brand'   => 'Mobilis',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Orascom Telecom Algerie Spa',
							'brand'   => 'Djezzy',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Wataniya Telecom Algerie',
							'brand'   => 'Nedjma',
							'status'  => 'Operational',
						),
					),
				),
				'544' => array(
					'country'      => 'American Samoa',
					'country_code' => 'AS',
					'operators'    => array(
						'11' => array(
							'network' => 'Blue Sky Communications',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'213' => array(
					'country'      => 'Andorra',
					'country_code' => 'AD',
					'operators'    => array(
						'03' => array(
							'network' => 'Servei De Tele. DAndorra',
							'brand'   => 'Mobiland ',
							'status'  => 'Operational',
						),
					),
				),
				'631' => array(
					'country'      => 'Angola',
					'country_code' => 'AO',
					'operators'    => array(
						'02' => array(
							'network' => 'Unitel S.a.r.l.',
							'brand'   => 'Unitel',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Movicel',
							'brand'   => 'Movicel',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Movicel',
							'brand'   => 'Movicel',
							'status'  => 'Operational',
						),
					),
				),
				'365' => array(
					'country'      => 'Anguilla',
					'country_code' => 'AI',
					'operators'    => array(
						'05'  => array(
							'network' => 'Mossel Ltd (Digicel)',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'10'  => array(
							'network' => 'Weblinks Limited ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'840' => array(
							'network' => 'Cable & Wireless',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'344' => array(
					'country'      => 'Antigua and Barbuda',
					'country_code' => 'AG',
					'operators'    => array(
						'30'  => array(
							'network' => 'Antigua Public Utilities Authority',
							'brand'   => 'APUA',
							'status'  => 'Operational',
						),
						'50'  => array(
							'network' => 'Antigua Wireless Ventures Limited',
							'brand'   => 'Digicel',
							'status'  => 'Inactive',
						),
						'920' => array(
							'network' => 'Cable & Wireless (Antigua) ',
							'brand'   => 'Lime',
							'status'  => 'Operational',
						),
						'930' => array(
							'network' => 'Antigua Wireless Ventures Limited',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
					),
				),
				'722' => array(
					'country'      => 'Argentina',
					'country_code' => 'AR',
					'operators'    => array(
						'01'  => array(
							'network' => 'Telefonica Móviles Argentina SA',
							'brand'   => 'Movistar',
							'status'  => 'Operational',
						),
						'02'  => array(
							'network' => 'Nll Holdings',
							'brand'   => 'Nextel',
							'status'  => 'Operational',
						),
						'07'  => array(
							'network' => 'Telefonica Móviles Argentina SA',
							'brand'   => 'Movistar',
							'status'  => 'Operational',
						),
						'10'  => array(
							'network' => 'Telefonica Móviles Argentina SA',
							'brand'   => 'Movistar',
							'status'  => 'Operational',
						),
						'20'  => array(
							'network' => 'Nll Holdings',
							'brand'   => 'Nextel',
							'status'  => 'Operational',
						),
						'34'  => array(
							'network' => 'Telecom Personal S.A.',
							'brand'   => 'Personal',
							'status'  => 'Inactive',
						),
						'35'  => array(
							'network' => 'Hutchison Telecommunications Argentina S.A.',
							'brand'   => 'Port-Hable',
							'status'  => 'Inactive',
						),
						'36'  => array(
							'network' => 'Telecom Personal S.A.',
							'brand'   => 'Personal',
							'status'  => 'Inactive',
						),
						'40'  => array(
							'network' => 'TE.SA.M Argentina S.A',
							'brand'   => 'Globalstar',
							'status'  => 'Inactive',
						),
						'70'  => array(
							'network' => 'Telefonica Móviles Argentina SA',
							'brand'   => 'Movistar',
							'status'  => 'Operational',
						),
						'310' => array(
							'network' => 'AMX Argentina S.A',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
						'320' => array(
							'network' => 'AMX Argentina S.A',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
						'330' => array(
							'network' => 'AMX Argentina S.A',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
						'340' => array(
							'network' => 'Telecom Personal S.A. ',
							'brand'   => 'Personal',
							'status'  => 'Operational',
						),
						'341' => array(
							'network' => 'Telecom Personal S.A. ',
							'brand'   => 'Personal',
							'status'  => 'Inactive',
						),
						'350' => array(
							'network' => 'Hutchison Telecommunications Argentina S.A.',
							'brand'   => 'Port-Hable',
							'status'  => 'Operational',
						),
					),
				),
				'283' => array(
					'country'      => 'Armenia',
					'country_code' => 'AM',
					'operators'    => array(
						'01' => array(
							'network' => 'ArmenTel',
							'brand'   => 'Beeline (telecommunications)',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Karabakh Telecom',
							'brand'   => 'Karabakh Telecom',
							'status'  => 'Inactive',
						),
						'05' => array(
							'network' => 'K Telecom CJSC',
							'brand'   => 'VivaCell-MTS',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Orange Armenia',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'77' => array(
							'network' => 'K Telecom ',
							'brand'   => 'VivaCell-MTS',
							'status'  => 'Operational',
						),
					),
				),
				'363' => array(
					'country'      => 'Aruba',
					'country_code' => 'AW',
					'operators'    => array(
						'01' => array(
							'network' => 'Servicio di Telecomunicacio di Aruba',
							'brand'   => 'SETAR',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Digicel',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
					),
				),
				'505' => array(
					'country'      => 'Australia',
					'country_code' => 'AU',
					'operators'    => array(
						'01' => array(
							'network' => 'Telstra Corporation Ltd. ',
							'brand'   => 'Telstra',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Optus Mobile Pty. Ltd. ',
							'brand'   => 'Optus',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Vodafone Network Pty. Ltd. ',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Department of Defence ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'05' => array(
							'network' => 'The Ozitel Network Pty. Ltd. ',
							'brand'   => 'Ozitel',
							'status'  => 'Inactive',
						),
						'06' => array(
							'network' => 'Hutchison 3G Australia Pty. Ltd. ',
							'brand'   => 'Hi3G',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'One.Tel GSM 1800 Pty. Ltd. ',
							'brand'   => 'One.Tel',
							'status'  => 'Inactive',
						),
						'09' => array(
							'network' => 'Airnet Commercial Australia Ltd. ',
							'brand'   => 'Airnet',
							'status'  => 'Inactive',
						),
						'11' => array(
							'network' => 'Telstra Corporation Ltd. ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'12' => array(
							'network' => 'Hutchison 3G Australia Pty. Ltd. ',
							'brand'   => 'Hi3G',
							'status'  => 'Operational',
						),
						'13' => array(
							'network' => 'Rail Corporation New South Wales',
							'brand'   => 'Railcorp',
							'status'  => 'Inactive',
						),
						'14' => array(
							'network' => 'Telecom New Zealand',
							'brand'   => 'AAPT',
							'status'  => 'Operational',
						),
						'15' => array(
							'network' => '3GIS Pty Ltd. (Telstra & Hutchison 3G) ',
							'brand'   => '3GIS',
							'status'  => 'Inactive',
						),
						'21' => array(
							'network' => 'TPG Telecom Limited',
							'brand'   => 'SOUL',
							'status'  => 'Operational',
						),
						'24' => array(
							'network' => 'Advanced Communications Technologies Pty. Ltd. ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'38' => array(
							'network' => 'Vodafone Hutchison Australia Proprietary Limited',
							'brand'   => 'Crazy John`s',
							'status'  => 'Operational',
						),
						'71' => array(
							'network' => 'Telstra Corporation Ltd. ',
							'brand'   => 'Telstra',
							'status'  => 'Operational',
						),
						'72' => array(
							'network' => 'Telstra Corporation Ltd. ',
							'brand'   => 'Telstra',
							'status'  => 'Operational',
						),
						'88' => array(
							'network' => 'Localstar Holding Pty. Ltd. ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'90' => array(
							'network' => 'Optus Ltd. ',
							'brand'   => 'Optus',
							'status'  => 'Operational',
						),
						'99' => array(
							'network' => 'One.Tel GSM 1800 Pty. Ltd. ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'232' => array(
					'country'      => 'Austria',
					'country_code' => 'AT',
					'operators'    => array(
						'01' => array(
							'network' => 'A1 Telekom Austria',
							'brand'   => 'A1',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'A1 Telekom Austria',
							'brand'   => 'A1',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'T-Mobile ',
							'brand'   => 'T-Mobile',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Orange',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => '',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'tele.ring ',
							'brand'   => 'Tele.ring',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'A1 Telekom Austria',
							'brand'   => 'A1',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Hutchison 3G Austria',
							'brand'   => '3 (Drei)',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'A1 Telekom Austria',
							'brand'   => 'Bob',
							'status'  => 'Operational',
						),
						'12' => array(
							'network' => 'Yesss (Orange)',
							'brand'   => 'Yesss',
							'status'  => 'Operational',
						),
						'14' => array(
							'network' => 'Hutchison 3G Austria',
							'brand'   => '3 (Drei)',
							'status'  => 'Operational',
						),
						'15' => array(
							'network' => 'Barablu Mobile Ltd',
							'brand'   => 'Barablu',
							'status'  => 'Operational',
						),
						'91' => array(
							'network' => 'ÖBB',
							'brand'   => 'GSM-R A',
							'status'  => 'Inactive',
						),
					),
				),
				'400' => array(
					'country'      => 'Azerbaijan',
					'country_code' => 'AZ',
					'operators'    => array(
						'01' => array(
							'network' => 'Azercell Limited Liability Joint Venture ',
							'brand'   => 'Azercell',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Bakcell Limited Liabil ity Company ',
							'brand'   => 'Bakcell',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Catel',
							'brand'   => 'FONEX',
							'status'  => 'Inactive',
						),
						'04' => array(
							'network' => 'Azerfon',
							'brand'   => 'Nar Mobile (Azerfon)',
							'status'  => 'Operational',
						),
					),
				),
				'364' => array(
					'country'      => 'Bahamas',
					'country_code' => 'BS',
					'operators'    => array(
						'39'  => array(
							'network' => 'The Bahamas Telecommunications Company Ltd',
							'brand'   => 'BaTelCo',
							'status'  => 'Inactive',
						),
						'390' => array(
							'network' => 'The Bahamas Telecommunications Company Ltd',
							'brand'   => 'BaTelCo',
							'status'  => 'Operational',
						),
					),
				),
				'426' => array(
					'country'      => 'Bahrain',
					'country_code' => 'BH',
					'operators'    => array(
						'01' => array(
							'network' => 'Bahrain Telecommunications Company',
							'brand'   => 'Batelco',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Zain Bahrain',
							'brand'   => 'Zain BH',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'STC Bahrain',
							'brand'   => 'Viva',
							'status'  => 'Operational',
						),
					),
				),
				'470' => array(
					'country'      => 'Bangladesh',
					'country_code' => 'BD',
					'operators'    => array(
						'01' => array(
							'network' => 'GrameenPhone Ltd',
							'brand'   => 'GramenPhone ',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Axiata Bangladesh Ltd.',
							'brand'   => 'Robi',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Orascom Telecom Holding',
							'brand'   => 'Banglalink',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'TeleTalk',
							'brand'   => 'TeleTalk',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Citycell',
							'brand'   => 'Citycell',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Airtel',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Aritel',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
					),
				),
				'342' => array(
					'country'      => 'Barbados',
					'country_code' => 'BB',
					'operators'    => array(
						'50'  => array(
							'network' => 'Digicel Barbados',
							'brand'   => 'DigiCel',
							'status'  => 'Operational',
						),
						'600' => array(
							'network' => 'Lime (Cable & Wireless)',
							'brand'   => 'Lime (Cable & Wireless)',
							'status'  => 'Operational',
						),
						'750' => array(
							'network' => 'Digicel',
							'brand'   => 'DigiCel',
							'status'  => 'Operational',
						),
						'810' => array(
							'network' => 'Cingular Wireless',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'820' => array(
							'network' => 'Sunbeach Communications ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'257' => array(
					'country'      => 'Belarus',
					'country_code' => 'BY',
					'operators'    => array(
						'01'  => array(
							'network' => 'MDC Velcom ',
							'brand'   => 'Velcom',
							'status'  => 'Operational',
						),
						'02'  => array(
							'network' => 'Mobile TeleSystems',
							'brand'   => 'MTS',
							'status'  => 'Operational',
						),
						'03'  => array(
							'network' => 'BelCel',
							'brand'   => 'DIALLOG',
							'status'  => 'Operational',
						),
						'04'  => array(
							'network' => 'Belarussian Telecommunications Network',
							'brand'   => 'life :)',
							'status'  => 'Operational',
						),
						'501' => array(
							'network' => '',
							'brand'   => 'BelCel JV',
							'status'  => 'Operational',
						),
					),
				),
				'206' => array(
					'country'      => 'Belgium',
					'country_code' => 'BE',
					'operators'    => array(
						'01' => array(
							'network' => 'Belgacom Mobile',
							'brand'   => 'Proximus ',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Telenet',
							'brand'   => 'Telenet',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => '',
							'brand'   => 'Lyca Mobile',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'France Telecom',
							'brand'   => 'Mobistar ',
							'status'  => 'Operational',
						),
						'20' => array(
							'network' => 'KPN',
							'brand'   => 'Base ',
							'status'  => 'Operational',
						),
					),
				),
				'702' => array(
					'country'      => 'Belize',
					'country_code' => 'BZ',
					'operators'    => array(
						'67' => array(
							'network' => 'Belize Telemedia',
							'brand'   => 'DigiCell',
							'status'  => 'Operational',
						),
						'68' => array(
							'network' => 'International Telecommunications Ltd. (INTELCO) ',
							'brand'   => 'IntelCo',
							'status'  => 'Operational',
						),
						'99' => array(
							'network' => 'SpeedNet Communications Ltd',
							'brand'   => 'Smart',
							'status'  => 'Operational',
						),
					),
				),
				'616' => array(
					'country'      => 'Benin',
					'country_code' => 'BJ',
					'operators'    => array(
						'00' => array(
							'network' => 'BBCOM',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'01' => array(
							'network' => 'Benin Telecoms Mobile',
							'brand'   => 'Libercom',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Telecel Benin',
							'brand'   => 'Moov',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Spacetel Benin ',
							'brand'   => 'MTN',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Bell Benin Communications',
							'brand'   => 'BBCOM',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Glo Communications',
							'brand'   => 'Glo',
							'status'  => 'Operational',
						),
					),
				),
				'350' => array(
					'country'      => 'Bermuda',
					'country_code' => 'BM',
					'operators'    => array(
						'01' => array(
							'network' => 'Cingular GSM 1900',
							'brand'   => 'Digicel Bermuda',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'M3 Wireless',
							'brand'   => 'Mobility',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Cingular Wireless',
							'brand'   => 'Cellular One (Cingular)',
							'status'  => 'Operational',
						),
						'38' => array(
							'network' => 'Mossel (Digicel)',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
					),
				),
				'402' => array(
					'country'      => 'Bhutan',
					'country_code' => 'BT',
					'operators'    => array(
						'11' => array(
							'network' => 'Bhutan Telecom Ltd ',
							'brand'   => 'B-Mobile',
							'status'  => 'Operational',
						),
						'17' => array(
							'network' => 'B-Mobile of Bhutan Telecom ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'77' => array(
							'network' => 'Tashi InfoComm Limited',
							'brand'   => 'TashiCell',
							'status'  => 'Operational',
						),
					),
				),
				'736' => array(
					'country'      => 'Bolivia',
					'country_code' => 'BO',
					'operators'    => array(
						'01' => array(
							'network' => 'Nuevatel S.A. ',
							'brand'   => 'Nuevatel',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'ENTEL S.A. ',
							'brand'   => 'Entel',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Telefonica Celular De Bolivia S.A',
							'brand'   => 'Tigo',
							'status'  => 'Operational',
						),
						'20' => array(
							'network' => 'Entel BOlivia',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'218' => array(
					'country'      => 'Bosnia and Herzegovina',
					'country_code' => 'BA',
					'operators'    => array(
						'03' => array(
							'network' => 'Public Enterprise Croatian Telecom Ltd.',
							'brand'   => 'HT-Eronet',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'RS Telecommunications JSC Banja Luka',
							'brand'   => 'm:tel',
							'status'  => 'Operational',
						),
						'90' => array(
							'network' => 'BH Telecom',
							'brand'   => 'BH Mobile',
							'status'  => 'Operational',
						),
					),
				),
				'652' => array(
					'country'      => 'Botswana',
					'country_code' => 'BW',
					'operators'    => array(
						'01' => array(
							'network' => 'Mascom Wireless (Pty) Ltd. ',
							'brand'   => 'Mascom',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Orange Botswana (Pty) Ltd. ',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Botswana Telecommunications Corporation',
							'brand'   => 'BTC Mobile',
							'status'  => 'Operational',
						),
					),
				),
				'724' => array(
					'country'      => 'Brazil',
					'country_code' => 'BR',
					'operators'    => array(
						'00' => array(
							'network' => 'Nll Holdings, INC.',
							'brand'   => 'Nextel',
							'status'  => 'Operational',
						),
						'01' => array(
							'network' => 'CRT Cellular ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Telecom Italia Mobile',
							'brand'   => 'TIM',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Telecom Italia Mobile',
							'brand'   => 'TIM',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Telecom Italia Mobile',
							'brand'   => 'TIM',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Claro',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Vivo S.A',
							'brand'   => 'Vivo',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'CTBC Telecom',
							'brand'   => 'CTBC Celular',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'Telecom Italia Mobile',
							'brand'   => 'TIM',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'Telepar Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'10' => array(
							'network' => 'Vivo S.A',
							'brand'   => 'Vivo',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'Vivo S.A',
							'brand'   => 'Vivo',
							'status'  => 'Operational',
						),
						'12' => array(
							'network' => 'Americel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'13' => array(
							'network' => 'Telesp Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'14' => array(
							'network' => 'Maxitel BA ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'15' => array(
							'network' => 'Sercomtel Celular',
							'brand'   => 'Sercomtel',
							'status'  => 'Operational',
						),
						'16' => array(
							'network' => 'Brasil Telecom Celular S.A',
							'brand'   => 'Oi',
							'status'  => 'Operational',
						),
						'17' => array(
							'network' => 'Ceterp Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'18' => array(
							'network' => 'Norte Brasil Tel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'19' => array(
							'network' => 'Telemig Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'21' => array(
							'network' => 'Telerj Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'23' => array(
							'network' => 'Vivo S.A',
							'brand'   => 'Oi',
							'status'  => 'Operational',
						),
						'24' => array(
							'network' => 'Amazonia Celular S/A',
							'brand'   => 'Oi / Brasil Telecom',
							'status'  => 'Operational',
						),
						'25' => array(
							'network' => 'Telebrasilia Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'27' => array(
							'network' => 'Telegoias Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'29' => array(
							'network' => 'Telemat Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'31' => array(
							'network' => 'TNL PCS',
							'brand'   => 'Oi',
							'status'  => 'Operational',
						),
						'32' => array(
							'network' => 'CTBC Celular',
							'brand'   => 'CTBC Celular',
							'status'  => 'Operational',
						),
						'33' => array(
							'network' => 'CTBC Celular',
							'brand'   => 'CTBC Celular',
							'status'  => 'Operational',
						),
						'34' => array(
							'network' => 'CTBC Celular',
							'brand'   => 'CTBC Celular',
							'status'  => 'Operational',
						),
						'35' => array(
							'network' => 'Telebahia Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'37' => array(
							'network' => 'Unicel Do Brasil',
							'brand'   => 'Aeiou',
							'status'  => 'Operational',
						),
						'39' => array(
							'network' => 'NII Holdings Inc',
							'brand'   => 'Nextel',
							'status'  => 'Inactive',
						),
						'41' => array(
							'network' => 'Telpe Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'43' => array(
							'network' => 'Telepisa Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'45' => array(
							'network' => 'Telpa Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'47' => array(
							'network' => 'Telern Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'48' => array(
							'network' => 'Teleceara Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'51' => array(
							'network' => 'Telma Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'53' => array(
							'network' => 'Telepara Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'55' => array(
							'network' => 'Teleamazon Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'57' => array(
							'network' => 'Teleamapa Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'59' => array(
							'network' => 'Telaima Cel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'348' => array(
					'country'      => 'British Virgin Islands',
					'country_code' => '',
					'operators'    => array(
						'170' => array(
							'network' => 'Cabel & Wireless (west Indies)',
							'brand'   => 'Cabel & Wireless',
							'status'  => 'Operational',
						),
						'570' => array(
							'network' => 'Caribbean Cellular Telephone, Boatphone Ltd. ',
							'brand'   => 'CCT Boatphone',
							'status'  => 'Operational',
						),
						'770' => array(
							'network' => 'Digicel (BVI) Limited',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
					),
				),
				'528' => array(
					'country'      => 'Brunei Darussalam',
					'country_code' => 'BN',
					'operators'    => array(
						'01' => array(
							'network' => 'Jabatan Telekom Brunei',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'B-Mobile Communications Sdn Bhd',
							'brand'   => 'B-Mobile',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'DST Com ',
							'brand'   => 'DSTCom',
							'status'  => 'Operational',
						),
					),
				),
				'284' => array(
					'country'      => 'Bulgaria',
					'country_code' => 'BG',
					'operators'    => array(
						'01' => array(
							'network' => 'M-Tel GSM BG ',
							'brand'   => 'M-Tel',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'BTC Mobile',
							'brand'   => 'Vivacom',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Globul ',
							'brand'   => 'GLOBUL',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'BTC Mobile',
							'brand'   => 'Vivacom',
							'status'  => 'Operational',
						),
					),
				),
				'613' => array(
					'country'      => 'Burkina Faso',
					'country_code' => 'BF',
					'operators'    => array(
						'01' => array(
							'network' => 'Onatal',
							'brand'   => 'Telmob',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Celtel Burkina Faso',
							'brand'   => 'Zain',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Telcel Faso SA',
							'brand'   => 'Telcel Faso',
							'status'  => 'Operational',
						),
					),
				),
				'642' => array(
					'country'      => 'Burundi',
					'country_code' => 'BI',
					'operators'    => array(
						'01' => array(
							'network' => 'Econet Wireless Burundi PLC',
							'brand'   => 'Spacetel',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Africell PLC',
							'brand'   => 'Africell',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Onatel',
							'brand'   => 'Onatel',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'LACELL SU',
							'brand'   => 'Smart Mobile',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'HiTs Telecom',
							'brand'   => 'HiTs Telecom',
							'status'  => 'Inactive',
						),
						'82' => array(
							'network' => 'U-COM Burundi S.A.',
							'brand'   => 'U-COM Burundi',
							'status'  => 'Operational',
						),
					),
				),
				'456' => array(
					'country'      => 'Cambodia',
					'country_code' => 'KH',
					'operators'    => array(
						'01' => array(
							'network' => 'CamGSM',
							'brand'   => 'Mobitel',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Telekom Malaysia International (Cambodia) Co. Ltd',
							'brand'   => 'Hello',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'S Telecom (reserved) ',
							'brand'   => 'S Telecom',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Cambodia Advance Communications Co. Ltd',
							'brand'   => 'QB',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Applifone Co. Ltd',
							'brand'   => 'Star-Cell',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Latelz Co. Ltd',
							'brand'   => 'Smart Mobile',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'Viettel',
							'brand'   => 'Mefone',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'Sotelco Ltd.',
							'brand'   => 'Beeline',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'Excell',
							'brand'   => 'Excell',
							'status'  => 'Operational',
						),
						'18' => array(
							'network' => 'Camshin (Shinawatra) ',
							'brand'   => 'Mfone',
							'status'  => 'Operational',
						),
					),
				),
				'624' => array(
					'country'      => 'Cameroon',
					'country_code' => 'CM',
					'operators'    => array(
						'01' => array(
							'network' => 'Mobile Telephone Networks Cameroon ',
							'brand'   => 'MTN Cameroon',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Orange Cameroun ',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
					),
				),
				'302' => array(
					'country'      => 'Canada',
					'country_code' => 'CA',
					'operators'    => array(
						'220' => array(
							'network' => 'Telus Mobility',
							'brand'   => 'Telus',
							'status'  => 'Operational',
						),
						'221' => array(
							'network' => 'Telus Mobility (Unknown)',
							'brand'   => 'Telus',
							'status'  => 'Operational',
						),
						'290' => array(
							'network' => 'Aurtek Wurekess',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'320' => array(
							'network' => 'Dave Wireless',
							'brand'   => 'Mobilicity',
							'status'  => 'Operational',
						),
						'350' => array(
							'network' => 'FIRST Networks Operations',
							'brand'   => 'FIRST',
							'status'  => 'Operational',
						),
						'360' => array(
							'network' => 'Telus Mobility',
							'brand'   => 'MiKE',
							'status'  => 'Operational',
						),
						'361' => array(
							'network' => 'Telus Mobility',
							'brand'   => 'Telus',
							'status'  => 'Operational',
						),
						'370' => array(
							'network' => 'Fido Solutions (Rogers Wireless)',
							'brand'   => 'Fido',
							'status'  => 'Operational',
						),
						'380' => array(
							'network' => 'Dryden Mobility',
							'brand'   => 'DMTS',
							'status'  => 'Operational',
						),
						'490' => array(
							'network' => 'Globalive Communications',
							'brand'   => 'WIND Mobile',
							'status'  => 'Operational',
						),
						'500' => array(
							'network' => 'Videotron',
							'brand'   => 'Videotron',
							'status'  => 'Operational',
						),
						'510' => array(
							'network' => 'Videotron',
							'brand'   => 'Videotron',
							'status'  => 'Operational',
						),
						'610' => array(
							'network' => 'Bell Mobility',
							'brand'   => 'Bell',
							'status'  => 'Operational',
						),
						'620' => array(
							'network' => 'ICE Wireless',
							'brand'   => 'ICE Wireless',
							'status'  => 'Operational',
						),
						'640' => array(
							'network' => 'Bell Mobility',
							'brand'   => 'Bell',
							'status'  => 'Operational',
						),
						'651' => array(
							'network' => 'Bell Mobility',
							'brand'   => 'Bell',
							'status'  => 'Operational',
						),
						'652' => array(
							'network' => 'BC Tel Mobility',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'653' => array(
							'network' => 'Telus Mobility',
							'brand'   => 'Telus',
							'status'  => 'Operational',
						),
						'654' => array(
							'network' => 'Sask Tel Mobility',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'655' => array(
							'network' => 'MTS Mobility',
							'brand'   => 'MTS',
							'status'  => 'Operational',
						),
						'656' => array(
							'network' => 'Thunder Bay Telephone Mobility',
							'brand'   => 'TBay',
							'status'  => 'Operational',
						),
						'657' => array(
							'network' => 'Telus Mobility',
							'brand'   => 'Telus',
							'status'  => 'Operational',
						),
						'680' => array(
							'network' => 'SaskTel Mobility',
							'brand'   => 'SaskTel',
							'status'  => 'Operational',
						),
						'701' => array(
							'network' => 'MB Tel Mobility',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'702' => array(
							'network' => 'MT&T Mobility (Aliant)',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'703' => array(
							'network' => 'New Tel Mobility (Aliant',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'710' => array(
							'network' => 'Globalstar Canada',
							'brand'   => 'Globalstar',
							'status'  => 'Operational',
						),
						'720' => array(
							'network' => 'Rorges Communications',
							'brand'   => 'Rogers Wireless',
							'status'  => 'Operational',
						),
						'780' => array(
							'network' => 'SaskTel Mobility',
							'brand'   => 'SaskTel',
							'status'  => 'Operational',
						),
						'880' => array(
							'network' => 'Shared Telus, Bell, and SaskTel',
							'brand'   => 'Bell / Telus / SaskTel',
							'status'  => 'Operational',
						),
					),
				),
				'625' => array(
					'country'      => 'Cape Verde',
					'country_code' => 'CV',
					'operators'    => array(
						'01' => array(
							'network' => 'CVMovel, S.A.',
							'brand'   => 'CVMOVEL',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'T+Telecomunicaçôes ',
							'brand'   => 'T+',
							'status'  => 'Operational',
						),
					),
				),
				'346' => array(
					'country'      => 'Cayman Islands',
					'country_code' => 'KY',
					'operators'    => array(
						'50'  => array(
							'network' => 'Digicel Cayman Ltd.',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'140' => array(
							'network' => 'Cable & Wireless (Cayman) Limited',
							'brand'   => 'Cable & Wireless (Lime)',
							'status'  => 'Operational',
						),
					),
				),
				'623' => array(
					'country'      => 'Central African Republic',
					'country_code' => 'CF',
					'operators'    => array(
						'01' => array(
							'network' => 'Atlantique Telecom Centrafrique SA',
							'brand'   => 'MOOV',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Telecel Centrafrique (TC)',
							'brand'   => 'TC',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Orange RCA',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Nationlink Telecom RCA',
							'brand'   => 'Nationlink',
							'status'  => 'Operational',
						),
					),
				),
				'622' => array(
					'country'      => 'Chad',
					'country_code' => 'TD',
					'operators'    => array(
						'01' => array(
							'network' => 'CelTel Tchad SA',
							'brand'   => 'Zain',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Tchad Mobile',
							'brand'   => 'Tawali',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Millicom Tchad',
							'brand'   => 'Millicom Tchad',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Sotel Mobile',
							'brand'   => 'Salam',
							'status'  => 'Operational',
						),
					),
				),
				'730' => array(
					'country'      => 'Chile',
					'country_code' => 'CL',
					'operators'    => array(
						'01' => array(
							'network' => 'Entel PCS Telecomunicaciones S.A.',
							'brand'   => 'entel',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Telefónica Móvil de Chile',
							'brand'   => 'movistar',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Claro Chile S.A.',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Centennial Cayman Corp. Chile S.A. ',
							'brand'   => 'Nextel',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'VTW S.A.',
							'brand'   => 'VTR Móvil',
							'status'  => 'Inactive',
						),
						'09' => array(
							'network' => 'Centennial Cayman Corp. Chile',
							'brand'   => 'Nextel',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Entel Telefonia Móvil S.A.',
							'brand'   => 'entel',
							'status'  => 'Operational',
						),
						'99' => array(
							'network' => 'WILL Telefonia',
							'brand'   => 'Will',
							'status'  => 'Operational',
						),
					),
				),
				'460' => array(
					'country'      => 'China',
					'country_code' => 'CN',
					'operators'    => array(
						'00' => array(
							'network' => 'China Mobile ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'01' => array(
							'network' => 'China Unicom ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Liaoning PPTA',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'China Unicom CDMA ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'China Satellite Global Star Network ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'05' => array(
							'network' => 'China Telecom',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'China Unicom',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'China Mobile',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'20' => array(
							'network' => 'China Tietong (GSM-R)',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'732' => array(
					'country'      => 'Colombia',
					'country_code' => 'CO',
					'operators'    => array(
						'01'  => array(
							'network' => 'Colombia Telecomunicaciones S.A.',
							'brand'   => 'Movistar',
							'status'  => 'Operational',
						),
						'02'  => array(
							'network' => 'Edatel S.A. ',
							'brand'   => 'Edatel',
							'status'  => 'Operational',
						),
						'20'  => array(
							'network' => 'Emtelsa ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'99'  => array(
							'network' => 'Emcali ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'101' => array(
							'network' => 'Comcel S.A. Occel S.A./Celcaribe ',
							'brand'   => 'Comcel',
							'status'  => 'Operational',
						),
						'102' => array(
							'network' => 'Bellsouth Colombia S.A. ',
							'brand'   => 'Movistar',
							'status'  => 'Operational',
						),
						'103' => array(
							'network' => 'Colombia Móvil S.A. ',
							'brand'   => 'Tigo',
							'status'  => 'Operational',
						),
						'111' => array(
							'network' => 'Colombia Móvil S.A. ',
							'brand'   => 'Tigo',
							'status'  => 'Operational',
						),
						'123' => array(
							'network' => 'Telefónica Móviles Colombia',
							'brand'   => 'Movistar',
							'status'  => 'Operational',
						),
						'130' => array(
							'network' => 'Avantel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'654' => array(
					'country'      => 'Comoros',
					'country_code' => 'KM',
					'operators'    => array(
						'01' => array(
							'network' => 'HURI - SNPT ',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'548' => array(
					'country'      => 'Cook Islands',
					'country_code' => 'CK',
					'operators'    => array(
						'01' => array(
							'network' => 'Telecom Cook ',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'712' => array(
					'country'      => 'Costa Rica',
					'country_code' => 'CR',
					'operators'    => array(
						'01' => array(
							'network' => 'Instituto Costarricense de Electricidad - ICE ',
							'brand'   => 'ICE',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Instituto Costarricense de Electricidad - ICE ',
							'brand'   => 'ICE',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Gruop ICE',
							'brand'   => 'ICE',
							'status'  => 'Operational',
						),
					),
				),
				'612' => array(
					'country'      => 'Côte d\'Ivoire',
					'country_code' => 'CI',
					'operators'    => array(
						'01' => array(
							'network' => 'Cora de Comstar',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'02' => array(
							'network' => 'Moov',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Orange',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Koz',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'MTN',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'OriCel',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'219' => array(
					'country'      => 'Croatia',
					'country_code' => 'HR',
					'operators'    => array(
						'01' => array(
							'network' => 'T - Mobile',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Tele2',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'VIPnet',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'368' => array(
					'country'      => 'Cuba',
					'country_code' => 'CU',
					'operators'    => array(
						'01' => array(
							'network' => 'Empresa de Telecomunicaciones de Cuba, SA',
							'brand'   => 'Cubacel',
							'status'  => 'Operational',
						),
					),
				),
				'280' => array(
					'country'      => 'Cyprus',
					'country_code' => 'CY',
					'operators'    => array(
						'00' => array(
							'network' => 'Areeba Ltd . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'01' => array(
							'network' => 'CYTA ',
							'brand'   => 'Cytamobile - Vodafone',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Areeba Ltd',
							'brand'   => 'MTN',
							'status'  => 'Operational',
						),
						'20' => array(
							'network' => 'Primetel PLC',
							'brand'   => 'Primetel',
							'status'  => 'Operational',
						),
					),
				),
				'230' => array(
					'country'      => 'Czech Republic',
					'country_code' => 'CZ',
					'operators'    => array(
						'01' => array(
							'network' => 'T - Mobile Czech Republic a . s . ',
							'brand'   => 'T - Mobile',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Telefónica O2 Czech Republic a . s . ',
							'brand'   => 'O2',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Vodafone Czech Republic a . s . ',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Mobilkom a . s . ',
							'brand'   => 'U:fon',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Travel Telecomunication s . r . o . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'06' => array(
							'network' => 'Osno Telecomunication s . r . o . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'10' => array(
							'network' => 'Telefónica O2 Czech Republic a . s . ',
							'brand'   => 'O2',
							'status'  => 'Operational',
						),
						'98' => array(
							'network' => 'Sprava Zeleznicni Dopravni Cesty ',
							'brand'   => 'SDC s . o . ',
							'status'  => 'Inactive',
						),
						'99' => array(
							'network' => 'Vodafone Czech Republic a . s . R & D Centre ',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
					),
				),
				'630' => array(
					'country'      => 'Congo, Democratic Republic',
					'country_code' => 'CD',
					'operators'    => array(
						'01' => array(
							'network' => 'Vodacom Congo RDC sprl ',
							'brand'   => 'Vodacom',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Airtel sprl',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => '',
							'brand'   => 'Cellco',
							'status'  => 'Inactive',
						),
						'05' => array(
							'network' => 'Supercell sprl ',
							'brand'   => 'Supercell',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Libertis Telecom',
							'brand'   => 'Libertis',
							'status'  => 'Inactive',
						),
						'86' => array(
							'network' => 'Orange RDC sarl',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'88' => array(
							'network' => 'Yozma Timeturns',
							'brand'   => 'YTT',
							'status'  => 'Inactive',
						),
						'89' => array(
							'network' => 'OASIS sprl',
							'brand'   => 'Tigo',
							'status'  => 'Operational',
						),
						'90' => array(
							'network' => 'Africell RDC Sprl',
							'brand'   => 'Africell',
							'status'  => 'Operational',
						),
					),
				),
				'238' => array(
					'country'      => 'Denmark',
					'country_code' => 'DK',
					'operators'    => array(
						'01' => array(
							'network' => 'TDC Mobil APS',
							'brand'   => 'TDC',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Telenor Denmark',
							'brand'   => 'Telenor',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'End2End',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Dansk Beredskapskommunikasjon',
							'brand'   => 'Dansk Beredskapskommunikasjon',
							'status'  => 'Inactive',
						),
						'06' => array(
							'network' => 'H3G APS',
							'brand'   => '3',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Mundio Mobile',
							'brand'   => 'Mundio Mobile',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => '',
							'brand'   => 'Nordisk Mobiltelefon',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'TDC Mobil APS',
							'brand'   => 'TDC',
							'status'  => 'Operational',
						),
						'12' => array(
							'network' => 'Lyca Mobile Denmark Ltd',
							'brand'   => 'Lyca',
							'status'  => 'Operational',
						),
						'20' => array(
							'network' => 'Telia Sonera APS',
							'brand'   => 'Telia',
							'status'  => 'Operational',
						),
						'23' => array(
							'network' => '',
							'brand'   => 'Banedanmark',
							'status'  => 'Inactive',
						),
						'30' => array(
							'network' => 'Telia Sonera APS',
							'brand'   => 'Telia',
							'status'  => 'Operational',
						),
						'40' => array(
							'network' => '',
							'brand'   => 'Ericsson Danmark A / S',
							'status'  => 'Inactive',
						),
						'77' => array(
							'network' => 'Telenor Denmark',
							'brand'   => 'Telenor',
							'status'  => 'Operational',
						),
					),
				),
				'638' => array(
					'country'      => 'Djibouti',
					'country_code' => 'DJ',
					'operators'    => array(
						'01' => array(
							'network' => 'Evatis ',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'366' => array(
					'country'      => 'Dominica',
					'country_code' => 'DM',
					'operators'    => array(
						'20'  => array(
							'network' => 'Cingular Wireless',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'110' => array(
							'network' => 'Cable & Wireless Dominica Ltd . ',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'370' => array(
					'country'      => 'Dominican Republic',
					'country_code' => 'DO {
				',
					'}operators'   => array(
						'01' => array(
							'network' => 'Orange Dominicana, S . A . ',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Compañía Dominicana de Teléfonos, C por',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Tricom S . A . ',
							'brand'   => 'Tricom',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Trilogy Dominicana, S . A . ',
							'brand'   => 'Viva',
							'status'  => 'Operational',
						),
					),
				),
				'740' => array(
					'country'      => 'Ecuador',
					'country_code' => 'EC',
					'operators'    => array(
						'00' => array(
							'network' => 'Otecel S . A . - Bellsouth ',
							'brand'   => 'Moviestar',
							'status'  => 'Operational',
						),
						'01' => array(
							'network' => 'América Móvil',
							'brand'   => 'Porta',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Telecsa S . A . ',
							'brand'   => 'Alegro',
							'status'  => 'Operational',
						),
					),
				),
				'602' => array(
					'country'      => 'Egypt',
					'country_code' => 'EG',
					'operators'    => array(
						'01' => array(
							'network' => 'EEMS - Mobinil ',
							'brand'   => 'Mobinil',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Vodafone Egypt ',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Etisalat Egypt',
							'brand'   => 'Etisalat',
							'status'  => 'Operational',
						),
					),
				),
				'706' => array(
					'country'      => 'El Salvador',
					'country_code' => 'SV',
					'operators'    => array(
						'01' => array(
							'network' => 'CTE Telecom Personal, S . A . de C . V . ',
							'brand'   => 'CTW Telecom Personal',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Digicel Group',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Telemovil EL Salvador S . A . ',
							'brand'   => 'Tigo',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Telefónica Móviles El Salvador',
							'brand'   => 'movistar',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'América Móvil',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
					),
				),
				'627' => array(
					'country'      => 'Equatorial Guinea',
					'country_code' => 'GQ',
					'operators'    => array(
						'01' => array(
							'network' => 'Guinea Ecuatorial de Telecomunicaciones Sociedad Anónima  ',
							'brand'   => 'Orange GQ',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'HiTs EG . SA',
							'brand'   => 'Hits GQ',
							'status'  => 'Operational',
						),
					),
				),
				'248' => array(
					'country'      => 'Estonia',
					'country_code' => 'EE',
					'operators'    => array(
						'01' => array(
							'network' => 'EMT GSM ',
							'brand'   => 'EMT',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Elisa Eesti',
							'brand'   => 'Elisa',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Tele 2 Eesti',
							'brand'   => 'Tele 2',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'OY Top Connect ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'05' => array(
							'network' => 'AS Bravocom Mobiil ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'06' => array(
							'network' => 'OY ViaTel( UMTS )',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'07' => array(
							'network' => 'Televõrgu AS ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'71' => array(
							'network' => 'Siseministeerium( Ministry of Interior) ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'636' => array(
					'country'      => 'Ethiopia',
					'country_code' => 'ET',
					'operators'    => array(
						'01' => array(
							'network' => 'Ethiopian Telecoms Auth . ( ETH MTN)',
							'brand'   => 'ETH MTN ',
							'status'  => 'Operational',
						),
					),
				),
				'750' => array(
					'country'      => 'Falkland Islands',
					'country_code' => '',
					'operators'    => array(
						'01' => array(
							'network' => 'Touch',
							'brand'   => 'Cable & Wireless',
							'status'  => 'Operational',
						),
					),
				),
				'288' => array(
					'country'      => 'Faroe Islands',
					'country_code' => 'FO',
					'operators'    => array(
						'01' => array(
							'network' => 'Faroese Telecom - GSM ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Vodafone Faroe Islands',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
					),
				),
				'542' => array(
					'country'      => 'Fiji',
					'country_code' => 'FJ',
					'operators'    => array(
						'01' => array(
							'network' => 'Vodafone ',
							'brand'   => 'Vodafone ',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Digicel Fiji',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
					),
				),
				'244' => array(
					'country'      => 'Finland',
					'country_code' => 'FI',
					'operators'    => array(
						'03' => array(
							'network' => 'DNA Oy',
							'brand'   => 'DNA',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Elisa Oyj',
							'brand'   => 'Elisa',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Nokia test network',
							'brand'   => 'Nokia',
							'status'  => 'Inactive',
						),
						'08' => array(
							'network' => 'Unknown network / operator',
							'brand'   => 'Uknown brand',
							'status'  => 'Inactive',
						),
						'09' => array(
							'network' => 'Finnet Group ',
							'brand'   => 'Finnet',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'TDC Oy',
							'brand'   => 'TDC',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'Soumen Erillisverkot Oy',
							'brand'   => 'Virve',
							'status'  => 'Inactive',
						),
						'12' => array(
							'network' => 'DNA Oy',
							'brand'   => 'DNA',
							'status'  => 'Operational',
						),
						'14' => array(
							'network' => 'Alands Mobiltelefon AB ',
							'brand'   => 'AMT',
							'status'  => 'Operational',
						),
						'15' => array(
							'network' => 'Samk student network',
							'brand'   => 'Samk',
							'status'  => 'Operational',
						),
						'16' => array(
							'network' => 'Oy Finland Tele2 AB ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'21' => array(
							'network' => 'Saunalahti',
							'brand'   => 'Saunalahti',
							'status'  => 'Operational',
						),
						'29' => array(
							'network' => '',
							'brand'   => 'Scnl Truphone',
							'status'  => 'Operational',
						),
						'41' => array(
							'network' => 'Saunalahti',
							'brand'   => 'Saunalahti',
							'status'  => 'Inactive',
						),
						'91' => array(
							'network' => 'TeliaSonera Finland Oyj',
							'brand'   => 'Sonera',
							'status'  => 'Operational',
						),
					),
				),
				'208' => array(
					'country'      => 'France',
					'country_code' => 'FR',
					'operators'    => array(
						'00' => array(
							'network' => 'France Telecom',
							'brand'   => 'Orange',
							'status'  => 'Inactive',
						),
						'01' => array(
							'network' => 'France Telecom',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'France Telecom',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Globalstar Europe ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'06' => array(
							'network' => 'Globalstar Europe ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'07' => array(
							'network' => 'Globalstar Europe ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'10' => array(
							'network' => 'Vivendi',
							'brand'   => 'SFR',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'Vivendi',
							'brand'   => 'SFR',
							'status'  => 'Operational',
						),
						'13' => array(
							'network' => 'Vivendi',
							'brand'   => 'SFR',
							'status'  => 'Inactive',
						),
						'14' => array(
							'network' => 'Iliad',
							'brand'   => 'Free Mobile',
							'status'  => 'Operational',
						),
						'15' => array(
							'network' => 'Iliad',
							'brand'   => 'Free Mobile',
							'status'  => 'Operational',
						),
						'20' => array(
							'network' => 'Bouygues Telecom ',
							'brand'   => 'Bouygues',
							'status'  => 'Operational',
						),
						'21' => array(
							'network' => 'Bouygues Telecom ',
							'brand'   => 'Bouygues',
							'status'  => 'Operational',
						),
						'88' => array(
							'network' => 'Bouygues Telecom( Zones Blanches) ',
							'brand'   => 'Bouygues',
							'status'  => 'Operational',
						),
					),
				),
				'742' => array(
					'country'      => 'French Guiana',
					'country_code' => 'GF',
					'operators'    => array(
						'01' => array(
							'network' => 'Orange Caribe French Guiana',
							'brand'   => 'Orange Caribe French Guiana',
							'status'  => 'Inactive',
						),
						'20' => array(
							'network' => 'Digicel French Guiana',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
					),
				),
				'547' => array(
					'country'      => 'French Polynesia',
					'country_code' => 'PF',
					'operators'    => array(
						'00' => array(
							'network' => 'Digicel Antilles Francaises Guyane',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'01' => array(
							'network' => 'Orange Caraibe Mobiles',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'02' => array(
							'network' => 'Outremer Telecom',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'03' => array(
							'network' => 'Saint Martin et Saint Barthelemy Telcell Sarl',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'08' => array(
							'network' => 'AMIGO GSM',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'20' => array(
							'network' => 'Tikiphone ',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'628' => array(
					'country'      => 'Gabon',
					'country_code' => 'GA',
					'operators'    => array(
						'01' => array(
							'network' => 'Gabon Telecom & Libertis S . A . ',
							'brand'   => 'Libertis',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Atlantique Télécom( Etisalat Group) ',
							'brand'   => 'Moov',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Celtel Gabon S . A . ',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'USAN Gabon S . A . ',
							'brand'   => 'Azur',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Réseau de lAdministration Gabonaise',
							'brand'   => 'RAG',
							'status'  => 'Operational',
						),
					),
				),
				'607' => array(
					'country'      => 'Gambia',
					'country_code' => 'GM',
					'operators'    => array(
						'01' => array(
							'network' => 'Gamcel ',
							'brand'   => 'Gamcel ',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Africell ',
							'brand'   => 'Africell ',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Comium Services Ltd ',
							'brand'   => 'Comium',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'QCell Gambia',
							'brand'   => 'QCell',
							'status'  => 'Operational',
						),
					),
				),
				'282' => array(
					'country'      => 'Georgia',
					'country_code' => 'GE',
					'operators'    => array(
						'01' => array(
							'network' => 'Geocell Ltd . ',
							'brand'   => 'Geocell',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Magti GSM Ltd . ',
							'brand'   => 'MagtiCom',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Iberiatel Ltd . ',
							'brand'   => 'Iberiatel',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Beeline',
							'brand'   => 'Beeline',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Sliknet',
							'brand'   => 'SLINKNET',
							'status'  => 'Operational',
						),
						'67' => array(
							'network' => 'Aquafon',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'88' => array(
							'network' => 'A - Mobile',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'262' => array(
					'country'      => 'Germany',
					'country_code' => 'DE',
					'operators'    => array(
						'01'  => array(
							'network' => 'T - Mobile Deutschland GmbH ',
							'brand'   => 'T - Mobile',
							'status'  => 'Operational',
						),
						'02'  => array(
							'network' => 'Vodafone D2 GmbH ',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'03'  => array(
							'network' => 'E - Plus Mobilfunk GmbH & Co . KG ',
							'brand'   => 'E - plus',
							'status'  => 'Operational',
						),
						'04'  => array(
							'network' => 'Vodafone D2 GmbH ',
							'brand'   => 'Vodafone( Reserved )',
							'status'  => 'Inactive',
						),
						'05'  => array(
							'network' => 'E - Plus Mobilfunk GmbH & Co . KG ',
							'brand'   => 'E - Plus( Reserved )',
							'status'  => 'Inactive',
						),
						'06'  => array(
							'network' => 'T - Mobile Deutschland GmbH ',
							'brand'   => 'T - Mobile( Reserved )',
							'status'  => 'Inactive',
						),
						'07'  => array(
							'network' => 'O2( Germany ) GmbH & Co . OHG ',
							'brand'   => 'O2',
							'status'  => 'Operational',
						),
						'08'  => array(
							'network' => 'O2( Germany ) GmbH & Co . OHG ',
							'brand'   => 'O2',
							'status'  => 'Operational',
						),
						'09'  => array(
							'network' => 'Vodafone D2 GmbH ',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'10'  => array(
							'network' => 'Arcor AG & Co . ( GSM - R )',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'11'  => array(
							'network' => 'O2( Germany ) GmbH & Co . OHG ',
							'brand'   => 'O2( RESERVED )',
							'status'  => 'Inactive',
						),
						'12'  => array(
							'network' => 'Dolphin Telecom( Deutschland ) GmbH ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'13'  => array(
							'network' => 'Mobilcom Multimedia GmbH ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'14'  => array(
							'network' => 'Group 3G UMTS GmbH( Quam ) ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'15'  => array(
							'network' => 'Airdata AG ',
							'brand'   => 'Airdata',
							'status'  => 'Inactive',
						),
						'16'  => array(
							'network' => 'MVNE( E - plus )',
							'brand'   => 'Vistream',
							'status'  => 'Operational',
						),
						'17'  => array(
							'network' => 'Ring Mobilfunk',
							'brand'   => 'Ring Mobilfunk',
							'status'  => 'Operational',
						),
						'20'  => array(
							'network' => 'E - Plus',
							'brand'   => 'OnePhone',
							'status'  => 'Operational',
						),
						'43'  => array(
							'network' => 'Lyca Mobile',
							'brand'   => 'Lyca',
							'status'  => 'Operational',
						),
						'60'  => array(
							'network' => 'DB Telematik( GSM - R )',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'76'  => array(
							'network' => 'Siemens AG,',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'77'  => array(
							'network' => 'E - Plus Mobilfunk GmbH & Co . KG ',
							'brand'   => 'E - Plus',
							'status'  => 'Inactive',
						),
						'901' => array(
							'network' => 'Debitel AG',
							'brand'   => 'Debitel',
							'status'  => 'Operational',
						),
					),
				),
				'620' => array(
					'country'      => 'Ghana',
					'country_code' => 'GH',
					'operators'    => array(
						'01'  => array(
							'network' => 'MTN Group',
							'brand'   => 'MTN',
							'status'  => 'Operational',
						),
						'02'  => array(
							'network' => 'Vodafone Group',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'03'  => array(
							'network' => 'Millicom Ghana',
							'brand'   => 'tiGO',
							'status'  => 'Operational',
						),
						'04'  => array(
							'network' => 'Kasapa Telecom Ltd . ',
							'brand'   => 'Expresso',
							'status'  => 'Operational',
						),
						'06'  => array(
							'network' => 'Airtel',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'997' => array(
							'network' => 'Glo Mobile Ghana',
							'brand'   => 'Glo Mobile',
							'status'  => 'Operational',
						),
					),
				),
				'266' => array(
					'country'      => 'Gibraltar',
					'country_code' => 'GI',
					'operators'    => array(
						'01' => array(
							'network' => 'Gibtelecom GSM ',
							'brand'   => 'GibTel',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'CTS Gibraltar',
							'brand'   => 'CTS Mobile',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'Cloud9 Mobile Communications ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'202' => array(
					'country'      => 'Greece',
					'country_code' => 'GR',
					'operators'    => array(
						'01' => array(
							'network' => 'Cosmote Mobile Teelecommunications S . A . ',
							'brand'   => 'Cosmote',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Vodafone - Panafon ',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'Wind Hellas Telecommunications S . A . ',
							'brand'   => 'Wind',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Wind Hellas Telecommunications S . A . ',
							'brand'   => 'Wind',
							'status'  => 'Operational',
						),
					),
				),
				'290' => array(
					'country'      => 'Greenland',
					'country_code' => 'GL',
					'operators'    => array(
						'01' => array(
							'network' => 'Tele Greenland ',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'352' => array(
					'country'      => 'Grenada',
					'country_code' => 'GD',
					'operators'    => array(
						'30'  => array(
							'network' => 'Digicel',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'110' => array(
							'network' => 'Cable & Wireless Grenada Ltd . ',
							'brand'   => 'Cable & Wireless',
							'status'  => 'Operational',
						),
					),
				),
				'535' => array(
					'country'      => 'Guam( US )',
					'country_code' => '',
					'operators'    => array(
						'47' => array(
							'network' => 'Docomo Pacific Inc',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'704' => array(
					'country'      => 'Guatemala',
					'country_code' => 'GT',
					'operators'    => array(
						'01' => array(
							'network' => 'Servicios de Comunicaciones Personales Inalámbricas, S . A . ',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Comunicaciones Celulares S . A . ',
							'brand'   => 'Comcel / Tigo',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Telefónica Centroamérica Guatemala S . A . ',
							'brand'   => 'Movistar',
							'status'  => 'Operational',
						),
					),
				),
				'611' => array(
					'country'      => 'Guinea',
					'country_code' => 'GN',
					'operators'    => array(
						'01' => array(
							'network' => 'Orange',
							'brand'   => 'Orange S . A . ',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Sotelgui Lagui',
							'brand'   => 'Sotelgui',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'INTERCEL Guinée',
							'brand'   => 'Telecel Guinee',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Areeba Guinea',
							'brand'   => 'MTN',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Cellcom Guinée SA ',
							'brand'   => 'Cellcom',
							'status'  => 'Operational',
						),
					),
				),
				'632' => array(
					'country'      => 'Guinea - Bissau',
					'country_code' => 'GW',
					'operators'    => array(
						'01' => array(
							'network' => 'Guinétel S . A . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'02' => array(
							'network' => 'Spacetel Guiné - Bissau S . A . ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => '	Orange',
							'brand'   => '	Orange',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Guinetel( Telecel Guinee SARL)',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'738' => array(
					'country'      => 'Guyana',
					'country_code' => 'GY',
					'operators'    => array(
						'01' => array(
							'network' => 'U - Mobile( Cellular ) Inc . ',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Guyana Telephone & Telegraph Co . ',
							'brand'   => 'GT & T Cellink Plus',
							'status'  => 'Operational',
						),
					),
				),
				'372' => array(
					'country'      => 'Haiti',
					'country_code' => 'HT',
					'operators'    => array(
						'01' => array(
							'network' => 'Communication Cellulaire dHaiti SA',
							'brand'   => 'Voila',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Unigestion Holding S . A',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Telecommunication S . A',
							'brand'   => 'NATCOM',
							'status'  => 'Operational',
						),
						'50' => array(
							'network' => 'Unigestion Holding S . A',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
					),
				),
				'708' => array(
					'country'      => 'Honduras',
					'country_code' => 'HN',
					'operators'    => array(
						'01' => array(
							'network' => 'Servicios de Comunicaciones de Honduras S . A . de C . V . ',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Celtel / Tigo',
							'brand'   => 'Tigo',
							'status'  => 'Operational',
						),
						'30' => array(
							'network' => 'Empresa Hondurena de Telecomunicaciones',
							'brand'   => 'Hondutel',
							'status'  => 'Operational',
						),
						'40' => array(
							'network' => 'Digicel de Honduras',
							'brand'   => 'DIGICEL',
							'status'  => 'Operational',
						),
					),
				),
				'454' => array(
					'country'      => 'Hong Kong',
					'country_code' => 'HK',
					'operators'    => array(
						'00' => array(
							'network' => 'CSL Ltd',
							'brand'   => '1O1O / One2Free',
							'status'  => 'Operational',
						),
						'01' => array(
							'network' => 'CITIC Telecom 1616',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'CSL Ltd',
							'brand'   => 'CSL',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Hutchison Telecom',
							'brand'   => '3 ( 3G)',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Hutchison Telecom',
							'brand'   => '3 ( 2G)',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Hutchison Telecom',
							'brand'   => '3 ( CDMA )',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'SmarTone Mobile Communications Ltd',
							'brand'   => 'SmarTone - Vodafone',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'China Unicom( Hong Kong) Ltd',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'Trident Telecom',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'China Motion Telecom',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'CSL Ltd',
							'brand'   => 'New World Mobility',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'China - Hong Kong Telecom',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'12' => array(
							'network' => 'China Mobile Hong Kong Company Ltd',
							'brand'   => 'CMCC HK',
							'status'  => 'Operational',
						),
						'14' => array(
							'network' => 'Hutchison Telecom',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'15' => array(
							'network' => '3G Radio System / SMT3G ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'16' => array(
							'network' => 'PCCW Ltd',
							'brand'   => 'PCCW Mobile',
							'status'  => 'Operational',
						),
						'17' => array(
							'network' => 'SmarTone Mobile Communications Limited',
							'brand'   => 'SmarTone Mobile Communications Limited',
							'status'  => 'Operational',
						),
						'18' => array(
							'network' => 'CSL Ltd',
							'brand'   => 'CSL',
							'status'  => 'Operational',
						),
						'19' => array(
							'network' => 'PCCW Ltd',
							'brand'   => 'PCCW Mobile',
							'status'  => 'Operational',
						),
						'22' => array(
							'network' => '',
							'brand'   => 'SmarTone Mobile( P - Plus )',
							'status'  => 'Operational',
						),
						'29' => array(
							'network' => 'PCCW Ltd',
							'brand'   => 'PCCW Mobile',
							'status'  => 'Operational',
						),
						'40' => array(
							'network' => 'shared by private TETRA systems',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'47' => array(
							'network' => 'Hong Kong Police Force - TETRA systems',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'216' => array(
					'country'      => 'Hungary',
					'country_code' => 'HU',
					'operators'    => array(
						'01' => array(
							'network' => 'Telenor Magyarország Zrt . ',
							'brand'   => 'Telenor',
							'status'  => 'Operational',
						),
						'30' => array(
							'network' => 'Magyar Telekom Plc',
							'brand'   => 'T - Mobile',
							'status'  => 'Operational',
						),
						'70' => array(
							'network' => 'Vodafone Magyarország Zrt . ',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
					),
				),
				'274' => array(
					'country'      => 'Iceland',
					'country_code' => 'IS',
					'operators'    => array(
						'01' => array(
							'network' => 'Iceland Telecom Ltd . ',
							'brand'   => 'Siminn',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Vodafone',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Islandssimi GSM ehf ',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'IMC Islande ehf ',
							'brand'   => 'Viking',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => '09 Mobile',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'IceCell ehf ',
							'brand'   => 'IceCell',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'Nova ehf',
							'brand'   => 'Nova',
							'status'  => 'Operational',
						),
					),
				),
				'404' => array(
					'country'      => 'India',
					'country_code' => 'IN',
					'operators'    => array(
						'00'  => array(
							'network' => 'Sistema Shyam( Rajasthan )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'01'  => array(
							'network' => 'Reliance Telecom( Andhra Pradesh)',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'02'  => array(
							'network' => 'Bharti Airtel Ltd( Punjab )',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'03'  => array(
							'network' => 'Reliance Telecom( Bihar )',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'04'  => array(
							'network' => 'Idea( Delhi )',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'05'  => array(
							'network' => 'Vodafone( Gujarat )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'06'  => array(
							'network' => 'Reliance Telecom( Gujarat )',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'07'  => array(
							'network' => 'Idea( Andhra Pradesh)',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'08'  => array(
							'network' => 'Reliance Telecom( Himachal Pradesh)',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'09'  => array(
							'network' => 'Reliance Telecom( Assam )',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'10'  => array(
							'network' => 'Bharti Airtel Ltd( Delhi )',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'11'  => array(
							'network' => 'Vodafone( Delhi )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'12'  => array(
							'network' => 'Idea( Haryana )',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'13'  => array(
							'network' => 'Reliance Telecom( Maharashtra )',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'14'  => array(
							'network' => 'Idea( Punjab )',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'15'  => array(
							'network' => 'Vodafone( Uttar Pradesh( East )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'16'  => array(
							'network' => 'Bharti Airtel Ltd( North East)',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'17'  => array(
							'network' => 'Reliance Telecom( Orissa )',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'18'  => array(
							'network' => 'Reliance Telecom( Himachal Pradesh)',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'19'  => array(
							'network' => 'Idea( Kerala )',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'20'  => array(
							'network' => 'Vodafone( Mumbai )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'21'  => array(
							'network' => 'LOOP( Mumbai )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'22'  => array(
							'network' => 'Reliance Telecom( Uttar Pradesh( West )',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'23'  => array(
							'network' => 'Reliance Telecom( West Bengal)',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'24'  => array(
							'network' => 'HFCL INFOTEL( Punjab )',
							'brand'   => 'HFCL INFOTEL',
							'status'  => 'Operational',
						),
						'25'  => array(
							'network' => 'TATA Teleservices( Andhra Pradesh)',
							'brand'   => 'TATA Teleservices',
							'status'  => 'Operational',
						),
						'26'  => array(
							'network' => 'TATA Teleservices( Assam )',
							'brand'   => 'TATA Teleservices',
							'status'  => 'Operational',
						),
						'27'  => array(
							'network' => 'Vodafone( Maharashtra )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'28'  => array(
							'network' => 'Aircel( Orissa )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'29'  => array(
							'network' => 'TATA Teleservices( Delhi )',
							'brand'   => 'TATA Teleservices',
							'status'  => 'Operational',
						),
						'30'  => array(
							'network' => 'Vodafone( Kolkata )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'31'  => array(
							'network' => 'Bharti Airtel Ltd( Kolkata )',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'32'  => array(
							'network' => 'TATA Teleservices( Himachal Pradesh)',
							'brand'   => 'TATA Teleservices',
							'status'  => 'Operational',
						),
						'33'  => array(
							'network' => 'Aircel( North East)',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'34'  => array(
							'network' => 'TATA Teleservices( Karnataka )',
							'brand'   => 'TATA Teleservices',
							'status'  => 'Operational',
						),
						'35'  => array(
							'network' => 'Aircel( Himachal Pradesh)',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'36'  => array(
							'network' => 'Reliance Telecom( Bihar )',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'37'  => array(
							'network' => 'Aircel( Jammu & Kashmir )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'38'  => array(
							'network' => 'TATA Teleservices( Madhya Pradesh)',
							'brand'   => 'TATA Teleservices',
							'status'  => 'Operational',
						),
						'39'  => array(
							'network' => 'TATA Teleservices( Mumbai )',
							'brand'   => 'TATA Teleservices',
							'status'  => 'Operational',
						),
						'40'  => array(
							'network' => 'Bharti Airtel Ltd( Chennai )',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'41'  => array(
							'network' => 'TATA Teleservices( Orissa )',
							'brand'   => 'TATA Teleservices',
							'status'  => 'Operational',
						),
						'42'  => array(
							'network' => 'Aircel( Tamilnadu )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'43'  => array(
							'network' => 'Vodafone( Tamilnadu )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'44'  => array(
							'network' => 'Idea( Karnataka )',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'45'  => array(
							'network' => 'TATA Teleservices( Uttar Pradesh( East )',
							'brand'   => 'TATA Teleservices',
							'status'  => 'Operational',
						),
						'46'  => array(
							'network' => 'Vodafone( Kerala )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'47'  => array(
							'network' => 'TATA Teleservices( West Bengal)',
							'brand'   => 'TATA Teleservices',
							'status'  => 'Operational',
						),
						'48'  => array(
							'network' => 'INDIAN RAILWAYS GSM - R( ALL CIRCLES)',
							'brand'   => 'Indian Raylways',
							'status'  => 'Operational',
						),
						'49'  => array(
							'network' => 'Bharti Airtel Ltd( Andhra Pradesh)',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'50'  => array(
							'network' => 'Reliance Telecom( North East)',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'51'  => array(
							'network' => 'BSNL( Himachal Pradesh)',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'52'  => array(
							'network' => 'Reliance Telecom( Orissa )',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'53'  => array(
							'network' => 'BSNL( Punjab )',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'54'  => array(
							'network' => 'Bharti Airtel Ltd( Uttar Pradesh( East )',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'55'  => array(
							'network' => 'BSNL( Uttar Pradesh( East )',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'56'  => array(
							'network' => 'Bharti Airtel Ltd( Assam )',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'57'  => array(
							'network' => 'BSNL( Gujarat )',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'58'  => array(
							'network' => 'BSNL( Madhya Pradesh)',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'59'  => array(
							'network' => 'BSNL( Rajasthan )',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'60'  => array(
							'network' => 'Vodafone( Rajasthan )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'62'  => array(
							'network' => 'BSNL( Jammu & Kashmir )',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'64'  => array(
							'network' => 'BSNL( Chennai )',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'66'  => array(
							'network' => 'Vodafone( Uttar Pradesh( West )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'67'  => array(
							'network' => 'Reliance Telecom( Madhya Pradesh)',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'68'  => array(
							'network' => 'MTNL( Delhi )',
							'brand'   => 'Dolphin',
							'status'  => 'Operational',
						),
						'69'  => array(
							'network' => 'MTNL( Mumbai )',
							'brand'   => 'Dolphin',
							'status'  => 'Operational',
						),
						'70'  => array(
							'network' => 'Bharti Airtel Ltd( Rajasthan )',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'71'  => array(
							'network' => 'BSNL( Karnataka )',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'72'  => array(
							'network' => 'BSNL( Kerala )',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'73'  => array(
							'network' => 'BSNL( Andhra Pradesh)',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'74'  => array(
							'network' => 'BSNL( West Bengal)',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'75'  => array(
							'network' => 'BSNL( Bihar )',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'76'  => array(
							'network' => 'BSNL( Orissa )',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'77'  => array(
							'network' => 'BSNL( North East)',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'78'  => array(
							'network' => 'Idea( Madhya Pradesh)',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'79'  => array(
							'network' => 'BSNL( Andaman Nicobar)',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'80'  => array(
							'network' => 'BSNL( Tamilnadu )',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'81'  => array(
							'network' => 'BSNL( Kolkata )',
							'brand'   => 'BSNL',
							'status'  => 'Operational',
						),
						'82'  => array(
							'network' => 'Idea( Himachal Pradesh)',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'83'  => array(
							'network' => 'Reliance Telecom( Kolkata )',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'84'  => array(
							'network' => 'Vodafone( Chennai )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'85'  => array(
							'network' => 'Reliance Telecom( West Bengal)',
							'brand'   => 'Reliance Telecom',
							'status'  => 'Operational',
						),
						'86'  => array(
							'network' => 'Vodafone( Karnataka )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'87'  => array(
							'network' => 'Idea( Rajasthan )',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'88'  => array(
							'network' => 'Vodafone( Punjab )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'89'  => array(
							'network' => 'Idea( Uttar Pradesh( East )',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'90'  => array(
							'network' => 'Bharti Airtel Ltd( Maharashtra )',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'91'  => array(
							'network' => 'Aircel( Kolkata )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'92'  => array(
							'network' => 'Bharti Airtel Ltd( Mumbai )',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'93'  => array(
							'network' => 'Bharti Airtel Ltd( Madhya Pradesh)',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'94'  => array(
							'network' => 'Bharti Airtel Ltd( Tamilnadu )',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'95'  => array(
							'network' => 'Bharti Airtel Ltd( Kerala )',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'96'  => array(
							'network' => 'Bharti Airtel Ltd( Haryana )',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'97'  => array(
							'network' => 'Bharti Airtel Ltd( Uttar Pradesh( West )',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'98'  => array(
							'network' => 'Bharti Airtel Ltd( Gujarat )',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'750' => array(
							'network' => 'Vodafone( Jammu & Kashmir )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'751' => array(
							'network' => 'Vodafone( Assam )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'752' => array(
							'network' => 'Vodafone( Bihar )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'753' => array(
							'network' => 'Vodafone( Orissa )',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'754' => array(
							'network' => 'Vodafone( Himachal Pradesh)',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'755' => array(
							'network' => 'Vodafone( North East)',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'756' => array(
							'network' => 'Vodafone( Madhya Pradesh)',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'799' => array(
							'network' => 'Idea( Mumbai )',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'800' => array(
							'network' => 'Aircel( Delhi )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'801' => array(
							'network' => 'Aircel( Andhra Pradesh)',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'802' => array(
							'network' => 'Aircel( Gujarat )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'803' => array(
							'network' => 'Aircel( Karnataka )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'804' => array(
							'network' => 'Aircel( Maharashtra )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'805' => array(
							'network' => 'Aircel( Mumbai )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'806' => array(
							'network' => 'Aircel( Rajasthan )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'807' => array(
							'network' => 'Aircel( Haryana )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'808' => array(
							'network' => 'Aircel( Madhya Pradesh)',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'809' => array(
							'network' => 'Aircel( Kerala )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'810' => array(
							'network' => 'Aircel( Uttar Pradesh( East )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'811' => array(
							'network' => 'Aircel( Uttar Pradesh( West )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'812' => array(
							'network' => 'Aircel( Punjab )',
							'brand'   => 'Aircel',
							'status'  => 'Operational',
						),
						'813' => array(
							'network' => 'Telenor Unitech( Haryana )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'814' => array(
							'network' => 'Telenor Unitech( Himachal Pradesh)',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'815' => array(
							'network' => 'Telenor Unitech( Jammu & Kashmir )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'816' => array(
							'network' => 'Telenor Unitech( Punjab )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'817' => array(
							'network' => 'Telenor Unitech( Rajasthan )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'818' => array(
							'network' => 'Telenor Unitech( Uttar Pradesh( West )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'819' => array(
							'network' => 'Telenor Unitech( Andhra Pradesh)',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'820' => array(
							'network' => 'Telenor Unitech( Karnataka )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'821' => array(
							'network' => 'Telenor Unitech( Kerala )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'822' => array(
							'network' => 'Telenor Unitech( Kolkata )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'823' => array(
							'network' => 'Videocon( Andhra Pradesh)',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'824' => array(
							'network' => 'Videocon( Assam )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'825' => array(
							'network' => 'Videocon( Bihar )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'826' => array(
							'network' => 'Videocon( Delhi )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'827' => array(
							'network' => 'Videocon( Gujarat )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'828' => array(
							'network' => 'Videocon( Haryana )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'829' => array(
							'network' => 'Videocon( Himachal Pradesh)',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'830' => array(
							'network' => 'Videocon( Jammu & Kashmir )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'831' => array(
							'network' => 'Videocon( Karnataka )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'832' => array(
							'network' => 'Videocon( Kerala )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'833' => array(
							'network' => 'Videocon( Kolkata )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'834' => array(
							'network' => 'Videocon( Madhya Pradesh)',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'835' => array(
							'network' => 'Videocon( Maharashtra )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'836' => array(
							'network' => 'Videocon( Mumbai )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'837' => array(
							'network' => 'Videocon( North East)',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'838' => array(
							'network' => 'Videocon( Orissa )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'839' => array(
							'network' => 'Videocon( Rajasthan )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'840' => array(
							'network' => 'Videocon( Tamilnadu )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'841' => array(
							'network' => 'Videocon( Uttar Pradesh( East )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'842' => array(
							'network' => 'Videocon( Uttar Pradesh( West )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'843' => array(
							'network' => 'Videocon( West Bengal)',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
						'844' => array(
							'network' => 'Telenor Unitech( Delhi )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'845' => array(
							'network' => 'Idea( Assam )',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'846' => array(
							'network' => 'Idea( Jammu & Kashmir )',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'848' => array(
							'network' => 'Idea( Kolkata )',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'849' => array(
							'network' => 'Idea( North East)',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'850' => array(
							'network' => 'Idea( Orissa )',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'852' => array(
							'network' => 'Idea( Tamilnadu )',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'853' => array(
							'network' => 'Idea( West Bengal)',
							'brand'   => 'Idea',
							'status'  => 'Operational',
						),
						'854' => array(
							'network' => 'LOOP( Andhra Pradesh)',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'855' => array(
							'network' => 'LOOP( Assam )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'856' => array(
							'network' => 'LOOP( Bihar )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'857' => array(
							'network' => 'LOOP( Delhi )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'858' => array(
							'network' => 'LOOP( Gujarat )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'859' => array(
							'network' => 'LOOP( Haryana )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'860' => array(
							'network' => 'LOOP( Himachal Pradesh)',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'861' => array(
							'network' => 'LOOP( Jammu & Kashmir )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'862' => array(
							'network' => 'LOOP( Karnataka )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'863' => array(
							'network' => 'LOOP( Kerala )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'864' => array(
							'network' => 'LOOP( Kolkata )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'865' => array(
							'network' => 'LOOP( Madhya Pradesh)',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'866' => array(
							'network' => 'LOOP( Maharashtra )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'867' => array(
							'network' => 'LOOP( North East)',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'868' => array(
							'network' => 'LOOP( Orissa )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'869' => array(
							'network' => 'LOOP( Punjab )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'870' => array(
							'network' => 'LOOP( Rajasthan )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'871' => array(
							'network' => 'LOOP( Tamilnadu )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'872' => array(
							'network' => 'LOOP( Uttar Pradesh( East )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'873' => array(
							'network' => 'LOOP( Uttar Pradesh( West )',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'874' => array(
							'network' => 'LOOP( West Bengal)',
							'brand'   => 'LOOP',
							'status'  => 'Operational',
						),
						'875' => array(
							'network' => 'Telenor Unitech( Assam )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'876' => array(
							'network' => 'Telenor Unitech( Bihar )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'877' => array(
							'network' => 'Telenor Unitech( North East)',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'878' => array(
							'network' => 'Telenor Unitech( Orissa )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'879' => array(
							'network' => 'Telenor Unitech( Uttar Pradesh( East )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'880' => array(
							'network' => 'Telenor Unitech( West Bengal)',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'881' => array(
							'network' => 'S TEL( Assam )',
							'brand'   => 'S TEL',
							'status'  => 'Operational',
						),
						'882' => array(
							'network' => 'S TEL( Bihar )',
							'brand'   => 'S TEL',
							'status'  => 'Operational',
						),
						'883' => array(
							'network' => 'S TEL( Himachal Pradesh)',
							'brand'   => 'S TEL',
							'status'  => 'Operational',
						),
						'884' => array(
							'network' => 'S TEL( Jammu & Kashmir )',
							'brand'   => 'S TEL',
							'status'  => 'Operational',
						),
						'885' => array(
							'network' => 'S TEL( North East)',
							'brand'   => 'S TEL',
							'status'  => 'Operational',
						),
						'886' => array(
							'network' => 'S TEL( Orissa )',
							'brand'   => 'S TEL',
							'status'  => 'Operational',
						),
						'887' => array(
							'network' => 'Sistema Shyam( Andhra Pradesh)',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'888' => array(
							'network' => 'Sistema Shyam( Assam )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'889' => array(
							'network' => 'Sistema Shyam( Bihar )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'890' => array(
							'network' => 'Sistema Shyam( Delhi )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'891' => array(
							'network' => 'Sistema Shyam( Gujarat )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'892' => array(
							'network' => 'Sistema Shyam( Haryana )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'893' => array(
							'network' => 'Sistema Shyam( Himachal Pradesh)',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'894' => array(
							'network' => 'Sistema Shyam( Jammu & Kashmir )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'895' => array(
							'network' => 'Sistema Shyam( Karnataka )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'896' => array(
							'network' => 'Sistema Shyam( Kerala )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'897' => array(
							'network' => 'Sistema Shyam( Kolkata )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'898' => array(
							'network' => 'Sistema Shyam( Madhya Pradesh)',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'899' => array(
							'network' => 'Sistema Shyam( Maharashtra )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'900' => array(
							'network' => 'Sistema Shyam( Mumbai )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'901' => array(
							'network' => 'Sistema Shyam( North East)',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'902' => array(
							'network' => 'Sistema Shyam( Orissa )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'903' => array(
							'network' => 'Sistema Shyam( Punjab )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'904' => array(
							'network' => 'Sistema Shyam( Tamilnadu )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'905' => array(
							'network' => 'Sistema Shyam( Uttar Pradesh( East )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'906' => array(
							'network' => 'Sistema Shyam( Uttar Pradesh( West )',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'907' => array(
							'network' => 'Sistema Shyam( West Bengal)',
							'brand'   => 'Sistema Shyam',
							'status'  => 'Operational',
						),
						'912' => array(
							'network' => 'Etisalat DB( Andhra Pradesh)',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'913' => array(
							'network' => 'Etisalat DB( Delhi )',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'914' => array(
							'network' => 'Etisalat DB( Gujarat )',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'915' => array(
							'network' => 'Etisalat DB( Haryana )',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'916' => array(
							'network' => 'Etisalat DB( Karnataka )',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'917' => array(
							'network' => 'Etisalat DB( Kerala )',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'918' => array(
							'network' => 'Etisalat DB( Maharashtra )',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'919' => array(
							'network' => 'Etisalat DB( Mumbai )',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'920' => array(
							'network' => 'Etisalat DB( Punjab )',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'921' => array(
							'network' => 'Etisalat DB( Rajasthan )',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'922' => array(
							'network' => 'Etisalat DB( Tamilnadu )',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'923' => array(
							'network' => 'Etisalat DB( Uttar Pradesh( East )',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'924' => array(
							'network' => 'Etisalat DB( Uttar Pradesh( West )',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'925' => array(
							'network' => 'Telenor Unitech( Tamilnadu )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'926' => array(
							'network' => 'Telenor Unitech( Mumbai )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'927' => array(
							'network' => 'Telenor Unitech( Gujarat )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'928' => array(
							'network' => 'Telenor Unitech( Madhya Pradesh)',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'929' => array(
							'network' => 'Telenor Unitech( Maharashtra )',
							'brand'   => 'Uninor',
							'status'  => 'Operational',
						),
						'930' => array(
							'network' => 'Etisalat DB( Bihar )',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'931' => array(
							'network' => 'Etisalat DB( Madhya Pradesh)',
							'brand'   => 'Etisalat DB',
							'status'  => 'Operational',
						),
						'932' => array(
							'network' => 'Videocon( Punjab )',
							'brand'   => 'Videocon',
							'status'  => 'Operational',
						),
					),
				),
				'510' => array(
					'country'      => 'Indonesia',
					'country_code' => 'ID',
					'operators'    => array(
						'00' => array(
							'network' => 'PT Pasifik Satelit Nusantara( ACeS )',
							'brand'   => 'PSN',
							'status'  => 'Inactive',
						),
						'01' => array(
							'network' => 'PT Indonesian Satellite Corporation Tbk( INDOSAT )',
							'brand'   => 'INDOSAT',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'PT Indosat TBK',
							'brand'   => 'StarOne',
							'status'  => 'Inactive',
						),
						'07' => array(
							'network' => 'PT Telkom',
							'brand'   => 'TelkomFlexi',
							'status'  => 'Inactive',
						),
						'08' => array(
							'network' => 'PT Natrindo Telepon Seluler',
							'brand'   => 'AXIS',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'PT Smart Telecom',
							'brand'   => 'SMART',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'PT Telekomunikasi Selular',
							'brand'   => 'Telkomsel',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'PT XL Axiata Tbk',
							'brand'   => 'XL',
							'status'  => 'Operational',
						),
						'20' => array(
							'network' => 'Pt Telcom Indonesia TBK',
							'brand'   => 'TelkomMobile',
							'status'  => 'Inactive',
						),
						'21' => array(
							'network' => 'PT Indonesian Satelite Corporation Tbk( Indosat )',
							'brand'   => 'IM3',
							'status'  => 'Inactive',
						),
						'27' => array(
							'network' => 'PT Sampoerna Telekomunikasi Indonesia',
							'brand'   => 'Ceria',
							'status'  => 'Operational',
						),
						'28' => array(
							'network' => 'PT Mobile - 8 Telecom',
							'brand'   => 'Fren / Hepi',
							'status'  => 'Operational',
						),
						'89' => array(
							'network' => 'PT Hutchison CP Telecommunications',
							'brand'   => '3',
							'status'  => 'Operational',
						),
						'99' => array(
							'network' => 'PT Bakrie Telecom',
							'brand'   => 'Esia',
							'status'  => 'Inactive',
						),
					),
				),
				'901' => array(
					'country'      => 'International',
					'country_code' => '00',
					'operators'    => array(
						'01' => array(
							'network' => 'ICO Satellite Management',
							'brand'   => 'ICO',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Iridium',
							'brand'   => 'Iridium',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Globalstar',
							'brand'   => 'Globalstar',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Thuraya RMSS Network',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Thuraya Satellite Telecommunications Company',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'ACeS',
							'brand'   => 'ACeS',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'Inmarsat',
							'brand'   => 'Inmarsat',
							'status'  => 'Operational',
						),
						'12' => array(
							'network' => 'Maritime Communications Partner AS',
							'brand'   => 'MCP',
							'status'  => 'Operational',
						),
						'13' => array(
							'network' => 'Global Networks Switzerland Inc . ',
							'brand'   => 'GSM . AQ',
							'status'  => 'Operational',
						),
						'14' => array(
							'network' => 'AeroMobile AS',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'15' => array(
							'network' => 'OnAir Switzerland Sarl',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'17' => array(
							'network' => 'Navitas',
							'brand'   => 'Navitas',
							'status'  => 'Operational',
						),
						'18' => array(
							'network' => 'AT & T Mobility',
							'brand'   => 'Cellular @Sea',
							'status'  => 'Operational',
						),
						'21' => array(
							'network' => 'Seanet Maritime Communications',
							'brand'   => 'Seanet',
							'status'  => 'Operational',
						),
					),
				),
				'432' => array(
					'country'      => 'Iran, Islamic Republic Of',
					'country_code' => 'IR',
					'operators'    => array(
						'11' => array(
							'network' => 'Mobile Communications Company of Iran',
							'brand'   => 'IR - MCI',
							'status'  => 'Operational',
						),
						'14' => array(
							'network' => 'KIFZO( Telecommunication Kish Co .)',
							'brand'   => 'TKC',
							'status'  => 'Operational',
						),
						'19' => array(
							'network' => 'Mobile Telecommunications Company of Esfahan',
							'brand'   => 'MTCE',
							'status'  => 'Operational',
						),
						'32' => array(
							'network' => 'Rafsanjan Industrial Complex',
							'brand'   => 'Taliya',
							'status'  => 'Operational',
						),
						'35' => array(
							'network' => 'Irancell Telecommunications Services Company',
							'brand'   => 'Irancell',
							'status'  => 'Operational',
						),
						'70' => array(
							'network' => 'Telephone Communications Company of Iran',
							'brand'   => 'TCI',
							'status'  => 'Operational',
						),
						'93' => array(
							'network' => '',
							'brand'   => 'Iraphone',
							'status'  => 'Operational',
						),
					),
				),
				'418' => array(
					'country'      => 'Iraq',
					'country_code' => 'IQ',
					'operators'    => array(
						'05' => array(
							'network' => 'Asia Cell Telecommunications Com . ',
							'brand'   => 'Asia Cell',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'Korek Telecom Ltd',
							'brand'   => 'Korek',
							'status'  => 'Operational',
						),
						'20' => array(
							'network' => 'Zain Iraq',
							'brand'   => 'Zain',
							'status'  => 'Operational',
						),
						'30' => array(
							'network' => 'Zain Iraq',
							'brand'   => 'Zain',
							'status'  => 'Operational',
						),
						'40' => array(
							'network' => 'Korek Telecom Ltd',
							'brand'   => 'Korek',
							'status'  => 'Operational',
						),
						'45' => array(
							'network' => 'Mobitel Co . Ltd',
							'brand'   => 'Mobiltel',
							'status'  => 'Operational',
						),
						'92' => array(
							'network' => 'Ommnea Wireless',
							'brand'   => 'Omnnea',
							'status'  => 'Operational',
						),
					),
				),
				'272' => array(
					'country'      => 'Ireland',
					'country_code' => 'IE',
					'operators'    => array(
						'01' => array(
							'network' => 'Vodafone Ireland Plc ',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'O2 Ireland',
							'brand'   => 'O2',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Meteor Mobile Communications Ltd . ',
							'brand'   => 'Meteor',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Access Telecom Ltd . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'05' => array(
							'network' => 'Hutchison 3G Ireland limited',
							'brand'   => '3',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Eircom ',
							'brand'   => 'Eircom',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'Clever Communications Ltd . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'11' => array(
							'network' => 'Liffey Telecom',
							'brand'   => 'Tesco Mobile',
							'status'  => 'Operational',
						),
						'13' => array(
							'network' => '',
							'brand'   => 'Lyca Mobile',
							'status'  => 'Operational',
						),
					),
				),
				'425' => array(
					'country'      => 'Israel',
					'country_code' => 'IL',
					'operators'    => array(
						'01'  => array(
							'network' => 'Partner Communications Co . Ltd . ',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'02'  => array(
							'network' => 'Cellcom Israel Ltd . ',
							'brand'   => 'Cellcom',
							'status'  => 'Operational',
						),
						'03'  => array(
							'network' => 'Pelephone Communications Ltd . ',
							'brand'   => 'Pelephone',
							'status'  => 'Operational',
						),
						'05'  => array(
							'network' => 'Palestine Cellular Communications',
							'brand'   => 'Jawwal',
							'status'  => 'Operational',
						),
						'06'  => array(
							'network' => '',
							'brand'   => 'Wataniya Palestine',
							'status'  => 'Inactive',
						),
						'07'  => array(
							'network' => 'Hot Mobile',
							'brand'   => 'Hot Mobile',
							'status'  => 'Operational',
						),
						'08'  => array(
							'network' => '',
							'brand'   => 'Golan Telecom',
							'status'  => 'Operational',
						),
						'303' => array(
							'network' => 'Pelephone Communications Ltd . ',
							'brand'   => 'Rami Levy',
							'status'  => 'Operational',
						),
					),
				),
				'222' => array(
					'country'      => 'Italy',
					'country_code' => 'IT',
					'operators'    => array(
						'01' => array(
							'network' => 'Telecom Italia SpA',
							'brand'   => 'TIM',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Elsacom ',
							'brand'   => 'Elsacom',
							'status'  => 'Inactive',
						),
						'07' => array(
							'network' => '',
							'brand'   => 'Noverca',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => '',
							'brand'   => 'Fastweb',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Vodafone Omnitel N . V . ',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'30' => array(
							'network' => 'Rete Ferroviaria Italiana',
							'brand'   => 'RFI',
							'status'  => 'Inactive',
						),
						'77' => array(
							'network' => '',
							'brand'   => 'IPSE 2000',
							'status'  => 'Inactive',
						),
						'88' => array(
							'network' => 'Wind Telecomunicazioni SpA',
							'brand'   => 'Wind',
							'status'  => 'Operational',
						),
						'98' => array(
							'network' => '',
							'brand'   => 'Blu',
							'status'  => 'Inactive',
						),
						'99' => array(
							'network' => 'Hutchison 3G',
							'brand'   => '3 Italia',
							'status'  => 'Operational',
						),
					),
				),
				'338' => array(
					'country'      => 'Jamaica',
					'country_code' => 'JM',
					'operators'    => array(
						'05'  => array(
							'network' => 'JM DIGICEL',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'20'  => array(
							'network' => 'Cable & Wireless Jamaica Ltd . ',
							'brand'   => 'LIME( formerly known as Cable & Wireless)',
							'status'  => 'Operational',
						),
						'50'  => array(
							'network' => 'Digicel( Jamaica ) Limited',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'70'  => array(
							'network' => 'Oceanic Digital Jamaica Limited',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
						'180' => array(
							'network' => 'Cable and Wireless Jamaica Limited',
							'brand'   => 'LIME( formerly known as Cable & Wireless)',
							'status'  => 'Operational',
						),
					),
				),
				'440' => array(
					'country'      => 'Japan',
					'country_code' => 'JP',
					'operators'    => array(
						'00' => array(
							'network' => 'Emobile Ltd',
							'brand'   => 'eMobile',
							'status'  => 'Operational',
						),
						'01' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'NTT DoCoMo Kansai',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'NTT DoCoMo Hokuriku',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'SoftBank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'NTT DoCoMo Kansai',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'NTT DoCoMo Tokai',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'12' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'13' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'14' => array(
							'network' => 'NTT DoCoMo Thoku',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'15' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'16' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'17' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'18' => array(
							'network' => 'NTT DoCoMo Tokai',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'19' => array(
							'network' => 'NTT DoCoMo Hokkaido',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'20' => array(
							'network' => 'SoftBank Mobile Corp',
							'brand'   => 'SoftBank',
							'status'  => 'Operational',
						),
						'21' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'22' => array(
							'network' => 'NTT DoCoMo Kansai',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'23' => array(
							'network' => 'NTT DoCoMo Tokai',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'24' => array(
							'network' => 'NTT DoCoMo Chugoku',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'25' => array(
							'network' => 'NTT DoCoMo Hokkaido',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'26' => array(
							'network' => 'NTT DoCoMo Kyushu',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'27' => array(
							'network' => 'NTT DoCoMo Tohoku',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'28' => array(
							'network' => 'NTT DoCoMo Shikoku',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'29' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'30' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'31' => array(
							'network' => 'NTT DoCoMo Kansai',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'32' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'33' => array(
							'network' => 'NTT DoCoMo Tokai',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'34' => array(
							'network' => 'NTT DoCoMo Kyushu',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'35' => array(
							'network' => 'NTT DoCoMo Kansai',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'36' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'37' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'38' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'39' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'40' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'41' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'42' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'43' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'44' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'45' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'46' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'47' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'48' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'49' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'50' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'51' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'52' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'53' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'54' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'55' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'56' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'58' => array(
							'network' => 'NTT DoCoMo Kansai',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'60' => array(
							'network' => 'NTT DoCoMo Kansai',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'61' => array(
							'network' => 'NTT DoCoMo Chugoku',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'62' => array(
							'network' => 'NTT DoCoMo Kyushu',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'63' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'64' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'65' => array(
							'network' => 'NTT DoCoMo Shikoku',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'66' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'67' => array(
							'network' => 'NTT DoCoMo Tohoku',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'68' => array(
							'network' => 'NTT DoCoMo Kyushu',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'69' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'70' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'Au',
							'status'  => 'Operational',
						),
						'71' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'72' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'73' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'74' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'75' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'76' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'77' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'78' => array(
							'network' => 'Okinawa Cellular Telephone',
							'brand'   => 'Okinawa',
							'status'  => 'Operational',
						),
						'79' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'80' => array(
							'network' => 'TU - KA Cellular Tokyo',
							'brand'   => 'TU - KA',
							'status'  => 'Inactive',
						),
						'81' => array(
							'network' => 'TU - KA Cellular Tokyo',
							'brand'   => 'TU - KA',
							'status'  => 'Inactive',
						),
						'82' => array(
							'network' => 'TU - KA Phone Kansai',
							'brand'   => 'TU - KA',
							'status'  => 'Inactive',
						),
						'83' => array(
							'network' => 'TU - KA Cellular Tokai',
							'brand'   => 'TU - KA',
							'status'  => 'Inactive',
						),
						'85' => array(
							'network' => 'TU - KA Cellular Tokai',
							'brand'   => 'TU - KA',
							'status'  => 'Inactive',
						),
						'86' => array(
							'network' => 'TU - KA Cellular Tokyo',
							'brand'   => 'TU - KA',
							'status'  => 'Inactive',
						),
						'87' => array(
							'network' => 'NTT DoCoMo Chugoku',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
						'88' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'89' => array(
							'network' => 'KDDI Corporation',
							'brand'   => 'KDDI',
							'status'  => 'Operational',
						),
						'90' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'91' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'92' => array(
							'network' => 'SoftBank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'93' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'94' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'95' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'96' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'97' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'98' => array(
							'network' => 'Softbank Mobile Corp',
							'brand'   => 'Softbank',
							'status'  => 'Operational',
						),
						'99' => array(
							'network' => 'NTT DoCoMo',
							'brand'   => 'DoCoMo',
							'status'  => 'Operational',
						),
					),
				),
				'416' => array(
					'country'      => 'Jordan',
					'country_code' => 'JO',
					'operators'    => array(
						'01' => array(
							'network' => 'Jordan Mobile Telephone Services',
							'brand'   => 'zain JO',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Xpress ',
							'brand'   => 'XPress Telecom',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Umniah ',
							'brand'   => 'Umniah',
							'status'  => 'Operational',
						),
						'77' => array(
							'network' => 'Petra Jordanian Mobile Telecommunications Company( MobileCom )',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
					),
				),
				'401' => array(
					'country'      => 'Kazakhstan',
					'country_code' => 'KZ',
					'operators'    => array(
						'01' => array(
							'network' => 'Kar - Tel llc ',
							'brand'   => 'Beeline',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'GSM Kazakhstan Ltd',
							'brand'   => 'Kcell',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Dalacom( CDMA )',
							'brand'   => 'Dalacom',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'Kazakhtelecom',
							'brand'   => 'Kazakhtelecom',
							'status'  => 'Operational',
						),
						'77' => array(
							'network' => 'Mobile Telecom Service LLP',
							'brand'   => 'Mobile Telecom Service',
							'status'  => 'Operational',
						),
					),
				),
				'639' => array(
					'country'      => 'Kenya',
					'country_code' => 'KE',
					'operators'    => array(
						'02' => array(
							'network' => 'Safaricom Ltd . ',
							'brand'   => 'Safaricom',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Celtel Kenya Limited',
							'brand'   => 'Zain',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Telkom Kenya',
							'brand'   => 'yu',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Econet Wireless Kenya',
							'brand'   => 'Orange Kenya',
							'status'  => 'Operational',
						),
					),
				),
				'545' => array(
					'country'      => 'Kiribati',
					'country_code' => 'KI',
					'operators'    => array(
						'09' => array(
							'network' => 'Telecom Services Kiribati Ltd',
							'brand'   => 'Kiribati Frigate',
							'status'  => 'Operational',
						),
					),
				),
				'467' => array(
					'country'      => 'Korea',
					'country_code' => 'KR',
					'operators'    => array(
						'193' => array(
							'network' => 'Korea Posts and Telecommunications Corporation',
							'brand'   => 'SUN NET',
							'status'  => 'Operational',
						),
					),
				),
				'450' => array(
					'country'      => 'Korea',
					'country_code' => 'KR',
					'operators'    => array(
						'00' => array(
							'network' => 'KT',
							'brand'   => 'Dacom',
							'status'  => 'Inactive',
						),
						'02' => array(
							'network' => 'KT',
							'brand'   => 'KT',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Shinsegi Telecom Inc . ',
							'brand'   => 'Digital 017',
							'status'  => 'Inactive',
						),
						'04' => array(
							'network' => 'KT',
							'brand'   => 'KT',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'SK Telecom',
							'brand'   => 'SKT',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'LGT Telcom',
							'brand'   => 'LGT',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'KTF SHOW',
							'brand'   => 'KTF Show',
							'status'  => 'Operational',
						),
					),
				),
				'419' => array(
					'country'      => 'Kuwait',
					'country_code' => 'KW',
					'operators'    => array(
						'02' => array(
							'network' => 'Mobile Telecommunications Company ',
							'brand'   => 'Zain',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Wataniya Telecom ',
							'brand'   => 'Wataniya',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Kuwait Telecommunication Company',
							'brand'   => 'Viva',
							'status'  => 'Operational',
						),
					),
				),
				'437' => array(
					'country'      => 'Kyrqyzstan',
					'country_code' => '',
					'operators'    => array(
						'01' => array(
							'network' => 'Bitel GSM ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Aktel Ltd',
							'brand'   => 'Fonex',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'MEGACOM',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'NurTelecom LLC',
							'brand'   => 'O! ',
							'status'  => 'Operational',
						),
					),
				),
				'457' => array(
					'country'      => 'Lao People\'s Democratic Republic',
					'country_code' => 'LA',
					'operators'    => array(
						'01' => array(
							'network' => 'Lao Shinawatra Telecom',
							'brand'   => 'LaoTel',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Enterprise of Telecommunications Lao',
							'brand'   => 'ETL',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Star Telecom Co ., Ltd',
							'brand'   => 'Unitel',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'Millicom Lao Co Ltd',
							'brand'   => 'Tigo',
							'status'  => 'Operational',
						),
					),
				),
				'247' => array(
					'country'      => 'Latvia',
					'country_code' => 'LV',
					'operators'    => array(
						'01' => array(
							'network' => 'Latvian Mobile Phone( LMT )',
							'brand'   => 'LMT',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Tele2 ',
							'brand'   => 'Tele2',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Telekom Baltija( CDMA )',
							'brand'   => 'Triatel',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Bite Latvija',
							'brand'   => 'Bite',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Rigatta',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'07' => array(
							'network' => 'Master Telecom( MTS )',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'IZZI',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'Camel Mobile',
							'brand'   => 'Camel Mobile',
							'status'  => 'Operational',
						),
					),
				),
				'415' => array(
					'country'      => 'Lebanon',
					'country_code' => 'LB',
					'operators'    => array(
						'01' => array(
							'network' => 'MIC 1',
							'brand'   => 'Alfa',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'MIC 2',
							'brand'   => 'mtc touch',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Ogero Telecom',
							'brand'   => 'Ogero Mobile',
							'status'  => 'Operational',
						),
					),
				),
				'651' => array(
					'country'      => 'Lesotho',
					'country_code' => 'LS',
					'operators'    => array(
						'01' => array(
							'network' => 'Vodacom Lesotho( pty ) Ltd . ',
							'brand'   => 'Vodacom',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Econet Ezin - cel ',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'618' => array(
					'country'      => 'Liberia',
					'country_code' => 'LR',
					'operators'    => array(
						'01' => array(
							'network' => 'Lonestar Cell',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Libercell',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'03' => array(
							'network' => 'Cellcom',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Comium',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Cellcom',
							'brand'   => 'Cellcom',
							'status'  => 'Operational',
						),
						'20' => array(
							'network' => 'LIB Telco',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'606' => array(
					'country'      => 'Libyan Arab Jamahiriya',
					'country_code' => 'LY',
					'operators'    => array(
						'00' => array(
							'network' => 'Libyana Mobile Phone',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'01' => array(
							'network' => 'Madar',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'295' => array(
					'country'      => 'Liechtenstein',
					'country_code' => 'LI',
					'operators'    => array(
						'01' => array(
							'network' => 'Swisscom AG',
							'brand'   => 'Swisscom',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Orange AG',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Cubic Telecom AG',
							'brand'   => 'Cubic Telecom',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Mobilkom AG( FL1 )',
							'brand'   => 'FL1',
							'status'  => 'Operational',
						),
						'77' => array(
							'network' => 'Alpcom AG',
							'brand'   => 'Alpmobil',
							'status'  => 'Operational',
						),
					),
				),
				'246' => array(
					'country'      => 'Lithuania',
					'country_code' => 'LT',
					'operators'    => array(
						'01' => array(
							'network' => 'Omnitel ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Bite GSM',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Tele2 ',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'270' => array(
					'country'      => 'Luxembourg',
					'country_code' => 'LU',
					'operators'    => array(
						'01' => array(
							'network' => 'LuxGSM',
							'brand'   => 'LuxGSM',
							'status'  => 'Operational',
						),
						'77' => array(
							'network' => 'Tango ',
							'brand'   => 'Tango',
							'status'  => 'Operational',
						),
						'99' => array(
							'network' => 'Orange S . A . ',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
					),
				),
				'455' => array(
					'country'      => 'Macao',
					'country_code' => 'MO',
					'operators'    => array(
						'00' => array(
							'network' => 'SmarTone',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'01' => array(
							'network' => 'C . T . M Telemovel + ',
							'brand'   => 'CTM',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'China Telecom',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Hutchison Telecom ',
							'brand'   => '3',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'C . T . M Telemovel + ',
							'brand'   => 'CTM',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Hutchison Telecom',
							'brand'   => '3',
							'status'  => 'Operational',
						),
					),
				),
				'294' => array(
					'country'      => 'Macedonia',
					'country_code' => 'MK',
					'operators'    => array(
						'01' => array(
							'network' => 'T - Mobile ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Cosmofon ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'VIP Operator',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'646' => array(
					'country'      => 'Madagascar',
					'country_code' => 'MG',
					'operators'    => array(
						'01' => array(
							'network' => 'Celtel',
							'brand'   => 'Zain',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Orange Madagascar S . A',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Madamobil',
							'brand'   => 'Madamobil',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Telma Mobile S . A',
							'brand'   => 'Telma',
							'status'  => 'Operational',
						),
					),
				),
				'650' => array(
					'country'      => 'Malawi',
					'country_code' => 'MW',
					'operators'    => array(
						'01' => array(
							'network' => 'Telekom Network Ltd . ',
							'brand'   => 'TNM',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Bharti Airtel Limited',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
					),
				),
				'502' => array(
					'country'      => 'Malaysia',
					'country_code' => 'MY',
					'operators'    => array(
						'00' => array(
							'network' => 'Art900 ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'01' => array(
							'network' => 'Telekom Malaysia Bhd',
							'brand'   => 'TM CDMA',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'MTX Utara',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'12' => array(
							'network' => 'Maxis Communications Berhad',
							'brand'   => 'Maxis',
							'status'  => 'Operational',
						),
						'13' => array(
							'network' => 'TM Touch ',
							'brand'   => 'Celcom',
							'status'  => 'Operational',
						),
						'16' => array(
							'network' => 'DIGI ',
							'brand'   => 'DiGi',
							'status'  => 'Operational',
						),
						'17' => array(
							'network' => 'Maxis',
							'brand'   => 'Maxis',
							'status'  => 'Operational',
						),
						'18' => array(
							'network' => 'U Mobile Sdn Bhd',
							'brand'   => 'U Mobile',
							'status'  => 'Operational',
						),
						'19' => array(
							'network' => 'CelCom ',
							'brand'   => 'Celcom',
							'status'  => 'Operational',
						),
					),
				),
				'472' => array(
					'country'      => 'Maldives',
					'country_code' => 'MV',
					'operators'    => array(
						'01' => array(
							'network' => 'Dhivehi Raajjeyge Gulhun',
							'brand'   => 'Dhiraagu',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Wataniya Telecom Maldives',
							'brand'   => 'Wataniya',
							'status'  => 'Operational',
						),
					),
				),
				'610' => array(
					'country'      => 'Mali',
					'country_code' => 'ML',
					'operators'    => array(
						'01' => array(
							'network' => 'Malitel ',
							'brand'   => 'Malitel',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Orange Mali SA',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
					),
				),
				'278' => array(
					'country'      => 'Malta',
					'country_code' => 'MT',
					'operators'    => array(
						'01' => array(
							'network' => 'Vodafone Malta',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'21' => array(
							'network' => 'Mobisle Communications Limited',
							'brand'   => 'GO',
							'status'  => 'Operational',
						),
						'77' => array(
							'network' => 'Melita Plc',
							'brand'   => 'Melita',
							'status'  => 'Operational',
						),
					),
				),
				'340' => array(
					'country'      => 'Martinique',
					'country_code' => 'MQ',
					'operators'    => array(
						'01'  => array(
							'network' => 'Orange Caraïbe Mobiles ',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'02'  => array(
							'network' => 'Outremer Telecom ',
							'brand'   => 'Only',
							'status'  => 'Operational',
						),
						'03'  => array(
							'network' => 'Saint Martin et Saint Barthelemy Telcell Sarl ',
							'brand'   => 'Telcell',
							'status'  => 'Operational',
						),
						'08'  => array(
							'network' => 'Dauphin Telecom',
							'brand'   => 'Dauphin',
							'status'  => 'Operational',
						),
						'20'  => array(
							'network' => 'DIGICEL Antilles Française Guyane',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'993' => array(
							'network' => 'Orange Martinique',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'609' => array(
					'country'      => 'Mauritania',
					'country_code' => 'MR',
					'operators'    => array(
						'01' => array(
							'network' => 'Mattel S . A . ',
							'brand'   => 'Mattel',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Chinguitel S . A . ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Mauritel Mobiles ',
							'brand'   => 'Mauritel',
							'status'  => 'Operational',
						),
					),
				),
				'617' => array(
					'country'      => 'Mauritius',
					'country_code' => 'MU',
					'operators'    => array(
						'01' => array(
							'network' => 'Cellplus Mobile Communications Ltd . ',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Mahanagar Telephone( Mauritius ) Ltd . ',
							'brand'   => 'MTML',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Emtel Ltd',
							'brand'   => 'Emtel',
							'status'  => 'Operational',
						),
					),
				),
				'334' => array(
					'country'      => 'Mexico',
					'country_code' => 'MX',
					'operators'    => array(
						'01' => array(
							'network' => 'Nextel México',
							'brand'   => 'Nextel',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'América Móvil',
							'brand'   => 'Telcel',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Telefonica Moviles( Movistar )',
							'brand'   => 'Movistar',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Iusacell / Unefon',
							'brand'   => 'Iusacell / Unefon',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => '',
							'brand'   => 'Iusacell / Unefon',
							'status'  => 'Operational',
						),
						'20' => array(
							'network' => 'América Móvil',
							'brand'   => 'Telcel',
							'status'  => 'Inactive',
						),
						'50' => array(
							'network' => 'Iusacell / Unefon',
							'brand'   => 'Iusacell / Unefon',
							'status'  => 'Operational',
						),
					),
				),
				'550' => array(
					'country'      => 'Micronesia, Federated States Of',
					'country_code' => 'FM',
					'operators'    => array(
						'01' => array(
							'network' => 'FSM Telecom ',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'259' => array(
					'country'      => 'Moldova',
					'country_code' => 'MD',
					'operators'    => array(
						'01' => array(
							'network' => 'Orange Moldova GSM ',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Moldcell GSM ',
							'brand'   => 'Moldcell',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Moldtelecom',
							'brand'   => 'IDC',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Eventis Mobile GSM ',
							'brand'   => 'Evntis',
							'status'  => 'Inactive',
						),
						'05' => array(
							'network' => 'Moldtelecom',
							'brand'   => 'Unite',
							'status'  => 'Operational',
						),
					),
				),
				'212' => array(
					'country'      => 'Monaco',
					'country_code' => 'MC',
					'operators'    => array(
						'00' => array(
							'network' => '',
							'brand'   => 'Media Telecom',
							'status'  => 'Inactive',
						),
						'01' => array(
							'network' => 'Monaco Telecom',
							'brand'   => 'Office des Telephones',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => '',
							'brand'   => 'Morocco Wana',
							'status'  => 'Inactive',
						),
					),
				),
				'428' => array(
					'country'      => 'Mongolia',
					'country_code' => 'MN',
					'operators'    => array(
						'88' => array(
							'network' => 'Unitel',
							'brand'   => 'Unitel',
							'status'  => 'Operational',
						),
						'91' => array(
							'network' => 'Skytel LLC',
							'brand'   => 'Skytel',
							'status'  => 'Operational',
						),
						'98' => array(
							'network' => 'G - Mobile LLC',
							'brand'   => 'G . Mobile',
							'status'  => 'Operational',
						),
						'99' => array(
							'network' => 'Mobicom ',
							'brand'   => 'MobiCom',
							'status'  => 'Operational',
						),
					),
				),
				'297' => array(
					'country'      => 'Montenegro',
					'country_code' => 'ME',
					'operators'    => array(
						'01' => array(
							'network' => 'Telenor Montenegro',
							'brand'   => 'Telenor',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'T - Mobile Montenegro LLC',
							'brand'   => 'T - Mobile',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'MTEL CG',
							'brand'   => 'm:tel CG',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'T - Mobile Montenegro LLC',
							'brand'   => 'T - Mobile',
							'status'  => 'Operational',
						),
					),
				),
				'354' => array(
					'country'      => 'Montserrat',
					'country_code' => 'MS',
					'operators'    => array(
						'860' => array(
							'network' => 'Cable & Wireless West Indies( Montserrat )',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'604' => array(
					'country'      => 'Morocco',
					'country_code' => 'MA',
					'operators'    => array(
						'00' => array(
							'network' => 'Medi Telecom',
							'brand'   => 'Méditel',
							'status'  => 'Operational',
						),
						'01' => array(
							'network' => 'Ittissalat Al Maghrib( Maroc Telecom)',
							'brand'   => 'IAM',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'WANA - Groupe ONA',
							'brand'   => 'INWI',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'WANA - Groupe ON',
							'brand'   => 'INWI',
							'status'  => 'Operational',
						),
					),
				),
				'643' => array(
					'country'      => 'Mozambique',
					'country_code' => 'MZ',
					'operators'    => array(
						'01' => array(
							'network' => 'Mocambique Celular S . A . R . L',
							'brand'   => 'mCel',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Movitel',
							'brand'   => 'Movitel',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Vodacom Mozambique, S . A . R . L . ',
							'brand'   => 'Vodacom',
							'status'  => 'Operational',
						),
					),
				),
				'414' => array(
					'country'      => 'Myanmar',
					'country_code' => 'MM',
					'operators'    => array(
						'01' => array(
							'network' => 'Myanmar Post and Telecommunication',
							'brand'   => 'MPT',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Ooredoo Myanmar',
							'brand'   => 'Ooredoo',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Telenor Myanmar',
							'brand'   => 'Telenor',
							'status'  => 'Operational',
						),
					),
				),
				'649' => array(
					'country'      => 'Namibia',
					'country_code' => 'NA',
					'operators'    => array(
						'01' => array(
							'network' => 'Mobile Telecommunications Ltd . ',
							'brand'   => 'MTC',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Switch',
							'brand'   => 'Switch',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Orascom Telecom Holding',
							'brand'   => 'Leo',
							'status'  => 'Operational',
						),
					),
				),
				'429' => array(
					'country'      => 'Nepal',
					'country_code' => 'NP',
					'operators'    => array(
						'01' => array(
							'network' => 'Nepal Telecommunications ',
							'brand'   => 'Namaste / Nt Mobile',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Spice Nepal Private Ltd . ',
							'brand'   => 'Ncell',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Nepal Telecom',
							'brand'   => 'Sky / C - Phone',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Smart Telecom Pvt . Ltd',
							'brand'   => 'SmartCell',
							'status'  => 'Operational',
						),
					),
				),
				'204' => array(
					'country'      => 'Netherlands',
					'country_code' => 'NL',
					'operators'    => array(
						'01' => array(
							'network' => 'VastMobiel B . V . ',
							'brand'   => 'Scarlet Telecom B . V',
							'status'  => 'Inactive',
						),
						'02' => array(
							'network' => 'Tele2',
							'brand'   => 'Tele2',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Voiceworks B . V',
							'brand'   => 'Voiceworks B . V',
							'status'  => 'Inactive',
						),
						'04' => array(
							'network' => 'Vodafone',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => '',
							'brand'   => 'Elephant Talk Communications',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Mundio Mobile Ltd',
							'brand'   => 'Mundio Mobile',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Teleena Holding B . V . ',
							'brand'   => 'Teleena',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'KPN B . V',
							'brand'   => 'KPN',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'Lyca mobile Netherlands Ltd . ',
							'brand'   => 'Lyca mobile',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'KPN B . V',
							'brand'   => 'KPN',
							'status'  => 'Operational',
						),
						'12' => array(
							'network' => 'Telfort B . V',
							'brand'   => 'Telfort',
							'status'  => 'Operational',
						),
						'13' => array(
							'network' => 'Unica Installatietechniek B . V . ',
							'brand'   => 'Unica Installatietechniek B . V . ',
							'status'  => 'Inactive',
						),
						'14' => array(
							'network' => '6GMobile B . V',
							'brand'   => '6GMobile',
							'status'  => 'Operational',
						),
						'15' => array(
							'network' => 'Ziggo B . V',
							'brand'   => 'Ziggo B . V',
							'status'  => 'Operational',
						),
						'16' => array(
							'network' => 'T - Mobile',
							'brand'   => 'T - mobile',
							'status'  => 'Operational',
						),
						'17' => array(
							'network' => 'Intercity Mobile Communications B . V',
							'brand'   => 'Intercity Mobile Communications B . V',
							'status'  => 'Operational',
						),
						'18' => array(
							'network' => 'UPC Netherlands B . V',
							'brand'   => 'UPC',
							'status'  => 'Inactive',
						),
						'19' => array(
							'network' => 'Mixe Communication Solutions B . V',
							'brand'   => 'Mixe Communication Solutions B . V',
							'status'  => 'Inactive',
						),
						'20' => array(
							'network' => 'T - mobile',
							'brand'   => 'T - mobile',
							'status'  => 'Operational',
						),
						'21' => array(
							'network' => 'ProRail B . V',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'22' => array(
							'network' => '',
							'brand'   => 'Ministerie van Defensie',
							'status'  => 'Inactive',
						),
						'23' => array(
							'network' => 'ASPIDER Solutions B . V',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'24' => array(
							'network' => 'Private Mobility Netherlands B . V',
							'brand'   => 'Private Mobility Netherlands B . V',
							'status'  => 'Inactive',
						),
						'25' => array(
							'network' => 'CapX B . V . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'26' => array(
							'network' => 'SpeakUp B . V',
							'brand'   => 'SpeakUp B . V',
							'status'  => 'Inactive',
						),
						'27' => array(
							'network' => 'Brezz Nederland B . V',
							'brand'   => 'Brezz Nederland B . V',
							'status'  => 'Inactive',
						),
						'28' => array(
							'network' => 'Lancelot B . V',
							'brand'   => 'Lancelot B . V',
							'status'  => 'Inactive',
						),
						'67' => array(
							'network' => 'RadioAccess B . V . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'68' => array(
							'network' => '',
							'brand'   => 'Unify Group Holding B . V',
							'status'  => 'Inactive',
						),
						'69' => array(
							'network' => 'KPN B . V',
							'brand'   => 'KPN',
							'status'  => 'Operational',
						),
					),
				),
				'362' => array(
					'country'      => 'Netherlands Antilles',
					'country_code' => 'AN',
					'operators'    => array(
						'51'  => array(
							'network' => 'TelCell',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'69'  => array(
							'network' => 'Digicel',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'76'  => array(
							'network' => 'Antiliano Por N . V . ',
							'brand'   => 'Antiliano Por N . V . ',
							'status'  => 'Operational',
						),
						'91'  => array(
							'network' => 'UTS',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'94'  => array(
							'network' => 'Bayos',
							'brand'   => 'Bayos',
							'status'  => 'Operational',
						),
						'95'  => array(
							'network' => 'MIO',
							'brand'   => 'MIO',
							'status'  => 'Operational',
						),
						'630' => array(
							'network' => 'Cingular Wireless',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'951' => array(
							'network' => 'UTS Wireless Curacao',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'546' => array(
					'country'      => 'New Caledonia',
					'country_code' => 'NC',
					'operators'    => array(
						'01' => array(
							'network' => 'OPT Mobilis ',
							'brand'   => 'Mobilis',
							'status'  => 'Operational',
						),
					),
				),
				'530' => array(
					'country'      => 'New Zealand',
					'country_code' => 'NZ',
					'operators'    => array(
						'00' => array(
							'network' => 'Telecom New Zealand',
							'brand'   => 'Telecom',
							'status'  => 'Inactive',
						),
						'01' => array(
							'network' => 'Vodafone New Zealand',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Telecom New Zealand',
							'brand'   => 'Telecom',
							'status'  => 'Inactive',
						),
						'03' => array(
							'network' => 'Woosh Wireless New Zealand',
							'brand'   => 'Woosh',
							'status'  => 'Inactive',
						),
						'04' => array(
							'network' => 'TelstraClear New Zealand',
							'brand'   => 'TelstraClear',
							'status'  => 'Inactive',
						),
						'05' => array(
							'network' => 'Telecom New Zealand',
							'brand'   => 'XT Mobile( Telecom )',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Skinny',
							'brand'   => 'Skinny',
							'status'  => 'Operational',
						),
						'24' => array(
							'network' => '2degrees',
							'brand'   => '2degrees',
							'status'  => 'Operational',
						),
						'28' => array(
							'network' => 'Econet Wireless New Zealand GSM Mobile Network',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'55' => array(
							'network' => 'Cable & Wireless Guernsey Ltd',
							'brand'   => 'Telecom',
							'status'  => 'Inactive',
						),
					),
				),
				'710' => array(
					'country'      => 'Nicaraqua',
					'country_code' => '',
					'operators'    => array(
						'21'  => array(
							'network' => 'Empresa Nicaragüense de Telecomunicaciones, S . A . ( ENITEL ) ',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
						'30'  => array(
							'network' => 'Telefónica Móviles de Nicaragua S . A . ',
							'brand'   => 'movistar',
							'status'  => 'Operational',
						),
						'73'  => array(
							'network' => 'Servicios de Comunicaciones, S . A . ( SERCOM ) ',
							'brand'   => 'SERCOM',
							'status'  => 'Operational',
						),
						'730' => array(
							'network' => 'SERCOM S . A . ( Nicaragua )',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'614' => array(
					'country'      => 'Niger',
					'country_code' => 'NE',
					'operators'    => array(
						'01' => array(
							'network' => 'Sahel . Com ',
							'brand'   => 'SahelCom',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Celtel ',
							'brand'   => 'Zain',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Telecel Niger SA',
							'brand'   => 'Telecel',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Orange',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
					),
				),
				'621' => array(
					'country'      => 'Nigeria',
					'country_code' => 'NG',
					'operators'    => array(
						'20' => array(
							'network' => 'Bharti Airtel Ltd',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'25' => array(
							'network' => 'Vidafone Communications Ltd . ',
							'brand'   => 'Visafone',
							'status'  => 'Operational',
						),
						'30' => array(
							'network' => 'MTN Nigeria Communications ',
							'brand'   => 'MTN',
							'status'  => 'Operational',
						),
						'40' => array(
							'network' => 'Nigerian Mobile Telecommunications Ltd . ',
							'brand'   => 'M - Tel',
							'status'  => 'Operational',
						),
						'50' => array(
							'network' => 'Globacom Ltd',
							'brand'   => 'Glo',
							'status'  => 'Operational',
						),
						'60' => array(
							'network' => 'Etisalat Ltd . ',
							'brand'   => 'Etisalat',
							'status'  => 'Operational',
						),
					),
				),
				'242' => array(
					'country'      => 'Norway',
					'country_code' => 'NO',
					'operators'    => array(
						'01' => array(
							'network' => 'Telenor Norway AS',
							'brand'   => 'Telenor',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'TeliaSonera Norway AS',
							'brand'   => 'Netcom',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Teletopia Gruppen AS',
							'brand'   => 'MTU',
							'status'  => 'Inactive',
						),
						'04' => array(
							'network' => 'Tele2 Norway AS',
							'brand'   => 'Tele2',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Network Norway AS',
							'brand'   => 'Network Norway',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'ICE Norge AS',
							'brand'   => 'ICE',
							'status'  => 'Inactive',
						),
						'07' => array(
							'network' => 'Ventelo Norway AS',
							'brand'   => 'Ventelo',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'TDC Mobil AS',
							'brand'   => 'TDC',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'Com4 AS',
							'brand'   => 'Com4',
							'status'  => 'Inactive',
						),
						'11' => array(
							'network' => 'Systemnet AS',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'12' => array(
							'network' => 'Telenor Norway AS',
							'brand'   => 'Telenor',
							'status'  => 'Inactive',
						),
						'20' => array(
							'network' => '',
							'brand'   => 'Jernbaneverket',
							'status'  => 'Inactive',
						),
						'21' => array(
							'network' => '',
							'brand'   => 'Jernbaneverket',
							'status'  => 'Inactive',
						),
						'22' => array(
							'network' => 'Network Norway AS',
							'brand'   => 'Network Norway',
							'status'  => 'Inactive',
						),
						'23' => array(
							'network' => 'Lyca Mobile Ltd',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'422' => array(
					'country'      => 'Oman',
					'country_code' => 'OM',
					'operators'    => array(
						'02' => array(
							'network' => 'Oman Mobile Telecommunications Company',
							'brand'   => 'Oman Mobile',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Oman Qatari Telecommunications Company( Nawras ) ',
							'brand'   => 'Nawras',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Oman Telecommunications Company( Omantel ) ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'410' => array(
					'country'      => 'Pakistan',
					'country_code' => 'PK',
					'operators'    => array(
						'01' => array(
							'network' => 'Mobilink - PMCL',
							'brand'   => 'Mobilink',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Pakistan Telecommunication Mobile Ltd',
							'brand'   => 'Ufone',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'China Mobile',
							'brand'   => 'Zong',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Telenor Pakistan',
							'brand'   => 'Telenor',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'WaridTel',
							'brand'   => 'Warid',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'Instaphone( AMPS / CDMA )',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'552' => array(
					'country'      => 'Palau',
					'country_code' => 'PW',
					'operators'    => array(
						'01' => array(
							'network' => 'Palau National Communications Corp . ( a . k . a . PNCC ) ',
							'brand'   => 'PNCC',
							'status'  => 'Operational',
						),
						'80' => array(
							'network' => 'Palau Mobile Corporation',
							'brand'   => 'Palau Mobile',
							'status'  => 'Operational',
						),
					),
				),
				'714' => array(
					'country'      => 'Panama',
					'country_code' => 'PA',
					'operators'    => array(
						'01' => array(
							'network' => 'Cable & Wireless Panama S . A . ',
							'brand'   => 'Cable & Wireless',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Telefonica Moviles Panama S . A',
							'brand'   => 'movistar',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'América Móvil',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Group',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'20' => array(
							'network' => 'Movistar',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'537' => array(
					'country'      => 'Papua New Guinea',
					'country_code' => 'PG',
					'operators'    => array(
						'01' => array(
							'network' => 'Pacific Mobile Communications',
							'brand'   => 'B - Mobile',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Greencom ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'03' => array(
							'network' => 'Digicel PNG',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
					),
				),
				'744' => array(
					'country'      => 'Paraquay',
					'country_code' => '',
					'operators'    => array(
						'01' => array(
							'network' => 'Hola Paraguay S . A',
							'brand'   => 'VOX',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'AMX Paraguay S . A . ',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Compañia Privada de Comunicaciones S . A . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'04' => array(
							'network' => 'Telefonica Celular Del Paraguay S . A . ( Telecel )',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Núcleo S . A',
							'brand'   => 'Personal',
							'status'  => 'Operational',
						),
					),
				),
				'716' => array(
					'country'      => 'Peru',
					'country_code' => 'PE',
					'operators'    => array(
						'06' => array(
							'network' => 'Telefónica Móviles Perú',
							'brand'   => 'movistar',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Nextel',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'10' => array(
							'network' => 'América Móvil Perú',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
						'17' => array(
							'network' => 'NII Holdings',
							'brand'   => 'NEXTEL',
							'status'  => 'Operational',
						),
					),
				),
				'515' => array(
					'country'      => 'Philippines',
					'country_code' => 'PH',
					'operators'    => array(
						'01' => array(
							'network' => 'Globe Telecom via Innove Communications',
							'brand'   => 'Islacom',
							'status'  => 'Inactive',
						),
						'02' => array(
							'network' => 'Globe Telecom ',
							'brand'   => 'Globe',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'PLDT via Smart Communications',
							'brand'   => 'Smart',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Digital Telecommunications Philippines',
							'brand'   => 'Sun',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'PLDT via ACeS Philippines',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'18' => array(
							'network' => 'PLDT via Smarts Connectivity Unlimited Resources Enterprise',
							'brand'   => 'Cure',
							'status'  => 'Operational',
						),
						'88' => array(
							'network' => 'Nextel',
							'brand'   => 'Nextel',
							'status'  => 'Operational',
						),
					),
				),
				'260' => array(
					'country'      => 'Poland',
					'country_code' => 'PL',
					'operators'    => array(
						'01' => array(
							'network' => 'Plus GSM( Polkomtel S . A .) ',
							'brand'   => 'Plus( Polkomtel )',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'ERA GSM( Polska Telefonia Cyfrowa Sp . Z . o . o .) ',
							'brand'   => 'T - Mobile',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Orange',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Netia S . A',
							'brand'   => 'Tele 2 ( Netia )',
							'status'  => 'Inactive',
						),
						'05' => array(
							'network' => 'Polska Telefonia Komórkowa Centertel Sp . z o . o . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'06' => array(
							'network' => 'Play( P4 )',
							'brand'   => 'Play( P4 )',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Netia S . A',
							'brand'   => 'Netia( Using P4 Nw)',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'E - Telko Sp . z o . o . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'09' => array(
							'network' => 'Telekomunikacja Kolejowa( GSM - R ) ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'10' => array(
							'network' => 'Sferia S . A . ',
							'brand'   => 'Sferia( Using T - mobile)',
							'status'  => 'Operational',
						),
						'12' => array(
							'network' => 'Cyfrowy Polsat S . A . ',
							'brand'   => 'Cyfrowy Polsat',
							'status'  => 'Operational',
						),
						'14' => array(
							'network' => 'Sferia S . A',
							'brand'   => 'Sferia( Using T - mobile)',
							'status'  => 'Operational',
						),
						'15' => array(
							'network' => 'CenterNet S . A . ',
							'brand'   => 'CenterNet( UMTS Data only)',
							'status'  => 'Operational',
						),
						'16' => array(
							'network' => 'Mobyland Sp . z o . o . ',
							'brand'   => 'Mobyland( UMTS )',
							'status'  => 'Operational',
						),
						'17' => array(
							'network' => 'Aero 2 Sp . z o . o . ',
							'brand'   => 'Aero2( UMTS )',
							'status'  => 'Operational',
						),
					),
				),
				'268' => array(
					'country'      => 'Portugal',
					'country_code' => 'PT',
					'operators'    => array(
						'01' => array(
							'network' => 'Vodafone Portugal',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Sonaecom  Serviços de Comunicações, S . A . ',
							'brand'   => 'Optimus',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Oniway - Inforcomunicaçôes, S . A . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'06' => array(
							'network' => 'Telecomunicações Móveis Nacionais',
							'brand'   => 'TMN',
							'status'  => 'Operational',
						),
						'21' => array(
							'network' => 'Zapp Portugal',
							'brand'   => 'Zapp',
							'status'  => 'Operational',
						),
					),
				),
				'330' => array(
					'country'      => 'Puerto Rico',
					'country_code' => 'PR',
					'operators'    => array(
						'10' => array(
							'network' => 'Cingular Wireless',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'11' => array(
							'network' => 'Puerto Rico Telephone Company',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
					),
				),
				'427' => array(
					'country'      => 'Qatar',
					'country_code' => 'QA',
					'operators'    => array(
						'01' => array(
							'network' => 'Qtel',
							'brand'   => 'Qtel',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Vodaphone Quatar',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
					),
				),
				'629' => array(
					'country'      => 'Congo, Democratic Republic',
					'country_code' => 'CD',
					'operators'    => array(
						'01' => array(
							'network' => 'Celtel Congo',
							'brand'   => 'Zain',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'MTN CONGO S . A',
							'brand'   => 'Libertis Telecom',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Libertis Telecom',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'647' => array(
					'country'      => 'Reunion',
					'country_code' => 'RE',
					'operators'    => array(
						'00'  => array(
							'network' => 'Orange',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'02'  => array(
							'network' => 'Outremer Telecom ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'10'  => array(
							'network' => 'SFR Reunion',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'995' => array(
							'network' => 'SRR Mayotte',
							'brand'   => 'SRR Mayotte',
							'status'  => 'Operational',
						),
						'997' => array(
							'network' => 'Orange Mayotte',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
					),
				),
				'226' => array(
					'country'      => 'Romania',
					'country_code' => 'RO',
					'operators'    => array(
						'01' => array(
							'network' => 'Vodafone Romania SA ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Romtelecom',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Cosmote( Zapp )',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Cosmote( Zapp )',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Digi mobil',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Cosmote',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Orange Romania ',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'250' => array(
					'country'      => 'Russian Federation',
					'country_code' => 'RU',
					'operators'    => array(
						'01' => array(
							'network' => 'Mobile TeleSystems ',
							'brand'   => 'MTS ',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'MegaFon OJSC ',
							'brand'   => 'MegaFon ',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Nizhegorodskaya Cellular Communications ',
							'brand'   => 'NCC ',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Sibchallenge ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'05' => array(
							'network' => 'Yeniseytelecom ',
							'brand'   => 'ETK ',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'CJSC Saratov System of Cellular Communications ',
							'brand'   => 'Skylink',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Zao SMARTS ',
							'brand'   => 'SMARTS ',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'Khabarovsky Cellular Phone ',
							'brand'   => 'Skylink ',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Don Telecom ',
							'brand'   => 'DTC',
							'status'  => 'Inactive',
						),
						'11' => array(
							'network' => 'Orensot ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'12' => array(
							'network' => 'Baykal Westcom / Akos',
							'brand'   => 'Baykal',
							'status'  => 'Operational',
						),
						'13' => array(
							'network' => 'Kuban GSM ',
							'brand'   => 'KUGSM',
							'status'  => 'Inactive',
						),
						'15' => array(
							'network' => 'SMARTS Ufa, SMARTS Uljanovsk ',
							'brand'   => 'SMARTS ',
							'status'  => 'Operational',
						),
						'16' => array(
							'network' => 'New Telephone Company ',
							'brand'   => 'NTC ',
							'status'  => 'Operational',
						),
						'17' => array(
							'network' => 'JSC Uralsvyazinform ',
							'brand'   => 'Utel ',
							'status'  => 'Operational',
						),
						'19' => array(
							'network' => 'Indigo',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'20' => array(
							'network' => 'Tele2 ',
							'brand'   => 'Tele2 ',
							'status'  => 'Operational',
						),
						'23' => array(
							'network' => 'Mobicom - Novisibirsk',
							'brand'   => 'Mobicom',
							'status'  => 'Inactive',
						),
						'28' => array(
							'network' => 'Beeline',
							'brand'   => 'Beeline',
							'status'  => 'Inactive',
						),
						'32' => array(
							'network' => 'K - Telecom',
							'brand'   => 'Win Mobile',
							'status'  => 'Operational',
						),
						'35' => array(
							'network' => 'MOTIV ',
							'brand'   => 'MOTIV ',
							'status'  => 'Operational',
						),
						'37' => array(
							'network' => 'ZAO Kodotel',
							'brand'   => 'ZAO Kodotel',
							'status'  => 'Operational',
						),
						'38' => array(
							'network' => 'Tambov GSM ',
							'brand'   => 'Tambov GSM ',
							'status'  => 'Operational',
						),
						'39' => array(
							'network' => 'Uralsvyazinform ',
							'brand'   => 'Utel ',
							'status'  => 'Operational',
						),
						'44' => array(
							'network' => 'Stuvtelesot ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'50' => array(
							'network' => 'Mobile TeleSystems',
							'brand'   => 'MTS',
							'status'  => 'Operational',
						),
						'92' => array(
							'network' => 'MTS - Primtelefon',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'93' => array(
							'network' => 'Telecom XXI ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'99' => array(
							'network' => 'OJSC VimpelCom ',
							'brand'   => 'Beeline ',
							'status'  => 'Operational',
						),
					),
				),
				'635' => array(
					'country'      => 'Rwanda',
					'country_code' => 'RW',
					'operators'    => array(
						'10' => array(
							'network' => 'MTN Rwandacell SARL',
							'brand'   => 'MTN',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => '',
							'brand'   => 'Rwandatel( CDMA )',
							'status'  => 'Inactive',
						),
						'12' => array(
							'network' => '',
							'brand'   => 'Rwandatel( GSM )',
							'status'  => 'Operational',
						),
						'13' => array(
							'network' => 'TIGO RWANDA S . A',
							'brand'   => 'Tigo',
							'status'  => 'Operational',
						),
					),
				),
				'356' => array(
					'country'      => 'Saint Kitts and Nevis',
					'country_code' => 'KN',
					'operators'    => array(
						'50'  => array(
							'network' => 'Digicel',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'70'  => array(
							'network' => 'UTS',
							'brand'   => 'Chippie',
							'status'  => 'Operational',
						),
						'110' => array(
							'network' => 'Cable & Wireless',
							'brand'   => 'LIME',
							'status'  => 'Operational',
						),
					),
				),
				'358' => array(
					'country'      => 'Saint Lucia',
					'country_code' => 'LC',
					'operators'    => array(
						'30'  => array(
							'network' => 'Cingular Wireless',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'50'  => array(
							'network' => 'Digicel( St Lucia) Limited',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'110' => array(
							'network' => 'Cable & Wireless',
							'brand'   => 'Lime( Cable & Wireless )',
							'status'  => 'Operational',
						),
					),
				),
				'360' => array(
					'country'      => 'Saint Vincent and the Grenadines',
					'country_code' => '',
					'operators'    => array(
						'10'  => array(
							'network' => 'Cingular Wireless',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'70'  => array(
							'network' => 'Digicel( St . Vincent and Grenadines ) Limited',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'100' => array(
							'network' => 'Cingular Wireless',
							'brand'   => 'Cingular Wireless',
							'status'  => 'Operational',
						),
						'110' => array(
							'network' => 'Cable & Wireless ',
							'brand'   => 'Lime( Cable & Wireless )',
							'status'  => 'Operational',
						),
					),
				),
				'549' => array(
					'country'      => 'Samoa',
					'country_code' => 'WS',
					'operators'    => array(
						'01' => array(
							'network' => 'Digicel Pacific Ltd . ',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'27' => array(
							'network' => 'Bluesky Samoa Ltd',
							'brand'   => 'Bluesky',
							'status'  => 'Operational',
						),
					),
				),
				'626' => array(
					'country'      => 'Sao Tome and Principe',
					'country_code' => 'ST',
					'operators'    => array(
						'01' => array(
							'network' => 'Companhia Santomese de Telecomunicaçôes ',
							'brand'   => 'CSTmovel',
							'status'  => 'Operational',
						),
					),
				),
				'420' => array(
					'country'      => 'Saudi Arabia',
					'country_code' => 'SA',
					'operators'    => array(
						'01' => array(
							'network' => 'Saudi Telecom Company',
							'brand'   => 'Al Jawal',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Etihad Etisalat Company',
							'brand'   => 'Mobily',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Zain SA',
							'brand'   => 'Zain SA',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'EAE',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'608' => array(
					'country'      => 'Senegal',
					'country_code' => 'SN',
					'operators'    => array(
						'01' => array(
							'network' => 'Sonatel ',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Millicom',
							'brand'   => 'Tigo',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Sudatel',
							'brand'   => 'Expresso',
							'status'  => 'Operational',
						),
					),
				),
				'220' => array(
					'country'      => 'Serbia',
					'country_code' => 'RS',
					'operators'    => array(
						'01' => array(
							'network' => 'Telenor Serbia',
							'brand'   => 'Telenor',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Telenor Montenegro',
							'brand'   => 'Telenor',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Telekom Srbija a . d . ',
							'brand'   => 'mt:s',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Monet',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'05' => array(
							'network' => 'Vip mobile d . o . o . ',
							'brand'   => 'VIP',
							'status'  => 'Operational',
						),
					),
				),
				'633' => array(
					'country'      => 'Seychelles',
					'country_code' => 'SC',
					'operators'    => array(
						'01' => array(
							'network' => 'Cable & Wireless( Seychelles ) Ltd . ',
							'brand'   => 'Cable & Wireless',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Mediatech International Ltd . ',
							'brand'   => 'Mediatech International',
							'status'  => 'Inactive',
						),
						'10' => array(
							'network' => 'Telecom( Seychelles ) Ltd . ',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
					),
				),
				'619' => array(
					'country'      => 'Sierra Leone',
					'country_code' => 'SL',
					'operators'    => array(
						'01'  => array(
							'network' => 'Bharti Airtel Ltd',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'02'  => array(
							'network' => 'Millicom( SL ) Ltd',
							'brand'   => 'Tigo',
							'status'  => 'Operational',
						),
						'03'  => array(
							'network' => 'Lintel Sierra Leone Ltd',
							'brand'   => 'Africell',
							'status'  => 'Operational',
						),
						'04'  => array(
							'network' => 'Comium( Sierra Leone) Ltd . ',
							'brand'   => 'Comium',
							'status'  => 'Operational',
						),
						'05'  => array(
							'network' => 'Lintel( Sierra Leone) Ltd . ',
							'brand'   => 'Africell',
							'status'  => 'Operational',
						),
						'25'  => array(
							'network' => 'Mobitel ',
							'brand'   => 'Mobitel',
							'status'  => 'Operational',
						),
						'40'  => array(
							'network' => 'Datatel( SL ) Ltd GSM ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'50'  => array(
							'network' => 'Dtatel( SL ) Ltd CDMA ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'999' => array(
							'network' => 'Sierratel',
							'brand'   => 'LeoneCel',
							'status'  => 'Inactive',
						),
					),
				),
				'525' => array(
					'country'      => 'Singapore',
					'country_code' => 'SG',
					'operators'    => array(
						'01' => array(
							'network' => 'Singapore Telecom',
							'brand'   => 'SingTel',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Singapore Telecom',
							'brand'   => 'SingTel - G18',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'MobileOne Asia',
							'brand'   => 'M1',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'StarHub Mobile',
							'brand'   => 'StarHub',
							'status'  => 'Operational',
						),
						'12' => array(
							'network' => 'Digital Trunked Radio Network ',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'231' => array(
					'country'      => 'Slovakia',
					'country_code' => 'SK',
					'operators'    => array(
						'01' => array(
							'network' => 'Orange Slovensko',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'T - Mobile Slovensko',
							'brand'   => 'T - Mobile',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Unient Communications',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'04' => array(
							'network' => 'T - Mobile Slovensko',
							'brand'   => 'T - Mobile',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Orange Slovensko',
							'brand'   => 'Orange',
							'status'  => 'Inactive',
						),
						'06' => array(
							'network' => 'Telefónica O2',
							'brand'   => 'O2',
							'status'  => 'Operational',
						),
						'15' => array(
							'network' => 'Orange Slovensko',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'99' => array(
							'network' => 'eleznice Slovenskej Republiky',
							'brand'   => 'SR',
							'status'  => 'Inactive',
						),
					),
				),
				'293' => array(
					'country'      => 'Slovenia',
					'country_code' => 'SI',
					'operators'    => array(
						'40' => array(
							'network' => 'SI Mobil D . D',
							'brand'   => 'Si . mobil',
							'status'  => 'Operational',
						),
						'41' => array(
							'network' => 'Ipkonet',
							'brand'   => 'Mobitel',
							'status'  => 'Operational',
						),
						'64' => array(
							'network' => 'T - 2 d . o . o . ',
							'brand'   => 'T - 2',
							'status'  => 'Operational',
						),
						'70' => array(
							'network' => 'Tusmobil d . o . o . ',
							'brand'   => 'Tumobil',
							'status'  => 'Operational',
						),
					),
				),
				'540' => array(
					'country'      => 'Solomon Islands',
					'country_code' => 'SB',
					'operators'    => array(
						'01' => array(
							'network' => 'Solomon Telekom Co Ltd',
							'brand'   => 'BREEZE',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Breeze',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'637' => array(
					'country'      => 'Somalia',
					'country_code' => 'SO',
					'operators'    => array(
						'01' => array(
							'network' => 'Telesom',
							'brand'   => 'Telesom',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Somafone FZLLC',
							'brand'   => 'Somafone',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'NationLink Telecom',
							'brand'   => 'Nationlink',
							'status'  => 'Operational',
						),
						'19' => array(
							'network' => 'Hormuud Telecom',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'25' => array(
							'network' => 'Hormuud Telecom Somalia Inc',
							'brand'   => 'Hormuud',
							'status'  => 'Operational',
						),
						'30' => array(
							'network' => 'Golis Telecom Somalia',
							'brand'   => 'Golis',
							'status'  => 'Operational',
						),
						'62' => array(
							'network' => 'Telecom Mobile',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'65' => array(
							'network' => 'Telecom Mobile',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'82' => array(
							'network' => 'Telcom Somalia',
							'brand'   => 'Telcom',
							'status'  => 'Operational',
						),
					),
				),
				'655' => array(
					'country'      => 'South Africa',
					'country_code' => 'ZA',
					'operators'    => array(
						'01' => array(
							'network' => 'Vodacom( Pty ) Ltd . ',
							'brand'   => 'Vodacom',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Telkom',
							'brand'   => 'Telkom Mobile / 8.ta',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Sasol( PTY ) LTD',
							'brand'   => 'Sasol( PTY ) LTD',
							'status'  => 'Inactive',
						),
						'06' => array(
							'network' => 'Sentech( Pty ) Ltd . ',
							'brand'   => 'Sentech',
							'status'  => 'Inactive',
						),
						'07' => array(
							'network' => 'Cell C( Pty ) Ltd . ',
							'brand'   => 'Cell C & Virgin',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'Mobile Telephone Networks ',
							'brand'   => 'MTN',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'SAPS Gauteng ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'13' => array(
							'network' => 'Neotel',
							'brand'   => 'Neotel',
							'status'  => 'Inactive',
						),
						'19' => array(
							'network' => 'Wireless Business Solutions',
							'brand'   => 'iBurst',
							'status'  => 'Inactive',
						),
						'21' => array(
							'network' => 'Cape Town Metropolitan Council ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'25' => array(
							'network' => 'Wirels Connect',
							'brand'   => 'Wirels Connect',
							'status'  => 'Inactive',
						),
						'30' => array(
							'network' => 'Bokamoso Consortium ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'31' => array(
							'network' => 'Karabo Telecoms( Pty ) Ltd . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'32' => array(
							'network' => 'Ilizwi Telecommunications ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'33' => array(
							'network' => 'Thinta Thinta Telecommunications ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'34' => array(
							'network' => 'Bokone Telecoms ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'35' => array(
							'network' => 'Kingdom Communications ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'36' => array(
							'network' => 'Amatole Telecommunication Services ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'41' => array(
							'network' => 'South African Police Service',
							'brand'   => 'South African Police Service',
							'status'  => 'Inactive',
						),
					),
				),
				'659' => array(
					'country'      => 'South Sudan',
					'country_code' => 'SS',
					'operators'    => array(
						'01' => array(
							'network' => 'Vivacell',
							'brand'   => 'Now',
							'status'  => 'Operational',
						),
					),
				),
				'214' => array(
					'country'      => 'Spain',
					'country_code' => 'ES',
					'operators'    => array(
						'01' => array(
							'network' => 'Vodafone Spain',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'France Telecom España SA',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Xfera Moviles SA',
							'brand'   => 'Yoigo',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Telefónica Móviles España',
							'brand'   => 'TME',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Vodafone Spain',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Telefónica Móviles España',
							'brand'   => 'movistar',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'Euskaltel',
							'brand'   => 'Euskaltel',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'France Telecom España SA',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'15' => array(
							'network' => 'BT Group España Compañia de Servicios Globales de Telecomunicaciones S . A . U . ',
							'brand'   => 'BT',
							'status'  => 'Operational',
						),
						'16' => array(
							'network' => 'Telecable de Asturias S . A . U . ',
							'brand'   => 'TeleCable',
							'status'  => 'Operational',
						),
						'17' => array(
							'network' => 'R Cable y Telecomunicaciones Galicia S . A . ',
							'brand'   => 'Móbil R',
							'status'  => 'Operational',
						),
						'18' => array(
							'network' => 'Cableuropa S . A . U . ',
							'brand'   => 'ONO',
							'status'  => 'Operational',
						),
						'19' => array(
							'network' => 'E - PLUS Moviles Virtuales España S . L . U . ( KPN )',
							'brand'   => 'Simyo',
							'status'  => 'Operational',
						),
						'20' => array(
							'network' => 'Fonyou Telecom S . L . ',
							'brand'   => 'Fonyou',
							'status'  => 'Operational',
						),
						'21' => array(
							'network' => 'Jazz Telecom S . A . U . ',
							'brand'   => 'Jazztel',
							'status'  => 'Operational',
						),
						'22' => array(
							'network' => 'Best Spain Telecom',
							'brand'   => 'DigiMobil',
							'status'  => 'Operational',
						),
						'23' => array(
							'network' => 'Barablu Movil Espana',
							'brand'   => 'Barablu',
							'status'  => 'Operational',
						),
						'24' => array(
							'network' => 'Eroski Móvil España',
							'brand'   => 'Eroski',
							'status'  => 'Operational',
						),
						'25' => array(
							'network' => 'LycaMobile S . L . ',
							'brand'   => 'LycaMobile',
							'status'  => 'Operational',
						),
						'33' => array(
							'network' => 'France Telecom España SA',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
					),
				),
				'413' => array(
					'country'      => 'Sri Lanka',
					'country_code' => 'LK',
					'operators'    => array(
						'01' => array(
							'network' => 'Sri Lanka Telecom Mobitel',
							'brand'   => 'Mobitel',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Dialog Telekom',
							'brand'   => 'Dialog',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Emirates Telecommunication Corporation',
							'brand'   => 'Etisalat',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Bharti Airtel',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'Hutchison Telecommunications Lanka( Pvt ) Limited',
							'brand'   => 'Hutch',
							'status'  => 'Operational',
						),
					),
				),
				'658' => array(
					'country'      => 'Saint Helena',
					'country_code' => 'SH',
					'operators'    => array(
						'00' => array(
							'network' => '',
							'brand'   => 'Cable & Wireless Plc',
							'status'  => 'Inactive',
						),
					),
				),
				'308' => array(
					'country'      => 'Saint Pierre And Miquelon',
					'country_code' => 'PM',
					'operators'    => array(
						'01' => array(
							'network' => 'St . Pierre - et - Miquelon Télécom ',
							'brand'   => 'Ameris',
							'status'  => 'Operational',
						),
					),
				),
				'634' => array(
					'country'      => 'Sudan',
					'country_code' => 'SD',
					'operators'    => array(
						'01' => array(
							'network' => 'Zain Group - Sudan',
							'brand'   => 'Zain SD',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'MTN Sudan',
							'brand'   => 'MTN',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Wawat Securities',
							'brand'   => 'Vivacell( NOW )',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Sudatel Group',
							'brand'   => 'Sudani One',
							'status'  => 'Operational',
						),
					),
				),
				'746' => array(
					'country'      => 'Suriname',
					'country_code' => 'SR',
					'operators'    => array(
						'01' => array(
							'network' => 'Telecommunications Company Suriname',
							'brand'   => 'Telesur',
							'status'  => 'Inactive',
						),
						'02' => array(
							'network' => 'Telecommunications Company Suriname',
							'brand'   => 'Telesur',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Digicel Group Limited',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Intelsur N . V . / UTS N . V . ',
							'brand'   => 'Uniqa',
							'status'  => 'Operational',
						),
					),
				),
				'653' => array(
					'country'      => 'Swaziland',
					'country_code' => 'SZ',
					'operators'    => array(
						'10' => array(
							'network' => 'Swazi MTN ',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'240' => array(
					'country'      => 'Sweden',
					'country_code' => 'SE',
					'operators'    => array(
						'00'  => array(
							'network' => 'Direct2 Internet',
							'brand'   => 'Direct2 Internet',
							'status'  => 'Operational',
						),
						'01'  => array(
							'network' => 'Telia Sonera AB',
							'brand'   => 'Telia',
							'status'  => 'Operational',
						),
						'02'  => array(
							'network' => 'H3G Access AB',
							'brand'   => '3',
							'status'  => 'Operational',
						),
						'03'  => array(
							'network' => 'Netett Sverige AB',
							'brand'   => 'Netett Sverige AB',
							'status'  => 'Operational',
						),
						'05'  => array(
							'network' => 'Svenska UMTS - Nät',
							'brand'   => 'Sweden 3G( Telia / Tele2 )',
							'status'  => 'Operational',
						),
						'06'  => array(
							'network' => 'Telenor Sweden AB',
							'brand'   => 'Telenor',
							'status'  => 'Operational',
						),
						'07'  => array(
							'network' => 'Tele2 Sweden AB',
							'brand'   => 'Tele2',
							'status'  => 'Operational',
						),
						'08'  => array(
							'network' => 'Telenor Sweden AB',
							'brand'   => 'Telenor',
							'status'  => 'Operational',
						),
						'09'  => array(
							'network' => 'Djuice Mobile Sweden',
							'brand'   => 'Djuice Mobile Sweden',
							'status'  => 'Operational',
						),
						'10'  => array(
							'network' => 'Spring Mobil AB',
							'brand'   => 'Spring',
							'status'  => 'Operational',
						),
						'11'  => array(
							'network' => '',
							'brand'   => 'Lindholmen Science Park',
							'status'  => 'Operational',
						),
						'12'  => array(
							'network' => 'Lycamobile AB',
							'brand'   => 'Lycamobile',
							'status'  => 'Operational',
						),
						'13'  => array(
							'network' => 'Ventelo Sweden AB',
							'brand'   => 'Ventelo',
							'status'  => 'Operational',
						),
						'14'  => array(
							'network' => 'TDC Mobil AS',
							'brand'   => 'TDC',
							'status'  => 'Operational',
						),
						'15'  => array(
							'network' => 'Wireless Maingate',
							'brand'   => 'Wireless Maingate',
							'status'  => 'Inactive',
						),
						'16'  => array(
							'network' => '42 Telecom AB',
							'brand'   => '42 Telecom AB',
							'status'  => 'Operational',
						),
						'17'  => array(
							'network' => 'Götalandsnätet AB',
							'brand'   => 'Götalandsnätet AB',
							'status'  => 'Operational',
						),
						'18'  => array(
							'network' => 'Generic Mobile Systems Sweden AB',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'19'  => array(
							'network' => 'Mudio Mobile Sweden',
							'brand'   => 'Mudio Mobile',
							'status'  => 'Operational',
						),
						'20'  => array(
							'network' => 'Imez AB',
							'brand'   => 'Imez AB',
							'status'  => 'Inactive',
						),
						'22'  => array(
							'network' => '',
							'brand'   => 'EuTel',
							'status'  => 'Operational',
						),
						'23'  => array(
							'network' => '',
							'brand'   => 'Infobip Ltd',
							'status'  => 'Inactive',
						),
						'25'  => array(
							'network' => '',
							'brand'   => 'Digitel Mobile Srl',
							'status'  => 'Operational',
						),
						'26'  => array(
							'network' => 'Beepsend',
							'brand'   => 'Beepsend',
							'status'  => 'Inactive',
						),
						'27'  => array(
							'network' => 'MyIndian AB',
							'brand'   => 'MyIndian AB',
							'status'  => 'Operational',
						),
						'28'  => array(
							'network' => '',
							'brand'   => 'CoolTEL Aps',
							'status'  => 'Inactive',
						),
						'29'  => array(
							'network' => '',
							'brand'   => 'Mercury International Carrier Services',
							'status'  => 'Operational',
						),
						'30'  => array(
							'network' => '',
							'brand'   => 'NextGen Mobile Ltd',
							'status'  => 'Operational',
						),
						'32'  => array(
							'network' => '',
							'brand'   => 'CompaTel Ltd . ',
							'status'  => 'Inactive',
						),
						'34'  => array(
							'network' => 'Tigo LTD',
							'brand'   => 'Tigo LTD',
							'status'  => 'Operational',
						),
						'36'  => array(
							'network' => 'Interactive digital media GmbH',
							'brand'   => 'IDM',
							'status'  => 'Operational',
						),
						'41'  => array(
							'network' => '',
							'brand'   => 'Shyam Telecom UK Ltd',
							'status'  => 'Operational',
						),
						'89'  => array(
							'network' => 'ACN Communications Sweden AB',
							'brand'   => 'ACN Communications Sweden AB',
							'status'  => 'Operational',
						),
						'500' => array(
							'network' => 'Unknown',
							'brand'   => 'Unknown',
							'status'  => 'Operational',
						),
						'503' => array(
							'network' => 'Unknown',
							'brand'   => 'Unknown',
							'status'  => 'Operational',
						),
					),
				),
				'228' => array(
					'country'      => 'Switzerland',
					'country_code' => 'CH',
					'operators'    => array(
						'01' => array(
							'network' => 'Swisscom Ltd',
							'brand'   => 'Swisscom',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Sunrise Communications AG',
							'brand'   => 'Sunrise',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Orange Communications SA',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Togewanet AG( Comfone )',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'SBB AG ',
							'brand'   => 'SBB AG',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'IN & Phone SA ',
							'brand'   => 'IN & Phone',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'Tele2 Telecommunications AG ',
							'brand'   => 'Tele2',
							'status'  => 'Operational',
						),
						'09' => array(
							'network' => 'Comfone AG',
							'brand'   => 'Comfone',
							'status'  => 'Inactive',
						),
						'12' => array(
							'network' => 'Sunrise',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'50' => array(
							'network' => '3G Mobile AG',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'51' => array(
							'network' => 'Bebbicell AG ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'52' => array(
							'network' => 'Barablu Mobile AG',
							'brand'   => 'Barablu',
							'status'  => 'Inactive',
						),
						'53' => array(
							'network' => 'UPC Cablecom GmbH',
							'brand'   => 'UPC',
							'status'  => 'Inactive',
						),
						'54' => array(
							'network' => 'Lyca Mobile AG',
							'brand'   => 'Lyca Mobile',
							'status'  => 'Inactive',
						),
					),
				),
				'417' => array(
					'country'      => 'Syrian Arab Republic',
					'country_code' => 'SY',
					'operators'    => array(
						'01' => array(
							'network' => 'Syriatel Mobile Telecom',
							'brand'   => 'Syriatel',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'MTN Syria',
							'brand'   => 'MTN',
							'status'  => 'Operational',
						),
					),
				),
				'466' => array(
					'country'      => 'Taiwan',
					'country_code' => 'TW',
					'operators'    => array(
						'01' => array(
							'network' => 'Far EasTone Telecommunications Co Ltd',
							'brand'   => 'FarEasTone',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Asia Pacific Telecom',
							'brand'   => 'APTG',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Tuntex Telecom',
							'brand'   => 'Tuntex',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'LDTA / Chungwa Telecom',
							'brand'   => 'Chunghwa LDM',
							'status'  => 'Operational',
						),
						'68' => array(
							'network' => 'ACeS Taiwan Telecommunications Co Ltd',
							'brand'   => 'ACeS',
							'status'  => 'Inactive',
						),
						'88' => array(
							'network' => 'KG Telecom',
							'brand'   => 'KG Telecom',
							'status'  => 'Operational',
						),
						'89' => array(
							'network' => 'VIBO Telecom',
							'brand'   => 'VIBO',
							'status'  => 'Operational',
						),
						'92' => array(
							'network' => 'Chunghwa Telecom LDM',
							'brand'   => 'Chungwa',
							'status'  => 'Operational',
						),
						'93' => array(
							'network' => 'Mobitai Communications',
							'brand'   => 'MobiTai',
							'status'  => 'Operational',
						),
						'97' => array(
							'network' => 'Taiwan Cellular Corporation',
							'brand'   => 'Taiwan Mobile',
							'status'  => 'Operational',
						),
						'99' => array(
							'network' => 'TransAsia Telecoms',
							'brand'   => 'TransAsia',
							'status'  => 'Operational',
						),
					),
				),
				'436' => array(
					'country'      => 'Tajikistan',
					'country_code' => 'TJ',
					'operators'    => array(
						'01' => array(
							'network' => 'JV Somoncom',
							'brand'   => 'Tcell',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Indigo Tajikistan',
							'brand'   => 'Tcell',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'TT Mobile',
							'brand'   => 'MLT',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Babilon - Mobile',
							'brand'   => 'Babilon - M',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Vimpelcom',
							'brand'   => 'Beeline',
							'status'  => 'Operational',
						),
						'12' => array(
							'network' => 'Indigo',
							'brand'   => 'Tcell',
							'status'  => 'Operational',
						),
					),
				),
				'640' => array(
					'country'      => 'Tanzania',
					'country_code' => 'TZ',
					'operators'    => array(
						'01' => array(
							'network' => 'Tri Telecommunication( T ) Ltd . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'02' => array(
							'network' => 'MIC Tanzania Ltd',
							'brand'   => 'tiGO',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Zanzibar Telecom Ltd',
							'brand'   => 'Zantel',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Vodacom Tanzania Ltd',
							'brand'   => 'Vodacom',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Bharti Airtel',
							'brand'   => 'Airtel',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Dovetel Ltd',
							'brand'   => 'SasaTel',
							'status'  => 'Inactive',
						),
						'07' => array(
							'network' => 'Tanzania Telecommunication Company LTD',
							'brand'   => 'TTCL Mobile',
							'status'  => 'Inactive',
						),
						'08' => array(
							'network' => 'Benson Informatics Ltd',
							'brand'   => 'Benson Online( BOL )',
							'status'  => 'Inactive',
						),
						'09' => array(
							'network' => 'ExcellentCom Tanzania Ltd',
							'brand'   => 'Hits',
							'status'  => 'Inactive',
						),
						'11' => array(
							'network' => 'Smile Telecoms Holdings Ltd',
							'brand'   => 'SmileCom',
							'status'  => 'Inactive',
						),
					),
				),
				'520' => array(
					'country'      => 'Thailand',
					'country_code' => 'TH',
					'operators'    => array(
						'00' => array(
							'network' => 'CAT Telecom',
							'brand'   => 'My by CAT',
							'status'  => 'Operational',
						),
						'01' => array(
							'network' => 'Advanced Info Service',
							'brand'   => 'AIS',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'CAT Telecom',
							'brand'   => 'CAT',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'AWN',
							'brand'   => 'AIS 3G',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Real Future',
							'brand'   => 'true Move',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'DTN',
							'brand'   => 'dTac',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => '',
							'brand'   => 'WCS',
							'status'  => 'Inactive',
						),
						'15' => array(
							'network' => 'Telephone Organization of Thailand',
							'brand'   => 'TOT 3G',
							'status'  => 'Operational',
						),
						'18' => array(
							'network' => 'Total Access Communications( DTAC )',
							'brand'   => 'dTac',
							'status'  => 'Operational',
						),
						'23' => array(
							'network' => 'Digital Phone( AIS )',
							'brand'   => 'AIS',
							'status'  => 'Operational',
						),
						'25' => array(
							'network' => 'true Corporation',
							'brand'   => 'WE PCT',
							'status'  => 'Operational',
						),
						'88' => array(
							'network' => 'true Corporation',
							'brand'   => 'true Move H',
							'status'  => 'Inactive',
						),
						'99' => array(
							'network' => 'true Corporation',
							'brand'   => 'true Move',
							'status'  => 'Operational',
						),
					),
				),
				'514' => array(
					'country'      => 'Timor - Leste',
					'country_code' => 'TL',
					'operators'    => array(
						'02' => array(
							'network' => 'Timor Telecom',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'615' => array(
					'country'      => 'Togo',
					'country_code' => 'TG',
					'operators'    => array(
						'01' => array(
							'network' => 'Togo Telecom ',
							'brand'   => 'Togo Cell',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Telecel',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'03' => array(
							'network' => 'Moov Togo',
							'brand'   => 'Moov',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Telecel',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'539' => array(
					'country'      => 'Tonga',
					'country_code' => 'TO',
					'operators'    => array(
						'01' => array(
							'network' => 'Tonga Communications Corporation ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'43' => array(
							'network' => 'Shoreline Communication ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'88' => array(
							'network' => 'Digicel',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
					),
				),
				'374' => array(
					'country'      => 'Trinidad and Tobago',
					'country_code' => 'TT',
					'operators'    => array(
						'12'  => array(
							'network' => 'BMobile',
							'brand'   => 'bMobile',
							'status'  => 'Operational',
						),
						'13'  => array(
							'network' => 'Digicel',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'140' => array(
							'network' => 'LaqTel Ltd . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'605' => array(
					'country'      => 'Tunisia',
					'country_code' => 'TN',
					'operators'    => array(
						'01' => array(
							'network' => 'Orange Tunisia',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Tunisie Telecom',
							'brand'   => 'Tunicell',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Orascom Telecom ',
							'brand'   => 'Tunisiana',
							'status'  => 'Operational',
						),
					),
				),
				'286' => array(
					'country'      => 'Turkey',
					'country_code' => 'TR',
					'operators'    => array(
						'01' => array(
							'network' => 'Turkcell Iletisim Hizmetleri A . S . ',
							'brand'   => 'Turkcell',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Vodafone',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Avea',
							'brand'   => 'Avea',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Aycell',
							'brand'   => 'Aycell',
							'status'  => 'Inactive',
						),
					),
				),
				'438' => array(
					'country'      => 'Turkmenistan',
					'country_code' => 'TM',
					'operators'    => array(
						'01' => array(
							'network' => 'Barash Communication Technologies( BCTI ) ',
							'brand'   => 'MTS',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'TM - Cell ',
							'brand'   => 'TM - Cell',
							'status'  => 'Operational',
						),
					),
				),
				'376' => array(
					'country'      => 'Turks And Caicos Islands',
					'country_code' => 'TC',
					'operators'    => array(
						'05'  => array(
							'network' => 'Digicel( Turks & Caicos ) Limited',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
						'350' => array(
							'network' => 'Cable & Wireless West Indies Ltd( Turks & Caicos )',
							'brand'   => 'Lime( Cable & Wireless )',
							'status'  => 'Operational',
						),
						'352' => array(
							'network' => 'IslandCom Communications Ltd . ',
							'brand'   => 'Islandcom',
							'status'  => 'Operational',
						),
					),
				),
				'641' => array(
					'country'      => 'Uganda',
					'country_code' => 'UG',
					'operators'    => array(
						'01' => array(
							'network' => 'Celtel Uganda ',
							'brand'   => 'Zain',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'MTN Uganda Ltd . ',
							'brand'   => 'MTN',
							'status'  => 'Operational',
						),
						'11' => array(
							'network' => 'Uganda Telecom Ltd . ',
							'brand'   => 'Uganda Telecom',
							'status'  => 'Operational',
						),
						'14' => array(
							'network' => 'Orange Uganda',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'22' => array(
							'network' => 'Warid Telecom Uganda Ltd . ',
							'brand'   => 'Warid Telecom',
							'status'  => 'Operational',
						),
					),
				),
				'234' => array(
					'country'      => 'United Kingdom',
					'country_code' => 'GB',
					'operators'    => array(
						'00'  => array(
							'network' => 'British Telecom',
							'brand'   => 'BT',
							'status'  => 'Operational',
						),
						'01'  => array(
							'network' => 'Mudio Mobile Ltd',
							'brand'   => 'Vectone MObile',
							'status'  => 'Operational',
						),
						'02'  => array(
							'network' => 'O2 UK Ltd',
							'brand'   => 'O2',
							'status'  => 'Operational',
						),
						'03'  => array(
							'network' => 'Jersey Airtel Ltd',
							'brand'   => 'Airtel - Vodafone',
							'status'  => 'Operational',
						),
						'04'  => array(
							'network' => 'FMS Solutions Ltd',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'05'  => array(
							'network' => 'COLT Mobile Telecommunications Ltd',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'06'  => array(
							'network' => 'Internet Computer Bureau Ltd',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'07'  => array(
							'network' => 'Cable and Wireless Plc',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'08'  => array(
							'network' => 'OnePhone Ltd',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'09'  => array(
							'network' => 'Tismi BV',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'10'  => array(
							'network' => 'Telefónica Europe',
							'brand'   => 'O2',
							'status'  => 'Operational',
						),
						'11'  => array(
							'network' => 'Telefónica Europe',
							'brand'   => 'O2',
							'status'  => 'Operational',
						),
						'12'  => array(
							'network' => 'Network Rail Infrastructure Ltd',
							'brand'   => 'Railtrack Plc( UK )',
							'status'  => 'Inactive',
						),
						'13'  => array(
							'network' => 'Network Rail Infrastructure Ltd',
							'brand'   => 'Railtrack Plc( UK )',
							'status'  => 'Inactive',
						),
						'14'  => array(
							'network' => 'Hay Systems Ltd',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'15'  => array(
							'network' => 'Vodafone Ltd',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'16'  => array(
							'network' => 'Talk Talk Communications Ltd',
							'brand'   => 'Talk Talk',
							'status'  => 'Operational',
						),
						'17'  => array(
							'network' => 'Flextel Ltd',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'18'  => array(
							'network' => 'Wire9 Telecom Plc',
							'brand'   => 'Cloud9',
							'status'  => 'Operational',
						),
						'19'  => array(
							'network' => 'Teleware Plc',
							'brand'   => 'Teleware',
							'status'  => 'Operational',
						),
						'20'  => array(
							'network' => 'Hutchison 3G UK Ltd',
							'brand'   => '3',
							'status'  => 'Operational',
						),
						'22'  => array(
							'network' => 'Routo Telecommunications Ltd',
							'brand'   => 'RoutoMessaging',
							'status'  => 'Inactive',
						),
						'24'  => array(
							'network' => 'Stour Marine',
							'brand'   => 'Greenfone',
							'status'  => 'Inactive',
						),
						'25'  => array(
							'network' => '',
							'brand'   => 'Truphone( UK )',
							'status'  => 'Inactive',
						),
						'30'  => array(
							'network' => 'Everything Everywhere Ltd',
							'brand'   => 'T - mobile',
							'status'  => 'Operational',
						),
						'31'  => array(
							'network' => 'Virgin Media( MVNO on EE)',
							'brand'   => 'Virgin',
							'status'  => 'Operational',
						),
						'32'  => array(
							'network' => 'Virgin Media( MVNO on EE)',
							'brand'   => 'Virgin',
							'status'  => 'Operational',
						),
						'33'  => array(
							'network' => 'Everything Everywhere Ltd',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'34'  => array(
							'network' => 'Everything Everywhere Ltd',
							'brand'   => 'Orange',
							'status'  => 'Operational',
						),
						'35'  => array(
							'network' => 'JSC Ingenium Ltd',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'37'  => array(
							'network' => 'Synectiv Ltd',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'50'  => array(
							'network' => 'Jersey Telecom GSM',
							'brand'   => 'JT - Wave',
							'status'  => 'Operational',
						),
						'55'  => array(
							'network' => 'Cable and Wireless Plc',
							'brand'   => 'Cable and Wireless',
							'status'  => 'Operational',
						),
						'58'  => array(
							'network' => 'Manx Telecom',
							'brand'   => 'Manx Telecom',
							'status'  => 'Operational',
						),
						'60'  => array(
							'network' => 'Dobson Telephone Co',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'75'  => array(
							'network' => 'Inquam Telecom Ltd . ',
							'brand'   => 'Inquam',
							'status'  => 'Operational',
						),
						'76'  => array(
							'network' => 'British Telecom',
							'brand'   => 'BT',
							'status'  => 'Operational',
						),
						'78'  => array(
							'network' => 'Airwave',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'91'  => array(
							'network' => 'Vodafone United Kingdom',
							'brand'   => 'Vodafone',
							'status'  => 'Operational',
						),
						'95'  => array(
							'network' => 'Network Rail Infrastructure Ltd',
							'brand'   => 'Railtrack Plc( UK )',
							'status'  => 'Inactive',
						),
						'995' => array(
							'network' => 'Guernsey Airtel',
							'brand'   => 'Guernsey Airtel',
							'status'  => 'Inactive',
						),
					),
				),
				'255' => array(
					'country'      => 'Ukraine',
					'country_code' => 'UA',
					'operators'    => array(
						'01' => array(
							'network' => 'Ukrainian Mobile Communication, UMC ',
							'brand'   => 'MTS',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Ukranian Radio Systems, URS ',
							'brand'   => 'Beeline',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Kyivstar GSM JSC',
							'brand'   => 'Kyivstar',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Intertelecom',
							'brand'   => 'IT',
							'status'  => 'Inactive',
						),
						'05' => array(
							'network' => 'Golden Telecom ',
							'brand'   => 'Golden Telecom',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Astelit ',
							'brand'   => 'life:)',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Ukrtelecom ',
							'brand'   => 'Ukrtelecom',
							'status'  => 'Operational',
						),
						'21' => array(
							'network' => 'CJSC - Telesystems of Ukraine ',
							'brand'   => 'PEOPLEnet',
							'status'  => 'Operational',
						),
						'23' => array(
							'network' => 'CDMA Ukraine',
							'brand'   => 'ITC',
							'status'  => 'Operational',
						),
						'25' => array(
							'network' => 'CST Inves',
							'brand'   => 'Newtone',
							'status'  => 'Operational',
						),
						'39' => array(
							'network' => 'Golden Telecom GSM',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'50' => array(
							'network' => 'Ukrainian Mobile Communications',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'67' => array(
							'network' => 'Kyivstar',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'68' => array(
							'network' => 'Wellcom',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'424' => array(
					'country'      => 'United Arab Emirates',
					'country_code' => 'AE',
					'operators'    => array(
						'02' => array(
							'network' => 'E mirates Telecom Corp',
							'brand'   => 'Etisalat',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Emirates Integrated Telecommunications Company',
							'brand'   => 'du',
							'status'  => 'Operational',
						),
					),
				),
				'310' => array(
					'country'      => 'United States',
					'country_code' => 'US',
					'operators'    => array(
						'00'  => array(
							'network' => 'Mid - Tex Cellular Ltd . ',
							'brand'   => 'Mid - Tex Cellular',
							'status'  => 'Operational',
						),
						'02'  => array(
							'network' => 'Sprint Spectrum',
							'brand'   => 'Sprint',
							'status'  => 'Inactive',
						),
						'03'  => array(
							'network' => 'Verizon Wireless ',
							'brand'   => 'Verizon',
							'status'  => 'Operational',
						),
						'04'  => array(
							'network' => 'Verizon Wireless ',
							'brand'   => 'Verizon ',
							'status'  => 'Operational',
						),
						'05'  => array(
							'network' => 'Verizon Wireless ',
							'brand'   => 'Verizon ',
							'status'  => 'Operational',
						),
						'06'  => array(
							'network' => 'Consolidated Telcom',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'07'  => array(
							'network' => 'Highland',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'08'  => array(
							'network' => 'Corr Wireless Communications',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'09'  => array(
							'network' => 'Edge Wireless LLC',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'10'  => array(
							'network' => 'Chariton Valley Communications',
							'brand'   => 'Chariton Valley',
							'status'  => 'Operational',
						),
						'11'  => array(
							'network' => '',
							'brand'   => 'Southern Communications Services',
							'status'  => 'Operational',
						),
						'12'  => array(
							'network' => 'Verizon Wireless ',
							'brand'   => 'Verizon ',
							'status'  => 'Operational',
						),
						'13'  => array(
							'network' => 'Alltel Wireless',
							'brand'   => 'Alltel Wireless',
							'status'  => 'Operational',
						),
						'14'  => array(
							'network' => 'Testing',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'15'  => array(
							'network' => 'Southern Communications dba Southern LINC',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'16'  => array(
							'network' => 'Cricket Communications ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'17'  => array(
							'network' => 'North Sight Communications Inc . ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'20'  => array(
							'network' => 'Missouri RSA 5 Partnership',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'23'  => array(
							'network' => 'VoiceStream 23',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'24'  => array(
							'network' => 'VoiceStream 24',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'25'  => array(
							'network' => 'VoiceStream 25',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'26'  => array(
							'network' => 'T - Mobile ',
							'brand'   => 'T - Mobile ',
							'status'  => 'Operational',
						),
						'30'  => array(
							'network' => 'Centennial Communications ',
							'brand'   => 'Centennial ',
							'status'  => 'Operational',
						),
						'31'  => array(
							'network' => 'AERIAL',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'32'  => array(
							'network' => 'IT & E Overseas, Inc . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'34'  => array(
							'network' => 'Airpeak ',
							'brand'   => 'Airpeak ',
							'status'  => 'Operational',
						),
						'38'  => array(
							'network' => 'USA 3650 AT & T',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'40'  => array(
							'network' => 'Commnet Wireless',
							'brand'   => 'Commnet Wireless',
							'status'  => 'Operational',
						),
						'46'  => array(
							'network' => 'TMP Corp ',
							'brand'   => 'Simmetry',
							'status'  => 'Operational',
						),
						'50'  => array(
							'network' => 'Wikes Cellular',
							'brand'   => 'Wikes Cellular',
							'status'  => 'Operational',
						),
						'60'  => array(
							'network' => 'Consolidated Telcom ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'70'  => array(
							'network' => 'AT & T',
							'brand'   => 'AT & T',
							'status'  => 'Operational',
						),
						'80'  => array(
							'network' => 'Corr Wireless Communications LLC ',
							'brand'   => 'Corr ',
							'status'  => 'Operational',
						),
						'90'  => array(
							'network' => 'AT & T',
							'brand'   => 'AT & T ',
							'status'  => 'Operational',
						),
						'100' => array(
							'network' => 'New Mexico RSA 4 East Ltd . Partnership ',
							'brand'   => 'Plateau Wireless ',
							'status'  => 'Operational',
						),
						'110' => array(
							'network' => 'High Plains Wireless',
							'brand'   => 'High Plains Wireless',
							'status'  => 'Operational',
						),
						'120' => array(
							'network' => 'Sprint ',
							'brand'   => 'Sprint ',
							'status'  => 'Operational',
						),
						'130' => array(
							'network' => 'Alltel',
							'brand'   => 'Alltel',
							'status'  => 'Operational',
						),
						'140' => array(
							'network' => 'GTA Wireless LLC ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'150' => array(
							'network' => 'Wilkes Cellular',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'160' => array(
							'network' => 'T - Mobile ',
							'brand'   => 'T - Mobile ',
							'status'  => 'Operational',
						),
						'170' => array(
							'network' => 'Broadpoint Inc',
							'brand'   => 'PetroCom',
							'status'  => 'Operational',
						),
						'180' => array(
							'network' => 'West Central Wireless ',
							'brand'   => 'West Central ',
							'status'  => 'Operational',
						),
						'190' => array(
							'network' => 'Alaska Wireless Communications LLC ',
							'brand'   => 'Dutch Harbor ',
							'status'  => 'Operational',
						),
						'200' => array(
							'network' => 'T - Mobile ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'210' => array(
							'network' => 'Farmers Cellular Telephone',
							'brand'   => 'Farmers Cellular',
							'status'  => 'Operational',
						),
						'220' => array(
							'network' => 'T - Mobile ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'230' => array(
							'network' => 'T - Mobile ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'240' => array(
							'network' => 'T - Mobile ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'250' => array(
							'network' => 'T - Mobile ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'260' => array(
							'network' => 'Cellular One',
							'brand'   => 'Cellular One',
							'status'  => 'Operational',
						),
						'270' => array(
							'network' => 'T - Mobile ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'280' => array(
							'network' => 'Verizon Wireless ',
							'brand'   => 'Verizon ',
							'status'  => 'Inactive',
						),
						'290' => array(
							'network' => 'NEP Wireless',
							'brand'   => 'NEP Wireless',
							'status'  => 'Operational',
						),
						'300' => array(
							'network' => 'Smart Call( Truphone ) ',
							'brand'   => 'iSmart Mobile ',
							'status'  => 'Operational',
						),
						'310' => array(
							'network' => 'T - Mobile ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'311' => array(
							'network' => 'Farmers Wireless ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'320' => array(
							'network' => 'Smith Bagley Inc, dba Cellular One ',
							'brand'   => 'Cellular One ',
							'status'  => 'Operational',
						),
						'330' => array(
							'network' => 'Michigan Wireless, LLC',
							'brand'   => 'Bug Tussel Wireless',
							'status'  => 'Operational',
						),
						'340' => array(
							'network' => 'Westlink Communications ',
							'brand'   => 'Westlink ',
							'status'  => 'Operational',
						),
						'350' => array(
							'network' => 'Mohave Cellular L . P . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'360' => array(
							'network' => 'Cellular Network Partnership dba Pioneer Cellular ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'370' => array(
							'network' => 'General Communication Inc . ',
							'brand'   => 'GCI Wireless in Alaska',
							'status'  => 'Operational',
						),
						'380' => array(
							'network' => 'New Cingular Wireless PCS, LLC ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'390' => array(
							'network' => 'Verizon Wireless ',
							'brand'   => 'Verizon ',
							'status'  => 'Operational',
						),
						'400' => array(
							'network' => 'Wave Runner LLC ',
							'brand'   => 'i CAN_GSM ',
							'status'  => 'Operational',
						),
						'410' => array(
							'network' => 'AT & T',
							'brand'   => 'AT & T ',
							'status'  => 'Operational',
						),
						'420' => array(
							'network' => 'Cincinnati Bell Wireless LLC ',
							'brand'   => 'Cincinnati Bell ',
							'status'  => 'Operational',
						),
						'430' => array(
							'network' => 'Alaska Digitel LLC ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'440' => array(
							'network' => 'Numerex Corp . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'450' => array(
							'network' => 'Viaero Wireless ',
							'brand'   => 'Viaero ',
							'status'  => 'Operational',
						),
						'460' => array(
							'network' => 'TMP Corp ',
							'brand'   => 'Simmetry ',
							'status'  => 'Operational',
						),
						'470' => array(
							'network' => 'Omnipoint',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'480' => array(
							'network' => 'Verizon Wireless ',
							'brand'   => 'Verizon ',
							'status'  => 'Operational',
						),
						'490' => array(
							'network' => 'T - Mobile ',
							'brand'   => 'T - Mobile ',
							'status'  => 'Operational',
						),
						'500' => array(
							'network' => 'Alltel ',
							'brand'   => 'Alltel ',
							'status'  => 'Operational',
						),
						'510' => array(
							'network' => 'Airtel Wireless LLC ',
							'brand'   => 'Airtel ',
							'status'  => 'Operational',
						),
						'520' => array(
							'network' => 'VeriSign ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'530' => array(
							'network' => 'NewCore Wireless',
							'brand'   => 'NewCore Wireless',
							'status'  => 'Operational',
						),
						'540' => array(
							'network' => 'Oklahoma Western Telephone Company ',
							'brand'   => 'Oklahoma Western ',
							'status'  => 'Operational',
						),
						'550' => array(
							'network' => 'Wireless Solutions International ',
							'brand'   => 'AT & T ',
							'status'  => 'Inactive',
						),
						'560' => array(
							'network' => 'AT & T',
							'brand'   => 'AT & T ',
							'status'  => 'Operational',
						),
						'570' => array(
							'network' => 'MTPCS LLC ',
							'brand'   => 'Cellular One ',
							'status'  => 'Operational',
						),
						'580' => array(
							'network' => 'Inland Cellular Telephone Company ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'590' => array(
							'network' => 'Alltel',
							'brand'   => 'Alltel ',
							'status'  => 'Operational',
						),
						'600' => array(
							'network' => 'New Cell Inc . dba Cellcom ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'610' => array(
							'network' => 'Elkhart Telephone Co . ',
							'brand'   => 'Epic Touch ',
							'status'  => 'Operational',
						),
						'620' => array(
							'network' => 'Coleman County Telecommunications Inc . ( Trans Texas PCS) ',
							'brand'   => 'Coleman County Telecom ',
							'status'  => 'Operational',
						),
						'630' => array(
							'network' => 'Choice Wireless ',
							'brand'   => 'AmeriLink PCS ',
							'status'  => 'Operational',
						),
						'640' => array(
							'network' => 'Airadigm Communications ',
							'brand'   => 'Airadigm ',
							'status'  => 'Operational',
						),
						'650' => array(
							'network' => 'Jasper Wireless Inc . ',
							'brand'   => 'Jasper ',
							'status'  => 'Operational',
						),
						'660' => array(
							'network' => 'MetroPCS',
							'brand'   => 'MetroPCS',
							'status'  => 'Operational',
						),
						'670' => array(
							'network' => 'Northstar ',
							'brand'   => 'Northstar ',
							'status'  => 'Operational',
						),
						'680' => array(
							'network' => 'AT & T',
							'brand'   => 'AT & T ',
							'status'  => 'Operational',
						),
						'690' => array(
							'network' => 'Conestoga Wireless Company ',
							'brand'   => 'Conestoga ',
							'status'  => 'Operational',
						),
						'700' => array(
							'network' => 'Cross Valiant Cellular Partnership ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'710' => array(
							'network' => 'Arctic Slopo Telephone Association Cooperative ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'720' => array(
							'network' => 'Wireless Solutions International Inc . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'730' => array(
							'network' => 'SeaMobile ',
							'brand'   => 'SeaMobile ',
							'status'  => 'Operational',
						),
						'740' => array(
							'network' => 'Convey Communications Inc . ',
							'brand'   => 'Convey ',
							'status'  => 'Operational',
						),
						'750' => array(
							'network' => 'East Kentucky Network LLC dba Appalachian Wireless ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'760' => array(
							'network' => 'Panhandle Telecommunications Systems Inc . ',
							'brand'   => 'Panhandle ',
							'status'  => 'Operational',
						),
						'770' => array(
							'network' => 'Iowa Wireless Services LLC dba I Wireless ',
							'brand'   => 'i wireless ',
							'status'  => 'Operational',
						),
						'780' => array(
							'network' => 'Connect Net Inc ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'790' => array(
							'network' => 'PinPoint Communications Inc . ',
							'brand'   => 'PinPoint ',
							'status'  => 'Operational',
						),
						'800' => array(
							'network' => 'T - Mobile ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'810' => array(
							'network' => 'Brazos Cellular Communications Ltd . ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'820' => array(
							'network' => 'South Canaan Cellular Communications Co . LP ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'830' => array(
							'network' => 'Caprock Cellular Ltd . Partnership ',
							'brand'   => 'Caprock ',
							'status'  => 'Operational',
						),
						'840' => array(
							'network' => 'Telecom North America Mobile, Inc . ',
							'brand'   => 'telna Mobile ',
							'status'  => 'Operational',
						),
						'850' => array(
							'network' => 'Aeris Communications, Inc . ',
							'brand'   => 'Aeris ',
							'status'  => 'Operational',
						),
						'860' => array(
							'network' => 'TX RSA 15B2, LP dba Five Star Wireless ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'870' => array(
							'network' => 'Kaplan Telephone Company Inc . ',
							'brand'   => 'PACE ',
							'status'  => 'Operational',
						),
						'880' => array(
							'network' => 'Advantage Cellular Systems, Inc . ',
							'brand'   => 'Advantage ',
							'status'  => 'Operational',
						),
						'890' => array(
							'network' => 'Verizon Wireless ',
							'brand'   => 'Verizon ',
							'status'  => 'Operational',
						),
						'900' => array(
							'network' => 'Mid - Rivers Communications ',
							'brand'   => 'Mid - Rivers Wireless ',
							'status'  => 'Operational',
						),
						'910' => array(
							'network' => 'Verizon Wireless ',
							'brand'   => 'Verizon ',
							'status'  => 'Operational',
						),
						'920' => array(
							'network' => 'Get Mobile ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'930' => array(
							'network' => 'Copper Valley Wireless ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'940' => array(
							'network' => 'Poka Lambro Telecommunications Ltd . ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'950' => array(
							'network' => 'Iris Wireless LLC ',
							'brand'   => 'XIT Wireless ',
							'status'  => 'Operational',
						),
						'960' => array(
							'network' => 'Texas RSA 1 dba XIT Cellular ',
							'brand'   => 'Plateau Wireless ',
							'status'  => 'Operational',
						),
						'970' => array(
							'network' => 'Globalstar USA ',
							'brand'   => 'Globalstar ',
							'status'  => 'Operational',
						),
						'980' => array(
							'network' => 'New Cingular Wireless PCS LLC ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'990' => array(
							'network' => 'E . N . M . R . Telephone Cooperative',
							'brand'   => '',
							'status'  => 'Inactive',
						),
					),
				),
				'748' => array(
					'country'      => 'Uruguay',
					'country_code' => 'UY',
					'operators'    => array(
						'00' => array(
							'network' => 'Compania estatal( ANTEL )',
							'brand'   => 'Ancel',
							'status'  => 'Operational',
						),
						'01' => array(
							'network' => 'Compania estatal( ANTEL )',
							'brand'   => 'Ancel',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Ancel ',
							'brand'   => '',
							'status'  => 'Inactive',
						),
						'07' => array(
							'network' => 'Telefónica Móviles Uruguay',
							'brand'   => 'Movistar',
							'status'  => 'Operational',
						),
						'10' => array(
							'network' => 'AM Wireless Uruguay S . A . ',
							'brand'   => 'Claro',
							'status'  => 'Operational',
						),
					),
				),
				'434' => array(
					'country'      => 'Uzbekistan',
					'country_code' => 'UZ',
					'operators'    => array(
						'01' => array(
							'network' => 'Buztel ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Uzmacom ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Unitel LLC',
							'brand'   => 'Beeline',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Coscom ',
							'brand'   => 'Ucell',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Perfectum Mobile',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'Uzdunrobita ',
							'brand'   => 'MTS',
							'status'  => 'Operational',
						),
					),
				),
				'541' => array(
					'country'      => 'Vanuatu',
					'country_code' => 'VU',
					'operators'    => array(
						'01' => array(
							'network' => 'Telecom Vanatou',
							'brand'   => 'Smile',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Digicel',
							'brand'   => 'Digicel',
							'status'  => 'Operational',
						),
					),
				),
				'734' => array(
					'country'      => 'Venezuela',
					'country_code' => 'VE',
					'operators'    => array(
						'01' => array(
							'network' => 'Digitel',
							'brand'   => 'Digitel',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Digitel',
							'brand'   => 'Digitel',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Digitel',
							'brand'   => 'Digitel',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Movistar',
							'brand'   => 'Movistar',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'Movilnet',
							'brand'   => 'Movilnet',
							'status'  => 'Operational',
						),
					),
				),
				'452' => array(
					'country'      => 'Socialist Republic of Vietnam',
					'country_code' => 'VN',
					'operators'    => array(
						'01' => array(
							'network' => 'Vietnam Mobile Telecom( VMS )',
							'brand'   => 'MobilFone',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Vinaphone ',
							'brand'   => 'Vinaphone',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'S - Telecom',
							'brand'   => 'S - Fone',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Viettel Corporation( Viettel Mobile)',
							'brand'   => 'Viettel',
							'status'  => 'Operational',
						),
						'05' => array(
							'network' => 'Hanoi Telecom',
							'brand'   => 'Vietnamobile',
							'status'  => 'Operational',
						),
						'06' => array(
							'network' => 'EVN Telecom',
							'brand'   => 'E - Mobile',
							'status'  => 'Operational',
						),
						'07' => array(
							'network' => 'GTEL Mobile JSC',
							'brand'   => 'Beeline VN',
							'status'  => 'Operational',
						),
						'08' => array(
							'network' => 'EVN Telecom',
							'brand'   => 'EVN Telecom',
							'status'  => 'Operational',
						),
					),
				),
				'421' => array(
					'country'      => 'Yemen',
					'country_code' => 'YE',
					'operators'    => array(
						'01' => array(
							'network' => 'SabaFon',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'Spacetel Yemen ',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Yemen Mobile',
							'brand'   => 'Yemen Mobile',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Y',
							'brand'   => 'HiTS - Unitel',
							'status'  => 'Operational',
						),
					),
				),
				'645' => array(
					'country'      => 'Zambia',
					'country_code' => 'ZM',
					'operators'    => array(
						'01' => array(
							'network' => 'Zain',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'02' => array(
							'network' => 'MTN',
							'brand'   => '',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Zamtel',
							'brand'   => '',
							'status'  => 'Operational',
						),
					),
				),
				'648' => array(
					'country'      => 'Zimbabwe',
					'country_code' => 'ZW',
					'operators'    => array(
						'01' => array(
							'network' => 'Net * One Cellular Ltd',
							'brand'   => 'Net One ',
							'status'  => 'Operational',
						),
						'03' => array(
							'network' => 'Telecel Zimbabwe Ltd',
							'brand'   => 'Telecel',
							'status'  => 'Operational',
						),
						'04' => array(
							'network' => 'Econet Wireless',
							'brand'   => 'Econet',
							'status'  => 'Operational',
						),
					),
				),
			);
		}
	}

}
