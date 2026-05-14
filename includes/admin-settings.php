<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 管理メニュー登録
 */
add_action( 'admin_menu', 'mat_register_admin_menu' );
function mat_register_admin_menu() {
    add_menu_page(
        '勤怠管理ツール',
        '勤怠管理',
        'manage_options',
        'my-attendance-settings',
        'mat_history_page_render',
        'dashicons-calendar-alt',
        30
    );
    add_submenu_page(
        'my-attendance-settings',
        '勤怠履歴',
        '勤怠履歴',
        'manage_options',
        'my-attendance-settings',
        'mat_history_page_render'
    );
}

/**
 * 管理画面用スタイル・スクリプトの読み込み
 */
add_action( 'admin_enqueue_scripts', 'mat_admin_enqueue' );
function mat_admin_enqueue( $hook ) {
    $mat_pages = array(
        'toplevel_page_my-attendance-settings',
        '勤怠管理_page_my-attendance-settings',
        '勤怠管理_page_mat-auth-management',
        '勤怠管理_page_mat-settings',
        '勤怠管理_page_mat-test-data',
    );
    if ( ! in_array( $hook, $mat_pages, true ) ) return;

    $emp_css = WP_PLUGIN_DIR . '/employee-manager/admin/assets/admin.css';
    if ( file_exists( $emp_css ) ) {
        wp_enqueue_style( 'employee-manager-admin', plugins_url( 'employee-manager/admin/assets/admin.css' ) );
    }
}

/**
 * 管理画面：勤怠編集 Ajax（管理者用）
 */
add_action( 'wp_ajax_mat_admin_edit_log', 'mat_admin_edit_log_handler' );
function mat_admin_edit_log_handler() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '権限がありません。' );
    check_ajax_referer( 'mat_admin_nonce', 'nonce' );

    global $wpdb;
    $id         = intval( $_POST['id'] ?? 0 );
    $clock_in   = sanitize_text_field( $_POST['clock_in']   ?? '' );
    $clock_out  = sanitize_text_field( $_POST['clock_out']  ?? '' );
    $break_hhmm = sanitize_text_field( $_POST['break_time'] ?? '00:00' );
    $paid_leave = sanitize_text_field( $_POST['paid_leave'] ?? '' );
    $note       = sanitize_textarea_field( $_POST['note']   ?? '' );
    $is_holiday = ( ($_POST['is_holiday'] ?? '0') === '1' ); // 休日フラグ

    if ( ! preg_match( '/^\d{2}:\d{2}$/', $break_hhmm ) ) $break_hhmm = '00:00';

    $log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . MAT_LOG_TABLE . " WHERE id = %d", $id ) );
    if ( ! $log ) wp_send_json_error( 'レコードが見つかりません。' );

    if ( $is_holiday ) {
        // 休日として保存
        $update = array( 'item_name' => '休日', 'paid_leave_date' => null );
    } else {
        // 通常の打刻データとして保存
        $parts = array();
        if ( $clock_in  !== '' ) $parts[] = "出勤: {$clock_in}";
        if ( $clock_out !== '' ) $parts[] = "退勤: {$clock_out}";
        $parts[] = "休憩: {$break_hhmm}";
        if ( $note !== '' )      $parts[] = "備考: {$note}";
        $update = array( 'item_name' => implode( ' | ', $parts ) );
        $update['paid_leave_date'] = ( $paid_leave !== '' ) ? $paid_leave : null;
    }

    $updated = $wpdb->update( MAT_LOG_TABLE, $update, array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );
    if ( $updated === false ) wp_send_json_error( '更新失敗' );

    wp_send_json_success();
}

/**
 * 管理画面：勤怠削除 Ajax（管理者用）
 */
add_action( 'wp_ajax_mat_admin_delete_log', 'mat_admin_delete_log_handler' );
function mat_admin_delete_log_handler() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '権限がありません。' );
    check_ajax_referer( 'mat_admin_nonce', 'nonce' );

    global $wpdb;
    $id = intval( $_POST['id'] ?? 0 );
    if ( $id <= 0 ) wp_send_json_error( 'IDが不正です。' );

    $deleted = $wpdb->delete( MAT_LOG_TABLE, array( 'id' => $id ), array( '%d' ) );
    if ( $deleted !== false ) {
        wp_send_json_success( '削除しました。' );
    } else {
        wp_send_json_error( '削除に失敗しました。' );
    }
}

/**
 * 勤怠履歴ページのレンダリング
 */
function mat_history_page_render() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $employees = emp_get_active_employees();
    $selected_code = isset( $_GET['employee_code'] ) ? sanitize_text_field( $_GET['employee_code'] ) : ( ! empty( $employees ) ? $employees[0]->employee_code : '' );
    $view_month = isset( $_GET['view_month'] ) ? sanitize_text_field( $_GET['view_month'] ) : date( 'Y-m' );

    $selected_emp = null;
    foreach ( $employees as $emp ) { if ( $emp->employee_code === $selected_code ) { $selected_emp = $emp; break; } }

    $logs = array();
    if ( $selected_emp ) {
        $data = mat_get_grouped_data( $selected_emp->id, $view_month );
        $logs = $data['logs'];
    }
    ?>
    <div class="wrap">
        <h1>📋 従業員勤怠履歴</h1>

        <div class="card" style="max-width:100%; margin-top:20px; padding:15px;">
            <form method="get">
                <input type="hidden" name="page" value="my-attendance-settings">
                従業員：
                <select name="employee_code">
                    <?php foreach ( $employees as $emp ) : ?>
                        <option value="<?php echo esc_attr( $emp->employee_code ); ?>" <?php selected( $selected_code, $emp->employee_code ); ?>>
                            [<?php echo esc_html( $emp->employee_code ); ?>] <?php echo esc_html( $emp->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                表示月：<input type="month" name="view_month" value="<?php echo esc_attr( $view_month ); ?>">
                <input type="submit" class="button button-primary" value="表示">
            </form>
        </div>

        <?php if ( $selected_emp ) : ?>
            <table class="widefat fixed striped" style="margin-top:20px;">
                <thead>
                    <tr>
                        <th style="width:110px;">日付</th>
                        <th style="width:80px;">出勤</th>
                        <th style="width:80px;">退勤</th>
                        <th style="width:80px;">休憩</th>
                        <th style="width:110px; color:#d63638;">有給希望日</th>
                        <th>備考</th>
                        <th style="width:100px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $logs ) ) : ?>
                        <tr><td colspan="7" style="text-align:center; padding:20px;">データがありません。</td></tr>
                    <?php else : ?>
                        <?php foreach ( $logs as $day ) : ?>
                            <tr data-id="<?php echo esc_attr( $day['id'] ); ?>">
                                <td><?php echo esc_html( $day['date'] ); ?></td>
                                <td><?php echo esc_html( $day['in'] ); ?></td>
                                <td><?php echo esc_html( $day['out'] ); ?></td>
                                <td><?php echo esc_html( $day['break'] ); ?></td>
                                <td style="font-weight:bold; color:#d63638;"><?php echo esc_html( $day['paid_leave'] ); ?></td>
                                <td><?php echo esc_html( is_array( $day['notes'] ) ? implode( ' / ', $day['notes'] ) : '' ); ?></td>
                                <td>
                                    <button class="button button-small edit-log"
                                        data-id="<?php echo esc_attr( $day['id'] ); ?>"
                                        data-in="<?php echo esc_attr( $day['in'] === '-' ? '' : $day['in'] ); ?>"
                                        data-out="<?php echo esc_attr( $day['out'] === '-' ? '' : $day['out'] ); ?>"
                                        data-break="<?php echo esc_attr( $day['break'] === '-' ? '00:00' : $day['break'] ); ?>"
                                        data-paid="<?php echo esc_attr( $day['paid_leave'] === '-' ? '' : $day['paid_leave'] ); ?>"
                                        data-notes="<?php echo esc_attr( is_array( $day['notes'] ) ? implode( ' / ', $day['notes'] ) : '' ); ?>"
                                        data-holiday="<?php echo $day['is_holiday'] ? '1' : '0'; ?>">
                                        編集
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div id="mat-edit-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:8px; padding:28px; width:440px; max-width:90%;">
            <h3 style="margin:0 0 20px;">打刻データの編集</h3>
            <table class="form-table" style="margin:0;">
                <tr><th>出勤</th><td><input type="time" id="edit-in" class="regular-text"></td></tr>
                <tr><th>退勤</th><td><input type="time" id="edit-out" class="regular-text"></td></tr>
                <tr><th>休憩</th><td><input type="time" id="edit-break" class="regular-text" value="00:00"></td></tr>
                <tr><th>有給希望日</th><td><input type="date" id="edit-paid" class="regular-text"></td></tr>
                <tr><th>備考</th><td><textarea id="edit-notes" class="regular-text" rows="2"></textarea></td></tr>
                <tr style="border-top: 1px solid #eee;">
                    <th>休日設定</th>
                    <td>
                        <label style="color:#d63638; font-weight:bold;">
                            <input type="checkbox" id="edit-holiday"> この日を「休日」にする
                        </label>
                    </td>
                </tr>
            </table>
            <p id="edit-error" style="color:#d63638; margin:10px 0 0; display:none;"></p>
            <div style="margin-top:20px; display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" id="edit-delete" class="button" style="margin-right:auto; color:#d63638; border-color:#d63638;">🗑 削除する</button>
                <button type="button" id="edit-cancel" class="button">キャンセル</button>
                <button type="button" id="edit-save" class="button button-primary">💾 保存する</button>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var currentId = null;
        var nonce = '<?php echo wp_create_nonce("mat_admin_nonce"); ?>';
        var viewMonth = '<?php echo esc_js( $view_month ); ?>';

        function paidDisplayToInput(mmdd) {
            if (!mmdd || mmdd === '-') return '';
            var parts = mmdd.split('/');
            if (parts.length === 2) return viewMonth.split('-')[0] + '-' + parts[0] + '-' + parts[1];
            return mmdd;
        }

        function toggleHolidayUI(isHoliday) {
            var opacity = isHoliday ? '0.5' : '1';
            $('#edit-in, #edit-out, #edit-break').prop('disabled', isHoliday).parent().css('opacity', opacity);
        }

        // 編集ボタン
        $(document).on('click', '.edit-log', function() {
            currentId = $(this).data('id');
            $('#edit-in').val($(this).data('in') || '');
            $('#edit-out').val($(this).data('out') || '');
            $('#edit-break').val($(this).data('break') || '00:00');
            $('#edit-paid').val(paidDisplayToInput($(this).data('paid')));
            $('#edit-notes').val($(this).data('notes') || '');
            var isHoliday = $(this).data('holiday') == '1';
            $('#edit-holiday').prop('checked', isHoliday);
            toggleHolidayUI(isHoliday);
            $('#edit-error').hide();
            $('#mat-edit-modal').css('display', 'flex');
        });

        $('#edit-holiday').on('change', function() { toggleHolidayUI($(this).is(':checked')); });

        // 削除ボタン
        $('#edit-delete').on('click', function() {
            if (!currentId || !confirm('このデータを完全に削除しますか？')) return;
            var $btn = $(this);
            $btn.prop('disabled', true).text('削除中...');
            $.post(ajaxurl, { action: 'mat_admin_delete_log', id: currentId, nonce: nonce }, function(res) {
                if (res.success) { location.reload(); } else { alert(res.data); $btn.prop('disabled', false).text('🗑 削除する'); }
            });
        });

        $('#edit-cancel, #mat-edit-modal').on('click', function(e) { if (e.target === this) { $('#mat-edit-modal').hide(); currentId = null; } });
        $(document).on('keydown', function(e) { if (e.key === 'Escape') { $('#mat-edit-modal').hide(); } });

        // 保存ボタン
        $('#edit-save').on('click', function() {
            if (!currentId) return;
            $(this).prop('disabled', true).text('保存中...');
            $.post(ajaxurl, {
                action:     'mat_admin_edit_log',
                id:         currentId,
                clock_in:   $('#edit-in').val(),
                clock_out:  $('#edit-out').val(),
                break_time: $('#edit-break').val() || '00:00',
                paid_leave: $('#edit-paid').val(),
                note:       $('#edit-notes').val(),
                is_holiday: $('#edit-holiday').is(':checked') ? '1' : '0',
                nonce:      nonce
            }, function(res) {
                if (res.success) { location.reload(); } else { alert(res.data); $('#edit-save').prop('disabled', false).text('💾 保存する'); }
            });
        });
    });
    </script>
    <?php
}