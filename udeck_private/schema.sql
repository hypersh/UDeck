-- =====================================================================
-- UDeck DB 스키마 v1
-- 대상: SQLite 3.26.0 이상 (공유 호스팅 호환)
--   * 3.26에서 쓸 수 없는 기능은 사용하지 않는다:
--     STRICT 테이블(3.37), RETURNING(3.35), 생성 컬럼(3.31), ->> 연산자(3.38)
--   * UPSERT(ON CONFLICT DO UPDATE, 3.24)와 JSON1 함수는 사용 가능하다.
-- 시간 규칙: 모든 시각은 KST 텍스트 'YYYY-MM-DD HH:MM:SS'로 저장한다.
--   SQLite의 'now'는 항상 UTC이므로 기본값은 datetime('now','+9 hours')를 쓴다.
--   (한국은 서머타임이 없어 +9시간 고정이 안전하다.)
-- =====================================================================

PRAGMA foreign_keys = ON;

-- ---------------------------------------------------------------------
-- 1. 작업공간: PC 3대 (집PC, 사무실PC, 노트북)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS workspaces (
    id           INTEGER PRIMARY KEY,
    code         TEXT    NOT NULL UNIQUE,          -- 'home' | 'office' | 'laptop'
    name         TEXT    NOT NULL,                 -- 화면 표시명: '집PC'
    device_name  TEXT,                             -- Windows 컴퓨터 이름
    role_note    TEXT,                             -- 'GPU 작업, 딥러닝' 등 용도 메모
    sort_order   INTEGER NOT NULL DEFAULT 0,
    last_seen_at TEXT,                             -- 에이전트가 마지막으로 보고한 시각
    created_at   TEXT    NOT NULL DEFAULT (datetime('now','+9 hours'))
);

-- ---------------------------------------------------------------------
-- 2. 도구: ChatGPT 앱, Claude Code 앱, AI 웹, (추후) Antigravity 등
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tools (
    id          INTEGER PRIMARY KEY,
    code        TEXT    NOT NULL UNIQUE,           -- 'chatgpt_app' | 'claude_code' | ...
    name        TEXT    NOT NULL,
    kind        TEXT    NOT NULL DEFAULT 'desktop' -- 'desktop'(로컬 저장) | 'web'(서버 저장)
                CHECK (kind IN ('desktop','web','other')),
    is_active   INTEGER NOT NULL DEFAULT 1,
    sort_order  INTEGER NOT NULL DEFAULT 0
);

-- ---------------------------------------------------------------------
-- 3. 프로젝트
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
    id                    INTEGER PRIMARY KEY,
    slug                  TEXT    NOT NULL UNIQUE, -- 폴더명과 맞추면 자동 매칭이 쉬움
    name                  TEXT    NOT NULL,
    category              TEXT,                    -- '연구' | '개발' | '교육' | '행정' 등
    status                TEXT    NOT NULL DEFAULT 'active'
                          CHECK (status IN ('active','paused','done','archived')),
    phase                 TEXT,                    -- '초안' | '후반 작업' | '검토' 등 자유 기입
    priority              INTEGER NOT NULL DEFAULT 2,  -- 1 높음, 2 보통, 3 낮음
    due_date              TEXT,                    -- 'YYYY-MM-DD'
    folder_path           TEXT,                    -- 작업 폴더 이름(예: Grant_2027). 자동 기록의 cwd와 비교해 프로젝트를 찾는다
    next_action           TEXT,                    -- 다음에 할 일 한 줄
    handoff_md            TEXT,                    -- HANDOFF.md 최신 내용
    handoff_updated_at    TEXT,
    color                 TEXT,                    -- 보드 표시 색 '#4f7cff'
    sort_order            INTEGER NOT NULL DEFAULT 0,
    created_at            TEXT    NOT NULL DEFAULT (datetime('now','+9 hours')),
    updated_at            TEXT    NOT NULL DEFAULT (datetime('now','+9 hours'))
);
CREATE INDEX IF NOT EXISTS idx_projects_status ON projects(status, priority, sort_order);

-- ---------------------------------------------------------------------
-- 4. 작업 기록: 어느 PC, 어느 도구에서, 어떤 프로젝트를, 언제, 무엇을 했는가
--    source: manual(보드 입력) | hook(Claude Code Stop hook) | scan(에이전트 스캔)
--            | handoff(HANDOFF.md 변경 감지)
--    같은 세션은 (workspace, tool, session_key)로 UPSERT하여 1행만 유지한다.
--    현재 PC·도구와 작업 흐름은 이 테이블에서 계산하므로 projects에 따로 저장하지 않는다.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activities (
    id             INTEGER PRIMARY KEY,
    workspace_id   INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    tool_id        INTEGER NOT NULL REFERENCES tools(id)      ON DELETE CASCADE,
    project_id     INTEGER          REFERENCES projects(id)   ON DELETE SET NULL,
    session_key    TEXT,                           -- 도구의 세션 ID (수동 입력이면 NULL)
    source         TEXT    NOT NULL DEFAULT 'manual'
                   CHECK (source IN ('manual','hook','scan','handoff')),
    cwd            TEXT,                           -- 작업 폴더 경로
    stage          TEXT,                           -- 단계 이름: 초안, 수정, 검토, 학습 등
    title          TEXT,                           -- 작업 제목 한 줄
    summary        TEXT,                           -- 진행 상황 요약
    last_prompt    TEXT,                           -- 마지막 요청(앞부분만 저장)
    turn_count     INTEGER NOT NULL DEFAULT 0,
    started_at     TEXT,
    last_active_at TEXT    NOT NULL DEFAULT (datetime('now','+9 hours')),
    ended_at       TEXT,
    meta_json      TEXT,                           -- 기타 원본 정보(JSON 문자열)
    created_at     TEXT    NOT NULL DEFAULT (datetime('now','+9 hours'))
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_activities_session
    ON activities(workspace_id, tool_id, session_key) WHERE session_key IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_activities_recent  ON activities(last_active_at DESC);
CREATE INDEX IF NOT EXISTS idx_activities_matrix  ON activities(workspace_id, tool_id, last_active_at DESC);
CREATE INDEX IF NOT EXISTS idx_activities_project ON activities(project_id, last_active_at DESC);

-- 작업 신호: 응답·기록이 있을 때마다 1행 추가. 매트릭스의 7일 활동 막대에 쓴다.
CREATE TABLE IF NOT EXISTS activity_events (
    id            INTEGER PRIMARY KEY,
    activity_id   INTEGER NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    workspace_id  INTEGER NOT NULL,
    tool_id       INTEGER NOT NULL,
    at            TEXT    NOT NULL DEFAULT (datetime('now','+9 hours'))
);
CREATE INDEX IF NOT EXISTS idx_events_cell ON activity_events(workspace_id, tool_id, at);

-- ---------------------------------------------------------------------
-- 5. 구글 데이터: Apps Script가 주기적으로 보내는 일정·할 일·메일 요약
--    kind: event(캘린더) | task(Google Tasks) | mail(Gmail)
--    메일은 본문을 저장하지 않고 제목·발신자·링크만 저장한다.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS google_items (
    id          INTEGER PRIMARY KEY,
    kind        TEXT    NOT NULL CHECK (kind IN ('event','task','mail')),
    ext_id      TEXT    NOT NULL,                  -- 구글 쪽 고유 ID
    container   TEXT,                              -- 캘린더 이름 / 태스크 목록 이름 / 메일 라벨
    title       TEXT    NOT NULL,
    detail      TEXT,                              -- 장소, 메모, 발신자 등
    start_at    TEXT,                              -- 일정 시작
    end_at      TEXT,                              -- 일정 종료
    all_day     INTEGER NOT NULL DEFAULT 0,
    due_at      TEXT,                              -- 할 일 마감
    status      TEXT,                              -- task: needsAction/completed, mail: unread/starred
    link        TEXT,                              -- 원본 열기 URL
    synced_at   TEXT    NOT NULL DEFAULT (datetime('now','+9 hours')),
    UNIQUE (kind, ext_id)
);
CREATE INDEX IF NOT EXISTS idx_google_event ON google_items(kind, start_at);
CREATE INDEX IF NOT EXISTS idx_google_due   ON google_items(kind, due_at);

-- ---------------------------------------------------------------------
-- 6. 메모: 보드에서 바로 쓰는 빠른 메모 (Keep 대체)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notes (
    id          INTEGER PRIMARY KEY,
    project_id  INTEGER REFERENCES projects(id) ON DELETE SET NULL,
    body        TEXT    NOT NULL,
    pinned      INTEGER NOT NULL DEFAULT 0,
    done        INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT    NOT NULL DEFAULT (datetime('now','+9 hours')),
    updated_at  TEXT    NOT NULL DEFAULT (datetime('now','+9 hours'))
);
CREATE INDEX IF NOT EXISTS idx_notes_list ON notes(done, pinned DESC, updated_at DESC);

-- ---------------------------------------------------------------------
-- 7. API 토큰: PC 에이전트·Apps Script가 데이터를 보낼 때 쓰는 키
--    원문 토큰은 발급 시 한 번만 보여주고, DB에는 SHA-256 해시만 저장한다.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS api_tokens (
    id            INTEGER PRIMARY KEY,
    label         TEXT    NOT NULL,                -- '집PC 에이전트', 'Apps Script'
    token_hash    TEXT    NOT NULL UNIQUE,
    scope         TEXT    NOT NULL DEFAULT 'ingest'
                  CHECK (scope IN ('ingest','google','read')),
    workspace_id  INTEGER REFERENCES workspaces(id) ON DELETE CASCADE,
    revoked       INTEGER NOT NULL DEFAULT 0,
    last_used_at  TEXT,
    created_at    TEXT    NOT NULL DEFAULT (datetime('now','+9 hours'))
);

-- ---------------------------------------------------------------------
-- 8. 보안·운영 기록
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (      -- 로그인 무차별 대입 방지
    id          INTEGER PRIMARY KEY,
    ip          TEXT    NOT NULL,
    success     INTEGER NOT NULL DEFAULT 0,
    at          TEXT    NOT NULL DEFAULT (datetime('now','+9 hours'))
);
CREATE INDEX IF NOT EXISTS idx_login_ip ON login_attempts(ip, at);

CREATE TABLE IF NOT EXISTS sync_log (            -- 외부 수신 이력 (문제 추적용)
    id          INTEGER PRIMARY KEY,
    source      TEXT    NOT NULL,                  -- 'gas' | 'agent:home' | 'hook:laptop' ...
    ok          INTEGER NOT NULL DEFAULT 1,
    items       INTEGER NOT NULL DEFAULT 0,
    message     TEXT,
    at          TEXT    NOT NULL DEFAULT (datetime('now','+9 hours'))
);
CREATE INDEX IF NOT EXISTS idx_sync_log_at ON sync_log(at DESC);

CREATE TABLE IF NOT EXISTS settings (            -- 키-값 설정 (비밀번호 해시도 여기 저장)
    key         TEXT PRIMARY KEY,
    value       TEXT
);

-- ---------------------------------------------------------------------
-- 초기 데이터
-- ---------------------------------------------------------------------
INSERT OR IGNORE INTO workspaces (code, name, sort_order) VALUES
    ('home',   'Home PC',   1),
    ('office', 'Office PC', 2),
    ('laptop', 'Laptop',    3);

INSERT OR IGNORE INTO tools (code, name, kind, is_active, sort_order) VALUES
    ('chatgpt_app', 'ChatGPT',     'desktop', 1, 1),
    ('claude_code', 'Claude Code', 'desktop', 1, 2),
    ('antigravity', 'Antigravity', 'desktop', 0, 3),
    ('ai_web',      'AI Web',      'web',     1, 9);

INSERT OR IGNORE INTO settings (key, value) VALUES
    ('schema_version', '1'),
    ('board_title',    'UDeck');
