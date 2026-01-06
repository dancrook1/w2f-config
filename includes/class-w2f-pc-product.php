<?php
/**
 * W2F_PC_Product class
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PC Configurator Product Class.
 *
 * @class    W2F_PC_Product
 * @version  1.0.0
 */
class W2F_PC_Product extends WC_Product {

	/**
	 * Array of component data.
	 *
	 * @var array
	 */
	private $components_data = array();

	/**
	 * Array of component objects.
	 *
	 * @var array
	 */
	private $components = array();

	/**
	 * Default configuration (component_id => product_id).
	 *
	 * @var array
	 */
	private $default_configuration = array();

	/**
	 * Default price for default configuration.
	 *
	 * @var float
	 */
	private $default_price = 0;

	/**
	 * Product type.
	 *
	 * @var string
	 */
	protected $product_type = 'pc_configurator';

	/**
	 * Constructor.
	 *
	 * @param  mixed $product
	 */
	public function __construct( $product = 0 ) {
		$this->supports[] = 'ajax_add_to_cart';
		parent::__construct( $product );
	}

	/**
	 * Get internal type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'pc_configurator';
	}

	/**
	 * Check if product is virtual.
	 *
	 * @return boolean
	 */
	public function is_virtual() {
		return true;
	}

	/**
	 * Check if product manages stock.
	 * Configurator products don't manage stock individually.
	 *
	 * @return boolean
	 */
	public function managing_stock() {
		return false;
	}

	/**
	 * Check if product is in stock.
	 * Configurator products are always in stock.
	 *
	 * @return boolean
	 */
	public function is_in_stock() {
		return true;
	}

	/**
	 * Check if product is sold individually.
	 *
	 * @return boolean
	 */
	public function is_sold_individually() {
		return true;
	}

	/**
	 * Check if product is purchasable.
	 *
	 * @return boolean
	 */
	public function is_purchasable() {
		// Product must be published and have at least one component configured.
		$components = $this->get_components();
		if ( empty( $components ) ) {
			return false;
		}
		
		// Price is calculated from components, product is always purchasable if it has components.
		return parent::is_purchasable();
	}

	/**
	 * Get price.
	 * Returns the stored price if set (from cart calculation), otherwise 0.
	 *
	 * @param  string $context
	 * @return float
	 */
	public function get_price( $context = 'view' ) {
		// Get the stored price (set by cart calculation).
		$price = parent::get_price( $context );
		
		// If price is set (from cart), return it. Otherwise return 0.
		return $price > 0 ? $price : 0;
	}

	/**
	 * Get components data.
	 *
	 * @param  string $context
	 * @return array
	 */
	public function get_components_data( $context = 'view' ) {
		if ( empty( $this->components_data ) ) {
			$this->components_data = $this->get_meta( '_w2f_pc_components', true );
			if ( ! is_array( $this->components_data ) ) {
				$this->components_data = array();
			}
		}
		return $this->components_data;
	}

	/**
	 * Set components data.
	 *
	 * @param array $data
	 */
	public function set_components_data( $data ) {
		$this->components_data = $data;
		$this->update_meta_data( '_w2f_pc_components', $data );
	}

	/**
	 * Get components.
	 *
	 * @return array
	 */
	public function get_components() {
		if ( empty( $this->components ) ) {
			$components_data = $this->get_components_data();
			foreach ( $components_data as $component_id => $component_data ) {
				$this->components[ $component_id ] = new W2F_PC_Component( $component_id, $this, $component_data );
			}
			
			// Inject warranty component if warranty products are configured.
			$warranty_manager = W2F_PC_Warranty_Manager::instance();
			$warranty_products = $warranty_manager->get_warranty_products();
			
			if ( ! empty( $warranty_products ) && ! isset( $this->components['warranty'] ) ) {
				// Sort warranty products so Elite is second.
				usort( $warranty_products, function( $a, $b ) {
					$product_a = wc_get_product( $a );
					$product_b = wc_get_product( $b );
					
					if ( ! $product_a || ! $product_b ) {
						return 0;
					}
					
					$is_elite_a = stripos( $product_a->get_name(), 'elite' ) !== false;
					$is_elite_b = stripos( $product_b->get_name(), 'elite' ) !== false;
					
					// If one is Elite and the other isn't, Elite comes second.
					if ( $is_elite_a && ! $is_elite_b ) {
						return 1; // Elite comes after non-Elite.
					}
					if ( ! $is_elite_a && $is_elite_b ) {
						return -1; // Non-Elite comes before Elite.
					}
					
					// If both are Elite or both are not Elite, maintain original order.
					return 0;
				} );
				
				// Get warranty description from settings.
				$warranty_description = $warranty_manager->get_warranty_description();
				
				// Create warranty component data.
				$warranty_component_data = array(
					'title'              => __( 'Warranty', 'w2f-pc-configurator' ),
					'description'        => $warranty_description,
					'optional'           => 'no', // Required.
					'display_mode'       => 'thumbnail',
					'tab'                => 'Services',
					'show_search'        => 'no',
					'show_dropdown_image' => 'no',
					'enable_quantity'    => 'no',
					'min_quantity'       => 1,
					'max_quantity'       => 1,
					'options'            => $warranty_products,
					'categories'         => array(),
				);
				
				$this->components['warranty'] = new W2F_PC_Component( 'warranty', $this, $warranty_component_data );
			}
		}
		return $this->components;
	}

	/**
	 * Get component by ID.
	 *
	 * @param  string $component_id
	 * @return W2F_PC_Component|false
	 */
	public function get_component( $component_id ) {
		$components = $this->get_components();
		return isset( $components[ $component_id ] ) ? $components[ $component_id ] : false;
	}

	/**
	 * Get default configuration.
	 *
	 * @return array
	 */
	public function get_default_configuration() {
		if ( empty( $this->default_configuration ) ) {
			$this->default_configuration = $this->get_meta( '_w2f_pc_default_configuration', true );
			if ( ! is_array( $this->default_configuration ) ) {
				$this->default_configuration = array();
			}
			
			// Add default warranty if not already set and warranty component exists.
			if ( ! isset( $this->default_configuration['warranty'] ) ) {
				$warranty_manager = W2F_PC_Warranty_Manager::instance();
				$default_warranty = $warranty_manager->get_default_warranty();
				if ( $default_warranty > 0 ) {
					$this->default_configuration['warranty'] = $default_warranty;
				}
			}
		}
		return $this->default_configuration;
	}

	/**
	 * Set default configuration.
	 *
	 * @param array $configuration
	 */
	public function set_default_configuration( $configuration ) {
		$this->default_configuration = $configuration;
		$this->update_meta_data( '_w2f_pc_default_configuration', $configuration );
	}

	/**
	 * Get default price.
	 *
	 * @return float
	 */
	public function get_default_price() {
		if ( 0 === $this->default_price ) {
			$this->default_price = (float) $this->get_meta( '_w2f_pc_default_price', true );
		}
		return $this->default_price;
	}

	/**
	 * Set default price.
	 *
	 * @param float $price
	 */
	public function set_default_price( $price ) {
		$this->default_price = (float) $price;
		$this->update_meta_data( '_w2f_pc_default_price', $price );
	}

	/**
	 * Get tabs configuration.
	 *
	 * @return array
	 */
	public function get_tabs() {
		$tabs = $this->get_meta( '_w2f_pc_tabs', true );
		if ( ! is_array( $tabs ) ) {
			$tabs = array();
		}
		return $tabs;
	}

	/**
	 * Set tabs configuration.
	 *
	 * @param array $tabs
	 */
	public function set_tabs( $tabs ) {
		$this->update_meta_data( '_w2f_pc_tabs', $tabs );
	}

	/**
	 * Check if configuration matches default.
	 *
	 * @param  array $configuration
	 * @return boolean
	 */
	public function is_default_configuration( $configuration ) {
		$default = $this->get_default_configuration();
		if ( empty( $default ) || empty( $configuration ) ) {
			return false;
		}
		// Compare configurations.
		foreach ( $default as $component_id => $product_id ) {
			if ( ! isset( $configuration[ $component_id ] ) || (int) $configuration[ $component_id ] !== (int) $product_id ) {
				return false;
			}
		}
		// Check if all components in configuration are in default.
		foreach ( $configuration as $component_id => $product_id ) {
			if ( ! isset( $default[ $component_id ] ) || (int) $default[ $component_id ] !== (int) $product_id ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get tax rate from component products or use standard tax class.
	 *
	 * @return string Tax class.
	 */
	private function get_tax_class_for_calculation() {
		// Try to get tax class from component products.
		$components = $this->get_components();
		foreach ( $components as $component ) {
			$option_products = $component->get_option_products();
			if ( ! empty( $option_products ) ) {
				$first_product = reset( $option_products );
				if ( $first_product && $first_product->is_taxable() ) {
					return $first_product->get_tax_class();
				}
			}
		}
		// Fall back to standard tax class.
		return '';
	}

	/**
	 * Calculate price including tax manually (for default price).
	 *
	 * @param  float $price_excl_tax Price excluding tax.
	 * @return float Price including tax.
	 */
	private function calculate_price_including_tax( $price_excl_tax ) {
		// If product is taxable, use WooCommerce function.
		if ( $this->is_taxable() ) {
			return wc_get_price_including_tax( $this, array( 'price' => $price_excl_tax ) );
		}

		// Otherwise, calculate tax manually using component product tax class or standard.
		$tax_class = $this->get_tax_class_for_calculation();
		$tax_rates = WC_Tax::get_rates( $tax_class );

		if ( empty( $tax_rates ) ) {
			// No tax rates found, return price as-is.
			return $price_excl_tax;
		}

		// Check if customer is VAT exempt.
		if ( ! empty( WC()->customer ) && WC()->customer->get_is_vat_exempt() ) {
			return $price_excl_tax;
		}

		// Calculate tax.
		$taxes = WC_Tax::calc_tax( $price_excl_tax, $tax_rates, false );

		if ( 'yes' === get_option( 'woocommerce_tax_round_at_subtotal' ) ) {
			$taxes_total = array_sum( $taxes );
		} else {
			$taxes_total = array_sum( array_map( 'wc_round_tax_total', $taxes ) );
		}

		return (float) round( $price_excl_tax + $taxes_total, wc_get_price_decimals() );
	}

	/**
	 * Calculate the sum of component prices for a configuration (without any discount).
	 *
	 * @param  array $configuration
	 * @param  bool  $include_tax Whether to include tax in the price.
	 * @param  array $quantities Component quantities.
	 * @return float Sum of component prices.
	 */
	private function calculate_component_sum( $configuration, $include_tax = false, $quantities = array() ) {
		$total = 0;
		$components = $this->get_components();
		
		foreach ( $configuration as $component_id => $product_id ) {
			// Skip invalid product IDs.
			if ( ! is_numeric( $product_id ) || $product_id <= 0 ) {
				continue;
			}
			
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}
			
			// Get price (including or excluding tax).
			if ( $include_tax ) {
				$price = wc_get_price_including_tax( $product );
			} else {
				$price = wc_get_price_excluding_tax( $product );
			}
			
			// Multiply by quantity if quantity is enabled for this component.
			$quantity = 1;
			if ( isset( $components[ $component_id ] ) && $components[ $component_id ]->enable_quantity() ) {
				$quantity = isset( $quantities[ $component_id ] ) ? max( 1, intval( $quantities[ $component_id ] ) ) : 1;
			}
			
			$total += (float) $price * $quantity;
		}
		
		return $total;
	}

	/**
	 * Calculate price from configuration.
	 * Sums all component prices, adds base warranty cost, and adds tax.
	 *
	 * @param  array $configuration
	 * @param  bool  $include_tax Whether to include tax in the price (default: true for display).
	 * @param  array $quantities Component quantities.
	 * @return float
	 */
	public function calculate_configuration_price( $configuration, $include_tax = true, $quantities = array() ) {
		// Calculate component sum excluding warranty for base warranty calculation.
		$component_sum_ex_tax = 0;
		$components = $this->get_components();
		
		foreach ( $configuration as $component_id => $product_id ) {
			// Skip warranty component for base warranty calculation.
			if ( 'warranty' === $component_id ) {
				continue;
			}
			
			// Skip invalid product IDs.
			if ( ! is_numeric( $product_id ) || $product_id <= 0 ) {
				continue;
			}
			
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}
			
			// Get price excluding tax for base warranty calculation.
			$price = wc_get_price_excluding_tax( $product );
			
			// Multiply by quantity if quantity is enabled for this component.
			$quantity = 1;
			if ( isset( $components[ $component_id ] ) && $components[ $component_id ]->enable_quantity() ) {
				$quantity = isset( $quantities[ $component_id ] ) ? max( 1, intval( $quantities[ $component_id ] ) ) : 1;
			}
			
			$component_sum_ex_tax += (float) $price * $quantity;
		}
		
		// Get base warranty cost based on component sum (excluding tax for bracket lookup).
		$warranty_manager = W2F_PC_Warranty_Manager::instance();
		$base_warranty_cost = $warranty_manager->get_base_warranty_cost( $component_sum_ex_tax, $include_tax );
		
		// Calculate total component sum (including warranty product if selected, with correct tax).
		$component_sum = $this->calculate_component_sum( $configuration, $include_tax, $quantities );
		
		// Add base warranty cost.
		$total = $component_sum + $base_warranty_cost;
		
		return max( 0, $total );
	}

	/**
	 * Get price HTML.
	 * Price is calculated from components, so return empty or calculated price.
	 *
	 * @param  string $price
	 * @return string
	 */
	public function get_price_html( $price = '' ) {
		// Price is calculated dynamically from components.
		return '';
	}
}


