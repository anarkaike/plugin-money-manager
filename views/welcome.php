<?php
defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

use MoneyManager\Sample_Data;
?>
<div id="money-manager">
    <div class="html">
        <div class="body">
            <section class="hero is-fullheight is-info">
                <div class="hero-body">
                    <div class="container has-text-centered">
                        <p class="title is-1 is-spaced">
                            <?php esc_html_e( 'Welcome to Money Manager', 'money-manager' ) ?>
                        </p>
                        <p class="subtitle is-3">
                            <?php esc_html_e( 'Thank you for installing the plugin!', 'money-manager' ) ?>
                        </p>
                        <p class="subtitle is-3">
                            <?php esc_html_e( 'This software helps you organize your personal or business finances. You can always track where, when and how the money goes, thanks to the following features:', 'money-manager' ) ?>
                        </p>

                        <div class="tile is-ancestor">
                            <div class="tile is-4 is-vertical is-parent">
                                <div class="tile is-child box notification is-info">
                                    <p class="subtitle">
                                        <span class="icon-text">
                                            <span class="icon">
                                                <i class="fas fa-coins"></i>
                                            </span>
                                            <span><?php esc_html_e( 'Multi-Currency', 'money-manager' ) ?></span>
                                        </span>
                                    </p>
                                </div>
                                <div class="tile is-child box notification is-info">
                                    <p class="subtitle">
                                        <span class="icon-text">
                                            <span class="icon">
                                                <i class="fas fa-check-double"></i>
                                            </span>
                                            <span><?php esc_html_e( 'Double-Entry System', 'money-manager' ) ?></span>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="tile is-4 is-vertical is-parent">
                                <div class="tile is-child box notification is-info">
                                    <p class="subtitle">
                                        <span class="icon-text">
                                            <span class="icon">
                                                <i class="fas fa-university"></i>
                                            </span>
                                            <span><?php esc_html_e( 'Bank Accounts', 'money-manager' ) ?></span>
                                        </span>
                                    </p>
                                </div>
                                <div class="tile is-child box notification is-info">
                                    <p class="subtitle">
                                        <span class="icon-text">
                                            <span class="icon">
                                                <i class="fas fa-chart-line"></i>
                                            </span>
                                            <span><?php esc_html_e( 'Income/Expense Tracking', 'money-manager' ) ?></span>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="tile is-4 is-vertical is-parent">
                                <div class="tile is-child box notification is-info">
                                    <p class="subtitle">
                                        <span class="icon-text">
                                            <span class="icon">
                                                <i class="fas fa-chart-pie"></i>
                                            </span>
                                            <span><?php esc_html_e( 'Account Summaries', 'money-manager' ) ?></span>
                                        </span>
                                    </p>
                                </div>
                                <div class="tile is-child box notification is-info">
                                    <p class="subtitle">
                                        <span class="icon-text">
                                            <span class="icon">
                                                <i class="fas fa-edit"></i>
                                            </span>
                                            <span><?php esc_html_e( 'Transaction Categories', 'money-manager' ) ?></span>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <section class="section">
                            <?php if ( Sample_Data::imported() ): ?>
                                <button disabled class="button is-primary is-large">
                                <span class="icon is-small">
                                    <i class="fas fa-file-import"></i>
                                </span>
                                    <span><?php esc_html_e( 'Import Sample Data & Start', 'money-manager' ) ?></span>
                                </button>
                            <?php else: ?>
                                <a href="<?php echo esc_url( admin_url('admin.php?page=money-manager-home&money-manager-import=sample-data') ) ?>" class="button is-primary is-large">
                                <span class="icon is-small">
                                    <i class="fas fa-file-import"></i>
                                </span>
                                    <span><?php esc_html_e( 'Import Sample Data & Start', 'money-manager' ) ?></span>
                                </a>
                            <?php endif ?>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=money-manager-home') ) ?>" class="button is-large">
                                <span class="icon is-small">
                                    <i class="fas fa-play-circle"></i>
                                </span>
                                <span><?php esc_html_e( 'Just Start', 'money-manager' ) ?></span>
                            </a>
                        </section>

                        <p class="subtitle is-3">
                            <?php if ( Sample_Data::imported() ): ?>
                                <?php esc_html_e( 'You have already imported the sample data. We hope you enjoy using the Money Manager.', 'money-manager' ) ?>
                            <?php else: ?>
                                 <?php esc_html_e( 'It is recommended to import the sample data, so you can just play around with some records, and then delete them.', 'money-manager' ) ?>
                            <?php endif ?>
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
