<?php
/**
 * Fluent Forms Adapter
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fluent_Forms_Adapter
 */
class Fluent_Forms_Adapter {

    	/**
	 * Custom logger that always works.
	 */
	private function log( $message ) {
        return; // Disable logging for now

        $log_file = dirname(__FILE__) . '/atmos-debug.log';
		$timestamp = date('Y-m-d H:i:s');
		file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND);
		
		// Also try error_log if WP_DEBUG is enabled
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			// error_log('ATMOS: ' . $message);
		}
	}

	/**
	 * Register hooks.
	 */
	public function __construct(){
        // Hidden fields are added client-side by atmos-tracker.js, only for captured params.
        // Inject data before Fluent Forms processes submission
        add_filter('fluentform/insert_response_data', array($this, 'ff_insert_response_data'), 10, 3);
	}


	/**
	 * Get form field keys that match the JS mapping.
	 *
	 * @return array
	 */
	public function get_field_mapping() {
		
		$keys = array();
		foreach ( atmos_get_param_map() as $param ) {
			$keys[] = 'first_' . $param;
			$keys[] = 'last_' . $param;
		}

		return $keys;
	}

    public function get_field_mapping_for_form() {
        $field_names = $this->get_field_mapping();
        $fields = array();
        foreach ( $field_names as $field_name ) {
            $fields[] = array(
                'type' => 'hidden',
                'name' => $field_name,
            );
        }
        return $fields;
    }

    
	/**
	 * Inject attribution data into form submission data.
	 * This runs before Fluent Forms validates/stores the data.
	 *
	 * @param array $formData Submission data.
	 * @param array $form Form object.
	 * @param array $inputConfigs Field configurations.
	 * @return array
	 */
	public function ff_insert_response_data($formData, $form, $inputConfigs) {
		$this->log('=== insert_response_data filter fired ===');
		$this->log('Form data keys: ' . implode(', ', array_keys($formData)));
		$this->log('POST keys: ' . implode(', ', array_keys($_POST)));
		
		if ( ! is_array( $formData ) ) {
			return $formData;
		}

		// Fluent Forms sends data via AJAX in $_POST['data'] as URL-encoded string
		$posted_data = array();
		if ( isset( $_POST['data'] ) ) {
			$this->log('Found $_POST[data]: ' . substr($_POST['data'], 0, 200));
			// Parse URL-encoded data
			parse_str(wp_unslash($_POST['data']), $posted_data);
			if (is_array($posted_data)) {
				$this->log('Parsed POST data keys: ' . implode(', ', array_keys($posted_data)));
			}
		}

		// Server-side fallback for when the JS didn't populate the form
		$cookie_data = atmos_get_attribution();
		$added_count = 0;

		foreach ( array( 'first', 'last' ) as $touch ) {
			foreach ( atmos_get_param_map() as $param ) {
				$key = $touch . '_' . $param;

				// Check in parsed data first, then direct POST, then the cookie
				$value = null;
				if ( ! empty( $posted_data[ $key ] ) && is_scalar( $posted_data[ $key ] ) ) {
					$value = $posted_data[ $key ];
				} elseif ( ! empty( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ) {
					$value = wp_unslash( $_POST[ $key ] );
				} elseif ( ! empty( $cookie_data[ $touch ][ $param ] ) ) {
					$value = $cookie_data[ $touch ][ $param ];
				}

				if ( $value ) {
					$value = 'referrer' === $param ? esc_url_raw( $value ) : sanitize_text_field( $value );
				}

				// Only store parameters that were actually captured
				if ( $value ) {
					$formData[ $key ] = $value;
					$added_count++;
				}
			}
		}
		
		$this->log('Added ' . $added_count . ' attribution fields to form data');
		return $formData;
	}


	/**
	 * TODO: Add Render attribution data section on entry view page.
	 *
	 * @param object $submission Submission object.
	 * @param object $form Form object.
	 * @param array $feed Feed data.
	 */
	public function ff_render_attribution_section($submission, $form, $feed) {
		$this->log('=== submission_data_rendered action fired ===');
		
		// Get the response data
		$response = json_decode($submission->response, true);
		if (!is_array($response)) {
			return;
		}

		$keys = $this->get_field_mapping();
		$attribution_data = array();

		foreach ($keys as $key) {
			if (isset($response[$key]) && !empty($response[$key])) {
				$attribution_data[$key] = $response[$key];
			}
		}

		if (empty($attribution_data)) {
			return;
		}

		// Display the attribution data
		echo '<div class="ff_all_data" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">';
		echo '<h4 style="margin-top: 0;">Attribution Data</h4>';
		echo '<table class="wp-list-table widefat striped">';
		
		foreach ($attribution_data as $key => $value) {
			$label = ucwords(str_replace('_', ' ', $key));
			echo '<tr>';
			echo '<th style="width: 30%; text-align: left;">' . esc_html($label) . '</th>';
			echo '<td>' . esc_html($value) . '</td>';
			echo '</tr>';
		}
		
		echo '</table>';
		echo '</div>';
		
		$this->log('Rendered attribution section with ' . count($attribution_data) . ' fields');
	}

}

$fluentForms = new Fluent_Forms_Adapter();