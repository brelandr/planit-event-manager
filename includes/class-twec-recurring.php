<?php
/**
 * Recurring events functionality.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TWEC_RRule_Expand' ) ) {
	$_dir = defined( 'PLANIT_EVENT_MANAGER_DIR' ) ? PLANIT_EVENT_MANAGER_DIR : ( defined( 'TWEC_PLUGIN_DIR' ) ? TWEC_PLUGIN_DIR : '' );
	if ( $_dir && is_readable( $_dir . 'includes/twec-rrule.php' ) ) {
		require_once $_dir . 'includes/twec-rrule.php';
	}
}

/**
 * Recurring events class.
 *
 * Handles recurring event functionality.
 */
class TWEC_Recurring {

	/**
	 * Initialize recurring events.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_recurring_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_recurring_meta' ) );
		add_action( 'wp', array( $this, 'generate_recurring_instances' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_rrule_preview' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 15 );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_rrule_notice' ) );
	}

	/**
	 * Add recurring events meta box.
	 */
	public function add_recurring_meta_box() {
		add_meta_box(
			'twec_recurring',
			__( 'Recurring Event', 'planit-event-manager' ),
			array( $this, 'recurring_meta_box_callback' ),
			'twec_event',
			'side',
			'default'
		);
	}

	/**
	 * Recurring meta box callback.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function recurring_meta_box_callback( $post ) {
		wp_nonce_field( 'twec_save_recurring', 'twec_recurring_nonce' );

		$is_recurring        = get_post_meta( $post->ID, '_twec_is_recurring', true );
		$recurrence_type     = get_post_meta( $post->ID, '_twec_recurrence_type', true );
		$recurrence_interval = get_post_meta( $post->ID, '_twec_recurrence_interval', true );
		$recurrence_end_date = get_post_meta( $post->ID, '_twec_recurrence_end_date', true );
		$recurrence_count    = get_post_meta( $post->ID, '_twec_recurrence_count', true );
		$advanced            = get_post_meta( $post->ID, '_twec_recurrence_advanced', true );
		$rrule               = get_post_meta( $post->ID, '_twec_recurrence_rrule', true );
		$exdates             = get_post_meta( $post->ID, '_twec_recurrence_exdates', true );
		?>
		<p>
			<label>
				<input type="checkbox" id="twec_is_recurring" name="twec_is_recurring" value="1" <?php checked( $is_recurring, '1' ); ?> />
				<?php esc_html_e( 'This is a recurring event', 'planit-event-manager' ); ?>
			</label>
		</p>
		
		<div id="twec-recurring-options" style="<?php echo esc_attr( $is_recurring ? '' : 'display: none;' ); ?>">
			<p>
				<label for="twec_recurrence_type"><?php esc_html_e( 'Repeat:', 'planit-event-manager' ); ?></label>
				<select id="twec_recurrence_type" name="twec_recurrence_type">
					<option value="daily" <?php selected( $recurrence_type, 'daily' ); ?>><?php esc_html_e( 'Daily', 'planit-event-manager' ); ?></option>
					<option value="weekly" <?php selected( $recurrence_type, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'planit-event-manager' ); ?></option>
					<option value="monthly" <?php selected( $recurrence_type, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'planit-event-manager' ); ?></option>
					<option value="yearly" <?php selected( $recurrence_type, 'yearly' ); ?>><?php esc_html_e( 'Yearly', 'planit-event-manager' ); ?></option>
				</select>
			</p>
			
			<p>
				<label for="twec_recurrence_interval"><?php esc_html_e( 'Every:', 'planit-event-manager' ); ?></label>
				<input type="number" id="twec_recurrence_interval" name="twec_recurrence_interval" value="<?php echo esc_attr( $recurrence_interval ? $recurrence_interval : 1 ); ?>" min="1" style="width: 60px;" />
				<span id="twec-recurrence-interval-text"></span>
			</p>
			
			<p>
				<label>
					<input type="radio" name="twec_recurrence_end" value="date" <?php checked( empty( $recurrence_count ), true ); ?> />
					<?php esc_html_e( 'End date:', 'planit-event-manager' ); ?>
				</label>
				<input type="date" id="twec_recurrence_end_date" name="twec_recurrence_end_date" value="<?php echo esc_attr( $recurrence_end_date ); ?>" />
			</p>
			
			<p>
				<label>
					<input type="radio" name="twec_recurrence_end" value="count" <?php checked( ! empty( $recurrence_count ), true ); ?> />
					<?php esc_html_e( 'After', 'planit-event-manager' ); ?>
				</label>
				<input type="number" id="twec_recurrence_count" name="twec_recurrence_count" value="<?php echo esc_attr( $recurrence_count ); ?>" min="1" style="width: 60px;" />
				<?php esc_html_e( 'occurrences', 'planit-event-manager' ); ?>
			</p>

			<hr style="margin: 12px 0;" />
			<p>
				<label>
					<input type="checkbox" id="twec_recurrence_advanced_cb" name="twec_recurrence_advanced" value="1" <?php checked( $advanced, '1' ); ?> />
					<?php esc_html_e( 'Advanced: RRULE + holiday / date exclusions', 'planit-event-manager' ); ?>
				</label>
			</p>
			<div id="twec-advanced-recurrence" style="<?php echo '1' === $advanced ? '' : 'display:none;'; ?>">
				<p>
					<label for="twec_rrule_preset"><?php esc_html_e( 'Quick preset (fills RRULE field)', 'planit-event-manager' ); ?></label>
					<select id="twec_rrule_preset" class="widefat">
						<option value=""><?php esc_html_e( 'Choose a preset…', 'planit-event-manager' ); ?></option>
						<option value="FREQ=DAILY;INTERVAL=1"><?php esc_html_e( 'Daily — every day', 'planit-event-manager' ); ?></option>
						<option value="FREQ=WEEKLY;INTERVAL=1;BYDAY=MO"><?php esc_html_e( 'Weekly — every Monday', 'planit-event-manager' ); ?></option>
						<option value="FREQ=WEEKLY;INTERVAL=1;BYDAY=TU,TH"><?php esc_html_e( 'Weekly — Tuesdays and Thursdays', 'planit-event-manager' ); ?></option>
						<option value="FREQ=MONTHLY;INTERVAL=1;BYDAY=-1FR"><?php esc_html_e( 'Monthly — last Friday of month', 'planit-event-manager' ); ?></option>
						<option value="FREQ=MONTHLY;INTERVAL=1;BYDAY=2TU"><?php esc_html_e( 'Monthly — second Tuesday', 'planit-event-manager' ); ?></option>
						<option value="FREQ=YEARLY;INTERVAL=1"><?php esc_html_e( 'Yearly — same date annually', 'planit-event-manager' ); ?></option>
					</select>
				</p>
				<p>
					<label for="twec_recurrence_rrule"><?php esc_html_e( 'RRULE (subset: FREQ, INTERVAL, UNTIL/COUNT; BYDAY for monthly nth/last weekday; weekly: one day or comma-separated days, e.g. TU,TH)', 'planit-event-manager' ); ?></label>
					<input type="text" class="widefat" id="twec_recurrence_rrule" name="twec_recurrence_rrule" value="<?php echo esc_attr( $rrule ); ?>" placeholder="FREQ=MONTHLY;INTERVAL=2;BYDAY=-1FR" />
				</p>
				<p>
					<label for="twec_recurrence_exdates"><?php esc_html_e( 'Excluded dates (one Y-m-d per line, e.g. holidays)', 'planit-event-manager' ); ?></label>
					<textarea class="widefat" rows="4" id="twec_recurrence_exdates" name="twec_recurrence_exdates"><?php echo esc_textarea( $exdates ); ?></textarea>
				</p>
				<p>
					<button type="button" class="button twec_rrule_preview_btn"><?php esc_html_e( 'Preview instances', 'planit-event-manager' ); ?></button>
					<span class="description twec_rrule_preview_hint"><?php esc_html_e( 'Uses event start/end and saved draft when needed.', 'planit-event-manager' ); ?></span>
				</p>
				<div id="twec_rrule_preview_out" style="margin-top:8px; white-space: pre-wrap;" class="description" aria-live="polite"></div>
			</div>
		</div>
		<input type="hidden" id="twec_rrule_rest_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" />
		<input type="hidden" id="twec_rrule_preview_rest_endpoint" value="<?php echo esc_url( rest_url( 'planit/v1/recurrence' ) ); ?>" />

		<script>
		jQuery(function($) {
			$('#twec_recurrence_advanced_cb').on('change', function() {
				$('#twec-advanced-recurrence').toggle($(this).is(':checked'));
			});
			$('#twec_rrule_preset').on('change', function() {
				var v = $(this).val();
				if (v) $('#twec_recurrence_rrule').val(v);
			});
			$('.twec_rrule_preview_btn').on('click', function() {
				var btn = $(this), out = $('#twec_rrule_preview_out'), root = $('#twec_rrule_preview_rest_endpoint').val();
				var nonce = $('#twec_rrule_rest_nonce').val();
				out.text('<?php echo esc_js( __( 'Running preview…', 'planit-event-manager' ) ); ?>');
				$.ajax({
					url: (root.replace(/\/$/, '')) + '/preview',
					method: 'POST',
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', nonce);
					},
					contentType: 'application/json',
					data: JSON.stringify({
						nonce: nonce,
						post_id: parseInt(btn.closest('form').find('#post_ID').val(), 10) || <?php echo (int) $post->ID; ?>,
						rrule: $('#twec_recurrence_rrule').val(),
						exdates: $('#twec_recurrence_exdates').val()
					}),
					success: function(res) {
						if (!res || !res.preview || !res.preview.length) {
							out.text('<?php echo esc_js( __( 'No instances matched for this RRULE (check event dates and exclusions).', 'planit-event-manager' ) ); ?>');
							return;
						}
						var lines = [], i;
						var maxShow = <?php echo (int) min( TWEC_RRule_Expand::MAX_INSTANCES, 25 ); ?>;
						for (i = 0; i < res.preview.length && i < maxShow; i++) {
							lines.push(res.preview[i].start + ' — ' + res.preview[i].end);
						}
						if (res.preview.length > maxShow) {
							lines.push(String(res.preview.length) + '+ <?php echo esc_js( __( 'instances total (truncated)', 'planit-event-manager' ) ); ?>');
						}
						out.text(lines.join('\n'));
					},
					error: function(xhr) {
						var jp = xhr.responseJSON;
						var m = jp && (jp.message || (jp.data && jp.data.message)) ? (jp.message || jp.data.message) : xhr.statusText;
						out.text(m || '<?php echo esc_js( __( 'Preview failed.', 'planit-event-manager' ) ); ?>');
					}
				});
			});
		});
		</script>

		<?php
	}

	/**
	 * Save recurring meta data.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_recurring_meta( $post_id ) {
		// Verify nonce. Must sanitize with sanitize_text_field() because wp_verify_nonce() is pluggable.
		if ( ! isset( $_POST['twec_recurring_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['twec_recurring_nonce'] ) ), 'twec_save_recurring' ) ) {
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

		$is_recurring = isset( $_POST['twec_is_recurring'] ) ? '1' : '0';
		update_post_meta( $post_id, '_twec_is_recurring', $is_recurring );

		if ( $is_recurring ) {
			$adv                     = isset( $_POST['twec_recurrence_advanced'] ) ? '1' : '0';
			$prev_rrule_meta         = (string) get_post_meta( $post_id, '_twec_recurrence_rrule', true );
			$prev_ex_meta            = (string) get_post_meta( $post_id, '_twec_recurrence_exdates', true );
			$incoming_rrule          = isset( $_POST['twec_recurrence_rrule'] ) ? sanitize_textarea_field( wp_unslash( $_POST['twec_recurrence_rrule'] ) ) : '';
			$incoming_ex             = isset( $_POST['twec_recurrence_exdates'] ) ? sanitize_textarea_field( wp_unslash( $_POST['twec_recurrence_exdates'] ) ) : '';

			update_post_meta( $post_id, '_twec_recurrence_advanced', $adv );
			if ( '1' === $adv ) {
				if ( '' !== trim( $incoming_rrule ) ) {
					$checks = self::rrule_instances_for_post( $post_id, $incoming_rrule, $incoming_ex );
					if ( empty( $checks ) ) {
						self::remember_rrule_notice(
							sprintf(
								/* translators: %d: numeric post ID */
								__( 'Saved other recurrence fields. RRULE was not updated because PlanIt computed zero occurrences for event ID %d. Check event start/end and exclusions.', 'planit-event-manager' ),
								(int) $post_id
							)
						);
						update_post_meta( $post_id, '_twec_recurrence_rrule', $prev_rrule_meta );
						update_post_meta( $post_id, '_twec_recurrence_exdates', $prev_ex_meta );
					} else {
						update_post_meta( $post_id, '_twec_recurrence_rrule', $incoming_rrule );
						update_post_meta( $post_id, '_twec_recurrence_exdates', $incoming_ex );
					}
				} else {
					update_post_meta( $post_id, '_twec_recurrence_rrule', $incoming_rrule );
					update_post_meta( $post_id, '_twec_recurrence_exdates', $incoming_ex );
				}
			} else {
				delete_post_meta( $post_id, '_twec_recurrence_advanced' );
				delete_post_meta( $post_id, '_twec_recurrence_rrule' );
				delete_post_meta( $post_id, '_twec_recurrence_exdates' );
			}
			if ( isset( $_POST['twec_recurrence_type'] ) ) {
				update_post_meta( $post_id, '_twec_recurrence_type', sanitize_text_field( wp_unslash( $_POST['twec_recurrence_type'] ) ) );
			}
			if ( isset( $_POST['twec_recurrence_interval'] ) ) {
				update_post_meta( $post_id, '_twec_recurrence_interval', intval( wp_unslash( $_POST['twec_recurrence_interval'] ) ) );
			}
			$recurrence_end = isset( $_POST['twec_recurrence_end'] ) ? sanitize_text_field( wp_unslash( $_POST['twec_recurrence_end'] ) ) : '';
			if ( 'date' === $recurrence_end ) {
				if ( isset( $_POST['twec_recurrence_end_date'] ) ) {
					update_post_meta( $post_id, '_twec_recurrence_end_date', sanitize_text_field( wp_unslash( $_POST['twec_recurrence_end_date'] ) ) );
				}
				delete_post_meta( $post_id, '_twec_recurrence_count' );
			} elseif ( 'count' === $recurrence_end ) {
				if ( isset( $_POST['twec_recurrence_count'] ) ) {
					update_post_meta( $post_id, '_twec_recurrence_count', absint( wp_unslash( $_POST['twec_recurrence_count'] ) ) );
				}
				delete_post_meta( $post_id, '_twec_recurrence_end_date' );
			}
		} else {
			delete_post_meta( $post_id, '_twec_recurrence_type' );
			delete_post_meta( $post_id, '_twec_recurrence_interval' );
			delete_post_meta( $post_id, '_twec_recurrence_end_date' );
			delete_post_meta( $post_id, '_twec_recurrence_count' );
			delete_post_meta( $post_id, '_twec_recurrence_advanced' );
			delete_post_meta( $post_id, '_twec_recurrence_rrule' );
			delete_post_meta( $post_id, '_twec_recurrence_exdates' );
		}
	}

	/**
	 * Enqueue lightweight admin deps for recurrence meta box previews.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature matches WordPress hook.
		$scr = '';
		if ( function_exists( 'get_current_screen' ) ) {
			$s = get_current_screen();
			$scr = is_object( $s ) ? (string) $s->base : '';
		}
		if ( 'post' !== $scr ) {
			return;
		}
		global $post;
		if ( ! $post instanceof WP_Post || 'twec_event' !== get_post_type( $post ) ) {
			return;
		}
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Register REST endpoints for recurrence tooling.
	 *
	 * @return void
	 */
	public static function register_rest_rrule_preview() {
		register_rest_route(
			'planit/v1',
			'/recurrence/preview',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'rest_rrule_preview_perm' ),
				'callback'            => array( __CLASS__, 'rest_rrule_preview' ),
				'args'                => array(
					'nonce'    => array(
						'description'       => __( 'REST API nonce (`wp_rest`). May be provided in JSON body or as a param.', 'planit-event-manager' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'post_id'  => array(
						'description'       => __( 'Event post ID to evaluate recurrence against.', 'planit-event-manager' ),
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'rrule'    => array(
						'description'       => __( 'RFC 5545 RRULE string.', 'planit-event-manager' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'exdates'  => array(
						'description'       => __( 'Optional EXDATE list (comma/newline separated).', 'planit-event-manager' ),
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);
	}

	/**
	 * Can the current user invoke recurrence preview?
	 *
	 * @return bool
	 */
	public static function rest_rrule_preview_perm() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Validates request payload, verifies `wp_rest` nonce, and returns capped RRULE instance previews.
	 *
	 * Accepts JSON body (`application/json`) or parameters merged by the REST Server into the request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error Response with preview rows or error.
	 */
	public static function rest_rrule_preview( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$nonce = isset( $params['nonce'] ) ? sanitize_text_field( (string) $params['nonce'] ) : sanitize_text_field( (string) $request->get_param( 'nonce' ) );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'bad_nonce', __( 'Invalid nonce.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}

		$post_id = isset( $params['post_id'] ) ? absint( $params['post_id'] ) : absint( $request->get_param( 'post_id' ) );
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot edit this event.', 'planit-event-manager' ), array( 'status' => 403 ) );
		}

		$rrule   = isset( $params['rrule'] ) ? sanitize_textarea_field( (string) $params['rrule'] ) : sanitize_textarea_field( (string) $request->get_param( 'rrule' ) );
		$exdates = isset( $params['exdates'] ) ? sanitize_textarea_field( (string) $params['exdates'] ) : sanitize_textarea_field( (string) $request->get_param( 'exdates' ) );

		$res = self::rrule_instances_for_post( $post_id, $rrule, $exdates );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$slice = array_slice( $res, 0, 50 );

		return new WP_REST_Response(
			array(
				'preview' => array_map(
					static function ( $row ) {
						return array(
							'start' => isset( $row['start'] ) ? (string) $row['start'] : '',
							'end'   => isset( $row['end'] ) ? (string) $row['end'] : '',
						);
					},
					$slice
				),
			),
			200
		);
	}

	/**
	 * Persist admin notices for recurrence validation failures.
	 *
	 * @param string $msg Message.
	 * @return void
	 */
	private static function remember_rrule_notice( $msg ) {
		$msg = sanitize_text_field( (string) $msg );
		if ( '' !== $msg ) {
			set_transient( 'twec_rrule_warning_' . get_current_user_id(), $msg, 120 );
		}
	}

	/**
	 * Surface queued recurrence validation warnings.
	 *
	 * @return void
	 */
	public static function maybe_show_rrule_notice() {
		if ( ! is_admin() ) {
			return;
		}
		$key = 'twec_rrule_warning_' . get_current_user_id();
		$m   = get_transient( $key );
		if ( ! is_string( $m ) || '' === trim( $m ) ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-warning is-dismissible"><p>';
		echo esc_html( $m );
		echo '</p></div>';
	}

	/**
	 * Expand RRULE for an event using persisted start/end meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $rrule   RRULE string.
	 * @param string $exdates Exdates blob.
	 * @return array<int,array<string,string>>|\WP_Error
	 */
	private static function rrule_instances_for_post( $post_id, $rrule, $exdates ) {
		$post_id = (int) $post_id;
		if ( $post_id < 1 ) {
			return new WP_Error( 'invalid_event', __( 'Invalid event.', 'planit-event-manager' ), array( 'status' => 400 ) );
		}

		if ( ! class_exists( 'TWEC_RRule_Expand', false ) ) {
			return new WP_Error( 'missing_engine', __( 'Recurrence expansion engine not available.', 'planit-event-manager' ), array( 'status' => 500 ) );
		}

		$rrule = trim( (string) $rrule );
		if ( '' === $rrule ) {
			return array();
		}

		$base_start = (string) get_post_meta( $post_id, '_twec_event_start_date', true );
		$base_end   = (string) get_post_meta( $post_id, '_twec_event_end_date', true );

		if ( '' === trim( $base_start ) ) {
			return new WP_Error(
				'no_start',
				__( 'Save the event start/end dates before validating RRULE previews.', 'planit-event-manager' ),
				array( 'status' => 400 )
			);
		}
		if ( '' === trim( $base_end ) ) {
			$base_end = $base_start;
		}

		$max = (int) apply_filters( 'twec_recurring_max_instances', TWEC_RRule_Expand::MAX_INSTANCES, $post_id );

		return TWEC_RRule_Expand::expand( $base_start, $base_end, $rrule, (string) $exdates, null, null, $max );
	}

	/**
	 * Generate recurring event instances.
	 */
	public function generate_recurring_instances() {
		// This would generate instances on-the-fly or store them.
		// For now, we'll handle this in the query.
	}

	/**
	 * Get recurring instances for an event.
	 *
	 * @param int    $event_id   Event ID.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Array of recurring instances.
	 */
	public static function get_recurring_instances( $event_id, $start_date = null, $end_date = null ) {
		$is_recurring = get_post_meta( $event_id, '_twec_is_recurring', true );
		if ( ! $is_recurring ) {
			return array();
		}

		$event               = get_post( $event_id );
		$base_start          = get_post_meta( $event_id, '_twec_event_start_date', true );
		$base_end            = get_post_meta( $event_id, '_twec_event_end_date', true );
		$recurrence_type     = get_post_meta( $event_id, '_twec_recurrence_type', true );
		$recurrence_interval = get_post_meta( $event_id, '_twec_recurrence_interval', true );
		$recurrence_interval = $recurrence_interval ? $recurrence_interval : 1;
		$recurrence_end_date = get_post_meta( $event_id, '_twec_recurrence_end_date', true );
		$recurrence_count    = get_post_meta( $event_id, '_twec_recurrence_count', true );

		if ( ! $base_start ) {
			return array();
		}

		$advanced = get_post_meta( $event_id, '_twec_recurrence_advanced', true );
		$rrule    = get_post_meta( $event_id, '_twec_recurrence_rrule', true );
		$exraw    = get_post_meta( $event_id, '_twec_recurrence_exdates', true );
		if ( '1' === $advanced && is_string( $rrule ) && '' !== trim( $rrule ) && class_exists( 'TWEC_RRule_Expand' ) ) {
			$max = (int) apply_filters( 'twec_recurring_max_instances', TWEC_RRule_Expand::MAX_INSTANCES, $event_id );
			return TWEC_RRule_Expand::expand( $base_start, $base_end, $rrule, is_string( $exraw ) ? $exraw : '', $start_date, $end_date, $max );
		}

		$instances = array();
		$current   = new DateTime( $base_start );
		$end       = $recurrence_end_date ? new DateTime( $recurrence_end_date ) : null;
		$count     = 0;
		$max_count = $recurrence_count ? intval( $recurrence_count ) : null;

		$start_range = $start_date ? new DateTime( $start_date ) : null;
		$end_range   = $end_date ? new DateTime( $end_date ) : null;

		while ( true ) {
			if ( $end && $current > $end ) {
				break;
			}
			if ( $max_count && $count >= $max_count ) {
				break;
			}

			// Check if this instance is in the requested range.
			if ( $start_range && $current < $start_range ) {
				// Skip to start range.
				$current = self::get_next_occurrence( $current, $recurrence_type, $recurrence_interval, $start_range );
				continue;
			}
			if ( $end_range && $current > $end_range ) {
				break;
			}

			$instance_start = clone $current;
			$duration       = strtotime( $base_end ) - strtotime( $base_start );
			$instance_end   = clone $current;
			$instance_end->modify( '+' . $duration . ' seconds' );

			$instances[] = array(
				'start' => $instance_start->format( 'Y-m-d H:i:s' ),
				'end'   => $instance_end->format( 'Y-m-d H:i:s' ),
			);

			$current = self::get_next_occurrence( $current, $recurrence_type, $recurrence_interval );
			++$count;
		}

		return $instances;
	}

	/**
	 * Get next occurrence date.
	 *
	 * @param DateTime      $current   Current date.
	 * @param string        $type      Recurrence type.
	 * @param int           $interval  Recurrence interval.
	 * @param DateTime|null $min_date Minimum date.
	 * @return DateTime Next occurrence date.
	 */
	private static function get_next_occurrence( $current, $type, $interval, $min_date = null ) {
		$next = clone $current;

		switch ( $type ) {
			case 'daily':
				$next->modify( "+$interval days" );
				break;
			case 'weekly':
				$next->modify( "+$interval weeks" );
				break;
			case 'monthly':
				$next->modify( "+$interval months" );
				break;
			case 'yearly':
				$next->modify( "+$interval years" );
				break;
		}

		if ( $min_date && $next < $min_date ) {
			// Calculate how many intervals needed to reach min_date.
			$diff             = $current->diff( $min_date );
			$days             = $diff->days;
			$intervals_needed = ceil( $days / $interval );
			$next             = clone $current;

			switch ( $type ) {
				case 'daily':
					$next->modify( '+' . ( $intervals_needed * $interval ) . ' days' );
					break;
				case 'weekly':
					$next->modify( '+' . ( $intervals_needed * $interval ) . ' weeks' );
					break;
				case 'monthly':
					$next->modify( '+' . ( $intervals_needed * $interval ) . ' months' );
					break;
				case 'yearly':
					$next->modify( '+' . ( $intervals_needed * $interval ) . ' years' );
					break;
			}
		}

		return $next;
	}
}

// TWEC_Recurring is initialized by TWEC class.

