<?php

namespace MoneyManager\Controllers;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\Managers\Account_Manager;
use MoneyManager\Models\Account;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Accounts_Controller
 * @package MoneyManager\Controllers
 */
class Accounts_Controller extends Base_Controller
{
    /**
     * Register routes
     */
    public function register_routes()
    {
        $this->get( '/accounts/list', 'list_accounts' );
        $this->post( '/accounts/save', 'save_account' );
        $this->post( '/accounts/remove', 'remove_account' );
    }

    /**
     * Get list of accounts
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function list_accounts( WP_REST_Request $request )
    {
        $accounts = Account::rows( function ( $query ) {
            return $query . ' order by field(type,"checking","card","cash","debt","crypto"), title';
        } );

        return rest_ensure_response( array( 'result' => $accounts ) );
    }

    /**
     * Save account
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function save_account( WP_REST_Request $request )
    {
        $input = $request->get_param( 'item' );

        if ( isset ( $input['id'] ) ) {
            $account = Account::find( $input['id'] );
            if ( ! $account ) {
                return rest_ensure_response( array( 'error' => array( 'code' => 'RECORD_NOT_FOUND' ) ) );
            }
            $account->fill( $input );
        } else {
            $account = new Account( $input );
        }
        $account->save();

        Account_Manager::refresh_balance( $account->id );

        return rest_ensure_response( array( 'result' => 'ok' ) );
    }

    /**
     * Remove account
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function remove_account( WP_REST_Request $request )
    {
        Account::destroy( $request->get_param( 'id' ) );

        return rest_ensure_response( array( 'result' => 'ok' ) );
    }
}