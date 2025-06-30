<?php
/**
 * Class for awarding reward points and displaying user profile information.
 *
 * @package HerbalMixCreator2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HerbalMixRewardPoints {

    public function __construct() {
        // Hook to award points when an order is completed.
        add_action( 'woocommerce_order_status_completed', array( $this, 'award_points_on_order_complete' ), 10, 1 );

        // Register shortcode for displaying user profile with reward points.
        add_shortcode( 'herbal_mix_user_profile', array( $this, 'render_user_profile' ) );
    }

    /**
     * Award reward points when a WooCommerce order is completed.
     *
     * @param int $order_id The ID of the completed order.
     */
    public function award_points_on_order_complete( $order_id ) {
        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $user_id = $order->get_user_id();
        if ( ! $user_id ) {
            return;
        }

        $total_points = 0;
        // Sum up points for each order item.
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) {
                continue;
            }
            // Get points awarded per unit from custom product meta.
            $points_earned = get_post_meta( $product->get_id(), '_points_earned', true );
            if ( ! $points_earned ) {
                continue;
            }
            $points_earned = floatval( $points_earned );
            $quantity      = $item->get_quantity();
            $total_points += $points_earned * $quantity;
        }

        if ( $total_points > 0 ) {
            // Retrieve current reward points balance.
            $current_points = floatval( get_user_meta( $user_id, 'reward_points', true ) );
            // Update the user's reward points.
            update_user_meta( $user_id, 'reward_points', $current_points + $total_points );
        }
    }

    /**
     * Render the user profile with reward points information.
     *
     * To display this information, add the shortcode [herbal_mix_user_profile] to any page.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML content.
     */
    public function render_user_profile( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>' . __( 'You must be logged in to view your profile.', 'herbal-mix-creator2' ) . '</p>';
        }

        $user_id      = get_current_user_id();
        $reward_points = floatval( get_user_meta( $user_id, 'reward_points', true ) );
        
        ob_start();
        ?>
        <div class="herbal-mix-user-profile">
            <h2><?php _e( 'Your Reward Points', 'herbal-mix-creator2' ); ?></h2>
            <p><?php echo sprintf( __( 'You currently have %s reward points.', 'herbal-mix-creator2' ), $reward_points ); ?></p>
            <!-- Additional profile information (e.g. user-created blends, favorites) can be added here. -->
        </div>
        <?php
        return ob_get_clean();
    }
}

new HerbalMixRewardPoints();
