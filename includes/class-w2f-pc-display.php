<?php
/**
 * W2F_PC_Display class
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend display functions and filters.
 *
 * @class    W2F_PC_Display
 * @version  1.0.0
 */
class W2F_PC_Display {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Display
	 */
	protected static $_instance = null;

	/**
	 * Main W2F_PC_Display instance.
	 *
	 * @static
	 * @return W2F_PC_Display - Main instance
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
		// Front end scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );

		// Template function is registered in w2f-pc-template-functions.php
		// No need to register the action here as it's already registered there.
		
		add_filter( 'woocommerce_locate_template', array( $this, 'locate_template' ), 10, 3 );
		
		// Filter WooCommerce price display to ensure configurator products show price with tax.
		add_filter( 'woocommerce_get_price_html', array( $this, 'filter_price_html' ), 10, 2 );
		
		// Hide quantity input for configurator products.
		add_filter( 'woocommerce_quantity_input_args', array( $this, 'hide_quantity_input' ), 10, 2 );
	}

	/**
	 * Filter price HTML to ensure configurator products show price with tax.
	 *
	 * @param  string      $price_html
	 * @param  WC_Product  $product
	 * @return string
	 */
	public function filter_price_html( $price_html, $product ) {
		if ( ! w2f_pc_is_configurator_product( $product ) ) {
			return $price_html;
		}

		// Use the product's get_price_html method which handles tax correctly.
		$configurator_product = w2f_pc_get_configurator_product( $product );
		return $configurator_product->get_price_html( $price_html );
	}

	/**
	 * Frontend scripts and styles.
	 */
	public function frontend_scripts() {
		// Enqueue cart/checkout styles on cart and checkout pages.
		if ( is_cart() || is_checkout() || is_account_page() ) {
			wp_enqueue_style( 'w2f-pc-cart-checkout', W2F_PC()->plugin_url() . '/assets/css/cart-checkout.css', array(), W2F_PC()->plugin_version() );
		}

		if ( ! is_product() ) {
			return;
		}

		global $post;
		$product = wc_get_product( $post->ID );
		if ( ! w2f_pc_is_configurator_product( $product ) ) {
			return;
		}

		// Enqueue WooCommerce add to cart script for AJAX functionality.
		if ( function_exists( 'wc_get_template' ) ) {
			wp_enqueue_script( 'wc-add-to-cart' );
		}

		// Enqueue Red Hat Text font from Google Fonts.
		wp_enqueue_style( 'w2f-pc-red-hat-text', 'https://fonts.googleapis.com/css2?family=Red+Hat+Text:ital,wght@0,300..700;1,300..700&display=swap', array(), null );
		
		// Enqueue jsPDF library for PDF generation.
		wp_enqueue_script( 'jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', array(), '2.5.1', true );
		
		wp_enqueue_script( 'w2f-pc-configurator', W2F_PC()->plugin_url() . '/assets/js/frontend/configurator.js', array( 'jquery', 'wc-add-to-cart', 'jspdf' ), W2F_PC()->plugin_version(), true );
		wp_enqueue_script( 'w2f-pc-compatibility', W2F_PC()->plugin_url() . '/assets/js/frontend/compatibility.js', array( 'jquery', 'w2f-pc-configurator' ), W2F_PC()->plugin_version(), true );
		wp_enqueue_script( 'w2f-pc-price-calculator', W2F_PC()->plugin_url() . '/assets/js/frontend/price-calculator.js', array( 'jquery', 'w2f-pc-configurator' ), W2F_PC()->plugin_version(), true );
		wp_enqueue_style( 'w2f-pc-frontend', W2F_PC()->plugin_url() . '/assets/css/frontend.css', array( 'w2f-pc-red-hat-text' ), W2F_PC()->plugin_version() );

		$configurator_product = w2f_pc_get_configurator_product( $product );
		$default_configuration = $configurator_product->get_default_configuration();
		// Calculate default price from components (sum + tax).
		$default_price = $configurator_product->calculate_configuration_price( $default_configuration, true );

		// Get currency formatting from WooCommerce.
		$currency_symbol = get_woocommerce_currency_symbol();
		$currency_position = get_option( 'woocommerce_currency_pos' );
		$price_decimals = wc_get_price_decimals();
		$price_decimal_sep = wc_get_price_decimal_separator();
		$price_thousand_sep = wc_get_price_thousand_separator();

		wp_localize_script( 'w2f-pc-configurator', 'w2f_pc_params', array(
			'ajax_url'            => admin_url( 'admin-ajax.php' ),
			'nonce'               => wp_create_nonce( 'w2f-pc-configurator' ),
			'product_id'          => $product->get_id(),
			'site_url'            => home_url(),
			'default_configuration' => $default_configuration,
			'default_price'       => $default_price,
			'currency'            => array(
				'symbol'          => $currency_symbol,
				'position'        => $currency_position,
				'decimals'        => $price_decimals,
				'decimal_sep'     => $price_decimal_sep,
				'thousand_sep'   => $price_thousand_sep,
			),
			'i18n'                => array(
				'loading'          => __( 'Loading...', 'w2f-pc-configurator' ),
				'incompatible'      => __( 'Incompatible selection', 'w2f-pc-configurator' ),
				'select_component' => __( 'Please select a component', 'w2f-pc-configurator' ),
				'components_selected' => __( 'components selected', 'w2f-pc-configurator' ),
				'reset_confirm'    => __( 'Reset to default configuration?', 'w2f-pc-configurator' ),
				'warranty_required' => __( 'Please select a warranty option.', 'w2f-pc-configurator' ),
			),
		) );
	}

	/**
	 * Configurator add to cart template.
	 */
	public function configurator_add_to_cart() {
		global $product;
		
		if ( ! $product ) {
			$product = wc_get_product( get_the_ID() );
		}
		
		if ( ! $product || ! w2f_pc_is_configurator_product( $product ) ) {
			return;
		}

		$configurator_product = w2f_pc_get_configurator_product( $product );
		if ( ! $configurator_product ) {
			return;
		}

		$components = $configurator_product->get_components();
		$default_configuration = $configurator_product->get_default_configuration();

		if ( empty( $components ) ) {
			echo '<p>' . esc_html__( 'No components configured for this product.', 'w2f-pc-configurator' ) . '</p>';
			return;
		}

		wc_get_template( 'single-product/add-to-cart/pc-configurator.php', array(
			'product'              => $configurator_product,
			'components'           => $components,
			'default_configuration' => $default_configuration,
		), '', W2F_PC()->plugin_path() . '/templates/' );
	}

	/**
	 * Locate template.
	 *
	 * @param  string $template
	 * @param  string $template_name
	 * @param  string $template_path
	 * @return string
	 */
	public function locate_template( $template, $template_name, $template_path ) {
		if ( 'single-product/add-to-cart/pc-configurator.php' === $template_name ) {
			$plugin_template = W2F_PC()->plugin_path() . '/templates/single-product/add-to-cart/pc-configurator.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		return $template;
	}

	/**
	 * Hide quantity input for configurator products.
	 *
	 * @param  array      $args
	 * @param  WC_Product $product
	 * @return array
	 */
	public function hide_quantity_input( $args, $product ) {
		if ( w2f_pc_is_configurator_product( $product ) ) {
			$args['min_value'] = 1;
			$args['max_value'] = 1;
			$args['input_value'] = 1;
		}
		return $args;
	}
}

