<?php
/**
 * W2F_PC_Admin_Menu class
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu handler for PC Configurator.
 *
 * @class    W2F_PC_Admin_Menu
 * @version  1.0.0
 */
class W2F_PC_Admin_Menu {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Admin_Menu
	 */
	protected static $_instance = null;

	/**
	 * Main W2F_PC_Admin_Menu instance.
	 *
	 * @static
	 * @return W2F_PC_Admin_Menu - Main instance
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Add admin menu pages.
	 */
	public function add_admin_menu() {
		// Main menu page.
		add_menu_page(
			__( 'PC Configurator', 'w2f-pc-configurator' ),
			__( 'PC Configurator', 'w2f-pc-configurator' ),
			'manage_woocommerce',
			'w2f-pc-configurator',
			array( $this, 'render_settings_page' ),
			'dashicons-desktop',
			56
		);

		// Settings (main page - same as parent).
		add_submenu_page(
			'w2f-pc-configurator',
			__( 'PC Configurator Settings', 'w2f-pc-configurator' ),
			__( 'Settings', 'w2f-pc-configurator' ),
			'manage_woocommerce',
			'w2f-pc-configurator',
			array( $this, 'render_settings_page' )
		);

		// Compatibility Rules.
		add_submenu_page(
			'w2f-pc-configurator',
			__( 'PC Compatibility Rules', 'w2f-pc-configurator' ),
			__( 'Compatibility Rules', 'w2f-pc-configurator' ),
			'manage_woocommerce',
			'w2f-pc-compatibility',
			array( $this, 'render_compatibility_page' )
		);

		// Bulk Update.
		add_submenu_page(
			'w2f-pc-configurator',
			__( 'Bulk Update Products', 'w2f-pc-configurator' ),
			__( 'Bulk Update', 'w2f-pc-configurator' ),
			'manage_woocommerce',
			'w2f-pc-bulk-update',
			array( $this, 'render_bulk_update_page' )
		);

	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		include W2F_PC()->plugin_path() . '/includes/admin/views/html-admin-menu.php';
	}

	/**
	 * Render compatibility rules page.
	 */
	public function render_compatibility_page() {
		$compatibility_manager = W2F_PC_Compatibility_Manager::instance();
		$rules = $compatibility_manager->get_rules();
		$rule_id = isset( $_GET['edit'] ) ? sanitize_text_field( $_GET['edit'] ) : '';
		$rule = $rule_id ? $compatibility_manager->get_rule( $rule_id ) : false;
		$conditions = $rule && isset( $rule['conditions'] ) ? $rule['conditions'] : array();

		include W2F_PC()->plugin_path() . '/includes/admin/views/html-compatibility-rules.php';
	}

	/**
	 * Render bulk update page.
	 */
	public function render_bulk_update_page() {
		// Ensure bulk updater class is loaded.
		if ( ! class_exists( 'W2F_PC_Bulk_Updater' ) ) {
			require_once W2F_PC()->plugin_path() . '/includes/admin/class-w2f-pc-bulk-updater.php';
		}
		
		include W2F_PC()->plugin_path() . '/includes/admin/views/html-bulk-update.php';
	}

}

