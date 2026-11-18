<?php
/**
 * UDeck - 로그아웃 (POST만 허용)
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check_form()) {
    auth_logout();
}
redirect('login.php');
