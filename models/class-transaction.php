<?php

namespace MoneyManager\Models;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

/**
 * Class Transaction
 * @package MoneyManager\Models
 */
class Transaction extends Base
{
    protected static $table = 'money_manager_transactions';

    protected static $fillable = [
        'account_id',
        'to_account_id',
        'party_id',
        'category_id',
        'date',
        'type',
        'amount',
        'to_amount',
        'notes',
    ];

    protected static $hidden = [
        'wc_order_id',
        'created_at',
        'updated_at',
    ];

    protected static $casts = [
        'account_id' => 'int',
        'to_account_id' => 'int',
        'party_id' => 'int',
        'category_id' => 'int',
        'wc_order_id' => 'int',
    ];
}