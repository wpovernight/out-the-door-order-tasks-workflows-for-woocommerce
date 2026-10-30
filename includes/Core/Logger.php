<?php

namespace WPO\OTD\Core;

defined( 'ABSPATH' ) || exit;

final class Logger {
	/**
	 * The levels a message can be logged at.
	 */
	private const LEVELS = array( 'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency' );

	private static ?\WC_Logger_Interface $wc_logger = null;

	private static array $context = array( 'source' => 'wpo-otd' );

	/**
	 * Get the WC_Logger instance, initializing it on first use.
	 *
	 * @return \WC_Logger_Interface
	 */
	private static function get_logger(): \WC_Logger_Interface {
		if ( is_null( self::$wc_logger ) ) {
			self::$wc_logger = wc_get_logger();
		}

		return self::$wc_logger;
	}

	/**
	 * Log a message at the given level.
	 *
	 * @param string $level Allowed levels: debug, info, notice, warning, error, critical, alert, emergency.
	 * @param string $message
	 */
	public static function log( string $level, string $message ): void {
		if ( empty( $message ) ) {
			return;
		}

		// Validate level
		if ( ! in_array( $level, self::LEVELS, true ) ) {
			return;
		}

		/**
		 * Filter whether logging is enabled.
		 *
		 * @param bool   $enabled Whether logging is enabled. Default true.
		 * @param string $level   The log level of the message.
		 * @param string $message The message being logged.
		 */
		if ( ! apply_filters( 'wpo_otd_logging_enabled', true, $level, $message ) ) {
			return;
		}

		self::get_logger()->log( $level, $message, self::$context );
	}

	/**
	 * Log a message at most once a day, for something that recurs on every request until it is fixed, such as a
	 * connector that fails each time it is read.
	 *
	 * A transient named by the key marks the message as logged for a day, and is set even while logging is turned off
	 * by "wpo_otd_logging_enabled". A message that would never be logged, empty or at a level that is not allowed,
	 * is not marked.
	 *
	 * @param string $key     The transient's name: prefixed with the plugin's own, and at most 172 characters. A
	 *                        message that is the same thing logged again must have the same key; never put a value
	 *                        that must stay secret in it, a hash of it at most.
	 * @param string $level   As for log().
	 * @param string $message
	 *
	 * @return void
	 */
	public static function once( string $key, string $level, string $message ): void {
		if ( empty( $message ) || ! in_array( $level, self::LEVELS, true ) || false !== get_transient( $key ) ) {
			return;
		}

		set_transient( $key, 1, DAY_IN_SECONDS );

		self::log( $level, $message );
	}

	/**
	 * Log a message at the debug level.
	 *
	 * @param string $message
	 */
	public static function debug( string $message ): void {
		self::log( 'debug', $message );
	}

	/**
	 * Log a message at the info level.
	 *
	 * @param string $message
	 */
	public static function info( string $message ): void {
		self::log( 'info', $message );
	}

	/**
	 * Log a message at the notice level.
	 *
	 * @param string $message
	 */
	public static function notice( string $message ): void {
		self::log( 'notice', $message );
	}

	/**
	 * Log a message at the warning level.
	 *
	 * @param string $message
	 */
	public static function warning( string $message ): void {
		self::log( 'warning', $message );
	}

	/**
	 * Log a message at the error level.
	 *
	 * @param string $message
	 */
	public static function error( string $message ): void {
		self::log( 'error', $message );
	}

	/**
	 * Log a message at the critical level.
	 *
	 * @param string $message
	 */
	public static function critical( string $message ): void {
		self::log( 'critical', $message );
	}

	/**
	 * Log a message at the alert level.
	 *
	 * @param string $message
	 */
	public static function alert( string $message ): void {
		self::log( 'alert', $message );
	}

	/**
	 * Log a message at the emergency level.
	 *
	 * @param string $message
	 */
	public static function emergency( string $message ): void {
		self::log( 'emergency', $message );
	}
}
