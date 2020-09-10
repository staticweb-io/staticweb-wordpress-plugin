<?php

namespace StaticWeb;

class Controller {
    public function run() : void {
        add_action( 'admin_bar_menu',
                    [ 'StaticWeb\AdminBar', 'admin_bar_menu_hook' ],
                    100 );

        add_action( 'wp_after_admin_bar_render',
                    [ 'StaticWeb\AdminBar', 'after_admin_bar_render' ] );

        add_action( 'wp_ajax_staticweb_job_queue',
                    [ 'StaticWeb\AdminBar', 'ajax_staticweb_job_queue' ] );

    }

    public static function activate( bool $network_wide = null ) : void {
    }

    public static function deactivate( bool $network_wide = null ) : void {
    }
}
