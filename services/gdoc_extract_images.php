<?php

// TODO: по хорошему тоже нужно сделать корневой и публичный вариант.
// основная логика в корневом сервисе, а в публичном только передача параметров

include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/lib/simple_html_dom.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Stat.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Session.php';

try {
    $session = new Session();
    // Сервис работает только для авторизованных пользователей
    if ($session->getUserID() === false) {
        http_response_code(401);
        return;
    }

    $stat = new Stat();
    $stat->writeRunService(1);

    // Входные параметры
    $documentId = $_GET['documentId'];

    # создаем каталог во временной директории
    // На всякий случай напишу, вдруг пригодится. Временная директория может находиться в разных местах: https://www.the-art-of-web.com/php/where-is-tmp/
    $workDir = sys_get_temp_dir() . "/gdoc_extract_images/" . $session->getUserID() . "/" .  guidv4() . "/";
    mkdir($workDir, 0777, true);

    chdir($workDir); // теперь это будет текущей директорией, чтобы каждый раз не указывать ее

    // Скачиваем гугл-док как архив
    $zipFile = "zip.zip";
    file_put_contents($zipFile, fopen("https://docs.google.com/document/d/$documentId/export?format=zip", 'r'));

    // Распаковываем его
    $zip = new ZipArchive();
    $zip->open($zipFile, ZipArchive::CREATE);
    $unzipDir = "unzip/";
    $zip->extractTo($unzipDir);
    $zip->close();

    // определяем имя HTML-файла
    $htmlFile = null;
    $files = scandir($unzipDir); // список всех файлов в директории
    foreach ($files as $element) {
        if (strpos($element, '.html') !== false) {
            $htmlFile = $unzipDir . $element;
            break;
        }
    }

    // открываем HTML из архива
    $dom = file_get_html($htmlFile);

    // читаем HTML и переименовываем изображения в правильном порядке
    $cnt = 1;
    $dirImages = "images/";
    mkdir($dirImages, 0777, true); // сюда будем складывать картинки в правильном порядке
    foreach ($dom->find('img') as $image) {
        $oldFile = $unzipDir . $image->src;
        $newFile = $dirImages . ($cnt++) . '.png';
        copy($oldFile, $newFile);
    }

    // засовываем изображения в zip-архив
    $zipForDownload = 'images.zip';
    $zip->open($zipForDownload, ZipArchive::CREATE);
    $zip->addGlob($dirImages . "*.png", 0, array('remove_all_path' => TRUE));
    $zip->close();


    // !!! 
    // эта функция должна выполняться последней, т.к. за ней идет exit, и больше в скрипте ничего не отработает
    // !!!
    download_file($zipForDownload);
} catch (Exception $e) {
    http_response_code(400);
    echo $e->getMessage();
}

// // генерация guid4
function guidv4()
{
    $data = openssl_random_pseudo_bytes(16);
    assert(strlen($data) == 16);

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
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
