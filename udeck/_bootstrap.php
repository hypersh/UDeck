<?php
/**
 * UDeck 공통 시작 파일
 * 위치: (웹 루트)/udeck/_bootstrap.php
 * 웹 루트 밖의 비공개 폴더(웹 루트와 같은 위치의 udeck_private)를 찾아 설정, DB, 세션을 준비한다.
 */
declare(strict_types=1);

// 예: /home/me/www/udeck → /home/me/udeck_private
define('UDECK_PRIVATE', dirname(__DIR__, 2) . '/udeck_private');
define('UDECK_WWW', __DIR__);
define('UDECK_VERSION', '1.2.0');

if (!is_dir(UDECK_PRIVATE)) {
    http_response_code(500);
    exit('UDeck 비공개 폴더(udeck_private)를 찾을 수 없습니다. 업로드 위치를 확인하세요. / UDeck private folder (udeck_private) not found. Check the upload location.');
}

// 설정: 기본값 위에 config.php 값을 덮어쓴다
$UDECK_CONFIG = [
    'setup_key'        => '',
    'remember_seconds' => 60 * 60 * 24 * 14,
    'login_max_fail'   => 5,
    'login_window_min' => 15,
    'allowed_ips'      => [],
    'timezone'         => 'Asia/Seoul',
    'language'         => 'ko',       // 설정 화면에서 언어를 고르기 전의 기본 언어 (ko | en)
];
if (is_file(UDECK_PRIVATE . '/config.php')) {
    $UDECK_CONFIG = array_merge($UDECK_CONFIG, (array)require UDECK_PRIVATE . '/config.php');
}
date_default_timezone_set($UDECK_CONFIG['timezone']);
mb_internal_encoding('UTF-8');

require_once UDECK_PRIVATE . '/lib/db.php';
require_once UDECK_PRIVATE . '/lib/util.php';
require_once UDECK_PRIVATE . '/lib/auth.php';
require_once UDECK_PRIVATE . '/lib/repo.php';
require_once UDECK_PRIVATE . '/lib/i18n.php';

// 오류는 화면에 노출하지 않고 기록만 남긴다
if (!is_dir(UDECK_PRIVATE . '/data')) {
    @mkdir(UDECK_PRIVATE . '/data', 0750, true);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', UDECK_PRIVATE . '/data/php_error.log');

// 접속 IP 제한 (설정한 경우에만)
if (!ip_allowed(client_ip(), (array)$UDECK_CONFIG['allowed_ips'])) {
    http_response_code(403);
    exit('허용되지 않은 접속 위치입니다. / Access from this location is not allowed.');
}

// 외부 수신(ingest.php)은 세션을 쓰지 않는다
if (!defined('UDECK_NO_SESSION')) {
    auth_session_start();
}
