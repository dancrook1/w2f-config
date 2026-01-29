<?php
/**
 * Compatibility functions for W2F PC Configurator
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility: Provide is_composite_product() function if Composite Products plugin is not active.
 * This prevents fatal errors in themes that check for composite products.
 *
 * Note: The Composite Products plugin version doesn't take parameters, but themes may call it with a product.
 * We'll handle both cases for maximum compatibility.
 *
 * @param  mixed $product Optional product object or ID.
 * @return boolean
 */
if ( ! function_exists( 'is_composite_product' ) ) {
	function is_composite_product( $product = false ) {
		// If Composite Products plugin is active, it should define this function.
		// If we're here, the plugin is not active or function not loaded yet.

		// Handle case where product is passed as parameter (theme compatibility).
		if ( $product ) {
			if ( is_numeric( $product ) ) {
				$product = wc_get_product( $product );
			}
			if ( $product && is_a( $product, 'WC_Product' ) ) {
				return 'composite' === $product->get_type();
			}
			return false;
		}

		// Handle case with no parameters (matches Composite Products plugin signature).
		global $product;
		if ( function_exists( 'is_product' ) && is_product() && ! empty( $product ) && is_callable( array( $product, 'is_type' ) ) ) {
			return $product->is_type( 'composite' );
		}

		return false;
	}
}

