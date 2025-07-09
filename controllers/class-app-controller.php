<?php

namespace MoneyManager\Controllers;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\Managers\Quote_Manager;
use MoneyManager\Managers\WooCommerce_Manager;
use MoneyManager\Models\Account;
use MoneyManager\Models\Category;
use MoneyManager\Models\Currency;
use MoneyManager\Models\Party;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class App_Controller
 * @package MoneyManager\Controllers
 */
class App_Controller extends Base_Controller
{
    /**
     * Register routes
     */
    public function register_routes()
    {
        $this->get( '/app/load', 'load_app' );
        $this->post( '/app/save-meta', 'save_meta' );
    }

    /**
     * Load application data
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function load_app( WP_REST_Request $request )
    {
        $accounts = Account::rows( function ( $query ) {
            return $query . ' order by field(type,"checking","card","cash","debt"), title';
        } );
        $categories = Category::rows( function ( $query ) {
            return $query . ' order by title';
        } );
        $currencies = Currency::rows();
        $parties = Party::rows( function ( $query ) {
            return $query . ' order by title';
        } );

        return rest_ensure_response( array( 'result' => array(
            'accounts' => $accounts,
            'categories' => $categories,
            'currencies' => $currencies,
            'parties' => $parties,
            'quotes' => Quote_Manager::values( current_time( 'Y-m-d' ) ),
            'woocommerce' => WooCommerce_Manager::settings(),
        ) ) );
    }

    /**
     * Save user meta
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function save_meta( WP_REST_Request $request )
    {
        update_user_meta(
            get_current_user_id(),
            'money_manager',
            wp_slash( $request->get_param( 'meta' ) )
        );

        return rest_ensure_response( array( 'result' => 'ok' ) );
    }
}