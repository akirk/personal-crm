<?php

namespace PersonalCRM;

abstract class LocalLLMProvider {
    abstract public function get_name();
    abstract public function get_default_host();
    abstract public function get_base_url();
    abstract public function get_website();
    abstract public function get_models_path();
    abstract public function get_chat_path();
    abstract public function get_models_key();
    abstract public function normalize_model( $model );

    public function get_models_url() {
        return $this->get_base_url() . $this->get_models_path();
    }

    public function get_chat_url() {
        return $this->get_base_url() . $this->get_chat_path();
    }

    public function list_models() {
        $response = wp_remote_get( $this->get_models_url(), [
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

        $raw_models = $data[ $this->get_models_key() ] ?? [];
        $models = array_map( [ $this, 'normalize_model' ], $raw_models );

        return [
            'success' => true,
            'models'  => $models,
        ];
    }

    public function is_available() {
        $result = $this->list_models();
        return $result['success'];
    }
}

class OllamaProvider extends LocalLLMProvider {
    public function get_name() {
        return 'Ollama';
    }

    public function get_default_host() {
        return 'localhost:11434';
    }

    public function get_base_url() {
        $custom_host = get_option( 'personal_crm_local_llm_host', '' );
        $host = $custom_host ?: $this->get_default_host();
        $base_url = 'http://' . preg_replace( '#^https?://#', '', $host );
        return apply_filters( 'personal_crm_local_llm_base_url', $base_url, 'ollama' );
    }

    public function get_website() {
        return 'https://ollama.ai';
    }

    public function get_models_path() {
        return '/api/tags';
    }

    public function get_chat_path() {
        return '/api/chat';
    }

    public function get_models_key() {
        return 'models';
    }

    public function normalize_model( $model ) {
        return [ 'name' => $model['name'] ?? $model ];
    }
}

class LMStudioProvider extends LocalLLMProvider {
    public function get_name() {
        return 'LM Studio';
    }

    public function get_default_host() {
        return 'localhost:1234';
    }

    public function get_base_url() {
        $custom_host = get_option( 'personal_crm_local_llm_host', '' );
        $host = $custom_host ?: $this->get_default_host();
        $base_url = 'http://' . preg_replace( '#^https?://#', '', $host );
        return apply_filters( 'personal_crm_local_llm_base_url', $base_url, 'lm_studio' );
    }

    public function get_website() {
        return 'https://lmstudio.ai';
    }

    public function get_models_path() {
        return '/v1/models';
    }

    public function get_chat_path() {
        return '/v1/chat/completions';
    }

    public function get_models_key() {
        return 'data';
    }

    public function normalize_model( $model ) {
        return [ 'name' => $model['id'] ?? $model ];
    }
}

class LocalLLM {
    private static $providers = null;

    public static function get_providers() {
        if ( self::$providers === null ) {
            self::$providers = [
                'ollama'    => new OllamaProvider(),
                'lm_studio' => new LMStudioProvider(),
            ];
        }
        return self::$providers;
    }

    public static function get_provider_key() {
        return get_option( 'personal_crm_local_llm_provider', 'ollama' );
    }

    public static function get_provider( $key = null ) {
        $providers = self::get_providers();
        $key = $key ?? self::get_provider_key();
        return $providers[ $key ] ?? $providers['ollama'];
    }

    public static function get_base_url( $provider_key = null ) {
        return self::get_provider( $provider_key )->get_base_url();
    }

    public static function get_chat_url( $provider_key = null ) {
        return self::get_provider( $provider_key )->get_chat_url();
    }

    public static function list_models( $provider_key = null ) {
        return self::get_provider( $provider_key )->list_models();
    }

    public static function is_available( $provider_key = null ) {
        return self::get_provider( $provider_key )->is_available();
    }

    public static function get_model() {
        return get_option( 'personal_crm_local_llm_model', 'llama3.2' );
    }
}
