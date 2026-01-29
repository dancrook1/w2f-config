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
	 * Cache for parent-children relationships in admin.
	 *
	 * @var array
	 */
	protected $parent_children_cache = array();

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

		// Reorder order items to nest components under parent products.
		add_filter( 'woocommerce_order_get_items', array( $this, 'reorder_order_items' ), 10, 2 );
		
		// Hide child items from main table in admin (they'll be shown in nested table).
		// Using output buffering approach instead of filtering items.
		add_action( 'woocommerce_admin_order_items_after_line_items', array( $this, 'hide_child_items_with_css' ), 5 );

		// Add nested table structure for components in admin.
		add_action( 'woocommerce_before_order_item_line_item_html', array( $this, 'before_order_item_html' ), 10, 3 );
		add_action( 'woocommerce_order_item_line_item_html', array( $this, 'after_order_item_html' ), 10, 3 );

		// Add data attributes for visual grouping in admin.
		add_filter( 'woocommerce_admin_html_order_item_class', array( $this, 'admin_order_item_class' ), 10, 3 );

		// Hide subtotal row from order totals on frontend.
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'hide_subtotal_from_order_totals' ), 10, 3 );

		// Calculate cart item price.
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_cart_item_price' ), 10, 1 );
		
		// Ensure main product stays at £0 after order totals are calculated.
		add_action( 'woocommerce_checkout_order_created', array( $this, 'ensure_main_product_zero_price' ), 10, 1 );
		
		// Store system IDs on order for easy parsing.
		add_action( 'woocommerce_checkout_order_created', array( $this, 'store_order_system_ids' ), 15, 1 );
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
	 * Generate a unique system ID for a PC configurator.
	 *
	 * @return string
	 */
	private function generate_system_id() {
		// Generate a unique ID: timestamp + random string.
		$timestamp = time();
		$random = wp_generate_password( 8, false );
		return 'PC-' . $timestamp . '-' . strtoupper( $random );
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
			
			// Generate and store unique system ID for this PC configuration.
			if ( ! isset( $cart_item_data['w2f_pc_system_id'] ) ) {
				$cart_item_data['w2f_pc_system_id'] = $this->generate_system_id();
			}
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
		// Preserve system ID from session.
		if ( isset( $values['w2f_pc_system_id'] ) ) {
			$cart_item['w2f_pc_system_id'] = $values['w2f_pc_system_id'];
		} elseif ( isset( $cart_item['w2f_pc_configuration'] ) && ! isset( $cart_item['w2f_pc_system_id'] ) ) {
			// Generate system ID if missing (for legacy cart items).
			$cart_item['w2f_pc_system_id'] = $this->generate_system_id();
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
		
		// System ID is already set in add_component_line_items (priority 5) which runs before this.
		// Just ensure it exists, otherwise get from cart item data or generate.
		$system_id = $item->get_meta( '_w2f_pc_system_id' );
		if ( ! $system_id ) {
			$system_id = isset( $values['w2f_pc_system_id'] ) ? $values['w2f_pc_system_id'] : $this->generate_system_id();
			$item->add_meta_data( '_w2f_pc_system_id', $system_id );
			$item->add_meta_data( __( 'System ID', 'w2f-pc-configurator' ), $system_id );
		}

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
			'Base Warranty Cost', // Hide base warranty cost from customers - it's included in parent product price.
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
	 * Reorder order items to nest components under parent products.
	 *
	 * @param  array    $items
	 * @param  WC_Order $order
	 * @return array
	 */
	public function reorder_order_items( $items, $order ) {
		if ( empty( $items ) ) {
			return $items;
		}

		$reordered_items = array();
		$parent_items = array();
		$child_items = array();
		$other_items = array();

		// Separate items into parent, child, and other categories.
		foreach ( $items as $item_id => $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				$other_items[ $item_id ] = $item;
				continue;
			}

			// Check if this is a parent configurator product.
			if ( $item->get_meta( '_w2f_pc_is_configurator' ) === 'yes' && $item->get_meta( '_w2f_pc_has_components' ) === 'yes' ) {
				$parent_items[ $item_id ] = $item;
			}
			// Check if this is a child component.
			elseif ( $item->get_meta( '_w2f_pc_is_child_item' ) === 'yes' || $item->get_meta( '_w2f_pc_is_component' ) === 'yes' ) {
				$configurator_product_id = $item->get_meta( '_w2f_pc_configurator_product_id' );
				if ( $configurator_product_id ) {
					$child_items[ $configurator_product_id ][ $item_id ] = $item;
				} else {
					$other_items[ $item_id ] = $item;
				}
			} else {
				$other_items[ $item_id ] = $item;
			}
		}

		// Build reordered array: parent items first, then their children, then other items.
		foreach ( $parent_items as $parent_item_id => $parent_item ) {
			// Add parent item.
			$reordered_items[ $parent_item_id ] = $parent_item;

			// Add children of this parent.
			if ( isset( $child_items[ $parent_item->get_product_id() ] ) ) {
				foreach ( $child_items[ $parent_item->get_product_id() ] as $child_item_id => $child_item ) {
					$reordered_items[ $child_item_id ] = $child_item;
				}
			}
		}

		// Add remaining other items.
		foreach ( $other_items as $item_id => $item ) {
			$reordered_items[ $item_id ] = $item;
		}

		return $reordered_items;
	}

	/**
	 * Hide child items with CSS in admin (they're shown in nested table instead).
	 * This is called after line items are rendered.
	 *
	 * @param  int $order_id
	 */
	public function hide_child_items_with_css( $order_id ) {
		// Only in admin.
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		// Add CSS to hide child items in main table (they're shown in nested table).
		echo '<style>
			.woocommerce_order_items .w2f-pc-group-child,
			.woocommerce_order_items tr.item.w2f-pc-child-item {
				display: none !important;
			}
		</style>';
	}

	/**
	 * Add nested table structure before parent item HTML in admin.
	 *
	 * @param  int       $item_id
	 * @param  WC_Order_Item_Product $item
	 * @param  WC_Order  $order
	 */
	public function before_order_item_html( $item_id, $item, $order ) {
		// Only in admin.
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			return;
		}

		// Check if this is a parent configurator product with children.
		if ( $item->get_meta( '_w2f_pc_is_configurator' ) === 'yes' && $item->get_meta( '_w2f_pc_has_components' ) === 'yes' ) {
			// Get all children for this parent.
			$items = $order->get_items();
			$parent_product_id = $item->get_product_id();
			$child_items = array();
			
			foreach ( $items as $check_item_id => $check_item ) {
				if ( is_a( $check_item, 'WC_Order_Item_Product' ) ) {
					$check_configurator_id = $check_item->get_meta( '_w2f_pc_configurator_product_id' );
					if ( $check_configurator_id == $parent_product_id && $check_item->get_meta( '_w2f_pc_is_child_item' ) === 'yes' ) {
						$child_items[ $check_item_id ] = $check_item;
					}
				}
			}

			// Store children for later rendering.
			if ( ! empty( $child_items ) ) {
				$this->parent_children_cache[ $item_id ] = $child_items;
			}
		}
	}

	/**
	 * Add nested table structure after parent item HTML in admin.
	 *
	 * @param  int       $item_id
	 * @param  WC_Order_Item_Product $item
	 * @param  WC_Order  $order
	 */
	public function after_order_item_html( $item_id, $item, $order ) {
		// Only in admin.
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			return;
		}

		// Check if this is a parent configurator product with children.
		if ( $item->get_meta( '_w2f_pc_is_configurator' ) === 'yes' && $item->get_meta( '_w2f_pc_has_components' ) === 'yes' ) {
			if ( isset( $this->parent_children_cache[ $item_id ] ) && ! empty( $this->parent_children_cache[ $item_id ] ) ) {
				$child_items = $this->parent_children_cache[ $item_id ];
				
				// Count table columns to determine colspan.
				$order_taxes = $order->get_taxes();
				$tax_column_count = count( $order_taxes );
				$cogs_enabled = class_exists( '\Automattic\WooCommerce\Internal\CostOfGoodsSold\CostOfGoodsSoldController' ) && 
				                 wc_get_container()->get( \Automattic\WooCommerce\Internal\CostOfGoodsSold\CostOfGoodsSoldController::class )->feature_is_enabled();
				$colspan = 2 + ( $cogs_enabled ? 1 : 0 ) + 3 + $tax_column_count + 1; // Item (2 cols), COGS (1), Price/Qty/Total (3), Tax columns, Actions (1)
				
				// Output nested table row.
				echo '<tr class="w2f-pc-components-row"><td colspan="' . esc_attr( $colspan ) . '" class="w2f-pc-components-cell" style="padding: 0;">';
				echo '<table class="w2f-pc-components-table" cellpadding="0" cellspacing="0" style="width: 100%; margin: 0; background: #f9f9f9;">';
				
				// Render each child item.
				foreach ( $child_items as $child_item_id => $child_item ) {
					$this->render_child_item_row( $child_item_id, $child_item, $order );
				}
				
				echo '</table>';
				echo '</td></tr>';
				
				// Clear cache.
				unset( $this->parent_children_cache[ $item_id ] );
			}
		}
	}

	/**
	 * Render a child item row in the nested table.
	 *
	 * @param  int       $item_id
	 * @param  WC_Order_Item_Product $item
	 * @param  WC_Order  $order
	 */
	private function render_child_item_row( $item_id, $item, $order ) {
		$product = $item->get_product();
		$product_link = $product ? admin_url( 'post.php?post=' . $item->get_product_id() . '&action=edit' ) : '';
		$thumbnail = $product ? apply_filters( 'woocommerce_admin_order_item_thumbnail', $product->get_image( 'thumbnail', array( 'title' => '' ), false ), $item_id, $item ) : '';
		$item_name = apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, $product ? $product->is_visible() : false );
		
		$order_taxes = $order->get_taxes();
		$cogs_enabled = class_exists( '\Automattic\WooCommerce\Internal\CostOfGoodsSold\CostOfGoodsSoldController' ) && 
		                 wc_get_container()->get( \Automattic\WooCommerce\Internal\CostOfGoodsSold\CostOfGoodsSoldController::class )->feature_is_enabled();
		
		echo '<tr class="item w2f-pc-nested-child-item" data-order_item_id="' . esc_attr( $item_id ) . '">';
		
		// Thumbnail column.
		echo '<td class="thumb" style="width: 50px; padding: 8px;">';
		echo '<div class="wc-order-item-thumbnail">' . wp_kses_post( $thumbnail ) . '</div>';
		echo '</td>';
		
		// Name column.
		echo '<td class="name" style="padding: 8px;">';
		echo $product_link ? '<a href="' . esc_url( $product_link ) . '" class="wc-order-item-name">' . wp_kses_post( $item_name ) . '</a>' : '<div class="wc-order-item-name">' . wp_kses_post( $item_name ) . '</div>';
		
		if ( $product && $product->get_sku() ) {
			echo '<div class="wc-order-item-sku"><strong>' . esc_html__( 'SKU:', 'woocommerce' ) . '</strong> ' . esc_html( $product->get_sku() ) . '</div>';
		}
		
		if ( $item->get_variation_id() ) {
			echo '<div class="wc-order-item-variation"><strong>' . esc_html__( 'Variation ID:', 'woocommerce' ) . '</strong> ';
			if ( 'product_variation' === get_post_type( $item->get_variation_id() ) ) {
				echo esc_html( $item->get_variation_id() );
			} else {
				/* translators: %s: variation id */
				printf( esc_html__( '%s (No longer exists)', 'woocommerce' ), esc_html( $item->get_variation_id() ) );
			}
			echo '</div>';
		}
		
		echo '<input type="hidden" class="order_item_id" name="order_item_id[]" value="' . esc_attr( $item_id ) . '" />';
		echo '<input type="hidden" name="order_item_tax_class[' . absint( $item_id ) . ']" value="' . esc_attr( $item->get_tax_class() ) . '" />';
		
		do_action( 'woocommerce_before_order_itemmeta', $item_id, $item, $product );
		
		// Include the admin meta template.
		$meta_template = WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-item-meta.php';
		if ( file_exists( $meta_template ) ) {
			include $meta_template;
		} else {
			// Fallback to wc_display_item_meta if template doesn't exist.
			wc_display_item_meta( $item );
		}
		
		do_action( 'woocommerce_after_order_itemmeta', $item_id, $item, $product );
		
		echo '</td>';
		
		// Allow other plugins to add columns.
		do_action( 'woocommerce_admin_order_item_values', $product, $item, absint( $item_id ) );
		
		// COGS column (if enabled).
		if ( $cogs_enabled ) {
			echo '<td class="item_cost_of_goods" style="padding: 8px;">';
			echo '</td>';
		}
		
		// Price column.
		echo '<td class="item_cost" style="padding: 8px;">';
		echo '<div class="view">';
		echo wc_price( $item->get_subtotal() / max( 1, $item->get_quantity() ), array( 'currency' => $order->get_currency() ) );
		echo '</div>';
		echo '</td>';
		
		// Quantity column.
		echo '<td class="quantity" style="padding: 8px;">';
		echo '<div class="view">';
		echo esc_html( $item->get_quantity() );
		echo '</div>';
		echo '</td>';
		
		// Total column.
		echo '<td class="line_cost" style="padding: 8px;">';
		echo '<div class="view">';
		echo wc_price( $item->get_subtotal(), array( 'currency' => $order->get_currency() ) );
		echo '</div>';
		echo '</td>';
		
		// Tax columns.
		if ( ! empty( $order_taxes ) ) {
			foreach ( $order_taxes as $tax_id => $tax_item ) {
				echo '<td class="line_tax" style="padding: 8px;">';
				echo '<div class="view">';
				$tax_amount = $item->get_taxes();
				if ( isset( $tax_amount['total'][ $tax_id ] ) ) {
					echo wc_price( wc_round_tax_total( $tax_amount['total'][ $tax_id ] ), array( 'currency' => $order->get_currency() ) );
				} else {
					echo '&ndash;';
				}
				echo '</div>';
				echo '</td>';
			}
		}
		
		// Actions column.
		echo '<td class="wc-order-edit-line-item" style="padding: 8px;">';
		echo '</td>';
		
		echo '</tr>';
	}

	/**
	 * Hide subtotal row from order totals on frontend (display only).
	 * Does NOT affect price calculations - only removes the row from display.
	 *
	 * @param  array    $total_rows
	 * @param  WC_Order $order
	 * @param  string   $tax_display
	 * @return array
	 */
	public function hide_subtotal_from_order_totals( $total_rows, $order, $tax_display ) {
		// Only hide on frontend, not in admin.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $total_rows;
		}

		// Remove subtotal row from display array (display only - does not affect calculations).
		unset( $total_rows['cart_subtotal'] );

		return $total_rows;
	}

	/**
	 * Add data attributes and classes for visual grouping in admin.
	 *
	 * @param  string                $class
	 * @param  WC_Order_Item_Product $item
	 * @param  WC_Order              $order
	 * @return string
	 */
	public function admin_order_item_class( $class, $item, $order ) {
		// Only in admin.
		if ( ! is_admin() || wp_doing_ajax() ) {
			return $class;
		}

		if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			return $class;
		}

		// Check if this is a parent configurator product.
		if ( $item->get_meta( '_w2f_pc_is_configurator' ) === 'yes' && $item->get_meta( '_w2f_pc_has_components' ) === 'yes' ) {
			$class .= ' w2f-pc-group-parent';
			
			// Check if this parent has children.
			$items = $order->get_items();
			$parent_product_id = $item->get_product_id();
			$has_children = false;
			
			foreach ( $items as $check_item ) {
				if ( is_a( $check_item, 'WC_Order_Item_Product' ) ) {
					$check_configurator_id = $check_item->get_meta( '_w2f_pc_configurator_product_id' );
					if ( $check_configurator_id == $parent_product_id && $check_item->get_meta( '_w2f_pc_is_child_item' ) === 'yes' ) {
						$has_children = true;
						break;
					}
				}
			}

			if ( $has_children ) {
				$class .= ' w2f-pc-has-children';
			}
		}
		// Check if this is a child component.
		elseif ( $item->get_meta( '_w2f_pc_is_child_item' ) === 'yes' || $item->get_meta( '_w2f_pc_is_component' ) === 'yes' ) {
			$class .= ' w2f-pc-group-child';
			$configurator_product_id = $item->get_meta( '_w2f_pc_configurator_product_id' );
			if ( $configurator_product_id ) {
				$class .= ' w2f-pc-parent-' . esc_attr( $configurator_product_id );
			}
			
			// Check if this is the last child of its parent by checking items after this one.
			$items = $order->get_items();
			$current_item_id = $item->get_id();
			$is_last_child = true;
			$found_current = false;
			
			foreach ( $items as $check_item_id => $check_item ) {
				if ( ! is_a( $check_item, 'WC_Order_Item_Product' ) ) {
					continue;
				}
				
				// Find current item first.
				if ( ! $found_current ) {
					if ( $check_item_id == $current_item_id ) {
						$found_current = true;
					}
					continue;
				}
				
				// Now check items after current.
				$check_configurator_id = $check_item->get_meta( '_w2f_pc_configurator_product_id' );
				// If next item is also a child of the same parent, this is not the last child.
				if ( $check_configurator_id == $configurator_product_id && $check_item->get_meta( '_w2f_pc_is_child_item' ) === 'yes' ) {
					$is_last_child = false;
					break;
				}
				// If next item is not a child of this parent, we've reached the end of this group.
				if ( $check_configurator_id != $configurator_product_id ) {
					break;
				}
			}

			if ( $is_last_child ) {
				$class .= ' w2f-pc-last-child';
			}
		}

		return $class;
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
					'Base Warranty Cost', // Hide base warranty cost from customers - it's included in parent product price.
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
		
		// Ensure default warranty is set if not in configuration.
		if ( ! isset( $configuration['warranty'] ) || empty( $configuration['warranty'] ) ) {
			$warranty_manager = W2F_PC_Warranty_Manager::instance();
			$default_warranty = $warranty_manager->get_default_warranty();
			if ( $default_warranty > 0 ) {
				$configuration['warranty'] = $default_warranty;
			}
		}

		// Get or generate system ID for this PC configuration (from cart item data).
		// This must be done here (priority 5) before add_order_item_meta (priority 10).
		// The same system ID will be used for the parent and all child components.
		$system_id = isset( $values['w2f_pc_system_id'] ) ? $values['w2f_pc_system_id'] : $this->generate_system_id();
		
		// Store system ID on parent item first (before children are created).
		$item->add_meta_data( '_w2f_pc_system_id', $system_id );
		$item->add_meta_data( __( 'System ID', 'w2f-pc-configurator' ), $system_id );

		// Mark the main configurator product item.
		$item->add_meta_data( '_w2f_pc_is_configurator', 'yes' );
		$item->add_meta_data( '_w2f_pc_has_components', 'yes' );

		// Calculate base warranty cost from component sum (excluding warranty product).
		$warranty_manager = W2F_PC_Warranty_Manager::instance();
		$component_sum_ex_tax = 0;
		
		// Calculate component sum excluding warranty for base warranty calculation.
		foreach ( $configuration as $component_id => $product_id ) {
			if ( 'warranty' === $component_id ) {
				continue; // Skip warranty component for base warranty calculation.
			}
			
			if ( ! is_numeric( $product_id ) || $product_id <= 0 ) {
				continue;
			}
			
			$component_product = wc_get_product( $product_id );
			if ( ! $component_product ) {
				continue;
			}
			
			$component = $configurator_product->get_component( $component_id );
			$quantity = 1;
			if ( $component && $component->enable_quantity() && isset( $quantities[ $component_id ] ) ) {
				$quantity = max( 1, intval( $quantities[ $component_id ] ) );
			}
			
			$component_price = wc_get_price_excluding_tax( $component_product );
			$component_sum_ex_tax += $component_price * $quantity;
		}
		
		$base_warranty_cost = $warranty_manager->get_base_warranty_cost( $component_sum_ex_tax, false );
		
		// Calculate tax for base warranty.
		$tax_rates = WC_Tax::get_rates();
		$base_warranty_tax = 0;
		$base_warranty_tax_data = array();
		
		if ( $base_warranty_cost > 0 && ! empty( $tax_rates ) ) {
			$tax_amount = WC_Tax::calc_tax( $base_warranty_cost, $tax_rates, false );
			$base_warranty_tax = array_sum( $tax_amount );
			
			foreach ( $tax_rates as $rate_id => $rate ) {
				$tax_amount_for_rate = WC_Tax::calc_tax( $base_warranty_cost, array( $rate_id => $rate ), false );
				if ( ! empty( $tax_amount_for_rate ) ) {
					$base_warranty_tax_data[ $rate_id ] = array_sum( $tax_amount_for_rate );
				}
			}
		}
		
		// Set main product price to base warranty cost (hidden from customers, included in parent product).
		// Components will have their own pricing as separate line items.
		$item->set_subtotal( $base_warranty_cost );
		$item->set_total( $base_warranty_cost );
		$item->set_subtotal_tax( $base_warranty_tax );
		$item->set_total_tax( $base_warranty_tax );
		$item->set_taxes( array(
			'subtotal' => $base_warranty_tax_data,
			'total'    => $base_warranty_tax_data,
		) );
		
		// Calculate total component price.
		$total_component_price = 0;

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
			$line_subtotal = $component_price * $quantity;
			$line_total = $line_subtotal;
			$total_component_price += $line_subtotal;
			
			// Calculate tax for this component using WooCommerce tax calculation.
			$tax_rates = WC_Tax::get_rates( $component_product->get_tax_class() );
			$line_subtotal_tax = 0;
			$line_total_tax = 0;
			$line_tax_data = array();
			
			if ( ! empty( $tax_rates ) ) {
				// Calculate tax on the component price.
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

			// Use the same system ID that was set on the parent item above.
			// The $system_id variable is already set at the beginning of this method.
			// This ensures all components share the exact same system ID as the parent.
			
			// Add meta to identify this as a component (child item) of the configurator.
			$component_item->add_meta_data( '_w2f_pc_is_component', 'yes' );
			$component_item->add_meta_data( '_w2f_pc_is_child_item', 'yes' );
			$component_item->add_meta_data( '_w2f_pc_configurator_product_id', $product->get_id() );
			$component_item->add_meta_data( '_w2f_pc_component_id', $component_id );
			$component_item->add_meta_data( '_w2f_pc_component_title', $component->get_title() );
			
			// Store the same system ID on component items (same as parent).
			$component_item->add_meta_data( '_w2f_pc_system_id', $system_id );

			// Add the component item to the order.
			$order->add_item( $component_item );
		}

		// Reset flag after adding all component items.
		self::$processing_components = false;
		
		// Store component total and base warranty cost in meta for backend/admin display only (not visible to customers).
		$item->add_meta_data( '_w2f_pc_total_component_price', $total_component_price );
		$item->add_meta_data( '_w2f_pc_base_warranty_cost', $base_warranty_cost );
		$item->add_meta_data( __( 'Component Total', 'w2f-pc-configurator' ), wc_price( $total_component_price ) );
		// Note: Base warranty cost is now included in parent product price and not displayed separately to customers.
	}


	/**
	 * Hide component price in order line subtotal (frontend only).
	 * Display-only filter - does NOT affect price calculations.
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
		
		// Hide subtotals for component items.
		if ( $item->get_meta( '_w2f_pc_is_component' ) === 'yes' ) {
			return ''; // Hide price from frontend (display only).
		}
		
		// Hide subtotals for parent configurator products.
		if ( $item->get_meta( '_w2f_pc_is_configurator' ) === 'yes' && $item->get_meta( '_w2f_pc_has_components' ) === 'yes' ) {
			return ''; // Hide price from frontend (display only).
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

			// Calculate price from components (sum of all component prices excluding tax).
			$price = $configurator_product->calculate_configuration_price( $configuration, false, $quantities );
			
			// Set both price and regular_price so WooCommerce can calculate totals correctly.
			$product->set_price( max( 0, $price ) );
			$product->set_regular_price( max( 0, $price ) );
		}
	}

	/**
	 * Store all system IDs on the order for easy parsing.
	 *
	 * @param WC_Order $order The order object.
	 */
	public function store_order_system_ids( $order ) {
		if ( ! $order ) {
			return;
		}

		$system_ids = array();
		
		// Collect all unique system IDs from configurator products.
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}
			
			$system_id = $item->get_meta( '_w2f_pc_system_id' );
			if ( $system_id && ! in_array( $system_id, $system_ids, true ) ) {
				$system_ids[] = $system_id;
			}
		}
		
		// Store system IDs on order meta for easy access.
		if ( ! empty( $system_ids ) ) {
			$order->update_meta_data( '_w2f_pc_system_ids', $system_ids );
			$order->save();
		}
	}

	/**
	 * Ensure main configurator product price includes base warranty cost.
	 * This prevents WooCommerce from recalculating the main product price incorrectly.
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
				// Get the base warranty cost that was set during add_component_line_items.
				$base_warranty_cost = $item->get_meta( '_w2f_pc_base_warranty_cost' );
				if ( $base_warranty_cost > 0 ) {
					// Recalculate tax for base warranty cost.
					$tax_rates = WC_Tax::get_rates();
					$base_warranty_tax = 0;
					$base_warranty_tax_data = array();
					
					if ( ! empty( $tax_rates ) ) {
						$tax_amount = WC_Tax::calc_tax( $base_warranty_cost, $tax_rates, false );
						$base_warranty_tax = array_sum( $tax_amount );
						
						foreach ( $tax_rates as $rate_id => $rate ) {
							$tax_amount_for_rate = WC_Tax::calc_tax( $base_warranty_cost, array( $rate_id => $rate ), false );
							if ( ! empty( $tax_amount_for_rate ) ) {
								$base_warranty_tax_data[ $rate_id ] = array_sum( $tax_amount_for_rate );
							}
						}
					}
					
					// Ensure main product price includes base warranty cost (hidden from customers).
					$item->set_subtotal( $base_warranty_cost );
					$item->set_total( $base_warranty_cost );
					$item->set_subtotal_tax( $base_warranty_tax );
					$item->set_total_tax( $base_warranty_tax );
					$item->set_taxes( array(
						'subtotal' => $base_warranty_tax_data,
						'total'    => $base_warranty_tax_data,
					) );
					$item->save();
				}
			}
		}
	}
}

