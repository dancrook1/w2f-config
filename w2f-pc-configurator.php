<?php
/**
 * Plugin Name: W2F PC Configurator
 * Plugin URI: https://wired2fire.com
 * Description: A WooCommerce plugin for selling configurable PC products with global compatibility checking, real-time pricing, and interactive configuration interface.
 * Version: 1.0.0
 * Author: Wired2Fire
 * Author URI: https://wired2fire.com
 * Text Domain: w2f-pc-configurator
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 * Custom-Order-Table: 1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * @class    W2F_PC_Configurator
 * @version  1.0.0
 */
class W2F_PC_Configurator {

	/**
	 * The plugin version
	 *
	 * @var string
	 */
	public $version = '1.0.0';

	/**
	 * The minimum required WooCommerce version.
	 *
	 * @var string
	 */
	public $required_wc = '8.0.0';

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Configurator
	 */
	protected static $_instance = null;

	/**
	 * Main W2F_PC_Configurator instance.
	 *
	 * Ensures only one instance of W2F_PC_Configurator is loaded or can be loaded.
	 *
	 * @static
	 * @return W2F_PC_Configurator - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Foul!', 'w2f-pc-configurator' ), '1.0.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Foul!', 'w2f-pc-configurator' ), '1.0.0' );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Declare HPOS (High-Performance Order Storage) compatibility early.
		add_filter( 'woocommerce_feature_compatibility', array( $this, 'declare_hpos_compatibility' ), 10, 1 );
		
		// Entry point.
		add_action( 'plugins_loaded', array( $this, 'initialize_plugin' ), 9 );
	}

	/**
	 * Auto-load in-accessible properties.
	 *
	 * @param  mixed $key
	 * @return mixed
	 */
	public function __get( $key ) {
		if ( in_array( $key, array( 'compatibility', 'cart', 'order', 'display' ) ) ) {
			$classname = 'W2F_PC_' . ucfirst( $key );
			if ( class_exists( $classname ) ) {
				return call_user_func( array( $classname, 'instance' ) );
			}
		}
	}

	/**
	 * Gets the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Gets the plugin path.
	 *
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Plugin base path name getter.
	 *
	 * @return string
	 */
	public function plugin_basename() {
		return plugin_basename( __FILE__ );
	}

	/**
	 * Declare HPOS (High-Performance Order Storage) compatibility.
	 *
	 * @param  array $compatibility Array of compatibility features.
	 * @return array
	 */
	public function declare_hpos_compatibility( $compatibility ) {
		$compatibility['custom_order_tables'] = true;
		return $compatibility;
	}

	/**
	 * Fire in the hole!
	 */
	public function initialize_plugin() {

		$this->define_constants();

		// WC version sanity check.
		if ( ! function_exists( 'WC' ) || version_compare( WC()->version, $this->required_wc ) < 0 ) {
			/* translators: Required version. */
			$notice = sprintf( __( 'W2F PC Configurator requires at least WooCommerce <strong>%s</strong>.', 'w2f-pc-configurator' ), $this->required_wc );
			add_action( 'admin_notices', function() use ( $notice ) {
				echo '<div class="error"><p>' . wp_kses_post( $notice ) . '</p></div>';
			} );
			return false;
		}

		// PHP version check.
		if ( ! function_exists( 'phpversion' ) || version_compare( phpversion(), '7.4.0', '<' ) ) {
			/* translators: %1$s: PHP version, %2$s: Documentation link. */
			$notice = sprintf(
				__( 'W2F PC Configurator requires at least PHP <strong>%1$s</strong>.', 'w2f-pc-configurator' ),
				'7.4.0'
			);
			add_action( 'admin_notices', function() use ( $notice ) {
				echo '<div class="error"><p>' . wp_kses_post( $notice ) . '</p></div>';
			} );
			return false;
		}

		$this->includes();

		// Check if HPOS is enabled (for debugging/logging purposes).
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			$hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
			// HPOS is supported via proper WooCommerce APIs, no action needed.
		}

		// Register product class filter (needed on both frontend and admin).
		add_filter( 'woocommerce_product_class', array( $this, 'woocommerce_product_class' ), 10, 4 );

		// Initialize core classes.
		W2F_PC_Compatibility_Manager::instance();
		W2F_PC_Cart::instance();
		W2F_PC_Order::instance();
		W2F_PC_Display::instance();
		W2F_PC_Ajax::instance();

		// Initialize admin.
		if ( is_admin() ) {
			W2F_PC_Admin::instance();
		}

		// Load translations hook.
		add_action( 'init', array( $this, 'load_translation' ) );
	}

	/**
	 * Define constants.
	 */
	public function define_constants() {
		$this->maybe_define_constant( 'W2F_PC_VERSION', $this->version );
		$this->maybe_define_constant( 'W2F_PC_ABSPATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
		$this->maybe_define_constant( 'W2F_PC_PLUGIN_FILE', __FILE__ );
	}

	/**
	 * Define constant if not present.
	 *
	 * @param  string $name
	 * @param  mixed  $value
	 * @return boolean
	 */
	protected function maybe_define_constant( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Includes.
	 */
	public function includes() {

		// Compatibility functions.
		require_once W2F_PC_ABSPATH . 'includes/w2f-pc-compatibility.php';

		// Helper functions.
		require_once W2F_PC_ABSPATH . 'includes/w2f-pc-functions.php';

		// Template functions.
		require_once W2F_PC_ABSPATH . 'includes/w2f-pc-template-functions.php';

		// Product class.
		require_once W2F_PC_ABSPATH . 'includes/class-w2f-pc-product.php';

		// Component abstraction.
		require_once W2F_PC_ABSPATH . 'includes/class-w2f-pc-component.php';

		// Compatibility manager.
		require_once W2F_PC_ABSPATH . 'includes/class-w2f-pc-compatibility-manager.php';

		// Cart integration.
		require_once W2F_PC_ABSPATH . 'includes/class-w2f-pc-cart.php';

		// Order integration.
		require_once W2F_PC_ABSPATH . 'includes/class-w2f-pc-order.php';

		// Frontend display.
		require_once W2F_PC_ABSPATH . 'includes/class-w2f-pc-display.php';

		// AJAX handlers.
		require_once W2F_PC_ABSPATH . 'includes/class-w2f-pc-ajax.php';

		// Admin functions and meta-boxes.
		if ( is_admin() ) {
			$this->admin_includes();
		}
	}

	/**
	 * Loads the Admin filters / hooks.
	 */
	private function admin_includes() {
		// Admin hooks.
		require_once W2F_PC_ABSPATH . 'includes/admin/class-w2f-pc-admin.php';

		// Product data meta box.
		require_once W2F_PC_ABSPATH . 'includes/admin/class-w2f-pc-meta-box-product-data.php';

		// Compatibility rules meta box.
		require_once W2F_PC_ABSPATH . 'includes/admin/class-w2f-pc-meta-box-compatibility.php';

		// Bulk updater.
		require_once W2F_PC_ABSPATH . 'includes/admin/class-w2f-pc-bulk-updater.php';
	}

	/**
	 * Load textdomain.
	 */
	public function load_translation() {
		load_plugin_textdomain( 'w2f-pc-configurator', false, dirname( $this->plugin_basename() ) . '/languages/' );
	}

	/**
	 * Plugin version getter.
	 *
	 * @param  boolean $base
	 * @param  string  $version
	 * @return string
	 */
	public function plugin_version( $base = false, $version = '' ) {
		$version = $version ? $version : $this->version;
		if ( $base ) {
			$version_parts = explode( '-', $version );
			$version       = sizeof( $version_parts ) > 1 ? $version_parts[0] : $version;
		}
		return $version;
	}

	/**
	 * Use correct product class.
	 *
	 * @param  string $classname
	 * @param  string $product_type
	 * @param  string $post_type
	 * @param  int    $product_id
	 * @return string
	 */
	public function woocommerce_product_class( $classname, $product_type, $post_type = '', $product_id = 0 ) {
		if ( 'pc_configurator' === $product_type ) {
			$classname = 'W2F_PC_Product';
		}
		return $classname;
	}
}

/**
 * Returns the main instance of W2F_PC_Configurator to prevent the need to use globals.
 *
 * @return W2F_PC_Configurator
 */
function W2F_PC() {
	return W2F_PC_Configurator::instance();
}

// Initialize plugin.
$GLOBALS['w2f_pc_configurator'] = W2F_PC();

