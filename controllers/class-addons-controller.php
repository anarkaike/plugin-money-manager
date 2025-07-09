<?php

namespace MoneyManager\Controllers;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Addons_Controller
 * @package MoneyManager\Controllers
 */
class Addons_Controller extends Base_Controller
{
    /**
     * Register routes
     */
    public function register_routes()
    {
        $this->get( '/addons/list', 'list_addons' );
        $this->post( '/addons/install', 'install_addon' );
        $this->post( '/addons/activate', 'activate_addon' );
        $this->post( '/addons/deactivate', 'deactivate_addon' );
    }

    /**
     * Get list of add-ons
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function list_addons( WP_REST_Request $request )
    {
        $response = wp_remote_get(
            'https://getmoneymanager.com/addons/info.json',
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept' => 'application/json'
                )
            )
        );

        if (
            wp_remote_retrieve_response_code( $response ) !== 200 ||
            ( $body = wp_remote_retrieve_body( $response ) ) === ''
        ) {
            return rest_ensure_response( array( 'error' => array( 'code' => 'ERROR_ERROR' ) ) );
        } else {
            return rest_ensure_response( array( 'result' => json_decode( $body, true ) ) );
        }
    }

    /**
     * Install given add-on
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function install_addon( WP_REST_Request $request )
    {
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

        $download_link = $request->get_param( 'download_link' );

        $skin = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader( $skin );

        if ( $upgrader->install( $download_link ) ) {
            return rest_ensure_response( array( 'result' => 'ok' ) );
        } else {
            return rest_ensure_response( array( 'error' => array( 'code' => 'ERROR_ERROR' ) ) );
        }
    }

    /**
     * Activate given add-on
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function activate_addon( WP_REST_Request $request )
    {
        $slug = $request->get_param( 'slug' );

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        if ( activate_plugin( $slug . '/main.php' ) === null ) {
            return rest_ensure_response( array( 'result' => 'ok' ) );
        } else {
            return rest_ensure_response( array( 'error' => array( 'code' => 'ERROR_ERROR' ) ) );
        }
    }

    /**
     * Deactivate given add-on
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function deactivate_addon( WP_REST_Request $request )
    {
        $slug = $request->get_param( 'slug' );

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        deactivate_plugins( $slug . '/main.php' );

        return rest_ensure_response( array( 'result' => 'ok' ) );
    }
}