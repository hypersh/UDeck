<?php
/**
 * UDeck - 공통 함수
 */
declare(strict_types=1);

/** HTML 이스케이프 */
function e($s): string
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** JSON 응답 후 종료 */
function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_fail(string $message, int $status = 400): void
{
    // 오류 문구도 설정 언어로 바꿔 보낸다 (i18n.php가 불러와진 경우)
    if (function_exists('t')) {
        $message = t($message);
    }
    json_out(['ok' => false, 'error' => $message], $status);
}

/** 요청 본문 JSON 읽기 (최대 2MB) */
function json_in(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 2 * 1024 * 1024);
    if ($raw === false || $raw === '') {
        return [];
    }
    $d = json_decode($raw, true);
    if (!is_array($d)) {
        json_fail('요청 형식이 JSON이 아닙니다.');
    }
    return $d;
}

/** 문자열 정리: 앞뒤 공백 제거, 최대 길이 제한, 빈 값은 null */
function clean($v, int $max = 2000): ?string
{
    if ($v === null) {
        return null;
    }
    $s = trim((string)$v);
    if ($s === '') {
        return null;
    }
    return mb_substr($s, 0, $max, 'UTF-8');
}

function int_or_null($v): ?int
{
    if ($v === null || $v === '' || !is_numeric($v)) {
        return null;
    }
    return (int)$v;
}

/**
 * 다양한 시각 입력을 KST 'Y-m-d H:i:s'로 변환한다.
 * 허용: '2026-11-18T22:30', '2026-11-18 22:30:00', ISO 8601(타임존 포함), 유닉스 초.
 */
function to_kst(?string $v): ?string
{
    if ($v === null || trim($v) === '') {
        return null;
    }
    $v = trim($v);
    $tz = new DateTimeZone('Asia/Seoul');
    try {
        if (ctype_digit($v)) {
            $d = (new DateTimeImmutable('@' . $v))->setTimezone($tz);
        } else {
            // 타임존 표기가 없으면 KST로 간주
            $d = new DateTimeImmutable($v, $tz);
            $d = $d->setTimezone($tz);
        }
        return $d->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

/** 'YYYY-MM-DD' 날짜 검증 */
function to_date(?string $v): ?string
{
    if ($v === null || !preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) {
        return null;
    }
    return substr($v, 0, 10);
}

/** 접속 IP */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** IP가 CIDR 목록에 포함되는지 (IPv4, IPv6 모두) */
function ip_allowed(string $ip, array $list): bool
{
    if (!$list) {
        return true;
    }
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return false;
    }
    foreach ($list as $cidr) {
        [$net, $bits] = array_pad(explode('/', (string)$cidr, 2), 2, null);
        $netBin = @inet_pton($net);
        if ($netBin === false || strlen($netBin) !== strlen($bin)) {
            continue;
        }
        $bits = $bits === null ? strlen($bin) * 8 : (int)$bits;
        $bytes = intdiv($bits, 8);
        $rem   = $bits % 8;
        if (substr($bin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            continue;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = chr((0xFF << (8 - $rem)) & 0xFF);
        if ((($bin[$bytes] ?? "\0") & $mask) === (($netBin[$bytes] ?? "\0") & $mask)) {
            return true;
        }
    }
    return false;
}

/** settings 테이블 읽기·쓰기 */
function setting_get(string $key, ?string $default = null): ?string
{
    $st = udeck_db()->prepare('SELECT value FROM settings WHERE key = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : $v;
}

function setting_set(string $key, ?string $value): void
{
    udeck_db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value')->execute([$key, $value]);
}

/** 이 앱의 URL 경로 (/udeck/) */
function base_path(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($dir, '/') . '/';
}

function redirect(string $page): void
{
    header('Location: ' . base_path() . $page);
    exit;
}

/** 정적 파일 캐시 무효화용 버전 */
function asset(string $path): string
{
    $file = UDECK_WWW . '/' . $path;
    $v = is_file($file) ? filemtime($file) : 0;
    return base_path() . $path . '?v=' . $v;
}
