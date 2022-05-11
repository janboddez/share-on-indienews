<?php
/**
 * Main plugin class.
 *
 * @package Share_On_IndieNews
 */

namespace Share_On_IndieNews;

/**
 * Main plugin class.
 */
class Share_On_IndieNews {
	/**
	 * This plugin's single instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Share_On_IndieNews $instance Plugin instance.
	 */
	private static $instance;

	/**
	 * `Post_Handler` instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Post_Handler $instance `Post_Handler` instance.
	 */
	private $post_handler;

	/**
	 * Returns the single instance of this class.
	 *
	 * @since 0.1.0
	 *
	 * @return Share_On_IndieNews Single class instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {
		$this->post_handler = new Post_Handler();
		$this->post_handler->register();
	}

	/**
	 * Interacts with WordPress's Plugin API.
	 *
	 * @since 0.1.0
	 */
	public function register() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Enables localization.
	 *
	 * @since 0.1.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'share-on-indienews', false, basename( dirname( dirname( __FILE__ ) ) ) . '/languages' );
	}

	/**
	 * Returns `Post_Handler` instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Post_Handler This plugin's `Post_Handler` instance.
	 */
	public function get_post_handler() {
		return $this->post_handler;
	}
}
