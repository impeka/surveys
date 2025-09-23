<?php
/**
 * Plugin Name:  Surveys
 * Version:      0.0
 * Author:       Impeka
 * Author URI:   https://impeka.com
*/

namespace Impeka\Surveys;

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/src/helper-functions.php';
require_once __DIR__ . '/src/classes/class-plugin.php';
require_once __DIR__ . '/src/classes/class-entry-meta-box.php';
require_once __DIR__ . '/src/classes/class-survey-archive-manager.php';
require_once __DIR__ . '/src/classes/class-request-submit-survey.php';
require_once __DIR__ . '/src/classes/class-request-send-survey.php';
require_once __DIR__ . '/src/classes/class-template-manager.php';

add_action( 'plugins_loaded', function () {

    //load_plugin_textdomain( 'my-plugin', false, basename( MY_PLUGIN_DIR ) . '/languages' );

    $plugin = Plugin::getInstance( __FILE__, __DIR__, plugin_dir_url( __FILE__ ), '0.0' );
    $plugin->boot();
});