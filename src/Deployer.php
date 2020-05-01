<?php

namespace StaticWeb;

use Aws\S3\S3Client;
use WP2Static\CoreOptions;
use WP2StaticS3\Controller;

define("MD5_EMPTY_STRING", "d41d8cd98f00b204e9800998ecf8427e");

class Deployer {

    public static function deploy( string $processed_site_path ) : void {
        global $wpdb;

        // check if dir exists
        if ( ! is_dir( $processed_site_path ) ) {
            return;
        }

        $client_options = [
            'profile' => \WP2StaticS3\Controller::getValue( 's3Profile' ),
            'version' => 'latest',
            'region' => \WP2StaticS3\Controller::getValue( 's3Region' ),
        ];

        /*
           If no credentials option, SDK attempts to load credentials from
           your environment in the following order:

           - environment variables.
           - a credentials .ini file.
           - an IAM role.
         */
        if (
            \WP2StaticS3\Controller::getValue( 's3AccessKeyID' ) &&
            \WP2StaticS3\Controller::getValue( 's3SecretAccessKey' )
        ) {
            $client_options['credentials'] = [
                'key' => \WP2StaticS3\Controller::getValue( 's3AccessKeyID' ),
                'secret' => \WP2Static\CoreOptions::encrypt_decrypt(
                    'decrypt',
                    \WP2StaticS3\Controller::getValue( 's3SecretAccessKey' )
                ),
            ];
            unset( $client_options['profile'] );
        }

        // instantiate S3 client
        $s3 = new \Aws\S3\S3Client( $client_options );
        $bucket = \WP2StaticS3\Controller::getValue( 's3Bucket' );
        $post_processed_dir = \WP2Static\ProcessedSite::getPath();

        $table_name = $wpdb->prefix . 'staticweb_permalinks';
        $query = "SELECT * FROM $table_name WHERE deployed IS NULL OR deployed <= updated";
        $rows = $wpdb->get_results( $query );

        foreach ( $rows as $row ) {
            $post_id = $row->post_id;
            $post = get_post($post_id);
            $path = $row->relative_permalink;
            $canonical_path = wp_make_link_relative(get_permalink($post_id));

            if ( is_null( $post ) ) {
                self::delete_row( $row->id );
            } else if ( 'publish' === $post->post_status ) {
                if ($path !== $canonical_path) {
                    if ( mb_substr( $path, -1 ) === '/' ) {
                        $path = $path . "index.html";
                    }
                    $file_path = $post_processed_dir . $path;
                } else {
                    self::update_deployed( $row->id, $row->updated );
                }
            } else {
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
                                         string $key, string $redirect_to ) : array {
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
