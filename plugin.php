<?php
/**
 * Plugin Name: Atmos Cookie Tracker
 * Description: Captures First & Last Touch UTMs and Ad IDs into LocalStorage/Cookies.
 * Version: 1.1
 * Author: Dustin Wight
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function atm_enqueue_tracking_script() {
    wp_enqueue_script(
        'atm-tracker',
        plugins_url( 'js/atmos-tracker.js', __FILE__ ),
        array(),
        '1.1',
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