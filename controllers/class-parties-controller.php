<?php

namespace MoneyManager\Controllers;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\Models\Party;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Parties_Controller
 * @package MoneyManager\Controllers
 */
class Parties_Controller extends Base_Controller
{
    /**
     * Register routes
     */
    public function register_routes()
    {
        $this->get( '/parties/list', 'list_parties' );
        $this->post( '/parties/save', 'save_party' );
        $this->post( '/parties/remove', 'remove_party' );
    }

    /**
     * Get list of parties
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function list_parties( WP_REST_Request $request )
    {
        $parties = Party::rows( function ( $query ) {
            return $query . ' order by title';
        } );

        return rest_ensure_response( array( 'result' => $parties ) );
    }

    /**
     * Save party
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function save_party( WP_REST_Request $request )
    {
        $input = $request->get_param( 'item' );

        if ( isset ( $input['id'] ) ) {
            $party = Party::find( $input['id'] );
            if ( ! $party ) {
                return rest_ensure_response( array( 'error' => array( 'code' => 'RECORD_NOT_FOUND' ) ) );
            }
            $party->fill( $input );
        } else {
            $party = new Party( $input );
        }
        $party->save();

        return rest_ensure_response( array( 'result' => 'ok' ) );
    }

    /**
     * Remove party
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function remove_party( WP_REST_Request $request )
    {
        Party::destroy( $request->get_param( 'id' ) );

        return rest_ensure_response( array( 'result' => 'ok' ) );
    }
}