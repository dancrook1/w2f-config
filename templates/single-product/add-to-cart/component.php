<?php
/**
 * Component template for PC Configurator
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$default_product_id = isset( $default_configuration[ $component_id ] ) ? $default_configuration[ $component_id ] : 0;
$display_mode = $component->get_display_mode();
$option_products = $component->get_option_products();

// Calculate base price (default option price) including tax.
$base_price = 0;
if ( $default_product_id > 0 ) {
	$default_product = wc_get_product( $default_product_id );
	if ( $default_product ) {
		$base_price = (float) wc_get_price_including_tax( $default_product );
	}
}

// Sort products by price (ascending) including tax.
uasort( $option_products, function( $a, $b ) {
	$price_a = (float) wc_get_price_including_tax( $a );
	$price_b = (float) wc_get_price_including_tax( $b );
	if ( $price_a === $price_b ) {
		return 0;
	}
	return ( $price_a < $price_b ) ? -1 : 1;
} );
?>

<div class="w2f-pc-component w2f-pc-component-<?php echo esc_attr( $display_mode ); ?>" data-component-id="<?php echo esc_attr( $component_id ); ?>" data-base-price="<?php echo esc_attr( $base_price ); ?>">
	<h3 class="component-title">
		<?php echo esc_html( $component->get_title() ); ?>
		<?php if ( $component->is_optional() ) : ?>
			<span class="component-optional"><?php esc_html_e( '(Optional)', 'w2f-pc-configurator' ); ?></span>
		<?php endif; ?>
	</h3>
	
	<?php if ( $component->get_description() ) : ?>
		<p class="component-description"><?php echo wp_kses_post( $component->get_description() ); ?></p>
	<?php endif; ?>

	<div class="component-options component-options-<?php echo esc_attr( $display_mode ); ?>">
		<?php if ( $component->show_search() && count( $option_products ) > 5 ) : ?>
			<div class="w2f-pc-component-search">
				<input type="text" class="w2f-pc-search-input" placeholder="<?php esc_attr_e( 'Search options...', 'w2f-pc-configurator' ); ?>" data-component-id="<?php echo esc_attr( $component_id ); ?>" />
				<svg class="w2f-pc-search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z" fill="currentColor"/>
				</svg>
			</div>
		<?php endif; ?>
		<?php if ( 'thumbnail' === $display_mode ) : ?>
			<!-- Thumbnail Grid View -->
			<div class="w2f-pc-thumbnail-wrapper" data-component-id="<?php echo esc_attr( $component_id ); ?>">
				<div class="w2f-pc-thumbnail-grid">
					<?php if ( $component->is_optional() ) : ?>
						<!-- None Option for Optional Components -->
						<label class="w2f-pc-thumbnail-option w2f-pc-none-option <?php echo ( 0 === $default_product_id ) ? 'selected' : ''; ?>" data-product-id="0" data-product-name="<?php esc_attr_e( 'None', 'w2f-pc-configurator' ); ?>" data-price="0" data-relative-price="0">
							<input type="radio" name="w2f_pc_configuration[<?php echo esc_attr( $component_id ); ?>]" value="0" <?php checked( 0, $default_product_id ); ?> class="component-select-radio" data-component-id="<?php echo esc_attr( $component_id ); ?>" />
							<div class="thumbnail-image">
								<div class="w2f-pc-none-placeholder"><?php esc_html_e( 'None', 'w2f-pc-configurator' ); ?></div>
							</div>
							<div class="thumbnail-info">
								<span class="thumbnail-name"><?php esc_html_e( 'None', 'w2f-pc-configurator' ); ?></span>
								<span class="thumbnail-price" data-relative-price="0">—</span>
							</div>
						</label>
					<?php endif; ?>
					<?php foreach ( $option_products as $option_product_id => $option_product ) : ?>
						<?php
						$selected = ( $default_product_id === $option_product_id ) ? 'selected' : '';
						$image_id = $option_product->get_image_id();
						$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
						$option_price = (float) wc_get_price_including_tax( $option_product );
						$relative_price = $option_price - $base_price;
						$relative_price_formatted = '';
						if ( $relative_price > 0 ) {
							$relative_price_formatted = '+' . wc_price( $relative_price );
						} elseif ( $relative_price < 0 ) {
							$relative_price_formatted = wc_price( $relative_price );
						} else {
							$relative_price_formatted = '—';
						}
						?>
						<label class="w2f-pc-thumbnail-option <?php echo esc_attr( $selected ); ?>" data-product-id="<?php echo esc_attr( $option_product_id ); ?>" data-product-name="<?php echo esc_attr( $option_product->get_name() ); ?>" data-price="<?php echo esc_attr( $option_price ); ?>" data-relative-price="<?php echo esc_attr( $relative_price ); ?>">
							<input type="radio" name="w2f_pc_configuration[<?php echo esc_attr( $component_id ); ?>]" value="<?php echo esc_attr( $option_product_id ); ?>" <?php checked( $default_product_id, $option_product_id ); ?> class="component-select-radio" data-component-id="<?php echo esc_attr( $component_id ); ?>" />
							<div class="thumbnail-image">
								<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $option_product->get_name() ); ?>" />
								<?php if ( $selected ) : ?>
									<span class="selected-indicator">✓</span>
								<?php endif; ?>
								<button type="button" class="w2f-pc-quick-view" data-product-id="<?php echo esc_attr( $option_product_id ); ?>" aria-label="<?php esc_attr_e( 'View product details', 'w2f-pc-configurator' ); ?>">
									<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M8 3C4.5 3 1.73 5.61 0 9c1.73 3.39 4.5 6 8 6s6.27-2.61 8-6c-1.73-3.39-4.5-6-8-6zM8 11.5c-1.38 0-2.5-1.12-2.5-2.5S6.62 6.5 8 6.5s2.5 1.12 2.5 2.5S9.38 11.5 8 11.5z" fill="currentColor"/>
									</svg>
								</button>
							</div>
							<div class="thumbnail-info">
								<span class="thumbnail-name"><?php echo esc_html( $option_product->get_name() ); ?></span>
								<span class="thumbnail-price" data-relative-price="<?php echo esc_attr( $relative_price ); ?>"><?php echo wp_kses_post( $relative_price_formatted ); ?></span>
							</div>
						</label>
					<?php endforeach; ?>
				</div>
				<!-- Pagination Controls -->
				<div class="w2f-pc-thumbnail-pagination" style="display: none;">
					<button type="button" class="w2f-pc-pagination-prev" aria-label="<?php esc_attr_e( 'Previous page', 'w2f-pc-configurator' ); ?>">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M10 12L6 8l4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<?php esc_html_e( 'Previous', 'w2f-pc-configurator' ); ?>
					</button>
					<span class="w2f-pc-pagination-info">
						<span class="w2f-pc-pagination-current">1</span>
						<?php esc_html_e( 'of', 'w2f-pc-configurator' ); ?>
						<span class="w2f-pc-pagination-total">1</span>
					</span>
					<button type="button" class="w2f-pc-pagination-next" aria-label="<?php esc_attr_e( 'Next page', 'w2f-pc-configurator' ); ?>">
						<?php esc_html_e( 'Next', 'w2f-pc-configurator' ); ?>
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</button>
				</div>
			</div>
		<?php else : ?>
			<!-- Dropdown View -->
			<?php if ( $component->show_dropdown_image() ) : ?>
				<!-- Custom Dropdown with Images -->
				<div class="w2f-pc-custom-dropdown" data-component-id="<?php echo esc_attr( $component_id ); ?>">
					<div class="w2f-pc-dropdown-selected">
						<?php
						$selected_product = null;
						if ( $default_product_id > 0 ) {
							$selected_product = wc_get_product( $default_product_id );
						}
						?>
						<?php if ( $selected_product ) : ?>
							<?php
							$image_id = $selected_product->get_image_id();
							$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
							?>
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $selected_product->get_name() ); ?>" class="w2f-pc-dropdown-image" />
							<span class="w2f-pc-dropdown-text"><?php echo esc_html( $selected_product->get_name() ); ?></span>
						<?php else : ?>
							<span class="w2f-pc-dropdown-text"><?php esc_html_e( 'Select an option...', 'w2f-pc-configurator' ); ?></span>
						<?php endif; ?>
						<svg class="w2f-pc-dropdown-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
					<div class="w2f-pc-dropdown-options">
						<?php if ( $component->is_optional() ) : ?>
							<!-- None Option for Optional Components -->
							<div class="w2f-pc-dropdown-option w2f-pc-none-option <?php echo ( 0 === $default_product_id ) ? 'selected' : ''; ?>" data-product-id="0" data-product-name="<?php esc_attr_e( 'None', 'w2f-pc-configurator' ); ?>" data-price="0" data-relative-price="0">
								<span class="w2f-pc-dropdown-option-text"><?php esc_html_e( 'None', 'w2f-pc-configurator' ); ?></span>
								<span class="w2f-pc-dropdown-option-price">—</span>
							</div>
						<?php endif; ?>
						<?php foreach ( $option_products as $option_product_id => $option_product ) : ?>
							<?php
							$selected = ( $default_product_id === $option_product_id ) ? 'selected' : '';
							$image_id = $option_product->get_image_id();
							$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
							$option_price = (float) wc_get_price_including_tax( $option_product );
							$relative_price = $option_price - $base_price;
							$relative_price_formatted = '';
							if ( $relative_price > 0 ) {
								$relative_price_formatted = '+' . wc_price( $relative_price );
							} elseif ( $relative_price < 0 ) {
								$relative_price_formatted = wc_price( $relative_price );
							} else {
								$relative_price_formatted = '—';
							}
							?>
							<div class="w2f-pc-dropdown-option <?php echo esc_attr( $selected ); ?>" data-product-id="<?php echo esc_attr( $option_product_id ); ?>" data-product-name="<?php echo esc_attr( $option_product->get_name() ); ?>" data-price="<?php echo esc_attr( $option_price ); ?>" data-relative-price="<?php echo esc_attr( $relative_price ); ?>">
								<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $option_product->get_name() ); ?>" class="w2f-pc-dropdown-option-image" />
								<span class="w2f-pc-dropdown-option-text"><?php echo esc_html( $option_product->get_name() ); ?></span>
								<span class="w2f-pc-dropdown-option-price"><?php echo wp_kses_post( $relative_price_formatted ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
					<input type="hidden" name="w2f_pc_configuration[<?php echo esc_attr( $component_id ); ?>]" class="component-select" value="<?php echo esc_attr( $default_product_id ); ?>" data-component-id="<?php echo esc_attr( $component_id ); ?>" />
				</div>
			<?php else : ?>
				<!-- Standard Dropdown -->
				<select name="w2f_pc_configuration[<?php echo esc_attr( $component_id ); ?>]" class="component-select" data-component-id="<?php echo esc_attr( $component_id ); ?>">
					<?php if ( $component->is_optional() ) : ?>
						<option value="0" <?php selected( 0, $default_product_id ); ?> data-price="0" data-relative-price="0" data-product-name="<?php esc_attr_e( 'None', 'w2f-pc-configurator' ); ?>"><?php esc_html_e( 'None', 'w2f-pc-configurator' ); ?></option>
					<?php else : ?>
						<option value=""><?php esc_html_e( 'Select an option...', 'w2f-pc-configurator' ); ?></option>
					<?php endif; ?>
					<?php foreach ( $option_products as $option_product_id => $option_product ) : ?>
						<?php
						$selected = ( $default_product_id === $option_product_id ) ? 'selected' : '';
						$option_price = (float) wc_get_price_including_tax( $option_product );
						$relative_price = $option_price - $base_price;
						$relative_price_formatted = '';
						if ( $relative_price > 0 ) {
							$relative_price_formatted = ' (+' . wc_price( $relative_price ) . ')';
						} elseif ( $relative_price < 0 ) {
							$relative_price_formatted = ' (' . wc_price( $relative_price ) . ')';
						}
						?>
						<option value="<?php echo esc_attr( $option_product_id ); ?>" <?php echo esc_attr( $selected ); ?> data-price="<?php echo esc_attr( $option_price ); ?>" data-relative-price="<?php echo esc_attr( $relative_price ); ?>" data-product-name="<?php echo esc_attr( $option_product->get_name() ); ?>">
							<?php echo esc_html( $option_product->get_name() ); ?><?php echo wp_kses_post( $relative_price_formatted ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		<?php endif; ?>
		
		<?php if ( $component->enable_quantity() ) : ?>
			<div class="w2f-pc-component-quantity" data-component-id="<?php echo esc_attr( $component_id ); ?>">
				<label for="w2f_pc_configuration_quantity[<?php echo esc_attr( $component_id ); ?>]">
					<?php esc_html_e( 'Quantity:', 'w2f-pc-configurator' ); ?>
				</label>
				<input 
					type="number" 
					name="w2f_pc_configuration_quantity[<?php echo esc_attr( $component_id ); ?>]" 
					id="w2f_pc_configuration_quantity[<?php echo esc_attr( $component_id ); ?>]"
					class="w2f-pc-quantity-input" 
					value="<?php echo esc_attr( $component->get_min_quantity() ); ?>" 
					min="<?php echo esc_attr( $component->get_min_quantity() ); ?>" 
					max="<?php echo esc_attr( $component->get_max_quantity() ); ?>" 
					step="1"
					data-component-id="<?php echo esc_attr( $component_id ); ?>"
					data-min-quantity="<?php echo esc_attr( $component->get_min_quantity() ); ?>"
					data-max-quantity="<?php echo esc_attr( $component->get_max_quantity() ); ?>"
				/>
			</div>
		<?php endif; ?>
	</div>
</div>

