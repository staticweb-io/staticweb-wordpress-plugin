<?php

namespace StaticWeb;

class Controller {
    public function run() : void {
        add_action( 'admin_bar_menu',
                    [ 'StaticWeb\AdminBar', 'admin_bar_menu_hook' ],
                    100 );

        add_action( 'pre_post_update',
                    [ 'StaticWeb\Controller', 'pre_post_update' ] );

        add_action( 'wp_after_admin_bar_render',
                    [ 'StaticWeb\AdminBar', 'after_admin_bar_render' ] );

        add_action( 'wp_ajax_staticweb_job_queue',
                    [ 'StaticWeb\AdminBar', 'ajax_staticweb_job_queue' ] );

        add_action( 'wp2static_deploy',
                    [ 'StaticWeb\Controller', 'deploy' ],
                    100, 1 );
    }

    public static function activate_for_single_site() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'staticweb_permalinks';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) NOT NULL,
            relative_permalink VARCHAR(255) NOT NULL UNIQUE,
            updated DATETIME NOT NULL,
            deployed DATETIME NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function activate( bool $network_wide = null ) : void {
        if ( $network_wide ) {
            global $wpdb;

            $query = 'SELECT blog_id FROM %s WHERE site_id = %d;';

            $site_ids = $wpdb->get_col(
                sprintf(
                    $query,
                    $wpdb->blogs,
                    $wpdb->siteid
                )
            );

            foreach ( $site_ids as $site_id ) {
                switch_to_blog( $site_id );
                self::activate_for_single_site();
            }

            restore_current_blog();
        } else {
            self::activate_for_single_site();
        }
    }

    public static function deactivate( bool $network_wide = null ) : void {
    }

    public static function deploy( string $processed_site_path ) : void {
        \WP2Static\WsLog::l( 'StaticWeb Addon deploying' );

        Deployer::deploy( $processed_site_path );
    }

    public static function pre_post_update( int $post_id ) : void {
        global $wpdb;

        $post_link = wp_make_link_relative(get_permalink($post_id));

        $table_name = $wpdb->prefix . 'staticweb_permalinks';
        $query_string = "UPDATE $table_name SET updated=NOW() WHERE post_id=%s AND updated<deployed";
        $query = $wpdb->prepare($query_string, $post_id);
        $wpdb->query($query);

        $table_name = $wpdb->prefix . 'staticweb_permalinks';
        $query_string = "INSERT INTO $table_name (post_id, relative_permalink, updated) VALUES (%s, %s, NOW()) ON DUPLICATE KEY UPDATE post_id=%s, updated=NOW()";
        $query = $wpdb->prepare($query_string, $post_id, $post_link, $post_id);
        $wpdb->query($query);
    }
}
