<?php
/**
 * UDeck - 데이터 조회·저장
 */
declare(strict_types=1);

const UDECK_ENV_BUCKETS = 21;      // 7일 × 하루 3구간(8시간)
const UDECK_ENV_HOURS   = 8;

/** PC 색 팔레트 (CSS 변수 --c-이름 과 짝) */
const UDECK_COLORS = [
    'teal' => '청록', 'indigo' => '남색', 'ochre' => '황토', 'rose' => '분홍',
    'violet' => '보라', 'olive' => '올리브', 'sky' => '하늘', 'slate' => '회색',
];

/** 코드 형식: 영문 소문자·숫자·_·- 1~30자 */
function valid_code(?string $c): bool
{
    return $c !== null && (bool)preg_match('/^[a-z0-9][a-z0-9_-]{0,29}$/', $c);
}

/* =====================================================================
   기본 조회
   ===================================================================== */
function ws_all(): array
{
    return udeck_db()->query('SELECT * FROM workspaces ORDER BY sort_order, id')->fetchAll();
}

function tools_all(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM tools' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id';
    return udeck_db()->query($sql)->fetchAll();
}

/** 도구 아이콘 기본 이름: 코드·이름으로 판단, 해당 없으면 이름 앞 12자 */
function tool_short_default(string $code, string $name): string
{
    $s = strtolower($code . ' ' . $name);
    if (strpos($s, 'codex') !== false)                                       { return 'Codex'; }
    if (preg_match('/claude[\s_\-]*code/', $s))                            { return 'Claude C'; }
    if (strpos($s, 'claude') !== false)                                      { return 'Claude'; }
    if (strpos($s, 'gpt') !== false)                                         { return 'GPT'; }
    if (strpos($s, 'gemini') !== false)                                      { return 'Gemini'; }
    if (strpos($s, 'antigravity') !== false)                                 { return 'Antigravity'; }
    return mb_substr(trim($name) !== '' ? trim($name) : $code, 0, 12);
}

function ws_by_code(string $code): ?array
{
    $st = udeck_db()->prepare('SELECT * FROM workspaces WHERE code = ?');
    $st->execute([$code]);
    return $st->fetch() ?: null;
}

function tool_by_code(string $code): ?array
{
    $st = udeck_db()->prepare('SELECT * FROM tools WHERE code = ?');
    $st->execute([$code]);
    return $st->fetch() ?: null;
}

function row_exists(string $table, ?int $id): bool
{
    if (!$id) {
        return false;
    }
    $st = udeck_db()->prepare("SELECT 1 FROM {$table} WHERE id = ?");
    $st->execute([$id]);
    return (bool)$st->fetchColumn();
}

/* =====================================================================
   보드 전체 데이터 (화면 1회 로딩)
   ===================================================================== */
function board_data(bool $includeDone = false): array
{
    $db  = udeck_db();
    $now = udeck_now();

    // 작업공간 + 최근 활동 시각
    $workspaces = $db->query('SELECT w.*,
            (SELECT MAX(last_active_at) FROM activities a WHERE a.workspace_id = w.id) AS last_activity_at
        FROM workspaces w ORDER BY sort_order, id')->fetchAll();

    $tools = tools_all();

    // 작업공간 판: (PC, 프로젝트)별 묶음. 프로젝트가 없는 기록은 PC마다 '미분류' 하나로 묶는다.
    // 완료·보관 프로젝트는 빼고, 미분류는 최근 14일 기록만 보여 준다.
    $rows = $db->query("SELECT a.id, a.workspace_id, a.tool_id, a.project_id, a.source, a.stage, a.title,
            a.summary, a.last_prompt, a.cwd, a.progress, a.last_active_at, p.name AS project_name
        FROM activities a LEFT JOIN projects p ON p.id = a.project_id
        WHERE (a.project_id IS NOT NULL AND p.status IN ('active','paused'))
           OR (a.project_id IS NULL AND a.last_active_at >= datetime('now', '+9 hours', '-14 days'))
        ORDER BY a.last_active_at DESC, a.id DESC")->fetchAll();
    $groups = [];
    foreach ($rows as $r) {
        $k = $r['workspace_id'] . '|' . (int)$r['project_id'];
        if (!isset($groups[$k])) {
            $groups[$k] = [
                'key'            => $k,
                'workspace_id'   => (int)$r['workspace_id'],
                'project_id'     => $r['project_id'] ? (int)$r['project_id'] : null,
                'project_name'   => $r['project_name'],
                'last_active_at' => $r['last_active_at'],
                'summary'        => $r['summary'] ?: ($r['title'] ?: $r['last_prompt']),
                'stage'          => $r['stage'],
                'progress'       => null,
                'tools'          => [],
                'items'          => [],
                'count'          => 0,
            ];
        }
        $g = &$groups[$k];
        $g['count']++;
        if ($g['progress'] === null && $r['progress'] !== null) {
            $g['progress'] = (int)$r['progress'];
        }
        $tid = (int)$r['tool_id'];
        if (!in_array($tid, $g['tools'], true)) {
            $g['tools'][] = $tid;
            if (count($g['items']) < 6) {
                $g['items'][] = $r;          // 도구별 최신 기록
            }
        }
        unset($g);
    }

    // 7일 활동 막대 (PC, 프로젝트별)
    $env = [];
    $st = $db->query("SELECT e.workspace_id, a.project_id, e.at FROM activity_events e
        JOIN activities a ON a.id = e.activity_id
        WHERE e.at >= datetime('now', '+9 hours', '-7 days')");
    $nowTs = strtotime($now);
    foreach ($st as $r) {
        $k   = $r['workspace_id'] . '|' . (int)$r['project_id'];
        $age = ($nowTs - strtotime($r['at'])) / 3600;
        $idx = UDECK_ENV_BUCKETS - 1 - (int)floor($age / UDECK_ENV_HOURS);
        if ($idx < 0 || $idx >= UDECK_ENV_BUCKETS) {
            continue;
        }
        $env[$k] = $env[$k] ?? array_fill(0, UDECK_ENV_BUCKETS, 0);
        $env[$k][$idx]++;
    }
    foreach ($groups as $k => &$g) {
        $g['env'] = $env[$k] ?? array_fill(0, UDECK_ENV_BUCKETS, 0);
    }
    unset($g);
    $pcproj = array_values($groups);

    // 프로젝트 (화면에서 등록순, 마감일순, 중요도순으로 정렬한다)
    $where = $includeDone ? '' : "WHERE status IN ('active','paused')";
    $projects = $db->query("SELECT * FROM projects {$where} ORDER BY sort_order, id")->fetchAll();
    $actSt = $db->prepare('SELECT workspace_id, tool_id, progress, last_active_at
        FROM activities WHERE project_id = ? ORDER BY last_active_at DESC, id DESC');
    foreach ($projects as &$p) {
        $actSt->execute([$p['id']]);
        $wsIds = $toolIds = [];
        $prog = null;
        $last = null;
        foreach ($actSt as $a) {
            $last = $last ?? $a['last_active_at'];
            if (!in_array((int)$a['workspace_id'], $wsIds, true)) { $wsIds[] = (int)$a['workspace_id']; }
            if (!in_array((int)$a['tool_id'], $toolIds, true))    { $toolIds[] = (int)$a['tool_id']; }
            if ($prog === null && $a['progress'] !== null)         { $prog = (int)$a['progress']; }
        }
        $p['ws_ids']   = $wsIds;     // 최근에 작업한 PC가 앞
        $p['tool_ids'] = $toolIds;   // 최근에 쓴 도구가 앞
        $p['progress'] = $prog;      // 가장 최근에 적은 진행률
        $p['last_at']  = $last;
    }
    unset($p);

    // 최근 기록
    $feed = $db->query('SELECT a.*, p.name AS project_name
        FROM activities a LEFT JOIN projects p ON p.id = a.project_id
        ORDER BY a.last_active_at DESC, a.id DESC LIMIT 30')->fetchAll();

    // 구글
    // 일정: 이번 달 전후 1주 (화면에서 오늘·내일, 이번 주, 이번 달로 나눠 보여 준다)
    $events = $db->query("SELECT * FROM google_items WHERE kind = 'event'
        AND COALESCE(end_at, start_at) >= datetime('now', '+9 hours', 'start of month', '-7 days')
        AND start_at < datetime('now', '+9 hours', 'start of month', '+1 month', '+7 days')
        ORDER BY start_at, all_day DESC, title")->fetchAll();
    $tasks = $db->query("SELECT * FROM google_items WHERE kind = 'task'
        AND COALESCE(status, '') <> 'completed'
        ORDER BY CASE WHEN sort_key IS NULL THEN 1 ELSE 0 END, account, sort_key,
                 container, CASE WHEN due_at IS NULL THEN 1 ELSE 0 END, due_at, title LIMIT 300")->fetchAll();
    $mails = $db->query("SELECT * FROM google_items WHERE kind = 'mail'
        ORDER BY start_at DESC LIMIT 50")->fetchAll();

    $notes = $db->query('SELECT * FROM notes WHERE done = 0
        ORDER BY pinned DESC, updated_at DESC, id DESC LIMIT 200')->fetchAll();

    return [
        'ok'         => true,
        'now'        => $now,
        'workspaces' => $workspaces,
        'tools'      => $tools,
        'pcproj'     => $pcproj,
        'projects'   => $projects,
        'feed'       => $feed,
        'google'     => [
            'events'    => $events,
            'tasks'     => $tasks,
            'mails'     => $mails,
            'unread'    => (int)$db->query("SELECT COALESCE(SUM(CAST(value AS INTEGER)), 0) FROM settings
                                WHERE key LIKE 'gmail_unread:%'")->fetchColumn(),
            'synced_at' => setting_get('google_synced_at'),
            'accounts'  => $db->query("SELECT kind, account, COUNT(*) AS n FROM google_items
                                GROUP BY kind, account ORDER BY kind, account")->fetchAll(),
        ],
        'notes'      => $notes,
    ];
}

/* =====================================================================
   프로젝트 자동 연결: 작업 폴더(cwd)로 프로젝트 찾기
   ===================================================================== */
function project_match_cwd(?string $cwd): ?int
{
    if (!$cwd) {
        return null;
    }
    $norm = strtolower(trim(str_replace('\\', '/', $cwd), '/'));
    $segments = array_values(array_filter(explode('/', $norm), 'strlen'));
    $rows = udeck_db()->query("SELECT id, folder_path FROM projects
        WHERE folder_path IS NOT NULL AND folder_path <> '' AND status <> 'archived'")->fetchAll();
    $folders = [];
    foreach ($rows as $r) {
        $f = strtolower(trim(str_replace('\\', '/', $r['folder_path']), '/'));
        if ($f !== '') {
            $folders[] = [(int)$r['id'], $f];
        }
    }
    // 경로의 가장 안쪽 폴더부터 바깥쪽으로 올라가며 처음 맞는 프로젝트를 고른다.
    // 예: D:/Work/Grant_2027/docs → Grant_2027 이 Work 보다 먼저 맞는다.
    for ($i = count($segments); $i >= 1; $i--) {
        $prefix = implode('/', array_slice($segments, 0, $i));
        foreach ($folders as [$id, $f]) {
            if ($prefix === $f || str_ends_with($prefix, '/' . $f)) {
                return $id;
            }
        }
    }
    return null;
}

function folder_name(?string $cwd): ?string
{
    if (!$cwd) {
        return null;
    }
    $parts = preg_split('#[\\\\/]+#', rtrim($cwd, '\\/'));
    $last = end($parts);
    return $last !== '' ? $last : null;
}

/** 프로젝트의 다음 할 일 갱신 */
function project_set_next(int $projectId, string $next): void
{
    udeck_db()->prepare("UPDATE projects SET next_action = ?, updated_at = datetime('now','+9 hours') WHERE id = ?")
        ->execute([$next, $projectId]);
}

/** 미분류였던 같은 폴더의 기록을 프로젝트에 연결하고, 폴더 이름이 비어 있으면 채운다 */
function project_learn_folder(int $projectId, ?string $cwd): void
{
    $name = folder_name($cwd);
    if (!$name) {
        return;
    }
    $db = udeck_db();
    $st = $db->prepare('SELECT folder_path FROM projects WHERE id = ?');
    $st->execute([$projectId]);
    $cur = $st->fetchColumn();
    if ($cur === null || $cur === '' || $cur === false) {
        $db->prepare("UPDATE projects SET folder_path = ?, updated_at = datetime('now','+9 hours') WHERE id = ?")
            ->execute([$name, $projectId]);
    }
    $db->prepare('UPDATE activities SET project_id = ? WHERE project_id IS NULL AND cwd = ?')
        ->execute([$projectId, $cwd]);
}

/* =====================================================================
   작업 기록
   ===================================================================== */
function activity_event(int $activityId, int $wsId, int $toolId, ?string $at = null): void
{
    udeck_db()->prepare('INSERT INTO activity_events (activity_id, workspace_id, tool_id, at)
        VALUES (?, ?, ?, COALESCE(?, datetime(\'now\',\'+9 hours\')))')
        ->execute([$activityId, $wsId, $toolId, $at]);
}

/** 진행률: 0~100 정수, 비어 있으면 null */
function progress_val($v): ?int
{
    if ($v === null || $v === '') {
        return null;
    }
    return max(0, min(100, (int)round((float)$v)));
}

/** 보드에서 직접 입력한 기록 저장. 반환: 기록 id */
function activity_save_manual(array $in): int
{
    $db = udeck_db();
    $id      = int_or_null($in['id'] ?? null);
    $wsId    = int_or_null($in['workspace_id'] ?? null);
    $toolId  = int_or_null($in['tool_id'] ?? null);
    $projId  = int_or_null($in['project_id'] ?? null);
    $summary = clean($in['summary'] ?? null, 1000);
    $title   = clean($in['title'] ?? null, 200);
    $stage   = clean($in['stage'] ?? null, 40);
    $cwd     = clean($in['cwd'] ?? null, 500);
    $next    = clean($in['next_action'] ?? null, 300);
    $at      = to_kst($in['at'] ?? null) ?? udeck_now();
    $prog    = progress_val($in['progress'] ?? null);

    if (!row_exists('workspaces', $wsId))  { json_fail('PC를 선택하세요.'); }
    if (!row_exists('tools', $toolId))     { json_fail('도구를 선택하세요.'); }
    if ($projId && !row_exists('projects', $projId)) { $projId = null; }
    if (!$summary)                         { json_fail('작업 내용을 한 줄 이상 적으세요.'); }
    if (!$projId && $cwd)                  { $projId = project_match_cwd($cwd); }

    $db->beginTransaction();
    try {
        if ($id) {
            $st = $db->prepare('SELECT * FROM activities WHERE id = ?');
            $st->execute([$id]);
            if (!$st->fetch()) {
                $db->rollBack();
                json_fail('기록을 찾을 수 없습니다.', 404);
            }
            $db->prepare('UPDATE activities SET workspace_id=?, tool_id=?, project_id=?, stage=?, title=?,
                    summary=?, cwd=?, progress=?, last_active_at=? WHERE id=?')
                ->execute([$wsId, $toolId, $projId, $stage, $title, $summary, $cwd, $prog, $at, $id]);
            $db->prepare('UPDATE activity_events SET workspace_id=?, tool_id=? WHERE activity_id=?')
                ->execute([$wsId, $toolId, $id]);
        } else {
            $db->prepare("INSERT INTO activities (workspace_id, tool_id, project_id, source, stage, title,
                    summary, cwd, progress, turn_count, started_at, last_active_at)
                    VALUES (?, ?, ?, 'manual', ?, ?, ?, ?, ?, 1, ?, ?)")
                ->execute([$wsId, $toolId, $projId, $stage, $title, $summary, $cwd, $prog, $at, $at]);
            $id = (int)$db->lastInsertId();
            activity_event($id, $wsId, $toolId, $at);
        }
        if ($projId && $next) {
            project_set_next($projId, $next);
        }
        if ($projId && $cwd) {
            project_learn_folder($projId, $cwd);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
    return $id;
}

function activity_delete(int $id): void
{
    udeck_db()->prepare('DELETE FROM activities WHERE id = ?')->execute([$id]);
}

/** 기록의 프로젝트만 바꾸기 (미분류 지정) */
function activity_assign(int $id, ?int $projectId): void
{
    $db = udeck_db();
    $st = $db->prepare('SELECT cwd FROM activities WHERE id = ?');
    $st->execute([$id]);
    $cwd = $st->fetchColumn();
    if ($cwd === false) {
        json_fail('기록을 찾을 수 없습니다.', 404);
    }
    if ($projectId && !row_exists('projects', $projectId)) {
        json_fail('프로젝트를 찾을 수 없습니다.', 404);
    }
    $db->prepare('UPDATE activities SET project_id = ? WHERE id = ?')->execute([$projectId, $id]);
    if ($projectId && $cwd) {
        project_learn_folder($projectId, $cwd);
    }
}

/* =====================================================================
   프로젝트
   ===================================================================== */
function slugify(string $name): string
{
    $s = strtolower(trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', $name), '-'));
    return $s !== '' ? mb_substr($s, 0, 60) : 'project';
}

function project_save(array $in): int
{
    $db = udeck_db();
    $id     = int_or_null($in['id'] ?? null);
    $name   = clean($in['name'] ?? null, 100);
    if (!$name) {
        json_fail('프로젝트 이름을 입력하세요.');
    }
    $status = in_array($in['status'] ?? '', ['active', 'paused', 'done', 'archived'], true) ? $in['status'] : 'active';
    $pri    = (int)($in['priority'] ?? 2);
    $pri    = in_array($pri, [1, 2, 3], true) ? $pri : 2;
    $vals = [
        'name'        => $name,
        'category'    => clean($in['category'] ?? null, 30),
        'status'      => $status,
        'phase'       => clean($in['phase'] ?? null, 40),
        'priority'    => $pri,
        'due_date'    => to_date($in['due_date'] ?? null),
        'due_type'    => in_array($in['due_type'] ?? '', ['none', 'self', 'fixed'], true) ? $in['due_type'] : 'self',
        'folder_path' => clean($in['folder_path'] ?? null, 200),
        'next_action' => clean($in['next_action'] ?? null, 300),
        'workspace_id'=> null,
    ];
    // 마감 없음이면 날짜를 지우고, 날짜가 없으면 마감 없음으로 본다
    if ($vals['due_type'] === 'none' || !$vals['due_date']) {
        $vals['due_type'] = 'none';
        $vals['due_date'] = null;
    }
    $wsId = int_or_null($in['workspace_id'] ?? null);
    if ($wsId && row_exists('workspaces', $wsId)) {
        $vals['workspace_id'] = $wsId;
    }
    if ($id) {
        if (!row_exists('projects', $id)) {
            json_fail('프로젝트를 찾을 수 없습니다.', 404);
        }
        $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($vals)));
        $vals['id'] = $id;
        $db->prepare("UPDATE projects SET $set, updated_at = datetime('now','+9 hours') WHERE id = :id")->execute($vals);
    } else {
        // 고유 slug 생성
        $base = slugify($name);
        $slug = $base;
        $n = 2;
        $chk = $db->prepare('SELECT 1 FROM projects WHERE slug = ?');
        while (true) {
            $chk->execute([$slug]);
            if (!$chk->fetchColumn()) {
                break;
            }
            $slug = $base . '-' . $n++;
        }
        $vals['slug'] = $slug;
        $vals['sort_order'] = (int)$db->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM projects')->fetchColumn();
        $cols = implode(', ', array_keys($vals));
        $ph   = ':' . implode(', :', array_keys($vals));
        $db->prepare("INSERT INTO projects ($cols) VALUES ($ph)")->execute($vals);
        $id = (int)$db->lastInsertId();
    }
    // 폴더가 지정되면 같은 폴더의 미분류 기록을 연결
    if ($vals['folder_path']) {
        $rows = $db->query('SELECT id, cwd FROM activities WHERE project_id IS NULL AND cwd IS NOT NULL')->fetchAll();
        $up = $db->prepare('UPDATE activities SET project_id = ? WHERE id = ?');
        foreach ($rows as $r) {
            if (project_match_cwd($r['cwd']) === $id) {
                $up->execute([$id, $r['id']]);
            }
        }
    }
    return $id;
}

/**
 * 중요도 순서 바꾸기. ids: 화면에 보이는 프로젝트를 새 순서대로.
 * 보이지 않는(완료·보관) 프로젝트의 자리는 그대로 두고, 보이는 프로젝트들이 차지하던 자리만 새 순서로 채운다.
 */
function project_reorder(array $ids): void
{
    $db  = udeck_db();
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return;
    }
    $all = $db->query('SELECT id FROM projects ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN);
    $all = array_map('intval', $all);
    $set = array_flip($ids);
    $ids = array_values(array_filter($ids, fn($i) => in_array($i, $all, true)));
    $out = [];
    $k = 0;
    foreach ($all as $id) {
        $out[] = isset($set[$id]) ? ($ids[$k++] ?? $id) : $id;
    }
    $up = $db->prepare('UPDATE projects SET sort_order = ? WHERE id = ?');
    $db->beginTransaction();
    foreach ($out as $i => $id) {
        $up->execute([$i + 1, $id]);
    }
    $db->commit();
}

function project_delete(int $id): void
{
    udeck_db()->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
}

/* =====================================================================
   메모
   ===================================================================== */
function note_save(array $in): int
{
    $db   = udeck_db();
    $id   = int_or_null($in['id'] ?? null);
    $body = clean($in['body'] ?? null, 2000);
    if (!$body) {
        json_fail('메모 내용을 입력하세요.');
    }
    if ($id) {
        $db->prepare("UPDATE notes SET body = ?, updated_at = datetime('now','+9 hours') WHERE id = ?")->execute([$body, $id]);
        return $id;
    }
    $db->prepare('INSERT INTO notes (body) VALUES (?)')->execute([$body]);
    return (int)$db->lastInsertId();
}

function note_flag(int $id, string $field, bool $value): void
{
    if (!in_array($field, ['pinned', 'done'], true)) {
        json_fail('잘못된 요청입니다.');
    }
    udeck_db()->prepare("UPDATE notes SET {$field} = ?, updated_at = datetime('now','+9 hours') WHERE id = ?")
        ->execute([$value ? 1 : 0, $id]);
}

function note_delete(int $id): void
{
    udeck_db()->prepare('DELETE FROM notes WHERE id = ?')->execute([$id]);
}

/* =====================================================================
   외부 수신 (PC 에이전트, Claude Code hook, Apps Script)
   ===================================================================== */
function token_create(string $label, string $scope, ?int $wsId): string
{
    $raw = 'udk_' . bin2hex(random_bytes(24));
    udeck_db()->prepare('INSERT INTO api_tokens (label, token_hash, scope, workspace_id) VALUES (?, ?, ?, ?)')
        ->execute([$label, hash('sha256', $raw), $scope, $wsId]);
    return $raw;
}

function token_find(string $raw): ?array
{
    $st = udeck_db()->prepare('SELECT * FROM api_tokens WHERE token_hash = ? AND revoked = 0');
    $st->execute([hash('sha256', $raw)]);
    $t = $st->fetch();
    if ($t) {
        udeck_db()->prepare("UPDATE api_tokens SET last_used_at = datetime('now','+9 hours') WHERE id = ?")->execute([$t['id']]);
    }
    return $t ?: null;
}

function sync_log(string $source, bool $ok, int $items, ?string $msg = null): void
{
    $db = udeck_db();
    $db->prepare('INSERT INTO sync_log (source, ok, items, message) VALUES (?, ?, ?, ?)')
        ->execute([$source, $ok ? 1 : 0, $items, $msg ? mb_substr($msg, 0, 500) : null]);
    $db->exec("DELETE FROM sync_log WHERE at < datetime('now', '+9 hours', '-30 days')");
}

function ws_touch(int $wsId): void
{
    udeck_db()->prepare("UPDATE workspaces SET last_seen_at = datetime('now','+9 hours') WHERE id = ?")->execute([$wsId]);
}

/** 토큰과 요청으로 작업공간 결정 (토큰에 PC가 지정되어 있으면 그 PC로 고정) */
function ingest_ws(array $token, array $in): array
{
    if ($token['workspace_id']) {
        $st = udeck_db()->prepare('SELECT * FROM workspaces WHERE id = ?');
        $st->execute([$token['workspace_id']]);
        $ws = $st->fetch();
    } else {
        $ws = ws_by_code((string)($in['workspace'] ?? ''));
    }
    if (!$ws) {
        json_fail('workspace(home, office, laptop)를 확인하세요.');
    }
    return $ws;
}

/**
 * 작업 기록 수신.
 * 필수: tool, session_key / 선택: cwd, title, summary, last_prompt, stage, next_action, at, started_at, source
 */
function ingest_activity(array $token, array $in): array
{
    $db   = udeck_db();
    $ws   = ingest_ws($token, $in);
    $tool = tool_by_code((string)($in['tool'] ?? ''));
    if (!$tool) {
        json_fail('tool(chatgpt_app, claude_code 등)을 확인하세요.');
    }
    $key = clean($in['session_key'] ?? null, 200);
    if (!$key) {
        json_fail('session_key가 필요합니다.');
    }
    $source = in_array($in['source'] ?? '', ['hook', 'scan', 'handoff'], true) ? $in['source'] : 'hook';
    $cwd    = clean($in['cwd'] ?? null, 500);
    $at     = to_kst($in['at'] ?? null) ?? udeck_now();
    $f = [
        'title'       => clean($in['title'] ?? null, 200),
        'summary'     => clean($in['summary'] ?? null, 1000),
        'last_prompt' => clean($in['last_prompt'] ?? null, 500),
        'stage'       => clean($in['stage'] ?? null, 40),
        'meta_json'   => isset($in['meta']) ? json_encode($in['meta'], JSON_UNESCAPED_UNICODE) : null,
    ];
    $turns = int_or_null($in['turn_count'] ?? null);
    $prog  = progress_val($in['progress'] ?? null);

    $db->beginTransaction();
    try {
        $st = $db->prepare('SELECT * FROM activities WHERE workspace_id = ? AND tool_id = ? AND session_key = ?');
        $st->execute([$ws['id'], $tool['id'], $key]);
        $row = $st->fetch();
        if ($row) {
            $projId = $row['project_id'] ?: project_match_cwd($cwd ?? $row['cwd']);
            $db->prepare('UPDATE activities SET
                    project_id = ?, source = ?, cwd = COALESCE(?, cwd),
                    title = COALESCE(?, title), summary = COALESCE(?, summary),
                    last_prompt = COALESCE(?, last_prompt), stage = COALESCE(?, stage),
                    meta_json = COALESCE(?, meta_json), progress = COALESCE(?, progress),
                    turn_count = COALESCE(?, turn_count + 1), last_active_at = ?, ended_at = NULL
                WHERE id = ?')
                ->execute([$projId, $source, $cwd, $f['title'], $f['summary'], $f['last_prompt'], $f['stage'],
                           $f['meta_json'], $prog, $turns, $at, $row['id']]);
            $id = (int)$row['id'];
        } else {
            $projId = project_match_cwd($cwd);
            $db->prepare('INSERT INTO activities (workspace_id, tool_id, project_id, session_key, source, cwd,
                    title, summary, last_prompt, stage, meta_json, progress, turn_count, started_at, last_active_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$ws['id'], $tool['id'], $projId, $key, $source, $cwd, $f['title'], $f['summary'],
                           $f['last_prompt'], $f['stage'], $f['meta_json'], $prog, $turns ?? 1,
                           to_kst($in['started_at'] ?? null) ?? $at, $at]);
            $id = (int)$db->lastInsertId();
        }
        activity_event($id, (int)$ws['id'], (int)$tool['id'], $at);
        $next = clean($in['next_action'] ?? null, 300);
        if ($projId && $next) {
            project_set_next((int)$projId, $next);
        }
        if (!empty($in['ended'])) {
            $db->prepare('UPDATE activities SET ended_at = ? WHERE id = ?')->execute([$at, $id]);
        }
        ws_touch((int)$ws['id']);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
    sync_log($source . ':' . $ws['code'], true, 1, $tool['code']);
    return ['ok' => true, 'id' => $id, 'project_id' => $projId];
}

/** HANDOFF.md에서 "다음 할 일" 한 줄 찾기 (한국어 "다음 할 일", 영어 "Next step(s)" / "Next action") */
function handoff_next(string $md): ?string
{
    $label = '(?:다음\s*할\s*일|next\s*(?:steps?|actions?))';
    // 1) "다음 할 일: ..." / "Next step: ..." 형식
    if (preg_match('/^\s*(?:[-*]\s*)?(?:\*\*)?' . $label . '(?:\*\*)?\s*[:：]\s*(?:\*\*)?\s*(.+)$/mui', $md, $m)) {
        return clean(trim($m[1], " *"), 300);
    }
    // 2) "## 다음 할 일" / "## Next steps" 제목 아래 첫 항목
    if (preg_match('/^#{1,4}\s*' . $label . '.*\R+((?:\s*\R)*)\s*(?:[-*]|\d+\.)?\s*(?:\[[ x]\]\s*)?(.+)$/mui', $md, $m)) {
        return clean(trim($m[2]), 300);
    }
    return null;
}

/** HANDOFF.md 수신. 필수: cwd 또는 project(slug) / content */
function ingest_handoff(array $token, array $in): array
{
    $db = udeck_db();
    $ws = ingest_ws($token, $in);
    $content = clean($in['content'] ?? null, 20000);
    if (!$content) {
        json_fail('content가 필요합니다.');
    }
    $projId = null;
    if (!empty($in['project'])) {
        $st = $db->prepare('SELECT id FROM projects WHERE slug = ?');
        $st->execute([$in['project']]);
        $projId = $st->fetchColumn() ?: null;
    }
    $cwd = clean($in['cwd'] ?? null, 500);
    $projId = $projId ?: project_match_cwd($cwd);
    if (!$projId) {
        sync_log('handoff:' . $ws['code'], false, 0, '프로젝트 없음: ' . ($cwd ?? ''));
        return ['ok' => false, 'error' => t('이 폴더와 연결된 프로젝트가 없습니다. 보드에서 프로젝트의 작업 폴더를 지정하세요.')];
    }
    $at   = to_kst($in['at'] ?? null) ?? udeck_now();
    $next = handoff_next($content);
    $db->prepare('UPDATE projects SET handoff_md = ?, handoff_updated_at = ?,
            next_action = COALESCE(?, next_action), updated_at = datetime(\'now\',\'+9 hours\') WHERE id = ?')
        ->execute([$content, $at, $next, $projId]);
    ws_touch((int)$ws['id']);
    sync_log('handoff:' . $ws['code'], true, 1, 'project ' . $projId);
    return ['ok' => true, 'project_id' => (int)$projId, 'next_action' => $next];
}

/**
 * 구글 데이터 수신 (Apps Script). 보낸 종류(events, tasks, mails)는 보낸 계정(account) 몫만 통째로 교체한다.
 * 그래서 메일은 A 계정, 캘린더는 B 계정처럼 계정마다 스크립트를 따로 돌릴 수 있다.
 * events: [{id, calendar, title, detail, start, end, all_day, link}]
 * tasks:  [{id, list, title, notes, due, status, link}]
 * mails:  [{id, label, from, subject, date, status, link}], unread: 숫자
 */
function ingest_google(array $in): array
{
    $db = udeck_db();
    $account = clean($in['account'] ?? null, 120) ?? '';
    $ins = $db->prepare('INSERT OR REPLACE INTO google_items
        (kind, ext_id, container, title, detail, start_at, end_at, all_day, due_at, status, link, account, sort_key)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $count = 0;
    $db->beginTransaction();
    try {
        $map = [
            'event' => $in['events'] ?? null,
            'task'  => $in['tasks'] ?? null,
            'mail'  => $in['mails'] ?? null,
        ];
        foreach ($map as $kind => $list) {
            if (!is_array($list)) {
                continue;
            }
            // 계정 표시가 없던 이전 버전 데이터도 함께 정리
            $db->prepare("DELETE FROM google_items WHERE kind = ? AND (account = ? OR account = '')")->execute([$kind, $account]);
            foreach (array_slice($list, 0, 500) as $x) {
                if (!is_array($x) || empty($x['id'])) {
                    continue;
                }
                if ($kind === 'event') {
                    $row = [$kind, (string)$x['id'], clean($x['calendar'] ?? null, 100),
                        clean($x['title'] ?? '(제목 없음)', 300) ?? '(제목 없음)', clean($x['detail'] ?? null, 500),
                        to_kst($x['start'] ?? null), to_kst($x['end'] ?? null), !empty($x['all_day']) ? 1 : 0,
                        null, null, clean($x['link'] ?? null, 500)];
                } elseif ($kind === 'task') {
                    $row = [$kind, (string)$x['id'], clean($x['list'] ?? null, 100),
                        clean($x['title'] ?? '(제목 없음)', 300) ?? '(제목 없음)', clean($x['notes'] ?? null, 500),
                        null, null, 0, to_date($x['due'] ?? null), clean($x['status'] ?? null, 20),
                        clean($x['link'] ?? null, 500)];
                } else {
                    $row = [$kind, (string)$x['id'], clean($x['label'] ?? null, 100),
                        clean($x['subject'] ?? '(제목 없음)', 300) ?? '(제목 없음)', clean($x['from'] ?? null, 200),
                        to_kst($x['date'] ?? null), null, 0, null, clean($x['status'] ?? null, 20),
                        clean($x['link'] ?? null, 500)];
                }
                $row[] = $account;
                $row[] = $kind === 'task' ? clean($x['order'] ?? null, 160) : null;
                $ins->execute($row);
                $count++;
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
    if (isset($in['unread'])) {
        setting_set('gmail_unread:' . $account, (string)(int)$in['unread']);
    }
    setting_set('google_synced_at', udeck_now());
    setting_set('google_synced_at:' . $account, udeck_now());
    $kinds = implode(',', array_keys(array_filter(['events' => isset($in['events']), 'tasks' => isset($in['tasks']), 'mails' => isset($in['mails'])])));
    sync_log('google', true, $count, trim($account . ' ' . $kinds));
    return ['ok' => true, 'items' => $count];
}
