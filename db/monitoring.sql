CREATE DATABASE IF NOT EXISTS monitoring;
USE monitoring;

CREATE TABLE IF NOT EXISTS `data` (
    `node` INT NOT NULL,
    `time` DATETIME NOT NULL,
    `available_memory` FLOAT NOT NULL,
    `available_disk_space` FLOAT NOT NULL,
    `apache_requests` INT NOT NULL,
    `response_time` INT,
    `solr_solr_cpu` FLOAT,
    `solr_solr_mem` FLOAT,
    `solr_cron_cpu` FLOAT,
    `solr_cron_mem` FLOAT,
    `solr_zk_cpu` FLOAT,
    `solr_zk_mem` FLOAT,
    `catalog_catalog_cpu` FLOAT,
    `catalog_catalog_mem` FLOAT,
    `mariadb_galera_cpu` FLOAT,
    `mariadb_galera_mem` FLOAT
);

CREATE INDEX IF NOT EXISTS idx_node ON data(node);
CREATE INDEX IF NOT EXISTS idx_time ON data(time);

GRANT ALL PRIVILEGES ON monitoring.* TO 'monitoring'@'%' identified by '${MARIADB_MONITORING_PASSWORD}';

FLUSH PRIVILEGES;
