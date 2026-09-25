<?php
/**
 * Plugin Name: Atmos Cookie Tracker
 * Description: Captures First & Last Touch UTMs and Ad IDs into LocalStorage/Cookies.
 * Version: 1.3.0
 * Author: Dustin Wight
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'ATMOS_COOKIE_KEY', '_atmos_attribution' );

/**
 * Storage key (as used in the cookie JSON) => public param name.
 * Must match the `params` map in js/atmos-tracker.js.
 *
 * @return array
 */
function atmos_get_param_map() {
    return array(
        'src'      => 'utm_source',
        'mdm'      => 'utm_medium',
        'cmp'      => 'utm_campaign',
        'trm'      => 'utm_term',
        'cnt'      => 'utm_content',
        'gclid'    => 'gclid',
        'gbraid'   => 'gbraid',
        'wbraid'   => 'wbraid',
        'msclkid'  => 'msclkid',
        'fbclid'   => 'fbclid',
        'referrer' => 'referrer',
    );
}

/**
 * Form field name for a touch/param pair. Last touch uses the plain param name
 * (e.g. utm_source) so it works with CRMs expecting a single set; first touch is
 * prefixed (first_utm_source). Must match fillForm() in js/atmos-tracker.js.
 *
 * @param string $touch 'first' or 'last'.
 * @param string $param Public param name, e.g. 'utm_source'.
 * @return string
 */
function atmos_get_field_name( $touch, $param ) {
    return 'last' === $touch ? $param : $touch . '_' . $param;
}

/**
 * Reduce a referrer to origin + path. The query string and fragment belong to the
 * referring site (its own UTMs, search terms, possible PII) and are dropped.
 *
 * @param string $url Referrer URL.
 * @return string Cleaned URL, or '' if not a valid http(s) URL.
 */
function atmos_clean_referrer( $url ) {
    $parts = wp_parse_url( $url );
    if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
        return '';
    }

    $clean = strtolower( $parts['scheme'] ) . '://' . $parts['host']
        . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
        . ( isset( $parts['path'] ) ? $parts['path'] : '/' );

    return esc_url_raw( $clean );
}

/**
 * Read attribution from the tracking cookie, keyed by public param name.
 * Only parameters that were actually captured are included.
 *
 * Example: array( 'first' => array( 'utm_source' => 'google' ), 'last' => array( ... ) )
 *
 * @return array Empty array when no attribution is stored.
 */
function atmos_get_attribution() {
    if ( empty( $_COOKIE[ ATMOS_COOKIE_KEY ] ) ) {
        return array();
    }

    $raw = json_decode( wp_unslash( $_COOKIE[ ATMOS_COOKIE_KEY ] ), true );
    if ( ! is_array( $raw ) ) {
        return array();
    }

    // Until a second touch arrives only `last` is stored; it is also the first touch
    if ( empty( $raw['first'] ) && ! empty( $raw['last'] ) ) {
        $raw['first'] = $raw['last'];
    }

    $result = array();
    foreach ( array( 'first', 'last' ) as $touch ) {
        if ( empty( $raw[ $touch ] ) || ! is_array( $raw[ $touch ] ) ) {
            continue;
        }
        foreach ( atmos_get_param_map() as $key => $param ) {
            if ( isset( $raw[ $touch ][ $key ] ) && is_scalar( $raw[ $touch ][ $key ] ) && '' !== $raw[ $touch ][ $key ] ) {
                $value = 'referrer' === $param
                    ? atmos_clean_referrer( $raw[ $touch ][ $key ] )
                    : sanitize_text_field( $raw[ $touch ][ $key ] );
                if ( '' !== $value ) {
                    $result[ $touch ][ $param ] = $value;
                }
            }
        }
    }

    return $result;
}

/**
 * Rebuild attribution from submitted form fields (e.g. a stored entry), in the same
 * shape as atmos_get_attribution(). Field names follow atmos_get_field_name().
 *
 * @param array $fields Submitted data keyed by field name.
 * @return array
 */
function atmos_get_attribution_from_fields( $fields ) {
    $result = array();
    if ( ! is_array( $fields ) ) {
        return $result;
    }

    foreach ( array( 'first', 'last' ) as $touch ) {
        foreach ( atmos_get_param_map() as $param ) {
            $key = atmos_get_field_name( $touch, $param );
            if ( isset( $fields[ $key ] ) && is_scalar( $fields[ $key ] ) && '' !== (string) $fields[ $key ] ) {
                $result[ $touch ][ $param ] = (string) $fields[ $key ];
            }
        }
    }

    return $result;
}

/**
 * Attribution as a single URL: the last-touch referrer (or the site's home URL when
 * there was none) with every other captured value as query params, named as form
 * fields (utm_source, gclid, first_utm_source, first_referrer, ...).
 *
 * Example: https://www.google.com/?utm_source=google&utm_medium=cpc&gclid=abc&first_utm_source=meta
 *
 * @param array $attribution As returned by atmos_get_attribution() or atmos_get_attribution_from_fields().
 * @return string '' when there is no attribution.
 */
function atmos_build_attribution_url( $attribution ) {
    if ( empty( $attribution ) ) {
        return '';
    }

    // Stored referrers are already reduced to origin + path, so a query can be appended
    $base = ! empty( $attribution['last']['referrer'] ) ? $attribution['last']['referrer'] : home_url( '/' );

    $query = array();
    foreach ( array( 'last', 'first' ) as $touch ) {
        foreach ( atmos_get_param_map() as $param ) {
            if ( ( 'last' === $touch && 'referrer' === $param ) || empty( $attribution[ $touch ][ $param ] ) ) {
                continue;
            }
            $query[ atmos_get_field_name( $touch, $param ) ] = $attribution[ $touch ][ $param ];
        }
    }

    return $query ? $base . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : $base;
}

function atm_enqueue_tracking_script() {
    wp_enqueue_script(
        'atm-tracker',
        plugins_url( 'js/atmos-tracker.min.js', __FILE__ ),
        array(),
        '1.3.0',
        true // Load in footer for better performance
    );
}
add_action( 'wp_enqueue_scripts', 'atm_enqueue_tracking_script' );

add_action( 'plugins_loaded', function() {
    // Check for Fluent Forms and initialize adapter
    if ( class_exists( 'FluentForm' ) || defined( 'FLUENTFORM' ) ) {
        require_once plugin_dir_path( __FILE__ ) . '/forms/fluent-forms.php';
    }
} );
