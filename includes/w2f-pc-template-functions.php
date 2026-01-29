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

/**
 * Get the system ID for a PC configurator order item.
 *
 * @param  WC_Order_Item_Product|int $item Order item object or item ID.
 * @param  WC_Order|null              $order Optional order object (required if $item is an ID).
 * @return string|false System ID or false if not found.
 */
if ( ! function_exists( 'w2f_pc_get_order_item_system_id' ) ) {
	function w2f_pc_get_order_item_system_id( $item, $order = null ) {
		// If item is an ID, get the item object.
		if ( is_numeric( $item ) && $order ) {
			$items = $order->get_items();
			$item = isset( $items[ $item ] ) ? $items[ $item ] : null;
		}
		
		if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			return false;
		}
		
		// Try to get system ID from item meta.
		$system_id = $item->get_meta( '_w2f_pc_system_id' );
		
		// If not found on this item, check if it's a child item and get from parent.
		if ( ! $system_id && $item->get_meta( '_w2f_pc_is_child_item' ) === 'yes' ) {
			$configurator_product_id = $item->get_meta( '_w2f_pc_configurator_product_id' );
			if ( $configurator_product_id && $order ) {
				// Find parent item with this product ID.
				foreach ( $order->get_items() as $order_item ) {
					if ( is_a( $order_item, 'WC_Order_Item_Product' ) && 
						 $order_item->get_product_id() == $configurator_product_id &&
						 $order_item->get_meta( '_w2f_pc_is_configurator' ) === 'yes' ) {
						$system_id = $order_item->get_meta( '_w2f_pc_system_id' );
						break;
					}
				}
			}
		}
		
		return $system_id ? $system_id : false;
	}
}

/**
 * Get all order items for a specific system ID.
 *
 * @param  WC_Order $order    Order object.
 * @param  string   $system_id System ID to search for.
 * @return array Array of order item objects with matching system ID.
 */
if ( ! function_exists( 'w2f_pc_get_order_items_by_system_id' ) ) {
	function w2f_pc_get_order_items_by_system_id( $order, $system_id ) {
		$matching_items = array();
		
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}
			
			$item_system_id = $item->get_meta( '_w2f_pc_system_id' );
			if ( $item_system_id === $system_id ) {
				$matching_items[ $item_id ] = $item;
			}
		}
		
		return $matching_items;
	}
}

/**
 * Get all system IDs from an order.
 *
 * @param  WC_Order $order Order object.
 * @return array Array of system IDs found in the order.
 */
if ( ! function_exists( 'w2f_pc_get_order_system_ids' ) ) {
	function w2f_pc_get_order_system_ids( $order ) {
		// First try to get from order meta (faster).
		$system_ids = $order->get_meta( '_w2f_pc_system_ids' );
		if ( is_array( $system_ids ) && ! empty( $system_ids ) ) {
			return $system_ids;
		}
		
		// Fallback: collect from items.
		$system_ids = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}
			
			$system_id = $item->get_meta( '_w2f_pc_system_id' );
			if ( $system_id && ! in_array( $system_id, $system_ids, true ) ) {
				$system_ids[] = $system_id;
			}
		}
		
		return $system_ids;
	}
}

