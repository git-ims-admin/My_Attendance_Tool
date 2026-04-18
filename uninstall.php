<?php
/**
 * プラグイン削除時に実行されるファイル
 * WordPress管理画面でプラグインを「削除」したときのみ呼ばれる。
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/database-setup.php';
mat_drop_tables();
