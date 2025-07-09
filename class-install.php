<?php

namespace MoneyManager;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\Managers\WooCommerce_Manager;

/**
 * Class Install
 * @package MoneyManager
 */
class Install
{
    /**
     * Check whether the plugin has ever been installed
     *
     * @return bool
     */
    public function installed()
    {
        return get_option( 'money_manager_version' ) !== false;
    }

    /**
     * Install the plugin
     */
    public function install()
    {
        if ( get_transient( 'money_manager_installing' ) == 'yes' ) {
            return;
        }

        set_transient( 'money_manager_installing', 'yes', MINUTE_IN_SECONDS * 10 );

        $this->create_tables();
        $this->create_options();
        $this->create_fixtures();

        delete_transient( 'money_manager_installing' );
    }

    /**
     * Uninstall the plugin
     */
    public function uninstall()
    {
        $this->drop_tables();
        $this->drop_options_and_meta();
    }

    /**
     * Create tables in database
     */
    protected function create_tables()
    {
        global $wpdb;

        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            $collate = $wpdb->get_charset_collate();
        }

        /**
         * Some users are still using MySQL version <5.7, so we limit the length of the index
         * @see https://wordpress.org/support/topic/help-needed-102/
         * @see https://dev.mysql.com/doc/refman/5.7/en/innodb-parameters.html#sysvar_innodb_large_prefix
         */
        $max_index_length = 191;

        $wpdb->query( "
            create table {$wpdb->prefix}money_manager_categories (
                id bigint unsigned not null auto_increment primary key,
                parent_id bigint unsigned null,
                title varchar(255) not null,
                color varchar(7) not null,
                created_at timestamp null,
                updated_at timestamp null,
                key {$wpdb->prefix}money_manager_cat_title_index (title($max_index_length)),
                constraint {$wpdb->prefix}money_manager_cat_parent_id_foreign
                    foreign key (parent_id) references {$wpdb->prefix}money_manager_categories (id)
                        on delete cascade
            ) engine = innodb $collate" );
        $wpdb->query( "create table {$wpdb->prefix}money_manager_parties (
                id bigint unsigned auto_increment primary key,
                title varchar(255) not null,
                default_category_id bigint unsigned null,
                color varchar(7) not null,
                created_at timestamp null,
                updated_at timestamp null,
                key {$wpdb->prefix}money_manager_prt_title_index (title($max_index_length)),
                constraint {$wpdb->prefix}money_manager_prt_def_cat_id_foreign
                    foreign key (default_category_id) references {$wpdb->prefix}money_manager_categories (id)
                        on delete set null
            ) engine = innodb $collate" );
        $wpdb->query( "
            create table {$wpdb->prefix}money_manager_accounts (
                id bigint unsigned auto_increment primary key,
                title varchar(255) null,
                type enum('checking', 'card', 'cash', 'debt', 'crypto') not null,
                currency varchar(8) not null,
                balance decimal(15,3) default 0.000 null,
                initial_balance decimal(15,3) default 0.000 not null,
                notes text null,
                color varchar(7) not null,
                created_at timestamp null,
                updated_at timestamp null,
                key {$wpdb->prefix}money_manager_acc_title_index (title($max_index_length))
            ) engine = innodb $collate" );
        $wpdb->query( "
            create table {$wpdb->prefix}money_manager_transactions (
                id bigint unsigned auto_increment primary key,
                account_id bigint unsigned not null,
                to_account_id bigint unsigned null,
                party_id bigint unsigned null,
                category_id bigint unsigned null,
                wc_order_id bigint unsigned null,
                date date not null,
                type enum('transfer', 'income', 'expense') not null,
                amount decimal(15,3) default 0.000 not null,
                to_amount decimal(15,3) null,
                notes text null,
                created_at timestamp null,
                updated_at timestamp null,
                key {$wpdb->prefix}money_manager_txn_date_index (date),
                key {$wpdb->prefix}money_manager_txn_wc_order_id_index (wc_order_id),
                constraint {$wpdb->prefix}money_manager_txn_acc_id_foreign
                    foreign key (account_id) references {$wpdb->prefix}money_manager_accounts (id)
                        on delete cascade,
                constraint {$wpdb->prefix}money_manager_txn_to_acc_id_foreign
                    foreign key (to_account_id) references {$wpdb->prefix}money_manager_accounts (id)
                        on delete cascade,
                constraint {$wpdb->prefix}money_manager_txn_prt_id_foreign
                    foreign key (party_id) references {$wpdb->prefix}money_manager_parties (id)
                        on delete set null,
                constraint {$wpdb->prefix}money_manager_txn_cat_id_foreign
                    foreign key (category_id) references {$wpdb->prefix}money_manager_categories (id)
                        on delete set null
            ) engine = innodb $collate" );
        $wpdb->query( "
            create table {$wpdb->prefix}money_manager_currencies (
                id bigint unsigned auto_increment primary key,
                code varchar(8) not null,
                is_base tinyint(1) default 0 not null,
                default_quote double unsigned default '1' not null,
                color varchar(7) not null,
                created_at timestamp null,
                updated_at timestamp null,
                constraint {$wpdb->prefix}money_manager_curr_code_unique unique (code)
            ) engine = innodb $collate" );
        $wpdb->query( "
            create table {$wpdb->prefix}money_manager_quotes (
                id bigint unsigned auto_increment primary key,
                currency varchar(8) not null,
                date date not null,
                value double unsigned default '1' not null,
                created_at timestamp null,
                updated_at timestamp null,
                key {$wpdb->prefix}money_manager_qte_curr_date_index (currency, date),
                constraint {$wpdb->prefix}money_manager_qte_curr_foreign
                    foreign key (currency) references {$wpdb->prefix}money_manager_currencies (code)
                        on delete cascade
            ) engine = innodb $collate" );
        $wpdb->query( "
            create table {$wpdb->prefix}money_manager_files (
                id bigint unsigned auto_increment primary key,
                account_id bigint unsigned null,
                transaction_id bigint unsigned null,
                attachment_id bigint unsigned not null,
                filename varchar(255) not null,
                description text null,
                url varchar(255) not null,
                created_at timestamp null,
                updated_at timestamp null,
                key {$wpdb->prefix}money_manager_file_att_id_index (attachment_id),
                constraint {$wpdb->prefix}money_manager_file_acc_id_foreign
                    foreign key (account_id) references {$wpdb->prefix}money_manager_accounts (id)
                        on delete cascade,
                constraint {$wpdb->prefix}money_manager_file_txn_id_foreign
                    foreign key (transaction_id) references {$wpdb->prefix}money_manager_transactions (id)
                        on delete cascade
            ) engine = innodb $collate" );
        $wpdb->query( "
            create table {$wpdb->prefix}money_manager_budgets (
                id bigint unsigned auto_increment primary key,
                date date not null,
                type enum('income', 'expenses') not null,
                amount decimal(15,3) default 0.000 null,
                currency varchar(8) not null,
                created_at timestamp null,
                updated_at timestamp null,
                key {$wpdb->prefix}money_manager_bgt_date_index (date)
            ) engine = innodb $collate" );
        $wpdb->query( "
            create table {$wpdb->prefix}money_manager_recurring_transactions (
                id bigint unsigned auto_increment primary key,
                next_due_date date null,
                pattern varchar(255) not null,
                account_id bigint unsigned null,
                to_account_id bigint unsigned null,
                party_id bigint unsigned null,
                category_id bigint unsigned null,
                type enum('transfer', 'income', 'expense') not null,
                amount decimal(15,3) default 0.000 not null,
                to_amount decimal(15,3) null,
                notes text null,
                created_at timestamp null,
                updated_at timestamp null,
                key {$wpdb->prefix}money_manager_rec_txn_ndd_index (next_due_date),
                constraint {$wpdb->prefix}money_manager_rec_txn_acc_id_foreign
                    foreign key (account_id) references {$wpdb->prefix}money_manager_accounts (id)
                        on delete set null,
                constraint {$wpdb->prefix}money_manager_rec_txn_to_acc_id_foreign
                    foreign key (to_account_id) references {$wpdb->prefix}money_manager_accounts (id)
                        on delete set null,
                constraint {$wpdb->prefix}money_manager_rec_txn_prt_id_foreign
                    foreign key (party_id) references {$wpdb->prefix}money_manager_parties (id)
                        on delete set null,
                constraint {$wpdb->prefix}money_manager_rec_txn_cat_id_foreign
                    foreign key (category_id) references {$wpdb->prefix}money_manager_categories (id)
                        on delete set null
            ) engine = innodb $collate" );
        $wpdb->query( "
            create table {$wpdb->prefix}money_manager_notifications (
                id bigint unsigned auto_increment primary key,
                recurring_transaction_id bigint unsigned null,
                method enum('email') not null,
                enabled tinyint(1) default 0 not null,
                `to` varchar(255) null,
                subject varchar(255) null,
                message text null,
                schedule varchar(255) not null,
                next_date date null,
                sent_at timestamp null,
                created_at timestamp null,
                updated_at timestamp null,
                constraint {$wpdb->prefix}money_manager_notif_rec_txn_id_foreign
                    foreign key (recurring_transaction_id) references {$wpdb->prefix}money_manager_recurring_transactions (id)
                        on delete cascade
            ) engine = innodb $collate" );
        $wpdb->query( "
            create table {$wpdb->prefix}money_manager_split_transactions (
                id bigint unsigned auto_increment primary key,
                transaction_id bigint unsigned not null,
                category_id bigint unsigned null,
                amount decimal(15,3) default 0.000 not null,
                notes text null,
                created_at timestamp null,
                updated_at timestamp null,
                constraint {$wpdb->prefix}money_manager_split_txn_txn_id_foreign
                    foreign key (transaction_id) references {$wpdb->prefix}money_manager_transactions (id)
                        on delete cascade,
                constraint {$wpdb->prefix}money_manager_split_txn_cat_id_foreign
                    foreign key (category_id) references {$wpdb->prefix}money_manager_categories (id)
                        on delete set null
            ) engine = innodb $collate" );
    }

    /**
     * Create options
     */
    protected function create_options()
    {
        add_option( 'money_manager_version', MoneyManager()->version );
        add_option( 'money_manager_woocommerce', WooCommerce_Manager::settings() );
    }

    /**
     * Create fixtures in database
     */
    protected function create_fixtures()
    {
        $usd = new Models\Currency( array(
            'code' => 'BRL',
            'is_base' => true,
            'color' => '#cc86a6',
        ) );
        $usd->save();
    }

    /**
     * Drop tables in database
     */
    protected function drop_tables()
    {
        global $wpdb;

        $wpdb->query( "drop table if exists {$wpdb->prefix}money_manager_split_transactions" );
        $wpdb->query( "drop table if exists {$wpdb->prefix}money_manager_notifications" );
        $wpdb->query( "drop table if exists {$wpdb->prefix}money_manager_recurring_transactions" );
        $wpdb->query( "drop table if exists {$wpdb->prefix}money_manager_budgets" );
        $wpdb->query( "drop table if exists {$wpdb->prefix}money_manager_files" );
        $wpdb->query( "drop table if exists {$wpdb->prefix}money_manager_quotes" );
        $wpdb->query( "drop table if exists {$wpdb->prefix}money_manager_currencies" );
        $wpdb->query( "drop table if exists {$wpdb->prefix}money_manager_transactions" );
        $wpdb->query( "drop table if exists {$wpdb->prefix}money_manager_accounts" );
        $wpdb->query( "drop table if exists {$wpdb->prefix}money_manager_parties" );
        $wpdb->query( "drop table if exists {$wpdb->prefix}money_manager_categories" );
    }

    /**
     * Delete options and user meta
     */
    protected function drop_options_and_meta()
    {
        global $wpdb;

        $wpdb->query( "delete from {$wpdb->options} where option_name like 'money\\_manager%'" );
        $wpdb->query( "delete from {$wpdb->usermeta} where meta_key like 'money\\_manager%'" );
    }
}