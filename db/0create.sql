
CREATE TABLE users (
    id                      BIGINT UNSIGNED     primary key auto_increment,
    email                   char(100)           NOT NULL unique,
    password                char(255)           NOT NULL,                  -- хеш
    premium_until           datetime,                                      -- до какого времени оплачен премиум. будем просто обновлять это поле и все.
    last_auth               datetime,                                      -- дата последней авторизации
    reg_date                datetime,                                      -- дата регистрации
    is_validated            bool                default 0                  -- подтвердил ли пользователь e-mail
);

create table dict_services ( -- справочник возможных сервисов
    id                      TINYINT unsigned    primary key,
    name                    char(50)            not null,
    `desc`                  char(255)
);

insert into dict_services values(1, 'gdoc_extract_img', null);
insert into dict_services values(2, 'utm', null);
insert into dict_services values(3, 'habr_img', null);


create table services_runs_stat ( -- статистика запуска сервисов
    user_id                 BIGINT unsigned,
    service_id              TINYINT unsigned,
    service_type            TINYINT unsigned not null default 1, -- в сервисах могут быть различия, типа разные варианты использования. Пока я хз как это будет выглядеть, пока пусть будет так.
    `date`                  datetime not null   DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT              FOREIGN KEY (service_id) REFERENCES dict_services(id) ON DELETE CASCADE
);

-- для валидации e-mail
create table validate_email
(
    user_id                 BIGINT unsigned     primary key,
    token                   char(50)            not null, -- по идее токен будет 40 символов, но создам поле с запасом
    active_to               TIMESTAMP           not null,

    CONSTRAINT              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);

create table utm_config
(
    user_id                 BIGINT unsigned,
    name                    char(50)            not null,
    json_config             TEXT,

    CONSTRAINT              unique KEY (user_id, name),
    CONSTRAINT              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE

);

-- процедура, которая сначала удаляет просроченные токены, а потом невалидных пользователей
DELIMITER //

CREATE PROCEDURE remove_unvalidated_users ()
    BEGIN
        delete from validate_email
        where CURRENT_TIMESTAMP > active_to;
        
        delete 
        from users
        where is_validated = 0
        and id not in (
            select distinct user_id
            from validate_email
        );
       END//
DELIMITER ;

-- запуск этой процедуры по расписанию раз в 10 минут
CREATE EVENT event_remove_unvalidated_users
    ON SCHEDULE EVERY 10 MINUTE
    DO
      CALL remove_unvalidated_users();


-- заказы
-- я решил пойти по пути минимализма и не хранить дофига всего, чего планировал делать в Yelton.
-- Тут будет только айди пользователя и номер заказа.
-- А та функция, которую будет дергать тинькофф при успешной оплате просто будет по этому айди_заказа находить пользователя и добавлять ему premium_until
-- Потому что историю заказа я всегда смогу посмотреть в тинькове по номеру заказа, там все есть: дата, статус, успех не успех и т.п.
-- Единственное, я все-таки сделаю поле confirmed, чтобы как-нибудь случайно не подтвердился два раза один и тот же заказ

-- id делаю UUID -- строковым, рандомным. 
-- Потому что в тиньке идет сквозная нумерация для тестовых заказов (через тестовый терминал) и боевых.
-- Это значит, что если тестовый заказ с номером 100 оплачен, то на боевом терминале нельзя будет создать заказ 100 -- апи тинька не позволит
-- Поэтому я генерю uuid, чтобы было рандомно.
create table orders 
(
    id                      char(36)            primary key,
    user_id                 BIGINT unsigned     not null,
    confirmed               bool                default 0,

    CONSTRAINT              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);


-- восстановление пароля
create table password_recovery
(
    user_id                 BIGINT unsigned    primary key,
    token                   char(36)           not null,
    active_to               TIMESTAMP          not null,

    CONSTRAINT              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);