<?php

use ET\Ease\Utils;
use ET\Ease\Logger;

// ------>>>>> NOTE: Functions in this file appear in alphabetical order! <<<<<------

/**
 * Returns the {@see ET\Ease\Utils} instance.
 *
 * @since 1.0.0
 */
function et_(): Utils {
	return ET\Ease\Utils::instance();
}

/**
 * Wrapper for {@see Logger::debug()}
 *
 * @since 1.1.0
 */
function et_debug( $msg, int $bt_index = 4, bool $log_ajax = true ): void {
	Logger::debug( $msg, $bt_index, $log_ajax );
}

/**
 * Wrapper for {@see Logger::error()}
 *
 * @since 1.1.0
 */
function et_error( $msg, $bt_index = 4, $log_ajax = true ): void {
	Logger::error( "[ERROR]: {$msg}", $bt_index, $log_ajax );
}

/**
 * Prepends "You're Doing It Wrong!" to provided message and passes it to either
 * {@see et_debug()} or {@see et_error()} depending on the value of the `$error` param.
 *
 * @since 1.1.0
 */
function et_wrong( $msg, bool $error = false ): void {
	$msg = "You're Doing It Wrong! {$msg}";

	if ( $error ) {
		et_error( $msg );
	} else {
		et_debug( $msg );
	}
}

// ------>>>>> NOTE: Functions in this file appear in alphabetical order! <<<<<------
