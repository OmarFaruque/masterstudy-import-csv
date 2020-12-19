<?php

/**
 * Plugin Name: MasterStudy Import CSV
 * Version: 1.0.0
 * Description: Import plugin for MasterStudy LMS plugin
 * Author: LaraSoft
 * Author URI: http://larasoftbd.net
 * Requires at least: 4.4.0
 * Tested up to: 5.5.3
 * Text Domain: masterstudy-import-csv
 * WC requires at least: 3.0.0
 * WC tested up to: 4.7.0
 */

define('MICSV_TOKEN', 'masterstudy');
define('MICSV_VERSION', '1.0.0');
define('MICSV_FILE', __FILE__);
define('MICSV_PLUGIN_NAME', 'MasterStudy Import CSV');

// Helpers.
require_once realpath(plugin_dir_path(__FILE__)) . DIRECTORY_SEPARATOR . 'includes/helpers.php';

// Init.
add_action('plugins_loaded', 'micsv_init');
if (!function_exists('micsv_init')) {
    /**
     * Load plugin text domain
     *
     * @return  void
     */
    function micsv_init()
    {
        $plugin_rel_path = basename(dirname(__FILE__)) . '/languages'; /* Relative to WP_PLUGIN_DIR */
        load_plugin_textdomain('masterstudy-import-csv', false, $plugin_rel_path);
    }
}

// Loading Classes.
if (!function_exists('MICSV_autoloader')) {

    function MICSV_autoloader($class_name)
    {
        if (0 === strpos($class_name, 'MICSV')) {
            $classes_dir = realpath(plugin_dir_path(__FILE__)) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;
            $class_file = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
            require_once $classes_dir . $class_file;
        }
    }
}
spl_autoload_register('MICSV_autoloader');

// Backend UI.
if (!function_exists('MICSV_Backend')) {
    function MICSV_Backend()
    {
        return MICSV_Backend::instance(__FILE__);
    }
}
if (!function_exists('MICSV_Public')) {
    function MICSV_Public()
    {
        return MICSV_Public::instance(__FILE__);
    }
}
// Front end.
MICSV_Public();
if (is_admin()) {
    MICSV_Backend();
}
new MICSV_Api();
