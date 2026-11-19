/* =====================================================================
   UDeck 메인 보드 스크립트 v1.0
   ===================================================================== */
(function () {
  'use strict';

  const API = window.UDECK.api;
  /* ---------- 언어 ---------- */
  const DICT = window.UDECK.i18n || {};
  const EN = window.UDECK.lang === 'en';
  /** 번역: 한국어 원문을 키로 쓰고 {이름} 자리에 값을 넣는다 */
  const T = (s, v) => {
    // '원문|문맥' 형식은 한국어에서 | 앞만 쓴다
    let r = Object.prototype.hasOwnProperty.call(DICT, s) ? DICT[s] : s.split('|')[0];
    if (v) Object.keys(v).forEach(k => { r = r.split('{' + k + '}').join(v[k]); });
    return r;
  };
  const CSRF = window.UDECK.csrf;
  const $ = (s, el) => (el || document).querySelector(s);
  const $$ = (s, el) => Array.from((el || document).querySelectorAll(s));

  const state = {
    data: null,
    offset: 0,             // 서버 시각 - 브라우저 시각 (ms)
    range: 'days',         // 일정 탭: days | week | month
    taskList: '',          // 할 일 목록 필터
    showDone: false,
    openCell: null,        // 펼친 작업공간 칸 'wsId|projectId'
    loading: false,
  };

  /* ---------- 저장소 (브라우저별 설정) ---------- */
  const store = {
    get(k, d) { try { const v = localStorage.getItem('udeck.' + k); return v === null ? d : v; } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem('udeck.' + k, v); } catch (e) {} },
  };

  /* ---------- 공통 ---------- */
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const COLORS = ['teal', 'indigo', 'ochre', 'rose', 'violet', 'olive', 'sky', 'slate'];
  // PC 색: 설정에서 고른 색 (없으면 회색)
  const pcVar = w => 'var(--c-' + (w && COLORS.indexOf(w.color) >= 0 ? w.color : 'slate') + ')';
  const SRC = { hook: T('자동'), scan: T('자동'), handoff: 'HANDOFF', manual: T('직접 입력') };
  const WD = EN ? ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] : [T('일'), T('월'), T('화'), T('수'), T('목'), T('금'), T('토')];
  const WDL = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  /** 날짜 표기: 11월 19일 목요일 / Thu, Nov 19 */
  const fmtDay = (p, long) => EN ? (long ? WDL[p.wd] : WD[p.wd]) + ', ' + MON[p.m - 1] + ' ' + p.d
                                 : p.m + T('월 ') + p.d + T('일 ') + WD[p.wd] + T('요일');
  /** 시각 표기: 오후 2:13 / 2:13 PM */
  const fmtClock = p => {
    const h12 = (p.h % 12) || 12, mm = String(p.mi).padStart(2, '0');
    return EN ? h12 + ':' + mm + (p.h < 12 ? ' AM' : ' PM') : (p.h < 12 ? T('오전 ') : T('오후 ')) + h12 + ':' + mm;
  };

  function toast(msg, isErr) {
    const t = $('#toast');
    t.textContent = msg;
    t.className = 'toast' + (isErr ? ' err' : '');
    t.hidden = false;
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { t.hidden = true; }, isErr ? 5000 : 2200);
  }

  /** 저장 버튼: 누르면 "저장 중"으로 바꾸고 끝날 때까지 다시 못 누르게 */
  async function busy(btn, fn) {
    if (!btn || btn.disabled) return;
    const label = btn.textContent;
    btn.disabled = true;
    btn.textContent = (btn.dataset.busy || T('처리 중')) + '…';
    try { return await fn(); }
    finally { btn.disabled = false; btn.textContent = label; }
  }

  async function api(a, body) {
    const opt = body === undefined
      ? { method: 'GET', credentials: 'same-origin' }
      : { method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
          body: JSON.stringify(Object.assign({ a: a }, body)) };
    const url = body === undefined ? API + '?a=' + encodeURIComponent(a) + (state.showDone ? '&done=1' : '') : API;
    let res, json;
    try {
      res = await fetch(url, opt);
      json = await res.json();
    } catch (e) {
      throw new Error(T('서버에 연결하지 못했습니다. 인터넷 연결을 확인하세요.'));
    }
    if (res.status === 401) { location.href = 'login.php'; throw new Error(T('로그인이 필요합니다.')); }
    if (!json.ok) throw new Error(json.error || T('처리하지 못했습니다.'));
    return json;
  }

  /* ---------- 시간 ---------- */
  const parse = s => s ? new Date(s.replace(' ', 'T') + (s.length <= 10 ? 'T00:00:00' : '') + '+09:00') : null;
  const nowMs = () => Date.now() + state.offset;
  const kstParts = ms => {
    const d = new Date(ms + 9 * 3600000);
    return { y: d.getUTCFullYear(), m: d.getUTCMonth() + 1, d: d.getUTCDate(), h: d.getUTCHours(), mi: d.getUTCMinutes(), wd: d.getUTCDay() };
  };
  const dayKey = ms => { const p = kstParts(ms); return p.y * 10000 + p.m * 100 + p.d; };
  const pad = n => String(n).padStart(2, '0');
  const hm = ms => { const p = kstParts(ms); return pad(p.h) + ':' + pad(p.mi); };
  const dayDiff = ms => {
    const a = kstParts(nowMs()), b = kstParts(ms);
    return Math.round((Date.UTC(b.y, b.m - 1, b.d) - Date.UTC(a.y, a.m - 1, a.d)) / 86400000);
  };
  function ago(s) {
    const t = parse(s); if (!t) return '';
    const m = (nowMs() - t.getTime()) / 60000;
    if (m < 1) return T('방금');
    if (m < 60) return T('{n}분 전', { n: Math.round(m) });
    if (m < 60 * 12) return T('{n}시간 전', { n: Math.round(m / 60) });
    const dd = dayDiff(t.getTime());
    if (dd === 0) return T('오늘 {t}', { t: hm(t.getTime()) });
    if (dd === -1) return T('어제 {t}', { t: hm(t.getTime()) });
    const p = kstParts(t.getTime());
    return p.m + '/' + p.d + ' ' + hm(t.getTime());
  }
  const minsAgo = s => { const t = parse(s); return t ? (nowMs() - t.getTime()) / 60000 : Infinity; };
  const heat = s => Math.max(0.04, Math.pow(0.5, minsAgo(s) / 180));   // 반감기 3시간
  function localInputValue(ms) {  // datetime-local 값 (KST)
    const p = kstParts(ms);
    return p.y + '-' + pad(p.m) + '-' + pad(p.d) + 'T' + pad(p.h) + ':' + pad(p.mi);
  }

  /* ---------- 조회 도우미 ---------- */
  const wsById = id => state.data.workspaces.find(w => +w.id === +id);
  const toolById = id => state.data.tools.find(t => +t.id === +id);
  const projById = id => state.data.projects.find(p => +p.id === +id);

  /* =====================================================================
     렌더링
     ===================================================================== */
  function renderTop() {
    const p = kstParts(nowMs());
    $('#now').innerHTML = '<strong>' + fmtDay(p, true) + '</strong> <span>' + fmtClock(p) + '</span>';
    const d = state.data; if (!d) return;
    const g = d.google.synced_at;
    let h = '<span><i class="dot ' + (g ? (minsAgo(g) < 30 ? 'ok' : 'stale') : 'off') + '"></i>Google ' + (g ? ago(g) : T('연동 전')) + '</span>';
    d.workspaces.forEach(w => {
      const seen = w.last_seen_at, act = w.last_activity_at;
      let cls = 'off', txt = T('기록 없음');
      if (seen && minsAgo(seen) < 20) { cls = 'ok'; txt = T('접속 중'); }
      else {
        const last = [seen, act].filter(Boolean).sort().pop();
        if (last) { cls = minsAgo(last) < 60 * 24 ? 'stale' : 'off'; txt = ago(last); }
      }
      h += '<span><i class="dot ' + cls + '"></i>' + esc(w.name) + ' ' + esc(txt) + '</span>';
    });
    $('#syncs').innerHTML = h;
  }

  function renderHerePc() {
    const sel = $('#herePc');
    const cur = store.get('pc', '');
    sel.innerHTML = '<option value="">' + T('선택 안 함') + '</option>' + state.data.workspaces.map(w =>
      '<option value="' + w.id + '"' + (String(w.id) === cur ? ' selected' : '') + '>' + esc(w.name) + '</option>').join('');
  }

  /* 일정: 오늘·내일 / 이번 주(월~일) / 이번 달 */
  const DAY = 86400000;
  const kstMidnight = ms => { const p = kstParts(ms); return Date.UTC(p.y, p.m - 1, p.d) - 9 * 3600000; };
  function agendaRange(range) {
    const t0 = kstMidnight(nowMs());
    const p = kstParts(nowMs());
    if (range === 'week') {
      const start = t0 - ((p.wd + 6) % 7) * DAY;        // 월요일 시작
      return [start, start + 7 * DAY];
    }
    if (range === 'month') {
      const start = Date.UTC(p.y, p.m - 1, 1) - 9 * 3600000;
      return [start, Date.UTC(p.y, p.m, 1) - 9 * 3600000];
    }
    return [t0, t0 + 2 * DAY];
  }
  function dayTitle(ms) {
    const p = kstParts(ms), dd = dayDiff(ms);
    const base = fmtDay(p);
    return dd === 0 ? T('오늘, ') + base : dd === 1 ? T('내일, ') + base : base;
  }
  function renderAgenda() {
    const el = $('#agenda');
    $$('.tabs [data-range]').forEach(b => b.setAttribute('aria-selected', b.dataset.range === state.range));
    if (!state.data.google.synced_at) {
      el.innerHTML = '<p class="empty">' + T('구글 캘린더 연동 전입니다. Apps Script를 연결하면 일정이 표시됩니다.') + '</p>';
      return;
    }
    const [rs, re] = agendaRange(state.range);
    const groups = new Map();
    state.data.google.events.forEach(e => {
      const s = parse(e.start_at).getTime();
      const en = (parse(e.end_at) || parse(e.start_at)).getTime();
      if (s >= re || (s < rs && en <= rs)) return;                   // 범위 밖
      const key = kstMidnight(Math.max(s, rs));                     // 범위 이전에 시작한 일정은 첫날에 표시
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(e);
    });
    if (!groups.size) {
      const what = { days: T('오늘과 내일'), week: T('이번 주'), month: T('이번 달') }[state.range];
      el.innerHTML = '<p class="empty">' + T('{what} 일정이 없습니다.', { what: T(what) }) + '</p>';
      return;
    }
    let h = '', nowPlaced = false;
    Array.from(groups.keys()).sort((x, y) => x - y).forEach(day => {
      const isToday = dayDiff(day) === 0;
      h += '<p class="day-label' + (isToday ? ' today' : '') + '"' + (isToday ? ' id="agendaToday"' : '') + '>' + dayTitle(day) + '</p><ul class="agenda">';
      groups.get(day).forEach(e => {
        const s = parse(e.start_at), en = parse(e.end_at) || s;
        const past = en.getTime() <= nowMs();
        let cls = past ? 'past' : '';
        if (!past && !nowPlaced && dayDiff(s.getTime()) <= 0) { cls += ' nowline'; nowPlaced = true; }
        const time = +e.all_day ? T('종일') : hm(s.getTime());
        const sub = [+e.all_day ? '' : hm(s.getTime()) + '–' + hm(en.getTime()), e.detail].filter(Boolean).join(', ');
        h += '<li class="' + cls.trim() + '"><div class="row">' +
          '<time>' + time + '</time><div><div class="t">' + esc(e.title) + '</div>' +
          (sub ? '<div class="p">' + esc(sub) + '</div>' : '') + '</div></div></li>';
      });
      h += '</ul>';
    });
    el.innerHTML = h;
    // 이번 주·이번 달은 오늘 위치로 스크롤
    const today = $('#agendaToday', el);
    el.scrollTop = today && state.range !== 'days' ? today.offsetTop - el.offsetTop - 4 : 0;
  }

  function renderTasks() {
    const el = $('#tasks');
    const sel = $('#taskList');
    if (!state.data.google.synced_at) { el.innerHTML = '<p class="empty">' + T('Google Tasks 연동 전입니다.') + '</p>'; return; }
    const all = state.data.google.tasks;
    // 목록(카테고리) 선택 상자
    const lists = Array.from(new Set(all.map(t => t.container || T('내 할 일'))));
    if (state.taskList && lists.indexOf(state.taskList) < 0) state.taskList = '';
    sel.innerHTML = '<option value="">' + T('모든 목록 ({n})', { n: all.length }) + '</option>' + lists.map(l =>
      '<option value="' + esc(l) + '"' + (l === state.taskList ? ' selected' : '') + '>' + esc(l) + ' (' + all.filter(t => (t.container || T('내 할 일')) === l).length + ')</option>').join('');
    sel.hidden = lists.length < 2;
    const ts = state.taskList ? all.filter(t => (t.container || T('내 할 일')) === state.taskList) : all;
    if (!ts.length) { el.innerHTML = '<p class="empty">' + T('남은 할 일이 없습니다.') + '</p>'; return; }
    const item = t => {
      let due = '', over = false;
      if (t.due_at) {
        const ms = parse(t.due_at).getTime(), dd = dayDiff(ms), p = kstParts(ms);
        due = dd === 0 ? T('오늘') : dd === -1 ? T('어제') : dd === 1 ? T('내일') : (p.m + '/' + p.d);
        over = dd <= 0;
      }
      // 하위 항목(정렬 키에 상위 위치가 들어 있음)은 들여 쓴다
      const sub = t.sort_key && t.sort_key.split('|').length > 2;
      return '<li' + (sub ? ' class="sub"' : '') + '><div class="row"><span class="tt">' + esc(t.title) + '</span>' +
        '<span class="due' + (over ? ' over' : '') + '">' + esc(due) + '</span></div></li>';
    };
    if (state.taskList || lists.length < 2) {
      el.innerHTML = '<ul class="tasks">' + ts.map(item).join('') + '</ul>';
      return;
    }
    el.innerHTML = lists.map(l => {
      const g = ts.filter(t => (t.container || T('내 할 일')) === l);
      return '<p class="task-group"><span>' + esc(l) + '</span><span>' + g.length + '</span></p><ul class="tasks">' + g.map(item).join('') + '</ul>';
    }).join('');
  }

  function renderMails() {
    const el = $('#mails');
    const g = state.data.google;
    $('#mailLink').textContent = g.unread ? T('읽지 않음 {n}', { n: g.unread }) : T('Gmail 열기');
    if (!g.synced_at) { el.innerHTML = '<p class="empty">' + T('Gmail 연동 전입니다.') + '</p>'; return; }
    if (!g.mails.length) { el.innerHTML = '<p class="empty">' + T('확인할 메일이 없습니다.') + '</p>'; return; }
    const multi = new Set(g.mails.map(m => m.account || '')).size > 1;
    el.innerHTML = '<ul class="mails">' + g.mails.map(m => {
      const t = parse(m.start_at);
      const when = t ? (dayDiff(t.getTime()) === 0 ? hm(t.getTime()) : ago(m.start_at)) : '';
      return '<li><div class="row"><span class="from"><span>' +
        (m.status === 'starred' ? '<span class="star" aria-label="' + T('별표') + '">★</span> ' : '') + esc(m.detail || '') + '</span><span>' + esc(when) + '</span></span>' +
        '<span class="subj">' + esc(m.title) + '</span>' +
        (multi && m.account ? '<span class="from"><span>' + esc(m.account) + '</span></span>' : '') + '</div></li>';
    }).join('') + '</ul>';
  }

  function renderMemos() {
    const el = $('#memos');
    const ns = state.data.notes;
    $('#memoCount').textContent = ns.length ? T('{n}개', { n: ns.length }) : '';
    if (!ns.length) { el.innerHTML = '<li class="empty" style="border:0">' + T('메모가 없습니다.') + '</li>'; return; }
    el.innerHTML = ns.map(n => {
      const t = parse(n.updated_at);
      const dd = dayDiff(t.getTime()), p = kstParts(t.getTime());
      return '<li data-id="' + n.id + '">' + (+n.pinned ? '<span class="pin">' + T('고정|표시') + '</span>' : '') +
        '<span class="body" tabindex="0" title="' + T('눌러서 고치기') + '">' + esc(n.body) + '</span>' +
        '<span class="when">' + (dd === 0 ? T('오늘') : p.m + '/' + p.d) + '</span>' +
        '<span class="ops"><button type="button" data-op="pin">' + (+n.pinned ? T('고정 해제') : T('고정')) + '</button>' +
        '<button type="button" data-op="done">' + T('완료') + '</button><button type="button" data-op="del">' + T('삭제') + '</button></span></li>';
    }).join('');
  }

  /* ---------- 공통 표시 조각 ---------- */
  // 도구 아이콘 이름: 설정의 '아이콘 이름', 비어 있으면 이름으로 판단 (Codex, GPT, Claude, Claude C, Gemini, Antigravity)
  function toolAbbr(t) {
    if (t.short_name) return t.short_name;
    const s = (String(t.code || '') + ' ' + String(t.name || '')).toLowerCase();
    if (s.includes('codex')) return 'Codex';
    if (/claude[\s_\-]*code/.test(s)) return 'Claude C';
    if (s.includes('claude')) return 'Claude';
    if (s.includes('gpt')) return 'GPT';
    if (s.includes('gemini')) return 'Gemini';
    if (s.includes('antigravity')) return 'Antigravity';
    return String(t.name || t.code || '?').trim().slice(0, 12);
  }
  const toolIcons = ids => (ids || []).map(toolById).filter(Boolean).map(t =>
    '<span class="ticon" title="' + esc(t.name) + '"><span aria-hidden="true">' + esc(toolAbbr(t)) + '</span><span class="sr">' + esc(t.name) + '</span></span>').join('');
  const progBar = v => (v === null || v === undefined || v === '')
    ? '<span class="prog none">' + T('진행률 없음') + '</span>'
    : '<span class="prog" title="' + T('진행률 {n}%', { n: +v }) + '"><span class="bar"><i style="width:' + Math.max(0, Math.min(100, +v)) + '%"></i></span><span class="pv">' + (+v) + '%</span></span>';

  /* ---------- 현황판: 작업공간 (행: PC, 칸: 프로젝트) ---------- */
  function renderMatrix() {
    const d = state.data, m = $('#matrix');
    const by = {};
    d.pcproj.forEach(g => { (by[g.workspace_id] = by[g.workspace_id] || []).push(g); });
    const maxN = Math.max(1, ...d.workspaces.map(w => (by[w.id] || []).length));
    m.style.setProperty('--cols', maxN);
    let h = '';
    d.workspaces.forEach(w => {
      const seen = w.last_seen_at && minsAgo(w.last_seen_at) < 20;
      const last = [w.last_seen_at, w.last_activity_at].filter(Boolean).sort().pop();
      const st = seen ? '<i class="dot ok"></i>' + T('접속 중') : last ? '<i class="dot ' + (minsAgo(last) < 1440 ? 'stale' : 'off') + '"></i>' + esc(ago(last)) : '<i class="dot off"></i>' + T('기록 없음');
      h += '<div class="mx-rowh" style="--pc:' + pcVar(w) + '"><span class="nm">' + esc(w.name) + '</span>' +
        '<span class="st">' + st + '</span>' + (w.role_note ? '<span class="role">' + esc(w.role_note) + '</span>' : '') + '</div>';
      const gs = by[w.id] || [];
      if (!gs.length) {
        h += '<button type="button" class="cell empty" data-new="' + w.id + '" style="--pc:' + pcVar(w) + ';--heat:0">' +
          '<span class="sum">' + T('기록 없음. 눌러서 추가') + '</span></button>';
        return;
      }
      gs.forEach(g => {
        const env = g.env || [];
        const mx = Math.max(1, ...env);
        const bars = env.map(v => '<i style="height:' + Math.max(1, Math.round(v / mx * 14)) + 'px"></i>').join('');
        const hot = minsAgo(g.last_active_at) < 60;
        h += '<button type="button" class="cell" data-key="' + g.key + '" aria-pressed="' + (state.openCell === g.key) + '"' +
          ' style="--pc:' + pcVar(w) + ';--heat:' + heat(g.last_active_at).toFixed(2) + '">' +
          '<span class="proj' + (g.project_id ? '' : ' none') + '">' + esc(g.project_name || T('미분류')) + '</span>' +
          '<span class="sum">' + esc(g.summary || '') + '</span>' +
          '<span class="tl">' + toolIcons(g.tools) + '</span>' +
          progBar(g.progress) +
          '<span class="meta"><span class="ago' + (hot ? ' hot' : '') + '">' + esc(ago(g.last_active_at)) + '</span>' +
          '<span class="env" title="' + T('최근 7일 활동') + '">' + bars + '</span></span></button>';
      });
    });
    m.innerHTML = h;
    renderDetail();
    updateMxNav();
  }

  /* 칸이 넘치면 ◀ ▶ 버튼으로 옆으로 넘겨 본다 */
  function mxStep() {
    const c = $('#matrix .cell');
    return c ? c.getBoundingClientRect().width + 6 : 180;
  }
  function updateMxNav() {
    const sc = $('#mxScroll'), nav = $('#mxNav');
    const over = sc.scrollWidth - sc.clientWidth > 4 && getComputedStyle(sc).overflowX !== 'visible';
    nav.hidden = !over;
    if (!over) return;
    $('#mxPrev').disabled = sc.scrollLeft <= 2;
    $('#mxNext').disabled = sc.scrollLeft >= sc.scrollWidth - sc.clientWidth - 2;
  }

  const openGroup = () => state.openCell && state.data.pcproj.find(x => x.key === state.openCell);

  function renderDetail() {
    const box = $('#detail');
    const g = openGroup();
    if (!g) { box.hidden = true; state.openCell = null; return; }
    const w = wsById(g.workspace_id), p = g.project_id && projById(g.project_id);
    const it = g.items[0] || {};
    box.style.setProperty('--pc', pcVar(w));
    const projOpts = '<option value="">' + T('미분류') + '</option>' + state.data.projects.map(x =>
      '<option value="' + x.id + '"' + (+x.id === +g.project_id ? ' selected' : '') + '>' + esc(x.name) + '</option>').join('');
    const items = g.items.map(a => {
      const t = toolById(a.tool_id);
      return '<li>' + toolIcons([a.tool_id]) + '<div><div class="it-h"><b>' + esc(t ? t.name : '') + '</b>' +
        (a.stage ? ' <span class="stage">' + esc(a.stage) + '</span>' : '') +
        (a.progress !== null && a.progress !== undefined ? ' <span class="stage">' + (+a.progress) + '%</span>' : '') +
        '<span class="when">' + esc(ago(a.last_active_at)) + '</span></div>' +
        '<div class="it-s">' + esc(a.summary || a.title || a.last_prompt || '') + '</div></div>' +
        '<button class="btn btn-text" type="button" data-act="edit" data-id="' + a.id + '">' + T('고치기') + '</button></li>';
    }).join('');
    box.innerHTML =
      '<div><h3>' + esc(g.project_name || T('미분류 작업')) + '</h3>' +
      '<div class="ctx">' + T('{pc}, {ago} 마지막 기록', { pc: esc(w.name), ago: esc(ago(g.last_active_at)) }) + '</div>' +
      '<ul class="items">' + items + '</ul>' +
      (p && p.next_action ? '<div class="prompt">' + T('다음 할 일: ') + esc(p.next_action) + '</div>' : '') +
      '<div class="row-actions">' +
        '<button class="btn btn-ghost" type="button" data-act="continue">' + T('이어서 기록') + '</button>' +
        (it.cwd ? '<button class="btn btn-ghost" type="button" data-act="copy">' + T('폴더 경로 복사') + '</button>' : '') +
        '<a class="btn btn-ghost" href="https://remotedesktop.google.com/access" target="_blank" rel="noopener">' + T('원격 데스크톱 열기') + '</a>' +
      '</div></div>' +
      '<dl>' +
        '<dt>' + T('프로젝트') + '</dt><dd><select class="select" data-act="assign" style="height:30px">' + projOpts + '</select></dd>' +
        '<dt>' + T('진행률') + '</dt><dd>' + progBar(g.progress) + '</dd>' +
        '<dt>' + T('도구') + '</dt><dd>' + toolIcons(g.tools) + '</dd>' +
        (it.cwd ? '<dt>' + T('작업 폴더') + '</dt><dd>' + esc(it.cwd) + '</dd>' : '') +
        '<dt>' + T('기록 수') + '</dt><dd>' + T('{n}건', { n: g.count }) + '</dd>' +
      '</dl>';
    box.hidden = false;
  }

  /* ---------- 프로젝트 목록: 정렬과 중요도 순서 ---------- */
  function projectRanks() {
    const r = new Map();
    state.data.projects.slice().sort((a, b) => (+a.sort_order - +b.sort_order) || (+a.id - +b.id))
      .forEach((p, i) => r.set(+p.id, i + 1));
    return r;
  }
  function sortedProjects(rank) {
    const ps = state.data.projects.slice();
    if (state.projSort === 'created') return ps.sort((a, b) => +a.id - +b.id);
    if (state.projSort === 'due') {
      const key = p => (p.due_date && p.due_type !== 'none') ? p.due_date : '9999-99-99';
      return ps.sort((a, b) => key(a).localeCompare(key(b)) ||
        ((a.due_type === 'fixed' ? 0 : 1) - (b.due_type === 'fixed' ? 0 : 1)) || rank.get(+a.id) - rank.get(+b.id));
    }
    return ps.sort((a, b) => rank.get(+a.id) - rank.get(+b.id));
  }

  function renderProjects() {
    const ps = state.data.projects;
    $('#projEmpty').hidden = ps.length > 0;
    const STATUS = { paused: T('보류'), done: T('완료'), archived: T('보관') };
    const rank = projectRanks(), canDrag = state.projSort === 'rank';
    $('.projects').classList.toggle('can-drag', canDrag);
    $('#projRows').innerHTML = sortedProjects(rank).map(p => {
      const rk = rank.get(+p.id);
      let dd = '<span class="dday none">' + T('마감 없음') + '</span>';
      if (p.due_date && p.due_type !== 'none') {
        const n = dayDiff(parse(p.due_date).getTime());
        const fixed = p.due_type === 'fixed';
        const soon = fixed ? n <= 14 : n <= 3;
        dd = '<span class="dday ' + (fixed ? 'fixed' : 'self') + (soon ? ' soon' : '') + '" title="' + esc(p.due_date) + '">' + dlabel(n) + '</span>' +
          '<span class="dkind">' + (fixed ? T('정해진 마감') : T('자체 마감')) + '</span>';
      }
      const devs = (p.ws_ids || []).map(wsById).filter(Boolean).map((w, i) =>
        '<span class="pcws' + (i === 0 ? ' cur' : '') + '" style="--pc:' + pcVar(w) + '"' + (i === 0 ? ' title="' + T('가장 최근 작업') + '"' : '') + '>' + esc(w.name) + '</span>').join('');
      const mainWs = p.workspace_id && wsById(p.workspace_id);
      return '<tr data-id="' + p.id + '" class="' + esc(p.status) + '" tabindex="0">' +
        '<td class="c-rank">' + (canDrag
          ? '<button type="button" class="rk" data-drag aria-label="' + T('중요도 {n}위. 끌거나 위아래 화살표 키로 순서를 바꿉니다.', { n: rk }) + '" title="' + T('끌어서 순서 바꾸기') + '">' + rk + '</button>'
          : '<span class="rk">' + rk + '</span>') + '</td>' +
        '<td><div class="pname">' + esc(p.name) + (STATUS[p.status] ? '<span class="pstate">' + STATUS[p.status] + '</span>' : '') + '</div>' +
        '<div class="pcat">' + [esc(p.category || ''), esc(p.phase || ''),
          mainWs ? T('주: {pc}', { pc: esc(mainWs.name) }) : ''].filter(Boolean).join(', ') + '</div></td>' +
        '<td><div class="devs">' + (devs || '<span class="dday none">' + T('기록 없음') + '</span>') + '</div></td>' +
        '<td><div class="tl">' + (toolIcons(p.tool_ids) || '<span class="dday none">-</span>') + '</div></td>' +
        '<td>' + progBar(p.progress) + '</td>' +
        '<td class="next">' + (p.next_action ? esc(p.next_action) : '<span class="none">' + T('정하지 않음') + '</span>') + '</td>' +
        '<td>' + dd + '</td></tr>';
    }).join('');
  }

  const dlabel = n => n === 0 ? 'D-Day' : n > 0 ? 'D-' + n : 'D+' + (-n);

  function renderFeed() {
    const f = state.data.feed;
    const el = $('#feed');
    if (!f.length) {
      el.innerHTML = '<li style="display:block" class="empty">' + T('아직 기록이 없습니다. 상단의 "작업 기록 추가"로 첫 기록을 남겨 보세요.') + '</li>';
      return;
    }
    el.innerHTML = '<span class="feed-line" aria-hidden="true"></span>' + f.map(a => {
      const w = wsById(a.workspace_id), t = toolById(a.tool_id);
      return '<li style="--pc:' + pcVar(w) + '"><time>' + esc(ago(a.last_active_at)) + '</time><span class="node" aria-hidden="true"></span>' +
        '<div><span class="what' + (a.project_name ? '' : ' none') + '">' + esc(a.project_name || T('미분류')) + '</span><span class="src">' + esc(SRC[a.source] || a.source) + '</span>' +
        '<div class="where">' + esc(w ? w.name : '') + ', ' + esc(t ? t.name : '') + (a.stage ? ', ' + esc(a.stage) : '') + '. ' + esc(a.summary || a.title || a.last_prompt || '') + '</div></div>' +
        '<span class="ops"><button class="btn btn-text" type="button" data-edit="' + a.id + '">' + T('고치기') + '</button></span></li>';
    }).join('');
  }

  /* =====================================================================
     AI 비서 (규칙 기반, API를 쓰지 않음)
     ===================================================================== */
  const QUOTES = [
    ['천 리 길도 한 걸음부터.', 'A journey of a thousand miles begins with a single step.', '속담'],
    ['시작이 반이다.', 'Well begun is half done.', '속담'],
    ['티끌 모아 태산.', 'Many a little makes a mickle.', '속담'],
    ['급할수록 돌아가라.', 'More haste, less speed.', '속담'],
    ['돌다리도 두들겨 보고 건너라.', 'Look before you leap.', '속담'],
    ['구슬이 서 말이라도 꿰어야 보배.', 'Beads are treasure only once they are strung.', '속담'],
    ['낙숫물이 댓돌을 뚫는다.', 'Constant dripping wears away the stone.', '속담'],
    ['아는 길도 물어 가라.', 'Ask the way even on a road you know.', '속담'],
    ['오늘 끝낸 한 단락이 내일의 열 쪽보다 낫다.', 'One paragraph finished today beats ten pages planned for tomorrow.', '한 줄'],
    ['마감은 달력에서 보는 것보다 늘 가깝다.', 'Deadlines are always closer than they look on the calendar.', '한 줄'],
    ['커밋은 자주, 후회는 드물게.', 'Commit often, regret rarely.', '한 줄'],
    ['백업이 있는 사람은 밤에 잘 잔다.', 'People with backups sleep well.', '한 줄'],
    ['어제의 내가 남긴 HANDOFF.md는 오늘의 나에게 주는 선물이다.', 'The HANDOFF.md you left yesterday is a gift to yourself today.', '한 줄'],
    ['p 값보다 중요한 것은 끝까지 쓴 논문이다.', 'A finished paper matters more than a perfect p-value.', '한 줄'],
    ['버그는 숨지 않는다. 아직 만나지 않았을 뿐이다.', 'Bugs do not hide. You just have not met them yet.', '한 줄'],
    ['커피는 연료, 휴식은 정비.', 'Coffee is fuel; rest is maintenance.', '한 줄'],
    ['할 일 목록이 길면 맨 위 하나만 보자.', 'When the list is long, look only at the top item.', '한 줄'],
    ['쉬는 것도 일정에 넣어야 지켜진다.', 'Rest only happens if it is on the schedule.', '한 줄'],
  ];
  const isEn = () => (window.UDECK && window.UDECK.lang) === 'en';
  const slot15 = () => Math.floor(nowMs() / 900000);

  function aiUrgent() {
    const g = state.data.google, out = [], now = nowMs();
    (g.tasks || []).forEach(t => {
      if (!t.due_at) return;
      const n = dayDiff(parse(t.due_at).getTime());
      if (n < 0) out.push({ hot: 1, w: 0, h: T('할 일 기한이 {n}일 지났습니다: {t}', { n: -n, t: esc(t.title) }) });
      else if (n === 0) out.push({ hot: 1, w: 1, h: T('오늘까지 할 일: {t}', { t: esc(t.title) }) });
    });
    (g.events || []).forEach(e => {
      if (+e.all_day) return;
      const s = parse(e.start_at).getTime(), en = (parse(e.end_at) || parse(e.start_at)).getTime();
      const m = Math.round((s - now) / 60000);
      if (s <= now && en > now) out.push({ hot: 0, w: 2, h: T('진행 중인 일정: {t}', { t: esc(e.title) }) });
      else if (m > 0 && m <= 180) out.push({ hot: m <= 30 ? 1 : 0, w: 2, h: m < 60
        ? T('{time} {t} ({n}분 후)', { time: hm(s), t: esc(e.title), n: m })
        : T('{time} {t} ({h}시간 {n}분 후)', { time: hm(s), t: esc(e.title), h: Math.floor(m / 60), n: m % 60 }) });
    });
    state.data.projects.forEach(p => {
      if (!p.due_date || p.due_type === 'none' || p.status !== 'active') return;
      const n = dayDiff(parse(p.due_date).getTime());
      if (p.due_type === 'fixed' && n <= 14) out.push({ hot: n <= 3 ? 1 : 0, w: 3, h: T('정해진 마감 {d}: {p}', { d: dlabel(n), p: esc(p.name) }) });
      else if (p.due_type === 'self' && n <= 3) out.push({ hot: 0, w: 4, h: T('자체 마감 {d}: {p}', { d: dlabel(n), p: esc(p.name) }) });
    });
    const star = (g.mails || []).filter(m => m.status === 'starred');
    star.slice(0, 2).forEach(m => out.push({ hot: 0, w: 5, h: T('별표 메일: {t}', { t: esc(m.title) }) }));
    if (star.length > 2) out.push({ hot: 0, w: 5, h: T('별표 메일이 {n}통 더 있습니다.', { n: star.length - 2 }) });
    return out.sort((a, b) => b.hot - a.hot || a.w - b.w).slice(0, 6);
  }

  function aiContinue() {
    const d = state.data, out = [];
    const feed = d.feed || [];
    // 프로젝트가 지정된 가장 최근 기록을 기준으로 삼는다 (없으면 가장 최근 기록)
    const pick = feed.find(a => a.project_id) || feed[0];
    if (pick) {
      const w = wsById(pick.workspace_id), t = toolById(pick.tool_id), p = pick.project_id ? projById(pick.project_id) : null;
      const n = dayDiff(parse(pick.last_active_at).getTime());
      const when = n === 0 ? T('오늘') : n === -1 ? T('어제') : T('{n}일 전', { n: -n });
      const cap = x => x.charAt(0).toUpperCase() + x.slice(1);
      out.push(cap(T('{when} {pc}의 {tool}에서 {proj} 작업을 했습니다.', {
        when: when, pc: esc(w ? w.name : '?'), tool: esc(t ? t.name : '?'),
        proj: '<strong>' + esc(pick.project_name || T('미분류')) + '</strong>' })));
      if (p && p.progress !== null && p.progress !== undefined) out.push(T('진행률 {n}%', { n: +p.progress }));
      if (p && p.next_action) out.push(T('이어서 할 일: {next}', { next: esc(p.next_action) }));
      else if (pick.summary) out.push(T('마지막 기록: {s}', { s: esc(pick.summary) }));
      const here = store.get('pc', '');
      if (here && w && +here !== +w.id) {
        const hw = wsById(here);
        if (hw) out.push(T('지금은 {here}입니다. {pc}에서 이어 가거나 HANDOFF.md를 먼저 확인하세요.', { here: esc(hw.name), pc: esc(w.name) }));
      }
    } else {
      out.push(T('아직 작업 기록이 없습니다. 오늘 첫 기록을 남겨 보세요.'));
    }
    // 오래 손대지 않은 진행 중 프로젝트 (우선순위 높은 순으로 1건)
    const rank = projectRanks();
    const stale = d.projects.filter(p => p.status === 'active' && p.last_at)
      .map(p => ({ p: p, n: -dayDiff(parse(p.last_at).getTime()) }))
      .filter(x => x.n >= 7).sort((a, b) => rank.get(+a.p.id) - rank.get(+b.p.id) || b.n - a.n)[0];
    if (stale) out.push(T('{p}는 {n}일째 기록이 없습니다.', { p: esc(stale.p.name), n: stale.n }));
    return out.slice(0, 4);
  }

  function aiGreeting() {
    const h = kstParts(nowMs()).h;
    const g = h < 5 ? T('밤이 깊었습니다. 무리하지 마세요.') : h < 11 ? T('좋은 아침입니다.') : h < 14 ? T('점심 무렵입니다.') :
      h < 18 ? T('오후 시간입니다.') : h < 22 ? T('저녁 시간입니다.') : T('밤이 깊었습니다. 무리하지 마세요.');
    const today = dayKey(nowMs()), g2 = state.data.google;
    const ev = (g2.events || []).filter(e => dayKey(parse(e.start_at).getTime()) === today).length;
    const tk = (g2.tasks || []).filter(t => t.due_at && dayDiff(parse(t.due_at).getTime()) <= 0).length;
    return g + ' ' + T('오늘 일정 {e}건, 오늘까지 할 일 {t}건입니다.', { e: ev, t: tk });
  }

  function renderAI(force) {
    if (!state.data) return;
    const sl = slot15();
    if (!force && state.aiSlot === sl) return;
    state.aiSlot = sl;
    const urg = aiUrgent(), con = aiContinue();
    const q = QUOTES[(sl + (force ? Math.floor(Math.random() * QUOTES.length) : 0)) % QUOTES.length];
    $('#aiBox').innerHTML =
      '<p class="ai-hello">' + esc(aiGreeting()) + '</p>' +
      '<div class="ai-block"><h3>' + T('급한 일') + '</h3>' + (urg.length
        ? '<ul>' + urg.map(x => '<li' + (x.hot ? ' class="hot"' : '') + '>' + x.h + '</li>').join('') + '</ul>'
        : '<p class="muted">' + T('급한 일이 없습니다. 깊이 있는 작업에 좋은 때입니다.') + '</p>') + '</div>' +
      '<div class="ai-block"><h3>' + T('오늘 이어서') + '</h3><ul>' + con.map(x => '<li>' + x + '</li>').join('') + '</ul></div>' +
      '<p class="ai-quote">' + esc(isEn() ? q[1] : q[0]) + '</p>' +
      '<p class="ai-foot">' + T('규칙 기반 안내. {t} 기준, 15분마다 바뀝니다.', { t: hm(nowMs()) }) + '</p>';
  }

  function renderAll() {
    renderTop(); renderAgenda(); renderTasks(); renderMails(); renderMemos();
    renderMatrix(); renderProjects(); renderFeed(); renderAI();
  }

  /* =====================================================================
     데이터 불러오기
     ===================================================================== */
  async function load(quiet) {
    if (state.loading) return;
    state.loading = true;
    try {
      const d = await api('board');
      state.data = d;
      state.offset = parse(d.now).getTime() - Date.now();
      if (!$('#herePc').options.length) renderHerePc();
      renderAll();
    } catch (e) {
      if (!quiet) toast(e.message, true);
    } finally {
      state.loading = false;
    }
  }

  /* =====================================================================
     작업 기록 대화상자
     ===================================================================== */
  const dlgA = $('#dlgActivity'), formA = $('#formActivity');

  function chips(el, name, items, selected) {
    el.innerHTML = items.map(x =>
      '<label style="--pc:' + (x.color ? pcVar(x) : 'var(--ink-2)') + '"><input type="radio" name="' + name + '" value="' + x.id + '"' +
      (+x.id === +selected ? ' checked' : '') + ' required><span>' + esc(x.name) + '</span></label>').join('');
  }

  function openActivity(pre) {
    pre = pre || {};
    const d = state.data;
    const tools = d.tools.filter(t => +t.is_active === 1 || +t.id === +pre.tool_id);
    chips($('#actPc'), 'workspace_id', d.workspaces, pre.workspace_id || store.get('pc', ''));
    chips($('#actTool'), 'tool_id', tools, pre.tool_id || store.get('lastTool', ''));
    $('#actProject').innerHTML = '<option value="">' + T('미분류') + '</option>' + d.projects.map(p =>
      '<option value="' + p.id + '">' + esc(p.name) + '</option>').join('');
    formA.reset();
    formA.elements.id.value = pre.id || '';
    $('#actProject').value = pre.project_id || '';
    formA.elements.stage.value = pre.stage || '';
    formA.elements.summary.value = pre.summary || '';
    formA.elements.next_action.value = '';
    formA.elements.cwd.value = pre.cwd || '';
    const pp = pre.project_id && projById(pre.project_id);
    setProgress(pre.progress !== undefined && pre.progress !== null ? pre.progress : (!pre.id && pp ? pp.progress : null));
    formA.elements.at.value = pre.at ? localInputValue(parse(pre.at).getTime()) : localInputValue(nowMs());
    // reset()이 라디오 선택을 지우므로 다시 지정
    const pcSel = pre.workspace_id || store.get('pc', '');
    const toolSel = pre.tool_id || store.get('lastTool', '');
    $$('input[name="workspace_id"]', formA).forEach(r => { r.checked = r.value === String(pcSel); });
    $$('input[name="tool_id"]', formA).forEach(r => { r.checked = r.value === String(toolSel); });
    $('#dlgActTitle').textContent = pre.id ? T('작업 기록 고치기') : T('작업 기록 추가');
    $('#actDelete').hidden = !pre.id;
    dlgA.showModal();
    (pcSel ? formA.elements.summary : $('input[name="workspace_id"]', formA)).focus();
  }

  // 진행률: 막대를 끌거나 숫자를 직접 입력한다. 비워 두면 진행률을 기록하지 않는다.
  const progR = $('#actProgR'), progN = $('#actProgN');
  function setProgress(v) {
    const has = v !== null && v !== undefined && v !== '';
    progN.value = has ? Math.max(0, Math.min(100, Math.round(+v))) : '';
    progR.value = has ? progN.value : 0;
    $('#actProgWrap').classList.toggle('unset', !has);
    progR.style.setProperty('--v', (has ? progN.value : 0) + '%');
  }
  progR.addEventListener('input', () => setProgress(progR.value));
  progN.addEventListener('input', () => {
    if (progN.value === '') { setProgress(null); return; }
    const v = Math.max(0, Math.min(100, +progN.value));
    progR.value = v; progR.style.setProperty('--v', v + '%');
    $('#actProgWrap').classList.remove('unset');
  });
  progN.addEventListener('change', () => setProgress(progN.value === '' ? null : progN.value));
  $('#actProgClear').addEventListener('click', () => setProgress(null));
  $('#actProject').addEventListener('change', e => {
    if (formA.elements.id.value) return;
    const p = projById(e.target.value);
    setProgress(p ? p.progress : null);
  });

  formA.addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(formA);
    const body = Object.fromEntries(fd.entries());
    if (!body.workspace_id) { toast(T('PC를 선택하세요.'), true); return; }
    if (!body.tool_id) { toast(T('도구를 선택하세요.'), true); return; }
    await busy($('button[type=submit]', formA), async () => {
      try {
        await api('activity.save', body);
        store.set('lastTool', body.tool_id);
        dlgA.close();
        toast(body.id ? T('기록을 고쳤습니다.') : T('기록을 저장했습니다.'));
        load();
      } catch (err) { toast(err.message, true); }
    });
  });

  $('#actDelete').addEventListener('click', async e => {
    if (!confirm(T('이 기록을 삭제할까요?'))) return;
    const btn = e.currentTarget; btn.disabled = true;
    try {
      await api('activity.delete', { id: formA.elements.id.value });
      dlgA.close(); state.openCell = null;
      toast(T('기록을 삭제했습니다.'));
      load();
    } catch (err) { toast(err.message, true); }
    finally { btn.disabled = false; }
  });

  /* =====================================================================
     프로젝트 대화상자
     ===================================================================== */
  const dlgP = $('#dlgProject'), formP = $('#formProject');

  function openProject(p) {
    formP.reset();
    p = p || {};
    formP.elements.id.value = p.id || '';
    formP.elements.name.value = p.name || '';
    formP.elements.category.value = p.category || '';
    formP.elements.status.value = p.status || 'active';
    formP.elements.due_date.value = p.due_date || '';
    setDueType(p.id ? (p.due_type || (p.due_date ? 'self' : 'none')) : 'none');
    formP.elements.folder_path.value = p.folder_path || '';
    formP.elements.next_action.value = p.next_action || '';
    $('#pjWs').innerHTML = '<option value="">' + T('지정 안 함') + '</option>' + state.data.workspaces.map(w =>
      '<option value="' + w.id + '">' + esc(w.name) + '</option>').join('');
    $('#pjWs').value = p.workspace_id || (p.id ? '' : store.get('pc', ''));
    $('#pjHandoffWrap').hidden = !p.handoff_md;
    $('#pjHandoff').textContent = p.handoff_md || '';
    $('#pjHandoffAt').textContent = p.handoff_updated_at ? T('{ago} 갱신', { ago: ago(p.handoff_updated_at) }) : '';
    $('#dlgProjTitle').textContent = p.id ? T('프로젝트 고치기') : T('프로젝트 추가');
    $('#pjDelete').hidden = !p.id;
    dlgP.showModal();
    formP.elements.name.focus();
  }

  function setDueType(v) {
    $$('input[name=due_type]', formP).forEach(r => { r.checked = r.value === v; });
    const inp = formP.elements.due_date;
    inp.disabled = v === 'none';
    inp.required = v !== 'none';
  }
  $$('input[name=due_type]', formP).forEach(r => r.addEventListener('change', () => {
    setDueType(r.value);
    if (r.value !== 'none' && !formP.elements.due_date.value) formP.elements.due_date.focus();
  }));

  formP.addEventListener('submit', async e => {
    e.preventDefault();
    const body = Object.fromEntries(new FormData(formP).entries());
    if (body.due_type === 'none') body.due_date = '';
    await busy($('button[type=submit]', formP), async () => {
      try {
        await api('project.save', body);
        dlgP.close();
        toast(body.id ? T('프로젝트를 고쳤습니다.') : T('프로젝트를 추가했습니다.'));
        load();
      } catch (err) { toast(err.message, true); }
    });
  });

  $('#pjDelete').addEventListener('click', async e => {
    if (!confirm(T('이 프로젝트를 삭제할까요? 연결된 작업 기록은 미분류로 남습니다.'))) return;
    const btn = e.currentTarget; btn.disabled = true;
    try {
      await api('project.delete', { id: formP.elements.id.value });
      dlgP.close();
      toast(T('프로젝트를 삭제했습니다.'));
      load();
    } catch (err) { toast(err.message, true); }
    finally { btn.disabled = false; }
  });

  $$('[data-close]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));

  /* =====================================================================
     이벤트
     ===================================================================== */
  $('#btnAddActivity').addEventListener('click', () => openActivity());
  state.range = store.get('range', 'days');
  $$('.tabs [data-range]').forEach(b => b.addEventListener('click', () => {
    state.range = b.dataset.range; store.set('range', state.range); renderAgenda();
  }));
  state.taskList = store.get('taskList', '');
  $('#taskList').addEventListener('change', e => { state.taskList = e.target.value; store.set('taskList', state.taskList); renderTasks(); });
  $('#btnAddProject').addEventListener('click', () => openProject());
  $('#showDone').addEventListener('change', e => { state.showDone = e.target.checked; load(); });
  $('#herePc').addEventListener('change', e => {
    store.set('pc', e.target.value);
    toast(e.target.value ? T('이 브라우저를 {pc}로 지정했습니다.', { pc: e.target.selectedOptions[0].text }) : T('PC 지정을 해제했습니다.'));
  });
  $('#btnTheme').addEventListener('click', () => {
    const t = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = t;
    store.set('theme', t);
  });

  // 판 전환 탭 (휴대폰)
  const boardEl = $('.board');
  function setBoard(b) {
    boardEl.dataset.active = b;
    $$('.boardtabs [data-board]').forEach(x => x.setAttribute('aria-selected', x.dataset.board === b));
    store.set('board', b);
    if (b === 'status') updateMxNav();
  }
  $$('.boardtabs [data-board]').forEach(x => x.addEventListener('click', () => { setBoard(x.dataset.board); window.scrollTo(0, 0); }));
  setBoard(store.get('board', 'black'));

  // AI 비서
  $('#aiRefresh').addEventListener('click', () => renderAI(true));

  // 매트릭스 좌우 넘기기
  $('#mxPrev').addEventListener('click', () => { $('#mxScroll').scrollBy({ left: -mxStep(), behavior: 'smooth' }); });
  $('#mxNext').addEventListener('click', () => { $('#mxScroll').scrollBy({ left: mxStep(), behavior: 'smooth' }); });
  $('#mxScroll').addEventListener('scroll', () => updateMxNav(), { passive: true });
  window.addEventListener('resize', () => { if (state.data) updateMxNav(); });

  // 작업공간 칸
  $('#matrix').addEventListener('click', e => {
    const b = e.target.closest('button.cell');
    if (!b) return;
    if (b.dataset.new) { openActivity({ workspace_id: b.dataset.new }); return; }
    state.openCell = state.openCell === b.dataset.key ? null : b.dataset.key;
    $$('#matrix .cell[aria-pressed]').forEach(x => x.setAttribute('aria-pressed', x.dataset.key === state.openCell));
    renderDetail();
  });

  $('#detail').addEventListener('click', async e => {
    const b = e.target.closest('[data-act]');
    if (!b || b.tagName === 'SELECT') return;
    const g = openGroup();
    if (!g) return;
    const it = g.items[0] || {};
    if (b.dataset.act === 'continue') {
      openActivity({ workspace_id: g.workspace_id, tool_id: it.tool_id, project_id: g.project_id, stage: it.stage, cwd: it.cwd, progress: g.progress });
    } else if (b.dataset.act === 'edit') {
      const a = g.items.find(x => +x.id === +b.dataset.id);
      if (a) openActivity(a);
    } else if (b.dataset.act === 'copy') {
      try { await navigator.clipboard.writeText(it.cwd); toast(T('폴더 경로를 복사했습니다.')); }
      catch (err) { toast(T('복사하지 못했습니다. 경로를 직접 선택해 복사하세요.'), true); }
    }
  });

  $('#detail').addEventListener('change', async e => {
    if (e.target.dataset.act !== 'assign') return;
    const g = openGroup();
    if (!g) return;
    try {
      for (const a of g.items) await api('activity.assign', { id: a.id, project_id: e.target.value });
      state.openCell = null;
      toast(T('프로젝트를 지정했습니다. 같은 폴더의 기록은 앞으로 자동 분류됩니다.'));
      load();
    } catch (err) { toast(err.message, true); }
  });

  // 프로젝트 행
  const openRow = tr => { const p = projById(tr.dataset.id); if (p) openProject(p); };
  const tbodyP = $('#projRows');
  tbodyP.addEventListener('click', e => {
    if (e.target.closest('[data-drag]') || Date.now() - (state.dragEnd || 0) < 400) return;
    const tr = e.target.closest('tr[data-id]'); if (tr) openRow(tr);
  });
  tbodyP.addEventListener('keydown', e => {
    const h = e.target.closest('[data-drag]');
    if (h && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
      e.preventDefault();
      const tr = h.closest('tr');
      const sib = e.key === 'ArrowUp' ? tr.previousElementSibling : tr.nextElementSibling;
      if (!sib) return;
      tbodyP.insertBefore(tr, e.key === 'ArrowUp' ? sib : sib.nextElementSibling);
      h.focus();
      saveRank(tr.dataset.id);
      return;
    }
    if (e.key !== 'Enter' || h) return;
    const tr = e.target.closest('tr[data-id]'); if (tr) openRow(tr);
  });

  // 중요도 순서: 번호를 끌어서 위아래로 옮긴다 (마우스, 터치 공통)
  state.drag = null;
  tbodyP.addEventListener('pointerdown', e => {
    const h = e.target.closest('[data-drag]');
    if (!h || state.projSort !== 'rank' || e.button > 0) return;
    e.preventDefault();
    h.setPointerCapture(e.pointerId);
    state.drag = { tr: h.closest('tr'), y: e.clientY, moved: false };
    state.drag.tr.classList.add('dragging');
  });
  tbodyP.addEventListener('pointermove', e => {
    const dg = state.drag;
    if (!dg) return;
    if (!dg.moved && Math.abs(e.clientY - dg.y) < 4) return;
    dg.moved = true;
    const rows = $$('tr[data-id]', tbodyP).filter(r => r !== dg.tr);
    const before = rows.find(r => { const b = r.getBoundingClientRect(); return e.clientY < b.top + b.height / 2; });
    if (before !== dg.tr.nextElementSibling) tbodyP.insertBefore(dg.tr, before || null);
  });
  const endDrag = () => {
    const dg = state.drag;
    if (!dg) return;
    state.drag = null;
    dg.tr.classList.remove('dragging');
    if (dg.moved) { state.dragEnd = Date.now(); saveRank(dg.tr.dataset.id); }
  };
  tbodyP.addEventListener('pointerup', endDrag);
  tbodyP.addEventListener('pointercancel', endDrag);

  async function saveRank(focusId) {
    const rows = $$('tr[data-id]', tbodyP);
    rows.forEach((r, i) => { const b = $('.rk', r); if (b) b.textContent = i + 1; });
    const ids = rows.map(r => +r.dataset.id);
    // 화면 데이터도 바로 고쳐 둔다 (다음 새로고침 전까지)
    const slots = ids.map(id => +projById(id).sort_order).sort((a, b) => a - b);
    ids.forEach((id, i) => { projById(id).sort_order = slots[i]; });
    try {
      await api('project.reorder', { ids: ids });
      await load(true);
      const b = focusId && $('tr[data-id="' + focusId + '"] [data-drag]', tbodyP);
      if (b && document.activeElement === document.body) b.focus();
    } catch (err) { toast(err.message, true); load(); }
  }

  state.projSort = store.get('projSort', 'rank');
  $('#projSort').value = state.projSort;
  $('#projSort').addEventListener('change', e => { state.projSort = e.target.value; store.set('projSort', state.projSort); renderProjects(); });

  // 최근 기록
  $('#feed').addEventListener('click', e => {
    const b = e.target.closest('[data-edit]');
    if (!b) return;
    const a = state.data.feed.find(x => +x.id === +b.dataset.edit);
    if (a) openActivity(a);
  });

  // 메모
  $('#memoForm').addEventListener('submit', async e => {
    e.preventDefault();
    const inp = $('#memoInput'), body = inp.value.trim();
    if (!body) return;
    await busy($('#memoForm button[type=submit]'), async () => {
      try {
        await api('note.save', { body: body });
        inp.value = '';
        await load();
      } catch (err) { toast(err.message, true); }
    });
  });

  $('#memos').addEventListener('click', async e => {
    const li = e.target.closest('li[data-id]');
    if (!li) return;
    const id = li.dataset.id, n = state.data.notes.find(x => +x.id === +id);
    const op = e.target.dataset.op;
    try {
      if (op === 'pin') { await api('note.pin', { id: id, value: !+n.pinned }); load(); }
      else if (op === 'done') { await api('note.done', { id: id, value: true }); toast(T('메모를 완료했습니다.')); load(); }
      else if (op === 'del') { if (confirm(T('이 메모를 삭제할까요?'))) { await api('note.delete', { id: id }); load(); } }
      else if (e.target.classList.contains('body')) { editMemo(li, n); }
    } catch (err) { toast(err.message, true); }
  });
  $('#memos').addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.classList.contains('body')) {
      e.preventDefault();
      const li = e.target.closest('li'); editMemo(li, state.data.notes.find(x => +x.id === +li.dataset.id));
    }
  });

  function editMemo(li, n) {
    const span = $('.body', li);
    const ta = document.createElement('textarea');
    ta.className = 'textarea edit';
    ta.value = n.body;
    ta.setAttribute('aria-label', T('메모 고치기'));
    span.replaceWith(ta);
    ta.focus();
    let done = false;
    const finish = async save => {
      if (done) return; done = true;
      const v = ta.value.trim();
      if (save && v && v !== n.body) {
        try { await api('note.save', { id: n.id, body: v }); } catch (err) { toast(err.message, true); }
      }
      load();
    };
    ta.addEventListener('keydown', ev => {
      if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); finish(true); }
      if (ev.key === 'Escape') { ev.preventDefault(); finish(false); }
    });
    ta.addEventListener('blur', () => finish(true));
  }

  /* ---------- 자동 새로고침: 1분마다, 대화상자가 열려 있거나 편집 중이면 건너뜀 ---------- */
  setInterval(() => {
    if (document.hidden || dlgA.open || dlgP.open || state.drag || document.querySelector('.memos .edit')) { renderTop(); return; }
    load(true);
  }, 60000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden && !dlgA.open && !dlgP.open) load(true); });

  if (/welcome=1/.test(location.search)) {
    history.replaceState(null, '', location.pathname);
    setTimeout(() => toast(T('비밀번호를 저장했습니다. 상단에서 이 브라우저의 PC를 지정해 두세요.')), 300);
  }

  renderTop();
  load();
})();
