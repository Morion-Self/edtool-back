<?php

// для приема ответов об оплате от тинькофф-банка

include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Pay.php';

try {
    $pay = new Pay();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Если приходит content-type: json, то по дефолту оно не запишется в POST. Нужно читать именно так
        $_POST = json_decode(file_get_contents("php://input"), true);

        // FIXME: в теории, мне надо проверять токен, чтобы кто угодно не слал сюда запросы. 
        // но пока обойдемся и так, по идее этот адрес никто не знает

        if ($_POST['Status'] === 'CONFIRMED') {
            $pay->confirmOrder($_POST['OrderId']);
            echo 'OK';
        } else {
            echo 'OK';
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo $e->getMessage();
}