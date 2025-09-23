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
        add_filter( 'request', [$this, 'request'], 10 );
        add_filter( 'post_type_link', [$this, 'post_type_link'], 10, 2);
        add_filter( 'redirect_canonical', [$this, 'redirect_canonical'] );
    }

    public function redirect_canonical( string $redirect_url ) : string {
        if( ! is_singular( 'impeka-survey' ) ) {
           return $redirect_url;
        } 

        $post = get_queried_object();
        if( ! ( $post instanceof WP_Post ) ) {
            return $redirect_url;
        }

        $target = home_url( sprintf( '/survey/%s/', $post->ID ) );

        $req = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
        if( stripos( $req, sprintf( '/survey/%s/', $post->ID ) ) === false ) {
            return $target;
        }

        return $redirect_url;
    }

    public function post_type_link( string $url, \WP_Post $post ) : string {
        if( $post->post_type !== 'impeka-survey' ) {
            return $url;
        } 

        return home_url( sprintf( '/survey/%s/', $post->ID ) );
    }

    public function request( array $vars ) : array {
        if( ! empty( $vars['impeka_survey_id'] ) ) {
            $id = (int) $vars['impeka_survey_id'];
            $vars['p'] = $id;
            $vars['post_type'] = 'impeka-survey';
            unset( $vars['impeka_survey_id'] );
        }

        return $vars;
    }

    public function rewrite_rules() : void {
        add_rewrite_tag( '%impeka_survey_id%', '([0-9]+)' );
        add_rewrite_rule( '^survey/([0-9]+)/?$', 'index.php?impeka_survey_id=$matches[1]', 'top' );
    }

    public function select_template( string $template ) : string {
        if( ! is_singular( 'impeka-survey' ) ) {
            return $template;
        }

        $theme_template = locate_template( [
            'archive-impeka-survey.php',
            'templates/archive-impeka-survey.php'
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