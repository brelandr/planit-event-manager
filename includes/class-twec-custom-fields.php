<?php
/**
 * Custom fields functionality for events.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-twec-custom-fields.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom fields functionality for events.
 *
 * Handles custom field configuration and meta box display for events.
 */
class TWEC_Custom_Fields {

	/**
	 * Initialize custom fields.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_custom_fields_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_custom_fields' ) );
		add_action( 'admin_init', array( $this, 'register_custom_fields_settings' ) );
	}

	/**
	 * Register custom fields settings.
	 */
	public function register_custom_fields_settings() {
		register_setting(
			'twec_custom_fields',
			'twec_custom_fields_config',
			array(
				'sanitize_callback' => array( $this, 'sanitize_custom_fields_config' ),
			)
		);
	}

	/**
	 * Sanitize custom fields configuration.
	 *
	 * @param array $input Custom fields configuration input.
	 * @return array Sanitized configuration.
	 */
	public function sanitize_custom_fields_config( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $input as $key => $value ) {
			$sanitized[ sanitize_key( $key ) ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( $value );
		}

		return $sanitized;
	}

	/**
	 * Add custom fields meta box.
	 */
	public function add_custom_fields_meta_box() {
		add_meta_box(
			'twec_custom_fields',
			__( 'Custom Fields', 'planit-event-manager' ),
			array( $this, 'custom_fields_meta_box_callback' ),
			'twec_event',
			'normal',
			'default'
		);
	}

	/**
	 * Custom fields meta box callback.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function custom_fields_meta_box_callback( $post ) {
		wp_nonce_field( 'twec_save_custom_fields', 'twec_custom_fields_nonce' );

		$config        = get_option( 'twec_custom_fields_config', array() );
		$custom_fields = get_post_meta( $post->ID, '_twec_custom_fields', true );
		$custom_fields = $custom_fields ? $custom_fields : array();

		if ( empty( $config ) ) {
			echo '<p>' . esc_html__( 'No custom fields configured. Go to Events > Settings > Custom Fields to add custom fields.', 'planit-event-manager' ) . '</p>';
			return;
		}

		echo '<table class="form-table">';
		foreach ( $config as $field ) {
			$field_id    = sanitize_key( $field['name'] );
			$field_value = isset( $custom_fields[ $field_id ] ) ? $custom_fields[ $field_id ] : '';
			?>
			<tr>
				<th><label for="twec_cf_<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
				<td>
					<?php
					switch ( $field['type'] ) {
						case 'text':
							?>
							<input type="text" id="twec_cf_<?php echo esc_attr( $field_id ); ?>" name="twec_custom_fields[<?php echo esc_attr( $field_id ); ?>]" value="<?php echo esc_attr( $field_value ); ?>" class="regular-text" />
							<?php
							break;
						case 'textarea':
							?>
							<textarea id="twec_cf_<?php echo esc_attr( $field_id ); ?>" name="twec_custom_fields[<?php echo esc_attr( $field_id ); ?>]" class="large-text" rows="4"><?php echo esc_textarea( $field_value ); ?></textarea>
							<?php
							break;
						case 'number':
							?>
							<input type="number" id="twec_cf_<?php echo esc_attr( $field_id ); ?>" name="twec_custom_fields[<?php echo esc_attr( $field_id ); ?>]" value="<?php echo esc_attr( $field_value ); ?>" class="small-text" />
							<?php
							break;
						case 'url':
							?>
							<input type="url" id="twec_cf_<?php echo esc_attr( $field_id ); ?>" name="twec_custom_fields[<?php echo esc_attr( $field_id ); ?>]" value="<?php echo esc_url( $field_value ); ?>" class="regular-text" />
							<?php
							break;
						case 'email':
							?>
							<input type="email" id="twec_cf_<?php echo esc_attr( $field_id ); ?>" name="twec_custom_fields[<?php echo esc_attr( $field_id ); ?>]" value="<?php echo esc_attr( $field_value ); ?>" class="regular-text" />
							<?php
							break;
						case 'select':
							?>
							<select id="twec_cf_<?php echo esc_attr( $field_id ); ?>" name="twec_custom_fields[<?php echo esc_attr( $field_id ); ?>]">
								<option value=""><?php esc_html_e( 'Select...', 'planit-event-manager' ); ?></option>
								<?php
								$options = explode( "\n", $field['options'] );
								foreach ( $options as $option ) {
									$option = trim( $option );
									if ( empty( $option ) ) {
										continue;
									}
									?>
									<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $field_value, $option ); ?>><?php echo esc_html( $option ); ?></option>
									<?php
								}
								?>
							</select>
							<?php
							break;
						case 'checkbox':
							?>
							<label>
								<input type="checkbox" id="twec_cf_<?php echo esc_attr( $field_id ); ?>" name="twec_custom_fields[<?php echo esc_attr( $field_id ); ?>]" value="1" <?php checked( $field_value, '1' ); ?> />
								<?php echo esc_html( $field['label'] ); ?>
							</label>
							<?php
							break;
					}
					?>
					<?php if ( ! empty( $field['description'] ) ) : ?>
						<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<?php
		}
		echo '</table>';
	}

	/**
	 * Save custom fields.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_custom_fields( $post_id ) {
		if ( ! twec_verify_post_nonce_field( 'twec_custom_fields_nonce', 'twec_save_custom_fields' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( 'twec_event' !== get_post_type( $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified earlier in function
		if ( isset( $_POST['twec_custom_fields'] ) && is_array( $_POST['twec_custom_fields'] ) ) {
			$post_fields = wp_unslash( $_POST['twec_custom_fields'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below
			$config      = get_option( 'twec_custom_fields_config', array() );

			$existing = get_post_meta( $post_id, '_twec_custom_fields', true );
			$existing = is_array( $existing ) ? $existing : array();

			$merged         = $existing;
			$controlled_ids = array();

			if ( is_array( $config ) ) {
				foreach ( $config as $field ) {
					if ( ! is_array( $field ) ) {
						continue;
					}
					$fid    = isset( $field['name'] ) ? sanitize_key( (string) $field['name'] ) : '';
					$f_type = isset( $field['type'] ) ? (string) $field['type'] : '';
					if ( '' === $fid ) {
						continue;
					}

					$controlled_ids[ $fid ] = true;

					if ( 'checkbox' === $f_type ) {
						$merged[ $fid ] = isset( $post_fields[ $fid ] ) && '1' === (string) $post_fields[ $fid ] ? '1' : '0';
						continue;
					}

					if ( ! isset( $post_fields[ $fid ] ) ) {
						continue;
					}

					$raw = $post_fields[ $fid ];
					if ( 'textarea' === $f_type ) {
						$merged[ $fid ] = sanitize_textarea_field( $raw );
					} elseif ( 'url' === $f_type ) {
						$merged[ $fid ] = esc_url_raw( (string) $raw );
					} elseif ( 'email' === $f_type ) {
						$merged[ $fid ] = sanitize_email( (string) $raw );
					} elseif ( 'number' === $f_type ) {
						$merged[ $fid ] = is_numeric( $raw ) ? (string) (float) $raw : '';
					} else {
						$merged[ $fid ] = sanitize_text_field( (string) $raw );
					}
				}
			}

			foreach ( $post_fields as $key => $value ) {
				$kid = sanitize_key( (string) $key );
				if ( '' === $kid || isset( $controlled_ids[ $kid ] ) ) {
					continue;
				}
				$merged[ $kid ] = sanitize_text_field( (string) $value );
			}

			update_post_meta( $post_id, '_twec_custom_fields', $merged );
		}
	}

	/**
	 * Get custom field value.
	 *
	 * @param int    $event_id   Event ID.
	 * @param string $field_name Field name.
	 * @param string $default_value Default value.
	 * @return string Field value or default.
	 */
	public static function get( $event_id, $field_name, $default_value = '' ) {
		$custom_fields = get_post_meta( $event_id, '_twec_custom_fields', true );
		if ( ! is_array( $custom_fields ) ) {
			return $default_value;
		}
		$field_id = sanitize_key( $field_name );
		return isset( $custom_fields[ $field_id ] ) ? $custom_fields[ $field_id ] : $default_value;
	}
}

// TWEC_Custom_Fields is initialized by TWEC class.

