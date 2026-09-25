<?php
/**
 * Plugin Name: Atmos Cookie Tracker
 * Description: Captures First & Last Touch UTMs and Ad IDs into LocalStorage/Cookies.
 * Version: 1.2
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

function atm_enqueue_tracking_script() {
    wp_enqueue_script(
        'atm-tracker',
        plugins_url( 'js/atmos-tracker.js', __FILE__ ),
        array(),
        '1.2',
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
