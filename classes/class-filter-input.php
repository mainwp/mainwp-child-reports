<?php
/** MainWP Child Reports filter input. */

namespace WP_MainWP_Stream;

/**
 * Class Filter_Input.
 *
 * @package WP_MainWP_Stream
 */
class Filter_Input {

	/** @var array $filter_callbacks Array of filter callbacks. */
	public static $filter_callbacks = array(
		FILTER_DEFAULT                     => null,
		// Validate.
		FILTER_VALIDATE_BOOLEAN            => 'is_bool',
		FILTER_VALIDATE_EMAIL              => 'is_email',
		FILTER_VALIDATE_FLOAT              => 'is_float',
		FILTER_VALIDATE_INT                => 'is_int',
		FILTER_VALIDATE_IP                 => array( __CLASS__, 'is_ip_address' ),
		FILTER_VALIDATE_REGEXP             => array( __CLASS__, 'is_regex' ),
		FILTER_VALIDATE_URL                => 'wp_http_validate_url',
		// Sanitize.
		FILTER_SANITIZE_EMAIL              => 'sanitize_email',
		FILTER_SANITIZE_ENCODED            => 'esc_url_raw',
		FILTER_SANITIZE_NUMBER_FLOAT       => 'floatval',
		FILTER_SANITIZE_NUMBER_INT         => 'intval',
		FILTER_SANITIZE_SPECIAL_CHARS      => 'htmlspecialchars',
		FILTER_SANITIZE_FULL_SPECIAL_CHARS => 'sanitize_text_field',
		FILTER_SANITIZE_URL                => 'esc_url_raw',
		// Other.
		FILTER_UNSAFE_RAW                  => null,
	);

	/**
	 * Input type checker.
	 *
	 * @param $type
	 * @param $variable_name
	 * @param null          $filter
	 * @param array         $options
	 * @return array|bool|mixed|null
	 * @throws \Exception
	 */
	public static function super( $type, $variable_name, $filter = null, $options = array() ) {
		$super = null;

		// @codingStandardsIgnoreStart
		switch ( $type ) {
			case INPUT_POST :
				$super = $_POST;
				break;
			case INPUT_GET :
				$super = $_GET;
				break;
			case INPUT_COOKIE :
				$super = $_COOKIE;
				break;
			case INPUT_ENV :
				$super = $_ENV;
				break;
			case INPUT_SERVER :
				$super = $_SERVER;
				break;
		}
		// @codingStandardsIgnoreEnd

		if ( is_null( $super ) ) {
			throw new \Exception( esc_html__( 'Invalid use, type must be one of INPUT_* family.', 'mainwp-child-reports' ) );
		}

		$var = isset( $super[ $variable_name ] ) ? $super[ $variable_name ] : null;
		$var = self::filter( $var, $filter, $options );

		return $var;
	}

	/**
	 * Filter & sanitiser.
	 *
	 * @param $var
	 * @param null  $filter
	 * @param array $options
	 * @return array|bool|mixed|null
	 * @throws \Exception
	 */
	public static function filter( $var, $filter = null, $options = array() ) {

		// Default filter is a sanitizer, not validator
		$filter_type = 'sanitizer';

		// Only filter value if it is not null
		if ( isset( $var ) && $filter && FILTER_DEFAULT !== $filter ) {
			if ( ! isset( self::$filter_callbacks[ $filter ] ) ) {
				throw new \Exception( esc_html__( 'Filter not supported.', 'mainwp-child-reports' ) );
			}

			$filter_callback = self::$filter_callbacks[ $filter ];
			$result          = call_user_func( $filter_callback, $var );

			/**
			 * filter_var / filter_input treats validation/sanitization filters the same.
			 * They both return output and change the var value, this shouldn't be the case here.
			 * We'll do a boolean check on validation function, and let sanitizers change the value.
			 */
			$filter_type = ( $filter < 500 ) ? 'validator' : 'sanitizer';
			if ( 'validator' === $filter_type ) { // Validation functions
				if ( ! $result ) {
					$var = false;
				}
			} else { // Santization functions
				$var = $result;
			}
		}

		// Detect FILTER_REQUIRE_ARRAY flag.
		if ( isset( $var ) && is_int( $options ) && FILTER_REQUIRE_ARRAY === $options ) {
			if ( ! is_array( $var ) ) {
				$var = ( 'validator' === $filter_type ) ? false : null;
			}
		}

		// Polyfill the `default` attribute only, for now.
		if ( is_array( $options ) && ! empty( $options['options']['default'] ) ) {
			if ( 'validator' === $filter_type && false === $var ) {
				$var = $options['options']['default'];
			} elseif ( 'sanitizer' === $filter_type && null === $var ) {
				$var = $options['options']['default'];
			}
		}

		return $var;
	}

	/**
	 * Check if subject is equal to $var.
	 *
	 * @param string $var Pregmatch pattern.
	 * @return bool Return FALSE if $test equals false.
	 */
	public static function is_regex( $var ) {
		// @codingStandardsIgnoreStart
		$test = @preg_match( $var, '' );
		// @codingStandardsIgnoreEnd

		return false !== $test;
	}

	/**
	 * Check for IP address.
	 *
	 * @param string $var String to check for IP address.
	 *
	 * @return bool Return FASLE if not an IP address.
	 */
	public static function is_ip_address( $var ) {
		return false !== \WP_Http::is_ip_address( $var );
	}
}
