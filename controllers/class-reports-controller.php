<?php

namespace MoneyManager\Controllers;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\Models\Account;
use MoneyManager\Models\Currency;
use MoneyManager\Models\Quote;
use MoneyManager\Models\Transaction;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Reports_Controller
 * @package MoneyManager\Controllers
 */
class Reports_Controller extends Base_Controller
{
    /**
     * Register routes
     */
    public function register_routes()
    {
        $this->get( '/reports/cash-flow', 'cash_flow' );
        $this->get( '/reports/income-expenses', 'income_expenses' );
    }

    /**
     * Load data for cash flow report
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function cash_flow( WP_REST_Request $request )
    {
        $currency = $request->get_param( 'currency' ) ?: 'BRL';
        $range = $request->get_param( 'range' );
        $group_by = $request->get_param( 'group_by' );
        $account_id = $request->get_param( 'account_id' );
        $include_transfers = $request->get_param( 'include_transfers' );

        return rest_ensure_response( array(
            'result' => $this->get_report_data( $currency, $range, $group_by, $account_id, $include_transfers )
        ) );
    }

    /**
     * Load data for income/expenses report
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function income_expenses( WP_REST_Request $request )
    {
        $currency = $request->get_param( 'currency' ) ?: 'BRL';
        $ranges = $request->get_param( 'ranges' ) ?: array();
        $group_by = $request->get_param( 'group_by' );

        $result = array();
        foreach ( $ranges as $range ) {
            $data = $this->get_report_data( $currency, $range, $group_by, null, false );
            $data['range'] = $range;
            $result[] = $data;
        }

        return rest_ensure_response( compact( 'result' ) );
    }

    /**
     * Get data for report
     *
     * @param string $currency
     * @param string $range
     * @param string $group_by
     * @param int|null $account_id
     * @param bool $include_transfers
     * @return array
     */
    protected function get_report_data( $currency, $range, $group_by, $account_id, $include_transfers )
    {
        global $wpdb;

        // Apply range
        $date_condition = '1';
        if ( $range !== null ) {
            switch ( $range ) {
                case 'recent_12_months':
                    $date_condition = $wpdb->prepare(
                        't.date >= %s',
                        current_datetime()->modify( 'first day of 11 months ago' )->format( 'Y-m-d' )
                    );
                    break;
                case 'this_year':
                    $date_condition = $wpdb->prepare(
                        't.date between %s and %s',
                        current_datetime()->modify( 'first day of January this year' )->format( 'Y-m-d' ),
                        current_datetime()->modify( 'last day of December this year' )->format( 'Y-m-d' )
                    );
                    break;
                case 'last_year':
                    $date_condition = $wpdb->prepare(
                        't.date between %s and %s',
                        current_datetime()->modify( 'first day of January last year' )->format( 'Y-m-d' ),
                        current_datetime()->modify( 'last day of December last year' )->format( 'Y-m-d' )
                    );
                    break;
                default:
                    $years = explode( '_', $range )[0];
                    $date_condition = $wpdb->prepare(
                        't.date between %s and %s',
                        current_datetime()->modify( sprintf( '%d years ago first day of January', $years ) )->format( 'Y-m-d' ),
                        current_datetime()->modify( sprintf( '%d years ago last day of December', $years ) )->format( 'Y-m-d' )
                    );
            }
        }
        // Apply account filter
        $account_condition = '1';
        if ( $account_id !== null ) {
            $account_condition = $wpdb->prepare(
                '(t.account_id = %1$d or t.to_account_id = %1$d)',
                $account_id
            );
        }
        // Apply grouping
        switch ( $group_by ) {
            case 'month':
                $group_id = "date_format(tmp.date, '%%Y-%%c') as id,";
                $group_by_id = 'id,';
                break;
            case 'party':
                $group_id = 'tmp.party_id,';
                $group_by_id = 'tmp.party_id,';
                break;
            default:
                $group_id = '';
                $group_by_id = '';
        }

        $quotes_table_name = Quote::table_name();
        $transactions_table_name = Transaction::table_name();
        $accounts_table_name = Account::table_name();
        $currencies_table_name = Currency::table_name();
        $split_txn = apply_filters( 'money_manager_split_txn_sql', array(
            'join' => 'left join (select null transaction_id, null category_id, null amount) st on st.transaction_id = t.id'
        ) );
        $query =
            "select
                $group_id
                tmp.category_id,
                sum(tmp.amount * coalesce(tmp.quote1, tmp.default_quote1) / coalesce(tmp.quote2, tmp.default_quote2)) as amount
            from (
                select
                    t.date,
                    coalesce(st.amount, t.amount) as amount,
                    coalesce(st.category_id, t.category_id) as category_id,
                    t.party_id,
                    c1.default_quote as default_quote1,
                    c2.default_quote as default_quote2,
                    (
                        select q1.value from $quotes_table_name q1
                        where q1.currency = c1.code and q1.date <= t.date
                        order by q1.date desc
                        limit 1
                    ) as quote1,
                    (
                        select q2.value from $quotes_table_name q2
                        where q2.currency = c2.code and q2.date <= t.date
                        order by q2.date desc
                        limit 1
                    ) as quote2
                from $transactions_table_name t
                left join $accounts_table_name a on a.id = t.account_id
                left join $currencies_table_name c1 on c1.code = a.currency
                left join $currencies_table_name c2 on c2.code = %s
                {$split_txn['join']}
                where (t.type = %s or t.type = %s and (t.account_id = %d or t.to_account_id = %d))
                    and $date_condition
                    and $account_condition
            ) tmp
            group by $group_by_id tmp.category_id"
        ;

        $income = $wpdb->get_results( $wpdb->prepare(
            $query,
            $currency,
            'income',
            'transfer',
            -1,
            $include_transfers && $account_id ? $account_id : -1
        ) );
        $expenses = $wpdb->get_results( $wpdb->prepare(
            $query,
            $currency,
            'expense',
            'transfer',
            $include_transfers && $account_id ? $account_id : -1,
            -1
        ) );

        $cast = function ( &$row ) {
            $row->amount = (double) $row->amount;
            if ( $row->category_id ) {
                $row->category_id = (int) $row->category_id;
            }
            if ( isset ( $row->party_id ) ) {
                $row->party_id = (int) $row->party_id;
            }
        };
        array_walk( $income, $cast );
        array_walk( $expenses, $cast );

        // Let add-ons add their data
        return apply_filters(
            'money_manager_cash_flow_report_data',
            compact( 'income', 'expenses' ),
            $currency,
            $date_condition,
            $group_by,
            $account_id,
            $include_transfers
        );
    }
}