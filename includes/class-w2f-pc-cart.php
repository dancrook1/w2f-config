<?php
/**
 * W2F_PC_Cart class
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart integration for PC Configurator products.
 *
 * @class    W2F_PC_Cart
 * @version  1.0.0
 */
class W2F_PC_Cart {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Cart
	 */
	protected static $_instance = null;

	/**
	 * Main W2F_PC_Cart instance.
	 *
	 * @static
	 * @return W2F_PC_Cart - Main instance
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
		// Add to cart validation.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'add_to_cart_validation' ), 10, 6 );

		// Add configuration to cart item data.
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );

		// Get cart item from session.
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 3 );

		// Display configuration in cart.
		add_filter( 'woocommerce_cart_item_name', array( $this, 'cart_item_name' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'cart_item_price' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'cart_item_data' ), 10, 2 );

		// Display configuration in checkout (uses same filter as cart).
		// The woocommerce_get_item_data filter is already hooked above.

		// Add configuration to order items.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );

		// Display configuration in order details.
		add_filter( 'woocommerce_order_item_name', array( $this, 'order_item_name' ), 10, 2 );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'format_order_item_meta' ), 10, 2 );

		// Calculate cart item price.
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_cart_item_price' ), 10, 1 );
	}

	/**
	 * Validate add to cart.
	 *
	 * @param  boolean $passed
	 * @param  int     $product_id
	 * @param  int     $quantity
	 * @param  int     $variation_id
	 * @param  array   $variations
	 * @param  array   $cart_item_data
	 * @return boolean
	 */
	public function add_to_cart_validation( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
		$product = wc_get_product( $product_id );
		if ( ! w2f_pc_is_configurator_product( $product ) ) {
			return $passed;
		}

		// Force quantity to 1 for configurator products.
		if ( $quantity > 1 ) {
			wc_add_notice( __( 'You can only add one configured PC to the cart at a time.', 'w2f-pc-configurator' ), 'error' );
			return false;
		}

		// Get configuration from cart_item_data or POST (in case validation runs before add_cart_item_data).
		$configuration = array();
		if ( isset( $cart_item_data['w2f_pc_configuration'] ) && is_array( $cart_item_data['w2f_pc_configuration'] ) ) {
			$configuration = $cart_item_data['w2f_pc_configuration'];
		} elseif ( isset( $_POST['w2f_pc_configuration'] ) && is_array( $_POST['w2f_pc_configuration'] ) ) {
			// Process POST data directly.
			foreach ( $_POST['w2f_pc_configuration'] as $component_id => $product_id_value ) {
				$product_id_value = intval( $product_id_value );
				if ( $product_id_value > 0 ) {
					$configuration[ sanitize_key( $component_id ) ] = $product_id_value;
				}
			}
			// Store in cart_item_data for later use.
			$cart_item_data['w2f_pc_configuration'] = $configuration;
		}

		$configurator_product = w2f_pc_get_configurator_product( $product );

		// Validate required components.
		$components = $configurator_product->get_components();
		$has_required_selection = false;
		foreach ( $components as $component_id => $component ) {
			if ( ! $component->is_optional() ) {
				if ( empty( $configuration[ $component_id ] ) || ! isset( $configuration[ $component_id ] ) ) {
					wc_add_notice( sprintf( __( 'Please select a product for %s.', 'w2f-pc-configurator' ), $component->get_title() ), 'error' );
					return false;
				}
				$has_required_selection = true;
			}
		}

		// Ensure at least one component is selected (even if all are optional).
		if ( empty( $configuration ) ) {
			wc_add_notice( __( 'Please configure your PC before adding to cart.', 'w2f-pc-configurator' ), 'error' );
			return false;
		}

		// Check compatibility.
		$compatibility_manager = W2F_PC_Compatibility_Manager::instance();
		$compatibility_result = $compatibility_manager->check_compatibility( $configuration, $configurator_product );
		if ( ! $compatibility_result['valid'] ) {
			foreach ( $compatibility_result['errors'] as $error ) {
				wc_add_notice( $error, 'error' );
			}
			return false;
		}

		// Check if this exact configuration is already in the cart.
		if ( ! WC()->cart->is_empty() ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( $cart_item['product_id'] === $product_id && isset( $cart_item['w2f_pc_configuration'] ) ) {
					// Compare configurations - they must match exactly.
					$existing_config = $cart_item['w2f_pc_configuration'];
					// Normalize arrays for comparison (sort keys and ensure same types).
					$existing_config_normalized = array_map( 'intval', $existing_config );
					$configuration_normalized = array_map( 'intval', $configuration );
					ksort( $existing_config_normalized );
					ksort( $configuration_normalized );
					if ( $existing_config_normalized === $configuration_normalized ) {
						wc_add_notice( __( 'This configuration is already in your cart.', 'w2f-pc-configurator' ), 'error' );
						return false;
					}
				}
			}
		}

		return $passed;
	}

	/**
	 * Add configuration to cart item data.
	 *
	 * @param  array $cart_item_data
	 * @param  int   $product_id
	 * @param  int   $variation_id
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		$product = wc_get_product( $product_id );
		if ( ! w2f_pc_is_configurator_product( $product ) ) {
			return $cart_item_data;
		}

		if ( isset( $_POST['w2f_pc_configuration'] ) && is_array( $_POST['w2f_pc_configuration'] ) ) {
			// Process configuration, filtering out empty values but preserving structure.
			$configuration = array();
			foreach ( $_POST['w2f_pc_configuration'] as $component_id => $product_id_value ) {
				$product_id_value = intval( $product_id_value );
				// Only include non-zero values (empty selects become 0).
				if ( $product_id_value > 0 ) {
					$configuration[ sanitize_key( $component_id ) ] = $product_id_value;
				}
			}
			$cart_item_data['w2f_pc_configuration'] = $configuration;
		}

		return $cart_item_data;
	}

	/**
	 * Get cart item from session.
	 *
	 * @param  array $cart_item
	 * @param  array $values
	 * @param  string $cart_item_key
	 * @return array
	 */
	public function get_cart_item_from_session( $cart_item, $values, $cart_item_key ) {
		if ( isset( $values['w2f_pc_configuration'] ) ) {
			$cart_item['w2f_pc_configuration'] = $values['w2f_pc_configuration'];
		}
		return $cart_item;
	}

	/**
	 * Display configuration in cart item name.
	 *
	 * @param  string $name
	 * @param  array  $cart_item
	 * @param  string $cart_item_key
	 * @return string
	 */
	public function cart_item_name( $name, $cart_item, $cart_item_key ) {
		if ( ! isset( $cart_item['w2f_pc_configuration'] ) ) {
			return $name;
		}

		$product = $cart_item['data'];
		if ( ! w2f_pc_is_configurator_product( $product ) ) {
			return $name;
		}

		// Configuration will be displayed via cart_item_data filter instead.
		return $name;
	}

	/**
	 * Display configuration in cart item data.
	 *
	 * @param  array $item_data
	 * @param  array $cart_item
	 * @return array
	 */
	public function cart_item_data( $item_data, $cart_item ) {
		if ( ! isset( $cart_item['w2f_pc_configuration'] ) ) {
			return $item_data;
		}

		$product = $cart_item['data'];
		if ( ! w2f_pc_is_configurator_product( $product ) ) {
			return $item_data;
		}

		$configurator_product = w2f_pc_get_configurator_product( $product );
		$configuration = $cart_item['w2f_pc_configuration'];

		// Add each component.
		foreach ( $configuration as $component_id => $product_id ) {
			$component = $configurator_product->get_component( $component_id );
			$selected_product = wc_get_product( $product_id );
			if ( $component && $selected_product ) {
				$item_data[] = array(
					'key'   => $component->get_title(),
					'value' => $selected_product->get_name(),
				);
			}
		}

		return $item_data;
	}

	/**
	 * Display cart item price.
	 *
	 * @param  string $price
	 * @param  array  $cart_item
	 * @param  string $cart_item_key
	 * @return string
	 */
	public function cart_item_price( $price, $cart_item, $cart_item_key ) {
		if ( ! isset( $cart_item['w2f_pc_configuration'] ) ) {
			return $price;
		}

		$product = $cart_item['data'];
		if ( ! w2f_pc_is_configurator_product( $product ) ) {
			return $price;
		}

		// Price is already calculated in calculate_cart_item_price.
		return $price;
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
		if ( ! isset( $values['w2f_pc_configuration'] ) ) {
			return;
		}

		$product = $values['data'];
		if ( ! w2f_pc_is_configurator_product( $product ) ) {
			return;
		}

		$configurator_product = w2f_pc_get_configurator_product( $product );
		$configuration = $values['w2f_pc_configuration'];

		// Store configuration as order item meta.
		$item->add_meta_data( '_w2f_pc_configuration', $configuration );

		// Add readable configuration data.
		foreach ( $configuration as $component_id => $product_id ) {
			$component = $configurator_product->get_component( $component_id );
			$selected_product = wc_get_product( $product_id );
			if ( $component && $selected_product ) {
				$item->add_meta_data( $component->get_title(), $selected_product->get_name() );
			}
		}
	}

	/**
	 * Display configuration in order item name.
	 *
	 * @param  string                $name
	 * @param  WC_Order_Item_Product $item
	 * @return string
	 */
	public function order_item_name( $name, $item ) {
		if ( ! $item->get_meta( '_w2f_pc_configuration' ) ) {
			return $name;
		}

		// Configuration will be displayed via format_order_item_meta.
		return $name;
	}

	/**
	 * Format order item meta data.
	 *
	 * @param  array                 $formatted_meta
	 * @param  WC_Order_Item_Product $item
	 * @return array
	 */
	public function format_order_item_meta( $formatted_meta, $item ) {
		$configuration = $item->get_meta( '_w2f_pc_configuration' );
		if ( ! $configuration || ! is_array( $configuration ) ) {
			return $formatted_meta;
		}

		// Remove the raw configuration meta from display.
		$formatted_meta = array_filter( $formatted_meta, function( $meta ) {
			return $meta->key !== '_w2f_pc_configuration';
		} );

		// Configuration components are already added as individual meta items.
		return $formatted_meta;
	}

	/**
	 * Calculate cart item price.
	 *
	 * @param  WC_Cart $cart
	 */
	public function calculate_cart_item_price( $cart ) {
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! isset( $cart_item['w2f_pc_configuration'] ) ) {
				continue;
			}

			$product = $cart_item['data'];
			if ( ! w2f_pc_is_configurator_product( $product ) ) {
				continue;
			}

			$configurator_product = w2f_pc_get_configurator_product( $product );
			$configuration = $cart_item['w2f_pc_configuration'];

			// Calculate price from configuration (excluding tax for cart, WooCommerce will add tax).
			$price = $configurator_product->calculate_configuration_price( $configuration, false );
			$product->set_price( $price );
		}
	}
}

