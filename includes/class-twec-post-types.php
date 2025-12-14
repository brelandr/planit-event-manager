<?php
/**
 * Register custom post types and taxonomies.
 *
 * @package    The_WordPress_Event_Calendar
 * @subpackage includes
 */
class TWEC_Post_Types {

	/**
	 * Register custom post types.
	 */
	public function register_post_types() {
		// Register Event post type
		$labels = array(
			'name'               => _x( 'Events', 'post type general name', 'the-wordpress-event-calendar' ),
			'singular_name'      => _x( 'Event', 'post type singular name', 'the-wordpress-event-calendar' ),
			'menu_name'          => _x( 'Events', 'admin menu', 'the-wordpress-event-calendar' ),
			'name_admin_bar'     => _x( 'Event', 'add new on admin bar', 'the-wordpress-event-calendar' ),
			'add_new'            => _x( 'Add New', 'event', 'the-wordpress-event-calendar' ),
			'add_new_item'       => __( 'Add New Event', 'the-wordpress-event-calendar' ),
			'new_item'           => __( 'New Event', 'the-wordpress-event-calendar' ),
			'edit_item'          => __( 'Edit Event', 'the-wordpress-event-calendar' ),
			'view_item'          => __( 'View Event', 'the-wordpress-event-calendar' ),
			'all_items'          => __( 'All Events', 'the-wordpress-event-calendar' ),
			'search_items'       => __( 'Search Events', 'the-wordpress-event-calendar' ),
			'parent_item_colon'  => __( 'Parent Events:', 'the-wordpress-event-calendar' ),
			'not_found'          => __( 'No events found.', 'the-wordpress-event-calendar' ),
			'not_found_in_trash' => __( 'No events found in Trash.', 'the-wordpress-event-calendar' ),
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

		// Register Venue post type
		$venue_labels = array(
			'name'               => _x( 'Venues', 'post type general name', 'the-wordpress-event-calendar' ),
			'singular_name'      => _x( 'Venue', 'post type singular name', 'the-wordpress-event-calendar' ),
			'menu_name'          => _x( 'Venues', 'admin menu', 'the-wordpress-event-calendar' ),
			'add_new_item'       => __( 'Add New Venue', 'the-wordpress-event-calendar' ),
			'edit_item'          => __( 'Edit Venue', 'the-wordpress-event-calendar' ),
			'view_item'          => __( 'View Venue', 'the-wordpress-event-calendar' ),
			'all_items'          => __( 'All Venues', 'the-wordpress-event-calendar' ),
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

		// Register Organizer post type
		$organizer_labels = array(
			'name'               => _x( 'Organizers', 'post type general name', 'the-wordpress-event-calendar' ),
			'singular_name'      => _x( 'Organizer', 'post type singular name', 'the-wordpress-event-calendar' ),
			'menu_name'          => _x( 'Organizers', 'admin menu', 'the-wordpress-event-calendar' ),
			'add_new_item'       => __( 'Add New Organizer', 'the-wordpress-event-calendar' ),
			'edit_item'          => __( 'Edit Organizer', 'the-wordpress-event-calendar' ),
			'view_item'          => __( 'View Organizer', 'the-wordpress-event-calendar' ),
			'all_items'          => __( 'All Organizers', 'the-wordpress-event-calendar' ),
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
	 * Register taxonomies.
	 */
	public function register_taxonomies() {
		// Event Categories
		$category_labels = array(
			'name'              => _x( 'Event Categories', 'taxonomy general name', 'the-wordpress-event-calendar' ),
			'singular_name'     => _x( 'Event Category', 'taxonomy singular name', 'the-wordpress-event-calendar' ),
			'search_items'      => __( 'Search Categories', 'the-wordpress-event-calendar' ),
			'all_items'         => __( 'All Categories', 'the-wordpress-event-calendar' ),
			'parent_item'       => __( 'Parent Category', 'the-wordpress-event-calendar' ),
			'parent_item_colon' => __( 'Parent Category:', 'the-wordpress-event-calendar' ),
			'edit_item'         => __( 'Edit Category', 'the-wordpress-event-calendar' ),
			'update_item'       => __( 'Update Category', 'the-wordpress-event-calendar' ),
			'add_new_item'      => __( 'Add New Category', 'the-wordpress-event-calendar' ),
			'new_item_name'     => __( 'New Category Name', 'the-wordpress-event-calendar' ),
			'menu_name'         => __( 'Categories', 'the-wordpress-event-calendar' ),
		);

		register_taxonomy( 'twec_event_category', array( 'twec_event' ), array(
			'hierarchical'      => true,
			'labels'            => $category_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'event-category' ),
			'show_in_rest'      => true,
		) );

		// Event Tags
		$tag_labels = array(
			'name'                       => _x( 'Event Tags', 'taxonomy general name', 'the-wordpress-event-calendar' ),
			'singular_name'              => _x( 'Event Tag', 'taxonomy singular name', 'the-wordpress-event-calendar' ),
			'search_items'               => __( 'Search Tags', 'the-wordpress-event-calendar' ),
			'popular_items'              => __( 'Popular Tags', 'the-wordpress-event-calendar' ),
			'all_items'                  => __( 'All Tags', 'the-wordpress-event-calendar' ),
			'edit_item'                  => __( 'Edit Tag', 'the-wordpress-event-calendar' ),
			'update_item'                => __( 'Update Tag', 'the-wordpress-event-calendar' ),
			'add_new_item'               => __( 'Add New Tag', 'the-wordpress-event-calendar' ),
			'new_item_name'              => __( 'New Tag Name', 'the-wordpress-event-calendar' ),
			'separate_items_with_commas' => __( 'Separate tags with commas', 'the-wordpress-event-calendar' ),
			'add_or_remove_items'        => __( 'Add or remove tags', 'the-wordpress-event-calendar' ),
			'choose_from_most_used'      => __( 'Choose from the most used tags', 'the-wordpress-event-calendar' ),
			'not_found'                  => __( 'No tags found.', 'the-wordpress-event-calendar' ),
			'menu_name'                  => __( 'Tags', 'the-wordpress-event-calendar' ),
		);

		register_taxonomy( 'twec_event_tag', array( 'twec_event' ), array(
			'hierarchical'          => false,
			'labels'                => $tag_labels,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => true,
			'rewrite'               => array( 'slug' => 'event-tag' ),
			'show_in_rest'          => true,
		) );
	}
}

// Initialize post types
add_action( 'init', array( new TWEC_Post_Types(), 'register_post_types' ) );
add_action( 'init', array( new TWEC_Post_Types(), 'register_taxonomies' ) );

