<?php

include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Users.php';

$email = trim($_POST['email']);
$password = trim($_POST['password']);

$user = new Users();

try {
    if ($user->isUserExist($email)) {
        if ($user->isUserValidated($email)) {
            if ($user->auth($email, $password)) {
                echo 'need_reload';
            } else {
                throw new Exception('wrong_password');
            }
        } else {
            throw new Exception('user_unvalidated');
        }
    } else {
        if ($user->create($email, $password)) {
            echo 'need_validate';
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo $e->getMessage();
}