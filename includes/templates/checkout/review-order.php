<?php
/**
 * Review order table with Points Summary
 * Enhanced checkout version
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="shop_table woocommerce-checkout-review-order-table">
	<thead>
		<tr>
			<th class="product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
			<th class="product-total"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				?>
				<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
					<td class="product-name">
						<?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ) . '&nbsp;'; ?>
						<?php echo apply_filters( 'woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', $cart_item['quantity'] ) . '</strong>', $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						
						<!-- Individual Product Points Display -->
						<?php if (function_exists('herbal_get_product_points_display')): ?>
							<?php echo herbal_get_product_points_display($_product, $cart_item['quantity']); ?>
						<?php endif; ?>
					</td>
					<td class="product-total">
						<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</td>
				</tr>
				<?php
			}
		}

		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</tbody>
	<tfoot>

		<tr class="cart-subtotal">
			<th><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
			<td><?php wc_cart_totals_subtotal_html(); ?></td>
		</tr>

		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
				<th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
				<td><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>

			<?php do_action( 'woocommerce_review_order_before_shipping' ); ?>

			<?php wc_cart_totals_shipping_html(); ?>

			<?php do_action( 'woocommerce_review_order_after_shipping' ); ?>

		<?php endif; ?>

		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<tr class="fee">
				<th><?php echo esc_html( $fee->name ); ?></th>
				<td><?php wc_cart_totals_fee_html( $fee ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
			<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
				<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
					<tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
						<th><?php echo esc_html( $tax->label ); ?></th>
						<td><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr class="tax-total">
					<th><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th>
					<td><?php wc_cart_totals_taxes_total_html(); ?></td>
				</tr>
			<?php endif; ?>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

		<!-- ============== ENHANCED POINTS SUMMARY ============== -->
		<?php if (is_user_logged_in() && function_exists('herbal_get_cart_points_summary')): ?>
			<?php 
			$points_summary = herbal_get_cart_points_summary();
			if ($points_summary['total_cost'] > 0): 
			?>
			<tr class="herbal-points-summary-row">
				<td colspan="2">
					<div class="herbal-points-summary checkout-style">
						<div class="points-header">
							<h4>💳 <?php esc_html_e('Points Payment Available', 'herbal-mix-creator2'); ?></h4>
						</div>
						
						<div class="points-breakdown">
							<div class="points-row">
								<span class="label"><?php esc_html_e('Total Points Required:', 'herbal-mix-creator2'); ?></span>
								<span class="value cost"><?php echo number_format($points_summary['total_cost'], 0); ?> pts</span>
							</div>
							
							<div class="points-row">
								<span class="label"><?php esc_html_e('Your Points Balance:', 'herbal-mix-creator2'); ?></span>
								<span class="value balance"><?php echo number_format($points_summary['user_points'], 0); ?> pts</span>
							</div>
							
							<?php if ($points_summary['total_earned'] > 0): ?>
							<div class="points-row">
								<span class="label"><?php esc_html_e('Points You\'ll Earn:', 'herbal-mix-creator2'); ?></span>
								<span class="value earned">+<?php echo number_format($points_summary['total_earned'], 0); ?> pts</span>
							</div>
							<?php endif; ?>
						</div>
						
						<div class="points-status">
							<?php if ($points_summary['can_pay']): ?>
								<div class="status-success">
									<span class="icon">✅</span>
									<span class="text"><?php esc_html_e('Points Payment Available Below!', 'herbal-mix-creator2'); ?></span>
									<div class="details">
										<?php $remaining = $points_summary['user_points'] - $points_summary['total_cost']; ?>
										<?php echo sprintf(esc_html__('You\'ll have %s points remaining', 'herbal-mix-creator2'), number_format($remaining, 0)); ?>
									</div>
								</div>
							<?php else: ?>
								<div class="status-insufficient">
									<span class="icon">❌</span>
									<span class="text"><?php esc_html_e('Insufficient Points for Full Payment', 'herbal-mix-creator2'); ?></span>
									<div class="details">
										<?php echo sprintf(esc_html__('You need %s more points', 'herbal-mix-creator2'), number_format($points_summary['shortage'], 0)); ?>
									</div>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</td>
			</tr>
			<?php endif; ?>
		<?php endif; ?>
		<!-- ============== END POINTS SUMMARY ============== -->

		<tr class="order-total">
			<th><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
			<td><?php wc_cart_totals_order_total_html(); ?></td>
		</tr>

		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>

	</tfoot>
</table>

<style>
/* Checkout specific styling */
.herbal-points-summary.checkout-style {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border: 2px solid #ffc107;
}

.herbal-points-summary.checkout-style .points-header h4 {
    color: #856404;
    border-bottom-color: #ffc107;
}

.herbal-points-summary .points-status .status-success {
    background: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}
</style>