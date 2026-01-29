<?php
/**
 * Helper functions for W2F PC Configurator
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if a product is a PC Configurator product.
 *
 * @param  mixed $product
 * @return boolean
 */
function w2f_pc_is_configurator_product( $product ) {
	if ( ! is_a( $product, 'WC_Product' ) ) {
		$product = wc_get_product( $product );
	}
	if ( ! $product ) {
		return false;
	}
	// Check by type first (more reliable).
	if ( 'pc_configurator' === $product->get_type() ) {
		return true;
	}
	// Fallback to class check.
	return is_a( $product, 'W2F_PC_Product' );
}

/**
 * Get PC Configurator product.
 *
 * @param  mixed $product
 * @return W2F_PC_Product|false
 */
function w2f_pc_get_configurator_product( $product ) {
	if ( ! is_a( $product, 'WC_Product' ) ) {
		$product = wc_get_product( $product );
	}
	if ( w2f_pc_is_configurator_product( $product ) ) {
		return $product;
	}
	return false;
}

/**
 * Get component options for a component.
 *
 * @param  int    $product_id
 * @param  string $component_id
 * @return array
 */
function w2f_pc_get_component_options( $product_id, $component_id ) {
	$product = w2f_pc_get_configurator_product( $product_id );
	if ( ! $product ) {
		return array();
	}
	$component = $product->get_component( $component_id );
	if ( ! $component ) {
		return array();
	}
	return $component->get_options();
}

