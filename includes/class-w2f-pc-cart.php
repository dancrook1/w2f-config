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
	 * Static flag to prevent infinite recursion.
	 *
	 * @var bool
	 */
	protected static $processing_components = false;

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
		
		// Hide component prices from frontend order display (but keep in backend).
		// Note: Components are only separate items in orders, not in cart.

		// Display configuration in checkout (uses same filter as cart).
		// The woocommerce_get_item_data filter is already hooked above.

		// Add configuration to order items.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		
		// Add component products as separate line items for ERP integration.
		// Use priority 5 to run before other hooks and ensure main product is set to £0 before item is added.
		// Only register hook if not in admin (to prevent memory issues when viewing products).
		if ( ! is_admin() || wp_doing_ajax() ) {
			add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_component_line_items' ), 5, 4 );
		}

		// Display configuration in order details.
		add_filter( 'woocommerce_order_item_name', array( $this, 'order_item_name' ), 10, 2 );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'format_order_item_meta' ), 10, 2 );
		
		// Hide discount-related meta from frontend checkout display.
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_discount_meta_from_checkout' ), 10, 1 );
		
		// Hide component prices from frontend order display.
		add_filter( 'woocommerce_order_formatted_line_subtotal', array( $this, 'hide_component_price_in_order' ), 10, 2 );
		add_filter( 'woocommerce_order_item_display_meta_value', array( $this, 'hide_price_meta_value' ), 10, 3 );
		
		// Style component items as child items (indented).
		add_filter( 'woocommerce_order_item_class', array( $this, 'order_item_class' ), 10, 2 );

		// Calculate cart item price.
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_cart_item_price' ), 10, 1 );
		
		// Ensure main product stays at £0 after order totals are calculated.
		add_action( 'woocommerce_checkout_order_created', array( $this, 'ensure_main_product_zero_price' ), 10, 1 );
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

		// Get quantities from POST or cart_item_data.
		$quantities = array();
		if ( isset( $cart_item_data['w2f_pc_configuration_quantities'] ) && is_array( $cart_item_data['w2f_pc_configuration_quantities'] ) ) {
			$quantities = $cart_item_data['w2f_pc_configuration_quantities'];
		} elseif ( isset( $_POST['w2f_pc_configuration_quantity'] ) && is_array( $_POST['w2f_pc_configuration_quantity'] ) ) {
			foreach ( $_POST['w2f_pc_configuration_quantity'] as $component_id => $quantity_value ) {
				$quantities[ sanitize_key( $component_id ) ] = max( 1, intval( $quantity_value ) );
			}
		}

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
			
			// Validate quantities if quantity is enabled for this component.
			if ( $component->enable_quantity() && isset( $configuration[ $component_id ] ) && $configuration[ $component_id ] > 0 ) {
				$quantity = isset( $quantities[ $component_id ] ) ? intval( $quantities[ $component_id ] ) : $component->get_min_quantity();
				$min_quantity = $component->get_min_quantity();
				$max_quantity = $component->get_max_quantity();
				
				if ( $quantity < $min_quantity ) {
					wc_add_notice( sprintf( __( 'Minimum quantity for %s is %d.', 'w2f-pc-configurator' ), $component->get_title(), $min_quantity ), 'error' );
					return false;
				}
				
				if ( $quantity > $max_quantity ) {
					wc_add_notice( sprintf( __( 'Maximum quantity for %s is %d.', 'w2f-pc-configurator' ), $component->get_title(), $max_quantity ), 'error' );
					return false;
				}
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

		// Process quantities.
		if ( isset( $_POST['w2f_pc_configuration_quantity'] ) && is_array( $_POST['w2f_pc_configuration_quantity'] ) ) {
			$quantities = array();
			foreach ( $_POST['w2f_pc_configuration_quantity'] as $component_id => $quantity_value ) {
				$quantities[ sanitize_key( $component_id ) ] = max( 1, intval( $quantity_value ) );
			}
			$cart_item_data['w2f_pc_configuration_quantities'] = $quantities;
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
		if ( isset( $values['w2f_pc_configuration_quantities'] ) ) {
			$cart_item['w2f_pc_configuration_quantities'] = $values['w2f_pc_configuration_quantities'];
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

		// Get quantities.
		$quantities = isset( $cart_item['w2f_pc_configuration_quantities'] ) ? $cart_item['w2f_pc_configuration_quantities'] : array();

		// Add each component.
		foreach ( $configuration as $component_id => $product_id ) {
			$component = $configurator_product->get_component( $component_id );
			$selected_product = wc_get_product( $product_id );
			if ( $component && $selected_product ) {
				$product_name = $selected_product->get_name();
				
				// Add quantity if enabled for this component.
				if ( $component->enable_quantity() && isset( $quantities[ $component_id ] ) && $quantities[ $component_id ] > 1 ) {
					$product_name .= ' (Qty: ' . $quantities[ $component_id ] . ')';
				}
				
				$item_data[] = array(
					'key'   => $component->get_title(),
					'value' => $product_name,
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
		$quantities = isset( $values['w2f_pc_configuration_quantities'] ) ? $values['w2f_pc_configuration_quantities'] : array();

		// Store configuration as order item meta (for reference, but components are now separate line items).
		$item->add_meta_data( '_w2f_pc_configuration', $configuration );
		
		// Store quantities as order item meta.
		if ( ! empty( $quantities ) ) {
			$item->add_meta_data( '_w2f_pc_configuration_quantities', $quantities );
		}

		// Note: Component products are now added as separate line items in add_component_line_items()
		// so they can be properly tracked by ERP systems. We still store the configuration here
		// for reference and display purposes.
	}

	/**
	 * Hide discount-related meta from frontend checkout display.
	 *
	 * @param  array $hidden_meta Array of hidden meta keys.
	 * @return array
	 */
	public function hide_discount_meta_from_checkout( $hidden_meta ) {
		// Only hide on frontend, not in admin.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $hidden_meta;
		}
		
		$discount_meta_keys = array(
			'Component Total (Before Discount)',
			'Discount Percentage',
			'Discount Amount',
			'Final Total',
			'Component Total',
		);
		
		return array_merge( $hidden_meta, $discount_meta_keys );
	}

	/**
	 * Display configuration in order item name.
	 *
	 * @param  string                $name
	 * @param  WC_Order_Item_Product $item
	 * @return string
	 */
	public function order_item_name( $name, $item ) {
		// If this is a component (child item), indent it visually.
		if ( $item->get_meta( '_w2f_pc_is_child_item' ) === 'yes' ) {
			$component_title = $item->get_meta( '_w2f_pc_component_title' );
			if ( $component_title ) {
				$name = '<span class="w2f-pc-child-item-indent">' . esc_html( $component_title ) . ': </span>' . $name;
			} else {
				$name = '<span class="w2f-pc-child-item-indent">— </span>' . $name;
			}
		}
		
		return $name;
	}

	/**
	 * Add CSS class to component items to style them as child items.
	 *
	 * @param  string                $class
	 * @param  WC_Order_Item_Product $item
	 * @return string
	 */
	public function order_item_class( $class, $item ) {
		if ( $item->get_meta( '_w2f_pc_is_child_item' ) === 'yes' ) {
			$class .= ' w2f-pc-child-item';
		}
		if ( $item->get_meta( '_w2f_pc_is_configurator' ) === 'yes' ) {
			$class .= ' w2f-pc-parent-item';
		}
		return $class;
	}

	/**
	 * Format order item meta data.
	 *
	 * @param  array                 $formatted_meta
	 * @param  WC_Order_Item_Product $item
	 * @return array
	 */
	public function format_order_item_meta( $formatted_meta, $item ) {
		// Only filter on frontend, not in admin.
		$is_frontend = ! is_admin() || wp_doing_ajax();
		
		$configuration = $item->get_meta( '_w2f_pc_configuration' );
		if ( ! $configuration || ! is_array( $configuration ) ) {
			// Still filter discount meta even if not a configurator product.
			if ( $is_frontend ) {
				$formatted_meta = array_filter( $formatted_meta, function( $meta ) {
					$discount_keys = array(
						'Component Total (Before Discount)',
						'Discount Percentage',
						'Discount Amount',
						'Final Total',
						'Component Total',
					);
					return ! in_array( $meta->key, $discount_keys, true );
				} );
			}
			return $formatted_meta;
		}

		// Remove the raw configuration meta from display.
		$formatted_meta = array_filter( $formatted_meta, function( $meta ) use ( $is_frontend ) {
			// Always remove raw configuration meta.
			if ( $meta->key === '_w2f_pc_configuration' ) {
				return false;
			}
			
			// On frontend, remove discount-related meta fields.
			if ( $is_frontend ) {
				$discount_keys = array(
					'Component Total (Before Discount)',
					'Discount Percentage',
					'Discount Amount',
					'Final Total',
					'Component Total',
				);
				if ( in_array( $meta->key, $discount_keys, true ) ) {
					return false;
				}
			}
			
			return true;
		} );

		// Configuration components are already added as individual meta items.
		return $formatted_meta;
	}

	/**
	 * Add component products as separate line items for ERP integration.
	 *
	 * @param  WC_Order_Item_Product $item
	 * @param  string                $cart_item_key
	 * @param  array                 $values
	 * @param  WC_Order              $order
	 */
	public function add_component_line_items( $item, $cart_item_key, $values, $order ) {
		// Only run during checkout, not in admin or other contexts.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		// Must have valid cart item key (only exists during checkout).
		if ( empty( $cart_item_key ) ) {
			return;
		}

		// Prevent infinite loop - don't process items we're adding (component items).
		if ( $item->get_meta( '_w2f_pc_is_child_item' ) === 'yes' || $item->get_meta( '_w2f_pc_is_component' ) === 'yes' ) {
			return;
		}

		// Prevent infinite recursion with static flag.
		if ( self::$processing_components ) {
			return;
		}

		if ( ! isset( $values['w2f_pc_configuration'] ) ) {
			return;
		}

		$product = $values['data'];
		if ( ! w2f_pc_is_configurator_product( $product ) ) {
			return;
		}

		// Set flag to prevent recursion.
		self::$processing_components = true;

		$configurator_product = w2f_pc_get_configurator_product( $product );
		$configuration = $values['w2f_pc_configuration'];
		$quantities = isset( $values['w2f_pc_configuration_quantities'] ) ? $values['w2f_pc_configuration_quantities'] : array();

		// Mark the main configurator product item.
		$item->add_meta_data( '_w2f_pc_is_configurator', 'yes' );
		$item->add_meta_data( '_w2f_pc_has_components', 'yes' );

		// Set main product to £0 - components will have the pricing.
		$item->set_subtotal( 0 );
		$item->set_total( 0 );
		$item->set_subtotal_tax( 0 );
		$item->set_total_tax( 0 );
		$item->set_taxes( array() );

		// Check if configuration matches default.
		$is_default = $configurator_product->is_default_configuration( $configuration );
		$default_price = $configurator_product->get_default_price();
		
		// Calculate total component price before any discount.
		$total_component_price_before_discount = 0;
		$component_line_totals = array();

		// First pass: calculate all component prices at full price.
		foreach ( $configuration as $component_id => $product_id ) {
			$component = $configurator_product->get_component( $component_id );
			$component_product = wc_get_product( $product_id );
			
			if ( ! $component || ! $component_product ) {
				continue;
			}

			// Get quantity for this component.
			$quantity = 1;
			if ( $component->enable_quantity() && isset( $quantities[ $component_id ] ) ) {
				$quantity = max( 1, intval( $quantities[ $component_id ] ) );
			}

			// Get component product price (excluding tax) at full price.
			$component_price = wc_get_price_excluding_tax( $component_product );
			$line_subtotal_full = $component_price * $quantity;
			$total_component_price_before_discount += $line_subtotal_full;
			$component_line_totals[ $component_id ] = $line_subtotal_full;
		}

		// Calculate discount percentage if default configuration.
		$discount_percentage = 0;
		$total_discount_amount = 0;
		if ( $is_default && $default_price > 0 && $total_component_price_before_discount > 0 ) {
			// Calculate discount amount and percentage.
			$total_discount_amount = $total_component_price_before_discount - $default_price;
			$discount_percentage = ( $total_discount_amount / $total_component_price_before_discount ) * 100;
		}

		// Add each component as a separate line item.
		foreach ( $configuration as $component_id => $product_id ) {
			$component = $configurator_product->get_component( $component_id );
			$component_product = wc_get_product( $product_id );
			
			if ( ! $component || ! $component_product ) {
				continue;
			}

			// Get quantity for this component.
			$quantity = 1;
			if ( $component->enable_quantity() && isset( $quantities[ $component_id ] ) ) {
				$quantity = max( 1, intval( $quantities[ $component_id ] ) );
			}

			// Get component product price (excluding tax).
			$component_price = wc_get_price_excluding_tax( $component_product );
			$line_subtotal_full = $component_price * $quantity;
			
			// Apply percentage discount if default configuration.
			$line_subtotal = $line_subtotal_full;
			$line_total = $line_subtotal;
			if ( $is_default && $discount_percentage > 0 ) {
				// Apply the same percentage discount to this component.
				$line_discount = $line_subtotal_full * ( $discount_percentage / 100 );
				$line_subtotal = $line_subtotal_full - $line_discount;
				$line_total = $line_subtotal;
			}
			
			// Calculate tax for this component using WooCommerce tax calculation (on discounted price if applicable).
			$tax_rates = WC_Tax::get_rates( $component_product->get_tax_class() );
			$line_subtotal_tax = 0;
			$line_total_tax = 0;
			$line_tax_data = array();
			
			if ( ! empty( $tax_rates ) ) {
				// Calculate tax on the discounted price (line_subtotal already has discount applied if default).
				$tax_amount = WC_Tax::calc_tax( $line_subtotal, $tax_rates, false );
				$line_subtotal_tax = array_sum( $tax_amount );
				$line_total_tax = $line_subtotal_tax;
				
				// Build tax data array in WooCommerce format.
				foreach ( $tax_rates as $rate_id => $rate ) {
					$tax_amount_for_rate = WC_Tax::calc_tax( $line_subtotal, array( $rate_id => $rate ), false );
					if ( ! empty( $tax_amount_for_rate ) ) {
						$line_tax_data[ $rate_id ] = array_sum( $tax_amount_for_rate );
					}
				}
			}

			// Create a new order item for this component.
			$component_item = new WC_Order_Item_Product();
			
			// Set all required properties using set_props (same as WooCommerce does).
			$component_item->set_props( array(
				'name'         => $component_product->get_name(),
				'tax_class'    => $component_product->get_tax_class(),
				'product_id'   => $component_product->is_type( 'variation' ) ? $component_product->get_parent_id() : $component_product->get_id(),
				'variation_id' => $component_product->is_type( 'variation' ) ? $component_product->get_id() : 0,
				'variation'    => $component_product->is_type( 'variation' ) ? $component_product->get_attributes() : array(),
				'quantity'     => $quantity,
				'subtotal'     => $line_subtotal,
				'total'        => $line_total,
				'subtotal_tax' => $line_subtotal_tax,
				'total_tax'    => $line_total_tax,
				'taxes'        => array(
					'subtotal' => $line_tax_data,
					'total'    => $line_tax_data,
				),
			) );
			
			$component_item->set_product( $component_product );
			$component_item->set_backorder_meta();
			
			// Set order ID if order already has one (for HPOS compatibility).
			if ( $order->get_id() > 0 ) {
				$component_item->set_order_id( $order->get_id() );
			}

			// Add meta to identify this as a component (child item) of the configurator.
			$component_item->add_meta_data( '_w2f_pc_is_component', 'yes' );
			$component_item->add_meta_data( '_w2f_pc_is_child_item', 'yes' );
			$component_item->add_meta_data( '_w2f_pc_configurator_product_id', $product->get_id() );
			$component_item->add_meta_data( '_w2f_pc_component_id', $component_id );
			$component_item->add_meta_data( '_w2f_pc_component_title', $component->get_title() );

			// Add the component item to the order.
			$order->add_item( $component_item );
		}

		// Reset flag after adding all component items.
		self::$processing_components = false;

		// Calculate final total (sum of all component prices after discount if default).
		// This is for reference only - main product stays at £0, components have all pricing.
		$total_component_price_after_discount = 0;
		foreach ( $component_line_totals as $component_id => $full_price ) {
			if ( $is_default && $discount_percentage > 0 ) {
				$total_component_price_after_discount += $full_price * ( 1 - ( $discount_percentage / 100 ) );
			} else {
				$total_component_price_after_discount += $full_price;
			}
		}
		
		// Store discount info in meta for backend display.
		// Main product is already set to £0 above - components have all the pricing.
		$item->add_meta_data( '_w2f_pc_is_default_configuration', $is_default ? 'yes' : 'no' );
		$item->add_meta_data( '_w2f_pc_default_price', $default_price );
		$item->add_meta_data( '_w2f_pc_total_component_price_before_discount', $total_component_price_before_discount );
		$item->add_meta_data( '_w2f_pc_total_component_price', $total_component_price_after_discount );
		
		// Add readable discount info for backend.
		if ( $is_default && $discount_percentage > 0 ) {
			$item->add_meta_data( __( 'Component Total (Before Discount)', 'w2f-pc-configurator' ), wc_price( $total_component_price_before_discount ) );
			$item->add_meta_data( __( 'Discount Percentage', 'w2f-pc-configurator' ), number_format( $discount_percentage, 2 ) . '%' );
			$item->add_meta_data( __( 'Discount Amount', 'w2f-pc-configurator' ), wc_price( $total_discount_amount ) );
			$item->add_meta_data( __( 'Final Total', 'w2f-pc-configurator' ), wc_price( $total_component_price_after_discount ) );
		} else {
			$item->add_meta_data( __( 'Component Total', 'w2f-pc-configurator' ), wc_price( $total_component_price_after_discount ) );
		}
	}


	/**
	 * Hide component price in order line subtotal (frontend only).
	 *
	 * @param  string                $subtotal
	 * @param  WC_Order_Item_Product $item
	 * @return string
	 */
	public function hide_component_price_in_order( $subtotal, $item ) {
		// Only hide on frontend, not in admin.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $subtotal;
		}
		
		// Check if this is a component item.
		if ( $item->get_meta( '_w2f_pc_is_component' ) === 'yes' ) {
			return ''; // Hide price from frontend.
		}
		
		return $subtotal;
	}

	/**
	 * Hide price meta values from frontend display.
	 *
	 * @param  string                $display_value
	 * @param  object                $meta
	 * @param  WC_Order_Item_Product $item
	 * @return string
	 */
	public function hide_price_meta_value( $display_value, $meta, $item ) {
		// Only hide on frontend, not in admin.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $display_value;
		}
		
		// Check if this is a component item and the meta key contains price information.
		if ( $item->get_meta( '_w2f_pc_is_component' ) === 'yes' ) {
			// Hide any meta that looks like it contains pricing.
			if ( false !== strpos( strtolower( $meta->key ), 'price' ) || false !== strpos( strtolower( $display_value ), '£' ) ) {
				return '';
			}
		}
		
		return $display_value;
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
			$quantities = isset( $cart_item['w2f_pc_configuration_quantities'] ) ? $cart_item['w2f_pc_configuration_quantities'] : array();

			// Check if configuration matches default.
			$is_default = $configurator_product->is_default_configuration( $configuration );
			
			// Calculate total component price (excluding tax) at full price.
			$total_component_price = $configurator_product->calculate_configuration_price( $configuration, false, $quantities );

			// Apply discount only if default configuration.
			$price = $total_component_price;
			if ( $is_default ) {
				// If default, use the default price (which already represents the discounted total).
				$default_price = $configurator_product->get_default_price();
				if ( $default_price > 0 ) {
					$price = $default_price;
				}
			}
			// If not default, price is already the sum of all components (no discount).
			
			$product->set_price( max( 0, $price ) ); // Ensure price doesn't go negative.
		}
	}

	/**
	 * Ensure main configurator product stays at £0 after order totals are calculated.
	 * This prevents WooCommerce from recalculating the main product price.
	 *
	 * @param WC_Order $order The order object.
	 */
	public function ensure_main_product_zero_price( $order ) {
		if ( ! $order ) {
			return;
		}

		// Find the main configurator product item.
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}

			// Check if this is the main configurator product (not a component).
			if ( $item->get_meta( '_w2f_pc_is_configurator' ) === 'yes' && $item->get_meta( '_w2f_pc_is_component' ) !== 'yes' ) {
				// Ensure main product stays at £0.
				$item->set_subtotal( 0 );
				$item->set_total( 0 );
				$item->set_subtotal_tax( 0 );
				$item->set_total_tax( 0 );
				$item->set_taxes( array() );
				$item->save();
			}
		}
	}
}

