<?php

namespace MoneyManager\Pages;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\Page;

/**
 * Class Welcome_Page
 * @package MoneyManager
 */
class Welcome_Page extends Page
{
    /**
     * Init welcome page
     */
    public function init()
    {
        if ( isset ( $_GET['page'] ) && $_GET['page'] == 'money-manager-welcome' ) {
            $this->add_submenu_page(
                esc_html__( 'Welcome', 'money-manager' ),
                'money-manager-welcome'
            );
        }
    }

    /**
     * Render welcome page
     */
    public function render_page()
    {
        require dirname( MONEY_MANAGER_PLUGIN_FILE ) . '/views/welcome.php';
    }

    /**
     * Enqueue assets for welcome page
     */
    protected function enqueue_assets()
    {
        $this->enqueue_style(
            'fontawesome.min.css',
            plugins_url( 'css/fontawesome.min.css', MONEY_MANAGER_PLUGIN_FILE )
        );
        $this->enqueue_style(
            'money-manager-app.min.css',
            plugins_url( 'css/app.min.css', MONEY_MANAGER_PLUGIN_FILE )
        );
        $this->enqueue_style(
            'money-manager-welcome.css',
            plugins_url( 'css/welcome.css', MONEY_MANAGER_PLUGIN_FILE )
        );
    }
}
