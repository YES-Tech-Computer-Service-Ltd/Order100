<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class O100_Loyalty_Misc_Controller {

	public static function check_conversion_rate() {
			ob_clean();
			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
			}
			
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Permission denied' );
			}
	
			$settings = O100_Loyalty_DB::get_settings();
			if ( !empty($settings['conversion_points']) && !empty($settings['conversion_value']) ) {
				wp_send_json_success([
					'has_rule' => true,
					'points'   => intval($settings['conversion_points']),
					'value'    => floatval($settings['conversion_value'])
				]);
			}
	
			wp_send_json_success([
				'has_rule' => false
			]);
		}

	public static function handle_save_booster() {
			ob_clean();
			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
			}
			
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Permission denied' );
			}
	
			if ( ! class_exists( 'O100_Loyalty_DB' ) ) {
				wp_send_json_error( 'Loyalty Engine not loaded' );
			}
	
			$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;
			$action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : '';
			$points      = isset( $_POST['points'] ) ? intval( $_POST['points'] ) : 0;
			$status      = isset( $_POST['status'] ) ? intval( $_POST['status'] ) : 1;
	
			if ( empty( $action_type ) ) {
				wp_send_json_error( 'Missing action type' );
			}
	
			$name_map = [
				'signup'         => 'Account Sign Up',
				'product_review' => 'Product Review',
				'facebook_share' => 'Social Share (Facebook)',
				'twitter_share'  => 'Social Share (X/Twitter)',
				'whatsapp_share' => 'Social Share (WhatsApp)',
				'email_share'    => 'Social Share (Email)',
				'pickup_bonus'   => 'Pickup Bonus',
				'profile_bonus'  => 'Profile Completion',
				'preorder_bonus' => 'Pre-order Bonus'
			];
			$campaign_name = isset( $name_map[ $action_type ] ) ? $name_map[ $action_type ] : 'Quick Booster';
	
			// Check uniqueness: only 1 booster of each type
			if ( $campaign_id == 0 ) {
				$existing = O100_Loyalty_DB::get_campaigns();
				foreach ( $existing as $ec ) {
					if ( $ec->type === $action_type ) {
						wp_send_json_error( 'A rule for this Booster already exists. Please edit the existing rule instead.' );
					}
				}
			}
	
			$data = [
				'title'                  => $campaign_name,
				'description'            => 'Quick Booster for ' . $campaign_name,
				'type'                   => $action_type,
				'earn_config'            => wp_json_encode( [ 'earn_point' => $points ] ),
				'conditions'             => '[]',
				'condition_relationship' => 'and',
				'status'                 => $status ? 'active' : 'disabled',
				'is_show_way_to_earn'    => 1,
				'ordering'               => 0,
				'priority'               => 0,
			];
	
			if ( $campaign_id > 0 ) {
				$existing = O100_Loyalty_DB::get_campaign( $campaign_id );
				if ( $existing ) {
					$data['title'] = $existing->title;
					$data['description'] = $existing->description;
				}
			}
	
			try {
				if ( $campaign_id > 0 ) {
					O100_Loyalty_DB::update_campaign( $campaign_id, $data );
					wp_send_json_success( 'Saved' );
				} else {
					$new_id = O100_Loyalty_DB::insert_campaign( $data );
					if ( $new_id ) {
						wp_send_json_success( 'Saved' );
					} else {
						global $wpdb;
						wp_send_json_error( 'DB Error: ' . $wpdb->last_error );
					}
				}
			} catch ( Exception $e ) {
				wp_send_json_error( $e->getMessage() );
			}
		}

	public static function handle_create_referral_coupon() {
			ob_clean();
			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
			}
			if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Permission denied.' ] ); }
			if ( ! class_exists( 'O100_Promotions_DB' ) ) { require_once O100_PATH . 'core/promotions/engine/class-o100-promotions-db.php'; }
	
			$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
			if ( empty( $title ) ) { wp_send_json_error( [ 'message' => 'Coupon name is required.' ] ); }
	
			$discount_type  = sanitize_text_field( $_POST['discount_type'] ?? 'fixed' );
			$discount_value = floatval( $_POST['discount_value'] ?? 5 );
			$min_spend      = floatval( $_POST['min_spend'] ?? 0 );
			$individual_use = ( $_POST['individual_use'] ?? '0' ) === '1';
			$usage_limit    = intval( $_POST['usage_limit'] ?? 1 );
			$expiry_days    = intval( $_POST['expiry_days'] ?? 30 );
			$promo_code     = strtoupper( substr( sanitize_title( $title ), 0, 8 ) ) . '_' . wp_rand( 1000, 9999 );
	
			$action_config = wp_json_encode([ 'discount_type' => $discount_type, 'discount_value' => $discount_value, 'min_spend' => $min_spend, 'individual_use' => $individual_use ]);
			$end_date = $expiry_days > 0 ? gmdate( 'Y-m-d H:i:s', strtotime( '+' . $expiry_days . ' days' ) ) : null;
			$conditions = $min_spend > 0 ? wp_json_encode([['type'=>'cart_subtotal','operator'=>'gte','value'=>$min_spend]]) : '[]';
	
			$new_id = O100_Promotions_DB::insert([
				'source' => 'loyalty', 'title' => $title, 'description' => sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) ),
				'rule_type' => 'simple', 'action_config' => $action_config, 'apply_to' => 'all_products', 'apply_to_items' => '[]',
				'promo_code' => $promo_code, 'conditions' => $conditions,
				'usage_limit' => $usage_limit, 'status' => 'active', 'priority' => 10,
				'is_exclusive' => $individual_use ? 1 : 0, 'end_date' => $end_date,
			]);
	
			if ( $new_id ) {
				$sum = $discount_type === 'percentage' ? $discount_value . '%' : '$' . $discount_value;
				wp_send_json_success([ 'id' => $new_id, 'title' => $title . ' (' . $sum . ')' ]);
			}
			wp_send_json_error([ 'message' => 'Database insert failed.' ]);
		}

	public static function handle_search_categories() {
			$term = sanitize_text_field( wp_unslash( $_POST['term'] ?? '' ) );
			if ( strlen( $term ) < 2 ) {
				wp_send_json_success( [] );
			}
			$cats = get_terms( [
				'taxonomy'   => 'product_cat',
				'name__like' => $term,
				'hide_empty' => false,
				'number'     => 20,
			] );
			$results = [];
			if ( ! is_wp_error( $cats ) ) {
				foreach ( $cats as $cat ) {
					$results[] = [ 'id' => $cat->term_id, 'text' => $cat->name ];
				}
			}
			wp_send_json_success( $results );
		}

}
