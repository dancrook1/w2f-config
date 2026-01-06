<?php
/**
 * W2F_PC_Warranty_Manager class
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Warranty Manager Class.
 * Manages warranty price brackets and warranty products.
 *
 * @class    W2F_PC_Warranty_Manager
 * @version  1.0.0
 */
class W2F_PC_Warranty_Manager {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Warranty_Manager
	 */
	protected static $_instance = null;

	/**
	 * Main W2F_PC_Warranty_Manager instance.
	 *
	 * @static
	 * @return W2F_PC_Warranty_Manager - Main instance
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
	private function __construct() {
		// Private constructor for singleton.
	}

	/**
	 * Get price brackets.
	 *
	 * @return array Array of price brackets with min, max, and cost.
	 */
	public function get_price_brackets() {
		$brackets = get_option( 'w2f_pc_warranty_price_brackets', array() );
		
		// Ensure brackets are properly formatted.
		if ( ! is_array( $brackets ) ) {
			return array();
		}
		
		// Sort brackets by min price.
		usort( $brackets, function( $a, $b ) {
			return ( $a['min'] ?? 0 ) <=> ( $b['min'] ?? 0 );
		} );
		
		return $brackets;
	}

	/**
	 * Save price brackets.
	 *
	 * @param  array $brackets Array of price brackets.
	 * @return bool
	 */
	public function save_price_brackets( $brackets ) {
		if ( ! is_array( $brackets ) ) {
			return false;
		}
		
		// Validate and sanitize brackets.
		$sanitized = array();
		foreach ( $brackets as $bracket ) {
			if ( ! isset( $bracket['min'] ) || ! isset( $bracket['max'] ) || ! isset( $bracket['cost'] ) ) {
				continue;
			}
			
			$min = floatval( $bracket['min'] );
			$max = floatval( $bracket['max'] );
			$cost = floatval( $bracket['cost'] );
			
			// Validate: min < max, cost >= 0.
			if ( $min < 0 || $max < 0 || $cost < 0 || $min >= $max ) {
				continue;
			}
			
			$sanitized[] = array(
				'min'  => $min,
				'max'  => $max,
				'cost' => $cost,
			);
		}
		
		// Sort by min price.
		usort( $sanitized, function( $a, $b ) {
			return $a['min'] <=> $b['min'];
		} );
		
		return update_option( 'w2f_pc_warranty_price_brackets', $sanitized );
	}

	/**
	 * Get base warranty cost based on PC price.
	 *
	 * @param  float $pc_price PC price (excluding tax).
	 * @param  bool  $include_tax Whether to include tax in the returned cost.
	 * @return float Base warranty cost.
	 */
	public function get_base_warranty_cost( $pc_price, $include_tax = false ) {
		$brackets = $this->get_price_brackets();
		
		if ( empty( $brackets ) ) {
			return 0.0;
		}
		
		// Find matching bracket.
		$matching_bracket = null;
		foreach ( $brackets as $bracket ) {
			if ( $pc_price >= $bracket['min'] && $pc_price <= $bracket['max'] ) {
				$matching_bracket = $bracket;
				break;
			}
		}
		
		// If no bracket matches, return 0.
		if ( ! $matching_bracket ) {
			return 0.0;
		}
		
		$base_cost = floatval( $matching_bracket['cost'] );
		
		// Add tax if requested.
		if ( $include_tax ) {
			// Get tax rates for standard products (or use main configurator product tax class).
			$tax_rates = WC_Tax::get_rates();
			if ( ! empty( $tax_rates ) ) {
				$tax_amount = WC_Tax::calc_tax( $base_cost, $tax_rates, false );
				$base_cost += array_sum( $tax_amount );
			}
		}
		
		return max( 0.0, $base_cost );
	}

	/**
	 * Get warranty product IDs.
	 *
	 * @return array Array of warranty product IDs.
	 */
	public function get_warranty_products() {
		$products = get_option( 'w2f_pc_warranty_products', array() );
		
		if ( ! is_array( $products ) ) {
			return array();
		}
		
		// Filter out invalid product IDs and ensure they're integers.
		$products = array_filter( array_map( 'intval', $products ), function( $id ) {
			return $id > 0 && wc_get_product( $id ) !== false;
		} );
		
		return array_values( $products );
	}

	/**
	 * Save warranty product IDs.
	 *
	 * @param  array $product_ids Array of product IDs.
	 * @return bool
	 */
	public function save_warranty_products( $product_ids ) {
		if ( ! is_array( $product_ids ) ) {
			return false;
		}
		
		// Sanitize: ensure all are valid integers and products exist.
		$sanitized = array();
		foreach ( $product_ids as $product_id ) {
			$product_id = intval( $product_id );
			if ( $product_id > 0 && wc_get_product( $product_id ) !== false ) {
				$sanitized[] = $product_id;
			}
		}
		
		return update_option( 'w2f_pc_warranty_products', array_unique( $sanitized ) );
	}

	/**
	 * Get default warranty product ID.
	 *
	 * @return int Default warranty product ID, or 0 if not set.
	 */
	public function get_default_warranty() {
		return intval( get_option( 'w2f_pc_default_warranty', 0 ) );
	}

	/**
	 * Save default warranty product ID.
	 *
	 * @param  int $product_id Default warranty product ID.
	 * @return bool
	 */
	public function save_default_warranty( $product_id ) {
		$product_id = intval( $product_id );
		
		// Validate that product exists and is in warranty products list.
		if ( $product_id > 0 ) {
			$product = wc_get_product( $product_id );
			if ( $product && in_array( $product_id, $this->get_warranty_products(), true ) ) {
				return update_option( 'w2f_pc_default_warranty', $product_id );
			}
		}
		
		// If invalid, clear the default.
		return update_option( 'w2f_pc_default_warranty', 0 );
	}

	/**
	 * Get warranty description.
	 *
	 * @return string Warranty description.
	 */
	public function get_warranty_description() {
		return get_option( 'w2f_pc_warranty_description', '' );
	}

	/**
	 * Save warranty description.
	 *
	 * @param  string $description Warranty description.
	 * @return bool
	 */
	public function save_warranty_description( $description ) {
		return update_option( 'w2f_pc_warranty_description', sanitize_textarea_field( $description ) );
	}
}

