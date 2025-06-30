/**
 * Herbal Mix Points JavaScript - COMPLETE COMPATIBLE VERSION
 * Works seamlessly with existing mix-creator.js and profile.js
 * No conflicts, proper integration, conditional loading
 * File: assets/js/herbal-points.js
 */

(function($) {
    'use strict';

    // Only initialize if herbalPointsData exists and no conflicts
    if (typeof herbalPointsData === 'undefined') {
        return; // Exit silently if not loaded
    }

    // Check for conflicts with existing scripts
    if (typeof window.HerbalPointsCompatible !== 'undefined') {
        return; // Already loaded
    }

    const HerbalPointsCompatible = {
        
        initialized: false,
        updateTimeout: null,
        
        init: function() {
            if (this.initialized) {
                return; // Prevent double initialization
            }
            
            console.log('Herbal Points Compatible: Initializing...');
            
            // Wait for DOM and other scripts to be ready
            this.waitForDependencies(() => {
                this.bindEvents();
                this.initTooltips();
                this.checkPointsAvailability();
                this.enhanceExistingElements();
                this.initialized = true;
                
                console.log('Herbal Points Compatible: Ready!');
            });
        },

        waitForDependencies: function(callback) {
            // Wait for WooCommerce cart events to be ready
            let attempts = 0;
            const maxAttempts = 10;
            
            const check = () => {
                attempts++;
                
                // Check if essential elements exist
                const cartExists = $('body.woocommerce-cart').length > 0;
                const checkoutExists = $('body.woocommerce-checkout').length > 0;
                const productExists = $('body.single-product').length > 0;
                
                if (cartExists || checkoutExists || productExists || attempts >= maxAttempts) {
                    callback();
                } else {
                    setTimeout(check, 100);
                }
            };
            
            check();
        },

        bindEvents: function() {
            // Cart/Checkout events - only if WooCommerce events exist
            if (typeof wc_checkout_params !== 'undefined' || typeof wc_cart_params !== 'undefined') {
                $(document.body).on('updated_cart_totals updated_checkout', this.handleCartUpdate.bind(this));
            }
            
            // Payment method selection - only on checkout
            if ($('body.woocommerce-checkout').length > 0) {
                $(document).on('change', 'input[name="payment_method"]', this.handlePaymentMethodChange.bind(this));
            }
            
            // Quantity changes - debounced to avoid conflicts
            $(document).on('change', '.qty', this.handleQuantityChange.bind(this));
            
            // Points refresh button - only if Points Manager elements exist
            $(document).on('click', '.refresh-points', this.refreshPointsBalance.bind(this));
            
            // Calculator trigger - optional feature
            $(document).on('click', '.points-calculator-trigger', this.showPointsCalculator.bind(this));
            
            // Window load event for final initialization
            $(window).on('load', this.finalizeSetup.bind(this));
        },

        handleCartUpdate: function() {
            // Debounce updates to avoid conflicts with other scripts
            clearTimeout(this.updateTimeout);
            this.updateTimeout = setTimeout(() => {
                this.updatePointsDisplay();
            }, 500);
        },

        handlePaymentMethodChange: function(e) {
            const selectedMethod = $(e.target).val();
            
            if (selectedMethod === 'points_payment') {
                this.highlightPointsElements();
                this.validatePointsBalance();
            } else {
                this.unhighlightPointsElements();
            }
        },

        highlightPointsElements: function() {
            // Only highlight if elements exist
            $('.points-payment-gateway-info').addClass('highlighted');
            $('.herbal-points-manager-display').addClass('highlighted');
            $('.herbal-points-total-row').addClass('highlighted');
        },

        unhighlightPointsElements: function() {
            $('.points-payment-gateway-info').removeClass('highlighted');
            $('.herbal-points-manager-display').removeClass('highlighted');
            $('.herbal-points-total-row').removeClass('highlighted');
        },

        handleQuantityChange: function(e) {
            // Only update if we're on cart/checkout pages
            if ($('body.woocommerce-cart, body.woocommerce-checkout').length === 0) {
                return;
            }
            
            // Debounce to avoid conflicts with WooCommerce updates
            clearTimeout(this.updateTimeout);
            this.updateTimeout = setTimeout(() => {
                this.updatePointsDisplay();
            }, 1000); // Longer delay to let WooCommerce finish first
        },

        initTooltips: function() {
            // Add tooltips only to our elements
            $('.herbal-points-manager-display .points-icon, .herbal-points-manager-display .cost-icon, .herbal-points-manager-display .earn-icon, .herbal-points-manager-display .balance-icon').each(function() {
                const $this = $(this);
                
                // Add helpful tooltips
                if ($this.hasClass('points-icon')) {
                    $this.attr('title', 'Points Summary - View your points balance and potential costs');
                } else if ($this.hasClass('cost-icon')) {
                    $this.attr('title', 'Alternative Cost - Pay with points instead of money');
                } else if ($this.hasClass('earn-icon')) {
                    $this.attr('title', 'Points Earned - Reward points you will receive');
                } else if ($this.hasClass('balance-icon')) {
                    $this.attr('title', 'Your Points Balance - Current available points');
                }
            });
        },

        checkPointsAvailability: function() {
            // Only run if we're on relevant pages
            if (!this.isRelevantPage()) {
                return;
            }

            this.validatePointsBalance();
        },

        validatePointsBalance: function() {
            const $paymentInfo = $('.points-payment-gateway-info');
            const $pointsMethod = $('input[value="points_payment"]');
            
            if (!$paymentInfo.length || !$pointsMethod.length) {
                return;
            }

            // Parse points values safely
            const userBalance = this.parsePointsFromText($paymentInfo.find('td').eq(1).text());
            const requiredPoints = this.parsePointsFromText($paymentInfo.find('td').eq(3).text());

            if (userBalance < requiredPoints) {
                $pointsMethod.prop('disabled', true);
                this.showInsufficientPointsMessage();
            } else {
                $pointsMethod.prop('disabled', false);
                this.hideInsufficientPointsMessage();
            }
        },

        parsePointsFromText: function(text) {
            if (!text) return 0;
            const match = text.match(/[\d,]+/);
            return match ? parseInt(match[0].replace(/,/g, '')) : 0;
        },

        showInsufficientPointsMessage: function() {
            if ($('.insufficient-points-message').length > 0) {
                return; // Already showing
            }

            const message = $('<div class="insufficient-points-message woocommerce-error">')
                .text(herbalPointsData.strings.insufficient_points)
                .hide()
                .fadeIn();
            
            $('.points-payment-gateway-info').after(message);
        },

        hideInsufficientPointsMessage: function() {
            $('.insufficient-points-message').fadeOut(function() {
                $(this).remove();
            });
        },

        updatePointsDisplay: function() {
            // Only update if AJAX endpoints are available
            if (!herbalPointsData.ajax_url) {
                return;
            }

            // Show loading state
            this.showLoadingState();

            // Use our specific action names to avoid conflicts
            $.ajax({
                url: herbalPointsData.ajax_url,
                type: 'POST',
                data: {
                    action: 'herbal_points_calculate_cart',
                    nonce: herbalPointsData.nonce
                },
                timeout: 10000, // 10 second timeout
                success: (response) => {
                    if (response && response.success) {
                        this.updatePointsUI(response.data);
                    }
                },
                error: (xhr, status, error) => {
                    // Fail silently to avoid disrupting other functionality
                    console.warn('Herbal Points: Failed to update display', error);
                },
                complete: () => {
                    this.hideLoadingState();
                }
            });
        },

        updatePointsUI: function(data) {
            if (!data) return;

            // Update cost display
            if (data.cost !== undefined) {
                $('.herbal-points-manager-display .cost-amount').text(this.formatPoints(data.cost));
            }

            // Update earned display
            if (data.earned !== undefined) {
                $('.herbal-points-manager-display .earn-amount').text(this.formatPoints(data.earned));
            }

            // Update balance display
            if (data.balance !== undefined) {
                $('.herbal-points-manager-display .balance-amount').text(this.formatPoints(data.balance));
            }

            // Update availability status
            if (data.can_afford !== undefined) {
                const $costSection = $('.herbal-points-manager-display .points-cost');
                $costSection.removeClass('affordable insufficient');
                $costSection.addClass(data.can_afford ? 'affordable' : 'insufficient');

                const $statusIndicator = $('.herbal-points-manager-display .status-indicator');
                if (data.can_afford) {
                    $statusIndicator.removeClass('insufficient').addClass('available');
                    $statusIndicator.text('✓ Available');
                } else if (data.needed && data.needed > 0) {
                    $statusIndicator.removeClass('available').addClass('insufficient');
                    $statusIndicator.text('⚠ Need ' + this.formatPoints(data.needed) + ' more');
                }
            }
        },

        formatPoints: function(points) {
            if (typeof points !== 'number') {
                points = parseFloat(points) || 0;
            }
            return new Intl.NumberFormat().format(Math.round(points)) + ' pts';
        },

        showLoadingState: function() {
            $('.herbal-points-manager-display').addClass('points-loading');
        },

        hideLoadingState: function() {
            $('.herbal-points-manager-display').removeClass('points-loading');
        },

        refreshPointsBalance: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const originalText = $button.text();
            
            $button.text('⟳').prop('disabled', true);

            $.ajax({
                url: herbalPointsData.ajax_url,
                type: 'POST',
                data: {
                    action: 'herbal_points_refresh_balance',
                    nonce: herbalPointsData.nonce
                },
                timeout: 5000,
                success: (response) => {
                    if (response && response.success && response.data && response.data.balance !== undefined) {
                        $('.herbal-points-manager-display .balance-amount').text(this.formatPoints(response.data.balance));
                        this.showSuccessMessage(herbalPointsData.strings.points_updated);
                    }
                },
                error: (xhr, status, error) => {
                    console.warn('Herbal Points: Failed to refresh balance', error);
                },
                complete: () => {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        showSuccessMessage: function(message) {
            const $success = $('<div class="woocommerce-message">')
                .text(message)
                .hide()
                .fadeIn();

            $('.herbal-points-manager-display').first().after($success);

            setTimeout(() => {
                $success.fadeOut(() => $success.remove());
            }, 3000);
        },

        showPointsCalculator: function(e) {
            e.preventDefault();

            // Only show if we have points data
            if (!this.hasPointsData()) {
                return;
            }

            // Create modal
            const $overlay = $('<div class="points-calculator-overlay">');
            const $modal = $('<div class="points-calculator-modal">');
            
            $modal.html(`
                <div class="calculator-header">
                    <h3>Points Calculator</h3>
                    <button class="close-calculator" type="button">&times;</button>
                </div>
                <div class="calculator-content">
                    <div class="calculator-section">
                        <label>Current Balance:</label>
                        <span class="current-balance">Loading...</span>
                    </div>
                    <div class="calculator-section">
                        <label>Needed for Cart:</label>
                        <span class="needed-points">Loading...</span>
                    </div>
                    <div class="calculator-section">
                        <label>After Purchase:</label>
                        <span class="after-purchase">Loading...</span>
                    </div>
                    <div class="calculator-section">
                        <label>Will Earn:</label>
                        <span class="will-earn">Loading...</span>
                    </div>
                </div>
            `);

            $overlay.append($modal);
            $('body').append($overlay);

            // Populate data
            this.populateCalculator($modal);

            // Bind events
            $overlay.on('click', (e) => {
                if (e.target === $overlay[0]) {
                    this.closeCalculator();
                }
            });

            $('.close-calculator').on('click', this.closeCalculator);
        },

        populateCalculator: function($modal) {
            const userBalance = this.parsePointsFromText($('.herbal-points-manager-display .balance-amount').text());
            const neededPoints = this.parsePointsFromText($('.herbal-points-manager-display .cost-amount').text());
            const willEarn = this.parsePointsFromText($('.herbal-points-manager-display .earn-amount').text());
            const afterPurchase = userBalance - neededPoints + willEarn;

            $modal.find('.current-balance').text(this.formatPoints(userBalance));
            $modal.find('.needed-points').text(this.formatPoints(neededPoints));
            $modal.find('.after-purchase').text(this.formatPoints(Math.max(0, afterPurchase)));
            $modal.find('.will-earn').text(this.formatPoints(willEarn));
        },

        closeCalculator: function() {
            $('.points-calculator-overlay').fadeOut(() => {
                $('.points-calculator-overlay').remove();
            });
        },

        enhanceExistingElements: function() {
            // Add helpful classes to existing elements
            $('.woocommerce-cart-form').addClass('herbal-points-enhanced');
            $('.checkout-review-order').addClass('herbal-points-enhanced');
            
            // Add loading indicators where appropriate
            if ($('.herbal-points-manager-display').length > 0) {
                $('.herbal-points-manager-display').attr('data-herbal-points', 'enhanced');
            }
        },

        finalizeSetup: function() {
            // Final checks and enhancements after page load
            this.checkPointsAvailability();
            
            // Add version info for debugging
            if (window.console && console.log) {
                console.log('Herbal Points Compatible v1.0 - Ready');
            }
        },

        hasPointsData: function() {
            return $('.herbal-points-manager-display .balance-amount').length > 0;
        },

        isRelevantPage: function() {
            return $('body.woocommerce-cart, body.woocommerce-checkout, body.single-product, body.woocommerce-account').length > 0;
        },

        // Utility function for safe property access
        safeGet: function(obj, path, defaultValue = null) {
            try {
                return path.split('.').reduce((current, key) => {
                    return current && current[key] !== undefined ? current[key] : defaultValue;
                }, obj);
            } catch (e) {
                return defaultValue;
            }
        }
    };

    // Initialize when DOM is ready
    $(document).ready(() => {
        // Small delay to let other scripts initialize first
        setTimeout(() => {
            HerbalPointsCompatible.init();
        }, 100);
    });

    // Make globally accessible for debugging (with unique name)
    window.HerbalPointsCompatible = HerbalPointsCompatible;

    // Add enhanced CSS styles
    if (!$('#herbal-points-enhanced-styles').length) {
        const enhancedStyles = `
            <style id="herbal-points-enhanced-styles">
            /* Loading states */
            .herbal-points-manager-display.points-loading {
                opacity: 0.6;
                pointer-events: none;
                position: relative;
            }
            
            .herbal-points-manager-display.points-loading::after {
                content: "";
                position: absolute;
                top: 50%;
                left: 50%;
                width: 20px;
                height: 20px;
                margin: -10px 0 0 -10px;
                border: 2px solid #f3f3f3;
                border-top: 2px solid #007cba;
                border-radius: 50%;
                animation: herbal-spin 1s linear infinite;
            }
            
            @keyframes herbal-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            /* Highlighted states */
            .herbal-points-manager-display.highlighted,
            .points-payment-gateway-info.highlighted {
                background: rgba(0, 123, 186, 0.1) !important;
                border-left: 4px solid #007cba !important;
                transition: all 0.3s ease;
            }
            
            /* Calculator modal */
            .points-calculator-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .points-calculator-modal {
                background: white;
                border-radius: 8px;
                max-width: 400px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            }
            
            .calculator-header {
                padding: 20px;
                border-bottom: 1px solid #eee;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .calculator-header h3 {
                margin: 0;
                color: #2c3e50;
            }
            
            .close-calculator {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #999;
                padding: 0;
                line-height: 1;
            }
            
            .close-calculator:hover {
                color: #333;
            }
            
            .calculator-content {
                padding: 20px;
            }
            
            .calculator-section {
                display: flex;
                justify-content: space-between;
                margin-bottom: 15px;
                padding: 12px;
                background: #f8f9fa;
                border-radius: 4px;
                border-left: 4px solid #007cba;
            }
            
            .calculator-section:last-child {
                margin-bottom: 0;
            }
            
            .calculator-section label {
                font-weight: 600;
                color: #495057;
            }
            
            .calculator-section span {
                color: #007cba;
                font-weight: 600;
            }
            
            /* Enhanced form elements */
            .herbal-points-enhanced {
                position: relative;
            }
            
            /* Error states */
            .insufficient-points-message {
                margin: 10px 0;
                padding: 10px;
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
                border-radius: 4px;
            }
            
            /* Success states */
            .woocommerce-message {
                margin: 10px 0;
                padding: 10px;
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
                border-radius: 4px;
            }
            
            /* Mobile responsive */
            @media (max-width: 768px) {
                .points-calculator-modal {
                    width: 95%;
                    margin: 20px;
                }
                
                .calculator-header {
                    padding: 15px;
                }
                
                .calculator-content {
                    padding: 15px;
                }
                
                .calculator-section {
                    flex-direction: column;
                    gap: 5px;
                }
                
                .calculator-section label {
                    font-size: 14px;
                }
            }
            </style>
        `;
        
        $('head').append(enhancedStyles);
    }

})(jQuery);