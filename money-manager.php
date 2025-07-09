<?php
/*
Plugin Name: Money Manager
Description: Finance software that keeps track of where, when and how the money goes.
Version: 1.32.0
License: GPLv2 or later
Text Domain: money-manager
*/

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

if ( ! defined( 'MONEY_MANAGER_PLUGIN_FILE' ) ) {
    define( 'MONEY_MANAGER_PLUGIN_FILE', __FILE__ );
}

spl_autoload_register( function( $class_name ) {
    if ( preg_match( '/^\\\\?MoneyManager(\\\\(?:\w+\\\\)*)(\w+)$/', $class_name, $match ) ) {
        $file = __DIR__
            . strtolower(
                str_replace( '\\', DIRECTORY_SEPARATOR, $match[1] )
                . 'class-'
                .  str_replace( '_', '-', $match[2] )
            )
            . '.php';
        if ( is_readable( $file ) ) {
            require_once $file;
        }
    }
});

MoneyManager()->run();

function MoneyManager() {
    static $app;

    if ( !$app ) {
        $app = new MoneyManager\App();
    }

    return $app;
}
