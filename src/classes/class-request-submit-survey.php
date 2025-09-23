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

    public static function process() : void {
        if( is_null( self::$_instance ) ) {
            new self();
        }
    }
}