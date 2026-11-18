<?php
/**
 * UDeck - 로그인
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_partials.php';

udeck_db();
if (!password_is_set()) {
    redirect('setup.php');
}
if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$locked = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check_form()) {
        $error = t('화면이 오래되었습니다. 새로고침한 뒤 다시 시도하세요.');
    } else {
        $r = auth_login((string)($_POST['pw'] ?? ''), !empty($_POST['remember']));
        if ($r['ok']) {
            redirect('index.php');
        }
        $error  = $r['error'];
        $locked = !empty($r['locked']);
    }
}

page_head('UDeck ' . t('로그인'));
?>
<body>
<main class="auth">
  <div class="auth-box">
    <div class="auth-head"><?= logo_mark() ?><span class="wordmark">UDeck</span></div>
    <h1><?= te('다시 오셨습니다') ?></h1>
    <p class="lead"><?= te('세 대의 PC에서 하던 작업이 한 화면에 모여 있습니다.') ?></p>

    <form class="panel-auth" method="post">
      <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="field">
        <label for="pw"><?= te('비밀번호') ?></label>
        <input class="input<?= $error ? ' err' : '' ?>" id="pw" name="pw" type="password" autocomplete="current-password" required autofocus <?= $locked ? 'disabled' : '' ?>>
      </div>
      <label class="check"><input type="checkbox" name="remember" value="1" checked> <?= te('이 기기에서 14일 동안 로그인 유지') ?></label>
      <button class="btn btn-primary btn-block" type="submit" <?= $locked ? 'disabled' : '' ?>><?= te('로그인') ?></button>
    </form>
    <p class="auth-foot"><?= e($_SERVER['HTTP_HOST'] ?? '') . e(base_path()) ?></p>
  </div>
</main>
</body>
</html>
