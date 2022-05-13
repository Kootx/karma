CREATE TABLE IF NOT EXISTS `users` (
 `id` INT(11) NOT NULL,
 `username` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
 `email` varchar(100) NOT NULL,
 `confirmed` int(1) DEFAULT 0,
 `checked` int(1) DEFAULT 0,
 `valid` int(1) DEFAULT 0,
 PRIMARY KEY (`id`),
 UNIQUE (`email`(100)),
 KEY `confirmed`(`confirmed`),
 KEY `checked`(`checked`),
 KEY `valid`(`valid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `userId` INT(11) NOT NULL,
    `service` varchar(100) NOT NULL,
    `queued`  BIGINT DEFAULT 0,
    `notified`  BIGINT DEFAULT 0,
    `validts`  BIGINT DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `userId` (`userId`),
    KEY `service_index` (`service`),
    UNIQUE `unique_index`(`userId`, `service`),
    CONSTRAINT `org_relations_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

