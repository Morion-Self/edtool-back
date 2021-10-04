<?php


class Session
{

    function __construct()
    {
        // таймаут активности сессии на сервере и куки
        $timeout = 1209600; // две недели


        if (!session_id()) {

            // время действия куки. 
            // После истечения этого срока кука сама сбросится в браузере пользователя. 
            // Получается, что сейчас у меня нет механизма, который продлевает куку после каждого запроса
            // раньше я думал, что он есть, но на самом деле это была хрень, которая не работала
            // По идее, можно обновлять таймаут куки именно в браузере пользователя, но я пока так не делаю (см код в конце этой функции)
            session_set_cookie_params($timeout);

            // запускаем сессию
            session_start();

            // 03.11.21: Похоже, что эта хрень и не нужна на самом деле
            // Потому что срок действия куки устанавливается в браузере. И если она истечет там, то браузер ее тупо не будет передавать
            // поэтому мне нужно просто продлевать срок действия куки в браузере пользователя, и не юзать какие-то свои локальные переменные
            // но пока код оставлю на всякий случай, вдруг будут косяки

            // если таймаут у сессии прошёл - убиваем её и создаем новую
            // if (isset($_SESSION['eh_discard_after']) && time() > $_SESSION['eh_discard_after']) {
            //     session_unset();
            //     session_destroy();
            //     session_start();
            // }
        }
        // продлеваем срок действия сессии
        // $_SESSION['eh_discard_after'] = time() + $timeout;

        // в теории, время действия куки можно было бы продлить как-то так.
        // но меня смущает использование названия куки (PHPSESSID). Вдруг со временем оно изменится?
        // да и вообще, не хочу вмешиваться в механизм сессии, пусть он сам работает.
        // setcookie("PHPSESSID", session_id(), time() + $timeout);
    }

    private static $ehUserID = 'ehUserID';
    private static $ehUserEmail = 'ehUserEmail';


    static function getUserID()
    {
        if (isset($_SESSION[self::$ehUserID])) {
            return $_SESSION[self::$ehUserID];
        } else {
            return false;
        }
    }

    static function setUserID($id)
    {
        $_SESSION[self::$ehUserID] = $id;
    }


    // static function setUserEmail($email)
    // {
    //     $_SESSION[self::$ehUserEmail] = $email;
    // }


    // static function getUserEmail()
    // {
    //     if (isset($_SESSION[self::$ehUserEmail])) {
    //         return $_SESSION[self::$ehUserEmail];
    //     } else {
    //         return false;
    //     }
    // }
}




// namespace ehSession;

// /**
//  * Cессии.
//  *
//  * При включении этого файла сразу запускается механизм для сессий.
//  * Также доступна функция проверки авторизации
//  */

// // основная функция инициализации механизма сессий
// function start()
// {

//     // if (session_id()) return;

//     // таймаут активности сессии на сервере и куки
//     $timeout = 1209600; // две недели

//     session_set_cookie_params($timeout);    // время действия куки
//     session_start();

//     // если таймаут у сессии прошёл - убиваем её и создаем новую
//     if (isset($_SESSION['eh_discard_after']) && time() > $_SESSION['eh_discard_after']) {
//         session_unset();
//         session_destroy();
//         session_start();
//     }
//     // продлеваем срок действия сессии
//     $_SESSION['eh_discard_after'] = time() + $timeout;
// }


// function getUserID()
// {
//     if (isset($_SESSION['ehUserID'])) {
//         return $_SESSION['ehUserID'];
//     } else {
//         return false;
//     }
// }

// function setUserID($id)
// {
//     $_SESSION['ehUserID'] = $id;
// }


// function setUserEmail($email)
// {
//      $_SESSION['ehUserEmail'] = $email;
// }


// function getUserEmail()
// {
//     if (isset($_SESSION['ehUserEmail'])) {
//         return $_SESSION['ehUserEmail'];
//     } else {
//         return false;
//     }
// }
