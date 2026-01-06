<?php
/**
 * Bulk update page template
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form submission.
$preview_data = null;
$update_results = null;

if ( isset( $_POST['w2f_pc_bulk_update_preview'] ) && check_admin_referer( 'w2f-pc-bulk-update-preview' ) ) {
	$old_product_id = isset( $_POST['old_product_id'] ) ? intval( $_POST['old_product_id'] ) : 0;
	if ( $old_product_id > 0 ) {
		$preview_data = W2F_PC_Bulk_Updater::find_product_usage( $old_product_id );
		$preview_data['old_product_id'] = $old_product_id;
		$old_product = wc_get_product( $old_product_id );
		$preview_data['old_product_name'] = $old_product ? $old_product->get_name() : '';
	}
}

if ( isset( $_POST['w2f_pc_bulk_update_execute'] ) && check_admin_referer( 'w2f-pc-bulk-update-execute' ) ) {
	$old_product_id = isset( $_POST['old_product_id'] ) ? intval( $_POST['old_product_id'] ) : 0;
	$new_product_id = isset( $_POST['new_product_id'] ) ? intval( $_POST['new_product_id'] ) : 0;
	$update_options = isset( $_POST['update_options'] ) && 'yes' === $_POST['update_options'];

	if ( $old_product_id > 0 && $new_product_id > 0 ) {
		$update_results = W2F_PC_Bulk_Updater::bulk_update( $old_product_id, $new_product_id, $update_options );
		$update_results['old_product_id'] = $old_product_id;
		$update_results['new_product_id'] = $new_product_id;
		$old_product = wc_get_product( $old_product_id );
		$new_product = wc_get_product( $new_product_id );
		$update_results['old_product_name'] = $old_product ? $old_product->get_name() : '';
		$update_results['new_product_name'] = $new_product ? $new_product->get_name() : '';
	}
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Bulk Update Products', 'w2f-pc-configurator' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Replace a product across all configurator products. This is useful when a product becomes unavailable and needs to be replaced with an alternative.', 'w2f-pc-configurator' ); ?></p>

	<?php if ( $update_results ) : ?>
		<?php if ( $update_results['total_errors'] > 0 ) : ?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( 'Update completed with errors:', 'w2f-pc-configurator' ); ?></strong></p>
				<ul>
					<?php foreach ( array_merge( $update_results['defaults']['errors'], $update_results['options']['errors'] ) as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $update_results['total_updated'] > 0 ) : ?>
			<div class="notice notice-success">
				<p><strong><?php esc_html_e( 'Update completed successfully!', 'w2f-pc-configurator' ); ?></strong></p>
				<p>
					<?php
					printf(
						esc_html__( 'Replaced %1$s with %2$s in %3$d configurator product(s).', 'w2f-pc-configurator' ),
						'<strong>' . esc_html( $update_results['old_product_name'] ) . '</strong>',
						'<strong>' . esc_html( $update_results['new_product_name'] ) . '</strong>',
						$update_results['total_updated']
					);
					?>
				</p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<form method="post" action="" class="w2f-pc-bulk-update-form">
		<?php wp_nonce_field( 'w2f-pc-bulk-update-preview' ); ?>
		<input type="hidden" name="w2f_pc_bulk_update_preview" value="1" />

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="old_product_id"><?php esc_html_e( 'Replace Product', 'w2f-pc-configurator' ); ?></label>
				</th>
				<td>
					<select id="old_product_id" name="old_product_id" class="wc-product-search" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Search for a product...', 'w2f-pc-configurator' ); ?>" required>
						<?php if ( $preview_data && isset( $preview_data['old_product_id'] ) ) : ?>
							<option value="<?php echo esc_attr( $preview_data['old_product_id'] ); ?>" selected><?php echo esc_html( $preview_data['old_product_name'] ); ?></option>
						<?php endif; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Select the product you want to replace.', 'w2f-pc-configurator' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Preview Usage', 'w2f-pc-configurator' ); ?></button>
		</p>
	</form>

	<?php if ( $preview_data ) : ?>
		<div class="w2f-pc-bulk-update-preview">
			<h2><?php esc_html_e( 'Preview: Where This Product Is Used', 'w2f-pc-configurator' ); ?></h2>
			
			<?php if ( empty( $preview_data['in_component_options'] ) ) : ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'This product is not used in any configurator products.', 'w2f-pc-configurator' ); ?></p>
				</div>
			<?php else : ?>
				<?php if ( ! empty( $preview_data['in_component_options'] ) ) : ?>
					<h3><?php esc_html_e( 'Used in Component Options', 'w2f-pc-configurator' ); ?></h3>
					<ul>
						<?php foreach ( $preview_data['in_component_options'] as $usage ) : ?>
							<li>
								<strong><?php echo esc_html( $usage['configurator_name'] ); ?></strong>
								<?php esc_html_e( ' - Components:', 'w2f-pc-configurator' ); ?>
								<?php
								$component_titles = array_map( function( $comp ) {
									return $comp['component_title'];
								}, $usage['components'] );
								echo esc_html( implode( ', ', $component_titles ) );
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<form method="post" action="" class="w2f-pc-bulk-update-execute">
					<?php wp_nonce_field( 'w2f-pc-bulk-update-execute' ); ?>
					<input type="hidden" name="w2f_pc_bulk_update_execute" value="1" />
					<input type="hidden" name="old_product_id" value="<?php echo esc_attr( $preview_data['old_product_id'] ); ?>" />

					<h3><?php esc_html_e( 'Replace With', 'w2f-pc-configurator' ); ?></h3>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="new_product_id"><?php esc_html_e( 'New Product', 'w2f-pc-configurator' ); ?></label>
							</th>
							<td>
								<select id="new_product_id" name="new_product_id" class="wc-product-search" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Search for a product...', 'w2f-pc-configurator' ); ?>" required>
								</select>
								<p class="description"><?php esc_html_e( 'Select the product to replace with.', 'w2f-pc-configurator' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Update Options', 'w2f-pc-configurator' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="update_options" value="yes" <?php checked( ! empty( $preview_data['in_component_options'] ) ); ?> <?php disabled( empty( $preview_data['in_component_options'] ) ); ?> />
									<?php esc_html_e( 'Update Component Options', 'w2f-pc-configurator' ); ?>
									<?php if ( ! empty( $preview_data['in_component_options'] ) ) : ?>
										<span class="description">(<?php echo esc_html( count( $preview_data['in_component_options'] ) ); ?> <?php esc_html_e( 'configurator(s) affected', 'w2f-pc-configurator' ); ?>)</span>
									<?php endif; ?>
								</label>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to perform this bulk update? This action cannot be undone.', 'w2f-pc-configurator' ); ?>');">
							<?php esc_html_e( 'Execute Update', 'w2f-pc-configurator' ); ?>
						</button>
					</p>
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	if (typeof $.fn.selectWoo !== 'undefined') {
		$('.wc-product-search').selectWoo({
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
			minimumInputLength: 2
		});
	}
});
</script>

