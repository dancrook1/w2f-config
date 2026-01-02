<?php
/**
 * Template functions for W2F PC Configurator
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output the PC Configurator add to cart area.
 * This function is called by WooCommerce's action hook system.
 */
if ( ! function_exists( 'woocommerce_pc_configurator_add_to_cart' ) ) {
	function woocommerce_pc_configurator_add_to_cart() {
		// Ensure Display class is available.
		if ( class_exists( 'W2F_PC_Display' ) ) {
			W2F_PC_Display::instance()->configurator_add_to_cart();
		}
	}
}

// Register the function as an action hook, just like WooCommerce does for other product types.
// This must be registered early, so we do it here in the template functions file.
add_action( 'woocommerce_pc_configurator_add_to_cart', 'woocommerce_pc_configurator_add_to_cart', 30 );

