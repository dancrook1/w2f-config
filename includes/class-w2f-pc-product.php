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
		
		// Ensure product has a price (use default price if regular price is not set).
		$price = parent::get_price();
		if ( empty( $price ) || $price <= 0 ) {
			$default_price = $this->get_default_price();
			if ( $default_price > 0 ) {
				// Temporarily set price for purchasability check.
				$this->set_price( $default_price );
			}
		}
		
		return parent::is_purchasable();
	}

	/**
	 * Get price.
	 * Returns default price if regular price is not set.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_price( $context = 'view' ) {
		$price = parent::get_price( $context );
		if ( empty( $price ) || $price <= 0 ) {
			$default_price = $this->get_default_price();
			if ( $default_price > 0 ) {
				return $default_price;
			}
		}
		return $price;
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
	 * Calculate price from configuration.
	 *
	 * @param  array $configuration
	 * @param  bool  $include_tax Whether to include tax in the price (default: true for display).
	 * @return float
	 */
	public function calculate_configuration_price( $configuration, $include_tax = true ) {
		// If matches default, return default price.
		if ( $this->is_default_configuration( $configuration ) ) {
			$default_price = $this->get_default_price(); // This is stored excluding tax.
			if ( $default_price > 0 ) {
				if ( $include_tax ) {
					// Default price is stored excluding tax, so add tax for display.
					return $this->calculate_price_including_tax( $default_price );
				}
				// Return excluding tax for cart calculations.
				return $default_price;
			}
		}

		// Otherwise, sum component prices.
		$total = 0;
		foreach ( $configuration as $component_id => $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				if ( $include_tax ) {
					// Get price including tax for display.
					$price = wc_get_price_including_tax( $product );
				} else {
					// Get price excluding tax for cart calculation.
					$price = wc_get_price_excluding_tax( $product );
				}
				$total += (float) $price;
			}
		}
		return $total;
	}

	/**
	 * Get price HTML.
	 *
	 * @param  string $price
	 * @return string
	 */
	public function get_price_html( $price = '' ) {
		$default_price = $this->get_default_price(); // Stored excluding tax.
		if ( $default_price > 0 ) {
			// Default price is stored excluding tax, convert to including tax for display.
			$display_price = $this->calculate_price_including_tax( $default_price );
			return wc_price( $display_price );
		}
		return parent::get_price_html( $price );
	}
}

