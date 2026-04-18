jQuery(document).ready(function ($) {

    // =========================================================
    //  設定・グローバル状態
    // =========================================================
    var ajaxurl = matAjax.ajaxurl;
    var nonce = matAjax.nonce;
    var usePasswordAuth = matAjax.usePasswordAuth === '1';
    var allowLogEdit = matAjax.allowLogEdit === '1';
    var showPaidLeave = matAjax.showPaidLeaveRequest === '1';

    var session = {
        empMasterId: 0,
        employeeCode: '',
        userName: '',
    };
    var editTargetId = null;

    // =========================================================
    //  時計
    // =========================================================
    function tickClock() {
        var now = new Date();
        var h = String(now.getHours()).padStart(2, '0');
        var m = String(now.getMinutes()).padStart(2, '0');
        var s = String(now.getSeconds()).padStart(2, '0');
        $('#mat-clock').text(h + ':' + m + ':' + s);
    }
    tickClock();
    setInterval(tickClock, 1000);

    // =========================================================
    //  ユーティリティ
    // =========================================================
    function minsToHHMM(mins) {
        mins = parseInt(mins, 10) || 0;
        return String(Math.floor(mins / 60)).padStart(2, '0')
            + ':' + String(mins % 60).padStart(2, '0');
    }

    function showSection(id) {
        $('.mat-section').hide();
        $('#' + id).show();
    }

    function setError(id, msg) {
        $('#' + id).text(msg || '').show();
    }

    function clearError(id) {
        $('#' + id).text('').hide();
    }

    function btnLoading($btn, loading) {
        if (loading) {
            $btn.prop('disabled', true)
                .data('original-text', $btn.text())
                .text('処理中...');
        } else {
            $btn.prop('disabled', false)
                .text($btn.data('original-text') || $btn.text());
        }
    }

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getCurrentYearMonth() {
        var now = new Date();
        return now.getFullYear() + '-'
            + String(now.getMonth() + 1).padStart(2, '0');
    }

    // =========================================================
    //  休憩スライダー
    // =========================================================
    $('#mat-break-slider').on('input', function () {
        $('#mat-break-display').text(minsToHHMM($(this).val()));
    });

    // =========================================================
    //  社員コード認証
    // =========================================================
    $('#mat-btn-verify-code').on('click', function () {
        var code = $.trim($('#mat-employee-code').val());
        if (!code) { setError('mat-error-code', '社員コードを入力してください。'); return; }

        clearError('mat-error-code');
        btnLoading($(this), true);

        $.post(ajaxurl, {
            action: 'mat_verify_code',
            employee_code: code,
            nonce: nonce,
        }, function (res) {
            btnLoading($('#mat-btn-verify-code'), false);
            if (!res.success) {
                setError('mat-error-code', res.data);
                return;
            }
            var d = res.data;
            session.employeeCode = code;

            if (d.status === 'needs_password_setup') {
                session.empMasterId = d.emp_master_id;
                session.userName = d.user_name;
                clearError('mat-error-set-password');
                $('#mat-new-password').val('');
                $('#mat-new-password2').val('');
                showSection('mat-section-set-password');

            } else if (d.status === 'needs_password') {
                session.userName = d.user_name;
                $('#mat-greeting-password').text(d.user_name + ' さん');
                clearError('mat-error-login');
                $('#mat-password').val('');
                showSection('mat-section-enter-password');

            } else if (d.status === 'logged_in') {
                session.empMasterId = d.emp_master_id;
                session.employeeCode = d.employee_code;
                session.userName = d.user_name;
                onLoginComplete();
            }
        }).fail(function () {
            btnLoading($('#mat-btn-verify-code'), false);
            setError('mat-error-code', '通信エラーが発生しました。');
        });
    });

    $('#mat-employee-code').on('keydown', function (e) {
        if (e.key === 'Enter') $('#mat-btn-verify-code').trigger('click');
    });

    // =========================================================
    //  パスワード新規設定
    // =========================================================
    $('#mat-btn-set-password').on('click', function () {
        var pw1 = $('#mat-new-password').val();
        var pw2 = $('#mat-new-password2').val();
        clearError('mat-error-set-password');

        if (pw1.length < 6) {
            setError('mat-error-set-password', 'パスワードは6文字以上で入力してください。');
            return;
        }
        if (pw1 !== pw2) {
            setError('mat-error-set-password', 'パスワードが一致しません。');
            return;
        }

        btnLoading($(this), true);

        $.post(ajaxurl, {
            action: 'mat_set_password',
            employee_code: session.employeeCode,
            password: pw1,
            nonce: nonce,
        }, function (res) {
            btnLoading($('#mat-btn-set-password'), false);
            if (!res.success) {
                setError('mat-error-set-password', res.data);
                return;
            }
            var d = res.data;
            session.empMasterId = d.emp_master_id;
            session.employeeCode = d.employee_code;
            session.userName = d.user_name;
            onLoginComplete();
        }).fail(function () {
            btnLoading($('#mat-btn-set-password'), false);
            setError('mat-error-set-password', '通信エラーが発生しました。');
        });
    });

    // =========================================================
    //  パスワードログイン
    // =========================================================
    $('#mat-btn-login').on('click', function () {
        var pw = $('#mat-password').val();
        clearError('mat-error-login');
        if (!pw) { setError('mat-error-login', 'パスワードを入力してください。'); return; }

        btnLoading($(this), true);

        $.post(ajaxurl, {
            action: 'mat_login',
            employee_code: session.employeeCode,
            password: pw,
            nonce: nonce,
        }, function (res) {
            btnLoading($('#mat-btn-login'), false);
            if (!res.success) {
                setError('mat-error-login', res.data);
                return;
            }
            var d = res.data;
            session.empMasterId = d.emp_master_id;
            session.employeeCode = d.employee_code;
            session.userName = d.user_name;
            onLoginComplete();
        }).fail(function () {
            btnLoading($('#mat-btn-login'), false);
            setError('mat-error-login', '通信エラーが発生しました。');
        });
    });

    $('#mat-password').on('keydown', function (e) {
        if (e.key === 'Enter') $('#mat-btn-login').trigger('click');
    });

    // =========================================================
    //  パスワードリセット申請
    // =========================================================
    $('#mat-forgot-password').on('click', function (e) {
        e.preventDefault();
        $('#mat-reset-code').val(session.employeeCode);
        clearError('mat-error-reset');
        $('#mat-success-reset').hide();
        showSection('mat-section-reset-request');
    });

    $('#mat-btn-reset-request').on('click', function () {
        var code = $.trim($('#mat-reset-code').val());
        if (!code) { setError('mat-error-reset', '社員コードを入力してください。'); return; }

        clearError('mat-error-reset');
        btnLoading($(this), true);

        $.post(ajaxurl, {
            action: 'mat_request_password_reset',
            employee_code: code,
            nonce: nonce,
        }, function (res) {
            btnLoading($('#mat-btn-reset-request'), false);
            if (res.success) {
                $('#mat-success-reset').text(res.data.message).show();
            } else {
                setError('mat-error-reset', res.data);
            }
        }).fail(function () {
            btnLoading($('#mat-btn-reset-request'), false);
            setError('mat-error-reset', '通信エラーが発生しました。');
        });
    });

    // =========================================================
    //  「戻る」リンク
    // =========================================================
    $('#mat-back-to-code-from-setpw, #mat-back-to-code-from-login, #mat-back-to-code-from-reset')
        .on('click', function (e) {
            e.preventDefault();
            session = { empMasterId: 0, employeeCode: '', userName: '' };
            $('#mat-employee-code').val('');
            clearError('mat-error-code');
            showSection('mat-section-code');
        });

    // =========================================================
    //  ログイン完了後の処理
    // =========================================================
    function onLoginComplete() {
        $('#mat-user-name').text(session.userName);
        showSection('mat-section-main');
        loadLogs();
        if (showPaidLeave) {
            loadPaidLeaveRequests();
        }
    }

    // =========================================================
    //  ログアウト
    // =========================================================
    $('#mat-logout').on('click', function (e) {
        e.preventDefault();
        session = { empMasterId: 0, employeeCode: '', userName: '' };
        editTargetId = null;
        $('#mat-employee-code').val('');
        $('#mat-note').val('');
        $('#mat-paid-leave-date').val('');
        showSection('mat-section-code');
    });

    // =========================================================
    //  打刻（出勤・退勤・休憩）
    // =========================================================
    $('.mat-punch-btn').on('click', function () {
        var label = $(this).data('label');
        var note = $('#mat-note').val();

        if (!session.empMasterId) {
            alert('ログインしてください。');
            return;
        }

        var postData = {
            action: 'mat_attendance_update',
            emp_master_id: session.empMasterId,
            employee_code: session.employeeCode,
            label: label,
            note: note,
            nonce: nonce,
        };

        if (label === '休憩') {
            postData.break_hhmm = minsToHHMM($('#mat-break-slider').val());
        }

        var $btn = $(this);
        btnLoading($btn, true);

        $.post(ajaxurl, postData, function (res) {
            btnLoading($btn, false);
            if (res.success) {
                $('#mat-note').val('');
                renderLogs(res.data);
            } else {
                alert('エラー: ' + res.data);
            }
        }).fail(function () {
            btnLoading($btn, false);
            alert('通信エラーが発生しました。');
        });
    });

    // =========================================================
    //  打刻ボタンの活性状態を更新
    // =========================================================
    function updatePunchButtons(logs) {
        var now = new Date();
        var mm = String(now.getMonth() + 1).padStart(2, '0');
        var dd = String(now.getDate()).padStart(2, '0');
        var todayStr = mm + '/' + dd;

        var hasClockin = false;
        var hasClockout = false;

        $.each(logs, function (_, row) {
            if (row.date && row.date.indexOf(todayStr) === 0) {
                if (row.in && row.in !== '-') hasClockin = true;
                if (row.out && row.out !== '-') hasClockout = true;
            }
        });

        var $btnIn = $('[data-label="出勤"]');
        if (hasClockin) {
            $btnIn.prop('disabled', true).text('出勤済み').css('opacity', '0.5');
        } else {
            $btnIn.prop('disabled', false).text('出勤').css('opacity', '1');
        }

        var $btnOut = $('[data-label="退勤"]');
        if (!hasClockin || hasClockout) {
            $btnOut.prop('disabled', true)
                .text(hasClockout ? '退勤済み' : '退勤')
                .css('opacity', '0.5');
        } else {
            $btnOut.prop('disabled', false).text('退勤').css('opacity', '1');
        }
    }

    // =========================================================
    //  有給希望申請ボタン
    // =========================================================
    $('#mat-btn-paid-leave').on('click', function () {
        var paidDate = $('#mat-paid-leave-date').val();
        clearError('mat-error-paid-leave');

        if (!paidDate) {
            setError('mat-error-paid-leave', '有給希望日を選択してください。');
            return;
        }

        var $btn = $(this);
        btnLoading($btn, true);

        $.post(ajaxurl, {
            action: 'mat_submit_paid_leave',
            emp_master_id: session.empMasterId,
            employee_code: session.employeeCode,
            paid_leave_date: paidDate,
            nonce: nonce,
        }, function (res) {
            btnLoading($btn, false);
            if (res.success) {
                $('#mat-paid-leave-date').val('');
                renderPaidLeaveRequests(res.data);
            } else {
                setError('mat-error-paid-leave', 'エラー: ' + res.data);
            }
        }).fail(function () {
            btnLoading($btn, false);
            setError('mat-error-paid-leave', '通信エラーが発生しました。');
        });
    });
    $('#mat-paid-leave-date').on('change', function () {
        if ($(this).val()) {
            $(this).attr('data-has-value', '1');
        } else {
            $(this).removeAttr('data-has-value');
        }
    });
    // =========================================================
    //  有給申請一覧の取得・表示
    // =========================================================
    function loadPaidLeaveRequests() {
        $('#mat-paid-leave-body').html(
            '<tr><td colspan="3" class="mat-loading">読み込み中...</td></tr>'
        );

        $.post(ajaxurl, {
            action: 'mat_get_paid_leave_requests',
            employee_code: session.employeeCode,
            nonce: nonce,
        }, function (res) {
            if (res.success) {
                renderPaidLeaveRequests(res.data);
            } else {
                $('#mat-paid-leave-body').html(
                    '<tr><td colspan="3" style="text-align:center;padding:12px;color:#999;">取得できませんでした。</td></tr>'
                );
            }
        });
    }

    function renderPaidLeaveRequests(data) {
        var requests = data.requests || [];

        if (requests.length === 0) {
            $('#mat-paid-leave-body').html(
                '<tr><td colspan="3" class="mat-loading">申請はありません。</td></tr>'
            );
            return;
        }

        var statusClass = {
            'pending': 'mat-status-pending',
            'approved': 'mat-status-approved',
            'rejected': 'mat-status-rejected',
        };

        var html = '';
        $.each(requests, function (_, r) {
            // ★ mat-status-badge を必ず付与し、さらに状態別クラスを追加する
            var cls = statusClass[r.status_key] || '';
            html += '<tr>';
            html += '<td>' + esc(r.request_date) + '</td>';
            html += '<td>' + esc(r.paid_leave_date) + '</td>';
            html += '<td><span class="mat-status-badge ' + cls + '">' + esc(r.status) + '</span></td>';
            html += '</tr>';
        });

        $('#mat-paid-leave-body').html(html);
    }
    // =========================================================
    //  打刻履歴の取得・表示
    // =========================================================
    function loadLogs() {
        var month = $('#mat-view-month').val() || getCurrentYearMonth();
        $('#mat-history-body').html(
            '<tr><td colspan="6" class="mat-loading">読み込み中...</td></tr>'
        );

        $.post(ajaxurl, {
            action: 'mat_get_logs',
            emp_master_id: session.empMasterId,
            month: month,
            nonce: nonce,
        }, function (res) {
            if (res.success) {
                renderLogs(res.data);
            } else {
                $('#mat-history-body').html(
                    '<tr><td colspan="6" style="text-align:center;padding:16px;color:#999;">取得できませんでした。</td></tr>'
                );
            }
        });
    }

    function renderLogs(data) {
        if (!data.logs || data.logs.length === 0) {
            $('#mat-history-body').html(
                '<tr><td colspan="6" class="mat-loading">データがありません。</td></tr>'
            );
            updatePunchButtons([]);
            return;
        }

        var html = '';

        $.each(data.logs, function (_, row) {
            html += '<tr data-id="' + row.id + '">';
            html += '<td>' + esc(row.date) + '</td>';
            html += '<td>' + esc(row.in) + '</td>';
            html += '<td>' + esc(row.out) + '</td>';
            html += '<td>' + esc(row.break) + '</td>';

            var notes = Array.isArray(row.notes) ? row.notes.join(' / ') : '';
            html += '<td style="text-align:left;">' + esc(notes) + '</td>';

            if (allowLogEdit) {
                if (row.can_edit) {
                    html += '<td>'
                        + '<button class="mat-btn-sm mat-edit-btn"'
                        + ' data-id="' + row.id + '"'
                        + ' data-in="' + esc(row.in === '-' ? '' : row.in) + '"'
                        + ' data-out="' + esc(row.out === '-' ? '' : row.out) + '"'
                        + ' data-break="' + esc(row.break === '-' ? '00:00' : row.break) + '"'
                        + ' data-notes="' + esc(notes) + '"'
                        + '>編集</button>'
                        + '</td>';
                } else {
                    html += '<td style="color:#ccc;font-size:.8em;">-</td>';
                }
            }

            html += '</tr>';
        });

        $('#mat-history-body').html(html);
        updatePunchButtons(data.logs);
    }

    // 月変更で自動リロード
    $('#mat-view-month').on('change', function () {
        if (session.empMasterId) loadLogs();
    });

    // =========================================================
    //  打刻編集モーダル（社員側）
    // =========================================================
    $(document).on('click', '.mat-edit-btn', function () {
        editTargetId = $(this).data('id');
        $('#mat-edit-in').val($(this).data('in') || '');
        $('#mat-edit-out').val($(this).data('out') || '');
        $('#mat-edit-break').val($(this).data('break') || '00:00');
        $('#mat-edit-note').val($(this).data('notes') || '');
        clearError('mat-edit-error');
        $('#mat-edit-modal').fadeIn(150);
    });

    $('#mat-edit-cancel').on('click', function () {
        $('#mat-edit-modal').fadeOut(150);
        editTargetId = null;
    });

    $('#mat-edit-save').on('click', function () {
        if (!editTargetId) return;

        clearError('mat-edit-error');
        btnLoading($(this), true);

        $.post(ajaxurl, {
            action: 'mat_edit_log',
            id: editTargetId,
            emp_master_id: session.empMasterId,
            clock_in: $('#mat-edit-in').val(),
            clock_out: $('#mat-edit-out').val(),
            break_time: $('#mat-edit-break').val(),
            note: $('#mat-edit-note').val(),
            nonce: nonce,
        }, function (res) {
            btnLoading($('#mat-edit-save'), false);
            if (res.success) {
                $('#mat-edit-modal').fadeOut(150);
                editTargetId = null;
                loadLogs();
            } else {
                setError('mat-edit-error', res.data);
            }
        }).fail(function () {
            btnLoading($('#mat-edit-save'), false);
            setError('mat-edit-error', '通信エラーが発生しました。');
        });
    });

    // モーダル外クリックで閉じる
    $('#mat-edit-modal').on('click', function (e) {
        if ($(e.target).is('#mat-edit-modal')) {
            $(this).fadeOut(150);
            editTargetId = null;
        }
    });

});
