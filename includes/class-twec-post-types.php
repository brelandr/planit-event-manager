<?php
/**
 * Register custom post types and taxonomies.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-twec-post-types.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register custom post types and taxonomies.
 *
 * Handles registration of events, venues, organizers, and related taxonomies.
 */
if ( ! class_exists( 'TWEC_Post_Types' ) ) {
	class TWEC_Post_Types {

		/**
		 * Register custom post types.
		 */
		public function register_post_types() {
			// Register Event post type.
			$labels = array(
				'name'               => _x( 'Events', 'post type general name', 'planit-event-manager' ),
				'singular_name'      => _x( 'Event', 'post type singular name', 'planit-event-manager' ),
				'menu_name'          => _x( 'Events', 'admin menu', 'planit-event-manager' ),
				'name_admin_bar'     => _x( 'Event', 'add new on admin bar', 'planit-event-manager' ),
				'add_new'            => _x( 'Add New', 'event', 'planit-event-manager' ),
				'add_new_item'       => __( 'Add New Event', 'planit-event-manager' ),
				'new_item'           => __( 'New Event', 'planit-event-manager' ),
				'edit_item'          => __( 'Edit Event', 'planit-event-manager' ),
				'view_item'          => __( 'View Event', 'planit-event-manager' ),
				'all_items'          => __( 'All Events', 'planit-event-manager' ),
				'search_items'       => __( 'Search Events', 'planit-event-manager' ),
				'parent_item_colon'  => __( 'Parent Events:', 'planit-event-manager' ),
				'not_found'          => __( 'No events found.', 'planit-event-manager' ),
				'not_found_in_trash' => __( 'No events found in Trash.', 'planit-event-manager' ),
			);

			$args = array(
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'query_var'          => true,
				'rewrite'            => array( 'slug' => 'events' ),
				'capability_type'    => 'post',
				'has_archive'        => true,
				'hierarchical'       => false,
				'menu_position'      => 20,
				'menu_icon'          => 'dashicons-calendar-alt',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
				'show_in_rest'       => true,
			);

			register_post_type( 'twec_event', $args );

			$this->register_twec_event_meta_for_rest();

			// Register Venue post type.
			$venue_labels = array(
				'name'          => _x( 'Venues', 'post type general name', 'planit-event-manager' ),
				'singular_name' => _x( 'Venue', 'post type singular name', 'planit-event-manager' ),
				'menu_name'     => _x( 'Venues', 'admin menu', 'planit-event-manager' ),
				'add_new_item'  => __( 'Add New Venue', 'planit-event-manager' ),
				'edit_item'     => __( 'Edit Venue', 'planit-event-manager' ),
				'view_item'     => __( 'View Venue', 'planit-event-manager' ),
				'all_items'     => __( 'All Venues', 'planit-event-manager' ),
			);

			$venue_args = array(
				'labels'             => $venue_labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => 'edit.php?post_type=twec_event',
				'query_var'          => true,
				'capability_type'    => 'post',
				'has_archive'        => false,
				'hierarchical'       => false,
				'supports'           => array( 'title', 'editor', 'custom-fields' ),
			);

			register_post_type( 'twec_venue', $venue_args );

			// Register Organizer post type.
			$organizer_labels = array(
				'name'          => _x( 'Organizers', 'post type general name', 'planit-event-manager' ),
				'singular_name' => _x( 'Organizer', 'post type singular name', 'planit-event-manager' ),
				'menu_name'     => _x( 'Organizers', 'admin menu', 'planit-event-manager' ),
				'add_new_item'  => __( 'Add New Organizer', 'planit-event-manager' ),
				'edit_item'     => __( 'Edit Organizer', 'planit-event-manager' ),
				'view_item'     => __( 'View Organizer', 'planit-event-manager' ),
				'all_items'     => __( 'All Organizers', 'planit-event-manager' ),
			);

			$organizer_args = array(
				'labels'             => $organizer_labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => 'edit.php?post_type=twec_event',
				'query_var'          => true,
				'capability_type'    => 'post',
				'has_archive'        => false,
				'hierarchical'       => false,
				'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			);

			register_post_type( 'twec_organizer', $organizer_args );
		}

		/**
		 * Register event meta for the REST API / block editor.
		 *
		 * Classic metaboxes rely on $_POST nonces on post.php saves. The block editor
		 * persists `twec_event` primarily via PUT /wp/v2/twec_event/{id}, so meta must be
		 * registered with show_in_rest or it will not update from the editor.
		 *
		 * @return void
		 */
		private function register_twec_event_meta_for_rest() {
			$post_type = 'twec_event';

			$edit_event = static function ( $allowed, $meta_key, $post_id ) {
				$post_id = (int) $post_id;
				if ( $post_id <= 0 ) {
					return false;
				}
				return current_user_can( 'edit_post', $post_id ) && 'twec_event' === get_post_type( $post_id );
			};

			// Core event fields (TWEC_Meta_Boxes / save_event_meta).
			$registered = array(
				array(
					'key'  => '_twec_event_all_day',
					'args' => array(
						'type'              => 'string',
						'default'           => '0',
						'sanitize_callback' => static function ( $meta_value ) {
							$s = is_scalar( $meta_value ) ? (string) $meta_value : '';
							return in_array( $s, array( '0', '1' ), true ) ? $s : '0';
						},
					),
				),
				array(
					'key'  => '_twec_event_start_date',
					'args' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				array(
					'key'  => '_twec_event_end_date',
					'args' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				array(
					'key'  => '_twec_event_start_time',
					'args' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				array(
					'key'  => '_twec_event_end_time',
					'args' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				array(
					'key'  => '_twec_event_venue',
					'args' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => static function ( $meta_value ) {
							return absint( $meta_value );
						},
					),
				),
				array(
					'key'  => '_twec_event_organizer',
					'args' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => static function ( $meta_value ) {
							return absint( $meta_value );
						},
					),
				),
				array(
					'key'  => '_twec_event_attendance',
					'args' => array(
						'type'              => 'string',
						'default'           => 'in_person',
						'sanitize_callback' => static function ( $meta_value ) {
							$a = sanitize_text_field( (string) $meta_value );
							return in_array( $a, array( 'in_person', 'online', 'mixed' ), true ) ? $a : 'in_person';
						},
					),
				),
				array(
					'key'  => '_twec_event_virtual_url',
					'args' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static function ( $meta_value ) {
							return '' === trim( (string) $meta_value ) ? '' : esc_url_raw( (string) $meta_value );
						},
					),
				),
				array(
					'key'  => '_twec_event_cost',
					'args' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				array(
					'key'  => '_twec_event_website',
					'args' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static function ( $meta_value ) {
							return esc_url_raw( (string) $meta_value );
						},
					),
				),
				array(
					'key'  => '_twec_event_timezone',
					'args' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static function ( $meta_value ) {
							$tz = sanitize_text_field( (string) $meta_value );
							if ( '' === $tz ) {
								return '';
							}
							return in_array( $tz, planit_event_manager_get_timezone_identifiers(), true ) ? $tz : '';
						},
					),
				),
				array(
					'key'  => '_twec_is_featured',
					'args' => array(
						'type'              => 'string',
						'default'           => '0',
						'sanitize_callback' => static function ( $meta_value ) {
							$s = is_scalar( $meta_value ) ? (string) $meta_value : '';
							return in_array( $s, array( '0', '1' ), true ) ? $s : '0';
						},
					),
				),
				array(
					'key'  => '_twec_event_capacity',
					'args' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => static function ( $meta_value ) {
							return max( 0, (int) $meta_value );
						},
					),
				),
				array(
					'key'  => '_twec_is_recurring',
					'args' => array(
						'type'              => 'string',
						'default'           => '0',
						'sanitize_callback' => static function ( $meta_value ) {
							$s = is_scalar( $meta_value ) ? (string) $meta_value : '';
							return in_array( $s, array( '0', '1' ), true ) ? $s : '0';
						},
					),
				),
				array(
					'key'  => '_twec_recurrence_type',
					'args' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static function ( $meta_value ) {
							$t = sanitize_key( (string) $meta_value );
							return in_array( $t, array( 'daily', 'weekly', 'monthly', 'yearly' ), true ) ? $t : '';
						},
					),
				),
				array(
					'key'  => '_twec_recurrence_interval',
					'args' => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => static function ( $meta_value ) {
							$n = (int) $meta_value;
							return max( 1, $n > 0 ? $n : 1 );
						},
					),
				),
				array(
					'key'  => '_twec_recurrence_end_date',
					'args' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static function ( $meta_value ) {
							return sanitize_text_field( (string) $meta_value );
						},
					),
				),
				array(
					'key'  => '_twec_recurrence_count',
					'args' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => static function ( $meta_value ) {
							return max( 0, (int) $meta_value );
						},
					),
				),
				array(
					'key'  => '_twec_recurrence_advanced',
					'args' => array(
						'type'              => 'string',
						'default'           => '0',
						'sanitize_callback' => static function ( $meta_value ) {
							$s = is_scalar( $meta_value ) ? (string) $meta_value : '';
							return in_array( $s, array( '0', '1' ), true ) ? $s : '0';
						},
					),
				),
				array(
					'key'  => '_twec_recurrence_rrule',
					'args' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static function ( $meta_value ) {
							return sanitize_textarea_field( (string) $meta_value );
						},
					),
				),
				array(
					'key'  => '_twec_recurrence_exdates',
					'args' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static function ( $meta_value ) {
							return sanitize_textarea_field( (string) $meta_value );
						},
					),
				),
			);

			foreach ( $registered as $row ) {
				$key  = isset( $row['key'] ) ? (string) $row['key'] : '';
				$opts = isset( $row['args'] ) && is_array( $row['args'] ) ? $row['args'] : array();
				if ( '' === $key ) {
					continue;
				}
				$args = array_merge(
					array(
						'single'        => true,
						'auth_callback' => $edit_event,
						'show_in_rest'  => true,
					),
					$opts
				);
				register_post_meta( $post_type, $key, $args );
			}

			// Bundled config fields (serialized array keyed by slug); stored by TWEC_Custom_Fields::save_custom_fields().
			register_post_meta(
				$post_type,
				'_twec_custom_fields',
				array(
					'single'            => true,
					'type'              => 'object',
					'default'           => array(),
					'auth_callback'     => $edit_event,
					'sanitize_callback' => static function ( $meta_value ) {
						if ( ! is_array( $meta_value ) ) {
							return array();
						}
						$out = array();
						foreach ( $meta_value as $k => $v ) {
							$kid = sanitize_key( (string) $k );
							if ( '' === $kid ) {
								continue;
							}
							$out[ $kid ] = is_scalar( $v ) ? sanitize_textarea_field( (string) $v ) : sanitize_textarea_field( wp_json_encode( $v ) );
						}
						return $out;
					},
					'show_in_rest'      => array(

						/*
						 * Expose as arbitrary string map for block/REST edits; avoid strict shape (field types vary).
						 */
						'schema' => array(
							'type'                 => 'object',
							'context'              => array( 'edit' ),
							'additionalProperties' => array(
								'type' => 'string',
							),
						),
					),
				)
			);
		}

		/**
		 * Register taxonomies.
		 */
		public function register_taxonomies() {
			// Event Categories.
			$category_labels = array(
				'name'              => _x( 'Event Categories', 'taxonomy general name', 'planit-event-manager' ),
				'singular_name'     => _x( 'Event Category', 'taxonomy singular name', 'planit-event-manager' ),
				'search_items'      => __( 'Search Categories', 'planit-event-manager' ),
				'all_items'         => __( 'All Categories', 'planit-event-manager' ),
				'parent_item'       => __( 'Parent Category', 'planit-event-manager' ),
				'parent_item_colon' => __( 'Parent Category:', 'planit-event-manager' ),
				'edit_item'         => __( 'Edit Category', 'planit-event-manager' ),
				'update_item'       => __( 'Update Category', 'planit-event-manager' ),
				'add_new_item'      => __( 'Add New Category', 'planit-event-manager' ),
				'new_item_name'     => __( 'New Category Name', 'planit-event-manager' ),
				'menu_name'         => __( 'Categories', 'planit-event-manager' ),
			);

			register_taxonomy(
				'twec_event_category',
				array( 'twec_event' ),
				array(
					'hierarchical'      => true,
					'labels'            => $category_labels,
					'show_ui'           => true,
					'show_admin_column' => true,
					'query_var'         => true,
					'rewrite'           => array( 'slug' => 'event-category' ),
					'show_in_rest'      => true,
				)
			);

			// Event Tags.
			$tag_labels = array(
				'name'                       => _x( 'Event Tags', 'taxonomy general name', 'planit-event-manager' ),
				'singular_name'              => _x( 'Event Tag', 'taxonomy singular name', 'planit-event-manager' ),
				'search_items'               => __( 'Search Tags', 'planit-event-manager' ),
				'popular_items'              => __( 'Popular Tags', 'planit-event-manager' ),
				'all_items'                  => __( 'All Tags', 'planit-event-manager' ),
				'edit_item'                  => __( 'Edit Tag', 'planit-event-manager' ),
				'update_item'                => __( 'Update Tag', 'planit-event-manager' ),
				'add_new_item'               => __( 'Add New Tag', 'planit-event-manager' ),
				'new_item_name'              => __( 'New Tag Name', 'planit-event-manager' ),
				'separate_items_with_commas' => __( 'Separate tags with commas', 'planit-event-manager' ),
				'add_or_remove_items'        => __( 'Add or remove tags', 'planit-event-manager' ),
				'choose_from_most_used'      => __( 'Choose from the most used tags', 'planit-event-manager' ),
				'not_found'                  => __( 'No tags found.', 'planit-event-manager' ),
				'menu_name'                  => __( 'Tags', 'planit-event-manager' ),
			);

			register_taxonomy(
				'twec_event_tag',
				array( 'twec_event' ),
				array(
					'hierarchical'          => false,
					'labels'                => $tag_labels,
					'show_ui'               => true,
					'show_admin_column'     => true,
					'update_count_callback' => '_update_post_term_count',
					'query_var'             => true,
					'rewrite'               => array( 'slug' => 'event-tag' ),
					'show_in_rest'          => true,
				)
			);
		}
	}
} // ! class_exists( 'TWEC_Post_Types' ).

// Initialize post types - handled by TWEC class.
