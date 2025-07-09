<?php

namespace MoneyManager\Models;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

/**
 * Class Category
 * @package MoneyManager\Models
 */
class Category extends Base
{
    protected static $table = 'money_manager_categories';

    protected static $fillable = [
        'title',
        'parent_id',
        'color',
    ];

    protected static $hidden = [
        'created_at',
        'updated_at',
    ];

    protected static $casts = [
        'parent_id' => 'int',
    ];
}