<?php

namespace MoneyManager;

defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

/**
 * Class Update
 * @package MoneyManager
 */
class Update
{
    public function update_1_27_0()
    {
        global $wpdb;

        $wpdb->query("
            alter table {$wpdb->prefix}money_manager_recurring_transactions
                modify next_due_date date null
        ");
    }

    public function update_1_26_1()
    {
        global $wpdb;

        // Fix `files` table in MySQL 5.7
        $columns = $wpdb->get_col( "show columns from {$wpdb->prefix}money_manager_files" );
        if ( ! empty ( $columns ) ) {
            if ( ! in_array( 'account_id', $columns ) ) {
                $wpdb->query( "
                    alter table {$wpdb->prefix}money_manager_files
                        add account_id bigint unsigned null after id
                " );
                $wpdb->query( "
                    alter table {$wpdb->prefix}money_manager_files
                        add constraint {$wpdb->prefix}money_manager_file_acc_id_foreign
                            foreign key (account_id) references {$wpdb->prefix}money_manager_accounts (id)
                                on delete cascade
                " );
            }
            if ( ! in_array( 'transaction_id', $columns ) ) {
                $wpdb->query( "
                    alter table {$wpdb->prefix}money_manager_files
                        add transaction_id bigint unsigned null after account_id
                " );
                $wpdb->query( "
                    alter table {$wpdb->prefix}money_manager_files
                        add constraint {$wpdb->prefix}money_manager_file_txn_id_foreign
                            foreign key (transaction_id) references {$wpdb->prefix}money_manager_transactions (id)
                                on delete cascade
                " );
            }
            if ( ! in_array( 'attachment_id', $columns ) ) {
                $wpdb->query( "
                    alter table {$wpdb->prefix}money_manager_files
                        add attachment_id bigint unsigned not null after transaction_id
                " );
                $wpdb->query( "
                    create index {$wpdb->prefix}money_manager_file_att_id_index
                        on {$wpdb->prefix}money_manager_files (attachment_id)
                " );
            }
            if ( in_array( 'path', $columns ) ) {
                $wpdb->query( "
                    alter table {$wpdb->prefix}money_manager_files
                        change path url varchar(255) not null
                " );
            }
            if ( in_array( 'ref', $columns ) ) {
                $wpdb->query( "
                    alter table {$wpdb->prefix}money_manager_files
                        drop column ref
                " );
            }
            if ( in_array( 'ref_id', $columns ) ) {
                $wpdb->query( "
                    alter table {$wpdb->prefix}money_manager_files
                        drop column ref_id
                " );
            }
        }
    }

    public function update_1_26_0()
    {
        global $wpdb;

        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            $collate = $wpdb->get_charset_collate();
        }

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

    public function update_1_25_0()
    {
        global $wpdb;

        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            $collate = $wpdb->get_charset_collate();
        }

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
    }

    public function update_1_23_0()
    {
        global $wpdb;

        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            $collate = $wpdb->get_charset_collate();
        }

        $wpdb->query( "
            create table {$wpdb->prefix}money_manager_recurring_transactions (
                id bigint unsigned auto_increment primary key,
                next_due_date date not null,
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
    }

    /**
     * Updater v1.22.0
     */
    public function update_1_22_0()
    {
        global $wpdb;

        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_categories
                rename key categories_title_index to {$wpdb->prefix}money_manager_cat_title_index,
                drop foreign key categories_parent_id_foreign,
                drop key categories_parent_id_foreign,
                add constraint {$wpdb->prefix}money_manager_cat_parent_id_foreign
                    foreign key (parent_id) references {$wpdb->prefix}money_manager_categories (id)
                        on delete cascade
        " );
        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_parties
                rename key parties_title_index to {$wpdb->prefix}money_manager_prt_title_index,
                drop foreign key parties_default_category_id_foreign,
                drop key parties_default_category_id_foreign,
                add constraint {$wpdb->prefix}money_manager_prt_def_cat_id_foreign
                    foreign key (default_category_id) references {$wpdb->prefix}money_manager_categories (id)
                        on delete set null
        " );
        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_accounts
                rename key accounts_title_index to {$wpdb->prefix}money_manager_acc_title_index
        " );
        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_transactions
                rename key transactions_date_index to {$wpdb->prefix}money_manager_txn_date_index,
                rename key transactions_wc_order_id_index to {$wpdb->prefix}money_manager_txn_wc_order_id_index,
                drop foreign key transactions_account_id_foreign,
                drop key transactions_account_id_foreign,
                add constraint {$wpdb->prefix}money_manager_txn_acc_id_foreign
                    foreign key (account_id) references {$wpdb->prefix}money_manager_accounts (id)
                        on delete cascade,
                drop foreign key transactions_to_account_id_foreign,
                drop key transactions_to_account_id_foreign,
                add constraint {$wpdb->prefix}money_manager_txn_to_acc_id_foreign
                    foreign key (to_account_id) references {$wpdb->prefix}money_manager_accounts (id)
                        on delete cascade,
                drop foreign key transactions_party_id_foreign,
                drop key transactions_party_id_foreign,
                add constraint {$wpdb->prefix}money_manager_txn_prt_id_foreign
                    foreign key (party_id) references {$wpdb->prefix}money_manager_parties (id)
                        on delete set null,
                drop foreign key transactions_category_id_foreign,
                drop key transactions_category_id_foreign,
                add constraint {$wpdb->prefix}money_manager_txn_cat_id_foreign
                    foreign key (category_id) references {$wpdb->prefix}money_manager_categories (id)
                        on delete set null
        " );
        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_currencies
                rename key currencies_code_unique to {$wpdb->prefix}money_manager_curr_code_unique
        " );
        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_quotes
                rename key quotes_currency_date_index to {$wpdb->prefix}money_manager_qte_curr_date_index,
                drop foreign key quotes_currency_foreign,
                add constraint {$wpdb->prefix}money_manager_qte_curr_foreign
                    foreign key (currency) references {$wpdb->prefix}money_manager_currencies (code)
                        on delete cascade
        " );
        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_files
                rename key files_attachment_id_index to {$wpdb->prefix}money_manager_file_att_id_index,
                drop foreign key files_account_id_foreign,
                drop key files_account_id_foreign,
                add constraint {$wpdb->prefix}money_manager_file_acc_id_foreign
                    foreign key (account_id) references {$wpdb->prefix}money_manager_accounts (id)
                        on delete cascade,
                drop foreign key files_transaction_id_foreign,
                drop key files_transaction_id_foreign,
                add constraint {$wpdb->prefix}money_manager_file_txn_id_foreign
                    foreign key (transaction_id) references {$wpdb->prefix}money_manager_transactions (id)
                        on delete cascade
        " );
        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_budgets
                rename key budgets_date_index to {$wpdb->prefix}money_manager_bgt_date_index
        " );
    }

    /**
     * Updater v1.21.0
     */
    public function update_1_21_0()
    {
        global $wpdb;

        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            $collate = $wpdb->get_charset_collate();
        }

        $wpdb->query( "
            create table {$wpdb->prefix}money_manager_budgets (
                id bigint unsigned auto_increment primary key,
                date date not null,
                type enum('income', 'expenses') not null,
                amount decimal(15,3) default 0.000 null,
                currency varchar(8) not null,
                created_at timestamp null,
                updated_at timestamp null,
                key budgets_date_index (date)
            ) engine = innodb $collate" );
    }

    /**
     * Updater v1.18.0
     */
    public function update_1_18_0()
    {
        global $wpdb;

        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_transactions
                add wc_order_id bigint unsigned null after category_id,
                add key transactions_wc_order_id_index (wc_order_id)
        " );
    }

    /**
     * Updater v1.17.0
     */
    public function update_1_17_0()
    {
        global $wpdb;

        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_parties
                add color varchar(7) not null default '#ff7700' after default_category_id
        " );
        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_parties
                alter column color drop default
        " );
    }

    /**
     * Updater v1.15.0
     */
    public function update_1_15_0()
    {
        global $wpdb;

        $wpdb->query( "
            drop index files_ref_ref_id_index on {$wpdb->prefix}money_manager_files;
        " );
        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_files
                add account_id bigint unsigned null after id,
                add transaction_id bigint unsigned null after account_id,
                add attachment_id bigint unsigned not null after transaction_id,
                change path url varchar(255) not null,
                drop column ref,
                drop column ref_id,
                add constraint files_account_id_foreign
                    foreign key (account_id) references {$wpdb->prefix}money_manager_accounts (id)
                        on delete cascade,
                add constraint files_transaction_id_foreign
                    foreign key (transaction_id) references {$wpdb->prefix}money_manager_transactions (id)
                        on delete cascade
        " );
        $wpdb->query( "
            create index files_attachment_id_index
                on {$wpdb->prefix}money_manager_files (attachment_id)
        " );
        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_accounts
                modify type enum ('checking', 'card', 'cash', 'debt', 'crypto') not null
        " );
        $wpdb->query( "
            update {$wpdb->prefix}money_manager_accounts
                set type = 'crypto' where type = ''
        " );
    }

    /**
     * Updater v1.12.0
     */
    public function update_1_12_0()
    {
        global $wpdb;

        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_quotes
                drop foreign key quotes_currency_foreign,
                modify currency varchar(8) null
        " );
        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_currencies
                modify code varchar(8) not null
        " );
        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_accounts
                modify currency varchar(8) not null
        " );
        $wpdb->query( "update {$wpdb->prefix}money_manager_quotes set currency = upper(currency)" );
        $wpdb->query( "update {$wpdb->prefix}money_manager_currencies set code = upper(code)" );
        $wpdb->query( "update {$wpdb->prefix}money_manager_accounts set currency = upper(currency)" );
        $wpdb->query( "
            alter table {$wpdb->prefix}money_manager_quotes
                add constraint quotes_currency_foreign
                    foreign key (currency) references {$wpdb->prefix}money_manager_currencies (code)
                        on delete cascade
        " );

        $entries = $wpdb->get_results( "select * from {$wpdb->usermeta} where meta_key = 'money_manager'" );
        foreach ( $entries as $entry ) {
            $value = unserialize( $entry->meta_value );
            $value['displayCurrency'] = strtoupper( $value['displayCurrency'] );
            $wpdb->update(
                $wpdb->usermeta,
                array( 'meta_value' => serialize( $value ) ),
                array( 'umeta_id' => $entry->umeta_id )
            );
        }
    }

    /**
     * Updater v1.10.1
     */
    public function update_1_10_1()
    {
        global $wpdb;

        $wpdb->query( "
            create index quotes_currency_date_index
                on {$wpdb->prefix}money_manager_quotes (currency, date)
        " );
        $wpdb->query( "drop index quotes_currency_index on {$wpdb->prefix}money_manager_quotes" );
        $wpdb->query( "drop index quotes_date_index on {$wpdb->prefix}money_manager_quotes" );
    }

    /**
     * Updater v1.8.0
     */
    public function update_1_8_0()
    {
        add_option( 'money_manager_woocommerce', array(
            'enabled' => false,
            'account_id' => null,
            'party_id' => null,
            'category_id' => null,
        ) );
    }

    /**
     * Check whether database version is up-to-date
     *
     * @return bool
     */
    public function up_to_date()
    {
        return get_option( 'money_manager_version' ) === MoneyManager()->version;
    }

    /**
     * Run updates
     */
    public function update()
    {
        if ( get_transient( 'money_manager_updating' ) == 'yes' ) {
            return;
        }

        set_transient( 'money_manager_updating', 'yes', MINUTE_IN_SECONDS * 10 );
        set_time_limit( 0 );

        $updates = array_filter(
            get_class_methods( $this ),
            function ( $method ) { return strpos( $method, 'update_' ) === 0; }
        );
        usort( $updates, 'strnatcmp' );

        $db_version = get_option( 'money_manager_version' );

        foreach ( $updates as $method ) {
            $version = str_replace( '_', '.', substr( $method, 7 ) );
            if ( strnatcmp( $version, $db_version ) > 0 && strnatcmp( $version, MoneyManager()->version ) <= 0 ) {
                // Do update
                call_user_func( array( $this, $method ) );
                // Update plugin version
                update_option( 'money_manager_version', $version );
            }
        }

        // Update plugin version in case no updates were made
        update_option( 'money_manager_version', MoneyManager()->version );

        delete_transient( 'money_manager_updating' );
    }
}