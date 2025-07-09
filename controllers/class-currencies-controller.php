<?php

namespace MoneyManager\Controllers;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\Managers\Quote_Manager;
use MoneyManager\Models\Currency;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Currencies_Controller
 * @package MoneyManager\Controllers
 */
class Currencies_Controller extends Base_Controller
{
    /**
     * Register routes
     */
    public function register_routes()
    {
        $this->get( '/currencies/list', 'list_currencies' );
        $this->post( '/currencies/save', 'save_currency' );
        $this->post( '/currencies/remove', 'remove_currency' );
    }

    /**
     * Get list of currencies
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function list_currencies( WP_REST_Request $request )
    {
        $currencies = Currency::rows();

        return rest_ensure_response( array( 'result' => $currencies ) );
    }

    /**
     * Save currency
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function save_currency( WP_REST_Request $request )
    {
        global $wpdb;

        $input = $request->get_param( 'item' );

        $changing_base_currency = false;
        if ( isset ( $input['id'] ) ) {
            $currency = Currency::find( $input['id'] );
            if ( ! $currency ) {
                return rest_ensure_response( array( 'error' => array( 'code' => 'RECORD_NOT_FOUND' ) ) );
            }
            // Changing 'code' is not allowed
            unset ( $input['code'] );
            // Handle base currency
            if ( $input['is_base'] != $currency->is_base ) {
                if ( $input['is_base'] ) {
                    $input['default_quote'] = 1;
                    $changing_base_currency = true;
                } else {
                    // Changing 'is_base' to false is not allowed
                    unset ( $input['is_base'] );
                }
            }
            $currency->fill( $input );
        } else {
            $input['code'] = strtoupper( trim( $input['code'] ) );
            // Check for duplicates
            $duplicates = Currency::get_results( function ( $query ) use ( $wpdb, $input ) {
                return $query . $wpdb->prepare( ' where code = %s', $input['code'] );
            } );
            if ( ! empty ( $duplicates ) ) {
                return rest_ensure_response( array( 'error' => array( 'code' => 'DUPLICATE_RECORD' ) ) );
            }
            if ( $input['is_base'] ) {
                $input['default_quote'] = 1;
                $changing_base_currency = true;
            }
            $currency = new Currency( $input );
        }
        if ( $changing_base_currency ) {
            // If base currency is changing then clear quote history
            Quote_Manager::clear_history();
            $table_name = Currency::table_name();
            $wpdb->query( "update $table_name set is_base = 0" );
        }
        $currency->save();

        return rest_ensure_response( array( 'result' => 'ok' ) );
    }

    /**
     * Remove currency
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function remove_currency( WP_REST_Request $request )
    {
        Currency::destroy( $request->get_param( 'id' ) );

        return rest_ensure_response( array( 'result' => 'ok' ) );
    }
}