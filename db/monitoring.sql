CREATE DATABASE IF NOT EXISTS monitoring;
USE monitoring;

CREATE TABLE IF NOT EXISTS `data` (
    `node` INT NOT NULL,
    `time` DATETIME NOT NULL,
    `available_memory` FLOAT NOT NULL,
    `available_disk_space` FLOAT NOT NULL,
    `apache_requests` INT NOT NULL
);

GRANT ALL PRIVILEGES ON monitoring.* TO 'monitoring'@'%' identified by 'monitoring';

FLUSH PRIVILEGES;
