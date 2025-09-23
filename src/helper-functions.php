<?php

namespace Impeka\Surveys;

if ( ! defined( 'ABSPATH' ) ) { exit; }

function display_survey() : void {
    $form_id = get_field( 'form_id' );

    if( ! $form_id ) {
        return;
    }

    if( ! class_exists( '\GFAPI' ) ) {
        return;
    }

    $entry_id = get_post_meta( get_the_ID(), '_entry_id', true );

    if( ! $entry_id ) {
        gravity_form( $form_id, true, false, false, null, true, 0, $echo = true );
        return;
    }
    
    $entry = \GFAPI::get_entry( $entry_id );
    $form = \GFAPI::get_form( $form_id );

    $confirmation_obj = null;
    foreach( $form['confirmations'] as $c ) {
        if( ! empty( $c['isDefault'] ) ) { 
            $confirmation_obj = $c; 
            break; 
        }
    }

    if( 
        $confirmation_obj 
        && $confirmation_obj['type'] === 'message' 
    ) {
        $html = \GFCommon::replace_variables( $confirmation_obj['message'], $form, $entry );
        echo wp_kses_post( $html );
    }
}