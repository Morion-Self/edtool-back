<?php

include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Session.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/tinkoff/TinkoffMerchantAPI.php';

class Pay
{
    function __construct()
    {
        // FIXME: подумай, как это будет работать, когда этот файл мы включаем из файла PayResult? Его отправляет тинькоф, у него не будет userId
        $this->session = new Session();
        $this->userID = $this->session->getUserID();
    }

    // Создать заказ
    // Возвращает url для оплаты
    public function createOrder()
    {
        $amount = 500; // в рублях

        try {
            $api = new TinkoffMerchantAPI();

            // генерим рандомную строку для таблицы orders.
            // Делаю это именно на стороне php, потому что если вставить триггером в MySQL, то потом его нельзя достать
            // в том плане, что функция $GLOBALS['db']->insert_id умеет возвращать только числа (или только автоинкремент)
            $uuid = bin2hex(random_bytes(18)); // 36 символов
            
            // создаем
            $s = $GLOBALS['db']->prepare(
                "INSERT INTO orders
                (id, user_id)
                VALUES (?, ?)"
            );

            $s->bind_param('si', $uuid, $this->userID);
            if (!$s->execute()) {
                throw new Exception('error#1');
            }

            $params = [
                'OrderId' => $uuid,
                'Amount'  => $amount * 100, // нужно умножить на 100, потому что апи принимает в копейках
                'Description' => 'Оплата сервиса edTool.ru на 1 год'
            ];

            $api->init($params);
            return $api->paymentUrl;
        } catch (Exception $e) {
            $GLOBALS['db']->rollback();
            throw new Exception('error#2');
        }
    }

    /**
     * При успешной оплате вызвается эта функция
     * (она дергается тиньковом)
     */
    public function confirmOrder($orderId)
    {
        // По хорошему, тут надо делать проверку есть ли такой заказ (или вдруг он уже оплачен) и в зависимости от этого генерить ошибки
        // но пока вроде можно обойтись без этого
        try {
            $GLOBALS['db']->begin_transaction();

            // Обновляем премиум период. Добавляем год к максимальной дате: либо текущей (если премиум не активен), или к премиумму(если премиум активен и еще осталость несколько дней)
            $s = $GLOBALS['db']->prepare(
                'UPDATE users 
                set premium_until = IF(premium_until < CURRENT_TIMESTAMP, adddate(CURRENT_TIMESTAMP, INTERVAL 1 YEAR), adddate(premium_until, INTERVAL 1 YEAR))
                where id = (
                    select user_id
                    from orders
                    where id = ?
                    and confirmed = 0
                )'
            );
            $s->bind_param('s', $orderId);
            if (!$s->execute()) {
                throw new Exception('error #1');
            }
            $s->close();

            $s = $GLOBALS['db']->prepare(
                'UPDATE orders 
                set confirmed = 1
                where  id = ?'
            );
            $s->bind_param('s', $orderId);
            if (!$s->execute()) {
                throw new Exception('error #2');
            }

            $GLOBALS['db']->commit();
            return true;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return false;
    }

    /**
     * Возвращает дату окончания премиума
     *
     * @return $User | false
     */
    public function getPremiumUntil()
    {
        $s = $GLOBALS['db']->prepare(
            'select premium_until
            from users
            where id = ?'
        );
        $s->bind_param('i', $this->userID);

        $out = 1;
        if ($s->execute()) {
            $s->bind_result($until);
            $s->fetch();
            $out = $until;
        }

        return $out;
    }


    /**
     * Активен ли премиум период
     * @return boolean [description]
     */
    public function isPremiumActive()
    {
        $s = $GLOBALS['db']->prepare(
            'select now() <= premium_until
            from users
            where id = ?'
        );
        $s->bind_param('i', $this->userID);

        $out = 0;
        if ($s->execute()) {
            $s->bind_result($isActive);
            $s->fetch();
            $out = $isActive;
        }
        return $out;
    }
}
