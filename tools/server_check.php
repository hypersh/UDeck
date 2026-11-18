<?php
/**
 * UDeck - server environment check
 * Usage: upload to your web root, open https://your-domain.com/server_check.php in a browser.
 * Delete this file from the server after checking. It shows server paths and settings.
 */
header('Content-Type: text/html; charset=utf-8');

function row($label, $ok, $detail = '') {
    $mark = $ok === null ? '➖' : ($ok ? '✅' : '❌');
    echo "<tr><td>$mark</td><td>" . htmlspecialchars($label) . "</td><td>" . htmlspecialchars((string)$detail) . "</td></tr>";
}

$docRoot   = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
$parentDir = dirname($docRoot);

echo "<h2>UDeck server check</h2><table border='1' cellpadding='6' style='border-collapse:collapse;font-family:sans-serif'>";
echo "<tr><th></th><th>Item</th><th>Result</th></tr>";

// 1. PHP
row('PHP version (8.0+ recommended)', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION);

// 2. SQLite
$drivers = class_exists('PDO') ? PDO::getAvailableDrivers() : [];
row('PDO extension', class_exists('PDO'), class_exists('PDO') ? 'available' : 'missing');
row('pdo_sqlite driver (required)', in_array('sqlite', $drivers, true), implode(', ', $drivers));
row('sqlite3 extension', extension_loaded('sqlite3'), extension_loaded('sqlite3') ? SQLite3::version()['versionString'] : 'missing');

// 3. 실제 SQLite 쓰기 테스트 (WAL 모드 포함)
$testDir = __DIR__;
$testDb  = $testDir . '/_udeck_test.sqlite';
try {
    $pdo = new PDO('sqlite:' . $testDb);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $ver  = $pdo->query('select sqlite_version()')->fetchColumn();
    $wal  = $pdo->query('PRAGMA journal_mode=WAL')->fetchColumn();
    $pdo->exec('CREATE TABLE IF NOT EXISTS t (id INTEGER PRIMARY KEY, v TEXT)');
    $pdo->exec("INSERT INTO t (v) VALUES ('ok')");
    $n = $pdo->query('SELECT COUNT(*) FROM t')->fetchColumn();
    $json = $pdo->query("select json_extract('{\"a\":1}', '$.a')")->fetchColumn();
    row('SQLite create/write/read', $n > 0, "SQLite $ver / journal_mode=$wal");
    row('SQLite JSON functions', $json == 1, $json == 1 ? 'supported' : 'not supported');
    $pdo = null;
} catch (Throwable $e) {
    row('SQLite create/write/read', false, $e->getMessage());
}
foreach (['', '-wal', '-shm'] as $s) { @unlink($testDb . $s); }

// 4. 웹 루트 밖 저장 가능 여부 (DB 보안용)
row('Web root', null, $docRoot);
row('Parent of web root', null, $parentDir);
$outside = $parentDir . '/_udeck_write_test.txt';
$canOutside = @file_put_contents($outside, 'test') !== false;
if ($canOutside) { @unlink($outside); }
row('Writable outside web root (recommended)', $canOutside, $canOutside ? 'udeck_private can live outside the web root' : 'not possible - protect with .htaccess');
$ob = ini_get('open_basedir');
row('open_basedir', null, $ob ?: 'none');

// 5. .htaccess 지원 여부 (간접 확인)
row('Server software', null, $_SERVER['SERVER_SOFTWARE'] ?? 'unknown');

// 6. 이후 단계(구글 연동·API)에 필요한 확장
foreach (['json', 'mbstring', 'openssl', 'curl', 'session', 'fileinfo'] as $ext) {
    row("$ext extension", extension_loaded($ext));
}
row('allow_url_fopen', (bool)ini_get('allow_url_fopen'), ini_get('allow_url_fopen') ? 'On' : 'Off');
row('password_hash (login)', function_exists('password_hash'));
row('random_bytes (keys)', function_exists('random_bytes'));

// 7. 기타
row('Default time zone', null, date_default_timezone_get());
row('HTTPS', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', $_SERVER['HTTPS'] ?? 'off');
row('upload_max_filesize', null, ini_get('upload_max_filesize'));
row('memory_limit', null, ini_get('memory_limit'));
row('max_execution_time', null, ini_get('max_execution_time') . 's');

echo "</table><p style='color:red;font-family:sans-serif'><b>Delete this file from the server after checking.</b></p>";
