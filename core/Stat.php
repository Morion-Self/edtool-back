<?php

/**
 * Класс для работы со статистикой.
 */

include_once $_SERVER ['DOCUMENT_ROOT'].'/backend/core/db.php';


class Stat
{
    /**
     * Записывает инфу о запуске сервиса
     */
    public function writeRunService($serviceId)
    {
        try {
            $s = $GLOBALS['db']->prepare(
                'insert INTO services_runs_stat (user_id, service_id) VALUES(?, ?)'
            );
            $s->bind_param('ii', $_SESSION ['ehUserID'], $serviceId);
            $s->execute();
        } catch (Exception $e) {
            $_SESSION = array(); // на всякий случай сбросим все переменные в сессии
            throw new Exception($e->getMessage());
        }
    }
}