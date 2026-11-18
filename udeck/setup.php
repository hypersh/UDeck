<?php
/**
 * UDeck - 최초 비밀번호 설정
 * 비밀번호가 이미 있으면 로그인 화면으로 보낸다.
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_partials.php';

$db = udeck_db();   // 첫 접속 시 DB 생성
if (password_is_set()) {
    redirect(is_logged_in() ? 'index.php' : 'login.php');
}

// 설치 키가 config.php에 지정되어 있어야 비밀번호를 설정할 수 있다 (공개 서버에서 남이 먼저 설정하는 것을 막기 위해)
$setupKey = trim((string)$UDECK_CONFIG['setup_key']);
$keyReady = $setupKey !== '' && $setupKey !== 'CHANGE-ME';
$needKey  = true;
$error = $keyReady ? '' : t('설치 키가 설정되지 않았습니다. udeck_private/config.sample.php를 config.php로 복사하고 setup_key에 임의의 긴 값을 적은 뒤 새로고침하세요.');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $keyReady) {
    $ip = client_ip();
    if (!csrf_check_form()) {
        $error = t('화면이 오래되었습니다. 새로고침한 뒤 다시 시도하세요.');
    } elseif (login_fail_count($ip) >= $UDECK_CONFIG['login_max_fail']) {
        $error = t('시도가 너무 많습니다. {m}분 뒤에 다시 시도하세요.', ['m' => $UDECK_CONFIG['login_window_min']]);
    } elseif (!hash_equals($setupKey, trim((string)($_POST['setup_key'] ?? '')))) {
        login_record($ip, false);
        $error = t('설치 키가 맞지 않습니다. udeck_private/config.php의 setup_key 값을 확인하세요.');
    } elseif ($p = password_problem((string)($_POST['pw1'] ?? ''), (string)($_POST['pw2'] ?? ''))) {
        $error = $p;
    } else {
        auth_set_password((string)$_POST['pw1']);
        auth_login((string)$_POST['pw1'], true);
        redirect('index.php?welcome=1');
    }
}

// 서버 점검
$privWritable = is_writable(UDECK_PRIVATE . '/data');
$checks = [
    ['DB 파일 생성', is_file(UDECK_PRIVATE . '/data/udeck.sqlite'), '완료', '실패'],
    ['데이터 폴더 쓰기 권한', $privWritable, '정상', '권한 없음'],
    ['세션 저장 폴더', is_dir(UDECK_PRIVATE . '/sessions') && is_writable(UDECK_PRIVATE . '/sessions'), '정상', '기본 경로 사용'],
    ['HTTPS 연결', is_https(), '사용 중', '사용 안 함'],
];

page_head('UDeck ' . t('비밀번호 설정'));
?>
<body>
<main class="auth">
  <div class="auth-box">
    <div class="auth-head"><?= logo_mark() ?><span class="wordmark">UDeck</span></div>
    <h1><?= te('비밀번호를 설정하세요') ?></h1>
    <p class="lead"><?= te('처음 한 번만 설정합니다. 이 비밀번호로 집PC, 사무실PC, 노트북, 휴대폰 어디서든 로그인합니다.') ?></p>

    <form class="panel-auth" method="post" autocomplete="off">
      <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <?php if ($needKey): ?>
      <div class="field">
        <label for="setup_key"><?= te('설치 키') ?></label>
        <input class="input" id="setup_key" name="setup_key" required>
        <p class="hint"><?= te('udeck_private/config.php 파일의 setup_key 값입니다. 처음 한 번만 필요합니다.') ?></p>
      </div>
      <?php endif; ?>
      <div class="field">
        <label for="pw1"><?= te('새 비밀번호') ?></label>
        <input class="input" id="pw1" name="pw1" type="password" autocomplete="new-password" minlength="12" required aria-describedby="pw1h">
        <div class="meter" id="meter" data-lv="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
        <p class="hint" id="pw1h"><?= te('12자 이상. 문장처럼 길게 만들면 기억하기 쉽고 안전합니다.') ?></p>
      </div>
      <div class="field">
        <label for="pw2"><?= te('비밀번호 확인') ?></label>
        <input class="input" id="pw2" name="pw2" type="password" autocomplete="new-password" minlength="12" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit"><?= te('비밀번호 저장') ?></button>

      <div class="syscheck">
        <h2><?= te('서버 점검') ?></h2>
        <ul>
          <?php foreach ($checks as [$label, $ok, $y, $n]): ?>
          <li><span><?= te($label) ?></span><span class="<?= $ok ? 'y' : 'n' ?>"><?= te($ok ? $y : $n) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </form>
    <p class="auth-foot"><?= te('비밀번호는 암호화된 값으로만 서버에 저장됩니다.') ?></p>
  </div>
</main>
<script>
document.getElementById('pw1').addEventListener('input', function (e) {
  var v = e.target.value, lv = 0;
  if (v.length >= 8) lv++; if (v.length >= 12) lv++;
  if (/[A-Za-z]/.test(v) && /\d/.test(v)) lv++;
  if (/[^A-Za-z0-9]/.test(v) || v.length >= 20) lv++;
  document.getElementById('meter').dataset.lv = v ? Math.max(1, lv) : 0;
});
</script>
</body>
</html>
