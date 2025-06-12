<?php
/**
 * Template for the Herbal Mix Creator frontend form with login/registration check
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Sprawdź, czy mamy komunikat po przekierowaniu
$login_message = '';
if (isset($_GET['login']) && $_GET['login'] == 'failed') {
    $login_message = '<div class="login-error">Incorrect username or password. Please try again.</div>';
} elseif (isset($_GET['login']) && $_GET['login'] == 'empty') {
    $login_message = '<div class="login-error">Username and password are required.</div>';
}

// Obsługa logowania bez przekierowania do wp-login.php
if (isset($_POST['herbal_mix_login']) && $_POST['herbal_mix_login'] == '1') {
    // Weryfikacja nonce
    if (isset($_POST['herbal_mix_login_nonce']) && wp_verify_nonce($_POST['herbal_mix_login_nonce'], 'herbal_mix_login')) {
        $creds = array(
            'user_login'    => isset($_POST['log']) ? sanitize_text_field($_POST['log']) : '',
            'user_password' => isset($_POST['pwd']) ? $_POST['pwd'] : '',
            'remember'      => isset($_POST['rememberme']) ? true : false
        );

        // Walidacja
        if (empty($creds['user_login']) || empty($creds['user_password'])) {
            $login_message = '<div class="login-error">Username and password are required.</div>';
        } else {
            // Próba logowania
            $user = wp_signon($creds, false);
            
            if (is_wp_error($user)) {
                // Zapisujemy konkretny błąd
                $error_message = $user->get_error_message();
                $login_message = '<div class="login-error">' . esc_html($error_message) . '</div>';
            } else {
                // Udane logowanie - odświeżamy stronę
                wp_safe_redirect(remove_query_arg(['login'], $_SERVER['REQUEST_URI']));
                exit;
            }
        }
    }
}

// Sprawdź czy użytkownik jest zalogowany
if ( is_user_logged_in() ) {
    // ===== KOD DLA ZALOGOWANYCH UŻYTKOWNIKÓW =====
    
    global $wpdb;
    // Fetch available packaging options
    $packaging_table = $wpdb->prefix . 'herbal_packaging';
    $packaging_items = $wpdb->get_results(
        "SELECT id, name, herb_capacity, image_url, price, price_point, point_earned
           FROM {$packaging_table}
          WHERE available = 1
          ORDER BY herb_capacity ASC",
        ARRAY_A
    );
    ?>

    <form id="herbal-mix-form" class="herbal-mix-wrapper">
      <!-- Packaging selection -->
      <div class="packaging-section">
        <h2>Select packaging</h2>
        <ul class="packaging-options">
          <?php foreach ( $packaging_items as $pack ) : ?>
            <li class="packaging-item"
                data-id="<?php echo esc_attr( $pack['id'] ); ?>"
                data-capacity="<?php echo esc_attr( $pack['herb_capacity'] ); ?>"
                data-price="<?php echo esc_attr( $pack['price'] ); ?>"
                data-price-point="<?php echo esc_attr( $pack['price_point'] ); ?>"
                data-point-earned="<?php echo esc_attr( $pack['point_earned'] ); ?>">
              <img src="<?php echo esc_url( $pack['image_url'] ); ?>"
                   alt="<?php echo esc_attr( $pack['name'] ); ?>"
                   class="packaging-thumb">
              <h3><?php echo esc_html( $pack['name'] ); ?></h3>
              <p><?php echo esc_html( $pack['herb_capacity'] ); ?> g &ndash; <?php echo esc_html( get_woocommerce_currency_symbol() ); ?><?php echo esc_html( number_format( $pack['price'], 2 ) ); ?></p>
              <p class="points-info"><?php echo esc_html( $pack['price_point'] ); ?> pts / <?php echo esc_html( $pack['point_earned'] ); ?> pts earned</p>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="mix-container">
        <!-- Left column: chart and summary -->
        <div class="left-column">
          <div class="chart-container">
            <canvas id="mixChart"></canvas>
          </div>
          <div class="mix-summary">
            <p>Total weight: <span id="total-weight">0g</span></p>
            <p>Price: <span id="total-price">0.00</span> <?php echo get_woocommerce_currency_symbol(); ?></p>
            <p>Cost in points: <span id="total-points-cost">0</span> pts</p>
            <p>Reward points earned: <span id="total-points-earned">0</span> pts</p>
          </div>
        </div>

        <!-- Right column: search, categories, ingredients -->
        <div class="right-column">
          <div class="search-box">
            <input type="text" id="ingredient-search" placeholder="Search ingredients..." disabled>
          </div>
          <div class="categories-section disabled">
            <div id="categories-container" class="categories-container"></div>
          </div>
          <div class="ingredients-section disabled">
            <div id="ingredients-container" class="ingredients-container"></div>
          </div>
        </div>
      </div>

      <!-- Selected ingredients -->
      <div class="selected-section">
        <h3>Selected ingredients</h3>
        <div id="selected-ingredients"></div>
      </div>

      <!-- Mix name and action buttons -->
      <div class="mix-name-input">
        <input type="text" id="mix-name" name="mix_name" placeholder="Enter mix name">
      </div>
      
      <div class="action-buttons">
        <button type="button" id="save-mix-btn" class="button" disabled>Save mix</button>
        <button type="button" id="add-to-basket-btn" class="button button-primary" disabled>Add to basket</button>
      </div>
    </form>

<?php } else { 
    // ===== KOD DLA NIEZALOGOWANYCH UŻYTKOWNIKÓW =====
    ?>
    
    <div class="herbal-mix-auth-required">
        <div class="auth-container">
            <div class="auth-message">
                <h2>Premium Herbal Mix Creator</h2>
                <p>Create your personalized herbal mixes with our premium ingredients and exclusive packaging options.</p>
                <div class="auth-divider">
                    <span>Login Required</span>
                </div>
                <p class="login-notice">To access the Herbal Mix Creator, please log in to your account or create a new one.</p>
            </div>
            
            <div class="auth-forms">
                <!-- Login Form -->
                <div class="auth-form login-form">
                    <h3>Already have an account?</h3>
                    <?php
                    // Używamy własnego formularza zamiast wbudowanych, aby mieć pełną kontrolę nad przekierowaniami
                    ?>
                    <?php if (!empty($login_message)) echo $login_message; ?>
                    
                    <form method="post" action="" class="custom-login-form">
                        <p class="login-username">
                            <label for="user_login"><?php _e( 'Username or Email', 'herbal-mix-creator2' ); ?></label>
                            <input type="text" name="log" id="user_login" class="input" value="" size="20">
                        </p>
                        <p class="login-password">
                            <label for="user_pass"><?php _e( 'Password', 'herbal-mix-creator2' ); ?></label>
                            <input type="password" name="pwd" id="user_pass" class="input" value="" size="20">
                        </p>
                        <p class="login-remember">
                            <label><input name="rememberme" type="checkbox" id="rememberme" value="forever"> <?php _e( 'Remember Me', 'herbal-mix-creator2' ); ?></label>
                        </p>
                        <input type="hidden" name="herbal_mix_login" value="1">
                        <?php wp_nonce_field( 'herbal_mix_login', 'herbal_mix_login_nonce' ); ?>
                        <p class="login-submit">
                            <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="<?php _e( 'Sign In', 'herbal-mix-creator2' ); ?>">
                        </p>
                    </form>
                    <?php
                    ?>
                    <p class="lost-password">
                        <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Forgot your password?</a>
                    </p>
                </div>
                
                <!-- Registration Form -->
                <div class="auth-form register-form">
                    <h3>New to our premium herbal collection?</h3>
                    <p>Create an account to access our exclusive Herbal Mix Creator and enjoy benefits:</p>
                    <ul class="benefits-list">
                        <li>Create custom herbal mixes</li>
                        <li>Save your favorite blends</li>
                        <li>Earn reward points with every purchase</li>
                        <li>Share your creations with the community</li>
                    </ul>
                    <a href="<?php echo esc_url( add_query_arg('action', 'register', get_permalink( get_option('woocommerce_myaccount_page_id') ) ) ); ?>" class="button button-primary register-button">Create Account</a>
                </div>
            </div>
            
            <!-- Optional: Feature Showcase For Non-Logged Users -->
            <div class="features-showcase">
                <h3>Explore Our Premium Herbal Mix Creator</h3>
                <div class="features-grid">
                    <div class="feature-item">
                        <div class="feature-icon packaging-icon"></div>
                        <h4>Premium Packaging</h4>
                        <p>Choose from our selection of high-quality, eco-friendly packaging options.</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon ingredients-icon"></div>
                        <h4>Curated Ingredients</h4>
                        <p>Access to over 50 carefully selected premium herbs and botanicals.</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon rewards-icon"></div>
                        <h4>Reward Points</h4>
                        <p>Earn points with every creation that you can redeem for future purchases.</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon share-icon"></div>
                        <h4>Share & Publish</h4>
                        <p>Share your successful creations with our community of herbal enthusiasts.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dodatkowa sekcja CSS dla niezalogowanych użytkowników -->
    <style>
        .herbal-mix-auth-required {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            font-family: 'Open Sans', Arial, sans-serif;
        }
        
        .auth-container {
            background-color: #FCFCFA;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .auth-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #8AC249, #2A6A3C);
        }
        
        .auth-message {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .auth-message h2 {
            font-size: 32px;
            color: #2A6A3C;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .auth-message p {
            font-size: 18px;
            color: #555;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .auth-divider {
            margin: 30px 0;
            position: relative;
            text-align: center;
        }
        
        .auth-divider::before {
            content: "";
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e0e0e0;
            z-index: 0;
        }
        
        .auth-divider span {
            background: #FCFCFA;
            padding: 0 15px;
            position: relative;
            z-index: 1;
            color: #2A6A3C;
            font-weight: 600;
            font-size: 16px;
        }
        
        .login-notice {
            color: #e83e8c;
            font-weight: 500;
            font-size: 16px;
            margin-top: 15px;
        }
        
        .login-error {
            background-color: #fff2f2;
            border-left: 4px solid #dc3545;
            color: #dc3545;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .auth-forms {
            display: flex;
            justify-content: space-between;
            gap: 40px;
            margin-bottom: 50px;
        }
        
        .auth-form {
            flex: 1;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .auth-form h3 {
            color: #2A6A3C;
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: 600;
        }
        
        /* Style dla formularza logowania */
        #herbal-login-form, .woocommerce-form-login {
            margin-bottom: 0;
        }
        
        .auth-form input[type="text"],
        .auth-form input[type="password"],
        .auth-form input[type="email"],
        .woocommerce-form-login input {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .auth-form input:focus,
        .woocommerce-form-login input:focus {
            border-color: #8AC249;
            outline: none;
            box-shadow: 0 0 0 2px rgba(138, 194, 73, 0.2);
        }
        
        .auth-form label,
        .woocommerce-form-login label {
            font-weight: 500;
            display: block;
            margin-bottom: 8px;
            color: #555;
        }
        
        .auth-form .button,
        .auth-form button,
        .woocommerce-form-login .button {
            background: #2A6A3C;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .auth-form .button:hover,
        .auth-form button:hover,
        .woocommerce-form-login .button:hover {
            background: #1d4c2a;
        }
        
        .lost-password {
            text-align: center;
            margin-top: 15px;
        }
        
        .lost-password a {
            color: #2A6A3C;
            text-decoration: none;
            font-size: 14px;
        }
        
        .lost-password a:hover {
            text-decoration: underline;
        }
        
        /* Style dla formularza rejestracji */
        .benefits-list {
            margin: 15px 0 25px;
            padding-left: 20px;
        }
        
        .benefits-list li {
            margin-bottom: 10px;
            color: #555;
            position: relative;
            padding-left: 15px;
        }
        
        .benefits-list li::before {
            content: "●";
            color: #8AC249;
            position: absolute;
            left: -15px;
            top: 2px;
        }
        
        .register-button {
            display: block;
            text-align: center;
            background: #8AC249 !important;
            color: white !important;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .register-button:hover {
            background: #78a93e !important;
            text-decoration: none;
        }
        
        /* Feature showcase */
        .features-showcase {
            margin-top: 50px;
            text-align: center;
        }
        
        .features-showcase h3 {
            color: #2A6A3C;
            margin-bottom: 30px;
            font-size: 24px;
            font-weight: 600;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .feature-item {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            background-position: center;
            background-size: contain;
            background-repeat: no-repeat;
        }
        
        .packaging-icon {
            background-image: url('<?php echo plugin_dir_url( __FILE__ ) . 'assets/img/packaging-icon.png'; ?>');
        }
        
        .ingredients-icon {
            background-image: url('<?php echo plugin_dir_url( __FILE__ ) . 'assets/img/ingredients-icon.png'; ?>');
        }
        
        .rewards-icon {
            background-image: url('<?php echo plugin_dir_url( __FILE__ ) . 'assets/img/rewards-icon.png'; ?>');
        }
        
        .share-icon {
            background-image: url('<?php echo plugin_dir_url( __FILE__ ) . 'assets/img/share-icon.png'; ?>');
        }
        
        .feature-item h4 {
            color: #2A6A3C;
            margin-bottom: 10px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .feature-item p {
            color: #555;
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Responsywność */
        @media (max-width: 768px) {
            .auth-forms {
                flex-direction: column;
                gap: 20px;
            }
            
            .auth-container {
                padding: 20px;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
<?php } ?>