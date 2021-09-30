
-- Кароч, что я думаю на счет таблицы заказов и т.п.
-- Мне кажется, можно обойтись без нее. Я просто в качестве id буду передавать id пользователя
-- возможно, будет плохо, если он будет повторяться... надо посмотреть и попробовать в эквайринге.



CREATE TABLE users (
    id                      BIGINT UNSIGNED     primary key auto_increment,
    email                   char(100)           NOT NULL unique,
    password                char(255)           NOT NULL,                  -- хеш
    last_auth               datetime,                                      -- дата последней авторизации
    reg_date                datetime,                                      -- дата регистрации
    premium_until           datetime,                                      -- до какого времени оплачен премиум. будем просто обвновлять это поле и все.
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


-- create table dict_orders_status ( -- справочник статусов заказов
--     id                      TINYINT unsigned    primary key,
--     name                    char(50)
-- );

-- insert into dict_orders_status (id, name) values
--     (1, 'Создан'),
--     (2, 'Оплачен'),
--     (3, 'Отменен');

-- create table dict_order_type -- справочник типов заказов
-- (
--     id                      SMALLINT unsigned   primary key auto_increment,
--     name                    char(50)            not null,
--     price                   double              not null,
--     months                  TINYINT unsigned    not null,
--     active_from             date                not null DEFAULT '2019-01-01',
--     active_to               date                not null DEFAULT '9999-12-31'
-- );

-- insert into dict_order_type (name, price, months) values
--     ('Месяц', 50, 1),
--     ('Полгода', 250, 6),
--     ('Год', 500, 12);

-- create table orders -- заказы
-- (
--     id                      BIGINT UNSIGNED     primary key auto_increment,
--     user_id                 BIGINT unsigned     not null,
--     -- type_id                 SMALLINT unsigned   not null,

--     CONSTRAINT              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
--     -- CONSTRAINT              FOREIGN KEY (type_id) REFERENCES dict_order_type(id) ON DELETE RESTRICT
-- );

-- create table orders_history -- история заказов
-- (
--     id                      BIGINT UNSIGNED     primary key auto_increment,
--     order_id                BIGINT UNSIGNED     not null,
--     status_id               TINYINT unsigned    not null,
--     `date`                  TIMESTAMP           not null DEFAULT CURRENT_TIMESTAMP,

--     CONSTRAINT              FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
--     CONSTRAINT              FOREIGN KEY (status_id) REFERENCES dict_orders_status(id) ON DELETE RESTRICT
-- );
