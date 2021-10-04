<?php

include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Session.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Stat.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/lib/simple_html_dom.php';

class GDocExtractImages
{
    private $session;
    private $userID;

    function __construct()
    {
        $this->session = new Session();
        $this->userID = $this->session->getUserID();
    }

    /**
     * Извлекает изображения из гугл-дока
     * Возвращает ссылку на готовый zip-архив, который нужно отдать пользователю
     */
    public function extract($documentId)
    {
        $stat = new Stat();
        $stat->writeRunService(1);

        // создаем каталог во временной директории
        // На всякий случай напишу, вдруг пригодится. Временная директория может находиться в разных местах: https://www.the-art-of-web.com/php/where-is-tmp/
        $workDir = sys_get_temp_dir() . "/gdoc_extract_images/" . $this->userID . "/" . $this->guidv4() . "/";
        mkdir($workDir, 0777, true);

        chdir($workDir); // теперь это будет текущей директорией, чтобы каждый раз не указывать ее

        // Скачиваем гугл-док как архив
        $zipFile = "zip.zip";
        $ch = curl_init("https://docs.google.com/document/d/$documentId/export?format=zip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $html = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        file_put_contents($zipFile, $html);
        $fileType = mime_content_type($zipFile);
        
        if ($httpcode == 404) {
            throw new Exception("NOT_FOUND");
        } else if ($fileType === 'text/html') {
            // нет точной гарантии, что html — это значит нет доступа
            // просто эта ссылка по любому будет с кодом 302 и переадресует на другой юрл: либо для скачивания файла, либо для логина (если нет доступа к файлу)
            // Поэтому оринетрироваться на код ответа не вариант
            // А парсить файл не хочу — формат может поменяться
            //
            // Поэтому сейчас делаю такое допущение: если файл скачался и нет ошибки 404, значит к файлу нет доступа
            throw new Exception("NO_PERMISSIONS");
        }

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
        $cnt = 0;
        $dirImages = "images/";
        mkdir($dirImages, 0777, true); // сюда будем складывать картинки в правильном порядке
        foreach ($dom->find('img') as $image) {
            $oldFile = $unzipDir . $image->src;
            $newFile = $dirImages . (++$cnt) . '.png';
            copy($oldFile, $newFile);
        }

        if ($cnt == 0) {
            throw new Exception('NO_IMAGES');
        }

        // засовываем изображения в zip-архив
        $zipForDownload = 'images.zip';
        $zip->open($zipForDownload, ZipArchive::CREATE);
        $zip->addGlob($dirImages . "*.png", 0, array('remove_all_path' => TRUE));
        $zip->close();

        return $zipForDownload;
    }

    // генерация guid4
    private function guidv4()
    {
        $data = openssl_random_pseudo_bytes(16);
        assert(strlen($data) == 16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
