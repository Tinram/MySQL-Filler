/**
    * Test Types.
    *
    * @author       Martin Latter
    * @copyright    Martin Latter 15/01/2022
    * @version      0.02
    * @license      GNU GPL version 3.0 (GPL v3); http://www.gnu.org/licenses/gpl.html
    * @link         https://github.com/Tinram/MySQL-Filler.git
*/


CREATE DATABASE `types` CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


USE `types`;


CREATE TABLE `t`
(
    `id`          INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `ext_id`      INT UNSIGNED NOT NULL,
    `s_int`       INT SIGNED NOT NULL,

    `b_int_s`     BIGINT SIGNED NOT NULL,
    `b_int_u`     BIGINT UNSIGNED NOT NULL,

    `ttxt`        TINYTEXT NOT NULL,
    `txt`         TEXT NOT NULL,

    `gender`      ENUM('-', 'M', 'F', 'O') NOT NULL DEFAULT '-',

    `response`    SET('y', 'n') NOT NULL,

    `uuid1`       BINARY(16) NOT NULL,
    `uuid2`       CHAR(36) NOT NULL,

    `blb`         BLOB NOT NULL,

    `vb`          VARBINARY(32) NOT NULL,

    `b`           BIT(1) DEFAULT b'0',

    `latitude`    DECIMAL(10, 8) NOT NULL DEFAULT '0.00',
    `longitude`   DECIMAL(11, 8) NOT NULL DEFAULT '0.00',

    `f1`          FLOAT UNSIGNED NOT NULL,
    `f2`          FLOAT SIGNED NOT NULL,
    `f3`          FLOAT(4, 2) NOT NULL DEFAULT 0.00,
    `f4`          FLOAT(5, 2) NOT NULL DEFAULT 0.00,
    `f5`          FLOAT(5, 2) UNSIGNED NOT NULL DEFAULT 0.00,
    `f6`          FLOAT(7, 2) NOT NULL DEFAULT 0.00,
    `f7`          FLOAT(8, 4) NOT NULL DEFAULT 0.00,

    `amount1`     FLOAT(5, 2) NOT NULL DEFAULT 0.00,
    `amount2`     FLOAT(5, 2) UNSIGNED NOT NULL DEFAULT 0.00,

    `d1`          DOUBLE UNSIGNED NOT NULL,
    `d2`          DOUBLE SIGNED NOT NULL,
    `d3`          DOUBLE(14,3) NOT NULL DEFAULT 0.00,

    `j`           JSON NOT NULL,

    `ts1`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ts2`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `ts3`         TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),

    `dt1`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `dt2`         DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

    `d`           DATE NOT NULL,

    `y`           YEAR NOT NULL,

    `t1`          TIME NOT NULL,
    `t2`          TIME(3) NOT NULL,
    `t3`          TIME(6) NOT NULL,

    `boo`         BOOLEAN NOT NULL,

    PRIMARY KEY (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
