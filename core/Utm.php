<?php

include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Session.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Stat.php';


class Utm
{
    private $session;
    private $userID;

    function __construct()
    {
        $this->session = new Session();
        $this->userID = $this->session->getUserID();
    }

    public function shortLink($url, $shorter)
    {
        $stat = new Stat();
        $stat->writeRunService(2);

        try {
            switch ($shorter) {
                case 'clck.ru':
                    $out = file_get_contents("https://clck.ru/--?url=" . urlencode($url)); // тут нужно urlencode
                    break;
                case 'is.gd':
                    $out = file_get_contents("https://is.gd/create.php?format=simple&url=" . urlencode($url)); // тут тоже
                    break;
                case 'tinyurl.com':
                    $out = file_get_contents("https://tinyurl.com/api-create.php?url=" . $url); // а тут не нужно 
                    break;
            }
            return $out;
        } catch (Exception $e) {
            return 'Не удалось сократить ссылку';
        }
    }

    public function saveConfig($name, $config)
    {
        $sql = 'INSERT INTO utm_config
                (user_id, name, json_config)
                VALUES(?, ?, ?)
                ON DUPLICATE KEY UPDATE
                json_config = ?';
        $s = $GLOBALS['db']->prepare($sql);
        $s->bind_param('isss', $this->userID, $name, $config, $config);
        $s->execute();

        return true;
    }

    // Возвращает список всех конфигов
    public function loadConfigList()
    {
        $sql = 'select name
            from utm_config
            where user_id = ?';
        $s = $GLOBALS['db']->prepare($sql);
        $s->bind_param('i', $this->userID);

        $out = [];
        if ($s->execute()) {
            $s->bind_result($name);
            while ($s->fetch()) {
                $out[] = $name;
            }
        } else {
            return false;
        }

        return $out;
    }


    public function loadConfig($name)
    {
        $sql = 'select json_config
                from utm_config
                where user_id = ?
                and name = ?';
        $s = $GLOBALS['db']->prepare($sql);
        $s->bind_param('is', $this->userID, $name);

        $out = '';
        if ($s->execute()) {
            $s->bind_result($config);
            while ($s->fetch()) {
                $out = $config;
            }
        } else {
            return false;
        }

        return $out;
    }

    public function deleteConfig($name)
    {
        $sql = 'delete from utm_config
            where user_id = ?
            and name = ? ';
        $s = $GLOBALS['db']->prepare($sql);
        $s->bind_param('is', $this->userID, $name);

        if ($s->execute()) {
            return true;
        } else {
            return false;
        }
    }
}
