<?php

namespace Impeka\Surveys;

if ( ! defined( 'ABSPATH' ) ) { exit; }

if( class_exists( TemplateManager::class ) ) {
    return;
}

class TemplateManager {
    private static ?TemplateManager $_instance = null;

    private function __construct() {
        add_action( 'init', [$this, 'rewrite_rules'] );

        add_filter( 'template_include', [$this, 'select_template'], 10 );
        add_filter( 'template_redirect', [$this, 'redirect_on_archive'], 10 );
        add_filter( 'request', [$this, 'request'], 10 );
        add_filter( 'post_type_link', [$this, 'post_type_link'], 10, 2);
        add_filter( 'redirect_canonical', [$this, 'redirect_canonical'] );
    }

    public function redirect_on_archive() : void {
        if( is_post_type_archive( 'impeka-survey' ) ) {
            wp_safe_redirect( home_url( '/' ) );
            exit;
        }
    }

    public function redirect_canonical( string $redirect_url ) : string {
        if( ! is_singular( 'impeka-survey' ) ) {
           return $redirect_url;
        } 

        $post = get_queried_object();
        if( ! ( $post instanceof WP_Post ) ) {
            return $redirect_url;
        }

        $target = home_url( sprintf( '/survey/%s-%s/', $post->ID, $this->_generate_token( $post->ID ) ) );

        $req = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
        if( stripos( $req, sprintf( '/survey/%s-%s/', $post->ID, $this->_generate_token( $post->ID ) ) ) === false ) {
            return $target;
        }

        return $redirect_url;
    }

    public function post_type_link( string $url, \WP_Post $post ) : string {
        if( $post->post_type !== 'impeka-survey' ) {
            return $url;
        } 

        return home_url( sprintf( '/survey/%s-%s/', $post->ID, $this->_generate_token( $post->ID ) ) );
    }

    public function request( array $vars ) : array {
        if( ! empty( $vars['impeka_survey_id'] ) ) {
            $id_parts = explode( '-', $vars['impeka_survey_id'] );

            if( $this->_generate_token( $id_parts[0] ) != $id_parts[1] ) {
                return ['error' => '404'];
            }

            $vars['p'] = $id_parts[0];
            $vars['post_type'] = 'impeka-survey';
            unset( $vars['impeka_survey_id'] );
        }

        return $vars;
    }

    private function _generate_token( string $id ) {
        $hash = hash( 'sha256', $id );
        $hash = substr( preg_replace( '/[^a-zA-Z0-9]/', '', $hash ), 0, 6 );
        return $hash;
    }

    public function rewrite_rules() : void {
        add_rewrite_tag( '%impeka_survey_id%', '([0-9]+-[0-9a-zA-Z]+)' );
        add_rewrite_rule( '^survey/([0-9]+-[0-9a-zA-Z]+)/?$', 'index.php?impeka_survey_id=$matches[1]', 'top' );
    }

    public function select_template( string $template ) : string {
        if( ! is_singular( 'impeka-survey' ) ) {
            return $template;
        }

        $theme_template = locate_template( [
            'single-impeka-survey.php',
            'templates/single-impeka-survey.php'
        ] );

        if( $theme_template ) {
            return $theme_template;
        }

        $plugin_fallback = Plugin::get_dir() . '/src/templates/single-impeka-survey.php';

        if( file_exists( $plugin_fallback ) ) {
            return $plugin_fallback;
        }

        return $template;
    }

    public static function boot() : void {
        if( is_null( self::$_instance ) ) {
            new self();
        }
    }
}