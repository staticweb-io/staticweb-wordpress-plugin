<?php

/**
 * Plugin Name:       StaticWeb
 * Plugin URI:        https://staticweb.io
 * Description:       StaticWeb Plugin
 * Version:           1.0.1-alpha
 * Author:            StaticWeb.io
 * Author URI:        https://staticweb.io
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'STATICWEB_PATH', plugin_dir_path( __FILE__ ) );
define( 'STATICWEB_VERSION', '1.0.1-alpha' );

require STATICWEB_PATH . 'vendor/autoload.php';

function run_staticweb() {
    $controller = new StaticWeb\Controller();
    $controller->run();
}

register_activation_hook(
    __FILE__,
    [ 'StaticWeb\Controller', 'activate' ]
);

register_deactivation_hook(
    __FILE__,
    [ 'StaticWeb\Controller', 'deactivate' ]
);

run_staticweb();