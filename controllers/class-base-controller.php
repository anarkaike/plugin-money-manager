<?php

namespace MoneyManager\Controllers;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use WP_Error;
use WP_REST_Controller;

/**
 * Class Base_Controller
 * @package MoneyManager\Controllers
 */
class Base_Controller extends WP_REST_Controller
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->namespace = 'money-manager/v1';
    }

    /**
     * Register GET route
     *
     * @param string $route
     * @param string $method
     */
    protected function get( $route, $method )
    {
        register_rest_route( $this->namespace, $route, array(
            array(
                'methods' => 'GET',
                'callback' => array( $this, $method ),
                'permission_callback' => array( $this, 'permission_callback' ),
            )
        ) );
    }

    /**
     * Register POST route
     *
     * @param string $route
     * @param string $method
     */
    protected function post( $route, $method )
    {
        register_rest_route( $this->namespace, $route, array(
            array(
                'methods' => 'POST',
                'callback' => array( $this, $method ),
                'permission_callback' => array( $this, 'permission_callback' ),
            )
        ) );
    }

    /**
     * Check whether current user has permissions to access a route
     *
     * @param $request
     * @return bool|WP_Error
     */
    public function permission_callback( $request )
    {
        if ( ! $this->check_permissions( $request ) ) {
            return new WP_Error(
                'rest_forbidden',
                'Access denied',
                array( 'status' => $this->authorization_status_code() )
            );
        }
        return true;
    }

    /**
     * Do check whether current user has permissions to access a route
     *
     * @param $request
     * @return bool
     */
    public function check_permissions( $request )
    {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get error code
     *
     * @return int
     */
    public function authorization_status_code()
    {
        $status = 401;

        if ( is_user_logged_in() ) {
            $status = 403;
        }

        return $status;
    }
}