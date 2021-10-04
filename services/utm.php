<?php

// Проверка авторизации
include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Session.php';
$session = new Session();
if ($session->getUserID() === false) {
    http_response_code(401);
    return;
}

$mode = isset($_POST['mode']) ? $_POST['mode'] : null;
// short
$url = isset($_POST['url']) ? $_POST['url'] : null;
$shorter = isset($_POST['shorter']) ? $_POST['shorter'] : null;
// saveConfig / loadConfig / deleteConfig
$config = isset($_POST['config']) ? $_POST['config'] : null;
$configName = isset($_POST['configName']) ? $_POST['configName'] : null;

include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Utm.php';
$utm = new Utm();

try {
    switch ($mode) {
        case 'short':
            $result = $utm->shortLink($url, $shorter);
            break;
        case 'saveConfig':
            $result = $utm->saveConfig($configName, $config);
            break;
        case 'loadList':
            $result = json_encode($utm->loadConfigList());
            break;
        case 'loadConfig':
            $result = $utm->loadConfig($configName);
            break;
        case 'deleteConfig':
            $result = $utm->deleteConfig($configName);
            break;
    }
    echo $result;
} catch (Exception $e) {
    http_response_code(400);
    echo $e->getMessage();
}
