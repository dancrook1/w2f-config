<?php
/**
 * W2F_PC_Order class
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order integration for PC Configurator products.
 *
 * @class    W2F_PC_Order
 * @version  1.0.0
 */
class W2F_PC_Order {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Order
	 */
	protected static $_instance = null;

	/**
	 * Main W2F_PC_Order instance.
	 *
	 * @static
	 * @return W2F_PC_Order - Main instance
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
		// Save configuration to order item meta.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );

		// Display configuration in order.
		add_filter( 'woocommerce_order_item_name', array( $this, 'order_item_name' ), 10, 2 );
	}

	/**
	 * Add configuration to order item meta.
	 *
	 * @param  WC_Order_Item_Product $item
	 * @param  string                $cart_item_key
	 * @param  array                 $values
	 * @param  WC_Order              $order
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['w2f_pc_configuration'] ) ) {
			$item->add_meta_data( '_w2f_pc_configuration', $values['w2f_pc_configuration'] );
		}
	}

	/**
	 * Display configuration in order item name.
	 *
	 * @param  string $name
	 * @param  WC_Order_Item $item
	 * @return string
	 */
	public function order_item_name( $name, $item ) {
		if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			return $name;
		}

		$configuration = $item->get_meta( '_w2f_pc_configuration' );
		if ( empty( $configuration ) || ! is_array( $configuration ) ) {
			return $name;
		}

		$product = $item->get_product();
		if ( ! w2f_pc_is_configurator_product( $product ) ) {
			return $name;
		}

		$configurator_product = w2f_pc_get_configurator_product( $product );

		$name .= '<dl class="w2f_pc_configuration">';
		foreach ( $configuration as $component_id => $product_id ) {
			$component = $configurator_product->get_component( $component_id );
			$selected_product = wc_get_product( $product_id );
			if ( $component && $selected_product ) {
				$name .= '<dt>' . esc_html( $component->get_title() ) . ':</dt>';
				$name .= '<dd>' . esc_html( $selected_product->get_name() ) . '</dd>';
			}
		}
		$name .= '</dl>';

		return $name;
	}
}

