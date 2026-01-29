<?php
/**
 * Compatibility log viewer page template
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle clear logs action.
if ( isset( $_POST['w2f_pc_clear_logs'] ) && check_admin_referer( 'w2f-pc-clear-logs' ) ) {
	W2F_PC_Compatibility_Logger::clear_logs();
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Logs cleared successfully.', 'w2f-pc-configurator' ) . '</p></div>';
}

// Get filters.
$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
$configurator_id = isset( $_GET['configurator_id'] ) ? intval( $_GET['configurator_id'] ) : 0;
$rule_id = isset( $_GET['rule_id'] ) ? sanitize_text_field( $_GET['rule_id'] ) : '';
$result_filter = isset( $_GET['result'] ) ? sanitize_text_field( $_GET['result'] ) : '';

$filters = array(
	'date_from' => $date_from,
	'date_to' => $date_to,
	'configurator_id' => $configurator_id,
	'rule_id' => $rule_id,
	'result' => $result_filter,
);

$logs = W2F_PC_Compatibility_Logger::get_filtered_logs( $filters );

// Get configurator products for filter.
$configurator_products = wc_get_products( array(
	'type' => 'pc_configurator',
	'limit' => -1,
	'status' => 'publish',
) );

// Get rules for filter.
$compatibility_manager = W2F_PC_Compatibility_Manager::instance();
$rules = $compatibility_manager->get_rules();

// Pagination.
$per_page = 50;
$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$total_logs = count( $logs );
$total_pages = ceil( $total_logs / $per_page );
$offset = ( $current_page - 1 ) * $per_page;
$paginated_logs = array_slice( $logs, $offset, $per_page );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Compatibility Log', 'w2f-pc-configurator' ); ?></h1>
	<p class="description"><?php esc_html_e( 'View compatibility check logs and see how rules affect products.', 'w2f-pc-configurator' ); ?></p>

	<div class="w2f-pc-log-filters">
		<form method="get" action="" class="w2f-pc-filter-form">
			<input type="hidden" name="page" value="w2f-pc-compatibility-log" />
			
			<div class="w2f-pc-filter-row">
				<div class="w2f-pc-filter-field">
					<label for="date_from"><?php esc_html_e( 'Date From', 'w2f-pc-configurator' ); ?></label>
					<input type="date" id="date_from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
				</div>
				<div class="w2f-pc-filter-field">
					<label for="date_to"><?php esc_html_e( 'Date To', 'w2f-pc-configurator' ); ?></label>
					<input type="date" id="date_to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
				</div>
				<div class="w2f-pc-filter-field">
					<label for="configurator_id"><?php esc_html_e( 'Configurator', 'w2f-pc-configurator' ); ?></label>
					<select id="configurator_id" name="configurator_id">
						<option value=""><?php esc_html_e( 'All Configurators', 'w2f-pc-configurator' ); ?></option>
						<?php foreach ( $configurator_products as $product ) : ?>
							<option value="<?php echo esc_attr( $product->get_id() ); ?>" <?php selected( $configurator_id, $product->get_id() ); ?>>
								<?php echo esc_html( $product->get_name() ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="w2f-pc-filter-field">
					<label for="rule_id"><?php esc_html_e( 'Rule', 'w2f-pc-configurator' ); ?></label>
					<select id="rule_id" name="rule_id">
						<option value=""><?php esc_html_e( 'All Rules', 'w2f-pc-configurator' ); ?></option>
						<?php foreach ( $rules as $id => $rule_data ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $rule_id, $id ); ?>>
								<?php echo esc_html( isset( $rule_data['name'] ) ? $rule_data['name'] : $id ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="w2f-pc-filter-field">
					<label for="result"><?php esc_html_e( 'Result', 'w2f-pc-configurator' ); ?></label>
					<select id="result" name="result">
						<option value=""><?php esc_html_e( 'All Results', 'w2f-pc-configurator' ); ?></option>
						<option value="compatible" <?php selected( $result_filter, 'compatible' ); ?>><?php esc_html_e( 'Compatible', 'w2f-pc-configurator' ); ?></option>
						<option value="incompatible" <?php selected( $result_filter, 'incompatible' ); ?>><?php esc_html_e( 'Incompatible', 'w2f-pc-configurator' ); ?></option>
						<option value="warning" <?php selected( $result_filter, 'warning' ); ?>><?php esc_html_e( 'Warning', 'w2f-pc-configurator' ); ?></option>
					</select>
				</div>
				<div class="w2f-pc-filter-field">
					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'w2f-pc-configurator' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=w2f-pc-compatibility-log' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'w2f-pc-configurator' ); ?></a>
				</div>
			</div>
		</form>
	</div>

	<div class="w2f-pc-log-actions">
		<form method="post" action="" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to clear all logs? This action cannot be undone.', 'w2f-pc-configurator' ); ?>');">
			<?php wp_nonce_field( 'w2f-pc-clear-logs' ); ?>
			<input type="hidden" name="w2f_pc_clear_logs" value="1" />
			<button type="submit" class="button"><?php esc_html_e( 'Clear All Logs', 'w2f-pc-configurator' ); ?></button>
		</form>
		<span class="w2f-pc-log-count">
			<?php
			printf(
				esc_html__( 'Showing %1$d of %2$d log entries', 'w2f-pc-configurator' ),
				count( $paginated_logs ),
				$total_logs
			);
			?>
		</span>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date/Time', 'w2f-pc-configurator' ); ?></th>
				<th><?php esc_html_e( 'Configurator', 'w2f-pc-configurator' ); ?></th>
				<th><?php esc_html_e( 'Rule', 'w2f-pc-configurator' ); ?></th>
				<th><?php esc_html_e( 'Component', 'w2f-pc-configurator' ); ?></th>
				<th><?php esc_html_e( 'Affected Products', 'w2f-pc-configurator' ); ?></th>
				<th><?php esc_html_e( 'Result', 'w2f-pc-configurator' ); ?></th>
				<th><?php esc_html_e( 'Message', 'w2f-pc-configurator' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $paginated_logs ) ) : ?>
				<tr>
					<td colspan="7"><?php esc_html_e( 'No log entries found.', 'w2f-pc-configurator' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $paginated_logs as $log ) : ?>
					<?php
					$configurator_product = $log['configurator_product_id'] ? wc_get_product( $log['configurator_product_id'] ) : null;
					$configurator_name = $configurator_product ? $configurator_product->get_name() : __( 'Unknown', 'w2f-pc-configurator' );
					$result_class = 'compatible' === $log['result'] ? 'w2f-pc-log-compatible' : ( 'incompatible' === $log['result'] ? 'w2f-pc-log-incompatible' : 'w2f-pc-log-warning' );
					?>
					<tr>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log['timestamp'] ) ) ); ?></td>
						<td>
							<?php if ( $configurator_product ) : ?>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $log['configurator_product_id'] . '&action=edit' ) ); ?>">
									<?php echo esc_html( $configurator_name ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $configurator_name ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $log['rule_name'] ? $log['rule_name'] : $log['rule_id'] ); ?></td>
						<td><?php echo esc_html( $log['component_id'] ? $log['component_id'] : '—' ); ?></td>
						<td>
							<?php if ( ! empty( $log['affected_products'] ) ) : ?>
								<?php
								$product_names = array();
								foreach ( array_slice( $log['affected_products'], 0, 5 ) as $product_id ) {
									$product = wc_get_product( $product_id );
									if ( $product ) {
										$product_names[] = $product->get_name();
									}
								}
								echo esc_html( implode( ', ', $product_names ) );
								if ( count( $log['affected_products'] ) > 5 ) {
									printf( ' <span class="description">+%d more</span>', count( $log['affected_products'] ) - 5 );
								}
								?>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td>
							<span class="w2f-pc-log-badge <?php echo esc_attr( $result_class ); ?>">
								<?php echo esc_html( ucfirst( $log['result'] ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $log['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php
				$page_links = paginate_links( array(
					'base' => add_query_arg( 'paged', '%#%' ),
					'format' => '',
					'prev_text' => __( '&laquo;' ),
					'next_text' => __( '&raquo;' ),
					'total' => $total_pages,
					'current' => $current_page,
				) );
				echo $page_links;
				?>
			</div>
		</div>
	<?php endif; ?>
</div>

<style>
.w2f-pc-log-filters {
	background: #fff;
	padding: 20px;
	margin: 20px 0;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.w2f-pc-filter-row {
	display: flex;
	gap: 15px;
	align-items: flex-end;
	flex-wrap: wrap;
}
.w2f-pc-filter-field {
	flex: 1;
	min-width: 150px;
}
.w2f-pc-filter-field label {
	display: block;
	margin-bottom: 5px;
	font-weight: 600;
}
.w2f-pc-filter-field input,
.w2f-pc-filter-field select {
	width: 100%;
}
.w2f-pc-log-actions {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin: 20px 0;
}
.w2f-pc-log-count {
	color: #666;
}
.w2f-pc-log-badge {
	padding: 4px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
}
.w2f-pc-log-compatible {
	background: #d4edda;
	color: #155724;
}
.w2f-pc-log-incompatible {
	background: #f8d7da;
	color: #721c24;
}
.w2f-pc-log-warning {
	background: #fff3cd;
	color: #856404;
}
</style>

