<?php

namespace StaticWeb;

class Controller {
    public function run() : void {
        add_filter( 'wp_insert_post_data',
                    [ 'StaticWeb\Controller', 'insertPostData' ] );
    }

    public static function insertPostData( array $data, array $postarr) : array {
        
        return $data;
    }

    public static function activate_for_single_site() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'staticweb_permalinks';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
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

    public static function insertPostData( array $data, array $postarr) : array {
        global $wpdb;

        $post_id = $postarr['ID'];
        $post_link = wp_make_link_relative(get_permalink($post_id));

        $table_name = $wpdb->prefix . 'staticweb_permalinks';
        $query_string = "INSERT INTO $table_name (post_id, relative_permalink, updated) VALUES (%s, %s, NOW()) ON DUPLICATE KEY UPDATE post_id=%s, updated=NOW()";
        $query = $wpdb->prepare($query_string, $post_id, $post_link, $post_id);
        $wpdb->query($query);

        return $data;
    }
}
