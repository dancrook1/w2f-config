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
	 * Note: Configuration is no longer displayed here since components are now separate line items.
	 *
	 * @param  string $name
	 * @param  WC_Order_Item $item
	 * @return string
	 */
	public function order_item_name( $name, $item ) {
		// Configuration is now displayed as separate line items, so we don't show it here.
		// This prevents duplicate/messy display of configuration data.
		return $name;
	}
}

