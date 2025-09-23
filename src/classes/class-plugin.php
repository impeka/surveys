<?php

namespace Impeka\Surveys;

if ( ! defined( 'ABSPATH' ) ) { exit; }

if( class_exists( Plugin::class ) ) {
    return;
}

class Plugin {

    private static ?Plugin $_instance = null;
    private static string $_file, $_dir, $_plugin_url, $_version;
    
    public function boot() : void {
        EntryMetaBox::boot();
        TemplateManager::boot();
        SurveyArchiveManager::boot();
        RequestSubmitSurvey::process();
        RequestSendSurvey::process();

        add_action( 'init', [$this, 'register_post_types'] );
        add_action( 'acf/include_fields', [$this, 'register_meta_fields'] );
        add_action( 'save_post_impeka-survey', [$this, 'set_title'], 10, 3 );

        add_filter( 'acf/load_field/key=field_68d199add200b', [$this, 'prepopulate_forms_select'] );
        add_filter( 'acf/location/rule_match', [$this, 'remove_fields_after_submission'], 10, 4 );
        $this->_disable_fields_maybe();
    }

    static public function get_file() : string {
        return self::$_file;
    }

    static public function get_dir() : string {
        return self::$_dir;
    }

    static public function get_plugin_url() : string {
        return self::$_plugin_url;
    }

    static public function get_version() : string {
        return self::$_version;
    }

    public function set_title( int|string $post_id, \WP_Post $post, bool $update ) : void {
        if( 
            $update 
            && ! empty( $post->post_title )
        ) {
            return;
        }

        wp_update_post( [
            'ID' => $post_id,
            'post_title' => sprintf( _x( 'Survey #%s', 'title of survey posts, %s is replaced by post ID', 'impeka-surveys' ), $post_id )
        ] );
    }
    
    public function remove_fields_after_submission( bool $match, array $rule, array $screen, array $field_group ) : bool {

        if( $field_group['key'] != 'group_68d19953434bd' ) {
            return $match;
        }

        $post_id = $screen['post_id'] ?? 0;
        if( ! $post_id && isset( $_GET['post'] ) ) {
            $post_id = (int) $_GET['post'];
        }
        if( ! $post_id && isset( $_POST['post_ID'] ) ) {
            $post_id = (int) $_POST['post_ID'];
        }

        if( ! $post_id ) {
            return $match;
        }

        $has_entry = (bool) get_post_meta( $post_id, '_entry_id', true );

        return ! $has_entry;
    }

    public function prepopulate_forms_select( array $field ) : array {
        if( 
            function_exists( 'acf_is_screen' ) 
            && acf_is_screen( 'acf-field-group' ) 
        ) {
            return $field;
        }

        if( ! class_exists( 'GFAPI' ) ) {
            $field['choices'] = [ '' => __( 'Install/activate Gravity Forms to select a form', 'impeka-surveys' ) ];
            return $field;
        }

        $choices = get_transient( 'impeka_surveys_form_select_options' );
        if( false === $choices ) {
            $choices = [];
            $forms = \GFAPI::get_forms();
            foreach( $forms as $form ) {
                $id = (int) $form['id'];
                $title = isset( $form['title'] ) ? $form['title'] : sprintf( __( 'Form %d', 'impeka-surveys' ), $id );
                $choices[(string)$id] = sprintf( '%2$s (%1$d)', $id, $title );
            }

            set_transient( 'impeka_surveys_form_select_options', $choices, 10 * MINUTE_IN_SECONDS );
        }

        $field['choices'] = $choices ?: [ '' => __( 'No forms found', 'impeka-surveys' ) ];

        return $field;
    }

    private function _disable_fields_maybe() : void {
        $fields = [
            'field_68d1964dfb7f5',
            'field_68d19665fb7f6',
            'field_68d19672fb7f7',
            'field_68d196acfb7f8',
        ];

        $post_id = $screen['post_id'] ?? 0;
        
        if( ! $post_id && isset( $_GET['post'] ) ) {
            $post_id = (int) $_GET['post'];
        }
        
        if( ! $post_id && isset( $_POST['post_ID'] ) ) {
            $post_id = (int) $_POST['post_ID'];
        }

        $has_entry = $post_id && (bool) get_post_meta( $post_id, '_entry_id', true );

        if( $has_entry ) {
            foreach( $fields as $field_key ) {
                add_filter( sprintf( 'acf/prepare_field/key=%s', $field_key ), function( $field ) {
                    $field['readonly'] = 1;
                    return $field;
                } );

                add_filter( sprintf( 'acf/update_value/key=%s', $field_key ), function( Mixed $value, int|string $post_id ) {
                    return get_field( $field_key, $post_id, false );
                }, 5, 2 );
            }
        }
        
    }

    public function register_post_types() : void {
        register_post_type( 'impeka-survey', array(
            'labels' => array(
                'name' => __( 'Surveys', 'impeka-surveys' ),
                'singular_name' => __( 'Survey', 'impeka-surveys' ),
                'menu_name' => __( 'Surveys', 'impeka-surveys' ),
                'all_items' => __( 'Surveys', 'impeka-surveys' ),
                'edit_item' => __( 'Edit Survey', 'impeka-surveys' ),
                'view_item' => __( 'View Survey', 'impeka-surveys' ),
                'view_items' => __( 'View Surveys', 'impeka-surveys' ),
                'add_new_item' => __( 'Add New Survey', 'impeka-surveys' ),
                'add_new' => __( 'Add New Survey', 'impeka-surveys' ),
                'new_item' => __( 'New Survey', 'impeka-surveys' ),
                'parent_item_colon' => __( 'Parent Survey:', 'impeka-surveys' ),
                'search_items' => __( 'Search Surveys', 'impeka-surveys' ),
                'not_found' => __( 'No surveys found', 'impeka-surveys' ),
                'not_found_in_trash' => __( 'No surveys found in Trash', 'impeka-surveys' ),
                'archives' => __( 'Survey Archives', 'impeka-surveys' ),
                'attributes' => __( 'Survey Attributes', 'impeka-surveys' ),
                'insert_into_item' => __( 'Insert into survey', 'impeka-surveys' ),
                'uploaded_to_this_item' => __( 'Uploaded to this survey', 'impeka-surveys' ),
                'filter_items_list' => __( 'Filter surveys list', 'impeka-surveys' ),
                'filter_by_date' => __( 'Filter surveys by date', 'impeka-surveys' ),
                'items_list_navigation' => __( 'Surveys list navigation', 'impeka-surveys' ),
                'items_list' => __( 'Surveys list', 'impeka-surveys' ),
                'item_published' => __( 'Survey published.', 'impeka-surveys' ),
                'item_published_privately' => __( 'Survey published privately.', 'impeka-surveys' ),
                'item_reverted_to_draft' => __( 'Survey reverted to draft.', 'impeka-surveys' ),
                'item_scheduled' => __( 'Survey scheduled.', 'impeka-surveys' ),
                'item_updated' => __( 'Survey updated.', 'impeka-surveys' ),
                'item_link' => __( 'Survey Link', 'impeka-surveys' ),
                'item_link_description' => __( 'A link to a survey.', 'impeka-surveys' ),
            ),
            'public' => true,
            'exclude_from_search' => true,
            'publicly_queryable' => true,
            'show_in_nav_menus' => false,
            'show_in_admin_bar' => false,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-editor-ol',
            'supports' => array(
                0 => 'title',
            ),
            'rewrite' => array(
                'slug' => 'survey',
            ),
            'delete_with_user' => false,
        ) );
    }

    public function register_meta_fields() : void {
        acf_add_local_field_group( array(
            'key' => 'group_68d1964d2a537',
            'title' => 'Client Information',
            'fields' => array(
                array(
                    'key' => 'field_68d1964dfb7f5',
                    'label' => __( 'Client Name', 'impeka-surveys' ),
                    'name' => 'client_name',
                    'aria-label' => '',
                    'type' => 'text',
                    'instructions' => '',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'maxlength' => '',
                    'allow_in_bindings' => 0,
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                ),
                array(
                    'key' => 'field_68d19665fb7f6',
                    'label' => __( 'Client Email', 'impeka-surveys' ),
                    'name' => 'client_email',
                    'aria-label' => '',
                    'type' => 'email',
                    'instructions' => '',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'allow_in_bindings' => 0,
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                ),
                array(
                    'key' => 'field_68d19672fb7f7',
                    'label' => __( 'Reference ID', 'impeka-surveys' ),
                    'name' => 'reference_id',
                    'aria-label' => '',
                    'type' => 'text',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'maxlength' => '',
                    'allow_in_bindings' => 0,
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                ),
                array(
                    'key' => 'field_68d196acfb7f8',
                    'label' => __( 'Notes', 'impeka-surveys' ),
                    'name' => 'notes',
                    'aria-label' => '',
                    'type' => 'textarea',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'maxlength' => '',
                    'allow_in_bindings' => 0,
                    'rows' => '',
                    'placeholder' => '',
                    'new_lines' => '',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'impeka-survey',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
            'show_in_rest' => 0,
        ) );

        acf_add_local_field_group( array(
            'key' => 'group_68d19953434bd',
            'title' => __( 'Survey Information', 'impeka-surveys' ),
            'fields' => array(
                array(
                    'key' => 'field_68d2bb8056baa',
                    'label' => __( 'Email Notification Subject', 'impeka-surveys' ),
                    'name' => 'email_notification_subject',
                    'aria-label' => '',
                    'type' => 'text',
                    'instructions' => '',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'maxlength' => '',
                    'allow_in_bindings' => 0,
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                ),
                array(
                    'key' => 'field_68d19953d200a',
                    'label' => __( 'Email Notification', 'impeka-surveys' ),
                    'name' => 'email_notification',
                    'aria-label' => '',
                    'type' => 'wysiwyg',
                    'instructions' => __( 'The text [SURVEY_URL] will be replaced with the URL to the page with the survey. Make sure to add it to your email.', 'impeka-surveys' ),
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '[SURVEY_URL]',
                    'allow_in_bindings' => 0,
                    'tabs' => 'all',
                    'toolbar' => 'basic',
                    'media_upload' => 0,
                    'delay' => 0,
                ),
                array(
                    'key' => 'field_68d199add200b',
                    'label' => __( 'Survey Form', 'impeka-surveys' ),
                    'name' => 'form_id',
                    'aria-label' => '',
                    'type' => 'select',
                    'instructions' => '',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'choices' => array(
                    ),
                    'default_value' => false,
                    'return_format' => 'value',
                    'multiple' => 0,
                    'allow_null' => 0,
                    'allow_in_bindings' => 0,
                    'ui' => 0,
                    'ajax' => 0,
                    'placeholder' => '',
                    'create_options' => 0,
                    'save_options' => 0,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'impeka-survey',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
            'show_in_rest' => 0,
        ) );
    }

    private function __construct( string $file, string $dir, string $plugin_url, string $version ) {
        self::$_file = $file;
        self::$_dir = $dir;
        self::$_plugin_url = $plugin_url;
        self::$_version = $version;
    }

    public static function getInstance( string $file, string $dir, string $plugin_url, string $version ) : Plugin {
        if( is_null( self::$_instance ) ) {
            self::$_instance = new self( $file, $dir, $plugin_url, $version );
        }

        return self::$_instance;
    }

    
}