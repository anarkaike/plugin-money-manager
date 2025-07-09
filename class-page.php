<?php

namespace MoneyManager;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

/**
 * Class Page
 * @package MoneyManager
 */
abstract class Page
{
    private $hook_suffix;

    /**
     * Init admin page
     */
    abstract public function init();

    /**
     * Render admin page
     */
    abstract public function render_page();

    /**
     * Enqueue assets for admin page
     */
    abstract protected function enqueue_assets();

    /**
     * Add submenu page
     */
    protected function add_submenu_page( $menu_title, $menu_slug )
    {
        $this->hook_suffix = add_submenu_page(
            'money-manager',
            esc_html__( 'Money Manager', 'money-manager' ),
            $menu_title,
            'manage_options',
            $menu_slug,
            array( $this, 'render_page' )
        );

        add_action( 'admin_enqueue_scripts', function ( $hook_suffix ) {
            if ( $hook_suffix == $this->hook_suffix ) {
                $this->enqueue_assets();
            }
        } );
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
        wp_enqueue_script( $handle, $src, $deps, MoneyManager()->version );
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
        wp_enqueue_style( $handle, $src, $deps, MoneyManager()->version );
    }
}