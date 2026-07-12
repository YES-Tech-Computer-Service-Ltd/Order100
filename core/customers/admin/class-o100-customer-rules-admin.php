<?php
/**
 * Customer Rules Admin Controller
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Customer_Rules_Admin {

	public static function init() {
		add_action( 'wp_ajax_o100_crm_save_rule', [ __CLASS__, 'ajax_save_rule' ] );
		add_action( 'wp_ajax_o100_crm_delete_rule', [ __CLASS__, 'ajax_delete_rule' ] );
		add_action( 'wp_ajax_o100_crm_toggle_rule_status', [ __CLASS__, 'ajax_toggle_rule_status' ] );
	}

	public static function ajax_save_rule() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active';
		$target_type = isset( $_POST['target_type'] ) ? sanitize_text_field( wp_unslash( $_POST['target_type'] ) ) : 'tags';
		$target_ids = isset( $_POST['target_ids'] ) ? wp_unslash( $_POST['target_ids'] ) : '[]';
		$privileges = isset( $_POST['privileges'] ) ? wp_unslash( $_POST['privileges'] ) : '{}';
		$restrictions = isset( $_POST['restrictions'] ) ? wp_unslash( $_POST['restrictions'] ) : '{}';
		$priority = isset( $_POST['priority'] ) ? intval( $_POST['priority'] ) : 0;

		if ( empty( $title ) ) {
			wp_send_json_error( [ 'message' => 'Rule title is required.' ] );
		}

		$data = [
			'title' => $title,
			'status' => $status,
			'target_type' => $target_type,
			'target_ids' => $target_ids,
			'privileges' => $privileges,
			'restrictions' => $restrictions,
			'priority' => $priority
		];

		if ( $id > 0 ) {
			$result = O100_Customer_Rules_DB::update_rule( $id, $data );
			if ( $result ) {
				wp_send_json_success( [ 'message' => 'Rule updated successfully.', 'id' => $id ] );
			} else {
				wp_send_json_error( [ 'message' => 'Failed to update rule.' ] );
			}
		} else {
			$inserted_id = O100_Customer_Rules_DB::insert_rule( $data );
			if ( $inserted_id ) {
				wp_send_json_success( [ 'message' => 'Rule created successfully.', 'id' => $inserted_id ] );
			} else {
				wp_send_json_error( [ 'message' => 'Failed to create rule.' ] );
			}
		}
	}

	public static function ajax_delete_rule() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $id <= 0 ) {
			wp_send_json_error( [ 'message' => 'Invalid ID.' ] );
		}

		$deleted = O100_Customer_Rules_DB::delete_rule( $id );
		if ( $deleted ) {
			wp_send_json_success( [ 'message' => 'Rule deleted successfully.' ] );
		} else {
			wp_send_json_error( [ 'message' => 'Failed to delete rule.' ] );
		}
	}

	public static function ajax_toggle_rule_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
		
		if ( $id <= 0 || ! in_array( $status, ['active', 'inactive'] ) ) {
			wp_send_json_error( [ 'message' => 'Invalid data.' ] );
		}

		$result = O100_Customer_Rules_DB::update_rule( $id, [ 'status' => $status ] );
		if ( $result ) {
			wp_send_json_success( [ 'message' => 'Status updated.' ] );
		} else {
			wp_send_json_error( [ 'message' => 'Failed to update status.' ] );
		}
	}

}

O100_Customer_Rules_Admin::init();
