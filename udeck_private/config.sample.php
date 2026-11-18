<?php
/**
 * UDeck configuration / 설정 파일
 *
 * Copy this file to config.php in the same folder and edit the values.
 * 이 파일을 같은 폴더에 config.php로 복사한 뒤 값을 고친다.
 * Location / 위치: udeck_private/config.php (outside the web root / 웹 루트 밖)
 */
return [
    // One-time key asked on the first password setup screen. Use a long random value.
    // 최초 비밀번호 설정 화면에서 입력하는 1회용 키. 임의의 긴 값으로 바꾼다.
    'setup_key'        => 'CHANGE-ME',

    // Default interface language before one is chosen in Settings: 'en' or 'ko'
    // 설정에서 언어를 고르기 전의 기본 언어
    'language'         => 'en',

    // "Keep me logged in" duration in seconds / 로그인 유지 기간(초)
    'remember_seconds' => 60 * 60 * 24 * 14,

    // Lock login for login_window_min minutes after login_max_fail failures
    // login_window_min 분 동안 login_max_fail 회 실패하면 잠금
    'login_max_fail'   => 5,
    'login_window_min' => 15,

    // Allowed client IPs (CIDR). Empty = no restriction. e.g. ['100.64.0.0/10'] for Tailscale
    // 접속 허용 IP. 비워 두면 제한 없음
    'allowed_ips'      => [],

    // Time zone. Version 1.0 stores and shows all times in Korea Standard Time (UTC+9).
    // 시간대. 1.0은 모든 시각을 한국 표준시(UTC+9)로 저장·표시한다.
    'timezone'         => 'Asia/Seoul',
];
