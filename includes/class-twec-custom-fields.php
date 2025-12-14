<?php
/**
 * Custom fields functionality for events.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
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
		register_setting( 'twec_custom_fields', 'twec_custom_fields_config' );
	}

	/**
	 * Add custom fields meta box.
	 */
	public function add_custom_fields_meta_box() {
		add_meta_box(
			'twec_custom_fields',
			__( 'Custom Fields', 'the-wordpress-event-calendar' ),
			array( $this, 'custom_fields_meta_box_callback' ),
			'twec_event',
			'normal',
			'default'
		);
	}

	/**
	 * Custom fields meta box callback.
	 */
	public function custom_fields_meta_box_callback( $post ) {
		wp_nonce_field( 'twec_save_custom_fields', 'twec_custom_fields_nonce' );
		
		$config = get_option( 'twec_custom_fields_config', array() );
		$custom_fields = get_post_meta( $post->ID, '_twec_custom_fields', true );
		$custom_fields = $custom_fields ? $custom_fields : array();
		
		if ( empty( $config ) ) {
			echo '<p>' . __( 'No custom fields configured. Go to Events > Settings > Custom Fields to add custom fields.', 'the-wordpress-event-calendar' ) . '</p>';
			return;
		}
		
		echo '<table class="form-table">';
		foreach ( $config as $field ) {
			$field_id = sanitize_key( $field['name'] );
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
								<option value=""><?php _e( 'Select...', 'the-wordpress-event-calendar' ); ?></option>
								<?php
								$options = explode( "\n", $field['options'] );
								foreach ( $options as $option ) {
									$option = trim( $option );
									if ( empty( $option ) ) continue;
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
	 */
	public function save_custom_fields( $post_id ) {
		if ( ! isset( $_POST['twec_custom_fields_nonce'] ) || ! wp_verify_nonce( $_POST['twec_custom_fields_nonce'], 'twec_save_custom_fields' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'twec_event' !== get_post_type( $post_id ) ) {
			return;
		}

		if ( isset( $_POST['twec_custom_fields'] ) && is_array( $_POST['twec_custom_fields'] ) ) {
			$custom_fields = array();
			foreach ( $_POST['twec_custom_fields'] as $key => $value ) {
				$custom_fields[ sanitize_key( $key ) ] = sanitize_text_field( $value );
			}
			update_post_meta( $post_id, '_twec_custom_fields', $custom_fields );
		}
	}

	/**
	 * Get custom field value.
	 */
	public static function get( $event_id, $field_name, $default = '' ) {
		$custom_fields = get_post_meta( $event_id, '_twec_custom_fields', true );
		if ( ! is_array( $custom_fields ) ) {
			return $default;
		}
		$field_id = sanitize_key( $field_name );
		return isset( $custom_fields[ $field_id ] ) ? $custom_fields[ $field_id ] : $default;
	}
}

new TWEC_Custom_Fields();

