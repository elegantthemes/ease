<?php

namespace ET\Ease;


class Utils {

	private static $_instance;

	private $_pick;
	private $_pick_value = '_undefined_';
	private $_sort_by;

	/**
	 * Sort arguments being passed through to callbacks. See {@see self::_userSort()}.
	 *
	 * @since 1.0.0
	 */
	protected array $sort_arguments = [
		'array'      => array(),
		'array_map'  => array(),
		'sort'       => '__return_false',
		'comparison' => '__return_false',
	];

	/**
	 * Sort using a custom function accounting for the common undefined order
	 * pitfall due to a return value of 0.
	 *
	 * @since 1.0.0
	 *
	 * @param  array &   $array                Array to sort
	 * @param  callable  $sort_function        "usort", "uasort" or "uksort"
	 * @param  callable  $comparison_function  Custom comparison function
	 *
	 * @return array
	 */
	protected function _userSort( &$array, $sort_function, $comparison_function ) {
		$allowed_sort_functions = array( 'usort', 'uasort', 'uksort' );

		if ( ! $this->includes( $allowed_sort_functions, $sort_function ) ) {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Only custom sorting functions can be used.', 'et_core' ), esc_html( et_get_theme_version() ) );
		}

		// Use properties temporarily to pass values in order to preserve PHP 5.2 support.
		$this->sort_arguments['array']      = $array;
		$this->sort_arguments['sort']       = $sort_function;
		$this->sort_arguments['comparison'] = $comparison_function;
		$this->sort_arguments['array_map']  = 'uksort' === $sort_function
			? array_flip( array_keys( $array ) )
			: array_values( $array );

		$sort_function( $array, array( $this, '_userSortCallback' ) );

		$this->sort_arguments['array']      = array();
		$this->sort_arguments['array_map']  = array();
		$this->sort_arguments['sort']       = '__return_false';
		$this->sort_arguments['comparison'] = '__return_false';

		return $array;
	}

	/**
	 * Sort callback only meant to acompany self::sort().
	 * Do not use outside of self::_user_sort().
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed  $a
	 * @param  mixed  $b
	 *
	 * @return integer
	 */
	protected function _userSortCallback( $a, $b ) {
		// @phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
		$result = (int) call_user_func( $this->sort_arguments['comparison'], $a, $b );

		if ( 0 !== $result ) {
			return $result;
		}

		if ( 'uksort' === $this->sort_arguments['sort'] ) {
			// Intentional isset() use for performance reasons.
			$a_order = isset( $this->sort_arguments['array_map'][ $a ] ) ?
				$this->sort_arguments['array_map'][ $a ] : false;
			$b_order = isset( $this->sort_arguments['array_map'][ $b ] ) ?
				$this->sort_arguments['array_map'][ $b ] : false;
		} else {
			$a_order = array_search( $a, $this->sort_arguments['array_map'] );
			$b_order = array_search( $b, $this->sort_arguments['array_map'] );
		}

		if ( false === $a_order || false === $b_order ) {
			// This should not be possible so we fallback to the undefined
			// sorting behavior by returning 0.
			return 0;
		}

		return $a_order - $b_order;
	}

	public function _arrayPickCallback( $item ) {
		$pick  = $this->_pick;
		$value = $this->_pick_value;

		if ( is_array( $item ) && isset( $item[ $pick ] ) ) {
			return '_undefined_' !== $value ? $value === $item[ $pick ] : $item[ $pick ];
		}

		if ( is_object( $item ) && isset( $item->$pick ) ) {
			return '_undefined_' !== $value ? $value === $item->$pick : $item->$pick;
		}

		return false;
	}

	public function _arraySortByCallback( $a, $b ): int {
		$sort_by = $this->_sort_by;

		if ( is_array( $a ) ) {
			return strcmp( $a[ $sort_by ], $b[ $sort_by ] );

		}

		if ( is_object( $a ) ) {
			return strcmp( $a->$sort_by, $b->$sort_by );
		}

		return 0;
	}

	public function __call( $name, $args ) {
		$class = __CLASS__;

		if ( method_exists( $this, $name ) ) {
			throw new \Exception( "Call to protected or private method: {$class}::{$name}() from out of scope!" );
		}

		if ( method_exists( 'ET_Core_Data_Utils', $name ) ) {
			return \ET_Core_Data_Utils::instance()->$name( ...$args );
		}

		throw new \Exception( "Call to undefined method: {$class}::{$name}()" );
	}

	/**
	 * Returns `true` if all values in `$array` are not empty, `false` otherwise.
	 * If `$callback` is provided then values are passed to it instead of {@see empty()} (it should return a booleon value).
	 *
	 * @param array     $array
	 * @param callable  $callback  Pass each value to callback instead of {@see empty()}. Optional.
	 */
	public function all( array $array, $callback = null ): bool {
		if ( null === $callback ) {
			foreach( $array as $key => $value ) {
				if ( empty( $value ) ) {
					return false;
				}
			}
		} else if ( is_callable( $callback ) ) {
			foreach( $array as $key => $value ) {
				if ( ! $callback( $value ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Flattens a multi-dimensional array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $array An array to flatten.
	 */
	function arrayFlatten( array $array ): array {
		$iterator = new \RecursiveIteratorIterator( new \RecursiveArrayIterator( $array ) );
		$use_keys = true;

		return iterator_to_array( $iterator, $use_keys );
	}

	/**
	 * Gets a value from a nested array using an address string.
	 *
	 * @param array  $array   An array which contains value located at `$address`.
	 * @param string|array $address The location of the value within `$array` (dot notation).
	 * @param mixed  $default Value to return if not found. Default is an empty string.
	 *
	 * @return mixed The value, if found, otherwise $default.
	 */
	public function arrayGet( $array, $address, $default = '' ) {
		$keys   = is_array( $address ) ? $address : explode( '.', $address );
		$value  = $array;

		foreach ( $keys as $key ) {
			if ( '[' === $key[0] ) {
				$index = substr( $key, 1, -1 );

				if ( is_numeric( $index ) ) {
					$key = (int) $index;
				}
			}

			if ( ! isset( $value[ $key ] ) ) {
				return $default;
			}

			$value = $value[ $key ];
		}

		return $value;
	}

	/**
	 * Wrapper for {@see self::array_get()} that sanitizes the value before returning it.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $array     An array which contains value located at `$address`.
	 * @param string $address   The location of the value within `$array` (dot notation).
	 * @param mixed  $default   Value to return if not found. Default is an empty string.
	 * @param string $sanitizer Sanitize function to use. Default is 'sanitize_text_field'.
	 *
	 * @return mixed The sanitized value if found, otherwise $default.
	 */
	public function arrayGetSanitized( $array, $address, $default = '', $sanitizer = 'sanitize_text_field' ) {
		if ( $value = $this->arrayGet( $array, $address, $default ) ) {
			$value = $sanitizer( $value );
		}

		return $value;
	}

	/**
	 * Creates a new array containing only the items that have a key or property or only the items that
	 * have a key or property that is equal to a certain value.
	 *
	 * @param array        $array   The array to pick from.
	 * @param string|array $pick_by The key or property to look for or an array mapping the key or property
	 *                              to a value to look for.
	 */
	public function arrayPick( $array, $pick_by ): array {
		if ( is_string( $pick_by ) || is_int( $pick_by ) ) {
			$this->_pick = $pick_by;
		} else if ( is_array( $pick_by ) && 1 === count( $pick_by ) ) {
			$this->_pick       = key( $pick_by );
			$this->_pick_value = array_pop( $pick_by );
		} else {
			return array();
		}

		return array_filter( $array, array( $this, '_arrayPickCallback' ) );
	}

	/**
	 * Sets a value in a nested array using an address string (dot notation)
	 *
	 * @see http://stackoverflow.com/a/9628276/419887
	 *
	 * @param array        $array The array to modify
	 * @param string|array $path  The path in the array
	 * @param mixed        $value The value to set
	 */
	public function arraySet( &$array, $path, $value ) {
		$path_parts = is_array( $path ) ? $path : explode( '.', $path );
		$current    = &$array;

		foreach ( $path_parts as $key ) {
			if ( ! is_array( $current ) ) {
				$current = array();
			}

			if ( '[' === $key[0] && is_numeric( substr( $key, 1, - 1 ) ) ) {
				$key = (int) $key;
			}

			$current = &$current[ $key ];
		}

		$current = $value;
	}

	public function arraySortBy( $array, $key_or_prop ) {
		if ( ! is_string( $key_or_prop ) && ! is_int( $key_or_prop ) ) {
			return $array;
		}

		$this->_sort_by = $key_or_prop;

		if ( $this->isAssocArray( $array ) ) {
			uasort( $array, array( $this, '_arraySortByCallback' ) );
		} else {
			usort( $array, array( $this, '_arraySortByCallback' ) );
		}

		return $array;
	}

	/**
	 * Update a nested array value found at the provided path using {@see array_merge()}.
	 *
	 * @since 1.0.0
	 *
	 * @param array $array
	 * @param $path
	 * @param $value
	 */
	public function arrayUpdate( &$array, $path, $value ): void {
		$current_value = $this->arrayGet( $array, $path, array() );

		$this->arraySet( $array, $path, array_merge( $current_value, $value ) );
	}

	/**
	 * Whether or not a string ends with a substring.
	 *
	 * @since 1.1.1
	 *
	 * @param  string  $haystack  The string to look in.
	 * @param  string  $needle    The string to look for.
	 *
	 * @return bool
	 */
	public function endsWith( string $haystack, string $needle ): bool {
		$length = strlen( $needle );

		if ( 0 === $length ) {
			return true;
		}

		return ( substr( $haystack, -$length ) === $needle );
	}

	public function ensureDirectoryExists( $path ): bool {
		if ( file_exists( $path ) ) {
			return is_dir( $path );
		}

		// Try to create the directory
		$path = $this->normalizePath( $path );

		if ( ! $this->WPFS()->mkdir( $path ) ) {
			// Walk up the tree and create any missing parent directories
			$this->ensureDirectoryExists( dirname( $path ) );
			$this->WPFS()->mkdir( $path );
		}

		return is_dir( $path );
	}

	public static function instance() {
		if ( ! self::$_instance ) {
			self::$_instance = new self;
		}

		return self::$_instance;
	}

	/**
	 * Determine if an array has any `string` keys (thus would be considered an object in JSON)
	 *
	 * @param $array
	 *
	 * @return bool
	 */
	public function isAssocArray( $array ) {
		return is_array( $array ) && count( array_filter( array_keys( $array ), 'is_string' ) ) > 0;
	}

	/**
	 * Replaces any Windows style directory separators in $path with Linux style separators.
	 * Windows actually supports both styles, even mixed together. However, its better not
	 * to mix them (especially when doing string comparisons on paths).
	 *
	 * @since 1.0.0
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public function normalizePath( $path = '' ) {
		$path = (string) $path;
		$path = str_replace( '..', '', $path );

		if ( function_exists( 'wp_normalize_path' ) ) {
			return wp_normalize_path( $path );
		}

		return str_replace( '\\', '/', $path );
	}

	/**
	 * Creates a path string using the provided arguments.
	 *
	 * Examples:
	 *   - ```
	 *      et_()->path( '/this/is', 'a', 'path' );
	 *      // Returns '/this/is/a/path'
	 *     ```
	 *   - ```
	 *      et_()->path( ['/this/is', 'a', 'path', 'to', 'file.php'] );
	 *      // Returns '/this/is/a/path/to/file.php'
	 *     ```
	 *
	 * @since 1.0.0
	 *
	 * @param string|string[] ...$parts
	 *
	 * @return string
	 */
	public function path( ...$parts ): string {
		$path  = '';

		if ( 1 === count( $parts ) && is_array( reset( $parts ) ) ) {
			$parts = array_pop( $parts );
		}

		foreach ( $parts as $part ) {
			$path .= "{$part}/";
		}

		return substr( $path, 0, -1 );
	}

	/**
	 * Whether or not a value includes another value.
	 *
	 * @param mixed  $haystack The value to look in.
	 * @param string $needle   The value to look for.
	 *
	 * @return bool
	 */
	public function includes( $haystack, $needle ) {
		if ( is_string( $haystack ) ) {
			return false !== strpos( $haystack, $needle );
		}

		if ( is_object( $haystack ) ) {
			return property_exists( $haystack, $needle );
		}

		if ( is_array( $haystack ) ) {
			return in_array( $needle, $haystack );
		}

		return false;
	}

	public function sanitizeTextFields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return sanitize_text_field( $fields );
		}

		$result = array();

		foreach ( $fields as $field_id => $field_value ) {
			$field_id = sanitize_text_field( $field_id );

			if ( is_array( $field_value ) ) {
				$field_value = $this->sanitizeTextFields( $field_value );
			} else {
				$field_value = sanitize_text_field( $field_value );
			}

			$result[ $field_id ] = $field_value;
		}

		return $result;
	}

	/**
	 * Recursively traverses an array and escapes the keys and values according to passed escaping function.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $values            The array to be recursively escaped.
	 * @param string $escaping_function The escaping function to be used on keys and values. Default 'esc_html'. Optional.
	 *
	 * @return array
	 */

	public function escArray( $values, $escaping_function = 'esc_html' ) {
		if ( ! is_array( $values ) ) {
			return $escaping_function( $values );
		}

		$result = array();

		foreach ( $values as $key => $value ) {
			$key = $escaping_function( $key );

			if ( is_array( $value ) ) {
				$value = $this->escArray( $value, $escaping_function );
			} else {
				$value = $escaping_function( $value );
			}

			$result[ $key ] = $value;
		}

		return $result;
	}

	/**
	 * Pass-through function for acknowledging a previously sanitized value
	 *
	 * @since 1.1.2
	 *
	 * @param mixed $value Value that has already been sanitized during the current request
	 *
	 * @return mixed
	 */
	public function sanitizedPreviously( $value ) {
		return $value;
	}

	/**
	 * Whether or not a string starts with a substring.
	 *
	 * @since 1.0.0
	 *
	 * @param string $string
	 * @param string $substring
	 *
	 * @return bool
	 */
	public function startsWith( $string, $substring ) {
		return 0 === strpos( $string, $substring );
	}

	/**
	 * Convert string to camel case format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $string Original string data.
	 * @param array  $no_strip Additional regex pattern exclusion.
	 *
	 * @return string
	 */
	public function camelCase( $string, $no_strip = array() ) {
		$words = preg_split( '/[^a-zA-Z0-9' . implode( '', $no_strip ) . ']+/i', strtolower( $string ) );

		if ( count( $words ) === 1 ) {
			return $words[0];
		}

		$camel_cased = implode( '', array_map( 'ucwords', $words ) );

		$camel_cased[0] = strtolower( $camel_cased[0] );

		return $camel_cased;
	}

	/**
	 * Equivalent of usort but preserves relative order of equally weighted values.
	 *
	 * @since 1.0.0
	 *
	 * @param array &$array
	 * @param callable $comparison_function
	 *
	 * @return array
	 */
	public function usort( &$array, $comparison_function ) {
		return $this->_userSort( $array, 'usort', $comparison_function );
	}

	/**
	 * Equivalent of uasort but preserves relative order of equally weighted values.
	 *
	 * @since 1.0.0
	 *
	 * @param array &$array
	 * @param callable $comparison_function
	 *
	 * @return array
	 */
	public function uasort( &$array, $comparison_function ) {
		return $this->_userSort( $array, 'uasort', $comparison_function );
	}

	/**
	 * Equivalent of uksort but preserves relative order of equally weighted values.
	 *
	 * @since 1.0.0
	 *
	 * @param array &$array
	 * @param callable $comparison_function
	 *
	 * @return array
	 */
	public function uksort( &$array, $comparison_function ) {
		return $this->_userSort( $array, 'uksort', $comparison_function );
	}
}
