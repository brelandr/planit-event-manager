<?php
/**
 * Register all actions and filters for the plugin.
 *
 * @package    The_Event_Calendar
 * @subpackage includes
 * @since      1.0.0
 * @file       class-twec-loader.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register all actions and filters for the plugin.
 *
 * Maintains a list of all hooks registered with WordPress and provides
 * a mechanism to register and unregister hooks.
 */
class TWEC_Loader {

	/**
	 * The array of actions registered with WordPress.
	 *
	 * @var array
	 */
	protected $actions;

	/**
	 * The array of filters registered with WordPress.
	 *
	 * @var array
	 */
	protected $filters;

	/**
	 * Initialize the collections used to maintain the actions and filters.
	 */
	public function __construct() {
		$this->actions = array();
		$this->filters = array();
	}

	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @param string $hook          Hook name.
	 * @param object $component     Component object.
	 * @param string $callback      Callback method.
	 * @param int    $priority      Priority.
	 * @param int    $accepted_args Number of accepted arguments.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @param string $hook          Hook name.
	 * @param object $component     Component object.
	 * @param string $callback      Callback method.
	 * @param int    $priority      Priority.
	 * @param int    $accepted_args Number of accepted arguments.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * A utility function that is used to register the actions and hooks.
	 *
	 * @param array  $hooks         Existing hooks.
	 * @param string $hook          Hook name.
	 * @param object $component     Component object.
	 * @param string $callback      Callback method.
	 * @param int    $priority      Priority.
	 * @param int    $accepted_args Number of accepted arguments.
	 * @return array Modified hooks array.
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return $hooks;
	}

	/**
	 * Register the filters and actions with WordPress.
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}
