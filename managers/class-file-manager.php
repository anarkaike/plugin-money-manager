<?php

namespace MoneyManager\Managers;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\Models\File;

/**
 * Class File_Manager
 * @package MoneyManager\Managers
 */
class File_Manager
{
    /**
     * Init file manager
     */
    public static function init()
    {
        add_filter( 'wp_insert_attachment_data', array( self::class, 'handle_insert_attachment_data' ), 10, 2 );
        add_action( 'delete_attachment', array( self::class, 'handle_delete_attachment' ) );
    }

    /**
     * Handle updating attachment metadata
     *
     * @param array $data
     * @param array $post
     * @return array
     */
    public static function handle_insert_attachment_data( array $data, array $post )
    {
        global $wpdb;

        $wpdb->update(
            File::table_name(),
            array(
                'filename' => basename( $data['guid'] ),
                'description' => $data['post_content'],
                'url' => $data['guid'],
            ),
            array( 'attachment_id' => $post['ID'] )
        );

        return $data;
    }

    /**
     * Handle deleting attachment
     *
     * @param int $post_ID
     */
    public static function handle_delete_attachment( $post_ID )
    {
        global $wpdb;

        $wpdb->delete( File::table_name(), array( 'attachment_id' => $post_ID ) );
    }
}