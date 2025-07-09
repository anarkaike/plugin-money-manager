<?php

namespace MoneyManager;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use WP_Site;

/**
 * Class Multisite
 * @package MoneyManager
 */
class Multisite
{
    /**
     * Register multisite hooks
     */
    public function register_hooks()
    {
        add_action( 'wp_initialize_site', array( $this, 'initialize_site' ), 10, 2 );
        add_action( 'wp_uninitialize_site', array( $this, 'uninitialize_site' ) );
    }

    /**
     * Activate the plugin when a new site is created
     *
     * @param WP_Site $new_site
     * @param array $args
     */
    public function initialize_site( WP_Site $new_site, array $args )
    {
        switch_to_blog( $new_site->blog_id );
        MoneyManager()->do_activate();
        restore_current_blog();
    }

    /**
     * Deactivate and uninstall the plugin when an old site is deleted
     *
     * @param WP_Site $old_site
     */
    public function uninitialize_site( WP_Site $old_site )
    {
        switch_to_blog( $old_site->blog_id );
        MoneyManager()->do_deactivate();
        App::uninstall();
        restore_current_blog();
    }

    /**
     * Run a function for all blogs within the network
     *
     * @param callable $callable
     */
    public static function run_for_all_blogs( $callable )
    {
        $blog_ids = get_sites( array( 'fields' => 'ids', 'number' => PHP_INT_MAX ) );
        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            call_user_func( $callable );
            restore_current_blog();
        }
    }
}
