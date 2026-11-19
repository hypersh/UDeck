<?php
/**
 * UDeck - 설정: PC 정보, 도구, 연결 키, 비밀번호, 수신 기록
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_partials.php';

$db = udeck_db();
require_login_page();

function flash(string $msg, bool $ok = true): void
{
    $_SESSION['flash'] = ['msg' => $msg, 'ok' => $ok];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check_form()) {
        flash(t('화면이 오래되었습니다. 다시 시도하세요.'), false);
        redirect('settings.php');
    }
    $act = (string)($_POST['act'] ?? '');

    if ($act === 'lang') {
        $lang = (string)($_POST['lang'] ?? 'ko');
        if (isset(UDECK_LANGS[$lang])) {
            setting_set('lang', $lang);
            // 바뀐 언어로 안내 문구를 보여 주기 위해 사전을 직접 읽는다
            $dict = $lang === 'ko' ? [] : (array)(require UDECK_PRIVATE . '/lang/' . $lang . '.php');
            flash($dict['언어를 바꿨습니다.'] ?? '언어를 바꿨습니다.');
        }
        redirect('settings.php');
    }
    if ($act === 'workspaces' || $act === 'tools') {
        $isWs  = $act === 'workspaces';
        $table = $isWs ? 'workspaces' : 'tools';
        $rows  = (array)($_POST['row'] ?? []);
        $what  = $isWs ? 'PC' : t('도구');
        // 1) 검사: 코드 형식과 중복
        $codes = [];
        $errors = [];
        foreach ($rows as $key => $r) {
            if (!empty($r['delete'])) {
                continue;
            }
            $code = strtolower(trim((string)($r['code'] ?? '')));
            $name = clean($r['name'] ?? null, 30);
            if ($key === 'new' && $code === '' && !$name) {
                continue;               // 빈 추가 줄은 무시
            }
            if (!valid_code($code)) {
                $errors[] = t("{what} 코드 '{code}'는 영문 소문자, 숫자, _, - 로 1~30자여야 합니다.", ['what' => $what, 'code' => $code]);
            } elseif (isset($codes[$code])) {
                $errors[] = t("{what} 코드 '{code}'가 중복되었습니다.", ['what' => $what, 'code' => $code]);
            }
            if (!$name) {
                $errors[] = t('{what} 표시 이름을 비울 수 없습니다.', ['what' => $what]);
            }
            $codes[$code] = true;
        }
        if (!$codes) {
            $errors[] = t('{what}는 하나 이상 있어야 합니다.', ['what' => $what]);
        }
        if ($errors) {
            flash(implode(' ', array_unique($errors)), false);
            redirect('settings.php');
        }
        // 2) 저장: 삭제 → 기존 수정(코드 충돌 방지를 위해 임시 코드 경유) → 추가
        $db->beginTransaction();
        try {
            $del = $db->prepare("DELETE FROM {$table} WHERE id = ?");
            foreach ($rows as $key => $r) {
                if ($key !== 'new' && !empty($r['delete'])) {
                    $del->execute([(int)$key]);
                }
            }
            $tmp = $db->prepare("UPDATE {$table} SET code = '__tmp_' || id WHERE id = ?");
            foreach ($rows as $key => $r) {
                if ($key !== 'new' && empty($r['delete'])) {
                    $tmp->execute([(int)$key]);
                }
            }
            if ($isWs) {
                $up  = $db->prepare('UPDATE workspaces SET code=?, name=?, device_name=?, role_note=?, color=?, sort_order=? WHERE id=?');
                $ins = $db->prepare('INSERT INTO workspaces (code, name, device_name, role_note, color, sort_order) VALUES (?,?,?,?,?,?)');
            } else {
                $up  = $db->prepare('UPDATE tools SET code=?, name=?, short_name=?, kind=?, is_active=?, sort_order=? WHERE id=?');
                $ins = $db->prepare('INSERT INTO tools (code, name, short_name, kind, is_active, sort_order) VALUES (?,?,?,?,?,?)');
            }
            foreach ($rows as $key => $r) {
                if (!empty($r['delete'])) {
                    continue;
                }
                $code = strtolower(trim((string)($r['code'] ?? '')));
                if ($key === 'new' && $code === '') {
                    continue;
                }
                $order = (int)($r['sort_order'] ?? 0);
                if ($isWs) {
                    $color = array_key_exists($r['color'] ?? '', UDECK_COLORS) ? $r['color'] : 'slate';
                    $vals = [$code, clean($r['name'] ?? null, 30), clean($r['device_name'] ?? null, 60),
                             clean($r['role_note'] ?? null, 120), $color, $order];
                } else {
                    $kind = in_array($r['kind'] ?? '', ['desktop', 'web', 'other'], true) ? $r['kind'] : 'desktop';
                    $nm   = clean($r['name'] ?? null, 30);
                    $vals = [$code, $nm, clean($r['short_name'] ?? null, 12) ?? tool_short_default($code, (string)$nm),
                             $kind, !empty($r['active']) ? 1 : 0, $order];
                }
                if ($key === 'new') {
                    $ins->execute($vals);
                } else {
                    $vals[] = (int)$key;
                    $up->execute($vals);
                }
            }
            $db->commit();
            flash(t('{what} 설정을 저장했습니다.', ['what' => $what]));
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[UDeck settings] ' . $e->getMessage());
            flash(t('{what} 설정을 저장하지 못했습니다: ', ['what' => $what]) . $e->getMessage(), false);
        }
    } elseif ($act === 'token_create') {
        $label = clean($_POST['label'] ?? null, 60) ?? t('연결 키');
        $scope = in_array($_POST['scope'] ?? '', ['ingest', 'google'], true) ? $_POST['scope'] : 'ingest';
        $wsId  = $scope === 'ingest' ? int_or_null($_POST['workspace_id'] ?? null) : null;
        if ($wsId && !row_exists('workspaces', $wsId)) {
            $wsId = null;
        }
        $_SESSION['new_token'] = ['label' => $label, 'token' => token_create($label, $scope, $wsId)];
        flash(t('연결 키를 만들었습니다. 지금 복사해 두세요. 이 화면을 벗어나면 다시 볼 수 없습니다.'));
    } elseif ($act === 'token_revoke') {
        $db->prepare('UPDATE api_tokens SET revoked = 1 WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash(t('연결 키를 폐기했습니다. 이 키를 쓰던 곳은 더 이상 기록을 보낼 수 없습니다.'));
    } elseif ($act === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        if (!password_verify($cur, (string)setting_get('password_hash', ''))) {
            flash(t('현재 비밀번호가 맞지 않습니다.'), false);
        } elseif ($p = password_problem((string)($_POST['pw1'] ?? ''), (string)($_POST['pw2'] ?? ''))) {
            flash($p, false);
        } else {
            auth_set_password((string)$_POST['pw1']);
            auth_login((string)$_POST['pw1'], !empty($_SESSION['remember']));
            flash(t('비밀번호를 바꿨습니다. 다른 기기에서는 다시 로그인해야 합니다.'));
        }
    }
    redirect('settings.php');
}

$flash = $_SESSION['flash'] ?? null;
$newToken = $_SESSION['new_token'] ?? null;
unset($_SESSION['flash'], $_SESSION['new_token']);

$workspaces = $db->query('SELECT w.*, (SELECT COUNT(*) FROM activities a WHERE a.workspace_id = w.id) AS n
    FROM workspaces w ORDER BY sort_order, id')->fetchAll();
$tools = $db->query('SELECT t.*, (SELECT COUNT(*) FROM activities a WHERE a.tool_id = t.id) AS n
    FROM tools t ORDER BY sort_order, id')->fetchAll();
$kindName = ['desktop' => t('데스크톱 앱'), 'web' => t('웹 서비스'), 'other' => t('기타')];
$tokens = $db->query('SELECT t.*, w.name AS ws_name FROM api_tokens t
    LEFT JOIN workspaces w ON w.id = t.workspace_id ORDER BY t.revoked, t.id DESC')->fetchAll();
$logs = $db->query('SELECT * FROM sync_log ORDER BY id DESC LIMIT 20')->fetchAll();
$scopeName = ['ingest' => t('PC 작업 기록'), 'google' => t('구글 연동'), 'read' => t('읽기')];

page_head('UDeck ' . t('설정'));
?>
<body>
<?php render_topbar('settings'); ?>

<div class="page">
  <h1><?= te('설정') ?></h1>
  <?php if ($flash): ?><div class="alert <?= $flash['ok'] ? 'ok' : '' ?>" role="status"><?= e($flash['msg']) ?></div><?php endif; ?>

  <?php if ($newToken): ?>
  <section class="sec">
    <div class="sec-h"><h2><?= te('새 연결 키:') ?> <?= e($newToken['label']) ?></h2></div>
    <div class="sec-b">
      <div class="tokenbox" id="newToken"><?= e($newToken['token']) ?></div>
      <button class="btn btn-ghost" type="button" onclick="navigator.clipboard.writeText(document.getElementById('newToken').textContent).then(()=>this.textContent='복사했습니다')"><?= te('키 복사') ?></button>
    </div>
  </section>
  <?php endif; ?>

  <section class="sec" aria-labelledby="h-lang">
    <div class="sec-h"><h2 id="h-lang"><?= te('언어') ?> / Language</h2></div>
    <div class="sec-b">
      <form method="post" class="inline-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="act" value="lang">
        <div class="field">
          <label for="langSel" class="sr"><?= te('언어') ?></label>
          <select class="select" id="langSel" name="lang" style="width:auto">
            <?php foreach (UDECK_LANGS as $code => $label): ?>
              <option value="<?= e($code) ?>" <?= udeck_lang() === $code ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary" type="submit"><?= te('언어 저장') ?></button>
      </form>
      <p class="muted" style="margin-top:8px"><?= te('화면에 쓰는 언어입니다. 모든 기기에 함께 적용됩니다.') ?></p>
    </div>
  </section>

  <section class="sec" aria-labelledby="h-ws">
    <div class="sec-h"><h2 id="h-ws"><?= te('작업공간(PC 또는 담당자)') ?></h2><span class="sub"><?= te('현황판 작업공간의 행이 됩니다') ?></span></div>
    <div class="sec-b">
      <p><?= te('작업공간은 작업이 이루어지는 PC나 그 일을 맡은 사람입니다. 코드는 자동 기록이 PC(또는 담당자)를 구분하는 이름으로, 영문 소문자, 숫자, _, - 만 쓸 수 있습니다. 코드를 바꾸면 그 PC(또는 담당자)의 자동 기록 설정에 적은 값도 함께 바꿔야 합니다.') ?></p>
      <form method="post" class="cfg-form" data-what="PC">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="act" value="workspaces">
        <div style="overflow-x:auto">
        <table class="tbl">
          <thead><tr><th style="width:64px"><?= te('순서') ?></th><th><?= te('코드') ?></th><th><?= te('표시 이름') ?></th><th><?= te('컴퓨터 이름') ?></th><th style="width:110px"><?= te('색') ?></th><th><?= te('용도 메모') ?></th><th style="width:60px"><?= te('삭제') ?></th></tr></thead>
          <tbody>
          <?php foreach ($workspaces as $w): $k = (int)$w['id']; ?>
            <tr>
              <td><input class="input" type="number" name="row[<?= $k ?>][sort_order]" value="<?= (int)$w['sort_order'] ?>"></td>
              <td><input class="input" name="row[<?= $k ?>][code]" value="<?= e($w['code']) ?>" maxlength="30" pattern="[a-z0-9][a-z0-9_\-]{0,29}" required></td>
              <td><input class="input" name="row[<?= $k ?>][name]" value="<?= e($w['name']) ?>" maxlength="30" required></td>
              <td><input class="input" name="row[<?= $k ?>][device_name]" value="<?= e($w['device_name']) ?>" maxlength="60"></td>
              <td><select class="select swatch-select" name="row[<?= $k ?>][color]">
                <?php foreach (UDECK_COLORS as $ck => $cn): ?><option value="<?= $ck ?>" <?= $w['color'] === $ck ? 'selected' : '' ?>><?= te($cn) ?></option><?php endforeach; ?>
              </select></td>
              <td><input class="input" name="row[<?= $k ?>][role_note]" value="<?= e($w['role_note']) ?>" maxlength="120"></td>
              <td><label class="check" title="<?= te('기록 {n}건', ['n' => (int)$w['n']]) ?>"><input type="checkbox" name="row[<?= $k ?>][delete]" value="1" data-count="<?= (int)$w['n'] ?>" data-name="<?= e($w['name']) ?>"></label></td>
            </tr>
          <?php endforeach; ?>
            <tr class="new-row">
              <td><input class="input" type="number" name="row[new][sort_order]" value="<?= count($workspaces) + 1 ?>"></td>
              <td><input class="input" name="row[new][code]" maxlength="30" pattern="[a-z0-9][a-z0-9_\-]{0,29}" placeholder="<?= te('예: lab') ?>"></td>
              <td><input class="input" name="row[new][name]" maxlength="30" placeholder="<?= te('새 PC 또는 담당자') ?>"></td>
              <td><input class="input" name="row[new][device_name]" maxlength="60"></td>
              <td><select class="select" name="row[new][color]">
                <?php $used = array_column($workspaces, 'color'); $next = array_values(array_diff(array_keys(UDECK_COLORS), $used))[0] ?? 'slate';
                foreach (UDECK_COLORS as $ck => $cn): ?><option value="<?= $ck ?>" <?= $ck === $next ? 'selected' : '' ?>><?= te($cn) ?></option><?php endforeach; ?>
              </select></td>
              <td><input class="input" name="row[new][role_note]" maxlength="120"></td>
              <td class="muted"><?= te('추가') ?></td>
            </tr>
          </tbody>
        </table>
        </div>
        <div class="row-actions"><button class="btn btn-primary" type="submit"><?= te('작업공간 저장') ?></button></div>
      </form>
    </div>
  </section>

  <section class="sec" aria-labelledby="h-tools">
    <div class="sec-h"><h2 id="h-tools"><?= te('도구') ?></h2><span class="sub"><?= te('사용 중인 도구가 작업 기록 창에 나옵니다') ?></span></div>
    <div class="sec-b">
      <p><?= te('아이콘 이름은 작업공간 박스와 프로젝트 목록의 도구 아이콘에 보이는 짧은 이름입니다(12자 이내). 비워 두면 이름에 맞춰 Codex, GPT, Claude, Claude C, Gemini, Antigravity 중 하나로 정해집니다. "사용"을 끄면 기록 창에서 숨겨집니다.') ?></p>
      <form method="post" class="cfg-form" data-what="<?= te('도구') ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="act" value="tools">
        <div style="overflow-x:auto">
        <table class="tbl">
          <thead><tr><th style="width:64px"><?= te('순서') ?></th><th><?= te('코드') ?></th><th><?= te('표시 이름') ?></th><th style="width:130px"><?= te('아이콘 이름') ?></th><th style="width:140px"><?= te('종류') ?></th><th style="width:84px"><?= te('사용') ?></th><th style="width:60px"><?= te('삭제') ?></th></tr></thead>
          <tbody>
          <?php foreach ($tools as $t): $k = (int)$t['id']; ?>
            <tr>
              <td><input class="input" type="number" name="row[<?= $k ?>][sort_order]" value="<?= (int)$t['sort_order'] ?>"></td>
              <td><input class="input" name="row[<?= $k ?>][code]" value="<?= e($t['code']) ?>" maxlength="30" pattern="[a-z0-9][a-z0-9_\-]{0,29}" required></td>
              <td><input class="input" name="row[<?= $k ?>][name]" value="<?= e($t['name']) ?>" maxlength="30" required></td>
              <td><input class="input" name="row[<?= $k ?>][short_name]" value="<?= e($t['short_name'] ?? '') ?>" maxlength="12" placeholder="<?= e(tool_short_default($t['code'], $t['name'])) ?>"></td>
              <td><select class="select" name="row[<?= $k ?>][kind]">
                <?php foreach ($kindName as $kk => $kn): ?><option value="<?= $kk ?>" <?= $t['kind'] === $kk ? 'selected' : '' ?>><?= e($kn) ?></option><?php endforeach; ?>
              </select></td>
              <td><label class="check"><input type="checkbox" name="row[<?= $k ?>][active]" value="1" <?= $t['is_active'] ? 'checked' : '' ?>> <?= te('사용') ?></label></td>
              <td><label class="check" title="<?= te('기록 {n}건', ['n' => (int)$t['n']]) ?>"><input type="checkbox" name="row[<?= $k ?>][delete]" value="1" data-count="<?= (int)$t['n'] ?>" data-name="<?= e($t['name']) ?>"></label></td>
            </tr>
          <?php endforeach; ?>
            <tr class="new-row">
              <td><input class="input" type="number" name="row[new][sort_order]" value="<?= count($tools) + 1 ?>"></td>
              <td><input class="input" name="row[new][code]" maxlength="30" pattern="[a-z0-9][a-z0-9_\-]{0,29}" placeholder="<?= te('예: perplexity') ?>"></td>
              <td><input class="input" name="row[new][name]" maxlength="30" placeholder="<?= te('새 도구 이름') ?>"></td>
              <td><input class="input" name="row[new][short_name]" maxlength="12" placeholder="<?= te('자동') ?>"></td>
              <td><select class="select" name="row[new][kind]"><?php foreach ($kindName as $kk => $kn): ?><option value="<?= $kk ?>"><?= e($kn) ?></option><?php endforeach; ?></select></td>
              <td><label class="check"><input type="checkbox" name="row[new][active]" value="1" checked> <?= te('사용') ?></label></td>
              <td class="muted"><?= te('추가') ?></td>
            </tr>
          </tbody>
        </table>
        </div>
        <div class="row-actions"><button class="btn btn-primary" type="submit"><?= te('도구 저장') ?></button></div>
      </form>
    </div>
  </section>

  <section class="sec" aria-labelledby="h-token">
    <div class="sec-h"><h2 id="h-token"><?= te('연결 키') ?></h2><span class="sub"><?= te('PC 자동 기록(3단계)과 구글 연동(2단계)에 씁니다') ?></span></div>
    <div class="sec-b">
      <form method="post" class="inline-form" style="margin-bottom:14px">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="act" value="token_create">
        <div class="field"><label for="tkLabel"><?= te('이름') ?></label><input class="input" id="tkLabel" name="label" placeholder="<?= te('집PC 자동 기록') ?>" maxlength="60" required></div>
        <div class="field"><label for="tkScope"><?= te('용도') ?></label>
          <select class="select" id="tkScope" name="scope"><option value="ingest"><?= te('PC 작업 기록') ?></option><option value="google"><?= te('구글 연동') ?></option></select></div>
        <div class="field"><label for="tkWs">PC</label>
          <select class="select" id="tkWs" name="workspace_id">
            <option value=""><?= te('지정 안 함') ?></option>
            <?php foreach ($workspaces as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?>
          </select></div>
        <button class="btn btn-primary" type="submit"><?= te('연결 키 만들기') ?></button>
      </form>
      <?php if ($tokens): ?>
      <table class="tbl">
        <thead><tr><th><?= te('이름') ?></th><th><?= te('용도') ?></th><th>PC</th><th><?= te('마지막 사용') ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tokens as $t): ?>
          <tr style="<?= $t['revoked'] ? 'opacity:.5' : '' ?>">
            <td><?= e($t['label']) ?></td>
            <td><?= e($scopeName[$t['scope']] ?? $t['scope']) ?></td>
            <td><?= e($t['ws_name'] ?? t('지정 안 함')) ?></td>
            <td><?= e($t['last_used_at'] ?? t('사용 전')) ?></td>
            <td>
              <?php if ($t['revoked']): ?><?= te('폐기됨') ?><?php else: ?>
              <form method="post" style="margin:0" onsubmit="return confirm(<?= e(json_encode(t('이 연결 키를 폐기할까요?'), JSON_UNESCAPED_UNICODE)) ?>)">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="act" value="token_revoke">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button class="btn btn-danger" type="submit" style="height:30px"><?= te('폐기') ?></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><p class="empty"><?= te('아직 만든 연결 키가 없습니다.') ?></p><?php endif; ?>
    </div>
  </section>

  <section class="sec" aria-labelledby="h-pw">
    <div class="sec-h"><h2 id="h-pw"><?= te('비밀번호 변경') ?></h2></div>
    <div class="sec-b">
      <form method="post" style="max-width:360px">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="act" value="password">
        <div class="field"><label for="cur"><?= te('현재 비밀번호') ?></label><input class="input" id="cur" name="current" type="password" autocomplete="current-password" required></div>
        <div class="field"><label for="np1"><?= te('새 비밀번호') ?></label><input class="input" id="np1" name="pw1" type="password" autocomplete="new-password" minlength="12" required></div>
        <div class="field"><label for="np2"><?= te('새 비밀번호 확인') ?></label><input class="input" id="np2" name="pw2" type="password" autocomplete="new-password" minlength="12" required></div>
        <button class="btn btn-primary" type="submit"><?= te('비밀번호 바꾸기') ?></button>
      </form>
    </div>
  </section>

  <section class="sec" aria-labelledby="h-log">
    <div class="sec-h"><h2 id="h-log"><?= te('수신 기록') ?></h2><span class="sub"><?= te('자동 기록과 구글 연동이 보낸 최근 20건') ?></span></div>
    <div class="sec-b">
      <?php if ($logs): ?>
      <table class="tbl">
        <thead><tr><th><?= te('시각') ?></th><th><?= te('보낸 곳') ?></th><th><?= te('결과') ?></th><th><?= te('건수') ?></th><th><?= te('메모') ?></th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
          <tr><td><?= e($l['at']) ?></td><td><?= e($l['source']) ?></td><td><?= $l['ok'] ? te('성공') : te('실패') ?></td><td><?= (int)$l['items'] ?></td><td><?= e($l['message']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><p class="empty"><?= te('아직 받은 기록이 없습니다.') ?></p><?php endif; ?>
      <p style="margin-top:12px;color:var(--muted);font-size:12.5px">UDeck <?= e(UDECK_VERSION) ?>, SQLite <?= e($db->query('select sqlite_version()')->fetchColumn()) ?></p>
    </div>
  </section>
</div>
<script>
// 삭제 표시가 있으면 저장 전에 확인
document.querySelectorAll('.cfg-form').forEach(function (f) {
  f.addEventListener('submit', function (e) {
    var del = Array.prototype.filter.call(f.querySelectorAll('input[name$="[delete]"]'), function (c) { return c.checked; });
    if (!del.length) return;
    var n = del.reduce(function (s, c) { return s + (+c.dataset.count || 0); }, 0);
    var names = del.map(function (c) { return c.dataset.name; }).join(', ');
    var T = <?= json_encode(['del' => t('{what} "{names}"을(를) 삭제합니다.'), 'recs' => t('이 {what}의 작업 기록 {n}건도 함께 삭제됩니다.'), 'go' => t('계속할까요?')], JSON_UNESCAPED_UNICODE) ?>;
    var fill = function (s) { return s.replace('{what}', f.dataset.what).replace('{names}', names).replace('{n}', n); };
    var msg = fill(T.del) + (n ? ' ' + fill(T.recs) : '') + ' ' + T.go;
    if (!confirm(msg)) e.preventDefault();
  });
});
</script>
</body>
</html>
