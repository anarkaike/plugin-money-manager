<?php

namespace MoneyManager\Models;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

/**
 * Class Base
 * @package MoneyManager\Models
 */
class Base
{
    protected static $table = '';

    protected static $fillable = [];

    protected static $hidden = [];

    protected static $casts = [];

    private $data = array();

    /**
     * Constructor
     *
     * @param array $data
     */
    public function __construct( array $data = array() )
    {
        $this->fill( $data );
    }

    /**
     * Dynamically retrieve fields data
     *
     * @param string $key
     * @return mixed
     */
    public function __get( $key )
    {
        return $this->data[ $key ];
    }

    /**
     * Dynamically set fields data
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set( $key, $value )
    {
        $this->data[ $key ] = $value;
    }

    /**
     * Fill fields with data
     *
     * @param array $data
     */
    public function fill( array $data )
    {
        $this->data = array_merge( $this->data, array_intersect_key( $data, array_flip( static::$fillable ) ) );
    }

    /**
     * Save item
     *
     * @return int|false
     */
    public function save()
    {
        global $wpdb;

        $now = current_time( 'mysql' );

        if ( isset ( $this->data['id'] ) ) {
            return $wpdb->update(
                static::table_name(),
                array( 'updated_at' => $now ) + static::normalize_data( array_diff_key( $this->data, array( 'id' => false ) ) ),
                array( 'id' => $this->data['id'] )
            );
        } else {
            $result = $wpdb->insert(
                static::table_name(),
                array( 'created_at' => $now, 'updated_at' => $now ) + static::normalize_data( $this->data )
            );
            if ( $result ) {
                $this->data['id'] = $wpdb->insert_id;
            }

            return $result;
        }
    }

    /**
     * Execute query and get results as array of models or raw objects
     *
     * @param callable $prepare_query
     * @return null|array
     */
    public static function get_results( $prepare_query = false, $as_models = false )
    {
        global $wpdb;

        $table_name = static::table_name();
        $query = "select * from $table_name";
        if ( is_callable( $prepare_query ) ) {
            $query = $prepare_query( $query );
        }

        $items = $wpdb->get_results( $query );

        if ( is_array( $items ) ) {
            array_walk( $items, 'static::cast_fields' );
            if ( $as_models ) {
                $result = array();
                foreach ( $items as $item ) {
                    $model = new static();
                    $model->data = get_object_vars( $item );
                    $result[] = $model;
                }

                return $result;
            } else {
                return $items;
            }
        }

        return null;
    }

    /**
     * Find item by ID
     *
     * @param int $id
     * @return null|static
     */
    public static function find( $id )
    {
        global $wpdb;

        $models = static::get_results( function ( $query ) use ( $wpdb, $id ) {
            return $query . $wpdb->prepare( ' where id = %d', $id );
        }, true );

        return empty ( $models ) ? null : $models[0];
    }

    /**
     * Remove items with given IDs
     *
     * @param int|array $ids
     */
    public static function destroy( $ids )
    {
        global $wpdb;

        $ids = is_array( $ids ) ? $ids : func_get_args();
        if ( empty ( $ids ) ) {
            return;
        }

        $table_name = static::table_name();
        $ids_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $query = "delete from $table_name where id in ($ids_placeholders)";

        $wpdb->query( $wpdb->prepare( $query, $ids ) );
    }

    /**
     * Get items as array of rows (visible fields only)
     *
     * @param callable $prepare_query
     * @return null|array
     */
    public static function rows( $prepare_query = false )
    {
        $items = static::get_results( $prepare_query );
        if ( is_array( $items ) ) {
            $result = array();
            $hidden = array_flip( static::$hidden );
            foreach ( $items as $item ) {
                $result[] = array_diff_key( (array) $item, $hidden );
            }

            return $result;
        }

        return null;
    }

    /**
     * Get table name
     *
     * @return string
     */
    public static function table_name()
    {
        global $wpdb;

        return $wpdb->prefix . static::$table;
    }

    /**
     * Destroy all records except those with given IDs
     *
     * @param array $ids_to_keep
     * @param array $where
     * @return void
     */
    public static function destroy_except( array $ids_to_keep, array $where )
    {
        global $wpdb;

        $table_name = static::table_name();
        $query = "delete from $table_name";

        $criteria = array();
        $values = array();
        foreach ( $where as $field => $value ) {
            $criteria[] = "$field = %d";
            $values[] = $value;
        }
        if ( ! empty ( $ids_to_keep ) ) {
            $ids_placeholders = implode( ',', array_fill( 0, count( $ids_to_keep ), '%d' ) );
            $criteria[] = "id not in ($ids_placeholders)";
            $values = array_merge( $values, $ids_to_keep );
        }
        $clause = implode( ' and ', $criteria );
        if ( $clause != '' ) {
            $query .= ' where ' . $wpdb->prepare( $clause, $values );
        }

        $wpdb->query( $query );
    }

    /**
     * Cast fields data
     *
     * @param $item
     */
    protected static function cast_fields( $item )
    {
        foreach ( static::$casts + array( 'id' => 'int' ) as $field => $type ) {
            if ( is_null( $item->$field ) ) {
                continue;
            }
            $parts = explode( ':', $type );
            switch ( $parts[0] ) {
                case 'bool':
                    $item->$field = (bool) $item->$field;
                    break;
                case 'double':
                    $item->$field = (double) $item->$field;
                    break;
                case 'int':
                    $item->$field = (int) $item->$field;
                    break;
                case 'decimal':
                    $item->$field = number_format( $item->$field, $parts[1], '.', '' );
                    break;
                case 'json':
                    $item->$field = json_decode( $item->$field, true );
                    break;
            }
        }
    }

    /**
     * Normalize data for storing in database
     *
     * @param array $data
     * @return array
     */
    protected static function normalize_data( array $data )
    {
        foreach ( static::$casts as $field => $type ) {
            if ( ! array_key_exists( $field, $data ) ) {
                continue;
            }
            $parts = explode( ':', $type );
            switch ( $parts[0] ) {
                case 'bool':
                    $data[ $field ] = $data[ $field ] ? 1 : 0;
                    break;
                case 'json':
                    $data[ $field ] = json_encode( $data[ $field ] );
                    break;
            }
        }

        return $data;
    }
}