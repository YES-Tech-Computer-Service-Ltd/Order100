<?php

namespace Order100\Notification\Engine\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\WC_Email_O100_Dynamic' ) ) {
	class WC_Email_O100_Dynamic extends \WC_Email {
		public function __construct( $id, $title ) {
			$this->id             = $id;
			$this->title          = $title;
			$this->customer_email = true;
			$this->description    = __( 'Custom template migrated or created dynamically.', 'order100' );
			$this->placeholders   = [ '{site_title}' => get_bloginfo( 'name' ) ];
			parent::__construct();
		}
		public function get_default_subject() { return $this->title; }
		public function get_default_heading() { return $this->title; }
	}
}
