<?php
/**
 * W2F_PC_Admin class
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin functions and hooks.
 *
 * @class    W2F_PC_Admin
 * @version  1.0.0
 */
class W2F_PC_Admin {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Admin
	 */
	protected static $_instance = null;

	/**
	 * Main W2F_PC_Admin instance.
	 *
	 * @static
	 * @return W2F_PC_Admin - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register product type (admin only).
		add_filter( 'product_type_selector', array( $this, 'add_product_type' ) );

		// Admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		// Initialize meta boxes.
		add_action( 'init', array( $this, 'init_meta_boxes' ) );
		
		// Load admin menu class.
		require_once W2F_PC_ABSPATH . 'includes/admin/class-w2f-pc-admin-menu.php';
	}

	/**
	 * Add PC Configurator to product type selector.
	 *
	 * @param  array $types
	 * @return array
	 */
	public function add_product_type( $types ) {
		$types['pc_configurator'] = __( 'PC Configurator', 'w2f-pc-configurator' );
		return $types;
	}


	/**
	 * Initialize meta boxes.
	 */
	public function init_meta_boxes() {
		W2F_PC_Meta_Box_Product_Data::init();
		W2F_PC_Meta_Box_Compatibility::init();
		
		// Initialize admin menu.
		W2F_PC_Admin_Menu::instance();
	}

	/**
	 * Admin scripts and styles.
	 *
	 * @param string $hook
	 */
	public function admin_scripts( $hook ) {
		global $post;

		// Enqueue for product edit pages.
		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			if ( $post && 'product' === $post->post_type ) {
				$product = wc_get_product( $post->ID );
				if ( $product && 'pc_configurator' === $product->get_type() ) {
					// Enqueue WooCommerce product search scripts.
					wp_enqueue_script( 'selectWoo' );
					wp_enqueue_style( 'select2' );

					// Enqueue enhanced select for categories.
					wp_enqueue_script( 'wc-enhanced-select' );

					wp_enqueue_script( 'w2f-pc-admin', W2F_PC()->plugin_url() . '/assets/js/admin/admin.js', array( 'jquery', 'jquery-ui-sortable', 'selectWoo' ), W2F_PC()->plugin_version(), true );
					wp_enqueue_style( 'w2f-pc-admin', W2F_PC()->plugin_url() . '/assets/css/admin.css', array(), W2F_PC()->plugin_version() );

					wp_localize_script( 'w2f-pc-admin', 'w2f_pc_admin_params', array(
						'ajax_url'  => admin_url( 'admin-ajax.php' ),
						'nonce'     => wp_create_nonce( 'w2f-pc-admin' ),
						'product_id' => $post->ID,
						'i18n'      => array(
							'tab_name' => __( 'Tab name', 'w2f-pc-configurator' ),
							'remove'   => __( 'Remove', 'w2f-pc-configurator' ),
							'no_tab'   => __( 'No Tab', 'w2f-pc-configurator' ),
						),
					) );
				}
			}
		}

		// Enqueue for configurator admin pages.
		if ( strpos( $hook, 'w2f-pc-' ) !== false ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_style( 'select2' );
			wp_enqueue_style( 'w2f-pc-admin', W2F_PC()->plugin_url() . '/assets/css/admin.css', array(), W2F_PC()->plugin_version() );
		}
	}
}

