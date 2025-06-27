/**
 * Herbal Product Template JavaScript
 * File: assets/js/herbal-product-template.js
 */

(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(document).ready(function() {
        initHerbalProductTemplate();
    });

    function initHerbalProductTemplate() {
        // Initialize ingredient modals
        initIngredientModals();
        
        // Initialize add to cart animations
        initAddToCartAnimations();
        
        // Initialize gallery enhancements
        initGalleryEnhancements();
        
        // Initialize scroll animations
        initScrollAnimations();
    }

    /**
     * Initialize ingredient modal functionality
     */
    function initIngredientModals() {
        const modal = $('#herbalIngredientModal');
        const modalContent = modal.find('.herbal-modal-content');
        
        // Handle ingredient card clicks and enter key
        $('.herbal-ingredient-card').on('click keydown', function(e) {
            if (e.type === 'click' || (e.type === 'keydown' && (e.key === 'Enter' || e.key === ' '))) {
                e.preventDefault();
                const ingredientId = $(this).data('ingredient-id');
                openIngredientModal(ingredientId);
            }
        }).attr('tabindex', '0').attr('role', 'button')
          .attr('aria-label', function() {
              const name = $(this).find('.herbal-ingredient-name').text();
              return 'Learn more about ' + name;
          });
        
        // Handle modal close
        $('#herbalModalClose, .herbal-ingredient-modal').on('click', function(e) {
            if (e.target === this) {
                closeIngredientModal();
            }
        });
        
        // Handle ESC key and focus management
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && modal.hasClass('active')) {
                closeIngredientModal();
            }
        });
        
        // Focus management for accessibility
        modal.on('shown', function() {
            const closeButton = $('#herbalModalClose');
            closeButton.focus();
        });
    }

    /**
     * Open ingredient modal with details
     */
    function openIngredientModal(ingredientId) {
        const modal = $('#herbalIngredientModal');
        
        // Check if we have ingredient data
        if (typeof herbalTemplateData !== 'undefined' && 
            herbalTemplateData.ingredient_details && 
            herbalTemplateData.ingredient_details[ingredientId]) {
            
            const ingredient = herbalTemplateData.ingredient_details[ingredientId];
            
            // Update modal content
            $('#herbalModalIngredientName').text(ingredient.name);
            
            // Handle image
            const imageContainer = $('#herbalModalIngredientImage');
            if (ingredient.image_url) {
                imageContainer.html('<img src="' + ingredient.image_url + '" alt="' + ingredient.name + '">');
            } else {
                imageContainer.html('<div style="padding: 20px; color: #7f8c8d; font-style: italic;">No image available</div>');
            }
            
            // Handle description
            $('#herbalModalIngredientDetails').html(ingredient.description || 'No description available.');
            
            // Handle story
            const storyContainer = $('#herbalModalIngredientStory');
            const storySection = $('#herbalModalStorySection');
            if (ingredient.story && ingredient.story.trim()) {
                storyContainer.html(ingredient.story);
                storySection.show();
            } else {
                storySection.hide();
            }
            
            // Show modal with animation
            modal.addClass('active');
            $('body').addClass('modal-open');
            
            // Focus management for accessibility
            setTimeout(() => {
                $('#herbalModalClose').focus();
            }, 100);
            
        } else {
            // Fallback - load via AJAX if data not available
            loadIngredientDetailsAjax(ingredientId);
        }
    }

    /**
     * Close ingredient modal
     */
    function closeIngredientModal() {
        const modal = $('#herbalIngredientModal');
        modal.removeClass('active');
        $('body').removeClass('modal-open');
        
        // Return focus to the card that opened the modal
        setTimeout(() => {
            const activeCard = $('.herbal-ingredient-card:focus');
            if (activeCard.length === 0) {
                // If no card has focus, focus the first visible card
                $('.herbal-ingredient-card:visible:first').focus();
            }
        }, 300);
    }

    /**
     * Load ingredient details via AJAX (fallback)
     */
    function loadIngredientDetailsAjax(ingredientId) {
        const modal = $('#herbalIngredientModal');
        
        // Show loading state
        $('#herbalModalIngredientName').text(herbalTemplateData.translations.loading || 'Loading...').addClass('herbal-loading');
        $('#herbalModalIngredientDetails').text('...').addClass('herbal-loading');
        $('#herbalModalStorySection').hide();
        $('#herbalModalIngredientImage').html('<div style="padding: 20px; color: #7f8c8d;" class="herbal-loading">Loading...</div>');
        
        modal.addClass('active');
        $('body').addClass('modal-open');
        
        // Focus management
        setTimeout(() => {
            $('#herbalModalClose').focus();
        }, 100);
        
        // Make AJAX request
        $.ajax({
            url: herbalTemplateData.ajax_url,
            type: 'POST',
            data: {
                action: 'get_ingredient_details',
                ingredient_id: ingredientId,
                nonce: herbalTemplateData.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const ingredient = response.data;
                    
                    $('#herbalModalIngredientName').text(ingredient.name).removeClass('herbal-loading');
                    
                    if (ingredient.image_url) {
                        $('#herbalModalIngredientImage').html(
                            '<img src="' + ingredient.image_url + '" alt="' + ingredient.name + '">'
                        );
                    } else {
                        $('#herbalModalIngredientImage').html(
                            '<div style="padding: 20px; color: #7f8c8d; font-style: italic;">No image available</div>'
                        );
                    }
                    
                    $('#herbalModalIngredientDetails').html(
                        ingredient.description || 'No description available.'
                    ).removeClass('herbal-loading');
                    
                    const storyContainer = $('#herbalModalIngredientStory');
                    const storySection = $('#herbalModalStorySection');
                    if (ingredient.story && ingredient.story.trim()) {
                        storyContainer.html(ingredient.story);
                        storySection.show();
                    } else {
                        storySection.hide();
                    }
                } else {
                    $('#herbalModalIngredientName').text('Error').removeClass('herbal-loading');
                    $('#herbalModalIngredientDetails').text(
                        response.data || (herbalTemplateData.translations.error || 'An error occurred')
                    ).removeClass('herbal-loading');
                    $('#herbalModalStorySection').hide();
                    $('#herbalModalIngredientImage').html(
                        '<div style="padding: 20px; color: #e74c3c;">Error loading image</div>'
                    );
                }
            },
            error: function() {
                $('#herbalModalIngredientName').text('Error').removeClass('herbal-loading');
                $('#herbalModalIngredientDetails').text(
                    herbalTemplateData.translations.error || 'An error occurred'
                ).removeClass('herbal-loading');
                $('#herbalModalStorySection').hide();
                $('#herbalModalIngredientImage').html(
                    '<div style="padding: 20px; color: #e74c3c;">Error loading content</div>'
                );
            }
        });
    }

    /**
     * Initialize add to cart animations
     */
    function initAddToCartAnimations() {
        $('.herbal-cart-section .single_add_to_cart_button').on('click', function(e) {
            const button = $(this);
            const originalText = button.text();
            
            // Don't animate if it's already processing
            if (button.hasClass('processing')) {
                return;
            }
            
            // Add processing state
            button.addClass('processing');
            button.text(herbalTemplateData.translations.loading);
            
            // Listen for WooCommerce events
            $(document.body).one('added_to_cart', function() {
                button.removeClass('processing');
                button.text(herbalTemplateData.translations.added_to_cart);
                button.css('background', '#388e3c');
                
                setTimeout(function() {
                    button.text(originalText);
                    button.css('background', '');
                }, 3000);
            });
            
            // Fallback timeout
            setTimeout(function() {
                if (button.hasClass('processing')) {
                    button.removeClass('processing');
                    button.text(originalText);
                }
            }, 10000);
        });
    }

    /**
     * Initialize gallery enhancements
     */
    function initGalleryEnhancements() {
        // Add smooth transitions to gallery thumbnails
        $('.herbal-product-gallery .flex-control-thumbs img').on('mouseenter', function() {
            $(this).css('transform', 'translateY(-3px) scale(1.05)');
        }).on('mouseleave', function() {
            if (!$(this).hasClass('flex-active')) {
                $(this).css('transform', '');
            }
        });
        
        // Enhance main gallery image display
        $('.herbal-product-gallery .woocommerce-product-gallery__image').on('mouseenter', function() {
            $(this).find('img').css('transform', 'scale(1.02)');
        }).on('mouseleave', function() {
            $(this).find('img').css('transform', '');
        });
    }

    /**
     * Initialize scroll animations
     */
    function initScrollAnimations() {
        // Add intersection observer for fade-in animations
        if ('IntersectionObserver' in window) {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-in');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            
            // Observe sections
            $('.herbal-created-by-section, .herbal-story-section, .herbal-ingredients-section, .herbal-packaging-section, .herbal-reviews-section, .herbal-description-section').each(function() {
                observer.observe(this);
            });
        }
        
        // Add smooth scrolling for anchor links
        $('a[href^="#"]').on('click', function(e) {
            const target = $($(this).attr('href'));
            if (target.length) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: target.offset().top - 100
                }, 800);
            }
        });
    }

    /**
     * Enhanced ingredient card interactions
     */
    function initIngredientCardAnimations() {
        $('.herbal-ingredient-card').each(function() {
            const card = $(this);
            
            card.on('mouseenter', function() {
                const image = $(this).find('.herbal-ingredient-image');
                const placeholder = $(this).find('.herbal-ingredient-placeholder');
                
                if (image.length) {
                    image.css('transform', 'scale(1.05)');
                }
                if (placeholder.length) {
                    placeholder.css('transform', 'scale(1.1) rotate(5deg)');
                }
            }).on('mouseleave', function() {
                const image = $(this).find('.herbal-ingredient-image');
                const placeholder = $(this).find('.herbal-ingredient-placeholder');
                
                if (image.length) {
                    image.css('transform', '');
                }
                if (placeholder.length) {
                    placeholder.css('transform', '');
                }
            });
        });
    }

    /**
     * Initialize points section animations
     */
    function initPointsAnimations() {
        $('.herbal-points-item').on('mouseenter', function() {
            $(this).find('.herbal-points-value').css('transform', 'scale(1.05)');
        }).on('mouseleave', function() {
            $(this).find('.herbal-points-value').css('transform', '');
        });
    }

    // Add CSS animations via JavaScript
    function addAnimationStyles() {
        const styles = `
            <style>
            .herbal-product-gallery img {
                transition: transform 0.3s ease;
            }
            
            .herbal-ingredient-icon {
                transition: transform 0.3s ease;
            }
            
            .herbal-points-value {
                transition: transform 0.2s ease;
            }
            
            .animate-in {
                animation: fadeInUp 0.6s ease forwards;
            }
            
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            body.modal-open {
                overflow: hidden;
            }
            
            .herbal-cart-section .single_add_to_cart_button.processing {
                pointer-events: none;
                opacity: 0.7;
            }
            </style>
        `;
        $('head').append(styles);
    }

    // Initialize additional animations when DOM is ready
    $(document).ready(function() {
        addAnimationStyles();
        initIngredientCardAnimations();
        initPointsAnimations();
    });

})(jQuery);