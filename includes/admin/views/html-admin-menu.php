<?php
/**
 * PC Configurator main settings page
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get statistics.
$configurator_products = wc_get_products( array(
	'type' => 'pc_configurator',
	'limit' => -1,
	'status' => 'publish',
) );

$compatibility_manager = W2F_PC_Compatibility_Manager::instance();
$rules = $compatibility_manager->get_rules();
$active_rules = array_filter( $rules, function( $rule ) {
	return isset( $rule['is_active'] ) && 'yes' === $rule['is_active'];
} );

$total_components = 0;
foreach ( $configurator_products as $product ) {
	if ( is_a( $product, 'W2F_PC_Product' ) ) {
		$components = $product->get_components();
		$total_components += count( $components );
	}
}
?>
<div class="wrap w2f-pc-admin-page">
	<h1><?php esc_html_e( 'PC Configurator Settings', 'w2f-pc-configurator' ); ?></h1>

	<div class="w2f-pc-admin-dashboard">
		<div class="w2f-pc-stats-grid">
			<div class="w2f-pc-stat-card">
				<div class="w2f-pc-stat-icon">
					<span class="dashicons dashicons-desktop"></span>
				</div>
				<div class="w2f-pc-stat-content">
					<h3><?php echo esc_html( count( $configurator_products ) ); ?></h3>
					<p><?php esc_html_e( 'Configurator Products', 'w2f-pc-configurator' ); ?></p>
				</div>
			</div>

			<div class="w2f-pc-stat-card">
				<div class="w2f-pc-stat-icon">
					<span class="dashicons dashicons-admin-generic"></span>
				</div>
				<div class="w2f-pc-stat-content">
					<h3><?php echo esc_html( $total_components ); ?></h3>
					<p><?php esc_html_e( 'Total Components', 'w2f-pc-configurator' ); ?></p>
				</div>
			</div>

			<div class="w2f-pc-stat-card">
				<div class="w2f-pc-stat-icon">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<div class="w2f-pc-stat-content">
					<h3><?php echo esc_html( count( $active_rules ) ); ?></h3>
					<p><?php esc_html_e( 'Active Rules', 'w2f-pc-configurator' ); ?></p>
				</div>
			</div>

			<div class="w2f-pc-stat-card">
				<div class="w2f-pc-stat-icon">
					<span class="dashicons dashicons-admin-settings"></span>
				</div>
				<div class="w2f-pc-stat-content">
					<h3><?php echo esc_html( count( $rules ) ); ?></h3>
					<p><?php esc_html_e( 'Total Rules', 'w2f-pc-configurator' ); ?></p>
				</div>
			</div>
		</div>

		<div class="w2f-pc-quick-links">
			<h2><?php esc_html_e( 'Quick Links', 'w2f-pc-configurator' ); ?></h2>
			<div class="w2f-pc-links-grid">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=w2f-pc-compatibility' ) ); ?>" class="w2f-pc-link-card">
					<span class="dashicons dashicons-yes-alt"></span>
					<h3><?php esc_html_e( 'Compatibility Rules', 'w2f-pc-configurator' ); ?></h3>
					<p><?php esc_html_e( 'Manage compatibility rules between components', 'w2f-pc-configurator' ); ?></p>
				</a>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=w2f-pc-bulk-update' ) ); ?>" class="w2f-pc-link-card">
					<span class="dashicons dashicons-update"></span>
					<h3><?php esc_html_e( 'Bulk Update', 'w2f-pc-configurator' ); ?></h3>
					<p><?php esc_html_e( 'Replace products across all configurators', 'w2f-pc-configurator' ); ?></p>
				</a>

				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product&product_type=pc_configurator' ) ); ?>" class="w2f-pc-link-card">
					<span class="dashicons dashicons-plus-alt"></span>
					<h3><?php esc_html_e( 'New Configurator', 'w2f-pc-configurator' ); ?></h3>
					<p><?php esc_html_e( 'Create a new PC configurator product', 'w2f-pc-configurator' ); ?></p>
				</a>
			</div>
		</div>
	</div>
</div>

