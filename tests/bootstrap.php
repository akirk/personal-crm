<?php
/**
 * PHPUnit bootstrap — WordPress stubs for running tests outside of WordPress.
 */

// WordPress constants
if ( ! defined( 'ARRAY_A' ) ) define( 'ARRAY_A', 'ARRAY_A' );
if ( ! defined( 'OBJECT' ) )  define( 'OBJECT', 'OBJECT' );
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/../' );

// WordPress function stubs
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql' ) { return date( 'Y-m-d H:i:s' ); }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://example.com' . $path; }
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) { return true; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) { return trim( $str ); }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) { return trim( $str ); }
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) { return $email; }
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) { return strtolower( preg_replace( '/[^a-z0-9-]/i', '-', $title ) ); }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) { return json_encode( $data ); }
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) { return $text; }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback ) {}
}
if ( ! function_exists( 'wp_register_ability_category' ) ) {
	function wp_register_ability_category( $slug, $args ) {}
}
if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( $slug, $args ) {}
}

// WP_Error stub
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;
		public function __construct( $code, $message ) {
			$this->code    = $code;
			$this->message = $message;
		}
	}
}

require_once __DIR__ . '/../vendor/autoload.php';
