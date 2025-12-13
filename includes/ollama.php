<?php

namespace PersonalCRM;

class Ollama {
    private static $base_url = 'http://localhost:11434';

    public static function get_base_url() {
        return apply_filters( 'personal_crm_ollama_base_url', self::$base_url );
    }

    public static function list_models() {
        $url = self::get_base_url() . '/api/tags';

        $response = wp_remote_get( $url, [
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
                'code'    => 'connection_failed',
            ];
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return [
                'success' => false,
                'error'   => 'Invalid JSON response',
                'code'    => 'json_error',
            ];
        }

        return [
            'success' => true,
            'models'  => $data['models'] ?? [],
        ];
    }

    public static function is_available() {
        $result = self::list_models();
        return $result['success'];
    }
}
