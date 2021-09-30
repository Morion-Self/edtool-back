<?php

include $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Users.php';

$token = trim($_GET['token']);
$user = new Users();

try {
    if ($user->validateEmail($token)) {
        header('Location: '.'https://'.$_SERVER['SERVER_NAME']);
    }
} catch (Exception $e) {
    if ($e->getMessage() == 'token_invalid_or_expired') {
        echo 'Токен устарел. Зайдите в приложение еще раз, чтобы получить новую ссылку для подтверждения';
    } else {
        http_response_code(400);
    }
}