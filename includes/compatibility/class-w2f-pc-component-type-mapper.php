<?php
/**
 * W2F_PC_Component_Type_Mapper class
 *
 * Maps components to standardized component types using WooCommerce categories.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Component Type Mapper - maps WC categories to component types.
 *
 * @class    W2F_PC_Component_Type_Mapper
 * @version  2.0.0
 */
class W2F_PC_Component_Type_Mapper {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Component_Type_Mapper
	 */
	protected static $_instance = null;

	/**
	 * Option name for storing mappings.
	 */
	const OPTION_NAME = 'w2f_pc_component_type_mappings';

	/**
	 * Supported component types.
	 *
	 * @var array
	 */
	private $component_types = array(
		'case' => array(
			'label' => 'Case',
			'description' => 'Computer cases and enclosures',
			'multi_select' => false,
		),
		'cpu' => array(
			'label' => 'CPU',
			'description' => 'Processors',
			'multi_select' => false,
		),
		'motherboard' => array(
			'label' => 'Motherboard',
			'description' => 'Mainboards',
			'multi_select' => false,
		),
		'gpu' => array(
			'label' => 'GPU',
			'description' => 'Graphics cards',
			'multi_select' => false,
		),
		'psu' => array(
			'label' => 'PSU',
			'description' => 'Power supplies',
			'multi_select' => false,
		),
		'cooler' => array(
			'label' => 'CPU Cooler',
			'description' => 'CPU cooling solutions',
			'multi_select' => false,
		),
		'ram' => array(
			'label' => 'RAM',
			'description' => 'Memory modules',
			'multi_select' => false,
		),
		'storage' => array(
			'label' => 'Storage',
			'description' => 'SSDs and HDDs',
			'multi_select' => true,
		),
		'fans' => array(
			'label' => 'Case Fans',
			'description' => 'Cooling fans',
			'multi_select' => true,
		),
		'accessories' => array(
			'label' => 'Accessories',
			'description' => 'Other accessories',
			'multi_select' => true,
		),
	);

	/**
	 * Category mappings (component_type => category_ids).
	 *
	 * @var array
	 */
	private $mappings = array();

	/**
	 * Main instance.
	 *
	 * @return W2F_PC_Component_Type_Mapper
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
		$this->load_mappings();
	}

	/**
	 * Load mappings from database.
	 */
	private function load_mappings() {
		$saved = get_option( self::OPTION_NAME, array() );

		if ( ! empty( $saved ) && is_array( $saved ) ) {
			$this->mappings = $saved;
		} else {
			// Initialize with empty mappings.
			foreach ( array_keys( $this->component_types ) as $type ) {
				$this->mappings[ $type ] = array();
			}
		}
	}

	/**
	 * Get all supported component types.
	 *
	 * @return array
	 */
	public function get_component_types() {
		return $this->component_types;
	}

	/**
	 * Get a single component type definition.
	 *
	 * @param  string $type Component type key.
	 * @return array|null
	 */
	public function get_component_type_definition( $type ) {
		return isset( $this->component_types[ $type ] ) ? $this->component_types[ $type ] : null;
	}

	/**
	 * Check if a component type allows multi-select.
	 *
	 * @param  string $type Component type.
	 * @return bool
	 */
	public function is_multi_select( $type ) {
		return isset( $this->component_types[ $type ] ) && ! empty( $this->component_types[ $type ]['multi_select'] );
	}

	/**
	 * Get all category mappings.
	 *
	 * @return array
	 */
	public function get_mappings() {
		return $this->mappings;
	}

	/**
	 * Get category IDs for a component type.
	 *
	 * @param  string $type Component type.
	 * @return array Category IDs.
	 */
	public function get_categories_for_type( $type ) {
		return isset( $this->mappings[ $type ] ) ? $this->mappings[ $type ] : array();
	}

	/**
	 * Set category IDs for a component type.
	 *
	 * @param string $type        Component type.
	 * @param array  $category_ids Category IDs.
	 */
	public function set_categories_for_type( $type, $category_ids ) {
		if ( isset( $this->component_types[ $type ] ) ) {
			$this->mappings[ $type ] = array_map( 'intval', $category_ids );
			update_option( self::OPTION_NAME, $this->mappings );
		}
	}

	/**
	 * Save all mappings.
	 *
	 * @param array $mappings All mappings.
	 */
	public function save_mappings( $mappings ) {
		foreach ( $mappings as $type => $category_ids ) {
			if ( isset( $this->component_types[ $type ] ) ) {
				$this->mappings[ $type ] = array_map( 'intval', (array) $category_ids );
			}
		}
		update_option( self::OPTION_NAME, $this->mappings );
	}

	/**
	 * Determine component type for a product.
	 *
	 * @param  string          $component_id         Component ID from configurator.
	 * @param  WC_Product      $product              Product to check.
	 * @param  W2F_PC_Product  $configurator_product Optional configurator product.
	 * @return string Component type (or 'unknown').
	 */
	public function get_component_type( $component_id, $product, $configurator_product = null ) {
		// First, try to infer from component ID/title.
		$type_from_id = $this->infer_type_from_component_id( $component_id, $configurator_product );
		if ( $type_from_id && 'unknown' !== $type_from_id ) {
			return $type_from_id;
		}

		// Second, try to determine from product categories.
		$product_categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );

		if ( is_wp_error( $product_categories ) ) {
			return 'unknown';
		}

		foreach ( $this->mappings as $type => $category_ids ) {
			if ( ! empty( $category_ids ) ) {
				$intersection = array_intersect( $product_categories, $category_ids );
				if ( ! empty( $intersection ) ) {
					return $type;
				}
			}
		}

		// Fallback to component ID.
		return $type_from_id ?: 'unknown';
	}

	/**
	 * Infer component type from component ID or title.
	 *
	 * @param  string         $component_id         Component ID.
	 * @param  W2F_PC_Product $configurator_product Optional configurator product.
	 * @return string|null
	 */
	private function infer_type_from_component_id( $component_id, $configurator_product = null ) {
		$id_lower = strtolower( $component_id );

		// Get component title if available.
		$component_title = '';
		if ( $configurator_product ) {
			$component = $configurator_product->get_component( $component_id );
			if ( $component ) {
				$component_title = strtolower( $component->get_title() );
			}
		}

		// Keywords to type mapping.
		$keywords = array(
			'cpu' => array( 'cpu', 'processor' ),
			'motherboard' => array( 'motherboard', 'mainboard', 'mobo' ),
			'gpu' => array( 'gpu', 'graphics', 'video_card', 'videocard' ),
			'ram' => array( 'ram', 'memory' ),
			'psu' => array( 'psu', 'power_supply', 'powersupply' ),
			'case' => array( 'case', 'chassis', 'enclosure' ),
			'cooler' => array( 'cooler', 'cooling', 'cpu_cooler', 'cpucooler' ),
			'storage' => array( 'storage', 'ssd', 'hdd', 'nvme', 'drive' ),
			'fans' => array( 'fan', 'fans' ),
			'accessories' => array( 'accessory', 'accessories', 'misc' ),
		);

		// Check component ID.
		foreach ( $keywords as $type => $type_keywords ) {
			foreach ( $type_keywords as $keyword ) {
				if ( strpos( $id_lower, $keyword ) !== false ) {
					return $type;
				}
			}
		}

		// Check component title.
		if ( $component_title ) {
			foreach ( $keywords as $type => $type_keywords ) {
				foreach ( $type_keywords as $keyword ) {
					if ( strpos( $component_title, $keyword ) !== false ) {
						return $type;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Get component type for a product category.
	 *
	 * @param  int $category_id Category ID.
	 * @return string|null Component type or null.
	 */
	public function get_type_for_category( $category_id ) {
		foreach ( $this->mappings as $type => $category_ids ) {
			if ( in_array( $category_id, $category_ids, true ) ) {
				return $type;
			}
		}
		return null;
	}

	/**
	 * Get all WooCommerce product categories.
	 *
	 * @return array
	 */
	public function get_available_categories() {
		$categories = get_terms( array(
			'taxonomy' => 'product_cat',
			'hide_empty' => false,
			'orderby' => 'name',
		) );

		if ( is_wp_error( $categories ) ) {
			return array();
		}

		$result = array();
		foreach ( $categories as $cat ) {
			$result[ $cat->term_id ] = array(
				'name' => $cat->name,
				'slug' => $cat->slug,
				'count' => $cat->count,
				'parent' => $cat->parent,
			);
		}

		return $result;
	}

	/**
	 * Get hierarchical category tree.
	 *
	 * @return array
	 */
	public function get_category_tree() {
		$categories = $this->get_available_categories();
		$tree = array();

		// Build tree structure.
		foreach ( $categories as $id => $cat ) {
			if ( $cat['parent'] === 0 ) {
				$tree[ $id ] = $cat;
				$tree[ $id ]['children'] = $this->get_category_children( $id, $categories );
			}
		}

		return $tree;
	}

	/**
	 * Get children of a category.
	 *
	 * @param  int   $parent_id  Parent category ID.
	 * @param  array $categories All categories.
	 * @return array
	 */
	private function get_category_children( $parent_id, $categories ) {
		$children = array();

		foreach ( $categories as $id => $cat ) {
			if ( $cat['parent'] === $parent_id ) {
				$children[ $id ] = $cat;
				$children[ $id ]['children'] = $this->get_category_children( $id, $categories );
			}
		}

		return $children;
	}
}
