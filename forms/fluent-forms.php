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

		// SmartCodes: {atmos_<field>} per value and {atmos_referrer_attribution} for the full URL
		// all_editor_shortcodes feeds the form settings/integration pickers. Not editor_shortcodes:
		// that's the form builder's list (default values), where no entry exists to resolve them.
		add_filter( 'fluentform/all_editor_shortcodes', array( $this, 'ff_editor_smartcodes' ) );
		foreach ( array_keys( $this->get_smartcodes() ) as $code ) {
			add_filter( 'fluentform/shortcode_parser_callback_' . $code, function () use ( $code ) {
				return $this->ff_smartcode_value( $code );
			} );
		}
	}

	/**
	 * SmartCode name => editor label. Labels are compact ("Last: utm_source") to fit
	 * the narrow SmartCode dropdown.
	 *
	 * @return array
	 */
	public function get_smartcodes() {
		$codes = array( 'atmos_referrer_attribution' => 'Full URL' );
		foreach ( array( 'last' => 'Last', 'first' => 'First' ) as $touch => $touch_label ) {
			foreach ( atmos_get_param_map() as $param ) {
				$codes[ 'atmos_' . atmos_get_field_name( $touch, $param ) ] = $touch_label . ': ' . $param;
			}
		}

		return $codes;
	}

	/**
	 * List the SmartCodes in the form settings pickers (feed field mapping, notifications, confirmations).
	 *
	 * @param array $groups SmartCode groups.
	 * @return array
	 */
	public function ff_editor_smartcodes( $groups ) {
		$shortcodes = array();
		foreach ( $this->get_smartcodes() as $code => $label ) {
			$shortcodes[ '{' . $code . '}' ] = $label;
		}

		$groups[] = array(
			'title'      => 'Atmos Attribution',
			'shortcodes' => $shortcodes,
		);

		return $groups;
	}

	/**
	 * Resolve a SmartCode from the submission being parsed. Reads the entry data, not
	 * the cookie: feeds usually run asynchronously, without the visitor's request.
	 *
	 * @param string $code SmartCode name without braces.
	 * @return string '' when not captured.
	 */
	public function ff_smartcode_value( $code ) {
		$parser = '\FluentForm\App\Services\FormBuilder\ShortCodeParser';

		$attribution = atmos_get_attribution_from_fields( $parser::getInputs() );
		if ( empty( $attribution ) ) {
			try {
				$entry = $parser::getEntry();
				if ( $entry && ! empty( $entry->response ) ) {
					$attribution = atmos_get_attribution_from_fields( json_decode( $entry->response, true ) );
				}
			} catch ( \Throwable $e ) {
				// No entry in this context (e.g. editor preview)
			}
		}

		if ( 'atmos_referrer_attribution' === $code ) {
			return atmos_build_attribution_url( $attribution );
		}

		$field = substr( $code, strlen( 'atmos_' ) );
		foreach ( array( 'first', 'last' ) as $touch ) {
			foreach ( atmos_get_param_map() as $param ) {
				if ( atmos_get_field_name( $touch, $param ) === $field ) {
					return isset( $attribution[ $touch ][ $param ] ) ? $attribution[ $touch ][ $param ] : '';
				}
			}
		}

		return '';
	}


	/**
	 * Get form field keys that match the JS mapping.
	 *
	 * @return array
	 */
	public function get_field_mapping() {
		
		$keys = array();
		foreach ( atmos_get_param_map() as $param ) {
			$keys[] = atmos_get_field_name( 'first', $param );
			$keys[] = atmos_get_field_name( 'last', $param );
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

		// Collect posted values (parsed data first, then direct POST)
		$values = array();
		foreach ( array( 'first', 'last' ) as $touch ) {
			foreach ( atmos_get_param_map() as $param ) {
				$key = atmos_get_field_name( $touch, $param );
				if ( ! empty( $posted_data[ $key ] ) && is_scalar( $posted_data[ $key ] ) ) {
					$values[ $key ] = array( $param, $posted_data[ $key ] );
				} elseif ( ! empty( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ) {
					$values[ $key ] = array( $param, wp_unslash( $_POST[ $key ] ) );
				}
			}
		}

		// Cookie fallback only when the JS posted nothing. Never mix sources, or
		// stale cookie values fill gaps in a newer touch.
		if ( empty( $values ) ) {
			foreach ( atmos_get_attribution() as $touch => $params ) {
				foreach ( $params as $param => $value ) {
					$values[ atmos_get_field_name( $touch, $param ) ] = array( $param, $value );
				}
			}
		}

		$added_count = 0;

		foreach ( $values as $key => $entry ) {
			list( $param, $value ) = $entry;
			$value = 'referrer' === $param ? atmos_clean_referrer( $value ) : sanitize_text_field( $value );

			// Only store parameters that were actually captured
			if ( $value ) {
				$formData[ $key ] = $value;
				$added_count++;
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