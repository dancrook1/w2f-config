<?php
/**
 * Product description modal template
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

<div class="w2f-pc-description-modal-overlay">
	<div class="w2f-pc-description-modal-content">
		<div class="w2f-pc-description-modal-header">
			<h2><?php echo esc_html( $product->get_name() ); ?></h2>
			<button type="button" class="w2f-pc-description-modal-close" aria-label="<?php esc_attr_e( 'Close', 'w2f-pc-configurator' ); ?>">&times;</button>
		</div>
		<div class="w2f-pc-description-modal-body">
			<div class="w2f-pc-quick-view-image">
				<?php
				$image_id = $product->get_image_id();
				if ( $image_id ) {
					$image_url = wp_get_attachment_image_url( $image_id, 'large' );
					$image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
					if ( empty( $image_alt ) ) {
						$image_alt = $product->get_name();
					}
					?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" />
				<?php } else {
					echo wc_placeholder_img( 'large' );
				} ?>
			</div>
			<div class="w2f-pc-description-content">
				<?php
				$short_description = $product->get_short_description();
				if ( $short_description ) {
					echo wp_kses_post( wpautop( $short_description ) );
				} else {
					echo '<p>' . esc_html__( 'No description available.', 'w2f-pc-configurator' ) . '</p>';
				}
				?>
			</div>
		</div>
	</div>
</div>

