<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 設定画面の登録・処理
 */
add_action( 'admin_menu', 'mat_register_settings_page', 20 );
function mat_register_settings_page() {
    add_submenu_page(
        'my-attendance-settings',
        '勤怠ツール設定',
        '設定',
        'manage_options',
        'mat-settings',
        'mat_settings_page_render'
    );
}

/**
 * 設定の保存処理
 */
add_action( 'admin_post_mat_save_settings', 'mat_save_settings_handler' );
function mat_save_settings_handler() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( '権限がありません。' );
    }
    check_admin_referer( 'mat_save_settings' );

    update_option( 'mat_use_password_auth',        isset( $_POST['mat_use_password_auth'] )        ? 1 : 0 );
    update_option( 'mat_use_paid_leave_approval',   isset( $_POST['mat_use_paid_leave_approval'] )   ? 1 : 0 );
    update_option( 'mat_show_paid_leave_request',   isset( $_POST['mat_show_paid_leave_request'] )   ? 1 : 0 );
    update_option( 'mat_allow_log_edit',             isset( $_POST['mat_allow_log_edit'] )             ? 1 : 0 );
    update_option( 'mat_closing_day',               intval( $_POST['mat_closing_day'] ?? 0 ) );

    wp_redirect( admin_url( 'admin.php?page=mat-settings&saved=1' ) );
    exit;
}

/**
 * 設定画面のレンダリング
 */
function mat_settings_page_render() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $use_password         = (bool) get_option( 'mat_use_password_auth', 1 );
    $use_approval         = (bool) get_option( 'mat_use_paid_leave_approval', 1 );
    $show_paid_leave_req  = (bool) get_option( 'mat_show_paid_leave_request', 1 );
    $allow_log_edit       = (bool) get_option( 'mat_allow_log_edit', 0 );
    $closing_day     = (int)  get_option( 'mat_closing_day', 0 );

    $closing_options = array(
        0  => '末日',
        10 => '10日',
        15 => '15日',
        20 => '20日',
        25 => '25日',
        28 => '28日',
    );
    ?>
    <div class="wrap">
        <h1>⚙️ 勤怠ツール設定</h1>

        <?php if ( isset( $_GET['saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
            <?php wp_nonce_field( 'mat_save_settings' ); ?>
            <input type="hidden" name="action" value="mat_save_settings">

            <table class="form-table" role="presentation">

                <!-- パスワード認証 -->
                <tr>
                    <th scope="row">パスワード認証</th>
                    <td>
                        <label>
                            <input type="checkbox" name="mat_use_password_auth" value="1"
                                <?php checked( $use_password ); ?>>
                            パスワード認証を使用する
                        </label>
                        <p class="description">
                            ONにすると社員コードに加えてパスワードでの認証が必要になります。<br>
                            OFFにすると社員コードのみでログインできます。
                        </p>
                    </td>
                </tr>

                <!-- 有給承認フロー -->
                <tr>
                    <th scope="row">有給申請の承認フロー</th>
                    <td>
                        <label>
                            <input type="checkbox" name="mat_use_paid_leave_approval" value="1"
                                <?php checked( $use_approval ); ?>>
                            有給申請の承認フローを使用する（paid-leave-manager 連携）
                        </label>
                        <p class="description">
                            ONにすると有給希望日の申請が paid-leave-manager に送信され、管理者の承認が必要になります。<br>
                            OFFにすると打刻データに記録するのみで承認フローは発生しません。
                        </p>
                        <?php if ( ! function_exists( 'pl_get_request_status' ) ) : ?>
                            <p class="description" style="color:#d63638;">
                                ⚠️ paid-leave-manager が有効化されていません。ONにしても連携は機能しません。
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <!-- 有給申請セクションの表示 -->
                <tr>
                    <th scope="row">有給希望日の申請</th>
                    <td>
                        <label>
                            <input type="checkbox" name="mat_show_paid_leave_request" value="1"
                                <?php checked( $show_paid_leave_req ); ?>>
                            フロントエンドに有給希望日の申請セクションを表示する
                        </label>
                        <p class="description">
                            OFFにすると社員の打刻画面から有給希望日の申請欄と履歴の「有給」列を非表示にします。
                        </p>
                    </td>
                </tr>

                <!-- 打刻編集許可 -->
                <tr>
                    <th scope="row">社員による打刻編集</th>
                    <td>
                        <label>
                            <input type="checkbox" name="mat_allow_log_edit" value="1"
                                <?php checked( $allow_log_edit ); ?>>
                            社員による打刻情報の編集を許可する
                        </label>
                        <p class="description">
                            ONにすると社員が自分の打刻データを編集できます。<br>
                            編集できる期間は「当月（締め日設定に基づく）」のみです。<br>
                            OFFにすると管理者のみが管理画面から編集できます。
                        </p>
                    </td>
                </tr>

                <!-- 締め日 -->
                <tr>
                    <th scope="row">月次締め日</th>
                    <td>
                        <select name="mat_closing_day">
                            <?php foreach ( $closing_options as $val => $label ) : ?>
                                <option value="<?php echo $val; ?>" <?php selected( $closing_day, $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            「当月」の区切り日を設定します。打刻編集の可否・有給の月次集計に影響します。<br>
                            例）20日締めの場合、2月15日時点での「当月」は 1月21日〜2月20日 になります。
                        </p>
                        <?php
                        // 当月の期間をプレビュー表示
                        $period = mat_get_current_period();
                        echo '<p class="description" style="color:#0073aa;">'
                            . '現在の当月期間：<strong>'
                            . esc_html( $period['start'] ) . ' 〜 ' . esc_html( $period['end'] )
                            . '</strong></p>';
                        ?>
                    </td>
                </tr>

            </table>

            <?php submit_button( '設定を保存' ); ?>
        </form>
    </div>
    <?php
}
