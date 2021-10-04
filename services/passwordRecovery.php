<?php

include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Users.php';

$usr = new Users();

if (isset($_POST['passwordReset'])) {
    if (isset($_POST['email'])) {
        echo  $usr->requestPasswordRecovery($_POST['email'], null);
    }
}

if (isset($_POST['passwordNew'])) {
    try {
        echo $usr->setNewPasswordRecovery($_POST['token'], $_POST['password']);
    } catch (Exception $e) {
        echo $e->getMessage();
    }
}