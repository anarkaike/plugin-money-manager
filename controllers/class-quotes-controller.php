<?php

namespace MoneyManager\Controllers;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\Managers\Quote_Manager;
use MoneyManager\Models\Quote;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Quotes_Controller
 * @package MoneyManager\Controllers
 */
class Quotes_Controller extends Base_Controller
{
    /**
     * Register routes
     */
    public function register_routes()
    {
        $this->get( '/quotes/load', 'load_quotes' );
        $this->get( '/quotes/list', 'list_quotes' );
        $this->post( '/quotes/refresh', 'refresh_quotes' );
        $this->post( '/quotes/fetch-history', 'fetch_quotes_history' );
        $this->post( '/quotes/clear-history', 'clear_quotes_history' );
    }

    /**
     * Load quotes data
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function load_quotes( WP_REST_Request $request )
    {
        return rest_ensure_response( array( 'result' => Quote_Manager::values(
            $request->has_param( 'date' ) ? $request->get_param( 'date' ) : current_time( 'Y-m-d' )
        ) ) );
    }

    /**
     * Load list of quotes
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function list_quotes( WP_REST_Request $request )
    {
        return rest_ensure_response( array( 'result' => Quote::rows( function ( $query ) use ( $request ) {
            global $wpdb;

            return $query . $wpdb->prepare(
                ' where currency = %s order by date desc',
                $request->get_param( 'currency' )
            );
        } ) ) );
    }

    /**
     * Refresh today's quotes
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function refresh_quotes( WP_REST_Request $request )
    {
        Quote_Manager::refresh();

        return rest_ensure_response( array( 'result' => 'ok' ) );
    }

    /**
     * Fetch quotes history for given range and currencies
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function fetch_quotes_history( WP_REST_Request $request )
    {
        $range = $request->get_param( 'range' ) ?: '1d';
        $currencies = $request->get_param( 'currencies' ) ?: array();

        Quote_Manager::fetch_history( $range, $currencies );

        return rest_ensure_response( array( 'result' => 'ok' ) );
    }

    /**
     * Clear quotes history for given currencies
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function clear_quotes_history( WP_REST_Request $request )
    {
        $currencies = $request->get_param( 'currencies' ) ?: array();

        Quote_Manager::clear_history( $currencies );

        return rest_ensure_response( array( 'result' => 'ok' ) );
    }
}