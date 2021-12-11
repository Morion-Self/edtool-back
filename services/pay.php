<?php

include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Session.php';

// Проверка авторизации
$session = new Session();
if ($session->getUserID() === false) {
    http_response_code(401);
    return;
}

include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Pay.php';

try {

    $pay = new Pay();

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['GET_PREMIUM_UNTIL'])) {
        echo $pay->getPremiumUntil();
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['GENERATE_LINK_FOR_PAY'])) {
        echo $pay->createOrder();
        return;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo $e->getMessage();
}
