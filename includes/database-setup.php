<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * テーブル作成・マイグレーション
 *
 * 変更点（v3.0）：
 *  - wp_my_attendance_auth を新規作成（パスワード認証情報）
 *  - wp_my_attendance_users を廃止（employee-manager に統合）
 *  - wp_my_attendance_logs の registered_user_id 参照先を wp_emp_master.id に変更
 */
function mat_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // -------------------------
    // 1. 勤怠ログテーブル（変更）
    //    registered_user_id の参照先を wp_emp_master.id に変更
    // -------------------------
    dbDelta( "CREATE TABLE " . MAT_LOG_TABLE . " (
        id                      BIGINT(20)      NOT NULL AUTO_INCREMENT,
        item_name               VARCHAR(255)    NOT NULL                    COMMENT '打刻内容（出勤: HH:MM | 退勤: HH:MM | ...）',
        timestamp               DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '打刻日時',
        registered_user_id      BIGINT(20)      NOT NULL                    COMMENT 'wp_emp_master.id を参照',
        registered_user_name    VARCHAR(255)    NOT NULL DEFAULT ''         COMMENT '氏名（冗長保持）',
        employee_code           VARCHAR(50)     NOT NULL DEFAULT ''         COMMENT '社員コード（冗長保持）',
        paid_leave_date         DATE                NULL DEFAULT NULL       COMMENT '有給希望日',
        PRIMARY KEY (id),
        KEY idx_employee_code (employee_code),
        KEY idx_timestamp     (timestamp),
        KEY idx_user_id       (registered_user_id)
    ) $charset;" );

    // -------------------------
    // 2. 認証テーブル（新規）
    // -------------------------
    dbDelta( "CREATE TABLE " . MAT_AUTH_TABLE . " (
        id                  BIGINT(20)      NOT NULL AUTO_INCREMENT,
        emp_master_id       BIGINT(20)      NOT NULL                    COMMENT 'wp_emp_master.id',
        employee_code       VARCHAR(50)     NOT NULL                    COMMENT '社員コード（照合用に冗長保持）',
        password_hash       VARCHAR(255)        NULL DEFAULT NULL       COMMENT 'password_hash() で保存。NULL = 未設定',
        is_registered       TINYINT(1)      NOT NULL DEFAULT 0          COMMENT '0=未設定 / 1=設定済み',
        login_failed_count  TINYINT(3)      NOT NULL DEFAULT 0          COMMENT 'ログイン失敗回数',
        locked_until        DATETIME            NULL DEFAULT NULL       COMMENT 'ロック解除日時（5回失敗で30分）',
        reset_token         VARCHAR(64)         NULL DEFAULT NULL       COMMENT 'パスワードリセット用トークン',
        reset_token_expires DATETIME            NULL DEFAULT NULL       COMMENT 'トークン有効期限',
        created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_emp_master_id  (emp_master_id),
        UNIQUE KEY uq_employee_code  (employee_code)
    ) $charset;" );

    // -------------------------
    // 3. 廃止テーブルの削除
    //    wp_my_attendance_users は employee-manager に統合
    // -------------------------
    $users_table = $wpdb->prefix . 'my_attendance_users';
    $table_exists = $wpdb->get_var(
        $wpdb->prepare( 'SHOW TABLES LIKE %s', $users_table )
    );
    if ( $table_exists ) {
        // データが残っている場合は削除しない（管理者が確認できるよう警告のみ）
        $row_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$users_table}`" );
        if ( $row_count === 0 ) {
            $wpdb->query( "DROP TABLE IF EXISTS `{$users_table}`" );
        } else {
            // 旧テーブルにデータがある場合は管理画面に警告を表示
            add_action( 'admin_notices', 'mat_old_users_table_notice' );
        }
    }

    // -------------------------
    // 4. wp_options にデフォルト設定を保存（初回のみ）
    // -------------------------
    $defaults = array(
        'mat_use_password_auth'       => 1,
        'mat_use_paid_leave_approval' => 1,
        'mat_show_paid_leave_request' => 1,
        'mat_allow_log_edit'          => 0,
        'mat_closing_day'             => 0,
    );
    foreach ( $defaults as $key => $value ) {
        if ( get_option( $key ) === false ) {
            add_option( $key, $value );
        }
    }

    // DBバージョンを更新
    update_option( 'mat_db_version', MAT_VERSION );
}

/**
 * 旧テーブル（wp_my_attendance_users）にデータが残っている場合の警告
 */
function mat_old_users_table_notice() {
    $users_table = $GLOBALS['wpdb']->prefix . 'my_attendance_users';
    echo '<div class="notice notice-warning"><p>'
        . '<strong>My Attendance Tool:</strong> '
        . "旧テーブル <code>{$users_table}</code> にデータが残っています。"
        . '内容を確認し、不要であれば手動で削除してください。'
        . '</p></div>';
}

/**
 * プラグインアンインストール時に呼ばれるテーブル削除
 * （uninstall.php から呼び出す）
 */
function mat_drop_tables() {
    global $wpdb;
    $wpdb->query( "DROP TABLE IF EXISTS " . MAT_LOG_TABLE );
    $wpdb->query( "DROP TABLE IF EXISTS " . MAT_AUTH_TABLE );
    $wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}my_attendance_users`" );

    // wp_options の設定値も削除
    $option_keys = array(
        'mat_db_version',
        'mat_use_password_auth',
        'mat_use_paid_leave_approval',
        'mat_allow_log_edit',
        'mat_closing_day',
    );
    foreach ( $option_keys as $key ) {
        delete_option( $key );
    }
}
