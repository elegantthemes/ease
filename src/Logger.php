<?php

namespace ET\Ease;

use function et_;


class Logger {

	/**
	 * Checksum for every log message output during the current request.
	 *
	 * @since 1.1.0
	 */
	protected static array $_HISTORY = [];

	/**
	 * Writes a message to the debug log if it hasn't already been written once.
	 *
	 * @since 1.1.0
	 *
	 * @param  mixed    $message
	 * @param  int      $bt_index
	 * @param  boolean  $log_ajax  Whether or not to log on AJAX calls.
	 */
	protected static function _maybeWriteLog( $message, int $bt_index = 4, bool $log_ajax = true ) {
		global $ET_IS_TESTING_DEPRECATIONS;

		if ( ! $log_ajax && ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return;
		}

		if ( ! is_scalar( $message ) ) {
			$message = print_r( $message, true );
		}

		$message = (string) $message;
		$hash    = md5( $message );

		if ( $ET_IS_TESTING_DEPRECATIONS ) {
			trigger_error( $message );

		} else if ( getenv( 'CI' ) || ! in_array( $hash, self::$_HISTORY ) ) {
			self::$_HISTORY[] = $hash;

			self::_writeLog( $message, $bt_index );
		}
	}

	/**
	 * Writes a message to the WP Debug and PHP Error logs.
	 *
	 * @since 1.1.0
	 *
	 * @param  string  $message   Message to log
	 * @param  int     $bt_index  Index of stack within the backtrace array from which to get
	 *                            the file and line number that triggered this log message.
	 */
	protected static function _writeLog( string $message, int $bt_index = 4 ) {
		$message   = trim( $message );
		$backtrace = debug_backtrace( 1 );

		if ( ! isset( $backtrace[ $bt_index ] ) ) {
			while ( $bt_index > 0 && ! isset( $backtrace[ $bt_index ] ) ) {
				$bt_index--;
			}

			// We need two stacks to get all the data we need so let's go up one more
			$bt_index--;
		}

		$stack = $backtrace[ $bt_index ];
		$file  = et_()->arrayGet( $stack, 'file', '<unknown file>' );
		$line  = et_()->arrayGet( $stack, 'line', '<unknown line>' );

		// Name of the function and class (if applicable) are in the previous stack (stacks are in reverse order)
		$stack    = $backtrace[ $bt_index + 1 ];
		$class    = et_()->arrayGet( $stack, 'class', '' );
		$function = et_()->arrayGet( $stack, 'function', '<unknown function>' );

		if ( $class ) {
			$class .= '::';
		}

		if ( '<unknown file>' !== $file ) {
			$file  = et_()->normalizePath( $file );
			$parts = explode( '/', $file );
			$parts = array_slice( $parts, -2 );
			$file  = ".../{$parts[0]}/{$parts[1]}";
		}

		$message = " {$file}:{$line}  {$class}{$function}():\n{$message}\n";

		error_log( $message );
	}

	/**
	 * Writes message to the logs if {@see WP_DEBUG} is `true`, otherwise does nothing.
	 *
	 * @since 1.1.0
	 *
	 * @param  mixed    $message
	 * @param  int      $bt_index  {@see self::_writeLog()}
	 * @param  boolean  $log_ajax  Whether or not to log on AJAX calls.
	 */
	public static function debug( $message, int $bt_index = 4, bool $log_ajax = true ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			self::_maybeWriteLog( $message, $bt_index, $log_ajax );
		}
	}

	/**
	 * Writes an error message to the logs regardless of whether or not debug mode is enabled.
	 *
	 * @since 1.1.0
	 *
	 * @param  mixed    $message
	 * @param  int      $bt_index  {@see self::_writeLog()}
	 * @param  boolean  $log_ajax  Whether or not to log on AJAX calls.
	 */
	public static function error( $message, int $bt_index = 4, bool $log_ajax = true ): void {
		self::_maybeWriteLog( $message, $bt_index, $log_ajax );
	}
}
