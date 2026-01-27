<?php
if ( ! defined( 'WPINC' ) ) die;

class O100_Auth_Modal {

	public function __construct() {
		// Output modal on frontend if not logged in
		add_action( 'wp_footer', array( $this, 'render_modal' ) );

		// Handle AJAX Login
		add_action( 'wp_ajax_nopriv_o100_ajax_login', array( $this, 'handle_login' ) );

		// Handle AJAX Register
		add_action( 'wp_ajax_nopriv_o100_ajax_register', array( $this, 'handle_register' ) );
	}

	public function render_modal() {
		error_log('O100_Auth_Modal::render_modal called. is_admin: ' . (is_admin() ? '1':'0') . ', logged_in: ' . (is_user_logged_in() ? '1':'0'));
		// Only render on frontend for logged out users
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		
		$primary_color = '#e11d48'; // Default Order100 primary
		$options = get_option( 'o100_options', array() );
		if ( ! empty( $options['o100_main_color'] ) ) {
			$primary_color = $options['o100_main_color'];
		}
		?>
		<div id="o100-auth-modal-overlay" class="o100-auth-overlay" style="display: none;">
			<div class="o100-auth-modal">
				<button type="button" class="o100-auth-close">&times;</button>
				
				<div class="o100-auth-content">
					<!-- Login Form -->
					<div id="o100-auth-login-view" class="o100-auth-view active">
						<h2 class="o100-auth-title"><?php esc_html_e( 'Welcome Back', 'order100' ); ?></h2>
						<p class="o100-auth-subtitle"><?php esc_html_e( 'Sign in to continue your order.', 'order100' ); ?></p>
						
						<div class="o100-auth-error" style="display:none;"></div>
						
						<form id="o100-login-form" class="o100-auth-form" method="post">
							<div class="o100-form-group">
								<label for="o100-log-username"><?php esc_html_e( 'Username or email address', 'order100' ); ?> <span class="required">*</span></label>
								<input type="text" name="username" id="o100-log-username" required>
							</div>
							<div class="o100-form-group">
								<label for="o100-log-password"><?php esc_html_e( 'Password', 'order100' ); ?> <span class="required">*</span></label>
								<input type="password" name="password" id="o100-log-password" required>
							</div>
							
							<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
							<input type="hidden" name="action" value="o100_ajax_login">
							
							<button type="submit" class="o100-auth-submit-btn"><?php esc_html_e( 'Sign in', 'order100' ); ?> <span class="o100-auth-spinner" style="display:none;">&#x21bb;</span></button>
						</form>
						
						<?php if ( get_option( 'woocommerce_enable_myaccount_registration' ) === 'yes' ) : ?>
							<div class="o100-auth-switch">
								<?php esc_html_e( 'New to us?', 'order100' ); ?> 
								<a href="#" class="o100-switch-to-register"><?php esc_html_e( 'Create an account', 'order100' ); ?></a>
							</div>
						<?php endif; ?>
					</div>
					
					<!-- Register Form -->
					<?php if ( get_option( 'woocommerce_enable_myaccount_registration' ) === 'yes' ) : ?>
					<div id="o100-auth-register-view" class="o100-auth-view" style="display: none;">
						<h2 class="o100-auth-title"><?php esc_html_e( 'Create Account', 'order100' ); ?></h2>
						<p class="o100-auth-subtitle"><?php esc_html_e( 'Join us to get special offers.', 'order100' ); ?></p>
						
						<div class="o100-auth-error" style="display:none;"></div>
						
						<form id="o100-register-form" class="o100-auth-form" method="post">
							<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
								<div class="o100-form-group">
									<label for="o100-reg-username"><?php esc_html_e( 'Username', 'order100' ); ?> <span class="required">*</span></label>
									<input type="text" name="username" id="o100-reg-username" required>
								</div>
							<?php endif; ?>
							<div class="o100-form-group">
								<label for="o100-reg-email"><?php esc_html_e( 'Email address', 'order100' ); ?> <span class="required">*</span></label>
								<input type="email" name="email" id="o100-reg-email" required>
							</div>
							<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
								<div class="o100-form-group">
									<label for="o100-reg-password"><?php esc_html_e( 'Password', 'order100' ); ?> <span class="required">*</span></label>
									<input type="password" name="password" id="o100-reg-password" required>
								</div>
							<?php endif; ?>
							
							<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
							<input type="hidden" name="action" value="o100_ajax_register">
							
							<button type="submit" class="o100-auth-submit-btn"><?php esc_html_e( 'Register', 'order100' ); ?> <span class="o100-auth-spinner" style="display:none;">&#x21bb;</span></button>
						</form>
						
						<div class="o100-auth-switch">
							<?php esc_html_e( 'Already have an account?', 'order100' ); ?> 
							<a href="#" class="o100-switch-to-login"><?php esc_html_e( 'Sign in', 'order100' ); ?></a>
						</div>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		
		<style>
		/* Global CSS for the trigger */
		.o100-login-trigger { cursor: pointer; }
		.woocommerce-error:has(.o100-login-trigger),
		.woocommerce-error li:has(.o100-login-trigger),
		.woocommerce-message:has(.o100-login-trigger),
		.woocommerce-info:has(.o100-login-trigger) {
			display: none !important;
		}
		
		/* Overlay */
		.o100-auth-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(3px); z-index: 99999; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
		.o100-auth-overlay.show { opacity: 1; visibility: visible; }
		
		/* Modal */
		.o100-auth-modal { background: #fff; width: 100%; max-width: 420px; border-radius: 16px; position: relative; box-shadow: 0 10px 40px rgba(0,0,0,0.15); transform: translateY(20px); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); margin: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; overflow: hidden; }
		.o100-auth-overlay.show .o100-auth-modal { transform: translateY(0); }
		
		/* Close Btn */
		.o100-auth-close { position: absolute; top: 12px; right: 12px; width: 32px; height: 32px; border-radius: 50%; background: #f3f4f6; border: none; font-size: 20px; color: #4b5563; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; z-index: 10; }
		.o100-auth-close:hover { background: #e5e7eb; color: #111; }
		
		/* Content */
		.o100-auth-content { padding: 40px 32px 32px; }
		.o100-auth-title { font-size: 24px; font-weight: 700; color: #111; margin: 0 0 8px; line-height: 1.2; text-align: center; }
		.o100-auth-subtitle { font-size: 14px; color: #6b7280; text-align: center; margin: 0 0 24px; }
		
		/* Error */
		.o100-auth-error { background: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; padding: 12px 16px; border-radius: 6px; font-size: 13px; margin-bottom: 20px; line-height: 1.4; }
		
		/* Form */
		.o100-auth-form { margin-bottom: 20px; }
		.o100-form-group { margin-bottom: 16px; }
		.o100-form-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
		.o100-form-group .required { color: #ef4444; }
		.o100-form-group input { width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 14px; font-size: 14px; color: #111; transition: border-color 0.2s, box-shadow 0.2s; background: #fff; }
		.o100-form-group input:focus { outline: none; border-color: <?php echo esc_attr( $primary_color ); ?>; box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.1); }
		
		/* Submit */
		.o100-auth-submit-btn { width: 100%; background: <?php echo esc_attr( $primary_color ); ?>; color: #fff !important; border: none; border-radius: 8px; padding: 12px; font-size: 15px; font-weight: 600; cursor: pointer; transition: opacity 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
		.o100-auth-submit-btn:hover { opacity: 0.9; }
		.o100-auth-submit-btn:disabled { opacity: 0.7; cursor: not-allowed; }
		@keyframes o100-spin-auth { 100% { transform: rotate(360deg); } }
		.o100-auth-spinner { animation: o100-spin-auth 1s linear infinite; display: inline-block; }
		
		/* Switch Links */
		.o100-auth-switch { text-align: center; font-size: 14px; color: #6b7280; border-top: 1px solid #f3f4f6; padding-top: 20px; margin-top: 20px; }
		.o100-auth-switch a { color: <?php echo esc_attr( $primary_color ); ?>; font-weight: 600; text-decoration: none; }
		.o100-auth-switch a:hover { text-decoration: underline; }
		</style>
		
		<script>
		(function() {
			// Ensure it runs even if loaded asynchronously
			function initAuthModal() {
				const overlay = document.getElementById('o100-auth-modal-overlay');
				
				// Open Modal Handler (Event Delegation)
				document.addEventListener('click', function(e) {
					const trigger = e.target.closest('.o100-login-trigger');
					if (trigger) {
						e.preventDefault();
						if (!overlay) {
							alert('Error: Auth modal overlay is missing from the page source. Please clear your cache and try again.');
							return;
						}
						
						// Dynamically update title and subtitle if clicked from a notice
						const nativeNotice = trigger.closest('.woocommerce-error, .woocommerce-message, .woocommerce-info');
						if (nativeNotice) {
							const noticeText = nativeNotice.innerText.replace('Login / Register', '').trim();
							const title = document.querySelector('.o100-auth-title');
							const subtitle = document.querySelector('.o100-auth-subtitle');
							
							if (title) title.innerText = '<?php echo esc_js( __( 'Login Required', 'order100' ) ); ?>';
							if (subtitle && noticeText) subtitle.innerText = noticeText;
							
							nativeNotice.style.display = 'none';
						}
						
						// Save pending promo to cookie if exists
						const promoId = trigger.getAttribute('data-pending-promo');
						if (promoId && promoId !== '0') {
							document.cookie = 'o100_pending_promo=' + promoId + '; path=/; max-age=3600';
						}

						overlay.style.display = 'flex';
						// Small delay for CSS transition
						setTimeout(() => overlay.classList.add('show'), 10);
						document.body.style.overflow = 'hidden';
					}
				});
				
				if (!overlay) return;

				const loginView = document.getElementById('o100-auth-login-view');
				const registerView = document.getElementById('o100-auth-register-view');
				const closeBtns = document.querySelectorAll('.o100-auth-close');
				
				// Close Modal Handler
				function closeModal() {
					overlay.classList.remove('show');
					setTimeout(() => {
						overlay.style.display = 'none';
						document.body.style.overflow = '';
					}, 300);
				}
				
				closeBtns.forEach(btn => btn.addEventListener('click', closeModal));
				overlay.addEventListener('click', function(e) {
					if (e.target === overlay) closeModal();
				});
				
				// Switch Views
				document.querySelectorAll('.o100-switch-to-register').forEach(el => {
					el.addEventListener('click', function(e) {
						e.preventDefault();
						if (loginView) loginView.style.display = 'none';
						if (registerView) registerView.style.display = 'block';
						document.querySelectorAll('.o100-auth-error').forEach(err => { err.style.display = 'none'; err.innerHTML = ''; });
					});
				});
				
				document.querySelectorAll('.o100-switch-to-login').forEach(el => {
					el.addEventListener('click', function(e) {
						e.preventDefault();
						if (registerView) registerView.style.display = 'none';
						if (loginView) loginView.style.display = 'block';
						document.querySelectorAll('.o100-auth-error').forEach(err => { err.style.display = 'none'; err.innerHTML = ''; });
					});
				});
			
				// Handle Forms using jQuery for AJAX convenience
				if (typeof jQuery !== 'undefined') {
					function handleAuthSubmit($form) {
						const $btn = $form.find('button[type="submit"]');
						const $spinner = $btn.find('.o100-auth-spinner');
						const $error = $form.closest('.o100-auth-view').find('.o100-auth-error');
						
						$btn.prop('disabled', true);
						$spinner.show();
						$error.hide().html('');
						
						jQuery.ajax({
							url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
							type: 'POST',
							data: $form.serialize(),
							dataType: 'json',
							success: function(response) {
								if (response.success) {
									window.location.reload();
								} else {
									$btn.prop('disabled', false);
									$spinner.hide();
									let msg = response.data || 'Authentication failed. Please try again.';
									$error.html(msg).slideDown(200);
								}
							},
							error: function() {
								$btn.prop('disabled', false);
								$spinner.hide();
								$error.html('Server communication error. Please try again.').slideDown(200);
							}
						});
					}
					
					jQuery('#o100-login-form').on('submit', function(e) {
						e.preventDefault();
						handleAuthSubmit(jQuery(this));
					});
					
					jQuery('#o100-register-form').on('submit', function(e) {
						e.preventDefault();
						handleAuthSubmit(jQuery(this));
					});
				}
			}
			
			// Run immediately since script is in footer
			initAuthModal();

			// Auto-open logic for dynamic checkout notices
			if (typeof jQuery !== 'undefined') {
				function checkAndAutoOpenModal() {
					const autoTrigger = document.querySelector('.o100-login-trigger');
					if (autoTrigger) {
						autoTrigger.click();
					}
				}
				
				jQuery(document.body).on('updated_checkout', checkAndAutoOpenModal);
				
				// Also check on page load in case it was printed directly
				jQuery(document).ready(checkAndAutoOpenModal);
			}
		})();
		</script>
		<?php
	}

	public function handle_login() {
		// Nonce verification
		if ( ! isset( $_POST['woocommerce-login-nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['woocommerce-login-nonce'] ) ), 'woocommerce-login' ) ) {
			wp_send_json_error( __( 'Security check failed. Please refresh the page and try again.', 'order100' ) );
		}

		$username = ! empty( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
		$password = ! empty( $_POST['password'] ) ? $_POST['password'] : '';

		if ( empty( $username ) || empty( $password ) ) {
			wp_send_json_error( __( 'Please enter both username and password.', 'order100' ) );
		}

		$creds = array(
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => true,
		);

		$user = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $user ) ) {
			$message = $user->get_error_message();
			$message = str_replace( '<strong>' . esc_html( $username ) . '</strong>', '<strong>' . esc_html( $username ) . '</strong>', $message );
			// Strip HTML if it contains links to forgot password, or just send it as raw HTML
			wp_send_json_error( $message );
		} else {
			wp_set_current_user( $user->ID );
			wp_send_json_success( array( 'message' => __( 'Login successful.', 'order100' ) ) );
		}
	}

	public function handle_register() {
		if ( ! isset( $_POST['woocommerce-register-nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['woocommerce-register-nonce'] ) ), 'woocommerce-register' ) ) {
			wp_send_json_error( __( 'Security check failed. Please refresh the page and try again.', 'order100' ) );
		}

		if ( get_option( 'woocommerce_enable_myaccount_registration' ) !== 'yes' ) {
			wp_send_json_error( __( 'Registration is currently disabled.', 'order100' ) );
		}

		$email    = ! empty( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$username = ! empty( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
		$password = ! empty( $_POST['password'] ) ? $_POST['password'] : '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( __( 'Please provide a valid email address.', 'order100' ) );
		}

		if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) && empty( $username ) ) {
			wp_send_json_error( __( 'Please enter a valid account username.', 'order100' ) );
		}

		if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) && empty( $password ) ) {
			wp_send_json_error( __( 'Please enter an account password.', 'order100' ) );
		}

		// Ensure wc_create_new_customer is available
		if ( ! function_exists( 'wc_create_new_customer' ) ) {
			require_once WP_PLUGIN_DIR . '/woocommerce/includes/wc-user-functions.php';
		}

		$customer_id = wc_create_new_customer( $email, $username, $password );

		if ( is_wp_error( $customer_id ) ) {
			wp_send_json_error( $customer_id->get_error_message() );
		} else {
			// Log the user in
			wp_set_current_user( $customer_id );
			wp_set_auth_cookie( $customer_id, true );
			wp_send_json_success( array( 'message' => __( 'Registration successful.', 'order100' ) ) );
		}
	}
}


// TS: 20260120165030

// TS: 20260127175320
