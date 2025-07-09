<?php

namespace MoneyManager\Controllers;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\Models\Category;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Categories_Controller
 * @package MoneyManager\Controllers
 */
class Categories_Controller extends Base_Controller
{
    /**
     * Register routes
     */
    public function register_routes()
    {
        $this->get( '/categories/list', 'list_categories' );
        $this->post( '/categories/save', 'save_category' );
        $this->post( '/categories/remove', 'remove_category' );
    }

    /**
     * Get list of categories
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function list_categories( WP_REST_Request $request )
    {
        $categories = Category::rows( function ( $query ) {
            return $query . ' order by title';
        } );

        return rest_ensure_response( array( 'result' => $categories ) );
    }

    /**
     * Save category
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function save_category( WP_REST_Request $request )
    {
        $input = $request->get_param( 'item' );

        if ( isset ( $input['id'] ) ) {
            $category = Category::find( $input['id'] );
            if ( ! $category ) {
                return rest_ensure_response( array( 'error' => array( 'code' => 'RECORD_NOT_FOUND' ) ) );
            }
            $category->fill( $input );
        } else {
            $category = new Category( $input );
        }
        $category->save();

        return rest_ensure_response( array( 'result' => 'ok' ) );
    }

    /**
     * Remove category
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function remove_category( WP_REST_Request $request )
    {
        Category::destroy( $request->get_param( 'id' ) );

        return rest_ensure_response( array( 'result' => 'ok' ) );
    }
}