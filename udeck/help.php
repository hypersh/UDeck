<?php
/**
 * UDeck - 사용 설명서
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_partials.php';

udeck_db();
require_login_page();

// Apps Script 코드: 비공개 폴더에 있으면 화면에서 바로 복사할 수 있게 보여 준다
$gasFile = UDECK_PRIVATE . '/gas/UDeck_Sync.gs';
$gasCode = is_file($gasFile) ? (string)file_get_contents($gasFile) : '';
$ingestUrl = (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path() . 'ingest.php';

function code(string $text, string $label = ''): string
{
    return '<div class="codebox">' . ($label ? '<span class="codelabel">' . e($label) . '</span>' : '') .
        '<button type="button" class="btn btn-ghost copy">' . te('복사') . '</button><pre><code>' . e($text) . '</code></pre></div>';
}

page_head('UDeck ' . t('도움말'));
?>
<body>
<?php render_topbar('help'); ?>

<?php
$helpFile = __DIR__ . '/_help_' . udeck_lang() . '.php';
require is_file($helpFile) ? $helpFile : __DIR__ . '/_help_ko.php';
?>
<script>
document.querySelectorAll('.codebox .copy').forEach(function (b) {
  b.addEventListener('click', function () {
    var t = b.parentElement.querySelector('code').textContent;
    navigator.clipboard.writeText(t).then(function () {
      var orig = b.textContent;
      b.textContent = <?= json_encode(t('복사했습니다'), JSON_UNESCAPED_UNICODE) ?>;
      setTimeout(function () { b.textContent = orig; }, 1500);
    });
  });
});
</script>
</body>
</html>
