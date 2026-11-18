<?php
/**
 * UDeck - 공통 화면 조각
 */
declare(strict_types=1);

function page_head(string $title): void
{
    ?><!DOCTYPE html>
<html lang="<?= e(udeck_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?></title>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 30"><rect x="0" y="0" width="9" height="9" rx="2" fill="#2A8C80"/><rect x="11" y="0" width="9" height="9" rx="2" fill="#2A8C80" opacity=".35"/><rect x="0" y="10.5" width="9" height="9" rx="2" fill="#4B5CCF" opacity=".35"/><rect x="11" y="10.5" width="9" height="9" rx="2" fill="#4B5CCF"/><rect x="0" y="21" width="9" height="9" rx="2" fill="#B9801C"/><rect x="11" y="21" width="9" height="9" rx="2" fill="#B9801C" opacity=".55"/></svg>') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+KR:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/udeck.css')) ?>">
<script>
// 테마: 저장된 값 → 시스템 설정 순
(function(){try{var t=localStorage.getItem('udeck.theme');if(!t){t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}document.documentElement.dataset.theme=t}catch(e){}})();
</script>
</head>
<?php
}

function logo_mark(): string
{
    return '<span class="mark" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></span>';
}

/** 도움말 버튼 (모든 화면 상단 오른쪽 끝) */
function help_button(bool $current = false): string
{
    return '<a class="btn btn-ghost help-btn' . ($current ? ' current' : '') . '" href="help.php" title="' . te('도움말') . '"' .
        ($current ? ' aria-current="page"' : '') . '><span class="q" aria-hidden="true">?</span><span class="t">' . te('도움말') . '</span></a>';
}

/** 설정·도움말 화면용 간단한 상단 바 */
function render_topbar(string $page): void
{
    ?>
<header class="topbar">
  <a class="brand" href="<?= e(base_path()) ?>"><?= logo_mark() ?><span class="wordmark">UDeck</span></a>
  <div class="actions" style="margin-left:auto">
    <a class="btn btn-ghost" href="index.php"><?= te('보드') ?></a>
    <a class="btn btn-ghost" href="settings.php"<?= $page === 'settings' ? ' aria-current="page"' : '' ?>><?= te('설정') ?></a>
    <form method="post" action="logout.php" style="margin:0">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button class="btn btn-ghost" type="submit"><?= te('로그아웃') ?></button>
    </form>
    <?= help_button($page === 'help') ?>
  </div>
</header>
<?php
}

