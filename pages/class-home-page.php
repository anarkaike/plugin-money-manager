<?php

namespace MoneyManager\Pages;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\I18n;
use MoneyManager\Managers\WooCommerce_Manager;
use MoneyManager\Page;
use MoneyManager\Sample_Data;

/**
 * Class Home_Page
 * @package MoneyManager
 */
class Home_Page extends Page
{
    /**
     * Init home page
     */
    public function init()
    {
        if ( isset ( $_GET['money-manager-import'] ) && $_GET['money-manager-import'] == 'sample-data' ) {
            if ( ! Sample_Data::imported() ) {
                $sample_data = new Sample_Data();
                $sample_data->import();
            }
            wp_redirect( remove_query_arg( 'money-manager-import' ) );
            exit;
        }
        $this->add_submenu_page(
            esc_html__( 'Home', 'money-manager' ),
            'money-manager-home'
        );
    }

    /**
     * Render home page
     */
    public function render_page()
    {
        echo '<div id="money-manager"></div>';
    }

    /**
     * Enqueue assets for home page
     */
    protected function enqueue_assets()
    {
        // Media Library
        wp_enqueue_media();

        $this->enqueue_script(
            'money-manager-app.min.js',
            plugins_url( 'js/app.min.js', MONEY_MANAGER_PLUGIN_FILE ),
            array( 'media-editor' )
        );
        $this->enqueue_style(
            'fontawesome.min.css',
            plugins_url( 'css/fontawesome.min.css', MONEY_MANAGER_PLUGIN_FILE )
        );
        $this->enqueue_style(
            'money-manager-app.min.css',
            plugins_url( 'css/app.min.css', MONEY_MANAGER_PLUGIN_FILE )
        );

        wp_localize_script(
            'money-manager-app.min.js',
            'MoneyManagerSettings',
            apply_filters(
                'money_manager_app_js_options',
                array(
                    'endpoint' => esc_url_raw( rest_url() ) . 'money-manager/v1',
                    'nonce' => wp_create_nonce( 'wp_rest' ),
                    'meta' => get_user_meta( get_current_user_id(), 'money_manager', true ) ?: array(),
                    'locale' => str_replace( '_', '-', get_locale() ),
                    'i18n' => I18n::getStrings(),
                ) + $this->get_wc_options()
            )
        );
    }

    /**
     * Get JS options for WooCommerce
     *
     * @return array
     */
    protected function get_wc_options()
    {
        if ( WooCommerce_Manager::active() ) {
            $payment_methods = array();
            $order_statuses = array();

            foreach ( WC()->payment_gateways->get_available_payment_gateways() as $gateway ) {
                $payment_methods[] = array(
                    'id' => $gateway->id,
                    'name' => $gateway->title,
                );
            }

            foreach ( wc_get_order_statuses() as $key => $name ) {
                $id = str_replace( 'wc-', '', $key );
                $order_statuses[] = compact( 'id', 'name' );
            }

            return array(
                'woocommerce' => true,
                'woocommerce_payment_methods' => $payment_methods,
                'woocommerce_order_statuses' => $order_statuses,
            );
        }

        return array(
            'woocommerce' => false,
        );
    }
}
