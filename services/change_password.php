<?php

try {

    include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Session.php';
    $session = new Session();
    // Сервис работает только для авторизованных пользователей
    if ($session->getUserID() === false) {
        http_response_code(401);
        return;
    }

    include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Users.php';

    $old_password = trim($_POST['old_password']);
    $new_password = trim($_POST['new_password']);

    $user = new Users();
    return $user->changePassword($old_password, $new_password);
} catch (Exception $e) {
    http_response_code(400);
    echo $e->getMessage();
}
