<?php

namespace Impeka\Surveys;

if ( ! defined( 'ABSPATH' ) ) { exit; }

if( class_exists( RequestSendSurvey::class ) ) {
    return;
}

class RequestSendSurvey {
    private static ?RequestSendSurvey $_instance = null;

    private function __construct() {
        add_action( 'acf/save_post', [$this, 'send_survey_after_creation'], 20 );
    }

    public function send_survey_after_creation( int|string $post_id ) : void {
        $post_status = get_post_status( $post_id );
        if( $post_status !== 'publish') {
            return;
        }

        $old_post_status = isset( $_POST['original_post_status'] ) ? sanitize_text_field( $_POST['original_post_status'] ) : '';
        
        if( $old_post_status === 'publish' ) {
            return;
        }

        if( get_post_meta( $post_id, '_survey_is_sent', true ) ) {
            return;
        }

        $this->_send_survey( $post_id );
        update_post_meta( $post_id, '_survey_is_sent', true );
    }

    public function send_survey_after_update( string $new_status, string $old_status, \WP_Post $post ) : void {
        if( 
            $new_status == 'publish' 
            && $old_status != 'publish' 
        ) {
            $this->_send_survey( $post->ID );
        }
    }

    private function _send_survey( int|string $post_id ) : void {
        $subject = get_field( 'email_notification_subject', $post_id );
        $message = get_field( 'email_notification', $post_id );
        $to = get_field( 'client_email', $post_id );

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $message = str_replace( '[SURVEY_URL]', get_permalink( $post_id ), $message );

        wp_mail( $to, $subject, $message, $headers );
    }

    public static function process() : void {
        if( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
    }
}


