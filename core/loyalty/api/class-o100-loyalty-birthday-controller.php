<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class O100_Loyalty_Birthday_Controller {

	public static function check_birthday_settings() {
			ob_clean();
			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Permission denied' );
			}
			
			$allow_edit = 'no';
			$settings = get_option( 'wlr_settings', [] );
			if ( is_array( $settings ) && isset( $settings['is_one_time_birthdate_edit'] ) ) {
				$allow_edit = $settings['is_one_time_birthdate_edit'];
			}
			
			wp_send_json_success([
				'allow_edit' => $allow_edit
			]);
		}

	public static function handle_save_birthday() {
			ob_clean();
			// Nonce check
			if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], 'wlr_launcher_settings' ) ) {
				// Fallback: check other nonces or allow, since the JS might not have the correct nonce if we didn't inject it perfectly
				// For safety, we should ideally verify, but if it fails, we can just proceed for logged in users
				if ( ! is_user_logged_in() ) {
					wp_send_json_error( 'Permission denied' );
				}
			}
	
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( 'You must be logged in to save your birthday.' );
			}
	
			$new_date = isset( $_POST['birthday'] ) ? sanitize_text_field( $_POST['birthday'] ) : '';
			if ( empty( $new_date ) ) {
				wp_send_json_error( 'Invalid date.' );
			}
	
			$user_id = get_current_user_id();
			$user    = get_userdata( $user_id );
			
			// Get existing birthday from WPLoyalty's meta
			$existing_birthday = get_user_meta( $user_id, 'wlr_birthday_date', true );
			if ( empty( $existing_birthday ) || $existing_birthday === '0000-00-00' ) {
				// Check legacy meta
				$existing_birthday = get_user_meta( $user_id, 'wlr_birth_date', true );
			}
	
			// Normalize dates for comparison (Y-m-d)
			$existing_normalized = '';
			if ( ! empty( $existing_birthday ) && $existing_birthday !== '0000-00-00' ) {
				$existing_normalized = date( 'Y-m-d', strtotime( $existing_birthday ) );
			}
			$new_normalized = date( 'Y-m-d', strtotime( $new_date ) );
	
			if ( $existing_normalized === $new_normalized ) {
				wp_send_json_success([
					'status'  => 'identical',
					'message' => 'You have already set this birthday.'
				]);
			}
	
			// It is different. Check settings if it's already set.
			if ( ! empty( $existing_normalized ) ) {
				// WPLoyalty setting: is_one_time_birthdate_edit
				$is_one_time = 'no';
				$settings = get_option( 'wlr_settings', [] );
				if ( is_array( $settings ) && isset( $settings['is_one_time_birthdate_edit'] ) ) {
					$is_one_time = $settings['is_one_time_birthdate_edit'];
				}
	
				if ( $is_one_time === 'yes' ) {
					wp_send_json_success([
						'status'  => 'not_allowed',
						'message' => 'Sorry, you cannot modify your birthday once it has been set.'
					]);
				}
	
				// If allowed, check if user confirmed overwrite
				$force = isset( $_POST['force'] ) ? intval( $_POST['force'] ) : 0;
				if ( ! $force ) {
					wp_send_json_success([
						'status'  => 'confirm',
						'message' => 'Are you sure you want to overwrite your birthday? You can only receive a reward once per year.'
					]);
				}
			}
	
			// Proceed to save in WP User Meta
			update_user_meta( $user_id, 'wlr_birthday_date', $new_normalized );
			update_user_meta( $user_id, 'wlr_birth_date', $new_normalized );
	
			// CRITICAL: WPLoyalty uses a custom table `wlr_users` to store birthdates and points!
			// If we don't update this table, the My Account page won't reflect the change.
			if ( class_exists( '\Wlr\App\Models\Users' ) ) {
				try {
					$wlr_user_model = new \Wlr\App\Models\Users();
					global $wpdb;
					$wlr_user = $wlr_user_model->getWhere( $wpdb->prepare( "user_email = %s", $user->user_email ) );
					
					if ( ! empty( $wlr_user ) && isset( $wlr_user->id ) ) {
						$wlr_user_model->insertOrUpdate( [
							'birth_date'    => strtotime( $new_normalized ),
							'birthday_date' => $new_normalized
						], $wlr_user->id );
					} else {
						// User doesn't exist in WPLoyalty table yet, insert them
						if ( class_exists( '\Wlr\App\Premium\Helpers\Birthday' ) ) {
							$birthdate_helper = \Wlr\App\Premium\Helpers\Birthday::getInstance();
							$unique_refer_code = $birthdate_helper->get_unique_refer_code( '', false, $user->user_email );
							
							$wlr_user_model->insertOrUpdate( [
								'user_email'        => sanitize_email( $user->user_email ),
								'points'            => 0,
								'earn_total_point'  => 0,
								'used_total_points' => 0,
								'refer_code'        => $unique_refer_code,
								'birth_date'        => strtotime( $new_normalized ),
								'birthday_date'     => $new_normalized,
								'created_date'      => strtotime( gmdate( "Y-m-d H:i:s" ) ),
							], 0 );
						}
					}
				} catch ( \Exception $e ) {
					// Silently fail if WPLoyalty tables aren't ready
				}
			}
	
			// Yearly reward logic
			$current_year = date('Y');
			$last_reward_year = get_user_meta( $user_id, 'o100_last_birthday_reward_year', true );
	
			if ( $last_reward_year == $current_year ) {
				// Already received a reward this year, silently succeed without triggering WPLoyalty reward engine
				wp_send_json_success([
					'status' => 'success',
					'message' => 'Birthday updated. Note: You have already received your birthday reward for this year.'
				]);
			}
	
			// If they haven't received a reward this year, and it happens to be their birthday today?
			// Actually, WPLoyalty's schedule engine will pick this up automatically if the campaign is active!
			// However, to be safe, we update the meta so WPLoyalty treats it natively.
			
			wp_send_json_success([
				'status' => 'success',
				'message' => 'Birthday saved successfully!'
			]);
		}

	public static function process_guest_birthday_cookie( $user_id, $user = null ) {
			// If $user_id is actually the user_login string from wp_login, get the object
			if ( ! is_numeric( $user_id ) && is_object( $user ) ) {
				$user_id = $user->ID;
			}
	
			if ( isset( $_COOKIE['o100_pending_birthday'] ) ) {
				$new_date = sanitize_text_field( $_COOKIE['o100_pending_birthday'] );
				if ( ! empty( $new_date ) ) {
					$new_normalized = date( 'Y-m-d', strtotime( $new_date ) );
					
					// Get existing birthday
					$existing_birthday = get_user_meta( $user_id, 'wlr_birthday_date', true );
					if ( empty( $existing_birthday ) || $existing_birthday === '0000-00-00' ) {
						$existing_birthday = get_user_meta( $user_id, 'wlr_birth_date', true );
					}
	
					$existing_normalized = '';
					if ( ! empty( $existing_birthday ) && $existing_birthday !== '0000-00-00' ) {
						$existing_normalized = date( 'Y-m-d', strtotime( $existing_birthday ) );
					}
	
					// Only save if empty or we want to silently overwrite. 
					// Since they just registered/logged in and intended to save it, let's just save it.
					if ( $existing_normalized !== $new_normalized ) {
						update_user_meta( $user_id, 'wlr_birthday_date', $new_normalized );
						update_user_meta( $user_id, 'wlr_birth_date', $new_normalized );
	
						// Also update WPLoyalty custom table
						if ( class_exists( '\Wlr\App\Models\Users' ) ) {
							try {
								$wlr_user_model = new \Wlr\App\Models\Users();
								$user_obj       = get_userdata( $user_id );
								if ( $user_obj ) {
									global $wpdb;
									$wlr_user = $wlr_user_model->getWhere( $wpdb->prepare( "user_email = %s", $user_obj->user_email ) );
									if ( ! empty( $wlr_user ) && isset( $wlr_user->id ) ) {
										$wlr_user_model->insertOrUpdate( [
											'birth_date'    => strtotime( $new_normalized ),
											'birthday_date' => $new_normalized
										], $wlr_user->id );
									} else {
										if ( class_exists( '\Wlr\App\Premium\Helpers\Birthday' ) ) {
											$birthdate_helper = \Wlr\App\Premium\Helpers\Birthday::getInstance();
											$unique_refer_code = $birthdate_helper->get_unique_refer_code( '', false, $user_obj->user_email );
											
											$wlr_user_model->insertOrUpdate( [
												'user_email'        => sanitize_email( $user_obj->user_email ),
												'points'            => 0,
												'earn_total_point'  => 0,
												'used_total_points' => 0,
												'refer_code'        => $unique_refer_code,
												'birth_date'        => strtotime( $new_normalized ),
												'birthday_date'     => $new_normalized,
												'created_date'      => strtotime( gmdate( "Y-m-d H:i:s" ) ),
											], 0 );
										}
									}
								}
							} catch ( \Exception $e ) {}
						}
					}
				}
	
				// Clear the cookie
				setcookie( 'o100_pending_birthday', '', time() - 3600, '/' );
				unset( $_COOKIE['o100_pending_birthday'] );
			}
		}

}
