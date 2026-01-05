<?php
/**
 * W2F_PC_Component class
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Component abstraction. Contains data and maintains state.
 *
 * @class    W2F_PC_Component
 * @version  1.0.0
 */
class W2F_PC_Component {

	/**
	 * The component ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * The component data.
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * The configurator product that the component belongs to.
	 *
	 * @var W2F_PC_Product
	 */
	private $configurator;

	/**
	 * Component options (product IDs).
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Component categories (category IDs).
	 *
	 * @var array
	 */
	private $categories = array();

	/**
	 * Constructor.
	 *
	 * @param  string         $id
	 * @param  W2F_PC_Product $configurator
	 * @param  array          $data
	 */
	public function __construct( $id, $configurator, $data = array() ) {
		$this->id          = strval( $id );
		$this->configurator = $configurator;
		$this->data        = wp_parse_args( $data, array(
			'title'              => '',
			'description'        => '',
			'optional'           => 'no',
			'display_mode'       => 'dropdown',
			'tab'                => '',
			'show_search'        => 'yes',
			'show_dropdown_image' => 'yes',
			'enable_quantity'    => 'no',
			'min_quantity'       => 1,
			'max_quantity'       => 99,
			'options'            => array(),
			'categories'         => array(),
		) );

		// Load options (products).
		if ( ! empty( $this->data['options'] ) && is_array( $this->data['options'] ) ) {
			$this->options = array_map( 'intval', $this->data['options'] );
		}

		// Load categories.
		if ( ! empty( $this->data['categories'] ) && is_array( $this->data['categories'] ) ) {
			$this->categories = array_map( 'intval', $this->data['categories'] );
		}
	}

	/**
	 * Get component ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get component title.
	 *
	 * @return string
	 */
	public function get_title() {
		return isset( $this->data['title'] ) ? $this->data['title'] : '';
	}

	/**
	 * Get component description.
	 *
	 * @return string
	 */
	public function get_description() {
		return isset( $this->data['description'] ) ? $this->data['description'] : '';
	}

	/**
	 * Check if component is optional.
	 *
	 * @return boolean
	 */
	public function is_optional() {
		return isset( $this->data['optional'] ) && 'yes' === $this->data['optional'];
	}

	/**
	 * Get component display mode.
	 *
	 * @return string
	 */
	public function get_display_mode() {
		return isset( $this->data['display_mode'] ) ? $this->data['display_mode'] : 'dropdown';
	}

	/**
	 * Get component tab.
	 *
	 * @return string
	 */
	public function get_tab() {
		return isset( $this->data['tab'] ) ? $this->data['tab'] : '';
	}

	/**
	 * Check if search should be shown.
	 *
	 * @return boolean
	 */
	public function show_search() {
		return isset( $this->data['show_search'] ) && 'yes' === $this->data['show_search'];
	}

	/**
	 * Check if dropdown should show images.
	 * Always returns true for dropdown mode (images are now default).
	 *
	 * @return boolean
	 */
	public function show_dropdown_image() {
		// If display mode is dropdown, always show images (they're now the default).
		if ( 'dropdown' === $this->get_display_mode() ) {
			return true;
		}
		// For other modes, check the setting (though this shouldn't be used for non-dropdown modes).
		return isset( $this->data['show_dropdown_image'] ) && 'yes' === $this->data['show_dropdown_image'];
	}

	/**
	 * Check if quantity selection is enabled.
	 *
	 * @return boolean
	 */
	public function enable_quantity() {
		return isset( $this->data['enable_quantity'] ) && 'yes' === $this->data['enable_quantity'];
	}

	/**
	 * Get minimum quantity.
	 *
	 * @return int
	 */
	public function get_min_quantity() {
		return isset( $this->data['min_quantity'] ) ? max( 1, intval( $this->data['min_quantity'] ) ) : 1;
	}

	/**
	 * Get maximum quantity.
	 *
	 * @return int
	 */
	public function get_max_quantity() {
		return isset( $this->data['max_quantity'] ) ? max( 1, intval( $this->data['max_quantity'] ) ) : 99;
	}

	/**
	 * Get component options (product IDs).
	 *
	 * @return array
	 */
	public function get_options() {
		return $this->options;
	}

	/**
	 * Get component categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		return $this->categories;
	}

	/**
	 * Get component option products (from both direct products and categories).
	 *
	 * @return array
	 */
	public function get_option_products() {
		$products = array();

		// Add direct product options.
		foreach ( $this->options as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product && $product->is_purchasable() ) {
				$products[ $product_id ] = $product;
			}
		}

		// Add products from categories.
		if ( ! empty( $this->categories ) ) {
			$args = array(
				'post_type'      => 'product',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $this->categories,
						'operator' => 'IN',
					),
				),
			);

			$query = new WP_Query( $args );
			if ( $query->have_posts() ) {
				foreach ( $query->posts as $post ) {
					$product = wc_get_product( $post->ID );
					if ( $product && $product->is_purchasable() && ! isset( $products[ $product->get_id() ] ) ) {
						$products[ $product->get_id() ] = $product;
					}
				}
			}
			wp_reset_postdata();
		}

		return $products;
	}

	/**
	 * Check if a product ID is a valid option for this component.
	 *
	 * @param  int $product_id
	 * @return boolean
	 */
	public function is_valid_option( $product_id ) {
		// Check direct products.
		if ( in_array( (int) $product_id, $this->options, true ) ) {
			return true;
		}

		// Check if product belongs to any of the selected categories.
		if ( ! empty( $this->categories ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$product_categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
				foreach ( $this->categories as $category_id ) {
					if ( in_array( $category_id, $product_categories, true ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Get component data.
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Get configurator product.
	 *
	 * @return W2F_PC_Product
	 */
	public function get_configurator() {
		return $this->configurator;
	}
}

