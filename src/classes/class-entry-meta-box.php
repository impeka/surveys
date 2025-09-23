<?php

namespace Impeka\Surveys;

if ( ! defined( 'ABSPATH' ) ) { exit; }

if( class_exists( EntryMetaBox::class ) ) {
    return;
}

class EntryMetaBox {
    private static ?EntryMetaBox $_instance = null;

    private function __construct() {
        add_action( 'add_meta_boxes_impeka-survey', [$this, 'add_meta_box_maybe'], 10, 1 );
    }

    public function add_meta_box_maybe( \WP_Post $post ) : void {

        $entry_id = get_post_meta( $post->ID, '_entry_id', true );
        if( ! $entry_id ) {
            return;
        }

        if ( ! class_exists( 'GFAPI' ) ) {
            return;
        }

        add_meta_box(
            'survey_entry',
            __( 'Survey Answers', 'impeka-surveys' ),
            [$this, 'populate_meta_box'],
            'impeka-survey',
            'normal',
            'default',
            $post
        );
    }

    public function populate_meta_box( \WP_Post $post ) : void {

        $entry_id = get_post_meta( $post->ID, '_entry_id', true );
        
        if( ! $entry_id ) {//this is a fallback in case the metabox appears
            echo '<p>' . esc_html__( 'No Gravity Forms entry is linked to this post.', 'impeka-surveys' ) . '</p>';
            return;
        }

        if( ! class_exists( 'GFAPI' ) ) {
            echo '<p>' . esc_html__( 'Gravity Forms is not active. Activate it to view the linked entry.', 'impeka-surveys' ) . '</p>';
            return;
        }

        $entry = \GFAPI::get_entry( $entry_id );
        if( is_wp_error( $entry ) ) {
            echo '<p>' . esc_html( sprintf( __( 'Entry %d not found, it may have been deleted.', 'impeka-surveys' ), $entry_id ) ) . '</p>';
            return;
        }

        $form = \GFAPI::get_form( $entry['form_id'] );
        if( ! $form ) {
            echo '<p>' . esc_html( sprintf( __( 'The survey form (ID: %d) cannot be found it may have been deleted.', 'impeka-surveys' ), (int) $entry['form_id'] ) ) . '</p>';
            return;
        }

        $entry_url = add_query_arg(
            ['page' => 'gf_entries', 'view' => 'entry', 'id' => $form['id'], 'lid' => $entry_id],
            admin_url( 'admin.php' )
        );

        echo '<p><a class="button" href="' . esc_url( $entry_url ) . '">' . esc_html__( 'Open in Gravity Forms', 'impeka-surveys' ) . '</a></p>';

        $html = \GFCommon::replace_variables( '{all_fields:admin}', $form, $entry );

        echo wp_kses_post( $html );
    }

    public static function boot() : void {
        if( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
    }
}