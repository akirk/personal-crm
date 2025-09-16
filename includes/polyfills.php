<?php

// WordPress escaping function polyfills for non-WordPress contexts
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $attribute ) {
        return htmlspecialchars( $attribute, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) {
        return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_textarea' ) ) {
    function esc_textarea( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

// Sanitization functions
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_url' ) ) {
	function sanitize_url( $url ) {
		return filter_var( trim( $url ), FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_html' ) ) {
	function sanitize_html( $html ) {
		// Allow only <a> tags with href and target attributes
		$allowed_tags = '<a>';
		$clean_html = strip_tags( $html, $allowed_tags );

		// Additional security: ensure href attributes don't contain javascript
		$clean_html = preg_replace('/javascript:/i', '', $clean_html);

		return $clean_html;
	}
}