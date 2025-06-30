?>
<?php
/**
 * Cart totals with Points Summary
 * Enhanced version with comprehensive points display
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="cart_totals <?php echo ( WC()->customer->has_calculated_shipping() ) ? 'calculated_shipping' : ''; ?>">

	<?php do_action( 'woocommerce_before_cart_totals' ); ?>

	<h2><?php esc_html_e( 'Cart totals', 'woocommerce' ); ?></h2>

	<table cellspacing="0" class="shop_table shop_table_responsive">

		<tr class="cart-subtotal">
			<th><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
			<td data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>"><?php wc_cart_totals_subtotal_html(); ?></td>
		</tr>

		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
				<th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
				<td data-title="<?php echo esc_attr( wc_cart_totals_coupon_label( $coupon, false ) ); ?>"><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>

			<?php do_action( 'woocommerce_cart_totals_before_shipping' ); ?>

			<?php wc_cart_totals_shipping_html(); ?>

			<?php do_action( 'woocommerce_cart_totals_after_shipping' ); ?>

		<?php elseif ( WC()->cart->needs_shipping() && 'yes' === get_option( 'woocommerce_enable_shipping_calc' ) ) : ?>

			<tr class="shipping">
				<th><?php esc_html_e( 'Shipping', 'woocommerce' ); ?></th>
				<td data-title="<?php esc_attr_e( 'Shipping', 'woocommerce' ); ?>"><?php woocommerce_shipping_calculator(); ?></td>
			</tr>

		<?php endif; ?>

		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<tr class="fee">
				<th><?php echo esc_html( $fee->name ); ?></th>
				<td data-title="<?php echo esc_attr( $fee->name ); ?>"><?php wc_cart_totals_fee_html( $fee ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
			<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
				<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
					<tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
						<th><?php echo esc_html( $tax->label ); ?></th>
						<td data-title="<?php echo esc_attr( $tax->label ); ?>"><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr class="tax-total">
					<th><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th>
					<td data-title="<?php echo esc_attr( WC()->countries->tax_or_vat() ); ?>"><?php wc_cart_totals_taxes_total_html(); ?></td>
				</tr>
			<?php endif; ?>
		<?php endif; ?>

		<?php do_action( 'woocommerce_cart_totals_before_order_total' ); ?>

		<!-- ============== ENHANCED POINTS SUMMARY ============== -->
		<?php if (is_user_logged_in() && function_exists('herbal_get_cart_points_summary')): ?>
			<?php 
			$points_summary = herbal_get_cart_points_summary();
			if ($points_summary['total_cost'] > 0): 
			?>
			<tr class="herbal-points-summary-row">
				<td colspan="2">
					<div class="herbal-points-summary">
						<div class="points-header">
							<h4>🛒 <?php esc_html_e('Points Summary', 'herbal-mix-creator2'); ?></h4>
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
								<?php $remaining = $points_summary['user_points'] - $points_summary['total_cost']; ?>
								<div class="status-success">
									<span class="icon">✅</span>
									<span class="text"><?php esc_html_e('You can pay with points!', 'herbal-mix-creator2'); ?></span>
									<div class="details">
										<?php echo sprintf(esc_html__('Remaining: %s pts', 'herbal-mix-creator2'), number_format($remaining, 0)); ?>
									</div>
								</div>
							<?php else: ?>
								<div class="status-insufficient">
									<span class="icon">❌</span>
									<span class="text"><?php esc_html_e('Insufficient points', 'herbal-mix-creator2'); ?></span>
									<div class="details">
										<?php echo sprintf(esc_html__('Need %s more points', 'herbal-mix-creator2'), number_format($points_summary['shortage'], 0)); ?>
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
			<td data-title="<?php esc_attr_e( 'Total', 'woocommerce' ); ?>"><?php wc_cart_totals_order_total_html(); ?></td>
		</tr>

		<?php do_action( 'woocommerce_cart_totals_after_order_total' ); ?>

	</table>

	<div class="wc-proceed-to-checkout">
		<?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
	</div>

	<?php do_action( 'woocommerce_after_cart_totals' ); ?>

</div>

<style>
/* Points Summary Styles */
.herbal-points-summary {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px solid #28a745;
    border-radius: 12px;
    padding: 20px;
    margin: 15px 0;
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.1);
}

.herbal-points-summary .points-header h4 {
    margin: 0 0 15px 0;
    color: #28a745;
    font-size: 18px;
    font-weight: 600;
    text-align: center;
    border-bottom: 2px solid #28a745;
    padding-bottom: 8px;
}

.points-breakdown .points-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #dee2e6;
}

.points-breakdown .points-row:last-child {
    border-bottom: none;
}

.points-row .label {
    font-weight: 500;
    color: #495057;
}

.points-row .value {
    font-weight: 700;
    font-size: 16px;
}

.points-row .value.cost {
    color: #dc3545;
}

.points-row .value.balance {
    color: #17a2b8;
}

.points-row .value.earned {
    color: #28a745;
}

.points-status {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 2px solid #dee2e6;
}

.status-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
}

.status-insufficient {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
}

.status-success .icon, .status-insufficient .icon {
    font-size: 18px;
    margin-right: 8px;
}

.status-success .text, .status-insufficient .text {
    font-weight: 600;
    font-size: 16px;
}

.status-success .details, .status-insufficient .details {
    font-size: 14px;
    margin-top: 5px;
    opacity: 0.9;
}

@media (max-width: 768px) {
    .herbal-points-summary {
        padding: 15px;
    }
    
    .points-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .points-row .value {
        font-size: 18px;
    }
}
</style>

