<?php

namespace StaticWeb;

use WP2Static\WsLog;

define("STATICWEB_MD5_EMPTY_STRING", "d41d8cd98f00b204e9800998ecf8427e");

class Deployer {

    public static function add_to_deploy_cache( string $local_path, string $hash ) : void {
        global $wpdb;

        $deploy_cache_table = $wpdb->prefix . 'wp2static_deploy_cache';
        $post_processed_dir = \WP2Static\ProcessedSite::getPath();
        $deployed_file = $post_processed_dir . $local_path;
        $path_hash = md5( $deployed_file );

        $sql = "INSERT INTO {$deploy_cache_table} (path_hash,path,file_hash)" .
            ' VALUES (%s,%s,%s) ON DUPLICATE KEY UPDATE file_hash = %s';
        $sql = $wpdb->prepare( $sql, $path_hash, $local_path, $hash, $hash );
        $wpdb->query( $sql );
    }

    public static function deploy( string $processed_site_path ) : void {
        global $wpdb;

        // check if dir exists
        if ( ! is_dir( $processed_site_path ) ) {
            return;
        }

        $site_path = rtrim( \WP2Static\SiteInfo::getURL( 'site' ), '/' );
        $client = new HTTPClient();

        // instantiate S3 client
        $s3 = \WP2StaticS3\Deployer::s3Client();
        $bucket = \WP2StaticS3\Controller::getValue( 's3Bucket' );

        $table_name = $wpdb->prefix . 'staticweb_permalinks';
        $query = "SELECT * FROM $table_name WHERE deployed IS NULL OR deployed <= updated";
        $rows = $wpdb->get_results( $query );

        foreach ( $rows as $row ) {
            $post_id = $row->post_id;
            $post = get_post($post_id);
            $path = $row->relative_permalink;
            $canonical_path = wp_make_link_relative(get_permalink($post_id));

            $remote_path = \WP2StaticS3\Controller::getValue( 's3RemotePath' );
            if ($remote_path) $remote_path = $remote_path . '/';

            if ( is_null( $post ) ) {
                self::delete_row( $row->id );
            } else if ( 'publish' === $post->post_status ) {
                if ($path !== $canonical_path) {
                    $url = new \WP2Static\URL( $site_path . $path );
                    $response = $client->getURL( $url );
                    $status = $response['code'];

                    if (200 <= $status && $status < 300) {
                        // If the server returns a valid page response,
                        // we don't write a redirect.
                        self::update_deployed( $row->id, $row->updated );
                    } else if (300 <= $status && $status < 500) {
                        // If the server returns a status from 300-499,
                        // create a redirect.
                        if ( mb_substr( $path, -1 ) === '/' ) {
                            $key = $path . "index.html";
                        } else {
                            $key = $path;
                        }

                        $s3_key = $remote_path . ltrim($key, '/');
                        $s3_response = self::put_redirect( $s3, $bucket, $s3_key, $canonical_path );
                        if (200 === $s3_response->get('@metadata')['statusCode']) {
                            self::add_to_deploy_cache( $key, STATICWEB_MD5_EMPTY_STRING );
                            self::update_deployed( $row->id, $row->updated );
                        } else {
                            WsLog::l('StaticWeb: AWS response status ' .
                                     $s3_response->get('@metadata')['statusCode']);
                        }
                    } else {
                        // This shouldn't happen.
                        WsLog::l('StaticWeb: ' . $status . ' for URL ' . $site_path . $path);
                    }
                } else {
                    // If this is the post's current URL, skip it.
                    self::update_deployed( $row->id, $row->updated );
                }
            } else {
                // If the post has any status other than publish, skip it.
                self::update_deployed( $row->id, $row->updated );
            }
        }
    }

    public static function delete_row ( int $id ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'staticweb_permalinks';
        $query_string = "DELETE FROM $table_name WHERE id=%s";
        $query = $wpdb->prepare($query_string, $id);
        $wpdb->query($query);
    }

    public static function put_redirect( \Aws\S3\S3Client $s3, string $bucket,
                                         string $key, string $redirect_to ) : object {
        $result = $s3->putObject(
            [
                'Bucket' => $bucket,
                'Key' => $key,
                'ACL' => 'public-read',
                'WebsiteRedirectLocation' => $redirect_to
            ]
        );
        return $result;
    }

    public static function update_deployed( int $id, string $updated ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'staticweb_permalinks';
        $query_string = "UPDATE $table_name SET deployed = NOW() WHERE id=%s AND updated=%s";
        $query = $wpdb->prepare($query_string, $id, $updated);
        $wpdb->query($query);
    }

}
