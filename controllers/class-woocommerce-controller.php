<?php

namespace MoneyManager\Controllers;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class WooCommerce_Controller
 * @package MoneyManager\Controllers
 */
class WooCommerce_Controller extends Base_Controller
{
    /**
     * Register routes
     */
    public function register_routes()
    {
        $this->post( '/woocommerce/save', 'save_woocommerce' );
    }

    /**
     * Save WooCommerce integration settings
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function save_woocommerce( WP_REST_Request $request )
    {
        update_option( 'money_manager_woocommerce', $request->get_param( 'item' ) );

        do_action( 'money_manager_woocommerce_saved', $request );

        return rest_ensure_response( array( 'result' => 'ok' ) );
    }
}