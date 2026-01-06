<?php
/**
 * PC Configurator add to cart template
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( ! w2f_pc_is_configurator_product( $product ) ) {
	return;
}

$configurator_product = w2f_pc_get_configurator_product( $product );
$components = $configurator_product->get_components();
$default_configuration = $configurator_product->get_default_configuration();
// Calculate default price from components (sum + tax).
$default_price = $configurator_product->calculate_configuration_price( $default_configuration, true );
$defined_tabs = $configurator_product->get_tabs();

// Group components by tabs using defined tabs.
$tabs = array();
$no_tab_components = array();

// Initialize tabs from defined tabs.
foreach ( $defined_tabs as $tab_name ) {
	$tabs[ $tab_name ] = array();
}

// Assign components to tabs.
foreach ( $components as $component_id => $component ) {
	$tab = $component->get_tab();
	if ( ! empty( $tab ) && isset( $tabs[ $tab ] ) ) {
		$tabs[ $tab ][ $component_id ] = $component;
	} else {
		$no_tab_components[ $component_id ] = $component;
	}
}

// Remove empty tabs.
foreach ( $tabs as $tab_name => $tab_components ) {
	if ( empty( $tab_components ) ) {
		unset( $tabs[ $tab_name ] );
	}
}

// If there are tabs, add "no tab" components to a default tab.
if ( ! empty( $tabs ) && ! empty( $no_tab_components ) ) {
	$tabs[ __( 'Other', 'w2f-pc-configurator' ) ] = $no_tab_components;
	$no_tab_components = array();
}
?>

<div class="w2f-pc-configurator-wrapper" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
	<!-- Action Buttons -->
	<div class="w2f-pc-action-buttons">
		<button type="button" class="button w2f-pc-configure-button">
			<?php esc_html_e( 'Configure', 'w2f-pc-configurator' ); ?>
		</button>
		<button type="button" class="button alt w2f-pc-add-default-to-cart" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
			<?php esc_html_e( 'Add to Cart', 'w2f-pc-configurator' ); ?>
		</button>
	</div>

	<!-- Modal Overlay -->
	<div class="w2f-pc-modal-overlay" id="w2f-pc-configurator-modal">
		<div class="w2f-pc-modal-content">
			<div class="w2f-pc-modal-header">
				<h2><?php esc_html_e( 'Configure Your PC', 'w2f-pc-configurator' ); ?></h2>
				<button type="button" class="w2f-pc-modal-close" aria-label="<?php esc_attr_e( 'Close', 'w2f-pc-configurator' ); ?>">&times;</button>
			</div>
			<div class="w2f-pc-modal-body">
				<div class="w2f-pc-modal-columns">
					<!-- Left Column: Configuration -->
					<div class="w2f-pc-configurator-column">
						<div class="w2f-pc-configurator" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
							<?php if ( ! empty( $tabs ) ) : ?>
								<!-- Tab Navigation -->
								<ul class="w2f-pc-tabs" role="tablist">
									<?php
									$first_tab = true;
									foreach ( $tabs as $tab_name => $tab_components ) :
										$tab_id = sanitize_title( $tab_name );
										?>
										<li class="w2f-pc-tab<?php echo $first_tab ? ' active' : ''; ?>">
											<a href="#w2f-pc-tab-<?php echo esc_attr( $tab_id ); ?>" role="tab" aria-selected="<?php echo $first_tab ? 'true' : 'false'; ?>">
												<?php echo esc_html( $tab_name ); ?>
											</a>
										</li>
										<?php
										$first_tab = false;
									endforeach;
									?>
								</ul>

								<!-- Tab Content -->
								<?php
								$first_tab = true;
								foreach ( $tabs as $tab_name => $tab_components ) :
									$tab_id = sanitize_title( $tab_name );
									?>
									<div class="w2f-pc-tab-content<?php echo $first_tab ? ' active' : ''; ?>" id="w2f-pc-tab-<?php echo esc_attr( $tab_id ); ?>" role="tabpanel">
										<?php foreach ( $tab_components as $component_id => $component ) : ?>
											<?php
											$default_product_id = isset( $default_configuration[ $component_id ] ) ? $default_configuration[ $component_id ] : 0;
											$display_mode = $component->get_display_mode();
											$option_products = $component->get_option_products();
											include W2F_PC()->plugin_path() . '/templates/single-product/add-to-cart/component.php';
											?>
										<?php endforeach; ?>
									</div>
									<?php
									$first_tab = false;
								endforeach;
								?>
							<?php else : ?>
								<!-- No tabs, show all components -->
								<div class="w2f-pc-components">
									<?php foreach ( $components as $component_id => $component ) : ?>
										<?php
										$default_product_id = isset( $default_configuration[ $component_id ] ) ? $default_configuration[ $component_id ] : 0;
										$display_mode = $component->get_display_mode();
										$option_products = $component->get_option_products();
										include W2F_PC()->plugin_path() . '/templates/single-product/add-to-cart/component.php';
										?>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Right Column: Summary -->
					<div class="w2f-pc-summary-column">
						<div class="w2f-pc-summary">
							<h3><?php esc_html_e( 'Configuration Summary', 'w2f-pc-configurator' ); ?></h3>

							<!-- Specifications -->
							<div class="w2f-pc-summary-section w2f-pc-summary-specs">
								<button type="button" class="w2f-pc-summary-toggle" aria-expanded="false">
									<span class="w2f-pc-summary-toggle-text"><?php esc_html_e( 'Specifications', 'w2f-pc-configurator' ); ?></span>
									<svg class="w2f-pc-summary-toggle-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</button>
								<div class="w2f-pc-summary-content">
									<dl class="w2f-pc-spec-list"></dl>
								</div>
							</div>

							<!-- Price -->
							<div class="w2f-pc-price">
								<strong><?php esc_html_e( 'Total Price:', 'w2f-pc-configurator' ); ?></strong>
								<span class="w2f-pc-total-price"><?php echo wp_kses_post( wc_price( $default_price ) ); ?></span>
							</div>

							<!-- Compatibility Messages -->
							<div class="w2f-pc-compatibility-messages"></div>

							<!-- Add to Cart Form -->
							<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

							<form class="cart w2f-pc-configurator-form" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data">
								<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

								<input type="hidden" name="quantity" value="1" />

								<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt">
									<?php esc_html_e( 'Add To Cart', 'w2f-pc-configurator' ); ?>
								</button>

								<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
							</form>

							<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
						</div>

						<div class="w2f-pc-actions">
							<button type="button" class="button w2f-pc-share"><?php esc_html_e( 'Share Configuration', 'w2f-pc-configurator' ); ?></button>
							<button type="button" class="button w2f-pc-load-config" style="display: none;"><?php esc_html_e( 'Load Saved', 'w2f-pc-configurator' ); ?></button>
							<button type="button" class="button w2f-pc-reset-config" style="display: none;"><?php esc_html_e( 'Reset to Default', 'w2f-pc-configurator' ); ?></button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

