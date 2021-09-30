<?php

/**
 * Класс для работы с пользователями.
 */

include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/backend/core/Session.php';


class Users
{
    private $session;

    function __construct()
    {
        $this->session = new Session();
    }


    /**
     * Создание нового пользователя.
     *
     * @param string $email
     * @param string $password    - в открытом виде (исходный, что ввел пользователь)
     *
     * @throws Exception текст ошибки
     *
     * @return bool успех операции
     */
    public function create($email, $password)
    {
        // $email = $parms['email'] ?: '';
        // $password = $parms['password'] ?: '';
        try {
            // Проверяем корректность логина, пароля
            // если че-то не так - бросится исключение
            self::isEmailCorrect($email);
            self::isPasswordCorrect($password);

            // Создаем хеш из пароля
            $hash = self::getPasswordHash($password);

            // TODO: premium_until нужно обновлять при первом входе, а не создании
            // а первый вход будет только после валидации емейла
            $s = $GLOBALS['db']->prepare(
                'insert INTO users (email, password, reg_date, premium_until) VALUES(?, ?, now(), adddate(CURRENT_TIMESTAMP, INTERVAL 14 DAY))'
            );
            $s->bind_param('ss', $email, $hash);

            if ($s->execute()) { // todo если так и останется, можно будет просто переделать на !$s->execute()
                self::requestValidateEmail($email);
                // $_SESSION['ehUserID'] = $GLOBALS['db']->insert_id;
                // $_SESSION['ehUserEmail'] = $email;
            } else {
                // $_SESSION = array();
                throw new Exception('Some error while create new user');
            }
            return true;
        } catch (Exception $e) {
            // $_SESSION = array(); // на всякий случай сбросим все переменные в сессии
            throw new Exception($e->getMessage());
        }
        return false;
    }

    /**
     * Возвращает, существует ли польовтель с таким емейлом
     */
    public function isUserExist($email)
    {
        $s = $GLOBALS['db']->prepare(
            'SELECT id
            from users
            where email = ?'
        );
        $s->bind_param('s', $email);

        return ($s->execute() && $s->fetch());
    }

    /**
     * Возвращает, 
     */
    public function isUserValidated($email)
    {
        $s = $GLOBALS['db']->prepare(
            'SELECT id
            from users
            where email = ?
            and is_validated = 1'
        );
        $s->bind_param('s', $email);

        return ($s->execute() && $s->fetch());
    }


    /**
     * Смена пароля.
     *
     * @param string $oldPassword - в открытом виде (исходный, что ввел пользователь)
     * @param string $newPassword - в открытом виде (исходный, что ввел пользователь)
     *
     * @throws Exception текст ошибки
     *
     * @return true в случае успешной смены пароля
     */
    public function changePassword($oldPassword, $newPassword)
    {
        // если че-то не так - бросится исключение
        // в теории, бу га га
        self::isPasswordCorrect($newPassword);

        $sql = 'select password
            from users
            where id = ?';
        $s = $GLOBALS['db']->prepare($sql);
        $s->bind_param('i', $this->userID);

        if ($s->execute()) {
            $s->bind_result($oldHash);
            $s->fetch();
            if (password_verify($oldPassword, $oldHash)) {
                $newHash = self::getPasswordHash($newPassword);
                $sql = 'update users
                        set password = ?
                        where id = ?';
                $s = $GLOBALS['db']->prepare($sql);
                $s->bind_param('si', $newHash, $this->userID);
                $s->execute();
            } else {
                throw new OldPasswordIncorrectException('Неверно указан старый пароль');   //fixme: удалить текст после переделки fronend            
            }
    
        } else {
            return false;
        }

        return true;
    }

    /**
     * Авторизация логину и паролю.
     * если успех - ставятся глобальные переменные в сесии session userid / session email.
     *
     * @param string $email
     * @param string $password - в открытом виде (исходный, что ввел пользователь)
     *
     * @return bool успех операции
     */
    public function auth($email, $password)
    {
        // TODO: тут нужно сделать какую-то проверку на существование пользвоателя
        // иначе сейчас, если юзверя нет, вываливается какая-то ошибка при sql-запросе

        $sql = "select id, password
                from users
                where email = '$email'
                and is_validated = 1";
        $result = $GLOBALS['db']->query($sql);
        $row = $result->fetch_assoc();

        $hash = $row['password'];

        if (password_verify($password, $hash)) {
            $userID = $row['id'];
            // $this->session->setUserEmail($email);
            $this->session->setUserID($userID);

            $sql = 'update users
                    set last_auth = now()
                    where id = ?';
            $s = $GLOBALS['db']->prepare($sql);
            $s->bind_param('i', $userID);
            $s->execute();

            return true;
        } else {
            // $_SESSION = array(); // на всякий случай сбросим переменные в сессии
            return false;
        }
    }


    /**
     * Проверка email на корректность.
     *
     * @param string $email
     *
     * @throws Exception текст ошибок
     *
     * @return true в случае успеха
     */
    private function isEmailCorrect($email)
    {
        // $MAX_SYMBOLS = 30;
        $email = trim($email);

        // TODO
        // тут нужна проверка на дубль емейлов

        return true;
    }

    /**
     * Отправляет письмо для подтверждения емейла
     */
    private function requestValidateEmail($email)
    {
        $s = null;

        $s = $GLOBALS['db']->prepare(
            'SELECT id
                FROM users
                WHERE email = ?
                and is_validated = 0'
        );
        $s->bind_param('s', $email);
        // $userID = $_SESSION['ehUserID'];

        if ($s->execute()) {
            $s->bind_result($userID);
            if ($s->fetch()) {
                // $code = $this->generateValidateEmailCode();
                $token = bin2hex(random_bytes(20)); // символов делает в 2 раза больше, чем указано
                $s->close(); // иначе insert не отработает
                $i = $GLOBALS['db']->prepare(
                    'INSERT INTO validate_email (user_id, token, active_to)
                    VALUES (?, ?, addtime(CURRENT_TIMESTAMP, "0:15:0"))'
                );
                $i->bind_param('is', $userID, $token);
                if ($i->execute()) {
                    $subject = "=?utf-8?B?" . base64_encode("Подтверждение e-mail на сайте edTool.ru") . "?=";
                    $msg = "Вы зарегистрировались на сайте https://edtool.ru<br><br>
                    Для продолжения регистрации перейдите по <a href=\"https://edtool.ru/backend/services/validate_email.php?token=" . $token . "\">этой ссылке</a>.<br>
                    Если это делали не вы, то можете проигнорировать это письмо.<br><br>
                    Ссылка для подтверждения действует 15 минут. Если за это время вы не успеете перейти по ней, то при повторном входе на сайт вы получите новую ссылку.<br><br>
                    <em>Это письмо составлено роботом, и нет смысла на него отвечать.</em>";
                    $headers = "From: edTool Validation <validation@edtool.ru>\r\n";
                    $headers .= "Content-Type: text/html; charset=utf-8\r\n";
                    mail($email, $subject, $msg, $headers);
                } else {
                    return $GLOBALS['db']->error;
                }
            }
        }
        return 'OK';
    }

    /**
     * Валидирует емейл
     */
    public function validateEmail($token)
    {
        $s = null;

        $s = $GLOBALS['db']->prepare(
            'SELECT user_id
                FROM validate_email
                WHERE token = ?
                and CURRENT_TIMESTAMP <= active_to'
        );
        $s->bind_param('s', $token);
        // $userID = $_SESSION['ehUserID'];

        if ($s->execute()) {
            $s->bind_result($userID);
            if ($s->fetch()) {
                $s->close(); // иначе update не отработает
                $i = $GLOBALS['db']->prepare(
                    'update users
                    set is_validated = 1
                    where id = ?'
                );
                $i->bind_param('i', $userID);
                if ($i->execute()) {
                    $this->session->setUserID($userID); // сразу авторизуем пользователя
                    return true;
                } else {
                    throw new Exception($GLOBALS['db']->error);
                }
            } else {
                throw new Exception('token_invalid_or_expired');
            }
        } else {
            throw new Exception($GLOBALS['db']->error);
        }
    }



    // /**
    //  * Запросить изменение пароля.
    //  * На вход принимает либо логин, либо email.
    //  * Если такой пользователь есть в БД, то ему отправляется письмо
    //  * При этом на выход функция всегда отдаёт OK, даже если такого пользователя нет
    //  * Чтобы лишний раз не говорить, нашли мы такого пользователя или нет
    //  *
    //  */
    // public function requestPasswordRecovery($inputEmail, $inputLogin)
    // {
    //     $s = null;
    //     if (!empty($inputEmail)) {
    //         $s = $GLOBALS['db']->prepare(
    //             'SELECT id, email
    //             FROM users
    //             WHERE email = ?'
    //         );
    //         $s->bind_param('s', $inputEmail);
    //     } else if (!empty($inputLogin)) {
    //         $s = $GLOBALS['db']->prepare(
    //             'SELECT id, email
    //             FROM users
    //             WHERE login = ?'
    //         );
    //         $s->bind_param('s', $inputLogin);
    //     } else {
    //         return 'ERROR';
    //     }

    //     if ($s->execute()) {
    //         $s->bind_result($userId, $email);
    //         if ($s->fetch()) {
    //             $token = bin2hex(random_bytes(50));
    //             $s->close(); // иначе insert не отработает
    //             $i = $GLOBALS['db']->prepare(
    //                 'INSERT INTO password_recovery (user_id, token, active_to)
    //                 VALUES (?, ?, addtime(CURRENT_TIMESTAMP, "3:0:0"))
    //                 ON DUPLICATE KEY UPDATE
    //                 token = ?, active_to = addtime(CURRENT_TIMESTAMP, "3:0:0")');
    //             $i->bind_param('iss', $userId, $token, $token);
    //             if ($i->execute()) {
    //                 $subject = "=?utf-8?B?".base64_encode(" Восстановление пароля")."?=";
    //                 $msg = "Здравствуйте.<br> Кто-то (возможно Вы) запросил восстановление пароля.<br>
    //                 Если это делали Вы, то перейдите по <a href=\"https://yelton.ru/password_recovery/#password/" . $token . "\">ссылке</a> для продолжения.<br>
    //                 Если это делали не Вы, то можете проигнорировать это письмо.<br><br>
    //                 Ссылка для восстановления пароля действительна в течение 3 часов.<br><br>
    //                 <em>Данное письмо составлено роботом, и нет смысла на него отвечать.</em>";
    //                 $headers = "From: Yelton Recovery <recovery@yelton.ru>\r\n";
    //                 $headers .= "Content-Type: text/html; charset=utf-8\r\n";
    //                 mail($email, $subject, $msg, $headers);
    //             } else {
    //                 return $GLOBALS['db']->error;
    //             }
    //         }
    //     }
    //     return 'OK';
    // }

    // /**
    //  * Установить новый пароль для пользователя, который ранее запросил сброс пароля
    //  */
    // public function setNewPasswordRecovery($inputToken, $inputPassword)
    // {
    //     $GLOBALS['db']->begin_transaction();
    //     $s = $GLOBALS['db']->prepare(
    //         'SELECT user_id
    //         FROM password_recovery
    //         WHERE token = ?
    //         AND CURRENT_TIMESTAMP <= active_to'
    //     );
    //     $s->bind_param('s', $inputToken);
    //     if ($s->execute()) {
    //         $s->bind_result($userId);
    //         if ($s->fetch()) {
    //             $s->close(); // иначе insert не отработает
    //             self::isPasswordCorrect($inputPassword);
    //             $i = $GLOBALS['db']->prepare(
    //                 'update users
    //                 set password = ?
    //                 where id = ?'
    //             );
    //             $hash = self::getPasswordHash($inputPassword);
    //             $i->bind_param('si', $hash, $userId);
    //             if (!$i->execute()) {
    //                 return 'ERROR'; // хз что произошло
    //             }
    //             $i = $GLOBALS['db']->prepare(
    //                 'DELETE from
    //                 password_recovery
    //                 where user_id = ?
    //                 and token = ?'
    //             );
    //             $i->bind_param('is', $userId, $inputToken);
    //             if (!$i->execute()) {
    //                 return 'ERROR'; // хз что произошло
    //             }
    //         } else {
    //             return 'NO_TOKEN'; // нет токена или он истек
    //         }
    //     } else {
    //         return 'NO_TOKEN';
    //     }
    //     $GLOBALS['db']->commit();
    //     return 'OK';
    // }

    /**
     * Возвращает хэш для пароля
     * @var [type]
     */
    private function getPasswordHash($password)
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Проверка пароля на корректность.
     *
     * @param string $password
     *
     * @throws Exception текст ошибок
     *
     * @return true в случае успеха
     */
    private function isPasswordCorrect($password)
    {
        $MIN_SYMBOLS = 5;
        if (iconv_strlen($password) < $MIN_SYMBOLS) {
            throw new Exception("weak_password");
        }

        // if (!preg_match('/^[0-9A-Za-z_-]+$/u', $password)) {
        //     throw new Exception('Пароль может состоять только из букв английского алфавита, цифр, а также символов подчёркивания и дефиса');
        // }

        return true;
    }

    /**
     * Генерирует код для валидации почты
     * по сути просто рандомная строка из 10 символов
     */
    // private function generateValidateEmailCode()
    // {
    //     $length = 10; // длина кода
    //     $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    //     $charactersLength = strlen($characters);
    //     $randomString = '';
    //     for ($i = 0; $i < $length; $i++) {
    //         $randomString .= $characters[rand(0, $charactersLength - 1)];
    //     }
    //     return $randomString;
    // }


    // /**
    //  * Возвращает инфу о текущем пользователе.
    //  *
    //  * @return $User | false
    //  */
    // public function getInfoAboutUser()
    // {
    //     $userID = $_SESSION ['ehUserID'];
    //     $s = $GLOBALS['db']->prepare(
    //         'select login, email, tutorial_done
    //         from users
    //         where id = ?'
    //     );
    //     $s->bind_param('i', $userID);

    //     $usr = null;
    //     if ($s->execute()) {
    //         $s->bind_result($login, $email, $tutorialDone);
    //         while ($s->fetch()) {
    //             $usr = new stdClass();
    //             $usr->login = $login;
    //             $usr->email = $email;
    //             $usr->tutorialDone = !!$tutorialDone;
    //         }
    //     } else {
    //         return false;
    //     }

    //     return $usr;
    // }

    /**
     * Возвращает дату окончания премиума (UNIX_TIMESTAMP)
     *
     * @return $User | false
     */
    // public function getPremiumUntil()
    // {
    //     // $userID = $_SESSION['ehUserID'];
    //     // обход проблемы 2038
    //     $s = $GLOBALS['db']->prepare(
    //         'select TO_SECONDS(max(date_to))-62167219200+TO_SECONDS(UTC_TIMESTAMP())-TO_SECONDS(NOW())
    //         from premium
    //         where user_id = ?'
    //     );
    //     $s->bind_param('i', $this->session->getUserID());

    //     $out = 1;
    //     if ($s->execute()) {
    //         $s->bind_result($until);
    //         $s->fetch();
    //         $out = $until;
    //     }

    //     return $out;
    // }


    // /**
    //  * Активен ли премиум период
    //  * @return boolean [description]
    //  */
    // public function isPremiumActive()
    // {
    //     $s = $GLOBALS['db']->prepare(
    //         'select now() <= premium_until
    //         from users
    //         where id = ?'
    //     );
    //     $s->bind_param('i', $this->session->getUserID());

    //     $out = 0;
    //     if ($s->execute()) {
    //         $s->bind_result($until);
    //         $s->fetch();
    //         $out = $until;
    //     }

    //     return $out;
    // }
}

/**
 * Выбрасывается при смене пароля.
 * Старый пароль указан неверно
 */
class OldPasswordIncorrectException extends Exception
{
}
