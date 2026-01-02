<?php
/**
 * Product tooltip/quick view template
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $product ) ) {
	return;
}
?>

<div class="w2f-pc-product-tooltip" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
	<div class="w2f-pc-tooltip-content">
		<div class="w2f-pc-tooltip-description">
			<?php
			$description = $product->get_description();
			if ( empty( $description ) ) {
				$description = $product->get_short_description();
			}
			if ( $description ) {
				echo wp_kses_post( wpautop( $description ) );
			} else {
				echo '<p>' . esc_html__( 'No description available.', 'w2f-pc-configurator' ) . '</p>';
			}
			?>
		</div>
	</div>
</div>

