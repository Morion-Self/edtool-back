<?php

// Временный сервис, нужен для запуска оплаты.
// Возвращает дату регистрации пользователя, чтобы определить, нужно показывать ему текст про ввод оплаты или нет.

include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Session.php';

try {
    $session = new Session();
    $userID = $session->getUserID();


    $s = $GLOBALS['db']->prepare(
        'select reg_date
        from users
        where id = ?'
    );
    $s->bind_param('i', $userID);

    if ($s->execute()) {
        $s->bind_result($regDate);
        $s->fetch();
    }

    echo $regDate;
    
} catch (Exception $e) {
    http_response_code(400);
    echo $e->getMessage();
}
