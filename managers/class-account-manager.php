<?php

namespace MoneyManager\Managers;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\Models;

/**
 * Class Account_Manager
 * @package MoneyManager\Managers
 */
class Account_Manager
{
    /**
     * Refresh balance of given account
     *
     * @param int $account_id
     */
    public static function refresh_balance( $account_id )
    {
        global $wpdb;

        $table_name = Models\Account::table_name();
        $query = "update $table_name set balance = initial_balance + %f where id = %d";

        $wpdb->query( $wpdb->prepare( $query, self::get_delta( $account_id ), $account_id ) );
    }

    /**
     * Get delta of all/some transactions of given account
     *
     * @param int $account_id
     * @param string|null $up_to_date
     * @return float
     */
    public static function get_delta( $account_id, $up_to_date = null )
    {
        global $wpdb;

        $table_name = Models\Transaction::table_name();
        $date_condition = $up_to_date !== null ? $wpdb->prepare( 'date < %s', $up_to_date ) : '1';

        $delta = $wpdb->get_var( $wpdb->prepare(
            "select sum(amount) from $table_name where type = \"income\" and account_id = %d and $date_condition",
            $account_id
        ) );

        $delta += $wpdb->get_var( $wpdb->prepare(
            "select sum(to_amount) from $table_name where type = \"transfer\" and to_account_id = %d and $date_condition",
            $account_id
        ) );

        $delta -= $wpdb->get_var( $wpdb->prepare(
            "select sum(amount) from $table_name where type = \"expense\" and account_id = %d and $date_condition",
            $account_id
        ) );

        $delta -= $wpdb->get_var( $wpdb->prepare(
            "select sum(amount) from $table_name where type = \"transfer\" and account_id = %d and $date_condition",
            $account_id
        ) );

        return (float) $delta;
    }
}