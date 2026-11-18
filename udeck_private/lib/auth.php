<?php
/**
 * UDeck - 로그인, 세션, CSRF
 * 사용자 1명 전용. 비밀번호 해시는 settings 테이블(password_hash)에 저장한다.
 */
declare(strict_types=1);

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/**
 * 세션 시작. 공유 호스팅의 기본 세션 청소(보통 24분)에 지워지지 않도록
 * 세션 파일을 비공개 폴더에 따로 저장한다.
 */
function auth_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $cfg = $GLOBALS['UDECK_CONFIG'];
    $dir = UDECK_PRIVATE . '/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
    }
    ini_set('session.gc_maxlifetime', (string)$cfg['remember_seconds']);
    ini_set('session.use_strict_mode', '1');
    session_name('UDECKSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path(),
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function password_is_set(): bool
{
    return (string)setting_get('password_hash', '') !== '';
}

function is_logged_in(): bool
{
    if (empty($_SESSION['uid'])) {
        return false;
    }
    $now = time();
    $remember = !empty($_SESSION['remember']);
    $limit = $remember ? $GLOBALS['UDECK_CONFIG']['remember_seconds'] : 60 * 60 * 12; // 미유지 시 12시간
    if ($now - (int)($_SESSION['last'] ?? 0) > $limit) {
        auth_logout();
        return false;
    }
    // 비밀번호가 바뀌면 기존 세션 무효화
    if (($_SESSION['pwv'] ?? '') !== (string)setting_get('password_version', '1')) {
        auth_logout();
        return false;
    }
    if ($now - (int)($_SESSION['last'] ?? 0) > 60) {
        $_SESSION['last'] = $now;
    }
    return true;
}

/** 화면용: 로그인 안 되어 있으면 로그인 또는 설치 화면으로 이동 */
function require_login_page(): void
{
    if (!password_is_set()) {
        redirect('setup.php');
    }
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

/** API용: 로그인과 CSRF 확인 */
function require_login_api(): void
{
    if (!is_logged_in()) {
        json_fail('로그인이 필요합니다.', 401);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals(csrf_token(), $sent)) {
            json_fail('보안 토큰이 맞지 않습니다. 화면을 새로고침하세요.', 403);
        }
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** 폼 POST용 CSRF 확인 */
function csrf_check_form(): bool
{
    return hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''));
}

/** 최근 실패 횟수 */
function login_fail_count(string $ip): int
{
    $win = (int)$GLOBALS['UDECK_CONFIG']['login_window_min'];
    $st = udeck_db()->prepare("SELECT COUNT(*) FROM login_attempts
        WHERE ip = ? AND success = 0 AND at >= datetime('now', '+9 hours', ?)");
    $st->execute([$ip, '-' . $win . ' minutes']);
    return (int)$st->fetchColumn();
}

function login_record(string $ip, bool $ok): void
{
    $db = udeck_db();
    $db->prepare('INSERT INTO login_attempts (ip, success) VALUES (?, ?)')->execute([$ip, $ok ? 1 : 0]);
    if ($ok) {
        // 성공하면 이 IP의 실패 기록을 지운다
        $db->prepare('DELETE FROM login_attempts WHERE ip = ? AND success = 0')->execute([$ip]);
    }
    // 30일 지난 기록 정리
    $db->exec("DELETE FROM login_attempts WHERE at < datetime('now', '+9 hours', '-30 days')");
}

/**
 * 로그인 시도. 반환: ['ok'=>bool, 'error'=>string]
 */
function auth_login(string $password, bool $remember): array
{
    $cfg = $GLOBALS['UDECK_CONFIG'];
    $ip  = client_ip();
    $fails = login_fail_count($ip);
    if ($fails >= $cfg['login_max_fail']) {
        return ['ok' => false, 'error' => t('로그인 시도가 너무 많습니다. {m}분 뒤에 다시 시도하세요.', ['m' => $cfg['login_window_min']]), 'locked' => true];
    }
    $hash = (string)setting_get('password_hash', '');
    if ($hash === '' || !password_verify($password, $hash)) {
        login_record($ip, false);
        $left = $cfg['login_max_fail'] - $fails - 1;
        $msg = $left > 0
            ? t('비밀번호가 맞지 않습니다. {n}번 더 틀리면 {m}분 동안 로그인할 수 없습니다.', ['n' => $left, 'm' => $cfg['login_window_min']])
            : t('비밀번호가 맞지 않습니다. {m}분 동안 로그인할 수 없습니다.', ['m' => $cfg['login_window_min']]);
        return ['ok' => false, 'error' => $msg, 'locked' => $left <= 0];
    }
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        setting_set('password_hash', password_hash($password, PASSWORD_DEFAULT));
    }
    login_record($ip, true);
    session_regenerate_id(true);
    $_SESSION = [
        'uid'      => 1,
        'remember' => $remember,
        'last'     => time(),
        'pwv'      => (string)setting_get('password_version', '1'),
        'csrf'     => bin2hex(random_bytes(32)),
    ];
    if ($remember) {
        // 브라우저를 닫아도 유지되도록 쿠키 만료 시각을 지정
        setcookie(session_name(), session_id(), [
            'expires'  => time() + $cfg['remember_seconds'],
            'path'     => base_path(),
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    return ['ok' => true, 'error' => ''];
}

function auth_logout(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => base_path(),
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_destroy();
    }
}

/** 비밀번호 저장 (설치, 변경 공용) */
function auth_set_password(string $password): void
{
    setting_set('password_hash', password_hash($password, PASSWORD_DEFAULT));
    $v = (int)setting_get('password_version', '0') + 1;
    setting_set('password_version', (string)$v);
}

/** 비밀번호 규칙 검사. 문제가 없으면 null */
function password_problem(string $pw, string $pw2): ?string
{
    if (mb_strlen($pw) < 12) {
        return t('비밀번호는 12자 이상이어야 합니다.');
    }
    if ($pw !== $pw2) {
        return t('두 비밀번호가 서로 다릅니다.');
    }
    return null;
}
