# UDeck 설치 및 설정 매뉴얼

UDeck 1.2.1 기준이다. 영문 문서는 [INSTALL.md](INSTALL.md), [GOOGLE_SYNC.md](GOOGLE_SYNC.md), [PC_AGENT.md](PC_AGENT.md)에 나뉘어 있고, 이 문서는 그 내용을 한국어로 한곳에 모은 것이다. 설치를 마친 뒤의 사용법은 UDeck 화면 오른쪽 위 **도움말**에 있다.

## 차례

1. [구성 한눈에 보기](#1-구성-한눈에-보기)
2. [준비물](#2-준비물)
3. [서버 점검](#3-서버-점검)
4. [파일 올리기](#4-파일-올리기)
5. [config.php 만들기](#5-configphp-만들기)
6. [첫 실행과 비밀번호 설정](#6-첫-실행과-비밀번호-설정)
7. [기본 설정](#7-기본-설정)
8. [구글 연동 (Apps Script)](#8-구글-연동-apps-script)
9. [PC 자동 기록 (install.cmd)](#9-pc-자동-기록-installcmd)
10. [HANDOFF.md](#10-handoffmd)
11. [UDeck 업데이트](#11-udeck-업데이트)
12. [문제 해결](#12-문제-해결)

---

## 1. 구성 한눈에 보기

| 부분 | 하는 일 | 어디서 |
|---|---|---|
| 웹 앱 (`udeck/`) | 보드 화면, 설정, 도움말, 기록 수신(`ingest.php`) | 내 웹 호스팅 |
| 비공개 폴더 (`udeck_private/`) | 설정 파일, 프로그램 코드, 데이터(SQLite 파일 하나) | 웹 루트 바깥 |
| Google Apps Script (`gas/`) | 캘린더·할 일·메일 요약을 10분마다 보냄 | 내 구글 계정 |
| PC 에이전트 (`agent/`) | Claude Code·Codex 작업과 HANDOFF.md를 기록 | 작업하는 각 Windows PC |

모든 기록은 `ingest.php` 한 곳으로 들어가 `udeck_private/data/udeck.sqlite` 파일 하나에 저장된다.

## 2. 준비물

| 항목 | 조건 |
|---|---|
| 서버 | Apache(`.htaccess`, mod_rewrite), PHP 8.0 이상과 `pdo_sqlite`, SQLite 3.26 이상, HTTPS 권장. 일반 공유 호스팅이면 된다. |
| 구글 | Apps Script를 만들 구글 계정 (일정·할 일·메일이 있는 계정) |
| PC | Windows 10/11, Python 3.8 이상 (추가 패키지 없음) |
| 파일 | [GitHub 릴리스](https://github.com/hypersh/UDeck/releases/latest)의 `UDeck-v*.zip` |

## 3. 서버 점검

1. 압축을 푼 폴더의 `tools/server_check.php`를 웹 루트에 올린다.
2. 브라우저에서 `https://내도메인/server_check.php`를 연다.
3. **pdo_sqlite driver**와 **SQLite create/write/read**가 통과인지 확인한다. **Writable outside web root**도 통과하면 비공개 폴더를 웹 루트 밖에 둘 수 있다.
4. 확인이 끝나면 서버에서 `server_check.php`를 지운다. 서버 경로와 설정이 보이는 파일이다.

## 4. 파일 올리기

UDeck은 공개 폴더 기준으로 `../../udeck_private`에서 비공개 폴더를 찾는다. 두 폴더를 다음처럼 둔다.

```
/home/계정/
├─ www/                      웹 루트 (호스팅에 따라 public_html, htdocs 등)
│  └─ udeck/                 udeck/ 폴더 내용
│     ├─ .htaccess
│     ├─ index.php, setup.php, login.php, settings.php, help.php …
│     └─ assets/css/udeck.css, assets/js/board.js
└─ udeck_private/            udeck_private/ 폴더 내용
   ├─ .htaccess
   ├─ config.php             5장에서 만든다
   ├─ config.sample.php
   ├─ schema.sql
   ├─ lang/en.php
   ├─ gas/UDeck_Sync.gs      도움말 화면에서 복사용으로 보여 준다
   └─ lib/*.php
```

- `.htaccess` 두 개를 반드시 올린다. FTP 프로그램은 점(.)으로 시작하는 파일을 숨기는 경우가 많다(FileZilla: 서버 → 숨김 파일 강제 표시).
- `udeck_private/data/`와 `udeck_private/sessions/`는 첫 접속 때 자동으로 생긴다. PHP가 `udeck_private/`에 쓸 수 있어야 한다.
- 웹 루트 밖에 폴더를 둘 수 없는 호스팅이라도 `udeck_private/.htaccess`가 웹 접근을 막는다. 다만 웹 루트 밖에 두는 것을 강하게 권한다.

## 5. config.php 만들기

`udeck_private/config.sample.php`를 복사해 `udeck_private/config.php`로 저장하고 값을 고친다.

| 항목 | 설명 |
|---|---|
| `setup_key` | 필수. 비밀번호를 처음 정할 때 한 번 입력하는 긴 임의 문자열이다. 비어 있거나 `CHANGE-ME`이면 설정이 거부된다. 다른 사람이 갓 올린 UDeck을 먼저 차지하지 못하게 막는다. |
| `language` | 설정에서 언어를 고르기 전까지 쓸 화면 언어. `ko` 또는 `en` |
| `remember_seconds` | "로그인 상태 유지" 기간. 기본 14일 |
| `login_max_fail`, `login_window_min` | 로그인 잠금. 기본 15분 안에 5회 실패 |
| `allowed_ips` | 선택. 접속을 허용할 IP 대역(CIDR). 예: Tailscale `['100.64.0.0/10']` |

`config.php`는 서버에만 두고 공개 저장소나 다른 사람에게 공유하지 않는다.

## 6. 첫 실행과 비밀번호 설정

1. `https://내도메인/udeck/`에 접속하면 비밀번호 설정 화면이 열린다.
2. `setup_key`와 12자 이상의 비밀번호를 입력한다. 화면 아래 서버 점검에 실패 항목이 없어야 한다.
3. 로그인되고 보드가 열린다.
4. 위쪽 선택 상자에서 지금 이 브라우저가 있는 PC를 고른다. PC와 브라우저마다 한 번씩 한다.

## 7. 기본 설정

오른쪽 위 **설정**에서 다음을 정한다.

- **작업공간(PC 또는 담당자)**: 기본으로 들어 있는 집 PC, 사무실 PC, 노트북의 이름과 색을 바꾸고 필요한 만큼 추가한다. 각 PC의 Windows 컴퓨터 이름도 적어 둔다. 코드는 자동 기록이 PC를 구분하는 이름이다(영문 소문자, 숫자, `_`, `-`).
- **도구**: 쓰는 AI 도구를 추가한다. "아이콘 이름"은 작업공간 박스와 프로젝트 목록에 보이는 짧은 이름이다(12자 이내). 비워 두면 이름에 맞춰 Codex, GPT, Claude, Claude C, Gemini, Antigravity 중 하나로 정해진다.
- **언어**: 한국어 또는 English.
- **연결 키**: 8장(구글)과 9장(PC)에서 만든다.

## 8. 구글 연동 (Apps Script)

Apps Script가 내 구글 계정 권한으로 10분마다 일정·할 일·메일 요약을 읽어 UDeck으로 보낸다. UDeck 서버에는 구글 비밀번호나 OAuth 토큰을 저장하지 않는다.

| 항목 | 보내는 내용 |
|---|---|
| 일정 | 구글 캘린더에서 체크(표시)된 캘린더의 이번 달 앞뒤 7일까지. 거절한 일정은 제외 |
| 할 일 | 모든 목록의 완료되지 않은 항목. 목록 이름, 마감일, Google Tasks의 "내 순서" |
| 메일 | 받은편지함의 읽지 않았거나 별표한 최근 7일 스레드 30개까지의 제목, 보낸 사람, 날짜. 본문은 보내지 않는다 |

### 8.1 처음 연결하기

1. UDeck **설정 → 연결 키**에서 이름 `Apps Script`, 용도 **구글 연동**으로 키를 만들고 **키 복사**를 누른다. 키는 만든 직후 한 번만 보인다.
2. [script.google.com](https://script.google.com)에 접속해 **새 프로젝트**를 만든다. 오른쪽 위 프로필 사진이 데이터를 가져올 계정인지 먼저 확인한다. 스크립트는 그 계정으로 실행된다.
3. 왼쪽 위 "제목 없는 프로젝트"를 눌러 이름을 `UDeck Sync`로 바꾼다.
4. 편집기의 기본 코드(`function myFunction() {}`)를 모두 지우고 `gas/UDeck_Sync.gs` 내용을 붙여 넣는다. 같은 코드가 UDeck 도움말 5장에 복사 버튼과 함께 있다.
5. 왼쪽 **서비스** 옆 **+** 를 누르고 **Google Tasks API**를 골라 추가한다(식별자 `Tasks` 그대로).
6. 왼쪽 톱니바퀴 **프로젝트 설정**에서
   - 시간대를 **(GMT+09:00) 서울**로 정한다.
   - 맨 아래 **스크립트 속성 → 스크립트 속성 추가**로 두 값을 넣고 저장한다.

   | 속성 | 값 |
   |---|---|
   | `UDECK_URL` | `https://내도메인/udeck/ingest.php` |
   | `UDECK_TOKEN` | 1번에서 복사한 키 (`udk_…`) |

7. 편집기로 돌아와 `Ctrl + S`로 저장한다. 위쪽 함수 선택 상자에서 `testSync`를 고르고 **▷ 실행**을 누른다.
8. 권한 승인 창에서 계정을 고른다. "Google에서 확인하지 않은 앱"이 나오면 **고급 → UDeck Sync(으)로 이동(안전하지 않음)** → **허용**을 누른다. 직접 만든 스크립트라서 나오는 안내다.
9. 아래 실행 로그에 `"ok":true`와 계정, 일정·할 일·메일 건수가 나오면 성공이다. 보드를 새로고침하면 목록판에 나타난다.
10. 함수 선택 상자에서 `installTrigger`를 실행하면 10분마다 자동 전송이 시작된다. 멈추려면 `removeTrigger`를 실행한다.

### 8.2 계정을 나눠 쓰는 경우

Apps Script는 만든 계정의 데이터만 읽는다. 메일, 캘린더, 할 일이 서로 다른 계정에 있으면 **계정마다 프로젝트를 하나씩** 만들고(8.1을 계정마다 반복) 코드 맨 위 `send`만 다르게 한다. 연결 키는 하나를 같이 써도 된다. UDeck은 계정별로 나눠 저장하므로 서로 덮어쓰지 않는다.

```javascript
// 개인 Gmail 계정: 메일만
send: { events: false, tasks: false, mails: true },

// 학교·회사 계정: 일정과 할 일
send: { events: true, tasks: true, mails: false },
```

학교·회사 Workspace 계정은 관리자 정책으로 실행이 막힐 수 있다. 그때는 그 캘린더를 개인 계정에 공유하거나 메일 전달을 설정하고, 개인 계정의 스크립트로 가져온다.

### 8.3 코드를 새 버전으로 바꾸기

UDeck을 업데이트하면서 Apps Script도 바뀐 경우([CHANGELOG](../CHANGELOG.md)에 표시) 데이터를 보내는 계정마다 다음을 한다.

1. 그 구글 계정으로 [script.google.com](https://script.google.com)에 접속해 `UDeck Sync` 프로젝트를 연다.
2. 왼쪽 파일 목록에서 `코드.gs`(`Code.gs`)를 열고, 맨 위 `CONFIG`의 `send` 값을 메모해 둔다.
3. 편집기 안을 누르고 `Ctrl + A` → `Delete`로 지운 뒤 새 `gas/UDeck_Sync.gs` 내용을 붙여 넣는다.
4. `send`를 메모한 값으로 다시 고치고 `Ctrl + S`로 저장한다. 두 번째 줄의 버전 표시가 바뀌었는지 확인한다.
5. `testSync`를 실행해 `"ok":true`를 확인한다. 권한 창이 다시 뜨면 허용한다.

스크립트 속성(`UDECK_URL`, `UDECK_TOKEN`)과 10분 트리거는 코드와 따로 저장되므로 다시 만들 필요가 없다.

### 8.4 주요 설정 (코드 맨 위 `CONFIG`)

| 항목 | 기본값 | 설명 |
|---|---|---|
| `send` | 모두 `true` | 이 계정이 보낼 항목 |
| `eventPadDays` | `7` | 이번 달 앞뒤로 더 보낼 날 수 |
| `mailQuery` | `in:inbox (is:unread OR is:starred) newer_than:7d` | Gmail 검색창과 같은 문법. 별표 메일만: `in:inbox is:starred` |
| `mailMax` | `30` | 보낼 메일 스레드 수 |
| `taskMax` | `100` | 목록당 할 일 수 |
| `excludeCalendars` | `[]` | 제외할 캘린더 이름. 예: `['대한민국의 휴일']` |
| `skipDeclined` | `true` | 거절한 일정 제외 |

수집 결과가 0건이면 `diagnoseUDeck`을 실행한다. 실행 계정, 캘린더별 표시 여부와 일정 수, 할 일 목록별 개수, 메일 개수가 로그에 나온다.

## 9. PC 자동 기록 (install.cmd)

`agent/` 폴더가 각 PC의 작업을 자동으로 기록한다. 상주 프로그램이 아니다. Claude Code는 응답이 끝날 때마다 잠깐 실행되는 `Stop` 훅으로, 나머지는 Windows 작업 스케줄러가 15분마다 몇 초씩 실행하는 에이전트로 기록한다.

| 출처 | 시점 | 보드 표시 |
|---|---|---|
| Claude Code | 응답이 끝날 때마다 | 그 PC 행의 프로젝트 박스 (Claude C 아이콘) |
| Codex (`%USERPROFILE%\.codex\sessions`) | 15분마다, 바뀐 세션만 | 그 PC 행의 프로젝트 박스 |
| HANDOFF.md | 15분마다, 내용이 바뀌었을 때 | 프로젝트의 인수인계 메모와 다음 할 일 |
| 접속 신호 | 15분마다 | 작업공간의 "접속 중" |

보내는 것: PC, 도구, 세션 ID, 작업 폴더 경로, 마지막 요청 앞 500자, 마지막 응답 앞 300자, 시각, HANDOFF.md 내용. 보내지 않는 것: 소스 코드, 데이터 파일, 전체 대화.

### 9.1 설치 (PC마다 한 번)

1. UDeck **설정 → 연결 키**에서 용도 **PC 작업 기록**을 고르고, PC 칸에서 설치할 PC를 골라 키를 만든 뒤 **키 복사**를 누른다. PC마다 키를 따로 만든다.
2. `agent` 폴더 전체를 그 PC에 둔다. 동기화 폴더에 두면 여러 PC가 같은 폴더를 쓸 수 있다.
3. `install.cmd`를 더블클릭한다.
4. UDeck 주소를 물으면 `https://내도메인/udeck/`처럼 입력한다. 키는 클립보드에서 읽는다.
5. 설치 프로그램이 연결 시험, Claude Code 훅 등록(`%USERPROFILE%\.claude\settings.json`, 기존 파일은 백업하고 다른 설정은 유지), 작업 스케줄러 **UDeck Agent** 등록(15분마다, 창 없음), 시험 기록 전송을 차례로 한다.
6. 보드에서 그 PC 행에 시험 기록이 보이면 성공이다. 최근 기록에서 고치기 → 삭제로 지운다.
7. 열려 있던 Claude Code 창은 다시 시작해야 훅이 적용된다.

### 9.2 관리 명령

| 파일·명령 | 용도 |
|---|---|
| `status.cmd` | 연결, 작업 스케줄러, 훅 상태와 최근 로그 |
| `uninstall.cmd` | 작업 스케줄러와 훅 제거 |
| `python agent\udeck_setup.py diagnose` | 최근 7일 동안 보낼 내용 미리 보기 (전송 안 함) |
| `python agent\udeck_setup.py watch D:\Projects` | HANDOFF.md를 찾을 폴더 추가 |

PC별 설정과 상태는 `%LOCALAPPDATA%\UDeck\`(`config.json`, `state.json`, `queue.jsonl`, `udeck.log`)에 저장된다. Microsoft Store판 Python은 이 위치를 `%LOCALAPPDATA%\Packages\PythonSoftwareFoundation.Python…\LocalCache\Local\UDeck\`로 바꿀 수 있다.

## 10. HANDOFF.md

프로젝트 폴더에 두는 인수인계 메모다. 내용이 바뀌면 UDeck이 읽어 프로젝트에 반영하므로, 다른 PC나 다른 AI 도구로 옮겨도 맥락이 이어진다.

- **다음 할 일**: `다음 할 일: …` 줄, 또는 `## 다음 할 일` 제목 아래 첫 항목. 영어 `Next step: …`, `## Next steps`도 알아본다.
- **인수인계 메모**: 파일 전체. 프로젝트 고치기 창에서 볼 수 있다.

```markdown
# HANDOFF
## 현재 상태
배경 절 2차 수정 완료.

## 다음 할 일
- 예산표를 한 쪽으로 줄이기

## 메모
- 마지막 작업: 노트북, Claude Code
```

조건은 두 가지다. 파일이 에이전트의 감시 폴더 안에 있어야 하고, 경로의 폴더 이름 하나가 UDeck 프로젝트의 **작업 폴더 이름**과 같아야 한다(가장 안쪽 폴더가 우선). AI 도구가 알아서 갱신하게 하려면 `CLAUDE.md`나 `AGENTS.md`에 다음을 적어 둔다.

```markdown
## 작업 인수인계
- 세션을 시작하면 HANDOFF.md를 먼저 읽는다.
- 세션을 마치기 전에 HANDOFF.md의 "현재 상태"와 "다음 할 일"을 갱신한다.
```

## 11. UDeck 업데이트

1. 서버의 `udeck_private/data/udeck.sqlite`를 내려받아 백업한다.
2. 새 `udeck/`와 `udeck_private/` 파일을 덮어쓴다. `udeck_private/config.php`, `data/`, `sessions/`는 덮어쓰지 않는다.
3. 보드를 열면 데이터베이스 변경이 자동으로 적용된다. 화면이 그대로면 `Ctrl + F5`로 강력 새로고침한다.
4. 변경 이력에 Apps Script나 PC 에이전트 변경이 있으면 8.3장이나 9.1장대로 함께 바꾼다.
5. 도움말 화면 맨 아래 버전 표시로 반영을 확인한다.

## 12. 문제 해결

| 증상 | 확인할 것 |
|---|---|
| "UDeck 비공개 폴더(udeck_private)를 찾을 수 없습니다" | `udeck_private`가 웹 루트 폴더와 같은 위치에 있는가 |
| 화면에 스타일이 없음 | `assets/`가 `udeck/` 안에 있는가 |
| "설치 키가 설정되지 않았습니다" | `config.php`를 만들고 `setup_key`를 정했는가 |
| "서버에서 처리하지 못했습니다" | `udeck_private/data/php_error.log` |
| 자꾸 로그아웃됨 | "로그인 상태 유지" 체크, `udeck_private/sessions/`가 있고 쓰기 가능한가 |
| 비밀번호를 잊음 | `udeck.sqlite`를 백업하고, SQLite 도구로 `settings` 표의 `password_hash` 행을 지운 뒤 설정 화면에서 새로 정한다 |
| 일정·할 일·메일이 안 나옴 | Apps Script 실행 계정 확인, `diagnoseUDeck` 실행 |
| 할 일 순서가 구글과 다름 | Apps Script가 v1.1 이상인가(8.3장). Google Tasks 화면이 "날짜순"이면 "내 순서"로 보면 같다 |
| Claude Code 작업이 기록되지 않음 | Claude Code를 다시 시작했는가, `status.cmd`에서 훅이 설치로 나오는가 |
| PC가 "접속 중"이 되지 않음 | 작업 스케줄러 → UDeck Agent → 마지막 실행 결과가 0x0인가 |
| HTTP 401 | 키가 틀렸거나 폐기됨. 새 키를 만들고 `install.cmd`를 다시 실행 |
| HTTP 403 | 키 용도가 PC 작업 기록이 아니라 구글 연동임 |
| HANDOFF.md가 반영되지 않음 | 감시 폴더 안에 있는가, 폴더 이름이 프로젝트의 작업 폴더 이름과 같은가 |
