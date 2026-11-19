/**
 * UDeck 구글 연동 스크립트 v1.1 (할 일을 Google Tasks의 '내 순서' 그대로 보냄)
 * Google Calendar, Google Tasks, Gmail의 최근 내용을 UDeck으로 보낸다.
 *
 * 준비
 *  1) 편집기 왼쪽 "서비스" + → "Google Tasks API" 추가 (식별자: Tasks)
 *  2) 프로젝트 설정 → 스크립트 속성에 다음 두 값을 추가
 *       UDECK_URL   = https://your-domain.com/udeck/ingest.php
 *       UDECK_TOKEN = udk_... (UDeck 설정 → 연결 키, 용도 "구글 연동")
 *  3) testSync 실행 → 권한 승인 → 실행 로그에서 결과 확인
 *  4) installTrigger 실행 → 10분마다 자동 전송 시작
 *
 * 계정을 나눠 쓰는 경우
 *  메일·캘린더·할 일이 서로 다른 구글 계정에 있으면, 계정마다 이 스크립트를 따로 만들고
 *  아래 CONFIG.send에서 그 계정이 보낼 것만 true로 둔다. UDeck은 계정별로 구분해 저장한다.
 */

// ===== 설정 =====
const CONFIG = {
  // 이 계정에서 보낼 항목. 계정을 나눠 쓰면 해당 항목만 true
  send: { events: true, tasks: true, mails: true },
  // 일정: 이번 달 1일 앞뒤로 며칠씩 더 보낼지 (이번 주가 달을 걸칠 때를 위해 7일)
  eventPadDays: 7,
  // 메일: Gmail 검색어. 받은편지함의 읽지 않은 메일과 별표 메일 중 최근 7일
  mailQuery: 'in:inbox (is:unread OR is:starred) newer_than:7d',
  mailMax: 30,
  // 할 일: 목록당 최대 개수
  taskMax: 100,
  // 일정에서 제외할 캘린더 이름 (예: ['대한민국의 휴일'])
  excludeCalendars: [],
  // 거절한 일정 제외
  skipDeclined: true,
};

/** 트리거가 부르는 함수: 수집해서 UDeck으로 전송 */
function syncUDeck() {
  const payload = { type: 'google', account: account_() };
  if (CONFIG.send.events) payload.events = collectEvents_();
  if (CONFIG.send.tasks) payload.tasks = collectTasks_();
  if (CONFIG.send.mails) {
    payload.mails = collectMails_();
    payload.unread = GmailApp.getInboxUnreadCount();
  }
  return send_(payload);
}

/** 수동 시험: 수집 결과를 로그로 보여 주고 전송까지 한다 */
function testSync() {
  const t0 = Date.now();
  const res = syncUDeck();
  Logger.log('전송 결과: ' + JSON.stringify(res));
  Logger.log('걸린 시간: ' + ((Date.now() - t0) / 1000).toFixed(1) + '초');
}

/** 10분마다 자동 실행 등록 (기존 등록은 지우고 다시 만든다) */
function installTrigger() {
  removeTrigger();
  ScriptApp.newTrigger('syncUDeck').timeBased().everyMinutes(10).create();
  Logger.log('10분마다 syncUDeck을 실행하도록 등록했습니다.');
}

/** 자동 실행 해제 */
function removeTrigger() {
  ScriptApp.getProjectTriggers()
    .filter(t => t.getHandlerFunction() === 'syncUDeck')
    .forEach(t => ScriptApp.deleteTrigger(t));
}

// ===== 수집 =====

/** 일정 수집 범위: 이번 달 1일 - eventPadDays ~ 다음 달 1일 + eventPadDays */
function eventRange_() {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), 1 - CONFIG.eventPadDays);
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 1 + CONFIG.eventPadDays);
  return [start, end];
}

/** 일정: 표시 중인 캘린더의 이번 달(앞뒤 여유 포함) */
function collectEvents_() {
  const [start, end] = eventRange_();
  const out = [];
  CalendarApp.getAllCalendars().forEach(cal => {
    if (cal.isHidden() || !cal.isSelected()) return;
    if (CONFIG.excludeCalendars.indexOf(cal.getName()) >= 0) return;
    cal.getEvents(start, end).forEach(ev => {
      if (CONFIG.skipDeclined && ev.getMyStatus() === CalendarApp.GuestStatus.NO) return;
      const s = ev.getStartTime(), e = ev.getEndTime();
      const allDay = ev.isAllDayEvent();
      out.push({
        // 반복 일정은 같은 ID를 공유하므로 시작 시각을 붙여 구분
        id: ev.getId() + '_' + s.getTime(),
        calendar: cal.getName(),
        title: ev.getTitle() || '(제목 없음)',
        detail: ev.getLocation() || '',
        start: allDay ? ymd_(ev.getAllDayStartDate()) : s.toISOString(),
        end: allDay ? ymd_(ev.getAllDayEndDate()) : e.toISOString(),
        all_day: allDay,
        link: 'https://calendar.google.com/calendar/u/' + encodeURIComponent(account_()) + '/r/day/' +
              Utilities.formatDate(s, Session.getScriptTimeZone(), 'yyyy/M/d'),
      });
    });
  });
  return out;
}

/** 할 일: 모든 목록의 완료되지 않은 항목 (고급 서비스 Tasks 필요) */
function collectTasks_() {
  const out = [];
  let lists;
  try {
    lists = Tasks.Tasklists.list({ maxResults: 100 }).items || [];
  } catch (err) {
    throw new Error('Google Tasks API 서비스가 추가되지 않았습니다. 편집기 왼쪽 "서비스"에서 추가하세요. (' + err.message + ')');
  }
  lists.forEach((list, li) => {
    // 한 목록의 항목을 모두 받은 뒤, Google Tasks 화면의 '내 순서'(position)대로 정렬 키를 만든다
    const items = [];
    let pageToken;
    do {
      const res = Tasks.Tasks.list(list.id, {
        showCompleted: false, showHidden: false, maxResults: CONFIG.taskMax, pageToken: pageToken,
      });
      (res.items || []).forEach(t => items.push(t));
      pageToken = res.nextPageToken;
    } while (pageToken && out.length + items.length < 500);

    const pos = {};
    items.forEach(t => { pos[t.id] = t.position || ''; });
    items.forEach(t => {
      if (!t.title || !t.title.trim()) return;
      // 목록 순서 | 상위 항목 위치 | 자기 위치  (하위 항목은 상위 항목 바로 아래에 온다)
      const sub = !!(t.parent && pos[t.parent] !== undefined);
      const order = ('000' + li).slice(-3) + '|' + (sub ? pos[t.parent] + '|' : '') + (t.position || '');
      out.push({
        id: t.id,
        list: list.title,
        title: t.title,
        notes: t.notes || '',
        due: t.due ? t.due.substring(0, 10) : null,   // 마감은 날짜만 의미가 있다
        status: t.status,
        order: order,
        link: withAuthUser_(t.webViewLink || 'https://tasks.google.com/'),
      });
    });
  });
  return out;
}

/** 메일: 검색어에 맞는 최근 스레드의 제목·발신자·링크만 (본문은 보내지 않음) */
function collectMails_() {
  return GmailApp.search(CONFIG.mailQuery, 0, CONFIG.mailMax).map(th => {
    const msgs = th.getMessages();
    const last = msgs[msgs.length - 1];
    return {
      id: th.getId(),
      label: 'INBOX',
      from: senderName_(last.getFrom()),
      subject: th.getFirstMessageSubject() || '(제목 없음)',
      date: th.getLastMessageDate().toISOString(),
      status: th.hasStarredMessages() ? 'starred' : (th.isUnread() ? 'unread' : 'read'),
      // 여러 계정에 로그인한 브라우저에서도 이 계정의 메일이 열리도록 계정을 지정
      link: 'https://mail.google.com/mail/u/' + encodeURIComponent(account_()) + '/#all/' + th.getId(),
    };
  });
}

// ===== 전송 =====

function send_(payload) {
  const props = PropertiesService.getScriptProperties();
  const url = props.getProperty('UDECK_URL');
  const token = props.getProperty('UDECK_TOKEN');
  if (!url || !token) {
    throw new Error('스크립트 속성 UDECK_URL, UDECK_TOKEN을 먼저 설정하세요. (프로젝트 설정 → 스크립트 속성)');
  }
  const res = UrlFetchApp.fetch(url, {
    method: 'post',
    contentType: 'application/json; charset=utf-8',
    headers: { 'X-UDeck-Token': token },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true,
    followRedirects: false,
  });
  const code = res.getResponseCode();
  const text = res.getContentText();
  let body;
  try { body = JSON.parse(text); } catch (e) { body = { ok: false, error: text.substring(0, 300) }; }
  if (code !== 200 || !body.ok) {
    throw new Error('UDeck 전송 실패 (HTTP ' + code + '): ' + (body.error || text.substring(0, 300)));
  }
  const n = k => (payload[k] ? payload[k].length : '보내지 않음');
  return { ok: true, account: payload.account, items: body.items, events: n('events'), tasks: n('tasks'), mails: n('mails') };
}

// ===== 도우미 =====

/** 이 스크립트를 실행하는 구글 계정 */
function account_() {
  return Session.getEffectiveUser().getEmail() || '';
}

/** 링크에 계정 지정(authuser) 추가 */
function withAuthUser_(url) {
  const a = account_();
  if (!a) return url;
  return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'authuser=' + encodeURIComponent(a);
}

/** '홍길동 <a@b.com>' → '홍길동' */
function senderName_(from) {
  const m = String(from).match(/^\s*"?([^"<]*?)"?\s*<.+>\s*$/);
  return (m && m[1].trim()) || String(from).replace(/[<>]/g, '').trim();
}

function ymd_(d) {
  return Utilities.formatDate(d, Session.getScriptTimeZone(), 'yyyy-MM-dd');
}

// ===== 진단 =====

/** 수집이 0건일 때 원인 확인용. 실행 로그 내용을 그대로 복사해 전달한다 */
function diagnoseUDeck() {
  const L = s => Logger.log(s);
  L('실행 계정: ' + Session.getEffectiveUser().getEmail());
  L('스크립트 시간대: ' + Session.getScriptTimeZone());

  const [start, end] = eventRange_();
  L('--- 캘린더 (' + ymd_(start) + ' ~ ' + ymd_(end) + ') ---');
  CalendarApp.getAllCalendars().forEach(cal => {
    L(cal.getName() + ' | 숨김=' + cal.isHidden() + ' 표시=' + cal.isSelected() +
      ' | 일정 ' + cal.getEvents(start, end).length + '건');
  });

  L('--- 할 일 ---');
  try {
    (Tasks.Tasklists.list({ maxResults: 100 }).items || []).forEach(list => {
      const all = Tasks.Tasks.list(list.id, { showCompleted: true, showHidden: true, maxResults: 100 }).items || [];
      const open = all.filter(t => t.status !== 'completed');
      L(list.title + ' | 전체 ' + all.length + '건, 미완료 ' + open.length + '건');
    });
  } catch (err) {
    L('Tasks 오류: ' + err.message);
  }

  L('--- 메일 ---');
  L('받은편지함 읽지 않음: ' + GmailApp.getInboxUnreadCount() + '건');
  L('현재 검색어(' + CONFIG.mailQuery + '): ' + GmailApp.search(CONFIG.mailQuery, 0, 50).length + '건');
  L('받은편지함 최근 7일 전체: ' + GmailApp.search('in:inbox newer_than:7d', 0, 50).length + '건');
}
