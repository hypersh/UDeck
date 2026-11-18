<?php
/**
 * UDeck - 다국어 (한국어 / English)
 *
 * 화면 문구는 한국어 원문을 그대로 키로 쓴다 (gettext 방식).
 *   t('비밀번호를 설정하세요')           → 영어 설정이면 사전의 영어 문구, 없으면 원문
 *   t('{n}번 더 틀리면', ['n' => 3])    → {이름} 자리에 값을 넣는다
 * 영어 사전: udeck_private/lang/en.php  (원문 => 번역)
 * 언어 설정: settings 테이블의 lang ('ko' | 'en')
 */
declare(strict_types=1);

const UDECK_LANGS = ['ko' => '한국어', 'en' => 'English'];

function udeck_lang(): string
{
    static $lang = null;
    if ($lang === null) {
        try {
            $default = (string)($GLOBALS['UDECK_CONFIG']['language'] ?? 'ko');
            $lang = (string)setting_get('lang', $default);
        } catch (Throwable $e) {
            $lang = 'ko';
        }
        if (!isset(UDECK_LANGS[$lang])) {
            $lang = 'ko';
        }
    }
    return $lang;
}

function udeck_dict(): array
{
    static $dict = null;
    if ($dict === null) {
        $dict = [];
        $lang = udeck_lang();
        if ($lang !== 'ko') {
            $file = UDECK_PRIVATE . '/lang/' . $lang . '.php';
            if (is_file($file)) {
                $dict = (array)require $file;
            }
        }
    }
    return $dict;
}

/** 번역 */
function t(string $ko, array $vars = []): string
{
    // '원문|문맥' 형식: 같은 원문을 쓰임에 따라 다르게 번역할 때. 한국어에서는 | 앞만 보인다
    $s = udeck_dict()[$ko] ?? (str_contains($ko, '|') ? strstr($ko, '|', true) : $ko);
    foreach ($vars as $k => $v) {
        $s = str_replace('{' . $k . '}', (string)$v, $s);
    }
    return $s;
}

/** 번역 + HTML 이스케이프 */
function te(string $ko, array $vars = []): string
{
    return e(t($ko, $vars));
}

/** 화면 스크립트(board.js)용 사전: 영어일 때 사전 전체를 넘긴다 */
function udeck_js_dict(): array
{
    return udeck_dict();
}
