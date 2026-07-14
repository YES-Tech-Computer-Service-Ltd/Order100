<?php

namespace Order100\Notification\Engine\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DynamicEmails {
	public static function init() {
		add_filter( 'woocommerce_email_classes', [ __CLASS__, 'register_dynamic_classes' ], 999 );
	}

	public static function register_dynamic_classes( $emails ) {
		// Only run if the environment is fully loaded
		if ( ! function_exists( 'get_posts' ) ) return $emails;

		$templates = get_posts( [
			'post_type'      => 'o100_template',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		] );

		if ( empty( $templates ) ) return $emails;

		require_once __DIR__ . '/class-wc-email-o100-dynamic.php';

		foreach ( $templates as $t ) {
			$id = $t->post_name;
			
			// Check if this email class is already registered
			$exists = false;
			foreach ( $emails as $class => $instance ) {
				if ( is_object( $instance ) && isset( $instance->id ) && $instance->id === $id ) {
					$exists = true;
					break;
				}
			}

			if ( ! $exists ) {
				// It's missing! Register a dynamic dummy class so the editor doesn't crash!
				$class_name = 'WC_Email_O100_Dynamic_' . md5( $id );
				$emails[ $class_name ] = new WC_Email_O100_Dynamic( $id, $t->post_title );
			}
		}

		return $emails;
	}
}

DynamicEmails::init();
