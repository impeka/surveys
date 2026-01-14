<?php

namespace Impeka\Surveys;

if ( ! defined( 'ABSPATH' ) ) { exit; }

if( class_exists( RequestSubmitSurvey::class ) ) {
    return;
}

class RequestSubmitSurvey {
    private static ?RequestSubmitSurvey $_instance = null;

    private function __construct() {
        add_action( 'gform_after_submission', [$this, 'associate_submission'], 10, 2 );
        add_filter( 'gform_notification', [$this, 'add_client_info_to_notification'], 10, 3 );
    }

    public function associate_submission( array $entry, array $form ) : void {
        global $post;

        if( $post->post_type != 'impeka-survey' ) {
            return;
        }

        $survey_form_id = get_field( 'form_id', $post );

        if( 
            ! $survey_form_id 
            || $survey_form_id != $form['id']
        ) {
            return;
        }

        update_post_meta( $post->ID, '_entry_id', $entry['id'] );
    }

    public function add_client_info_to_notification( array $notification, array $form, array $entry ) : array {

        if( isset( $notification['toType'] ) && $notification['toType'] !== 'email' ) {
            return $notification;
        }

        $survey_id = $this->_get_survey_post_id_from_request();
        if( ! $survey_id ) {
            $survey_id = $this->_get_survey_post_id_from_entry_id( (int) ( $entry['id'] ?? 0 ) );
        }

        if( ! $survey_id ) {
            return $notification;
        }

        $survey_form_id = (int) get_post_meta( $survey_id, 'form_id', true );
        if( $survey_form_id && $survey_form_id !== (int) ( $form['id'] ?? 0 ) ) {
            return $notification;
        }

        $client_name  = (string) get_post_meta( $survey_id, 'client_name', true );
        $client_email = (string) get_post_meta( $survey_id, 'client_email', true );
        $reference_id = (string) get_post_meta( $survey_id, 'reference_id', true );
        $notes        = (string) get_post_meta( $survey_id, 'notes', true );

        $message_format = (string) ( $notification['message_format'] ?? '' );
        $is_html = $message_format === 'html' || (bool) ( $notification['isHtml'] ?? false ) || (bool) ( $notification['isHTML'] ?? false );

        $items = [
            [ __( 'Client Name', 'impeka-surveys' ), $client_name ],
            [ __( 'Client Email', 'impeka-surveys' ), $client_email ],
        ];

        if( $reference_id !== '' ) {
            $items[] = [ __( 'Reference ID', 'impeka-surveys' ), $reference_id ];
        }

        if( $notes !== '' ) {
            $items[] = [ __( 'Notes', 'impeka-surveys' ), $notes, 'multiline' ];
        }

        $original_message = (string) ( $notification['message'] ?? '' );

        if( $is_html ) {
            $client_block = '<h3>' . esc_html__( 'Client Information', 'impeka-surveys' ) . '</h3>';
            $client_block .= '<ul>';
            foreach( $items as $item ) {
                $label = (string) ( $item[0] ?? '' );
                $value = (string) ( $item[1] ?? '' );
                $client_block .= '<li><strong>' . esc_html( $label ) . ':</strong> ' . nl2br( esc_html( $value ) ) . '</li>';
            }
            $client_block .= '</ul><hr />';

            $notification['message'] = $client_block . $original_message;
        } else {
            $client_block = __( 'Client Information', 'impeka-surveys' ) . "\n";
            foreach( $items as $item ) {
                $label = (string) ( $item[0] ?? '' );
                $value = (string) ( $item[1] ?? '' );

                if( ( $item[2] ?? '' ) === 'multiline' ) {
                    $client_block .= $label . ":\n" . sanitize_textarea_field( $value ) . "\n";
                } else {
                    $client_block .= $label . ': ' . sanitize_text_field( $value ) . "\n";
                }
            }

            $client_block .= "------------------------------\n\n";

            $notification['message'] = $client_block . ltrim( $original_message, "\r\n" );
        }

        return $notification;
    }

    private function _get_survey_post_id_from_request() : int {

        $post_id = 0;

        if( function_exists( 'is_singular' ) && is_singular( 'impeka-survey' ) ) {
            $post_id = (int) get_queried_object_id();
        }

        if( ! $post_id ) {
            global $post;
            if( isset( $post ) && $post instanceof \WP_Post && $post->post_type === 'impeka-survey' ) {
                $post_id = (int) $post->ID;
            }
        }

        if( ! $post_id ) {
            $req = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
            if( preg_match( '#/survey/([0-9]+)-[0-9a-zA-Z]+/?#', $req, $m ) ) {
                $post_id = (int) $m[1];
            }
        }

        if( $post_id && get_post_type( $post_id ) === 'impeka-survey' ) {
            return $post_id;
        }

        return 0;
    }

    private function _get_survey_post_id_from_entry_id( int $entry_id ) : int {
        if( ! $entry_id ) {
            return 0;
        }

        $posts = get_posts( [
            'post_type'        => 'impeka-survey',
            'post_status'      => 'any',
            'fields'           => 'ids',
            'meta_key'         => '_entry_id',
            'meta_value'       => (string) $entry_id,
            'posts_per_page'   => 1,
            'no_found_rows'    => true,
            'suppress_filters' => true,
        ] );

        return $posts ? (int) $posts[0] : 0;
    }

    public static function process() : void {
        if( is_null( self::$_instance ) ) {
            new self();
        }
    }
}
