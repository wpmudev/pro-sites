<?php
/**
 * A logging class that observes certain log actions.
 * This makes it easier to debug issues with payment gateways.
 *
 * Observed actions
 *  - 'psts_gateway_error'
 *    Indicates that a gateway process was not successful.
 *    It has following params:
 *        $gateway_id .. The gateway that triggered the error
 *        $message .. The error message (string)
 *        $data .. Additional data that are dumped with the error
 *
 *  - 'psts_gateway_success'
 *    Indicates that a gateway process was successful.
 *    It has following params:
 *        $gateway_id .. The gateway that triggered the error
 *        $message .. The success message, indicating what was done (string)
 *        $result .. The result of the gateway process (e.g. retun value)
 *        $data .. Additional data that are dumped with the message
 */

class ProSites_Logging {
	/**
	 * Absolute path to the log file.
	 *
	 * @var string
	 */
	protected $log_file = '';

	/**
	 * Singleton getter.
	 *
	 * @return ProSites_Logging The Singleton object.
	 */
	public static function instance() {
		static $Inst = null;

		if ( null === $Inst ) {
			$Inst = new ProSites_Logging();
		}

		return $Inst;
	}

	/**
	 * Private constructor. This will attach the hooks to observe the log
	 * actions.
	 */
	private function __construct() {
		$this->log_file = WP_CONTENT_DIR . '/psts-debug.log';

		// Action: psts_gateway_error
		add_action(
			'psts_gateway_error',
			array( $this, 'gateway_error' ),
			10, 99
		);

		// Action: psts_gateway_success
		add_action(
			'psts_gateway_success',
			array( $this, 'gateway_success' ),
			10, 99
		);
	}

	/**
	 * Log a gateway error message.
	 *
	 * @param string $gateway_id The gateway that triggered the error
	 * @param string $message The error message (string)
	 * @param mixed $data Optional. User defined argument list.
	 */
	public function gateway_error( $gateway_id, $message ) {
		$message = sprintf(
			"%s GATEWAY ERROR [%s]: %s\n",
			date( "Y-m-d\tH:i:s\t" ),
			ucwords( $gateway_id ),
			ucwords( $message )
		);

		error_log( $message, 3, $this->log_file );
		for ( $i = 2; $i < func_num_args(); $i += 1 ) {
			$this->log_data( func_get_arg( $i ) );
		}
		$this->log_separator();
	}

	/**
	 * Log a gateway success message.
	 *
	 * @param string $gateway_id The gateway that triggered the error
	 * @param string $message The success message, indicating what was done (string)
	 * @param mixed $data Optional. User defined argument list.
	 */
	public function gateway_success( $gateway_id, $message ) {
		$message = sprintf(
			"%s GATEWAY SUCCESS [%s]: %s\n",
			date( "Y-m-d\tH:i:s\t" ),
			ucwords( $gateway_id ),
			ucwords( $message )
		);

		error_log( $message, 3, $this->log_file );
		for ( $i = 2; $i < func_num_args(); $i += 1 ) {
			$this->log_data( func_get_arg( $i ) );
		}
		$this->log_separator();
	}

	/**
	 * Loggs the specified data value to the log file. The value can be a simple
	 * string or a complex object.
	 *
	 * @param  mixed $data Value to log.
	 */
	protected function log_data( $data ) {
		if ( ! $data ) { return; }

		if ( is_scalar( $data ) ) {
			$dump = $data;
		} else {
			$dump = str_replace(
				'    ',
				"\t",
				print_r( $data, true )
			);
		}
		error_log( $dump . "\n", 3, $this->log_file );
	}

	/**
	 * Adds a separation line to the log file.
	 */
	protected function log_separator() {
		$line = "- - - - - - - - - - - - - - - - - - - - - - - - -\n";
		error_log( $line, 3, $this->log_file );
	}
}

/*
 * To enable Logging simply add this line to wp-config.php
 *
 * define( 'PSTS_LOGGING', true );
 */
if ( defined( 'PSTS_LOGGING' ) && PSTS_LOGGING ) {
	// Initialize the logging object right now.
	ProSites_Logging::instance();
}