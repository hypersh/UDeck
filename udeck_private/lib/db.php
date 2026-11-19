<?php
/**
 * UDeck - SQLite 연결 및 자동 초기화
 * 위치: udeck_private/lib/db.php (웹 루트 밖)
 */
declare(strict_types=1);

function udeck_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dataDir = UDECK_PRIVATE . '/data';
    if (!is_dir($dataDir) && !mkdir($dataDir, 0750, true)) {
        throw new RuntimeException('데이터 폴더를 만들 수 없습니다: ' . $dataDir);
    }
    $dbFile  = $dataDir . '/udeck.sqlite';
    $isNew   = !file_exists($dbFile);

    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');    // 읽기·쓰기 동시성 향상
    $pdo->exec('PRAGMA synchronous = NORMAL');  // WAL과 함께 쓰는 권장값
    $pdo->exec('PRAGMA foreign_keys = ON');     // 연결마다 켜야 함
    $pdo->exec('PRAGMA busy_timeout = 5000');   // 동시 쓰기 시 5초 대기

    if ($isNew || !udeck_table_exists($pdo, 'settings')) {
        udeck_apply_schema($pdo);
    }
    udeck_migrate($pdo);

    return $pdo;
}

function udeck_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
}

function udeck_apply_schema(PDO $pdo): void
{
    $sql = file_get_contents(UDECK_PRIVATE . '/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('schema.sql을 읽을 수 없습니다.');
    }
    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);          // PDO_SQLITE는 여러 문장을 한 번에 실행한다
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * 스키마 버전 관리. 이후 v2, v3 변경은 아래 배열에 추가한다.
 * (SQLite 3.26은 DROP COLUMN을 지원하지 않으므로 컬럼 추가 위주로 변경한다.)
 */
function udeck_migrate(PDO $pdo): void
{
    $current = (int)$pdo->query("SELECT value FROM settings WHERE key='schema_version'")->fetchColumn();
    $migrations = [
        // v2: 구글 데이터를 계정별로 구분 (메일·캘린더·할 일을 서로 다른 계정에서 보낼 수 있게)
        2 => "ALTER TABLE google_items ADD COLUMN account TEXT NOT NULL DEFAULT '';
              CREATE INDEX IF NOT EXISTS idx_google_account ON google_items(kind, account);",
        // v3: PC 색 지정, 프로젝트의 주 작업공간(PC)
        3 => "ALTER TABLE workspaces ADD COLUMN color TEXT NOT NULL DEFAULT 'slate';
              UPDATE workspaces SET color = CASE code WHEN 'home' THEN 'teal' WHEN 'office' THEN 'indigo'
                                                     WHEN 'laptop' THEN 'ochre' ELSE 'slate' END;
              ALTER TABLE projects ADD COLUMN workspace_id INTEGER REFERENCES workspaces(id) ON DELETE SET NULL;",
        // v4: 마감 종류 (none: 마감 없음, self: 자체 마감, fixed: 정해진 제출기한)
        4 => "ALTER TABLE projects ADD COLUMN due_type TEXT NOT NULL DEFAULT 'none';
              UPDATE projects SET due_type = CASE WHEN due_date IS NULL OR due_date = '' THEN 'none' ELSE 'self' END;",
    ];
    foreach ($migrations as $ver => $sql) {
        if ($ver <= $current) {
            continue;
        }
        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $pdo->prepare("UPDATE settings SET value=? WHERE key='schema_version'")->execute([(string)$ver]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

/** KST 현재 시각 'YYYY-MM-DD HH:MM:SS' */
function udeck_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->format('Y-m-d H:i:s');
}
