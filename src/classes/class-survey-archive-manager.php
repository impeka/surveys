<?php

namespace Impeka\Surveys;

if ( ! defined( 'ABSPATH' ) ) { exit; }

if( class_exists( SurveyArchiveManager::class ) ) {
    return;
}

class SurveyArchiveManager {
    private static ?SurveyArchiveManager $_instance = null;

    private function __construct() {
        add_action( 'manage_impeka-survey_posts_custom_column', [$this, 'populate_columns'], 10, 2 );
        add_action( 'pre_get_posts', [$this, 'order_sortable_columns'] );
        
        add_filter( 'impeka_survey_admin_columns_meta_keys', [$this, 'column_meta_keys'] );
        add_filter( 'manage_edit-impeka-survey_columns', [$this, 'column_headers'] );
        add_filter( 'manage_edit-impeka-survey_sortable_columns',  [$this, 'sortable_columns'] );
    }

    public function column_meta_keys( array $keys ) : array {
        return wp_parse_args($keys, [
            'entry_id'     => '_entry_id', 
            'client_name'  => 'client_name',
            'reference_id' => 'reference_id',
        ]);
    }

    public function column_headers( array $columns ) : array {
        $new = [];
        foreach( $columns as $k => $v ) {
            $new[$k] = $v;
            if( $k === 'title' ) {
                $new['impeka_entry']  = __( 'Entry', 'my-plugin' );
                $new['impeka_client'] = __( 'Client', 'my-plugin' );
                $new['impeka_ref']    = __( 'Reference ID', 'my-plugin' );
            }
        }
        return $new;
    }

    public function populate_columns( $column, $post_id ) : void {
        if( $column === 'impeka_entry' ) {
            $entry_id = (int) get_post_meta( $post_id, '_entry_id', true );
            if( ! $entry_id ) { 
                echo '—'; return; 
            }

            if( class_exists( 'GFAPI' ) ) {
                static $cache = [];
                $entry = $cache[$entry_id] ?? \GFAPI::get_entry( $entry_id );
                $cache[$entry_id] = $entry;

                if( 
                    ! is_wp_error( $entry ) 
                    && is_array( $entry ) 
                ) {
                    $form_id = (int) $entry['form_id'];
                    $url = add_query_arg(
                        ['page' => 'gf_entries', 'view' => 'entry', 'id' => $form_id, 'lid' => $entry_id],
                        admin_url( 'admin.php' )
                    );

                    $ts = strtotime( ( $entry['date_created'] ?? '' ) . ' UTC' );
                    $fmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
                    $when = $ts ? wp_date( $fmt, $ts ) : '';

                    echo '<span class="dashicons dashicons-yes" style="color:#1d8137" title="' . esc_attr__( 'Linked', 'my-plugin' ) . '"></span> ';
                    echo '<a href="' . esc_url( $url ) . '">#' . esc_html( $entry_id ) . '</a>';
                    if( $when ) {
                        echo ' — ' . esc_html( $when );
                    }
                } else {
                    echo '<span class="dashicons dashicons-warning" style="color:#d63638"></span> #' . esc_html($entry_id);
                }
            } else {
                echo '#' . esc_html( $entry_id );
            }
            return;
        }

        if( $column === 'impeka_client' ) {
            $client = get_field( 'client_name', $post_id );
            echo $client !== '' ? esc_html( $client ) : '—';
            return;
        }

        if($column === 'impeka_ref') {
            $ref = get_field( 'reference_id', $post_id );
            echo $ref !== '' ? esc_html( $ref ) : '—';
            return;
        }
    }

    public function sortable_columns( array $cols ) : array {
        $cols['impeka_ref'] = 'impeka_ref';
        $cols['impeka_client'] = 'impeka_client';
        $cols['impeka_entry']  = 'impeka_entry';
        return $cols;
    }

    public function order_sortable_columns( \WP_Query $q ) : void {
        if( 
            ! is_admin() 
            || ! $q->is_main_query()
        ) {
            return;
        } 

        if( $q->get('post_type') !== 'impeka-survey' ) {
            return;
        }

        $orderby = $q->get('orderby');

        switch( $orderby ) {
            case 'impeka_ref':
                $q->set( 'meta_key', $keys['reference_id'] ?? 'reference_id' );
                $q->set( 'orderby', 'meta_value' );
                break;

            case 'impeka_client':
                $q->set( 'meta_key', $keys['client_name'] ?? 'client_name' );
                $q->set( 'orderby', 'meta_value' );
                break;

            case 'impeka_entry':
                $q->set( 'orderby', 'meta_value_num' );

                $q->set('meta_query', [
                    'relation' => 'OR',
                    [ 'key' => '_entry_id', 'compare' => 'EXISTS'     ],
                    [ 'key' => '_entry_id', 'compare' => 'NOT EXISTS' ],
                ]);

                break;
        }
    }

    public static function boot() : void {
        if( is_null( self::$_instance ) ) {
            new self();
        }
    }
}