<?php

// Сервис работает только для авторизованных пользователей
include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Session.php';
$session = new Session();
if ($session->getUserID() === false) {
    http_response_code(401);
    return;
}

// и для оплаченных
include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Pay.php';
$pay = new Pay();
if ($pay->isPremiumActive() == false) {
    http_response_code(402);
    return;
}

include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/GDocExtractImages.php';

try {
    $session = new Session();
    // Сервис работает только для авторизованных пользователей
    if ($session->getUserID() === false) {
        http_response_code(401);
        return;
    }

    $gDoc = new GDocExtractImages();

    // !!! 
    // эта функция должна выполняться последней, т.к. за ней идет exit, и больше в скрипте ничего не отработает
    // !!!

    if (isset($_POST['documentId'])) {
        download_file($gDoc->extract($_POST['documentId']));
    } else {
        throw new Exception('NOT_FOUND');
    }
} catch (Exception $e) {

    /**
     * Кароч, фишка такая.
     * 
     * По дефолту если я из интерфейса запрашиваю тип blob (ну, то есть на скачивание файла), то в интерфейсе не видно текста ответа, если он есть. Его там как-то через жопу надо доставать
     * И получается, что мне нужно либо скачать файл, либо прочитать текст из запроса (я не смог сделать и то и то сразу).
     * Поэтому были разные варианты, типа, либо делать скачивание в два этапа (сначала готовим зип-архив, а потом его скачиваем), либо разводить http-кодами
     * Я решил пойти по 2му варианту.
     * 
     * Поэтому в зависимости от того, какая там будет ошибка (нет прав на файл, в файле нет изображений и т.п.) будут выбрасываться разные http-коды
     * А на клиенте я их просто буду обрабатывать.
     * 
     * Тупо, но пока сойдет.
     * 
     * UPD: А еще прикол в том, что PHP походу не может выдавать все возможные коды. Например, вместо 418 он возвращает 500. Поэтому приходится выбирать из того, что есть
     */
    $RESPONSE_CODE = 400;
    switch ($e->getMessage()) {
        case "NOT_FOUND":
            $RESPONSE_CODE = 404;
            break;
        case "NO_PERMISSIONS":
            $RESPONSE_CODE = 406;
            break;
        case "NO_IMAGES":
            $RESPONSE_CODE = 415;
            break;
    }

    http_response_code($RESPONSE_CODE);
    // это все равно оставим, потому что когда делаешь отладку через бразуер этот текст в конце концов виден и отображается.
    // значит в теории до него все-таки можно нормально достучаться, просто я пока хз как
    echo $e->getMessage();
}

function download_file($file)
{
    if (file_exists($file)) {
        // сбрасываем буфер вывода PHP, чтобы избежать переполнения памяти выделенной под скрипт
        // если этого не сделать файл будет читаться в память полностью!
        if (ob_get_level()) {
            ob_end_clean();
        }
        // заставляем браузер показать окно сохранения файла
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($file));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        // читаем файл и отправляем его пользователю
        readfile($file);
        exit;
    }
}
