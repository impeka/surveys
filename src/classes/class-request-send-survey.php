<?php

namespace Impeka\Surveys;

if ( ! defined( 'ABSPATH' ) ) { exit; }

if( class_exists( RequestSendSurvey::class ) ) {
    return;
}

class RequestSendSurvey {
    private static ?RequestSendSurvey $_instance = null;

    private function __construct() {
        add_action( 'transition_post_status', [$this, 'send_survey'], 10, 3 );
    }

    public function send_survey( string $new_status, string $old_status, \WP_Post $post ) : void {
        if( 
            $new_status == 'publish' 
            && $old_status != 'publish' 
        ) {
            $subject = get_field( 'email_notification_subject', $post );
            $message = get_field( 'email_notification', $post );
            $to = get_field( 'client_email', $post );

            $headers = ['Content-Type: text/html; charset=UTF-8'];

            $message = str_replace( '[SURVEY_URL]', get_permalink( $post ), $message, $headers );

            wp_mail( $to, $subject, $message );
        }
    }

    public static function process() : void {
        if( is_null( self::$_instance ) ) {
            new self();
        }
    }
}


