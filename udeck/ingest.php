<?php
/**
 * UDeck - 외부 수신 API (연결 키 인증, 세션 없음)
 * 보내는 곳: PC 에이전트, Claude Code hook, Google Apps Script
 *
 * POST ingest.php
 * 헤더: X-UDeck-Token: udk_... (또는 Authorization: Bearer udk_...)
 * 본문 JSON: {"type": "activity" | "handoff" | "heartbeat" | "google", ...}
 * 자세한 형식은 docs/03_ingest_api.md 참고
 */
declare(strict_types=1);
define('UDECK_NO_SESSION', true);
require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail('POST만 허용됩니다.', 405);
}

udeck_db();

$raw = $_SERVER['HTTP_X_UDECK_TOKEN'] ?? '';
if ($raw === '') {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (stripos($auth, 'Bearer ') === 0) {
        $raw = trim(substr($auth, 7));
    }
}
$token = $raw !== '' ? token_find($raw) : null;
if (!$token) {
    usleep(300000);   // 무차별 대입 지연
    json_fail('연결 키가 없거나 맞지 않습니다.', 401);
}

try {
    $in   = json_in();
    $type = (string)($in['type'] ?? '');

    if ($type === 'google') {
        if ($token['scope'] !== 'google') {
            json_fail('이 연결 키로는 구글 데이터를 보낼 수 없습니다.', 403);
        }
        json_out(ingest_google($in));
    }

    if ($token['scope'] !== 'ingest') {
        json_fail('이 연결 키로는 작업 기록을 보낼 수 없습니다.', 403);
    }
    switch ($type) {
        case 'activity':
            json_out(ingest_activity($token, $in));
        case 'handoff':
            json_out(ingest_handoff($token, $in));
        case 'heartbeat':
            $ws = ingest_ws($token, $in);
            ws_touch((int)$ws['id']);
            json_out(['ok' => true]);
    }
    json_fail('type은 activity, handoff, heartbeat, google 중 하나여야 합니다.');
} catch (Throwable $e) {
    error_log('[UDeck ingest] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    sync_log('ingest', false, 0, $e->getMessage());
    json_fail('서버에서 처리하지 못했습니다.', 500);
}
