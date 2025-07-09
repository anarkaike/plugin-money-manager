<?php

namespace MoneyManager;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\Managers\File_Manager;
use MoneyManager\Managers\Quote_Manager;
use MoneyManager\Managers\WooCommerce_Manager;

/**
 * Class App
 * @package MoneyManager
 */
class App
{
    public $version = '1.32.0';

    /**
     * Run the plugin
     */
    public function run()
    {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
        add_action( 'activated_plugin', array( $this, 'activated_plugin' ) );
        add_action( 'init', array( $this, 'init' ) );

        // Init hourly task
        add_action( 'money_manager_hourly_task', array( Quote_Manager::class, 'fetch_history' ) );
        if ( ! wp_next_scheduled( 'money_manager_hourly_task' ) ) {
            wp_schedule_event( time(), 'hourly', 'money_manager_hourly_task' );
        }

        // Activation/deactivation hooks
        register_activation_hook( MONEY_MANAGER_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( MONEY_MANAGER_PLUGIN_FILE, array( $this, 'deactivate' ) );
        register_uninstall_hook( MONEY_MANAGER_PLUGIN_FILE, array( __CLASS__, 'uninstall' ) );

        // Additional multisite hooks
        ( new Multisite() )->register_hooks();

        // Let managers init their hooks
        File_Manager::init();
        WooCommerce_Manager::init();
    }

    /**
     * Add items to admin menu
     */
    public function admin_menu()
    {
        global $submenu;

        add_menu_page(
            esc_html__( 'Money Manager', 'money-manager' ),
            esc_html__( 'Money Manager', 'money-manager' ),
            'manage_options',
            'money-manager',
            '',
            'dashicons-money-alt',
            2
        );
        ( new Pages\Welcome_Page() )->init();
        ( new Pages\Home_Page() )->init();

        unset ( $submenu['money-manager'][0] );

        do_action( 'money_manager_admin_menu' );

        ( new Pages\Addons_Page() )->init();
    }

    /**
     * Init controllers
     */
    public function rest_api_init()
    {
        ( new Controllers\Accounts_Controller() )->register_routes();
        ( new Controllers\Addons_Controller() )->register_routes();
        ( new Controllers\App_Controller() )->register_routes();
        ( new Controllers\Categories_Controller() )->register_routes();
        ( new Controllers\Currencies_Controller() )->register_routes();
        ( new Controllers\Parties_Controller() )->register_routes();
        ( new Controllers\Quotes_Controller() )->register_routes();
        ( new Controllers\Reports_Controller() )->register_routes();
        ( new Controllers\Transactions_Controller() )->register_routes();
        ( new Controllers\WooCommerce_Controller() )->register_routes();
    }

    /**
     * Run updater on init
     */
    public function init()
    {
        $db = new Update();
        if ( ! $db->up_to_date() ) {
            $db->update();
        }
    }

    /**
     * Redirect to Welcome page after plugin activation
     *
     * @param string $plugin
     */
    public function activated_plugin( $plugin )
    {
        if ( $plugin == plugin_basename( MONEY_MANAGER_PLUGIN_FILE ) ) {
            wp_redirect( admin_url('admin.php?page=money-manager-welcome') );
            exit;
        }
    }

    /**
     * Handle plugin activation hook
     */
    public function activate( $network_wide )
    {
        if ( $network_wide ) {
            Multisite::run_for_all_blogs( array( $this, 'do_activate') );
        } else {
            $this->do_activate();
        }
    }

    /**
     * Activate the plugin
     */
    public function do_activate()
    {
        $db = new Install();
        if ( ! $db->installed() ) {
            $db->install();
        }
    }

    /**
     * Handle plugin deactivation hook
     */
    public function deactivate( $network_wide )
    {
        if ( $network_wide ) {
            Multisite::run_for_all_blogs( array( $this, 'do_deactivate') );
        } else {
            $this->do_deactivate();
        }
    }

    /**
     * Deactivate the plugin
     */
    public function do_deactivate()
    {
        // Disable hourly task
        $timestamp = wp_next_scheduled( 'money_manager_hourly_task' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'money_manager_hourly_task' );
        }
        // Disable daily task
        $timestamp = wp_next_scheduled( 'money_manager_daily_task' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'money_manager_daily_task' );
        }
    }

    /**
     * Handle plugin uninstall hook (WP requires this method to be static)
     */
    public static function uninstall()
    {
        if ( is_multisite() ) {
            Multisite::run_for_all_blogs( array( __CLASS__, 'do_uninstall') );
        } else {
            self::do_uninstall();
        }
    }

    /**
     * Run uninstall routine
     */
    public static function do_uninstall()
    {
        $db = new Install();
        if ( $db->installed() ) {
            $db->uninstall();
        }
    }

    /**
     * Enqueue a script
     *
     * @param string $handle
     * @param string $src
     * @param array $deps
     */
    protected function enqueue_script( $handle, $src = '', $deps = array() )
    {
        wp_enqueue_script( $handle, $src, $deps, $this->version );
    }

    /**
     * Enqueue a CSS stylesheet
     *
     * @param string $handle
     * @param string $src
     * @param array $deps
     */
    protected function enqueue_style( $handle, $src = '', $deps = array() )
    {
        wp_enqueue_style( $handle, $src, $deps, $this->version );
    }
}