<?php
/**
 * UDeck - 메인 보드
 * 화면 틀만 서버에서 그리고, 내용은 board.js가 api.php에서 받아 채운다.
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_partials.php';

udeck_db();
require_login_page();

page_head('UDeck');
?>
<body>
<header class="topbar">
  <a class="brand" href="<?= e(base_path()) ?>"><?= logo_mark() ?><span class="wordmark">UDeck</span></a>
  <div class="now" id="now"></div>
  <div class="syncs" id="syncs" aria-label="<?= te('동기화 상태') ?>"></div>
  <div class="actions">
    <label class="here"><span><?= te('이 브라우저') ?></span>
      <select class="select" id="herePc" aria-label="<?= te('이 브라우저를 쓰는 PC') ?>"></select>
    </label>
    <button class="btn btn-ghost" type="button" id="btnAddActivity"><?= te('작업 기록 추가') ?></button>
    <a class="btn btn-ghost" href="settings.php"><?= te('설정') ?></a>
    <button class="btn btn-ghost" type="button" id="btnTheme" aria-label="<?= te('화면 테마 바꾸기') ?>"><?= te('테마') ?></button>
    <form method="post" action="logout.php" style="margin:0">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button class="btn btn-ghost" type="submit"><?= te('로그아웃') ?></button>
    </form>
    <?= help_button() ?>
  </div>
</header>

<nav class="boardtabs" role="tablist" aria-label="<?= te('보드') ?>">
  <button type="button" role="tab" data-board="list"><?= te('목록판') ?></button>
  <button type="button" role="tab" data-board="status"><?= te('현황판') ?></button>
  <button type="button" role="tab" data-board="black"><?= te('칠판') ?></button>
</nav>

<div class="board">
  <aside class="rail" data-board="list" aria-label="<?= te('목록판') ?>">
    <p class="col-h"><?= te('목록판') ?></p>
    <section class="sec" aria-labelledby="h-agenda">
      <div class="sec-h"><h2 id="h-agenda"><?= te('일정') ?></h2><a class="link" href="https://calendar.google.com/" target="_blank" rel="noopener"><?= te('캘린더 열기') ?></a></div>
      <div class="tabs" role="tablist" aria-label="<?= te('일정 범위') ?>">
        <button type="button" role="tab" data-range="days"><?= te('오늘·내일') ?></button>
        <button type="button" role="tab" data-range="week"><?= te('이번 주') ?></button>
        <button type="button" role="tab" data-range="month"><?= te('이번 달') ?></button>
      </div>
      <div class="sec-b scroll" id="agenda" role="tabpanel" aria-labelledby="h-agenda"></div>
    </section>

    <section class="sec" aria-labelledby="h-tasks">
      <div class="sec-h"><h2 id="h-tasks"><?= te('할 일') ?></h2>
        <div class="tools">
          <select class="select mini" id="taskList" aria-label="<?= te('할 일 목록 선택') ?>"><option value=""><?= te('모든 목록') ?></option></select>
          <a class="link" href="https://tasks.google.com/" target="_blank" rel="noopener"><?= te('Tasks 열기') ?></a>
        </div>
      </div>
      <div class="sec-b scroll scroll-tasks" id="tasks"></div>
    </section>

    <section class="sec" aria-labelledby="h-mail">
      <div class="sec-h"><h2 id="h-mail"><?= te('메일') ?></h2><a class="link" id="mailLink" href="https://mail.google.com/" target="_blank" rel="noopener"><?= te('Gmail 열기') ?></a></div>
      <div class="sec-b scroll scroll-mails" id="mails"></div>
    </section>

  </aside>

  <main class="main" data-board="status" aria-label="<?= te('현황판') ?>">
    <p class="col-h"><?= te('현황판') ?></p>
    <section class="sec" aria-labelledby="h-matrix">
      <div class="sec-h">
        <h2 id="h-matrix"><?= te('작업공간') ?></h2>
        <div class="tools">
          <div class="legend" aria-hidden="true"><?= te('최근 활동') ?>
            <span class="ramp"><i style="--a:70%"></i><i style="--a:40%"></i><i style="--a:18%"></i><i style="--a:4%"></i></span>
            <?= te('오래됨') ?></div>
          <div class="mx-nav" id="mxNav" hidden>
            <button type="button" class="btn btn-ghost" id="mxPrev" aria-label="<?= te('이전 도구') ?>" title="<?= te('이전 도구') ?>">◀</button>
            <button type="button" class="btn btn-ghost" id="mxNext" aria-label="<?= te('다음 도구') ?>" title="<?= te('다음 도구') ?>">▶</button>
          </div>
        </div>
      </div>
      <div class="matrix-wrap">
        <div class="mx-scroll" id="mxScroll">
          <div class="matrix" id="matrix"><div class="loading"><?= te('불러오는 중') ?></div></div>
        </div>
        <div class="detail" id="detail" hidden></div>
      </div>
    </section>

    <section class="sec" aria-labelledby="h-proj">
      <div class="sec-h">
        <h2 id="h-proj"><?= te('프로젝트|제목') ?></h2>
        <div class="tools">
          <label class="check" style="font-size:12.5px"><input type="checkbox" id="showDone"> <?= te('완료 포함') ?></label>
          <button class="btn btn-ghost" type="button" id="btnAddProject"><?= te('프로젝트 추가') ?></button>
        </div>
      </div>
      <div class="sec-b" style="overflow-x:auto">
        <table class="projects">
          <thead><tr><th style="width:24%"><?= te('프로젝트') ?></th><th style="width:34%"><?= te('작업 흐름') ?></th><th><?= te('다음 할 일') ?></th><th style="width:70px"><?= te('마감') ?></th></tr></thead>
          <tbody id="projRows"></tbody>
        </table>
        <p class="empty" id="projEmpty" hidden><?= te('프로젝트가 없습니다. 프로젝트를 추가하면 작업 기록이 프로젝트별로 모입니다.') ?></p>
      </div>
    </section>

    <section class="sec" aria-labelledby="h-feed">
      <div class="sec-h"><h2 id="h-feed"><?= te('최근 기록') ?></h2><span class="sub"><?= te('모든 PC, 최근 30건') ?></span></div>
      <div class="sec-b">
        <ul class="feed" id="feed"></ul>
      </div>
    </section>
  </main>

  <aside class="memo-col" data-board="black" aria-label="<?= te('칠판') ?>">
    <p class="col-h"><?= te('칠판') ?></p>
    <section class="sec memo-sec" aria-labelledby="h-memo">
      <div class="sec-h"><h2 id="h-memo"><?= te('메모') ?></h2><span class="sub" id="memoCount"></span></div>
      <div class="sec-b memo-body">
        <form class="memo-in" id="memoForm">
          <label class="sr" for="memoInput"><?= te('새 메모') ?></label>
          <input class="input" id="memoInput" placeholder="<?= te('떠오른 것을 바로 적어 두세요') ?>" maxlength="2000">
          <button class="btn btn-ghost" type="submit" data-busy="<?= te('추가 중') ?>"><?= te('추가') ?></button>
        </form>
        <ul class="memos" id="memos"></ul>
      </div>
    </section>

    <section class="sec ai-sec" aria-labelledby="h-ai">
      <div class="sec-h">
        <h2 id="h-ai"><?= te('AI 비서') ?></h2>
        <button type="button" class="btn btn-text" id="aiRefresh"><?= te('새로 고침') ?></button>
      </div>
      <div class="sec-b" id="aiBox" aria-live="polite"></div>
    </section>
  </aside>
</div>

<!-- 작업 기록 추가·수정 -->
<dialog class="dlg" id="dlgActivity" aria-labelledby="dlgActTitle">
  <form method="dialog" id="formActivity">
    <div class="dlg-h"><h2 id="dlgActTitle"><?= te('작업 기록 추가') ?></h2><button class="x" type="button" data-close aria-label="<?= te('닫기') ?>">×</button></div>
    <div class="dlg-b">
      <input type="hidden" name="id">
      <div class="field"><span class="lbl">PC</span><div class="chips" id="actPc"></div></div>
      <div class="field"><span class="lbl"><?= te('도구') ?></span><div class="chips" id="actTool"></div></div>
      <div class="grid2">
        <div class="field">
          <label for="actProject"><?= te('프로젝트') ?></label>
          <select class="select" id="actProject" name="project_id"></select>
        </div>
        <div class="field">
          <label for="actStage"><?= te('단계') ?> <span class="opt"><?= te('선택') ?></span></label>
          <input class="input" id="actStage" name="stage" list="stageList" maxlength="40" placeholder="<?= te('초안, 수정, 검토') ?>">
          <datalist id="stageList"><option><?= te('초안') ?></option><option><?= te('수정') ?></option><option><?= te('검토') ?></option><option><?= te('학습') ?></option><option><?= te('분석') ?></option><option><?= te('개발') ?></option><option><?= te('제작') ?></option><option><?= te('설계') ?></option><option><?= te('마무리') ?></option></datalist>
        </div>
      </div>
      <div class="field">
        <label for="actSummary"><?= te('무엇을 했나요') ?></label>
        <textarea class="textarea" id="actSummary" name="summary" maxlength="1000" required placeholder="<?= te('예: 선행연구 절 2차 수정, 참고문헌 정리 중') ?>"></textarea>
      </div>
      <div class="field">
        <label for="actNext"><?= te('다음 할 일') ?> <span class="opt"><?= te('선택, 프로젝트의 다음 할 일로 반영') ?></span></label>
        <input class="input" id="actNext" name="next_action" maxlength="300">
      </div>
      <div class="grid2">
        <div class="field">
          <label for="actCwd"><?= te('작업 폴더') ?> <span class="opt"><?= te('선택') ?></span></label>
          <input class="input" id="actCwd" name="cwd" maxlength="500" placeholder="D:\Work\Grant_2027">
        </div>
        <div class="field">
          <label for="actAt"><?= te('시각') ?></label>
          <input class="input" id="actAt" name="at" type="datetime-local" required>
        </div>
      </div>
    </div>
    <div class="dlg-f">
      <button class="btn btn-danger" type="button" id="actDelete" hidden><?= te('삭제') ?></button>
      <span class="sp"></span>
      <button class="btn btn-ghost" type="button" data-close><?= te('취소') ?></button>
      <button class="btn btn-primary" type="submit" data-busy="<?= te('저장 중') ?>"><?= te('기록 저장') ?></button>
    </div>
  </form>
</dialog>

<!-- 프로젝트 추가·수정 -->
<dialog class="dlg" id="dlgProject" aria-labelledby="dlgProjTitle">
  <form method="dialog" id="formProject">
    <div class="dlg-h"><h2 id="dlgProjTitle"><?= te('프로젝트 추가') ?></h2><button class="x" type="button" data-close aria-label="<?= te('닫기') ?>">×</button></div>
    <div class="dlg-b">
      <input type="hidden" name="id">
      <div class="field">
        <label for="pjName"><?= te('이름') ?></label>
        <input class="input" id="pjName" name="name" maxlength="100" required>
      </div>
      <div class="grid3">
        <div class="field">
          <label for="pjCat"><?= te('분류') ?></label>
          <input class="input" id="pjCat" name="category" list="catList" maxlength="30">
          <datalist id="catList"><option><?= te('연구') ?></option><option><?= te('개발') ?></option><option><?= te('교육') ?></option><option><?= te('강의') ?></option><option><?= te('행정') ?></option><option><?= te('개인') ?></option></datalist>
        </div>
        <div class="field">
          <label for="pjPri"><?= te('우선순위') ?></label>
          <select class="select" id="pjPri" name="priority"><option value="1"><?= te('높음') ?></option><option value="2" selected><?= te('보통') ?></option><option value="3"><?= te('낮음') ?></option></select>
        </div>
        <div class="field">
          <label for="pjStatus"><?= te('상태') ?></label>
          <select class="select" id="pjStatus" name="status"><option value="active"><?= te('진행 중') ?></option><option value="paused"><?= te('보류') ?></option><option value="done"><?= te('완료') ?></option><option value="archived"><?= te('보관') ?></option></select>
        </div>
      </div>
      <div class="grid2">
        <div class="field">
          <label for="pjWs"><?= te('주 작업공간') ?></label>
          <select class="select" id="pjWs" name="workspace_id"></select>
        </div>
        <div class="field">
          <label for="pjFolder"><?= te('작업 폴더 이름') ?> <span class="opt"><?= te('선택') ?></span></label>
          <input class="input" id="pjFolder" name="folder_path" maxlength="200" placeholder="Grant_2027">
        </div>
      </div>
      <div class="field">
        <span class="lbl"><?= te('마감') ?></span>
        <div class="due-row">
          <div class="seg" role="radiogroup" aria-label="<?= te('마감 종류') ?>">
            <label><input type="radio" name="due_type" value="none" checked><span><?= te('마감 없음') ?></span></label>
            <label><input type="radio" name="due_type" value="self"><span><?= te('자체 마감') ?></span></label>
            <label class="fixed"><input type="radio" name="due_type" value="fixed"><span><?= te('정해진 마감') ?></span></label>
          </div>
          <label class="sr" for="pjDue"><?= te('마감 날짜') ?></label>
          <input class="input" id="pjDue" name="due_date" type="date" disabled>
        </div>
        <p class="hint"><?= te('정해진 마감은 제출기한처럼 반드시 지켜야 하는 날짜입니다. 보드에 빨간색으로 표시됩니다.') ?></p>
      </div>
      <p class="hint" style="margin:-6px 0 14px;color:var(--muted);font-size:12.5px"><?= te('주 작업공간은 이 프로젝트를 주로 진행하는 PC입니다. 목록에 없는 PC는 설정에서 추가합니다. 작업 폴더 이름을 적어 두면 그 폴더에서 한 작업이 이 프로젝트로 자동 분류됩니다.') ?></p>
      <div class="field">
        <label for="pjNext"><?= te('다음 할 일') ?></label>
        <input class="input" id="pjNext" name="next_action" maxlength="300">
      </div>
      <div class="field" id="pjHandoffWrap" hidden>
        <span class="lbl">HANDOFF.md <span class="opt" id="pjHandoffAt"></span></span>
        <div class="handoff" id="pjHandoff"></div>
      </div>
    </div>
    <div class="dlg-f">
      <button class="btn btn-danger" type="button" id="pjDelete" hidden><?= te('삭제') ?></button>
      <span class="sp"></span>
      <button class="btn btn-ghost" type="button" data-close><?= te('취소') ?></button>
      <button class="btn btn-primary" type="submit" data-busy="<?= te('저장 중') ?>"><?= te('프로젝트 저장') ?></button>
    </div>
  </form>
</dialog>

<div class="toast" id="toast" role="status" hidden></div>

<script>
window.UDECK = {
  api: <?= json_encode(base_path() . 'api.php') ?>,
  csrf: <?= json_encode(csrf_token()) ?>,
  lang: <?= json_encode(udeck_lang()) ?>,
  i18n: <?= json_encode((object)udeck_js_dict(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
};
</script>
<script src="<?= e(asset('assets/js/board.js')) ?>"></script>
</body>
</html>
