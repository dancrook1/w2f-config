<?php
/**
 * Component admin template
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$component_title = isset( $component_data['title'] ) ? $component_data['title'] : '';
$component_description = isset( $component_data['description'] ) ? $component_data['description'] : '';
$component_optional = isset( $component_data['optional'] ) && 'yes' === $component_data['optional'] ? 'yes' : 'no';
$component_options = isset( $component_data['options'] ) && is_array( $component_data['options'] ) ? $component_data['options'] : array();
$component_display_mode = isset( $component_data['display_mode'] ) ? $component_data['display_mode'] : 'dropdown';
$component_tab = isset( $component_data['tab'] ) ? $component_data['tab'] : '';
$component_show_search = isset( $component_data['show_search'] ) ? $component_data['show_search'] : 'yes';
$component_show_dropdown_image = isset( $component_data['show_dropdown_image'] ) ? $component_data['show_dropdown_image'] : 'no';
$component_enable_quantity = isset( $component_data['enable_quantity'] ) && 'yes' === $component_data['enable_quantity'] ? 'yes' : 'no';
$component_min_quantity = isset( $component_data['min_quantity'] ) ? intval( $component_data['min_quantity'] ) : 1;
$component_max_quantity = isset( $component_data['max_quantity'] ) ? intval( $component_data['max_quantity'] ) : 99;
?>

<div class="w2f_pc_component" data-component_id="<?php echo esc_attr( $component_id ); ?>">
	<!-- Hidden input to ensure component ID is always submitted -->
	<input type="hidden" name="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][_component_id]" value="<?php echo esc_attr( $component_id ); ?>" />
	
	<div class="component_header w2f-pc-component-toggle collapsed" role="button" tabindex="0" aria-expanded="false">
		<h4>
			<span class="component_drag_handle" title="<?php esc_attr_e( 'Drag to reorder', 'w2f-pc-configurator' ); ?>">☰</span>
			<svg class="w2f-pc-accordion-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
			<span class="component_title_display"><?php echo esc_html( $component_title ? $component_title : __( 'New Component', 'w2f-pc-configurator' ) ); ?></span>
			<button type="button" class="button remove_component"><?php esc_html_e( 'Remove', 'w2f-pc-configurator' ); ?></button>
		</h4>
	</div>

	<div class="component_data w2f-pc-component-content" style="display: none;">
		<p class="form-field">
			<label for="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][title]"><?php esc_html_e( 'Component Title', 'w2f-pc-configurator' ); ?></label>
			<input type="text" name="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][title]" value="<?php echo esc_attr( $component_title ); ?>" class="component_title" />
		</p>

		<p class="form-field">
			<label for="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][description]"><?php esc_html_e( 'Description', 'w2f-pc-configurator' ); ?></label>
			<textarea name="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][description]" rows="3" class="component_description"><?php echo esc_textarea( $component_description ); ?></textarea>
		</p>

		<p class="form-field">
			<label>
				<input type="checkbox" name="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][optional]" value="yes" <?php checked( $component_optional, 'yes' ); ?> />
				<?php esc_html_e( 'Optional Component', 'w2f-pc-configurator' ); ?>
			</label>
		</p>

		<p class="form-field">
			<label for="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][display_mode]"><?php esc_html_e( 'Display Mode', 'w2f-pc-configurator' ); ?></label>
			<select name="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][display_mode]" id="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][display_mode]">
				<option value="dropdown" <?php selected( $component_display_mode, 'dropdown' ); ?>><?php esc_html_e( 'Dropdown', 'w2f-pc-configurator' ); ?></option>
				<option value="thumbnail" <?php selected( $component_display_mode, 'thumbnail' ); ?>><?php esc_html_e( 'Thumbnail Grid', 'w2f-pc-configurator' ); ?></option>
			</select>
			<?php echo wc_help_tip( __( 'Choose how this component is displayed on the frontend. Thumbnail grid is ideal for visual products like cases.', 'w2f-pc-configurator' ) ); ?>
		</p>

		<p class="form-field">
			<label for="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][tab]"><?php esc_html_e( 'Tab', 'w2f-pc-configurator' ); ?></label>
			<select name="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][tab]" id="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][tab]" class="component-tab-select">
				<option value=""><?php esc_html_e( 'No Tab', 'w2f-pc-configurator' ); ?></option>
				<?php
				// Use $component_tabs if available, otherwise try global $tabs.
				$available_tabs = isset( $component_tabs ) ? $component_tabs : ( isset( $GLOBALS['tabs'] ) ? $GLOBALS['tabs'] : array() );
				if ( ! empty( $available_tabs ) && is_array( $available_tabs ) ) {
					foreach ( $available_tabs as $tab_name ) {
						$selected = ( $component_tab === $tab_name ) ? 'selected="selected"' : '';
						echo '<option value="' . esc_attr( $tab_name ) . '" ' . $selected . '>' . esc_html( $tab_name ) . '</option>';
					}
				}
				?>
			</select>
			<?php echo wc_help_tip( __( 'Assign this component to a tab. Tabs are defined above in the Tabs section.', 'w2f-pc-configurator' ) ); ?>
		</p>

		<p class="form-field">
			<label>
				<input type="checkbox" name="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][show_search]" value="yes" <?php checked( $component_show_search, 'yes' ); ?> />
				<?php esc_html_e( 'Show Search Bar', 'w2f-pc-configurator' ); ?>
			</label>
			<?php echo wc_help_tip( __( 'Enable search functionality for this component on the frontend. Search bar will only appear if there are more than 5 options.', 'w2f-pc-configurator' ) ); ?>
		</p>

		<p class="form-field">
			<label>
				<input type="checkbox" name="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][enable_quantity]" value="yes" <?php checked( $component_enable_quantity, 'yes' ); ?> class="w2f-pc-enable-quantity-checkbox" data-component-id="<?php echo esc_attr( $component_id ); ?>" />
				<?php esc_html_e( 'Enable Quantity Selection', 'w2f-pc-configurator' ); ?>
			</label>
			<?php echo wc_help_tip( __( 'Allow customers to select multiple quantities of this component (e.g., RAM sticks, fans).', 'w2f-pc-configurator' ) ); ?>
		</p>

		<p class="form-field w2f-pc-quantity-fields" style="<?php echo 'yes' === $component_enable_quantity ? '' : 'display: none;'; ?>">
			<label for="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][min_quantity]"><?php esc_html_e( 'Minimum Quantity', 'w2f-pc-configurator' ); ?></label>
			<input type="number" name="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][min_quantity]" value="<?php echo esc_attr( $component_min_quantity ); ?>" min="1" step="1" class="small-text" />
			<?php echo wc_help_tip( __( 'Minimum quantity customers can select for this component.', 'w2f-pc-configurator' ) ); ?>
		</p>

		<p class="form-field w2f-pc-quantity-fields" style="<?php echo 'yes' === $component_enable_quantity ? '' : 'display: none;'; ?>">
			<label for="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][max_quantity]"><?php esc_html_e( 'Maximum Quantity', 'w2f-pc-configurator' ); ?></label>
			<input type="number" name="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][max_quantity]" value="<?php echo esc_attr( $component_max_quantity ); ?>" min="1" step="1" class="small-text" />
			<?php echo wc_help_tip( __( 'Maximum quantity customers can select for this component. Set to prevent excessive quantities.', 'w2f-pc-configurator' ) ); ?>
		</p>


		<p class="form-field">
			<label for="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][options]"><?php esc_html_e( 'Available Products', 'w2f-pc-configurator' ); ?></label>
			<select name="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][options][]" class="wc-product-search" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Search for products&hellip;', 'w2f-pc-configurator' ); ?>">
				<?php
				$component_options = isset( $component_data['options'] ) && is_array( $component_data['options'] ) ? $component_data['options'] : array();
				foreach ( $component_options as $product_id ) {
					$product = wc_get_product( $product_id );
					if ( $product ) {
						echo '<option value="' . esc_attr( $product_id ) . '" selected="selected">' . esc_html( $product->get_formatted_name() ) . '</option>';
					}
				}
				?>
			</select>
			<?php echo wc_help_tip( __( 'Select individual products to include in this component.', 'w2f-pc-configurator' ) ); ?>
		</p>

		<p class="form-field">
			<label for="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][categories]"><?php esc_html_e( 'Available Categories', 'w2f-pc-configurator' ); ?></label>
			<select name="w2f_pc_components[<?php echo esc_attr( $component_id ); ?>][categories][]" class="wc-enhanced-select" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Select categories&hellip;', 'w2f-pc-configurator' ); ?>">
				<?php
				$component_categories = isset( $component_data['categories'] ) && is_array( $component_data['categories'] ) ? $component_data['categories'] : array();
				$product_categories = get_terms( array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				) );

				if ( ! is_wp_error( $product_categories ) ) {
					foreach ( $product_categories as $category ) {
						$selected = in_array( $category->term_id, $component_categories ) ? 'selected="selected"' : '';
						echo '<option value="' . esc_attr( $category->term_id ) . '" ' . $selected . '>' . esc_html( $category->name ) . '</option>';
					}
				}
				?>
			</select>
			<?php echo wc_help_tip( __( 'Select product categories. All products in selected categories will be available for this component.', 'w2f-pc-configurator' ) ); ?>
		</p>

	</div>
</div>

