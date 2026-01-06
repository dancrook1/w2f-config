<?php
/**
 * Warranty Settings Admin View
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$warranty_manager = W2F_PC_Warranty_Manager::instance();
$price_brackets = $warranty_manager->get_price_brackets();
$warranty_products = $warranty_manager->get_warranty_products();
$default_warranty = $warranty_manager->get_default_warranty();
$warranty_description = $warranty_manager->get_warranty_description();

// Handle form submission.
if ( isset( $_POST['w2f_pc_warranty_settings_save'] ) && check_admin_referer( 'w2f_pc_warranty_settings', 'w2f_pc_warranty_settings_nonce' ) ) {
	// Save price brackets.
	if ( isset( $_POST['w2f_pc_warranty_brackets'] ) && is_array( $_POST['w2f_pc_warranty_brackets'] ) ) {
		$brackets = array();
		foreach ( $_POST['w2f_pc_warranty_brackets'] as $bracket ) {
			if ( isset( $bracket['min'] ) && isset( $bracket['max'] ) && isset( $bracket['cost'] ) ) {
				$brackets[] = array(
					'min'  => floatval( $bracket['min'] ),
					'max'  => floatval( $bracket['max'] ),
					'cost' => floatval( $bracket['cost'] ),
				);
			}
		}
		$warranty_manager->save_price_brackets( $brackets );
		$price_brackets = $warranty_manager->get_price_brackets();
	}
	
	// Save warranty products.
	if ( isset( $_POST['w2f_pc_warranty_products'] ) ) {
		$product_ids = array();
		if ( is_array( $_POST['w2f_pc_warranty_products'] ) ) {
			$product_ids = array_map( 'intval', $_POST['w2f_pc_warranty_products'] );
		} else {
			$product_ids = array_filter( array_map( 'intval', explode( ',', $_POST['w2f_pc_warranty_products'] ) ) );
		}
		$warranty_manager->save_warranty_products( $product_ids );
		$warranty_products = $warranty_manager->get_warranty_products();
	}
	
	// Save default warranty.
	if ( isset( $_POST['w2f_pc_default_warranty'] ) ) {
		$default_warranty_id = intval( $_POST['w2f_pc_default_warranty'] );
		$warranty_manager->save_default_warranty( $default_warranty_id );
		$default_warranty = $warranty_manager->get_default_warranty();
	}
	
	// Save warranty description.
	if ( isset( $_POST['w2f_pc_warranty_description'] ) ) {
		$warranty_manager->save_warranty_description( $_POST['w2f_pc_warranty_description'] );
		$warranty_description = $warranty_manager->get_warranty_description();
	}
	
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Warranty settings saved.', 'w2f-pc-configurator' ) . '</p></div>';
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Warranty Settings', 'w2f-pc-configurator' ); ?></h1>
	
	<div class="w2f-pc-warranty-settings" style="margin-top: 20px;">
	
	<form method="post" action="">
		<?php wp_nonce_field( 'w2f_pc_warranty_settings', 'w2f_pc_warranty_settings_nonce' ); ?>
		
		<!-- Price Brackets Section -->
		<div class="w2f-pc-settings-section" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Base Warranty Price Brackets', 'w2f-pc-configurator' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Configure price brackets for base warranty cost. The base warranty cost is calculated based on the total configured PC price (sum of all components). All prices are excluding VAT.', 'w2f-pc-configurator' ); ?>
			</p>
			
			<div id="w2f-pc-warranty-brackets">
				<?php if ( ! empty( $price_brackets ) ) : ?>
					<?php foreach ( $price_brackets as $index => $bracket ) : ?>
						<div class="w2f-pc-bracket-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
							<label style="min-width: 80px;">
								<?php esc_html_e( 'Min Price:', 'w2f-pc-configurator' ); ?>
								<input type="number" name="w2f_pc_warranty_brackets[<?php echo esc_attr( $index ); ?>][min]" value="<?php echo esc_attr( $bracket['min'] ); ?>" step="0.01" min="0" class="small-text" required />
							</label>
							<label style="min-width: 80px;">
								<?php esc_html_e( 'Max Price:', 'w2f-pc-configurator' ); ?>
								<input type="number" name="w2f_pc_warranty_brackets[<?php echo esc_attr( $index ); ?>][max]" value="<?php echo esc_attr( $bracket['max'] ); ?>" step="0.01" min="0" class="small-text" required />
							</label>
							<label style="min-width: 120px;">
								<?php esc_html_e( 'Cost (ex VAT):', 'w2f-pc-configurator' ); ?>
								<input type="number" name="w2f_pc_warranty_brackets[<?php echo esc_attr( $index ); ?>][cost]" value="<?php echo esc_attr( $bracket['cost'] ); ?>" step="0.01" min="0" class="small-text" required />
							</label>
							<button type="button" class="button w2f-pc-remove-bracket" style="margin-top: 20px;"><?php esc_html_e( 'Remove', 'w2f-pc-configurator' ); ?></button>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			
			<button type="button" class="button w2f-pc-add-bracket" style="margin-top: 10px;"><?php esc_html_e( 'Add Bracket', 'w2f-pc-configurator' ); ?></button>
		</div>
		
		<!-- Warranty Products Section -->
		<div class="w2f-pc-settings-section" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Warranty Products', 'w2f-pc-configurator' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Select warranty products that will appear in the Services tab on all PC configurator products. These will be displayed as thumbnails and customers must select one.', 'w2f-pc-configurator' ); ?>
			</p>
			
			<select id="w2f_pc_warranty_products" name="w2f_pc_warranty_products[]" class="wc-product-search" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Search for warranty products...', 'w2f-pc-configurator' ); ?>">
				<?php foreach ( $warranty_products as $product_id ) : ?>
					<?php
					$product = wc_get_product( $product_id );
					if ( $product ) :
						?>
						<option value="<?php echo esc_attr( $product_id ); ?>" selected="selected"><?php echo esc_html( $product->get_formatted_name() ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</div>
		
		<!-- Warranty Description Section -->
		<div class="w2f-pc-settings-section" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Warranty Description', 'w2f-pc-configurator' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Enter a description that will be displayed above the warranty options in the Services tab on all PC configurator products.', 'w2f-pc-configurator' ); ?>
			</p>
			
			<textarea id="w2f_pc_warranty_description" name="w2f_pc_warranty_description" rows="4" style="width: 100%;" class="large-text"><?php echo esc_textarea( $warranty_description ); ?></textarea>
		</div>
		
		<!-- Default Warranty Section -->
		<div class="w2f-pc-settings-section" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Default Warranty', 'w2f-pc-configurator' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Select the default warranty option that will be pre-selected for all PC configurator products. This warranty will be automatically selected when customers open the configurator.', 'w2f-pc-configurator' ); ?>
			</p>
			
			<select id="w2f_pc_default_warranty" name="w2f_pc_default_warranty" class="wc-product-search" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Select default warranty...', 'w2f-pc-configurator' ); ?>">
				<option value="0"><?php esc_html_e( 'None (no default)', 'w2f-pc-configurator' ); ?></option>
				<?php foreach ( $warranty_products as $product_id ) : ?>
					<?php
					$product = wc_get_product( $product_id );
					if ( $product ) :
						?>
						<option value="<?php echo esc_attr( $product_id ); ?>" <?php selected( $default_warranty, $product_id ); ?>><?php echo esc_html( $product->get_formatted_name() ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</div>
		
		<p class="submit">
			<input type="submit" name="w2f_pc_warranty_settings_save" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'w2f-pc-configurator' ); ?>" />
		</p>
	</form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	var bracketIndex = <?php echo count( $price_brackets ); ?>;
	
	// Add bracket row.
	$('.w2f-pc-add-bracket').on('click', function() {
		var row = $('<div class="w2f-pc-bracket-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">' +
			'<label style="min-width: 80px;"><?php esc_html_e( 'Min Price:', 'w2f-pc-configurator' ); ?> ' +
			'<input type="number" name="w2f_pc_warranty_brackets[' + bracketIndex + '][min]" value="0" step="0.01" min="0" class="small-text" required /></label>' +
			'<label style="min-width: 80px;"><?php esc_html_e( 'Max Price:', 'w2f-pc-configurator' ); ?> ' +
			'<input type="number" name="w2f_pc_warranty_brackets[' + bracketIndex + '][max]" value="1000" step="0.01" min="0" class="small-text" required /></label>' +
			'<label style="min-width: 120px;"><?php esc_html_e( 'Cost (ex VAT):', 'w2f-pc-configurator' ); ?> ' +
			'<input type="number" name="w2f_pc_warranty_brackets[' + bracketIndex + '][cost]" value="10.00" step="0.01" min="0" class="small-text" required /></label>' +
			'<button type="button" class="button w2f-pc-remove-bracket" style="margin-top: 20px;"><?php esc_html_e( 'Remove', 'w2f-pc-configurator' ); ?></button>' +
			'</div>');
		$('#w2f-pc-warranty-brackets').append(row);
		bracketIndex++;
	});
	
	// Remove bracket row.
	$(document).on('click', '.w2f-pc-remove-bracket', function() {
		$(this).closest('.w2f-pc-bracket-row').remove();
	});
	
	// Initialize product search for warranty products.
	if ($.fn.selectWoo) {
		$('#w2f_pc_warranty_products').selectWoo({
			ajax: {
				url: ajaxurl,
				dataType: 'json',
				delay: 250,
				data: function(params) {
					return {
						action: 'woocommerce_json_search_products',
						term: params.term,
						security: '<?php echo esc_js( wp_create_nonce( 'search-products' ) ); ?>'
					};
				},
				processResults: function(data) {
					var terms = [];
					if (data) {
						$.each(data, function(id, text) {
							terms.push({id: id, text: text});
						});
					}
					return {results: terms};
				},
				cache: true
			},
			minimumInputLength: 2,
			multiple: true
		});
		
		// Initialize product search for default warranty.
		$('#w2f_pc_default_warranty').selectWoo({
			ajax: {
				url: ajaxurl,
				dataType: 'json',
				delay: 250,
				data: function(params) {
					return {
						action: 'woocommerce_json_search_products',
						term: params.term,
						security: '<?php echo esc_js( wp_create_nonce( 'search-products' ) ); ?>'
					};
				},
				processResults: function(data) {
					var terms = [];
					if (data) {
						$.each(data, function(id, text) {
							terms.push({id: id, text: text});
						});
					}
					return {results: terms};
				},
				cache: true
			},
			minimumInputLength: 2,
			multiple: false
		});
	}
});
</script>
	</div>
</div>

