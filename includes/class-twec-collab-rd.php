<?php
/**
 * WordPress 6.x+ / 7.0: collaboration & command-palette R&D hooks (no transport yet).
 *
 * @package PlanIt_Event_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * When true, the block editor hook that fires `twec_register_editor_commands` is registered
 * (for R&D: @wordpress/commands). Default false. Override in wp-config.php for spikes only.
 */
if ( ! defined( 'TWEC_EXPERIMENTAL_EDITOR_COMMANDS' ) ) {
	define( 'TWEC_EXPERIMENTAL_EDITOR_COMMANDS', false );
}

/**
 * Admin-only affordances: admin bar link; filter for future core commands.
 */
class TWEC_Collab_RD {

	/**
	 * Whether to load block-editor R&D (command palette hook). Admin bar is always available.
	 *
	 * @return bool
	 */
	public static function editor_experimental_enabled() {
		if ( class_exists( 'TWEC_AI', false ) && TWEC_AI::is_command_palette_enabled() ) {
			return true;
		}
		if ( defined( 'TWEC_EXPERIMENTAL_EDITOR_COMMANDS' ) && TWEC_EXPERIMENTAL_EDITOR_COMMANDS ) {
			return true;
		}
		/**
		 * Enable block editor command R&D (off by default).
		 *
		 * @param bool $enabled Default false.
		 */
		return (bool) apply_filters( 'twec_experimental_editor_commands', false );
	}

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 99 );
		if ( self::editor_experimental_enabled() ) {
			add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_commands' ), 20 );
		}
	}

	/**
	 * Main plugin bootstrap file (for plugins_url / filemtime).
	 *
	 * @return string
	 */
	private static function get_plugin_file() {
		if ( defined( 'PLANIT_EVENT_MANAGER_FILE' ) ) {
			return (string) PLANIT_EVENT_MANAGER_FILE;
		}
		if ( defined( 'TWEC_PLUGIN_FILE' ) ) {
			return (string) TWEC_PLUGIN_FILE;
		}
		return __FILE__;
	}

	/**
	 * @return string
	 */
	private static function get_plugin_version() {
		if ( defined( 'PLANIT_EVENT_MANAGER_VERSION' ) ) {
			return (string) PLANIT_EVENT_MANAGER_VERSION;
		}
		if ( defined( 'TWEC_VERSION' ) ) {
			return (string) TWEC_VERSION;
		}
		return '1';
	}

	/**
	 * Enqueue command-palette script on twec_event block editor (R&D flag only).
	 *
	 * Script: `admin/js/twec-editor-commands.js`. Dependencies include `wp-commands` when registered (WP 6.4+).
	 *
	 * @return void
	 */
	public static function enqueue_block_editor_commands() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || 'twec_event' !== $screen->post_type ) {
			return;
		}

		$plugin_file = self::get_plugin_file();
		$dir         = plugin_dir_path( $plugin_file );
		$url         = plugin_dir_url( $plugin_file );
		$path        = $dir . 'admin/js/twec-editor-commands.js';
		if ( ! is_readable( $path ) ) {
			return;
		}

		$ver  = (string) filemtime( $path );
		$deps = array( 'wp-data', 'wp-i18n', 'wp-api-fetch' );
		if ( wp_script_is( 'wp-commands', 'registered' ) ) {
			$deps[] = 'wp-commands';
		}
		if ( wp_script_is( 'wp-core-abilities', 'registered' ) ) {
			$deps[] = 'wp-core-abilities';
		}

		wp_enqueue_script(
			'twec-editor-commands',
			$url . 'admin/js/twec-editor-commands.js',
			$deps,
			$ver,
			true
		);

		$ai_assist = class_exists( 'TWEC_AI', false ) && TWEC_AI::is_admin_assist_enabled() && TWEC_AI::is_text_generation_available();

		wp_localize_script(
			'twec-editor-commands',
			'twecEditorCommands',
			array(
				'newEventUrl'      => admin_url( 'post-new.php?post_type=twec_event' ),
				'settingsUrl'      => admin_url( 'edit.php?post_type=twec_event&page=twec-settings' ),
				'eventsArchiveUrl' => get_post_type_archive_link( 'twec_event' ),
				'restRoot'         => esc_url_raw( rest_url() ),
				'canManage'        => current_user_can( 'manage_options' ),
				'aiAssistEnabled'  => $ai_assist,
				'addLabel'         => __( 'Add PlanIt event', 'planit-event-manager' ),
				'settingsLabel'    => __( 'PlanIt event calendar settings', 'planit-event-manager' ),
				'weekCommandLabel' => __( 'List PlanIt events this week', 'planit-event-manager' ),
				'weekListLabel'    => __( 'Events this week:', 'planit-event-manager' ),
				'weekEmptyLabel'   => __( 'No PlanIt events scheduled this week.', 'planit-event-manager' ),
				'aiDraftLabel'     => __( 'Draft PlanIt event with AI', 'planit-event-manager' ),
				'aiPromptLabel'    => __( 'Describe your event (title, date, venue):', 'planit-event-manager' ),
			)
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'twec-editor-commands', 'planit-event-manager', $dir . 'languages' );
		}

		/**
		 * Fires when PlanIt is ready to register block-editor / collaboration integrations.
		 * The `twec-editor-commands` script is enqueued and localized before this runs.
		 *
		 * @since 1.0.0
		 */
		do_action( 'twec_register_editor_commands' );
	}

	/**
	 * @param WP_Admin_Bar $bar Bar.
	 * @return void
	 */
	public static function admin_bar( $bar ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'twec-new-event',
				'title' => __( 'New PlanIt event', 'planit-event-manager' ),
				'href'  => admin_url( 'post-new.php?post_type=twec_event' ),
				'meta'  => array( 'class' => 'twec-rd-future-command' ),
			)
		);
	}
}
