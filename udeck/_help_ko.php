<?php /* UDeck 도움말 본문 (한국어). help.php가 불러온다. */ ?>
<div class="docwrap">
  <nav class="toc" aria-label="목차">
    <p class="toc-h">목차</p>
    <ol>
      <li><a href="#overview">UDeck 개요</a></li>
      <li><a href="#start">처음 설정</a></li>
      <li><a href="#board">메인 화면 쓰는 법</a></li>
      <li><a href="#records">작업 기록</a></li>
      <li><a href="#google">구글 연동 (Apps Script)</a></li>
      <li><a href="#agent">PC 자동 기록 (install.cmd)</a></li>
      <li><a href="#handoff">HANDOFF.md</a></li>
      <li><a href="#settings">설정 화면</a></li>
      <li><a href="#trouble">문제 해결</a></li>
      <li><a href="#backup">백업과 보안</a></li>
    </ol>
  </nav>

  <article class="doc">
    <h1>UDeck 도움말</h1>
    <p class="doc-lead">여러 PC와 여러 AI 도구에서 하는 작업을 한 화면에서 확인하고 이어 가기 위한 작업 데스크 보드다. 구글 일정·할 일·메일, PC별 작업 기록, 프로젝트, 메모를 함께 보여 준다.</p>

    <!-- 1 -->
    <section id="overview">
      <h2>1. UDeck 개요</h2>
      <h3>화면 구성</h3>
      <table class="tbl">
        <thead><tr><th style="width:18%">판</th><th>영역</th><th>내용</th></tr></thead>
        <tbody>
          <tr><td>목록판</td><td>일정, 할 일, 메일</td><td>구글에서 10분마다 가져와 제목과 목록만 보여 준다. 고치거나 자세히 볼 때는 각 영역의 "열기" 링크로 구글 화면을 연다.</td></tr>
          <tr><td>현황판</td><td>작업공간, 프로젝트, 최근 기록</td><td>PC별·도구별 작업 현황. 직접 입력과 자동 기록으로 채워진다.</td></tr>
          <tr><td>칠판</td><td>메모, AI 비서</td><td>UDeck에 직접 적는 빠른 메모와, 오늘 챙길 일을 정리해 주는 안내.</td></tr>
        </tbody>
      </table>
      <ul>
        <li>16인치 노트북 폭(약 1200px)까지는 3열을 유지하고, 폭이 줄면 현황판이 먼저 좁아진다.</li>
        <li>그보다 좁으면 2열이 된다. 왼쪽에 목록판, 오른쪽 위에 칠판(메모와 AI 비서를 나란히), 그 아래에 현황판이 온다.</li>
        <li>휴대폰(760px 이하)에서는 위쪽의 "목록판, 현황판, 칠판" 탭으로 판을 하나씩 본다. 마지막으로 본 판을 기억한다.</li>
      </ul>

      <h3>데이터가 들어오는 길</h3>
      <table class="tbl">
        <thead><tr><th style="width:28%">보내는 곳</th><th>보내는 것</th><th style="width:22%">주기</th></tr></thead>
        <tbody>
          <tr><td>Google Apps Script</td><td>캘린더 일정, Tasks 할 일, Gmail 메일 제목</td><td>10분마다</td></tr>
          <tr><td>Claude Code hook</td><td>작업 폴더, 마지막 요청, 마지막 답변 앞부분</td><td>응답이 끝날 때마다</td></tr>
          <tr><td>PC 에이전트</td><td>접속 신호, Codex 세션, HANDOFF.md</td><td>15분마다</td></tr>
          <tr><td>보드 직접 입력</td><td>작업 기록, 프로젝트, 메모</td><td>입력할 때</td></tr>
        </tbody>
      </table>
    </section>

    <!-- 2 -->
    <section id="start">
      <h2>2. 처음 설정</h2>
      <ol class="steps">
        <li><b>이 브라우저의 PC 지정.</b> 상단 PC 선택 상자에서 지금 쓰는 PC를 고른다. PC마다, 브라우저마다 한 번 지정한다. 작업 기록을 추가할 때 이 PC가 기본으로 선택된다. 휴대폰은 "선택 안 함"으로 둔다.</li>
        <li><b>PC와 도구 정리.</b> <a href="settings.php">설정</a>에서 PC 이름, 컴퓨터 이름(Windows 컴퓨터 이름), 색, 용도를 확인한다. 쓰는 도구를 추가한다.</li>
        <li><b>프로젝트 등록.</b> 보드의 "프로젝트 추가"로 진행 중인 프로젝트를 등록한다. <b>작업 폴더 이름</b>을 꼭 적는다. 자동 기록과 HANDOFF.md가 이 이름으로 프로젝트를 찾는다.</li>
        <li><b>구글 연동.</b> <a href="#google">5장</a>에 따라 Apps Script를 연결한다.</li>
        <li><b>PC 자동 기록 설치.</b> <a href="#agent">6장</a>에 따라 PC마다 install.cmd를 실행한다.</li>
      </ol>
    </section>

    <!-- 3 -->
    <section id="board">
      <h2>3. 메인 화면 쓰는 법</h2>
      <h3>일정</h3>
      <ul>
        <li>"오늘·내일", "이번 주", "이번 달" 탭으로 범위를 바꾼다. 이번 주는 월요일부터 일요일까지다.</li>
        <li>지난 일정은 흐리게 보이고, 다음 일정 위에 빨간 선이 표시된다.</li>
        <li>이번 주와 이번 달 탭은 열 때 오늘 위치로 스크롤된다. 마지막에 고른 탭은 브라우저가 기억한다.</li>
        <li>구글 캘린더 화면에서 체크되어 표시 중인 캘린더만 들어온다.</li>
      </ul>
      <h3>할 일</h3>
      <ul>
        <li>Google Tasks의 목록별로 묶어 보여 준다. 오른쪽 위 선택 상자로 한 목록만 볼 수 있다.</li>
        <li>지났거나 오늘이 마감인 항목은 붉게 표시된다. 완료 처리는 Google Tasks에서 한다.</li>
      </ul>
      <h3>메일</h3>
      <ul>
        <li>기본 기준은 받은편지함에서 읽지 않았거나 별표 표시된 최근 7일 메일이다. 별표 메일은 ★로 표시된다.</li>
        <li>본문은 가져오지 않는다. 메일을 읽을 때는 "Gmail 열기"를 누른다.</li>
        <li>기준은 Apps Script의 <code>mailQuery</code>에서 바꾼다. 예: 별표 메일만 → <code>in:inbox is:starred</code></li>
      </ul>
      <h3>메모</h3>
      <ul>
        <li>입력 후 Enter로 추가한다. 글자를 누르면 고치고, Enter로 저장, Esc로 취소한다.</li>
        <li>마우스를 올리면 고정, 완료, 삭제 버튼이 나온다. 고정한 메모는 맨 위에 남는다.</li>
      </ul>
      <h3>AI 비서</h3>
      <ul>
        <li>보드에 있는 데이터만으로 정해진 규칙에 따라 안내를 만든다. 외부 AI API를 부르지 않으므로 비용이 들지 않는다.</li>
        <li>급한 일: 기한이 지났거나 오늘까지인 할 일, 3시간 안의 일정, 14일 안의 정해진 마감, 3일 안의 자체 마감, 별표 메일.</li>
        <li>오늘 이어서: 프로젝트가 지정된 가장 최근 작업(어느 PC, 어느 도구, 어느 프로젝트)과 그 프로젝트의 다음 할 일. 지금 PC가 다르면 HANDOFF.md 확인을 권한다. 7일 넘게 기록이 없는 진행 중 프로젝트도 알려 준다.</li>
        <li>맨 아래에 속담이나 짧은 한 줄을 보여 준다.</li>
        <li>15분마다 새로 계산한다. "새로 고침"을 누르면 바로 다시 계산하고 한 줄도 바꾼다.</li>
      </ul>
      <h3>작업공간</h3>
      <ul>
        <li>행은 작업공간(PC 또는 담당자), 그 오른쪽 칸은 그 PC에서 작업한 프로젝트다. 최근에 작업한 프로젝트가 앞에 온다. 같은 프로젝트를 여러 PC에서 했으면 각 PC 행에 모두 나온다.</li>
        <li>칸에는 프로젝트 이름, 마지막 작업 내용, 사용한 도구(짧은 이름 아이콘, 예: Codex, GPT, Claude, Claude C, Gemini, Antigravity), 진행률(막대와 %), 마지막 작업 시각, 7일 활동 막대가 보인다. 프로젝트가 지정되지 않은 최근 14일 기록은 "미분류" 칸으로 모인다.</li>
        <li>칸이 넘치면 제목 오른쪽의 ◀ ▶ 버튼(또는 가로 스크롤)으로 넘겨 본다. PC 이름 열은 고정된다.</li>
        <li>최근에 작업한 칸일수록 색이 진하다(반감기 3시간). 1시간 안의 작업은 경과 시간이 PC 색으로 강조된다.</li>
        <li>칸을 누르면 상세가 펼쳐진다. 도구별 최근 기록과 고치기, 이어서 기록, 폴더 경로 복사, 원격 데스크톱 열기, 프로젝트 지정을 할 수 있다.</li>
        <li>기록이 없는 PC는 빈 칸을 눌러 기록을 추가한다.</li>
      </ul>
      <h3>프로젝트</h3>
      <ul>
        <li>열: 순위(#), 프로젝트, 장치(작업한 PC, 굵은 글씨가 가장 최근), 도구, 진행률, 다음 할 일, 마감.</li>
        <li>정렬은 중요도순, 마감일순, 등록순 중에서 고른다. 고른 정렬은 브라우저에 기억된다.</li>
        <li>중요도순에서 맨 왼쪽 번호를 끌어 위아래로 옮기면 중요도 순서가 바뀐다. 번호에 초점을 두고 위·아래 화살표 키로도 옮길 수 있다. 번호는 어느 정렬에서나 중요도 순위를 보여 준다.</li>
        <li>진행률은 그 프로젝트의 작업 기록에 가장 최근에 적은 값이다.</li>
        <li>행을 누르면 고치기 창이 열린다. 상태를 "완료"로 바꾸면 목록에서 빠지고, "완료 포함"을 체크하면 다시 보인다.</li>
        <li>주 작업공간은 이 프로젝트를 주로 진행하는 PC다. 이름 아래 "주: PC 이름"으로 표시된다.</li>
        <li>마감은 세 가지 중에서 고른다. 마감 없음, 자체 마감(스스로 정한 목표일, 황토색 D-day), 정해진 마감(제출기한처럼 반드시 지켜야 하는 날, 빨간 바탕 D-day). 정해진 마감은 14일 전부터 AI 비서의 급한 일에도 나온다.</li>
      </ul>
      <h3>최근 기록</h3>
      <p>모든 PC의 작업 기록을 최근 순으로 30건 보여 준다. 마우스를 올리면 "고치기"가 나오고, 고치기 창에서 삭제할 수 있다.</p>
    </section>

    <!-- 4 -->
    <section id="records">
      <h2>4. 작업 기록</h2>
      <h3>직접 입력</h3>
      <p>상단 "작업 기록 추가"를 누른다. PC, 도구, 프로젝트, 단계, 한 일, 진행률, 다음 할 일, 작업 폴더, 시각을 적는다. "다음 할 일"을 적으면 프로젝트의 다음 할 일도 함께 바뀐다.</p>
      <p>진행률은 막대를 끌거나 숫자(0~100)를 직접 적는다. 프로젝트를 고르면 그 프로젝트의 최근 진행률이 미리 채워진다. 기록하지 않으려면 "비우기"를 누른다.</p>
      <h3>자동 기록과 직접 입력의 구분</h3>
      <table class="tbl">
        <thead><tr><th style="width:34%">도구</th><th>기록 방식</th></tr></thead>
        <tbody>
          <tr><td>Claude Code 앱</td><td>자동. 응답이 끝날 때마다 기록된다.</td></tr>
          <tr><td>ChatGPT 앱의 Codex</td><td>자동. 15분마다 바뀐 세션을 기록한다.</td></tr>
          <tr><td>ChatGPT 앱의 일반 대화</td><td>대화가 OpenAI 서버에만 있어 자동으로 읽을 수 없다. HANDOFF.md 또는 직접 입력으로 남긴다.</td></tr>
          <tr><td>AI 웹, Perplexity 등</td><td>직접 입력.</td></tr>
        </tbody>
      </table>
      <h3>프로젝트 자동 분류</h3>
      <p>자동 기록에 들어 있는 작업 폴더 경로를 <b>가장 안쪽 폴더부터</b> 바깥쪽으로 올라가며, 프로젝트의 "작업 폴더 이름"과 처음 맞는 것을 고른다.</p>
      <table class="tbl">
        <thead><tr><th>작업 폴더</th><th>프로젝트 작업 폴더 이름</th><th>결과</th></tr></thead>
        <tbody>
          <tr><td><code>D:\Work\Grant_2027\docs</code></td><td><code>Grant_2027</code></td><td>Grant 프로젝트</td></tr>
          <tr><td><code>E:\Projects\Research\Gait_DL\src</code></td><td><code>Research/Gait_DL</code></td><td>여러 단계로 적어도 된다</td></tr>
          <tr><td><code>D:\Work</code></td><td><code>Work</code></td><td>상위 폴더 프로젝트 (하위 프로젝트 기록은 가져가지 않는다)</td></tr>
        </tbody>
      </table>
      <p>맞는 프로젝트가 없으면 "미분류"가 된다. 작업공간의 "미분류" 칸 상세에서 프로젝트를 한 번 지정하면 그 폴더 이름이 프로젝트에 저장되어 이후 같은 폴더의 기록은 자동 분류된다. 폴더 이름만 적어 두면 PC마다 경로가 달라도 같은 프로젝트로 묶인다.</p>
    </section>

    <!-- 5 -->
    <section id="google">
      <h2>5. 구글 연동 (Apps Script)</h2>
      <p>Apps Script가 구글 계정 권한으로 10분마다 일정·할 일·메일을 읽어 UDeck으로 보낸다. 서버에는 구글 비밀번호나 토큰을 저장하지 않는다.</p>
      <h3>연결 순서</h3>
      <ol class="steps">
        <li><a href="settings.php#h-token">설정 → 연결 키</a>에서 이름 <code>Apps Script</code>, 용도 <b>구글 연동</b>으로 키를 만들고 복사한다. 키는 만든 직후 한 번만 보인다.</li>
        <li><a href="https://script.google.com" target="_blank" rel="noopener">script.google.com</a>에서 <b>새 프로젝트</b>를 만든다. 오른쪽 위 프로필이 데이터를 가져올 계정인지 먼저 확인한다.</li>
        <li>기본 코드를 지우고 아래 코드를 붙여 넣는다. 맨 위 <code>send</code>에서 이 계정이 보낼 항목을 정한다.</li>
        <li>왼쪽 "서비스" 옆 <b>+</b> → <b>Google Tasks API</b>를 추가한다.</li>
        <li>톱니바퀴(프로젝트 설정)에서 시간대를 <b>서울</b>로 정하고, 맨 아래 <b>스크립트 속성</b>에 두 값을 넣는다.</li>
        <li><code>Ctrl + S</code>로 저장한다. 위쪽 함수 목록에서 <code>testSync</code>를 고르고 ▷ 실행을 누른다.</li>
        <li>권한 승인 창에서 계정을 고른다. "확인하지 않은 앱"이 나오면 <b>고급 → UDeck Sync(으)로 이동</b> → 허용을 누른다. 직접 만든 스크립트라서 나오는 안내다.</li>
        <li>실행 로그에 <code>"ok":true</code>와 계정, 건수가 나오면 성공이다.</li>
        <li>함수 목록에서 <code>installTrigger</code>를 실행하면 10분마다 자동 전송이 시작된다. 중지는 <code>removeTrigger</code>.</li>
      </ol>
      <?= code("UDECK_URL\t{$ingestUrl}\nUDECK_TOKEN\tudk_... (1번에서 복사한 키)", '스크립트 속성') ?>
      <?php if ($gasCode): ?>
        <details class="gas">
          <summary>Apps Script 코드 보기 (UDeck_Sync.gs)</summary>
          <?= code($gasCode, 'UDeck_Sync.gs') ?>
        </details>
      <?php else: ?>
        <p class="note">코드 파일은 동기화 폴더의 <code>UDeck\gas\UDeck_Sync.gs</code>에 있다.</p>
      <?php endif; ?>

      <h3>계정을 나눠 쓰는 경우</h3>
      <p>Apps Script는 만든 계정의 데이터만 읽고, 만든 뒤에는 실행 계정을 바꿀 수 없다. 메일·캘린더·할 일이 서로 다른 계정에 있으면 <b>계정마다 프로젝트를 하나씩</b> 만들고 <code>send</code>만 다르게 한다. UDeck은 계정별로 나눠 저장하므로 서로 덮어쓰지 않는다.</p>
      <?= code("// 개인 Gmail 계정: 메일만\nsend: { events: false, tasks: false, mails: true },\n\n// 학교 계정: 일정과 할 일\nsend: { events: true, tasks: true, mails: false },", 'CONFIG.send 예') ?>
      <p>학교 Workspace 계정은 관리자 정책으로 실행이 막힐 수 있다. 그때는 학교 캘린더를 개인 계정에 공유하거나 메일을 전달하도록 설정하고, 개인 계정 스크립트로 가져온다.</p>

      <h3>주요 설정 (코드 맨 위 CONFIG)</h3>
      <table class="tbl">
        <thead><tr><th style="width:22%">항목</th><th style="width:22%">기본값</th><th>설명</th></tr></thead>
        <tbody>
          <tr><td><code>send</code></td><td>모두 true</td><td>이 계정이 보낼 항목</td></tr>
          <tr><td><code>eventPadDays</code></td><td>7</td><td>이번 달 일정에 앞뒤로 더 보낼 날 수</td></tr>
          <tr><td><code>mailQuery</code></td><td>읽지 않음 또는 별표, 최근 7일</td><td>Gmail 검색창과 같은 문법</td></tr>
          <tr><td><code>mailMax</code></td><td>30</td><td>보낼 메일 스레드 수</td></tr>
          <tr><td><code>excludeCalendars</code></td><td>없음</td><td>제외할 캘린더 이름. 예: <code>['대한민국의 휴일']</code></td></tr>
        </tbody>
      </table>
      <p>코드를 새 버전으로 바꿀 때는 기존 <code>send</code> 값을 메모해 두었다가 다시 맞춘다. 스크립트 속성과 트리거는 그대로 둔다.</p>
      <p>수집 결과가 0건이면 함수 <code>diagnoseUDeck</code>을 실행해 실행 계정, 캘린더별 표시 여부와 일정 수, 할 일 목록별 개수, 메일 개수를 확인한다.</p>
    </section>

    <!-- 6 -->
    <section id="agent">
      <h2>6. PC 자동 기록 (install.cmd)</h2>
      <p>동기화 폴더의 <code>UDeck\agent</code>에 있는 작은 스크립트들이다. 창이 뜨거나 계속 실행되는 프로그램이 아니라, 정해진 때에만 몇 초 실행되고 끝난다. PC마다 한 번 설치한다.</p>
      <table class="tbl">
        <thead><tr><th style="width:26%">파일</th><th>역할</th></tr></thead>
        <tbody>
          <tr><td><code>install.cmd</code></td><td>설치. 더블클릭</td></tr>
          <tr><td><code>status.cmd</code></td><td>상태 확인과 보낼 내용 미리보기</td></tr>
          <tr><td><code>uninstall.cmd</code></td><td>제거</td></tr>
          <tr><td><code>udeck_hook.py</code></td><td>Claude Code 응답이 끝날 때마다 실행</td></tr>
          <tr><td><code>udeck_agent.py</code></td><td>작업 스케줄러가 15분마다 실행</td></tr>
        </tbody>
      </table>

      <p>PC마다 설치한다. install.cmd 파일 하나가 아니라 <code>agent</code> 폴더 전체가 필요하다. 이 폴더가 Syncthing으로 동기화되어 있으면 각 PC의 동기화된 폴더에서 install.cmd를 실행한다.</p>

      <h3>설치 순서</h3>
      <ol class="steps">
        <li>준비: Python 3.8 이상.</li>
        <li><a href="settings.php#h-token">설정 → 연결 키</a>에서 이름 <code>노트북 자동 기록</code>처럼 정하고, 용도 <b>PC 작업 기록</b>, PC는 <b>그 PC</b>로 골라 키를 만든 뒤 <b>키 복사</b>를 누른다. 키는 PC마다 따로 만든다.</li>
        <li><code>UDeck\agent\install.cmd</code>를 더블클릭한다. 클립보드의 키를 자동으로 읽는다.</li>
        <li>연결 시험, Claude Code hook 추가, 작업 스케줄러 등록, 첫 실행, 시험 기록 전송이 차례로 진행된다.</li>
        <li>보드에서 그 PC 행에 시험 기록(Claude C 아이콘)이 보이면 성공이다. 시험 기록은 최근 기록의 고치기 → 삭제로 지운다.</li>
        <li>열려 있던 Claude Code 앱은 다시 시작해야 자동 기록이 적용된다.</li>
      </ol>

      <h3>기록되는 것과 기록되지 않는 것</h3>
      <ul>
        <li>보낸다: PC, 도구, 세션 ID, 작업 폴더 경로, 마지막 요청 앞 500자, 마지막 답변 앞 300자, 시각, HANDOFF.md 내용.</li>
        <li>보내지 않는다: 소스 코드, 데이터 파일, AI 답변 전문, 대화 전체.</li>
        <li>처음 설치할 때는 최근 24시간 안에 바뀐 Codex 세션만 보낸다.</li>
        <li>인터넷이 끊겼을 때 생긴 기록은 저장해 두었다가 다음 15분 실행 때 보낸다.</li>
      </ul>

      <h3>명령어 (PowerShell)</h3>
      <?= code("# 상태 확인\npython D:\\Work\\UDeck\\agent\\udeck_setup.py status\n\n# 보낼 내용 미리보기 (최근 7일, 전송 안 함)\npython D:\\Work\\UDeck\\agent\\udeck_setup.py diagnose\n\n# HANDOFF.md 감시 폴더 보기, 추가, 제거\npython D:\\Work\\UDeck\\agent\\udeck_setup.py watch\npython D:\\Work\\UDeck\\agent\\udeck_setup.py watch D:\\Projects\npython D:\\Work\\UDeck\\agent\\udeck_setup.py watch --remove D:\\Projects\n\n# 제거 (설정까지 지우려면 --purge)\npython D:\\Work\\UDeck\\agent\\udeck_setup.py uninstall", '경로는 그 PC의 동기화 폴더 위치에 맞춘다') ?>

      <h3>PC별 설정 파일</h3>
      <p>연결 키와 전송 상태는 동기화 폴더가 아닌 <code>%LOCALAPPDATA%\UDeck\config.json</code>에 저장되므로 PC끼리 섞이지 않는다. Microsoft Store판 Python은 이 파일을 <code>%LOCALAPPDATA%\Packages\PythonSoftwareFoundation.Python…\LocalCache\Local\UDeck\</code>에 저장할 수 있다.</p>
      <table class="tbl">
        <thead><tr><th style="width:22%">항목</th><th>설명</th></tr></thead>
        <tbody>
          <tr><td><code>watch_roots</code></td><td>HANDOFF.md를 찾을 폴더 목록. 기본은 agent 폴더의 두 단계 위(예: D:\Work). <code>watch</code> 명령으로 바꾸는 것이 편하다.</td></tr>
          <tr><td><code>watch_depth</code></td><td>찾을 깊이. 기본 3</td></tr>
          <tr><td><code>codex_tool</code>, <code>claude_tool</code></td><td>기록할 UDeck 도구 코드. 기본 <code>chatgpt_app</code>, <code>claude_code</code></td></tr>
        </tbody>
      </table>
    </section>

    <!-- 7 -->
    <section id="handoff">
      <h2>7. HANDOFF.md</h2>
      <p>프로젝트 폴더에 두는 인수인계 메모다. 다른 PC나 다른 AI 도구로 넘어가도 이 파일 하나로 맥락이 이어진다. 내용이 바뀌면 15분 안에 UDeck에 반영된다.</p>
      <h3>반영 조건</h3>
      <ul>
        <li>위치: 감시 폴더 아래 3단계 깊이 안. 예: <code>D:\Work\Grant_2027\HANDOFF.md</code></li>
        <li>연결: 파일이 있는 경로의 폴더 이름이 프로젝트의 "작업 폴더 이름"과 맞아야 한다(<a href="#records">4장 규칙</a>). 맞는 프로젝트가 없으면 보내지 않고 6시간마다 다시 확인한다.</li>
        <li>파일 이름은 <code>HANDOFF.md</code>(대소문자 무관). Syncthing 충돌 파일(<code>.sync-conflict</code>)은 읽지 않는다.</li>
      </ul>
      <h3>반영되는 곳</h3>
      <ul>
        <li>프로젝트의 <b>다음 할 일</b>: <code>다음 할 일: …</code> 줄, 또는 <code>## 다음 할 일</code> 제목 아래 첫 항목.</li>
        <li>프로젝트 고치기 창 아래쪽: HANDOFF.md 전체 내용과 갱신 시각.</li>
      </ul>
      <h3>작성 예</h3>
      <?= code("# HANDOFF\n## 현재 상태\n선행연구 절 2차 수정 완료. 참고문헌 정리 중.\n\n## 다음 할 일\n- 예산표 작성 후 연구진 이력 정리\n\n## 메모\n- 최근 5년 논문 위주로 정리\n- 마지막 작업: 노트북, Claude Code", 'HANDOFF.md') ?>
      <h3>AI가 직접 갱신하게 하기</h3>
      <p>프로젝트 폴더의 <code>CLAUDE.md</code>(Claude Code)와 <code>AGENTS.md</code>(Codex)에 다음을 넣어 두면 작업을 시작할 때 읽고, 마칠 때 갱신한다.</p>
      <?= code("## 작업 인수인계\n- 작업을 시작할 때 HANDOFF.md를 먼저 읽고 현재 상태와 다음 할 일을 확인한다.\n- 작업을 마칠 때 HANDOFF.md의 \"현재 상태\"와 \"다음 할 일\"을 갱신한다.\n- \"다음 할 일\"의 첫 항목에는 바로 이어서 할 일 하나를 적는다.\n- 메모에 마지막으로 작업한 PC와 도구를 적는다.", 'CLAUDE.md / AGENTS.md에 추가') ?>
      <p>ChatGPT 앱의 일반 대화처럼 자동 기록이 안 되는 작업은, 마칠 때 AI에게 "HANDOFF.md 내용을 정리해 줘"라고 요청한 뒤 결과를 파일에 붙여 넣는다.</p>
      <h3>최상위 폴더에 둘 때</h3>
      <p><code>Projects</code>, <code>Work</code> 같은 최상위 폴더에도 둘 수 있다. 이때는 작업 폴더 이름이 그 폴더 이름인 "전체 현황" 프로젝트를 하나 만들고, 그 폴더가 감시 폴더 안에 있어야 한다(필요하면 <code>watch</code> 명령으로 추가). 최상위 HANDOFF.md는 이 프로젝트에만 반영되고, 하위 프로젝트는 각자의 HANDOFF.md를 따른다. 프로젝트마다 자기 폴더에 HANDOFF.md를 두는 것을 기본으로 한다.</p>
    </section>

    <!-- 8 -->
    <section id="settings">
      <h2>8. 설정 화면</h2>
      <table class="tbl">
        <thead><tr><th style="width:22%">영역</th><th>할 수 있는 일</th></tr></thead>
        <tbody>
          <tr><td>언어</td><td>한국어 또는 영어를 고른다. 모든 기기에 함께 적용된다.</td></tr>
          <tr><td>작업공간(PC 또는 담당자)</td><td>코드, 이름, 컴퓨터 이름, 색, 용도, 순서 변경. PC 대신 일을 맡은 사람을 작업공간으로 둘 수도 있다. 맨 아래 빈 줄로 추가, "삭제" 체크로 삭제. 삭제하면 그 작업공간의 작업 기록도 함께 지워진다. 코드를 바꾸면 그 PC(또는 담당자)의 자동 기록 설정도 맞춰야 한다.</td></tr>
          <tr><td>도구</td><td>추가, 삭제, 이름·코드 변경. "사용" 중인 도구가 기록 창에 나온다. 작업공간 박스와 프로젝트 목록에는 "아이콘 이름"(12자 이내)으로 표시된다. 비워 두면 이름에 맞춰 Codex, GPT, Claude, Claude C, Gemini, Antigravity 중 하나로 정해지고, 해당이 없으면 표시 이름을 쓴다.</td></tr>
          <tr><td>연결 키</td><td>Apps Script용(구글 연동)과 PC용(PC 작업 기록) 키를 만들고 폐기한다. 키는 만든 직후 한 번만 보인다.</td></tr>
          <tr><td>비밀번호 변경</td><td>바꾸면 다른 기기는 다시 로그인해야 한다.</td></tr>
          <tr><td>수신 기록</td><td>자동 기록과 구글 연동이 보낸 최근 20건. 전송이 의심될 때 먼저 확인한다.</td></tr>
        </tbody>
      </table>
    </section>

    <!-- 9 -->
    <section id="trouble">
      <h2>9. 문제 해결</h2>
      <table class="tbl">
        <thead><tr><th style="width:34%">증상</th><th>확인할 것</th></tr></thead>
        <tbody>
          <tr><td>상단 "Google" 표시가 30분 넘게 그대로</td><td>Apps Script 왼쪽 "실행" 메뉴에서 실패 기록과 오류 메시지 확인</td></tr>
          <tr><td>일정·할 일·메일이 0건</td><td>Apps Script의 실행 계정 확인, <code>diagnoseUDeck</code> 실행</td></tr>
          <tr><td>이번 주·이번 달 탭에 오늘·내일 일정만 나옴</td><td>Apps Script 코드가 옛 버전. 새 코드로 교체</td></tr>
          <tr><td>Claude Code 기록이 안 들어옴</td><td>Claude Code 앱을 다시 시작했는지, <code>status.cmd</code>에서 hook "설치됨"인지</td></tr>
          <tr><td>PC가 "접속 중"으로 안 바뀜</td><td>작업 스케줄러에서 "UDeck Agent"의 마지막 실행 결과가 0x0인지</td></tr>
          <tr><td>연결 실패, HTTP 401</td><td>키가 틀렸거나 폐기됨. 새 키를 만들어 복사한 뒤 install.cmd 다시 실행</td></tr>
          <tr><td>HTTP 403</td><td>키 용도가 다름. 구글 연동과 PC 작업 기록 키는 서로 바꿔 쓸 수 없다</td></tr>
          <tr><td>작업이 "미분류"로 들어옴</td><td>프로젝트의 작업 폴더 이름 확인. 작업공간의 "미분류" 칸 상세에서 프로젝트를 한 번 지정</td></tr>
          <tr><td>HANDOFF.md가 반영 안 됨</td><td>감시 폴더 안에 있는지(<code>watch</code>), 폴더 이름이 프로젝트와 맞는지</td></tr>
          <tr><td>"서버에서 처리하지 못했습니다"</td><td>서버의 <code>udeck_private/data/php_error.log</code> 내용 확인</td></tr>
          <tr><td>로그인이 자꾸 풀림</td><td>로그인할 때 "로그인 유지" 체크. 서버의 <code>udeck_private/sessions</code> 폴더 확인</td></tr>
        </tbody>
      </table>
    </section>

    <!-- 10 -->
    <section id="backup">
      <h2>10. 백업과 보안</h2>
      <ul>
        <li>모든 데이터는 서버의 <code>udeck_private/data/udeck.sqlite</code> 파일 하나에 있다. 이 파일만 내려받으면 전체 백업이 된다.</li>
        <li>코드를 다시 올릴 때 <code>udeck_private/data</code>와 <code>sessions</code> 폴더는 덮어쓰거나 지우지 않는다.</li>
        <li>로그인은 15분 안에 5번 틀리면 15분 동안 잠긴다. "로그인 유지"는 14일이다.</li>
        <li>연결 키는 서버에 암호화된 값(해시)으로만 저장된다. PC를 바꾸거나 잃어버리면 설정에서 그 PC의 키를 폐기한다.</li>
        <li>메일 본문, 소스 코드, 대화 전체는 UDeck에 저장하지 않는다.</li>
      </ul>
      <p class="muted">UDeck <?= e(UDECK_VERSION) ?></p>
    </section>
  </article>
</div>

