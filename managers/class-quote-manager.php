<?php

namespace MoneyManager\Managers;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\Models;

/**
 * Class Quote_Manager
 * @package MoneyManager\Managers
 */
class Quote_Manager
{
    /**
     * Refresh today's currency quotes
     */
    public static function refresh()
    {
        $today = current_time( 'Y-m-d' );
        foreach ( self::currencies() as $currency ) {
            if ( $currency->code != self::base_currency() ) {
                // Try different pairs
                $pairs = [
                    sprintf( '%s%s=X', $currency->code, self::base_currency() ),
                    sprintf( '%s%s=X', self::base_currency(), $currency->code ),
                    sprintf( '%s-%s', $currency->code, self::base_currency() ),
                    sprintf( '%s-%s', self::base_currency(), $currency->code ),
                ];
                $direct = true;
                foreach ( $pairs as $pair ) {
                    $response = wp_remote_get( sprintf(
                        'https://query1.finance.yahoo.com/v7/finance/quote?symbols=%s&fields=regularMarketPrice',
                        $pair
                    ) );
                    $response = json_decode( wp_remote_retrieve_body( $response ), true );
                    if ( is_array( $response ) && isset ( $response['quoteResponse']['result'][0]['regularMarketPrice'] ) ) {
                        $quote = $response['quoteResponse']['result'][0]['regularMarketPrice'];
                        $value = $direct ? $quote : 1 / $quote;
                        self::save( $currency->code, $today, $value );
                        break;
                    }
                    $direct = ! $direct;
                }
            }
        }
    }

    /**
     * Fetch quote history for given period
     *
     * @param string $range  // 1d, 5d, 1mo, 3mo, 6mo, 1y, 2y, 5y, 10y, ytd, max
     * @param array|null $currencies
     */
    public static function fetch_history( $range = '1mo', array $currencies = null )
    {
        if ( is_null( $currencies ) ) {
            $currencies = array_map( function ( $currency ) {
                return $currency->code;
            }, self::currencies() );
        }
        foreach ( self::currencies() as $currency ) {
            if ( $currency->code != self::base_currency() && in_array( $currency->code, $currencies ) ) {
                // Try different pairs
                $pairs = [
                    sprintf( '%s%s=X', $currency->code, self::base_currency() ),
                    sprintf( '%s%s=X', self::base_currency(), $currency->code ),
                    sprintf( '%s-%s', $currency->code, self::base_currency() ),
                    sprintf( '%s-%s', self::base_currency(), $currency->code ),
                ];
                $direct = true;
                foreach ( $pairs as $pair ) {
                    $response = wp_remote_get( sprintf(
                        'https://query1.finance.yahoo.com/v8/finance/chart/%s?range=%s&interval=%s&fields=currency',
                        $pair,
                        $range,
                        '1d'
                    ) );
                    $response = json_decode( wp_remote_retrieve_body( $response ), true );
                    if (
                        is_array( $response ) &&
                        isset ( $response['chart']['result'][0]['timestamp'] ) &&
                        isset ( $response['chart']['result'][0]['meta']['exchangeTimezoneName'] ) &&
                        isset ( $response['chart']['result'][0]['indicators']['adjclose'][0]['adjclose'] )
                    ) {
                        $times = $response['chart']['result'][0]['timestamp'];
                        $timezone = $response['chart']['result'][0]['meta']['exchangeTimezoneName'];
                        $quotes = $response['chart']['result'][0]['indicators']['adjclose'][0]['adjclose'];
                        if ( ! empty ( $times ) ) {
                            $prev_date = null;
                            $value = null;
                            $timezone = timezone_open( $timezone );
                            foreach ( $times as $i => $time ) {
                                $date = date_create( '@' . $time )
                                    ->setTimezone( $timezone )
                                    ->modify( 'midnight' )
                                ;
                                while ( $prev_date && $date->diff( $prev_date )->days > 1 ) {
                                    $prev_date->modify( '+1 day' );
                                    self::save( $currency->code, $prev_date->format( 'Y-m-d' ), $value );
                                }
                                if ( $quotes[ $i ] !== null ) {
                                    $value = $direct ? $quotes[ $i ] : 1 / $quotes[ $i ];
                                    self::save( $currency->code, $date->format( 'Y-m-d' ), $value );
                                    $prev_date = $date;
                                }
                            }
                            $today = date_create( 'today', $timezone );
                            while ( $prev_date && $prev_date < $today ) {
                                $prev_date->modify( '+1 day' );
                                self::save( $currency->code, $prev_date->format( 'Y-m-d' ), $value );
                            }
                            break;
                        }
                    }
                    $direct = ! $direct;
                }
            }
        }
    }

    /**
     * Remove all quote history
     *
     * @param array|null $currencies
     */
    public static function clear_history( array $currencies = null )
    {
        global $wpdb;

        $table_name = Models\Quote::table_name();
        $query = "delete from $table_name";
        if ( ! is_null( $currencies ) ) {
            if ( empty ( $currencies ) ) {
                return;
            }
            $placeholders = implode( ',', array_fill( 0, count( $currencies ), '%s' ) );
            $query .= $wpdb->prepare(
                " where currency in ($placeholders)",
                $currencies
            );
        }
        $wpdb->query( $query );
    }

    /**
     * Get quote values for given date
     *
     * @param string $date
     * @return array
     */
    public static function values( $date )
    {
        global $wpdb;

        $result = array();

        $table_name = Models\Quote::table_name();
        $query = array();
        foreach ( self::currencies() as $currency ) {
            $query[] = $wpdb->prepare(
                "(select currency, value from $table_name where currency = %s and date <= %s order by date desc limit 1)",
                $currency->code,
                $date
            );
        }

        if ( ! empty ( $query ) ) {
            $items = $wpdb->get_results( implode( ' union all ', $query ) );
            if ( is_array( $items ) ) {
                $quotes = array();
                foreach ( $items as $item ) {
                    $quotes[ $item->currency ] = $item->value;
                }

                foreach ( self::currencies() as $currency ) {
                    $result[ $currency->code ] = isset ( $quotes[ $currency->code ] )
                        ? $quotes[ $currency->code ]
                        : ( $currency->code == self::base_currency() ? 1 : (double) $currency->default_quote );
                }
            }
        }

        return $result;
    }

    /**
     * Save data to DB
     *
     * @param string $currency
     * @param string $date
     * @param float $value
     */
    protected static function save( $currency, $date, $value )
    {
        global $wpdb;

        $table_name = Models\Quote::table_name();
        $created_at = $updated_at = current_time( 'mysql' );
        $updated = $wpdb->update(
            $table_name,
            compact( 'value', 'updated_at' ),
            compact( 'currency', 'date' ),
            array( '%f', '%s' ),
            array( '%s', '%s' )
        );
        if ( ! $updated ) {
            $wpdb->insert(
                $table_name,
                compact( 'currency', 'date', 'value', 'created_at', 'updated_at' ),
                array( '%s', '%s', '%f', '%s', '%s' )
            );
        }
    }

    /**
     * Get base currency
     *
     * @return string
     */
    protected static function base_currency()
    {
        static $base_currency;

        if ( ! $base_currency ) {
            $items = Models\Currency::get_results( function ( $query ) {
                return $query . ' where is_base = 1';
            } );
            $base_currency = empty ( $items ) ? 'BRL' : $items[0]->code;
        }

        return $base_currency;
    }

    /**
     * Get list of currencies
     *
     * @return array
     */
    protected static function currencies()
    {
        static $currencies;

        if ( ! $currencies ) {
            $currencies = Models\Currency::get_results();
        }

        return $currencies;
    }
}
