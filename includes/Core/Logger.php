<?php

namespace WPO\AOM\Core;

defined( 'ABSPATH' ) || exit;

final class Logger {
	private static ?\WC_Logger_Interface $wc_logger = null;

	private static array $context = array( 'source' => 'wpo-aom' );

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
		$allowed_levels = array( 'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency' );
		if ( ! in_array( $level, $allowed_levels, true ) ) {
			return;
		}

		/**
		 * Filter whether logging is enabled.
		 *
		 * @param bool   $enabled
		 * @param string $level
		 * @param string $message
		 */
		if ( ! apply_filters( 'wpo_aom_logging_enabled', true, $level, $message ) ) {
			return;
		}

		self::get_logger()->log( $level, $message, self::$context );
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
