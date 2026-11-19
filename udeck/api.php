<?php
/**
 * UDeck - 보드 화면용 API (로그인 세션 + CSRF)
 * GET  api.php?a=board[&done=1]
 * POST api.php  본문 JSON {a: '동작', ...}
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

udeck_db();
require_login_api();
// 응답 중에는 세션 잠금을 풀어 동시 요청이 막히지 않게 한다
session_write_close();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $a = (string)($_GET['a'] ?? '');
        if ($a === 'board') {
            json_out(board_data(!empty($_GET['done'])));
        }
        json_fail('알 수 없는 요청입니다.', 404);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_fail('허용되지 않은 방식입니다.', 405);
    }

    $in = json_in();
    $id = int_or_null($in['id'] ?? null);

    switch ((string)($in['a'] ?? '')) {
        case 'activity.save':
            json_out(['ok' => true, 'id' => activity_save_manual($in)]);
        case 'activity.delete':
            if (!$id) { json_fail('기록을 지정하세요.'); }
            activity_delete($id);
            json_out(['ok' => true]);
        case 'activity.assign':
            if (!$id) { json_fail('기록을 지정하세요.'); }
            activity_assign($id, int_or_null($in['project_id'] ?? null));
            json_out(['ok' => true]);

        case 'project.save':
            json_out(['ok' => true, 'id' => project_save($in)]);
        case 'project.reorder':
            project_reorder(is_array($in['ids'] ?? null) ? $in['ids'] : []);
            json_out(['ok' => true]);
        case 'project.delete':
            if (!$id) { json_fail('프로젝트를 지정하세요.'); }
            project_delete($id);
            json_out(['ok' => true]);

        case 'note.save':
            json_out(['ok' => true, 'id' => note_save($in)]);
        case 'note.pin':
            if (!$id) { json_fail('메모를 지정하세요.'); }
            note_flag($id, 'pinned', !empty($in['value']));
            json_out(['ok' => true]);
        case 'note.done':
            if (!$id) { json_fail('메모를 지정하세요.'); }
            note_flag($id, 'done', !empty($in['value']));
            json_out(['ok' => true]);
        case 'note.delete':
            if (!$id) { json_fail('메모를 지정하세요.'); }
            note_delete($id);
            json_out(['ok' => true]);
    }
    json_fail('알 수 없는 요청입니다.', 404);
} catch (Throwable $e) {
    error_log('[UDeck api] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    json_fail('서버에서 처리하지 못했습니다. 잠시 뒤 다시 시도하세요.', 500);
}
