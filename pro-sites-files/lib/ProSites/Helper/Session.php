<?php
if ( ! class_exists( 'ProSites_Helper_Session' ) ) {

	class ProSites_Helper_Session {

		public static $token = 'prosites_';

		/**
		 * IMPORTANT: Only works for logged in users.
		 *
		 * To use this for registration, create the user first and login immediately
		 *
		 * @param $key
		 * @param null $value
		 * @param bool $unset
		 * @param bool $duration
		 *
		 * @return bool|null|string
		 */
		public static function session( $key, $value = null, $unset = false, $duration = false ) {

			// WordPress 4.0+ only
			if ( class_exists( 'WP_Session_Tokens' ) && is_user_logged_in() ) {
				$user_id = get_current_user_id();

				$session     = WP_Session_Tokens::get_instance( $user_id );
				$token_parts = explode( '_', self::$token );
				$token_parts = (int) array_pop( $token_parts );
				self::$token = empty( $token_parts ) ? self::$token . $user_id : self::$token;

				if ( empty( $duration ) ) {
					// Default 1 hr
					$duration = strtotime( '+1 hour', time() );
				}

				$session_data = $session->get( self::$token );
				if ( empty( $session_data ) ) {
					$session_data = array(
						'expiration' => $duration,
					);
				}

				if ( null === $value && ! $unset ) {
					if ( is_array( $key ) ) {
						return self::_get_val( $session_data, $key );
					} else {
						return isset( $session_data[ $key ] ) ? $session_data[ $key ] : null;
					}
				} else {
					if ( ! $unset ) {
						if ( is_array( $key ) ) {
							self::_set_val( $session_data, $key, $value );
						} else {
							$session_data[ $key ] = $value;
						}
					} else {
						if ( is_array( $key ) ) {
							self::_unset_val( $session_data, $key );
						} else {
							unset( $session_data[ $key ] );
						}
					}
					$session->update( self::$token, $session_data );

					return $value;
				}
			} else {
				// Pre WordPress 4.0
				// Rely on $_SESSION vars. May require some plugins or server configuration
				// to work properly.
				if ( ! session_id() ) {
					session_start();
				}
				if ( null == $value && ! $unset ) {
					if ( is_array( $key ) ) {
						return self::_get_val( $_SESSION, $key );
					} else {
						return isset( $_SESSION[ $key ] ) ? $_SESSION[ $key ] : null;
					}
				} else {
					if ( ! $unset ) {
						if ( is_array( $key ) ) {
							self::_set_val( $_SESSION, $key, $value );
						} else {
							$_SESSION[ $key ] = $value;
						}
					} else {
						if ( is_array( $key ) ) {
							self::_unset_val( $_SESSION, $key );
						} else {
							unset( $_SESSION[ $key ] );
						}
					}
					return $value;
				}
			}
		}

		private static function _get_val( $arr, $index ) {
			$value = false;
			if ( is_array( $index ) ) {
				$key = array_shift( $index );
				if ( isset( $arr[ $key ] ) ) {
					$value = $arr[ $key ];
					if ( count( $index ) ) {
						$value = self::_get_val( $value, $index );
					}
				} else {
					return null;
				}
			}

			return $value;
		}

		private static function _set_val( &$data, $path, $value ) {
			$temp = &$data;
			foreach ( $path as $key ) {
				$temp = &$temp[ $key ];
			}
			$temp = $value;

			return $value;
		}

		private static function _unset_val( &$data, $path ) {
			$temp = &$data;
			$kill = $path[ count($path) - 1 ];
			foreach ( $path as $key ) {
				if ( $kill != $key ) {
					$temp = &$temp[ $key ];
				} else {
					unset( $temp[ $key ] );
				}
			}
		}


	}

}